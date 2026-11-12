<?php
/**
 * A short walkthrough of the template list, shown again when it grows.
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
 * Until then a card beside the Block Templates menu says there is something to see, on every admin
 * screen but the plugin's own, until it is taken or put off with "Not now". It has the plugin's own
 * look, the banner's dark glow and its Wapuu, so it reads as this plugin's and not as one of core's.
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
	 * The build entries: the walkthrough itself, and the card beside the menu.
	 */
	const ENTRY = 'tour';

	/**
	 * The build entry of the card beside the menu.
	 */
	const POINTER_ENTRY = 'menu-pointer';

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
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'menu_pointer' ] );
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

		// Archive and special templates are for classic themes only; a block theme has its own.
		if ( count( Admin::post_types() ) > 1 ) {
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
	 * Whether the current user manages the templates and has steps they have not seen yet.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
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
	 * Whether the current screen is one of the plugin's own: a template list or editor, or Settings.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return bool
	 */
	private static function is_plugin_screen( $hook_suffix ) {
		$screen = get_current_screen();

		if ( $screen && array_key_exists( (string) $screen->post_type, Admin::post_types() ) ) {
			return true;
		}

		return is_string( $hook_suffix ) && false !== strpos( $hook_suffix, Settings::PAGE );
	}

	/**
	 * Load the walkthrough on the Post Type Templates list, for those who manage the templates.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$screen = get_current_screen();

		if ( ! $screen || self::SCREEN !== $screen->id || ! current_user_can( self::CAPABILITY ) || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.js' ) ) {
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
	 * Core's name for this version's menu card, dismissed per user through core's own
	 * `dismiss-wp-pointer`, so a newer walkthrough brings the card back.
	 *
	 * @return string
	 */
	private static function pointer_name() {
		return 'abet_tour_' . str_replace( '.', '_', self::VERSION );
	}

	/**
	 * The card beside the Block Templates menu, on every other admin screen, until the walkthrough is
	 * taken or put off.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return void
	 */
	public static function menu_pointer( $hook_suffix ) {
		if ( is_network_admin() || self::is_plugin_screen( $hook_suffix ) || ! self::is_pending() || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::POINTER_ENTRY . '.js' ) ) {
			return;
		}

		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		if ( in_array( self::pointer_name(), $dismissed, true ) ) {
			return;
		}

		$asset  = Admin::script_asset( self::POINTER_ENTRY );
		$handle = 'block-editor-templates-' . self::POINTER_ENTRY;
		$first  = '' === self::seen();

		if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::POINTER_ENTRY . '.css' ) ) {
			wp_enqueue_style( $handle, ABET_ASSETS_URL . self::POINTER_ENTRY . '.css', [], $asset['version'] ?? ABET_VERSION );
		}

		wp_enqueue_script( $handle, ABET_ASSETS_URL . self::POINTER_ENTRY . '.js', $asset['dependencies'] ?? [], $asset['version'] ?? ABET_VERSION, true );
		wp_add_inline_script(
			$handle,
			'window.abetMenuPointer = ' . wp_json_encode(
				[
					'name'     => self::pointer_name(),
					'title'    => __( 'Block Editor Templates', 'block-editor-templates' ),
					'text'     => $first
						? _x( 'Let every new post start with the blocks it needs. A short walkthrough shows you around the templates.', 'walkthrough pointer', 'block-editor-templates' )
						: _x( 'Block Editor Templates has changed. A short walkthrough shows what is new.', 'walkthrough pointer', 'block-editor-templates' ),
					'link'     => admin_url( 'edit.php?post_type=block-templates' ),
					'linkText' => _x( 'Take the walkthrough', 'walkthrough pointer', 'block-editor-templates' ),
					'dismiss'  => _x( 'Not now', 'walkthrough pointer', 'block-editor-templates' ),
					'wapuu'    => plugins_url( 'assets/images/wapuu.png', __FILE__ ),
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				],
				JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
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
	 * Forget the walkthrough for every user: the version they saw, and the invite cards they put
	 * off, which core keeps in its own list of dismissed pointers next to those of other plugins.
	 *
	 * User meta is shared by all sites of a network, so this runs once, not per site.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_metadata( 'user', 0, self::USER_META, '', true );

		$user_ids = get_users(
			[
				'blog_id'    => 0,
				'fields'     => 'ID',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Once, on uninstall.
				'meta_query' => [
					[
						'key'     => 'dismissed_wp_pointers',
						'value'   => 'abet_tour_',
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $user_ids as $user_id ) {
			$dismissed = explode( ',', (string) get_user_meta( (int) $user_id, 'dismissed_wp_pointers', true ) );
			$kept      = array_filter(
				$dismissed,
				static function ( $name ) {
					return 0 !== strpos( $name, 'abet_tour_' );
				}
			);

			update_user_meta( (int) $user_id, 'dismissed_wp_pointers', implode( ',', $kept ) );
		}
	}
}
