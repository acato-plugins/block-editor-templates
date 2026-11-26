<?php
/**
 * A short walkthrough of the template list, taken when somebody asks for it.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

/**
 * Walks those who manage the templates through the Post Type Templates list, on WordPress's own
 * admin pointers.
 *
 * The walkthrough has a version of its own, self::VERSION, apart from the plugin's, and every step
 * says in which walkthrough version it was added. Each user's last completed or skipped version is
 * kept in their user meta. Somebody who never took the walkthrough gets all of it; after a release
 * that adds steps (with a newer `since`) and bumps self::VERSION, somebody who took an older one gets
 * only the new steps, so it reads as what changed.
 *
 * It never starts by itself. It is started from the "Start walkthrough" link on the Plugins screen,
 * tagged "New" while this user has steps they have not seen, or from the "Take the walkthrough"
 * button next to the list's title. A site, or the network default, can switch it off in
 * Block Templates > Settings, and code can with the `abet_walkthrough` filter or the
 * ABET_DISABLE_WALKTHROUGH constant; then neither the link nor the button shows.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 * @author     Paul van Impelen <paul@acato.nl>
 */
class Tour {

	/**
	 * The walkthrough's version. Bump it, and give the new steps this as their `since`, when a
	 * release adds steps: everybody who took an older walkthrough is then invited to the new steps.
	 */
	const VERSION = '1.0.0';

	/**
	 * User meta holding the walkthrough version this user last completed or skipped.
	 */
	const USER_META = 'abet_tour';

	/**
	 * AJAX action the script reports a finished walkthrough to.
	 */
	const ACTION = 'abet_tour_seen';

	/**
	 * The capability that manages the templates, as the Block Templates menu asks for.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The screen the walkthrough runs on: the Post Type Templates list, where the menu leads.
	 */
	const SCREEN = 'edit-block-templates';

	/**
	 * The build entry of the walkthrough.
	 */
	const ENTRY = 'tour';

	/**
	 * Query argument on the list's URL that starts the walkthrough.
	 */
	const START_ARG = 'abet-walkthrough';

	/**
	 * The singleton instance of this class.
	 *
	 * @var Tour|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Tour The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Tour();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 */
	private function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( ABET_ABSPATH . 'block-editor-templates.php' ), [ self::class, 'add_action_link' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'tag_style' ] );
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'mark_seen' ] );
	}

	/**
	 * Every step, oldest first.
	 *
	 * A step whose target is not on the screen, or hidden (a column switched off in the settings or
	 * in Screen Options, the submenu of a folded admin menu), is left out. `target` is a list of
	 * selectors, tried in order: the first one that matches something visible is used. `edge` and
	 * `align` place the pointer, as core's wp-pointer takes them.
	 *
	 * @return array<int, array{since: string, target: string, title: string, text: string, edge?: string, align?: string}>
	 */
	public static function steps() {
		$menu  = '#toplevel_page_edit-post_type-block-templates';
		$steps = [
			[
				'since'  => '1.0.0',
				'target' => '#the-list tr:first-child .row-title, .wp-list-table thead .column-title',
				'title'  => _x( 'A template for every post type', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'Every post type that uses the block editor gets a template here by itself. Open one and add the blocks that every new post of that type should start with.', 'walkthrough step', 'block-editor-templates' ),
			],
			[
				'since'  => '1.0.0',
				'target' => '.subsubsub',
				'title'  => _x( 'Published means active', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'A template starts as a draft and is only used once it is published. Switch it back to draft, or move it to the trash, to stop using it.', 'walkthrough step', 'block-editor-templates' ),
			],
			[
				'since'  => '1.0.0',
				'target' => '.wp-list-table thead .column-abet_default_content',
				'title'  => _x( 'Prefill new posts', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'On: new posts start with the template’s blocks and the content entered in them. Off: they get only the empty block structure. Set it per template, in the editor’s sidebar.', 'walkthrough step', 'block-editor-templates' ),
				'align'  => 'center',
			],
			[
				'since'  => '1.0.0',
				'target' => '.wp-list-table thead .column-abet_preview',
				'title'  => _x( 'Preview', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'A thumbnail of each template’s blocks. Select it to open the template in the editor.', 'walkthrough step', 'block-editor-templates' ),
			],
			[
				'since'  => '1.0.0',
				'target' => '.wp-list-table thead .column-abet_modified, .wp-list-table thead .column-date',
				'title'  => _x( 'Who changed what', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'Created and Modified show when and by whom. Select a name to list only the templates that person created or changed; the filters above the list do the same.', 'walkthrough step', 'block-editor-templates' ),
				'align'  => 'right',
			],
		];

		// Archive and special templates are for classic themes only: Admin::post_types() leaves them
		// out under a block theme, which has templates of its own.
		if ( array_key_exists( 'pt-arch-templates', Admin::post_types() ) ) {
			$steps[] = [
				'since'  => '1.0.0',
				'target' => $menu . ' .wp-submenu a[href$="post_type=pt-arch-templates"], ' . $menu,
				'title'  => _x( 'Archives and special pages', 'walkthrough step title', 'block-editor-templates' ),
				'text'   => _x( 'Your theme is a classic theme, so you can also give the archive pages of post types and taxonomies content of their own, and special pages such as the 404 page.', 'walkthrough step', 'block-editor-templates' ),
				'edge'   => 'left',
				'align'  => 'center',
			];
		}

		$steps[] = [
			'since'  => '1.0.0',
			'target' => $menu . ' .wp-submenu a[href$="page=' . Settings::PAGE . '"], ' . $menu,
			'title'  => _x( 'Settings', 'walkthrough step title', 'block-editor-templates' ),
			'text'   => _x( 'Switch the Preview, the author under Created, and the Modified column on or off for the template lists.', 'walkthrough step', 'block-editor-templates' ),
			'edge'   => 'left',
			'align'  => 'center',
		];

		return $steps;
	}

	/**
	 * The version this user last completed or skipped, or '' when they never did.
	 *
	 * @return string
	 */
	public static function seen() {
		return (string) get_user_meta( get_current_user_id(), self::USER_META, true );
	}

	/**
	 * Whether the walkthrough is offered at all on the current site.
	 *
	 * The setting follows the network default like the others, unless the site uses its own. Code has
	 * the last word: the ABET_DISABLE_WALKTHROUGH constant switches it off everywhere, and the
	 * `abet_walkthrough` filter decides per site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( defined( 'ABET_DISABLE_WALKTHROUGH' ) && ABET_DISABLE_WALKTHROUGH ) {
			return false;
		}

		/**
		 * Whether the walkthrough of the template list is offered.
		 *
		 * @param bool $enabled Whether the settings switch it on.
		 */
		return (bool) apply_filters( 'abet_walkthrough', Settings::enabled( 'walkthrough' ) );
	}

	/**
	 * Whether the current user manages the templates and has steps they have not seen yet.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		if ( ! current_user_can( self::CAPABILITY ) || ! self::is_enabled() ) {
			return false;
		}

		$seen = self::seen();

		return version_compare( '' === $seen ? '0' : $seen, self::VERSION, '<' );
	}

	/**
	 * The steps added after a version, or every step when nothing was seen.
	 *
	 * @param string $seen Version last seen, '' for none.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function steps_since( $seen ) {
		if ( '' === $seen ) {
			return self::steps();
		}

		return array_values(
			array_filter(
				self::steps(),
				static function ( $step ) use ( $seen ) {
					return version_compare( $step['since'], $seen, '>' );
				}
			)
		);
	}

	/**
	 * Load the walkthrough on the Post Type Templates list, for those who manage the templates.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$screen = get_current_screen();

		if ( ! $screen || self::SCREEN !== $screen->id || ! current_user_can( self::CAPABILITY ) || ! self::is_enabled() || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.js' ) ) {
			return;
		}

		$asset  = Admin::script_asset( self::ENTRY );
		$handle = 'block-editor-templates-' . self::ENTRY;

		wp_enqueue_style( 'wp-pointer' );
		if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.css' ) ) {
			wp_enqueue_style( $handle, ABET_ASSETS_URL . self::ENTRY . '.css', [ 'wp-pointer' ], $asset['version'] ?? ABET_VERSION );
		}

		wp_enqueue_script( $handle, ABET_ASSETS_URL . self::ENTRY . '.js', array_merge( $asset['dependencies'] ?? [], [ 'wp-pointer' ] ), $asset['version'] ?? ABET_VERSION, true );
		wp_set_script_translations( $handle, 'block-editor-templates' );
		wp_add_inline_script(
			$handle,
			'window.abetTour = ' . wp_json_encode(
				[
					'steps'   => self::steps(),
					'pending' => self::is_pending() ? self::steps_since( self::seen() ) : [],
					'start'   => self::START_ARG,
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => self::ACTION,
					'nonce'   => wp_create_nonce( self::ACTION ),
				],
				JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
	}

	/**
	 * The "Start walkthrough" link on the plugin's row of the Plugins screen, tagged "New" while this
	 * user has steps they have not seen.
	 *
	 * The plugin has no Settings link of its own there, so this one goes first, where such a link
	 * would be.
	 *
	 * @param mixed $links Action links of the plugin's row.
	 *
	 * @return mixed
	 */
	public static function add_action_link( $links ) {
		if ( ! is_array( $links ) || ! current_user_can( self::CAPABILITY ) || ! self::is_enabled() ) {
			return $links;
		}

		$tag = self::is_pending()
			? ' <span class="abet-walkthrough-tag">' . esc_html_x( 'New', 'walkthrough tag on the Plugins screen', 'block-editor-templates' ) . '</span>'
			: '';

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>%s',
				esc_url( add_query_arg( self::START_ARG, '1', admin_url( 'edit.php?post_type=block-templates' ) ) ),
				esc_html_x( 'Start walkthrough', 'plugin action link', 'block-editor-templates' ),
				$tag
			)
		);

		return $links;
	}

	/**
	 * The look of the "New" tag, on the Plugins screen only, while it shows.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return void
	 */
	public static function tag_style( $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix || is_network_admin() || ! self::is_pending() ) {
			return;
		}

		// Shaped like core's update count bubbles, in core's red, white on it.
		wp_register_style( 'block-editor-templates-walkthrough-tag', false, [], ABET_VERSION );
		wp_enqueue_style( 'block-editor-templates-walkthrough-tag' );
		wp_add_inline_style(
			'block-editor-templates-walkthrough-tag',
			'.abet-walkthrough-tag{display:inline-block;margin-inline-start:4px;padding:0 6px;border-radius:9px;background:#d63638;color:#fff;font-size:9px;font-weight:600;line-height:17px;text-transform:uppercase;vertical-align:text-top}@media (forced-colors:active){.abet-walkthrough-tag{border:1px solid CanvasText}}'
		);
	}

	/**
	 * Store that the current user completed or skipped this version.
	 *
	 * @return void
	 */
	public static function mark_seen() {
		check_ajax_referer( self::ACTION );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( null, 403 );
		}

		update_user_meta( get_current_user_id(), self::USER_META, self::VERSION );

		wp_send_json_success( [ 'version' => self::VERSION ] );
	}

	/**
	 * Forget the walkthrough: the version every user saw, and its switch in the settings of every site
	 * and in the network default. The other settings stay.
	 *
	 * User meta is shared by all sites of a network, so that goes once, not per site.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_metadata( 'user', 0, self::USER_META, '', true );

		if ( ! is_multisite() ) {
			self::forget_setting();

			return;
		}

		$network = get_site_option( Settings::OPTION );
		if ( is_array( $network ) && array_key_exists( 'walkthrough', $network ) ) {
			unset( $network['walkthrough'] );
			update_site_option( Settings::OPTION, $network );
		}

		foreach ( get_sites(
			[
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::forget_setting();
			restore_current_blog();
		}
	}

	/**
	 * Remove the walkthrough's switch from the current site's settings.
	 *
	 * @return void
	 */
	private static function forget_setting() {
		$values = get_option( Settings::OPTION );

		if ( is_array( $values ) && array_key_exists( 'walkthrough', $values ) ) {
			unset( $values['walkthrough'] );
			update_option( Settings::OPTION, $values );
		}
	}
}
