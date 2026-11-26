<?php
/**
 * Settings for the template list tables.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

/**
 * Settings for who may create, edit and publish templates, and switches for the extra columns of the
 * template lists and the walkthrough.
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
	 * The REST namespace of the network settings route.
	 */
	const REST_NAMESPACE = 'block-editor-templates/v1';

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
	 * The hook suffix of the network settings page, to load its assets there only.
	 *
	 * @var string|false
	 */
	private static $network_hook_suffix = false;

	/**
	 * True while the network default is stored, see self::update_network_values().
	 *
	 * @var bool
	 */
	private static $saving_network = false;

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
		add_action( 'rest_api_init', [ self::class, 'register_network_route' ] );
	}

	/**
	 * The groups the settings are shown in, with their titles, in display order.
	 *
	 * @return array<string, string>
	 */
	public static function sections() {
		return [
			'access'      => __( 'Access', 'block-editor-templates' ),
			'lists'       => __( 'Template lists', 'block-editor-templates' ),
			'walkthrough' => _x( 'Walkthrough', 'settings section', 'block-editor-templates' ),
		];
	}

	/**
	 * The settings with their labels, descriptions, sections and types, in display order.
	 *
	 * A 'toggle' is on or off. A 'role' holds the slugs of one or more minimum roles, see Capabilities.
	 *
	 * @return array<string, array{label: string, description: string, section: string, type: string}>
	 */
	public static function fields() {
		$fields = [
			'create_role'  => [
				'label'       => __( 'Minimum roles to create templates', 'block-editor-templates' ),
				'description' => __( 'Who may add a template with “Add New”. Also needs the roles to edit templates. For each of these settings, users with one of the chosen roles qualify, and so do users whose role has at least the same WordPress publishing rights as one of them. Administrators always do.', 'block-editor-templates' ),
				'section'     => 'access',
				'type'        => 'role',
			],
			'edit_role'    => [
				'label'       => __( 'Minimum roles to edit templates', 'block-editor-templates' ),
				'description' => __( 'Who sees the Block Templates menu and may edit templates. Changes to a published template are used straight away.', 'block-editor-templates' ),
				'section'     => 'access',
				'type'        => 'role',
			],
			'publish_role' => [
				'label'       => __( 'Minimum roles to publish templates', 'block-editor-templates' ),
				'description' => __( 'Who may publish a template, which puts it to use. Also needs the roles to edit templates.', 'block-editor-templates' ),
				'section'     => 'access',
				'type'        => 'role',
			],
			'delete_role'  => [
				'label'       => __( 'Minimum roles to delete templates', 'block-editor-templates' ),
				'description' => __( 'Who may move templates to the trash and delete them. Also needs the roles to edit templates. A deleted template the plugin made itself is made again, as a draft.', 'block-editor-templates' ),
				'section'     => 'access',
				'type'        => 'role',
			],
			'preview'      => [
				'label'       => __( 'Enable preview', 'block-editor-templates' ),
				'description' => __( 'Show a thumbnail of each template’s blocks in the template lists.', 'block-editor-templates' ),
				'section'     => 'lists',
			],
			'created_by'   => [
				'label'       => __( 'Show the author under “Created”', 'block-editor-templates' ),
				'description' => __( 'Show who created each template under its date, with an “All creators” filter.', 'block-editor-templates' ),
				'section'     => 'lists',
			],
			'modified_by'  => [
				'label'       => __( 'Enable “Modified”', 'block-editor-templates' ),
				'description' => __( 'Show when each template was last modified and by whom, with an “All modifiers” filter.', 'block-editor-templates' ),
				'section'     => 'lists',
			],
			'walkthrough'  => [
				'label'       => _x( 'Offer the walkthrough', 'checkbox label', 'block-editor-templates' ),
				'description' => _x( 'Adds a “Start walkthrough” link to the plugin on the Plugins screen, and a “Take the walkthrough” button to the Post Type Templates list. It never starts by itself.', 'field description', 'block-editor-templates' ),
				'section'     => 'walkthrough',
			],
		];

		foreach ( $fields as $key => $field ) {
			$fields[ $key ] += [ 'type' => 'toggle' ];
		}

		return $fields;
	}

	/**
	 * The values used before anyone saves the settings: every column, and the walkthrough, on, and
	 * Editor as the minimum role for templates.
	 *
	 * @return array<string, bool|string[]>
	 */
	private static function defaults() {
		$defaults = [];
		foreach ( self::fields() as $key => $field ) {
			$defaults[ $key ] = 'role' === $field['type'] ? [ Capabilities::DEFAULT_ROLE ] : true;
		}

		return $defaults;
	}

	/**
	 * Normalize stored or submitted values, falling back to the defaults: a boolean per toggle, and a
	 * list of role slugs per role setting (a single slug, stored by an earlier version, becomes a list
	 * of one). Submitted roles must be roles that can be picked (see Capabilities::role_choices());
	 * stored ones are checked when they are used. A role setting is never empty.
	 *
	 * @param mixed $values    The stored option, or the submitted form values.
	 * @param bool  $submitted True for form input, where an unchecked checkbox is simply absent.
	 * @param bool  $network   True for the network default, whose roles come from every site.
	 *
	 * @return array<string, bool|string[]>
	 */
	private static function normalize( $values, $submitted = false, $network = false ) {
		$values     = is_array( $values ) ? $values : [];
		$normalized = [];

		foreach ( self::defaults() as $key => $default ) {
			if ( is_array( $default ) ) {
				$roles = [];
				foreach ( (array) ( $values[ $key ] ?? [] ) as $role ) {
					$role = is_string( $role ) ? sanitize_key( $role ) : '';
					if ( '' !== $role && ( ! $submitted || Capabilities::is_choice( $role, $network ) ) ) {
						$roles[] = $role;
					}
				}
				$normalized[ $key ] = $roles ? array_values( array_unique( $roles ) ) : $default;
			} elseif ( $submitted ) {
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
	 * @return array<string, bool|string[]>
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
	 * @return array<string, bool|string[]>
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
	 * The minimum roles a role setting holds for the current site.
	 *
	 * @param string $key The setting key: 'create_role', 'edit_role', 'publish_role' or 'delete_role'.
	 *
	 * @return string[] Role slugs. The site may not have all of them, see Capabilities.
	 */
	public static function roles( $key ) {
		$roles = self::values()[ $key ] ?? [];

		return is_array( $roles ) && $roles ? $roles : [ Capabilities::DEFAULT_ROLE ];
	}

	/**
	 * The REST schema of each setting: a boolean per toggle, one of the roles per role setting.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function schema_properties() {
		$properties = [];
		foreach ( self::fields() as $key => $field ) {
			$properties[ $key ] = 'role' === $field['type']
				? [
					'type'     => 'array',
					'minItems' => 1,
					'items'    => [
						'type'    => 'string',
						'pattern' => '^[a-z0-9_-]+$',
					],
				]
				: [ 'type' => 'boolean' ];
		}

		return $properties;
	}

	/**
	 * Register the per-site option with the Settings API.
	 *
	 * @return void
	 */
	public static function register_setting() {
		$properties = self::schema_properties();
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
	 * @return array<string, bool|string[]>
	 */
	public static function sanitize_site_settings( $input ) {
		if ( self::$saving_network ) {
			return $input;
		}

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
		self::$network_hook_suffix = add_submenu_page(
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
									<?php
									if ( self::network_settings_url() ) {
										printf(
											'<p class="description"><a href="%1$s">%2$s</a></p>',
											esc_url( self::network_settings_url() ),
											esc_html__( 'Change the network settings', 'block-editor-templates' )
										);
									}
									?>
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
		$is_network = self::$network_hook_suffix && self::$network_hook_suffix === $hook_suffix;
		if ( ( ! $is_network && ( ! self::$hook_suffix || self::$hook_suffix !== $hook_suffix ) ) || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.js' ) ) {
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
		$sections = [];
		foreach ( self::sections() as $key => $title ) {
			$sections[] = [
				'key'   => $key,
				'title' => $title,
			];
		}
		wp_add_inline_script(
			$handle,
			'window.abetSettings = ' . wp_json_encode(
				[
					'option'        => self::OPTION,
					'sections'      => $sections,
					'fields'        => $fields,
					// The network page edits the network default, through its own REST route.
					'isNetwork'     => (bool) $is_network,
					'restPath'      => $is_network ? '/' . self::REST_NAMESPACE . '/network-settings' : '/wp/v2/settings',
					'values'        => $is_network ? self::network_values() : self::normalize( get_option( self::OPTION, [] ) ),
					'networkValues' => is_multisite() && ! $is_network ? self::network_values() : null,
					'usesNetwork'   => ! $is_network && self::site_uses_network(),
					'isMultisite'   => is_multisite() && ! $is_network,
					'roles'         => Capabilities::role_choices( (bool) $is_network ),
					'roleGroups'    => Capabilities::role_groups(),
					'networkUrl'    => self::network_settings_url(),
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
	 * The network settings page, when the current user may change it.
	 *
	 * The page exists only when the plugin is network-activated, and only a user who may manage the
	 * network's options can open it.
	 *
	 * @return string The URL, or an empty string.
	 */
	public static function network_settings_url() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			return '';
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( ABET_ABSPATH . 'block-editor-templates.php' ) ) ) {
			return '';
		}

		return network_admin_url( 'settings.php?page=' . self::NETWORK_PAGE );
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
			<?php // The settings script replaces this form, as on the site's page; it stays as the fallback without JavaScript. ?>
			<div id="abet-settings-root">
			<p><?php esc_html_e( 'The default for every site in the network. A site administrator can choose to use different settings for their own site.', 'block-editor-templates' ); ?></p>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . self::NETWORK_PAGE ) ); ?>">
				<?php wp_nonce_field( self::NETWORK_PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php self::render_fields( self::network_values(), true ); ?>
				</table>
				<?php submit_button(); ?>
			</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Register the REST route the network settings page saves through.
	 *
	 * /wp/v2/settings only handles the options of a site, not those of the network. The route is for
	 * that page only: see self::can_save_network_settings() for who may use it, and it accepts the
	 * known settings only.
	 *
	 * @return void
	 */
	public static function register_network_route() {
		if ( ! is_multisite() ) {
			return;
		}

		register_rest_route(
			self::REST_NAMESPACE,
			'/network-settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'permission_callback' => [ self::class, 'can_save_network_settings' ],
				'show_in_index'       => false,
				'args'                => [
					self::OPTION => [
						'type'                 => 'object',
						'required'             => true,
						'properties'           => self::schema_properties(),
						'additionalProperties' => false,
					],
				],
				'callback'            => static function ( \WP_REST_Request $request ) {
					$values = self::normalize( $request->get_param( self::OPTION ), true, true );
					self::update_network_values( $values );

					return rest_ensure_response( [ self::OPTION => $values ] );
				},
			]
		);
	}

	/**
	 * Store the network default.
	 *
	 * The option shares its name with the per-site setting, so WordPress runs the site's sanitize
	 * callback on the network value as well. That would check the roles against the current site
	 * only, and add the site's use_network flag, so it is skipped for values normalized already.
	 *
	 * @param array<string, bool|string[]> $values The normalized network values.
	 *
	 * @return void
	 */
	private static function update_network_values( $values ) {
		self::$saving_network = true;
		update_site_option( self::OPTION, $values );
		self::$saving_network = false;
	}

	/**
	 * Whether the current request may save the network settings.
	 *
	 * Only from the network settings page itself: a logged-in user who may manage the network's options
	 * (a super admin), in a browser session that sent a valid REST nonce. WordPress already treats a
	 * cookie without that nonce as logged out; the nonce is checked here as well, so the route never
	 * relies on that alone. Application passwords are refused, so the route cannot be used by a script
	 * from outside, even with a super admin's credentials.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error True when allowed.
	 */
	public static function can_save_network_settings( $request ) {
		$denied = new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to change the network settings.', 'block-editor-templates' ), [ 'status' => rest_authorization_required_code() ] );

		if ( ! is_multisite() || ! is_user_logged_in() || ! current_user_can( 'manage_network_options' ) ) {
			return $denied;
		}

		if ( function_exists( 'rest_get_authenticated_app_password' ) && rest_get_authenticated_app_password() ) {
			return $denied;
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $denied;
		}

		return true;
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to booleans and known roles by normalize().
		$input = isset( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : [];
		self::update_network_values( self::normalize( $input, true, true ) );

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
	 * Render the checkbox rows, one per section.
	 *
	 * @param array<string, bool|string[]> $values  The values to show.
	 * @param bool                         $network True on the network settings page.
	 *
	 * @return void
	 */
	private static function render_fields( $values, $network = false ) {
		foreach ( self::sections() as $section => $title ) {
			self::render_section( $section, $title, $values, $network );
		}
	}

	/**
	 * Render the checkboxes of one section as a row.
	 *
	 * @param string                       $section The section key, see self::sections().
	 * @param string                       $title   The section title.
	 * @param array<string, bool|string[]> $values  The values to show.
	 * @param bool                         $network True on the network settings page.
	 *
	 * @return void
	 */
	private static function render_section( $section, $title, $values, $network = false ) {
		$fields = array_filter(
			self::fields(),
			static function ( $field ) use ( $section ) {
				return $section === $field['section'];
			}
		);
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $title ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php echo esc_html( $title ); ?></legend>
					<?php foreach ( $fields as $key => $field ) : ?>
						<?php $id = 'abet-setting-' . $key; ?>
						<?php
						if ( 'role' === $field['type'] ) {
							self::render_role_field( $id, $key, $field, $values[ $key ] ?? '', $network );
							continue;
						}
						?>
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
	 * Render the checkboxes of a role setting, in a group per kind of role, with its description.
	 *
	 * @param string                $id      The field ID.
	 * @param string                $key     The setting key.
	 * @param array<string, string> $field   The setting, see self::fields().
	 * @param mixed                 $value   The selected roles.
	 * @param bool                  $network True on the network settings page.
	 *
	 * @return void
	 */
	private static function render_role_field( $id, $key, $field, $value, $network = false ) {
		$selected = (array) $value;
		$groups   = [];
		foreach ( Capabilities::role_choices( $network ) as $choice ) {
			$groups[ $choice['group'] ][] = sprintf(
				'<label><input type="checkbox" name="%1$s" value="%2$s"%3$s /> %4$s</label><br />',
				esc_attr( self::OPTION . '[' . $key . '][]' ),
				esc_attr( $choice['value'] ),
				checked( in_array( $choice['value'], $selected, true ), true, false ),
				esc_html( $choice['label'] )
			);
		}

		$boxes = '';
		foreach ( Capabilities::role_groups() as $group => $title ) {
			if ( ! empty( $groups[ $group ] ) ) {
				$boxes .= sprintf( '<fieldset class="abet-role-group"><legend>%1$s</legend>%2$s</fieldset>', esc_html( $title ), implode( '', $groups[ $group ] ) );
			}
		}

		printf(
			'<fieldset id="%1$s" aria-describedby="%1$s-description"><legend><strong>%2$s</strong></legend>%3$s</fieldset><p class="description" id="%1$s-description">%4$s</p>',
			esc_attr( $id ),
			esc_html( $field['label'] ),
			$boxes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while built above.
			esc_html( $field['description'] )
		);
	}

	/**
	 * Summarize settings in one sentence, e.g. for the network default on a site's settings page.
	 *
	 * @param array<string, bool|string[]> $values The values to describe.
	 *
	 * @return string
	 */
	private static function describe( $values ) {
		$parts = [];
		$roles = wp_list_pluck( Capabilities::role_choices( true ), 'label', 'value' );
		foreach ( self::fields() as $key => $field ) {
			if ( 'role' === $field['type'] ) {
				$names = [];
				foreach ( (array) ( $values[ $key ] ?? [] ) as $role ) {
					$names[] = $roles[ $role ] ?? (string) $role;
				}
				$state = implode( ', ', $names );
			} else {
				$state = empty( $values[ $key ] ) ? __( 'off', 'block-editor-templates' ) : __( 'on', 'block-editor-templates' );
			}
			$parts[] = sprintf(
				/* translators: 1: setting label, 2: "on", "off" or a role name. */
				__( '%1$s: %2$s', 'block-editor-templates' ),
				$field['label'],
				$state
			);
		}

		/* translators: %s: list of settings with their state. */
		return sprintf( __( 'Currently: %s.', 'block-editor-templates' ), implode( '; ', $parts ) );
	}
}
