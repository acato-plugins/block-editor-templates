<?php
/**
 * Settings for the template list tables.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

/**
 * Settings that switch the extra columns of the template list tables on or off.
 *
 * On a single site the settings live on Block Templates > Settings. On a multisite network a super
 * admin sets the network default on Network Admin > Settings > Block Editor Templates (only shown when
 * the plugin is network-activated), and each site either follows that default or uses its own values.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */
class Settings {

	/**
	 * The option (per site) and site option (per network) that hold the settings.
	 */
	const OPTION = 'abet_admin_lists';

	/**
	 * The settings group and page slug.
	 */
	const PAGE = 'abet-settings';

	/**
	 * The network settings page slug, also used as the network form action.
	 */
	const NETWORK_PAGE = 'abet-network-settings';

	/**
	 * The script and style handle, and the build entry name, of the settings page.
	 */
	const ENTRY = 'settings';

	/**
	 * The singleton instance of this class.
	 *
	 * @var Settings|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * The hook suffix of the per-site settings page, to load its assets there only.
	 *
	 * @var string|false
	 */
	private static $hook_suffix = false;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Settings The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Settings();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 */
	private function __construct() {
		// The settings page saves through the REST API, where admin_init does not run.
		add_action( 'admin_init', [ self::class, 'register_setting' ] );
		add_action( 'rest_api_init', [ self::class, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		// After Admin::admin_menu() (priority 10) has added the Block Templates parent menu.
		add_action( 'admin_menu', [ self::class, 'admin_menu' ], 20 );
		add_action( 'network_admin_menu', [ self::class, 'network_admin_menu' ] );
		add_action( 'network_admin_edit_' . self::NETWORK_PAGE, [ self::class, 'save_network_settings' ] );
	}

	/**
	 * The settings with their labels and descriptions, in display order.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function fields() {
		return [
			'preview'     => [
				'label'       => __( 'Enable preview', 'block-editor-templates' ),
				'description' => __( 'Show a thumbnail of each template’s blocks in the template lists.', 'block-editor-templates' ),
			],
			'created_by'  => [
				'label'       => __( 'Show the author under “Created”', 'block-editor-templates' ),
				'description' => __( 'Show who created each template under its date, with an “All creators” filter.', 'block-editor-templates' ),
			],
			'modified_by' => [
				'label'       => __( 'Enable “Modified”', 'block-editor-templates' ),
				'description' => __( 'Show when each template was last modified and by whom, with an “All modifiers” filter.', 'block-editor-templates' ),
			],
		];
	}

	/**
	 * The values used before anyone saves the settings: every column on.
	 *
	 * @return array<string, bool>
	 */
	private static function defaults() {
		return array_fill_keys( array_keys( self::fields() ), true );
	}

	/**
	 * Normalize stored or submitted values to a boolean per setting, falling back to the defaults.
	 *
	 * @param mixed $values The stored option, or the submitted form values.
	 * @param bool  $submitted True for form input, where an unchecked checkbox is simply absent.
	 *
	 * @return array<string, bool>
	 */
	private static function normalize( $values, $submitted = false ) {
		$values     = is_array( $values ) ? $values : [];
		$normalized = [];

		foreach ( self::defaults() as $key => $default ) {
			if ( $submitted ) {
				$normalized[ $key ] = ! empty( $values[ $key ] );
			} else {
				$normalized[ $key ] = array_key_exists( $key, $values ) ? (bool) $values[ $key ] : $default;
			}
		}

		return $normalized;
	}

	/**
	 * The network default (multisite only).
	 *
	 * @return array<string, bool>
	 */
	public static function network_values() {
		return self::normalize( get_site_option( self::OPTION, [] ) );
	}

	/**
	 * Whether the current site follows the network default (always false on a single site).
	 *
	 * @return bool
	 */
	public static function site_uses_network() {
		if ( ! is_multisite() ) {
			return false;
		}

		$site = get_option( self::OPTION, [] );

		return ! is_array( $site ) || ! array_key_exists( 'use_network', $site ) || ! empty( $site['use_network'] );
	}

	/**
	 * The settings in effect for the current site.
	 *
	 * @return array<string, bool>
	 */
	public static function values() {
		if ( self::site_uses_network() ) {
			return self::network_values();
		}

		return self::normalize( get_option( self::OPTION, [] ) );
	}

	/**
	 * Whether one setting is on for the current site.
	 *
	 * @param string $key The setting key, see self::fields().
	 *
	 * @return bool
	 */
	public static function enabled( $key ) {
		return ! empty( self::values()[ $key ] );
	}

	/**
	 * Register the per-site option with the Settings API.
	 *
	 * @return void
	 */
	public static function register_setting() {
		$properties = array_fill_keys( array_keys( self::fields() ), [ 'type' => 'boolean' ] );
		if ( is_multisite() ) {
			$properties['use_network'] = [ 'type' => 'boolean' ];
		}

		register_setting(
			self::PAGE,
			self::OPTION,
			[
				'type'              => 'object',
				'sanitize_callback' => [ self::class, 'sanitize_site_settings' ],
				// Saved by the settings page through /wp/v2/settings, which requires manage_options.
				'show_in_rest'      => [
					'schema' => [
						'type'                 => 'object',
						'properties'           => $properties,
						'additionalProperties' => false,
					],
				],
			]
		);
	}

	/**
	 * Sanitize the per-site settings, from the REST API or the no-JavaScript form.
	 *
	 * @param mixed $input The submitted values: booleans from the REST API, or the form's checkboxes
	 *                     and its 'source' radio ('network' or 'site').
	 *
	 * @return array<string, bool>
	 */
	public static function sanitize_site_settings( $input ) {
		$sanitized = self::normalize( $input, true );

		if ( is_multisite() ) {
			if ( is_array( $input ) && array_key_exists( 'use_network', $input ) ) {
				$sanitized['use_network'] = (bool) $input['use_network'];
			} else {
				$sanitized['use_network'] = is_array( $input ) && isset( $input['source'] ) && 'network' === $input['source'];
			}
		}

		return $sanitized;
	}

	/**
	 * Add Block Templates > Settings.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		self::$hook_suffix = add_submenu_page(
			'edit.php?post_type=block-templates',
			__( 'Block Templates settings', 'block-editor-templates' ),
			__( 'Settings', 'block-editor-templates' ),
			'manage_options',
			self::PAGE,
			[ self::class, 'render_site_page' ]
		);
	}

	/**
	 * Add Network Admin > Settings > Block Editor Templates.
	 *
	 * @return void
	 */
	public static function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Block Editor Templates', 'block-editor-templates' ),
			__( 'Block Editor Templates', 'block-editor-templates' ),
			'manage_network_options',
			self::NETWORK_PAGE,
			[ self::class, 'render_network_page' ]
		);
	}

	/**
	 * Render the per-site settings page.
	 *
	 * @return void
	 */
	public static function render_site_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$uses_network = self::site_uses_network();
		$values       = is_multisite() ? self::normalize( get_option( self::OPTION, [] ) ) : self::values();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php // The settings script replaces this form; it stays as the fallback without JavaScript. ?>
			<div id="abet-settings-root">
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php if ( is_multisite() ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Settings for this site', 'block-editor-templates' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Settings for this site', 'block-editor-templates' ); ?></legend>
									<label>
										<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[source]" value="network" <?php checked( $uses_network ); ?> />
										<?php esc_html_e( 'Use the network settings', 'block-editor-templates' ); ?>
									</label>
									<p class="description"><?php echo esc_html( self::describe( self::network_values() ) ); ?></p>
									<br />
									<label>
										<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[source]" value="site" <?php checked( ! $uses_network ); ?> />
										<?php esc_html_e( 'Use the settings below for this site', 'block-editor-templates' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					<?php endif; ?>
					<?php self::render_fields( $values ); ?>
				</table>
				<?php submit_button(); ?>
			</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Load the settings page script, with the settings and their labels.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! self::$hook_suffix || self::$hook_suffix !== $hook_suffix || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.js' ) ) {
			return;
		}

		$asset  = Admin::script_asset( self::ENTRY );
		$handle = 'block-editor-templates-' . self::ENTRY;

		wp_enqueue_script( $handle, ABET_ASSETS_URL . self::ENTRY . '.js', $asset['dependencies'] ?? [], $asset['version'] ?? ABET_VERSION, true );
		wp_set_script_translations( $handle, 'block-editor-templates' );

		$fields = [];
		foreach ( self::fields() as $key => $field ) {
			$fields[] = [ 'key' => $key ] + $field;
		}
		wp_add_inline_script(
			$handle,
			'window.abetSettings = ' . wp_json_encode(
				[
					'option'        => self::OPTION,
					'fields'        => $fields,
					'values'        => self::normalize( get_option( self::OPTION, [] ) ),
					'networkValues' => is_multisite() ? self::network_values() : null,
					'usesNetwork'   => self::site_uses_network(),
					'isMultisite'   => is_multisite(),
				],
				JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);

		wp_enqueue_style( 'wp-components' );
		if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.css' ) ) {
			wp_enqueue_style( $handle, ABET_ASSETS_URL . self::ENTRY . '.css', [ 'wp-components' ], $asset['version'] ?? ABET_VERSION );
		}
	}

	/**
	 * Render the network settings page.
	 *
	 * @return void
	 */
	public static function render_network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the redirect flag to show a notice.
		$updated = isset( $_GET['updated'] );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'block-editor-templates' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'The default for every site in the network. A site administrator can choose to use different settings for their own site.', 'block-editor-templates' ); ?></p>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . self::NETWORK_PAGE ) ); ?>">
				<?php wp_nonce_field( self::NETWORK_PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php self::render_fields( self::network_values() ); ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save the network settings form.
	 *
	 * @return void
	 */
	public static function save_network_settings() {
		check_admin_referer( self::NETWORK_PAGE );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this network.', 'block-editor-templates' ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to booleans by normalize().
		$input = isset( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : [];
		update_site_option( self::OPTION, self::normalize( $input, true ) );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => self::NETWORK_PAGE,
					'updated' => 'true',
				],
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Render the checkbox rows.
	 *
	 * @param array<string, bool> $values The values to show as checked.
	 *
	 * @return void
	 */
	private static function render_fields( $values ) {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Template lists', 'block-editor-templates' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Template lists', 'block-editor-templates' ); ?></legend>
					<?php foreach ( self::fields() as $key => $field ) : ?>
						<?php $id = 'abet-setting-' . $key; ?>
						<p>
							<label for="<?php echo esc_attr( $id ); ?>">
								<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="1" aria-describedby="<?php echo esc_attr( $id . '-description' ); ?>" <?php checked( ! empty( $values[ $key ] ) ); ?> />
								<?php echo esc_html( $field['label'] ); ?>
							</label>
						</p>
						<p class="description" id="<?php echo esc_attr( $id . '-description' ); ?>"><?php echo esc_html( $field['description'] ); ?></p>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Summarize settings in one sentence, e.g. for the network default on a site's settings page.
	 *
	 * @param array<string, bool> $values The values to describe.
	 *
	 * @return string
	 */
	private static function describe( $values ) {
		$parts = [];
		foreach ( self::fields() as $key => $field ) {
			$parts[] = sprintf(
				/* translators: 1: setting label, 2: "on" or "off". */
				__( '%1$s: %2$s', 'block-editor-templates' ),
				$field['label'],
				empty( $values[ $key ] ) ? __( 'off', 'block-editor-templates' ) : __( 'on', 'block-editor-templates' )
			);
		}

		/* translators: %s: list of settings with their state. */
		return sprintf( __( 'Currently: %s.', 'block-editor-templates' ), implode( '; ', $parts ) );
	}
}
