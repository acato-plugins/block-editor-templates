<?php
/**
 * Extra columns for the template list tables.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

use WP_Post;
use WP_Screen;

/**
 * Adds the Preview, Created by and Last edited by columns to the template list tables.
 *
 * Each column can be switched off in the settings (see Settings). The preview renders the
 * template's blocks with the block editor's own BlockPreview, so this screen loads the editor
 * scripts of every registered block - the same way the Customizer's widget editor does.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */
class List_Table {

	/**
	 * The script and style handle, and the build entry name, of the preview.
	 */
	const HANDLE = 'block-editor-templates-list-preview';

	/**
	 * The build entry of the preview.
	 */
	const ENTRY = 'list-preview';

	/**
	 * The singleton instance of this class.
	 *
	 * @var List_Table|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return List_Table The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new List_Table();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 */
	private function __construct() {
		add_action( 'current_screen', [ self::class, 'setup_screen' ] );
	}

	/**
	 * Hook the columns and assets in when the current screen is one of the template lists.
	 *
	 * @param WP_Screen $screen The current screen.
	 *
	 * @return void
	 */
	public static function setup_screen( $screen ) {
		if ( 'edit' !== $screen->base || ! array_key_exists( $screen->post_type, Admin::post_types() ) ) {
			return;
		}

		add_filter( 'manage_' . $screen->post_type . '_posts_columns', [ self::class, 'add_columns' ], 20 );
		add_action( 'manage_' . $screen->post_type . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );

		if ( self::preview_enabled() ) {
			add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_preview_assets' ] );
		}
	}

	/**
	 * Whether the preview is switched on and can run here.
	 *
	 * BlockPreview needs the block editor settings API of WordPress 5.8+ and a build of the script.
	 *
	 * @return bool
	 */
	private static function preview_enabled() {
		return Settings::enabled( 'preview' )
			&& function_exists( 'get_block_editor_settings' )
			&& file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.js' );
	}

	/**
	 * Add the enabled columns: the preview after the title, the people before the date.
	 *
	 * @param array<string, string> $columns The existing list-table columns.
	 *
	 * @return array<string, string>
	 */
	public static function add_columns( $columns ) {
		$result = [];
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$result = array_merge( $result, self::people_columns() );
			}

			$result[ $key ] = $label;

			if ( 'title' === $key && self::preview_enabled() ) {
				$result['abet_preview'] = __( 'Preview', 'block-editor-templates' );
			}
		}

		// No date column (removed by another plugin): add the people columns at the end.
		return $result + self::people_columns();
	}

	/**
	 * The enabled Created by / Last edited by columns.
	 *
	 * @return array<string, string>
	 */
	private static function people_columns() {
		$columns = [];
		if ( Settings::enabled( 'created_by' ) ) {
			$columns['abet_created_by'] = __( 'Created by', 'block-editor-templates' );
		}
		if ( Settings::enabled( 'last_edited_by' ) ) {
			$columns['abet_last_edited_by'] = __( 'Last edited by', 'block-editor-templates' );
		}

		return $columns;
	}

	/**
	 * Render one of our columns for a row.
	 *
	 * @param string $column  The current column key.
	 * @param int    $post_id The current post ID.
	 *
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		switch ( $column ) {
			case 'abet_preview':
				self::render_preview( $post );
				break;
			case 'abet_created_by':
				self::prime_users();
				self::render_user( (int) $post->post_author, $post );
				break;
			case 'abet_last_edited_by':
				self::prime_users();
				self::render_user( (int) get_post_meta( $post->ID, '_edit_last', true ), $post );
				if ( (int) get_post_meta( $post->ID, '_edit_last', true ) ) {
					printf(
						'<br /><span class="abet-list-meta">%s</span>',
						esc_html(
							sprintf(
								/* translators: 1: date, 2: time. */
								__( '%1$s at %2$s', 'block-editor-templates' ),
								get_the_modified_date( '', $post ),
								get_the_modified_time( '', $post )
							)
						)
					);
				}
				break;
		}
	}

	/**
	 * Render the preview cell: the stored block markup, which the script renders with BlockPreview.
	 *
	 * The cell links to the editor like the title does. It is hidden from assistive technology and
	 * left out of the tab order, because the title link right next to it already does the same.
	 *
	 * @param WP_Post $post The template post.
	 *
	 * @return void
	 */
	private static function render_preview( $post ) {
		if ( '' === trim( $post->post_content ) ) {
			printf(
				'<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
				esc_html__( 'No blocks yet', 'block-editor-templates' )
			);

			return;
		}

		$edit_link = get_edit_post_link( $post->ID );

		printf(
			'<a class="abet-template-preview" href="%1$s" tabindex="-1" aria-hidden="true" data-content="%2$s"></a>',
			esc_url( (string) $edit_link ),
			esc_attr( $post->post_content )
		);
	}

	/**
	 * Render a user's name, linked to the list filtered on that author when they created posts.
	 *
	 * @param int     $user_id The user ID, or 0 when unknown.
	 * @param WP_Post $post    The template post.
	 *
	 * @return void
	 */
	private static function render_user( $user_id, $post ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			printf(
				'<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
				esc_html__( 'Unknown', 'block-editor-templates' )
			);

			return;
		}

		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url(
				add_query_arg(
					[
						'post_type' => $post->post_type,
						'author'    => $user->ID,
					],
					admin_url( 'edit.php' )
				)
			),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Load all authors and last editors of the listed posts in one query, instead of one per row.
	 *
	 * @return void
	 */
	private static function prime_users() {
		static $primed = false;
		global $wp_query;

		if ( $primed || ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) ) {
			return;
		}
		$primed = true;

		$user_ids = [];
		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$user_ids[] = (int) $post->post_author;
			$user_ids[] = (int) get_post_meta( $post->ID, '_edit_last', true );
		}

		$user_ids = array_filter( array_unique( $user_ids ) );
		if ( $user_ids ) {
			cache_users( $user_ids );
		}
	}

	/**
	 * Load what BlockPreview needs to render the templates as the editor does.
	 *
	 * Mirrors how core sets up the Customizer's widget block editor: every registered block's editor
	 * script and style (through 'enqueue_block_editor_assets'), the server-side block definitions and
	 * categories, and the editor settings, which carry the theme's editor styles and the assets for
	 * the preview iframes.
	 *
	 * @return void
	 */
	public static function enqueue_preview_assets() {
		$asset = Admin::script_asset( self::ENTRY );

		// Load the editor scripts and styles of all registered blocks on this non-editor screen.
		add_filter( 'should_load_block_editor_scripts_and_styles', '__return_true' );

		$context  = new \WP_Block_Editor_Context( [ 'name' => 'core/edit-post' ] );
		$settings = get_block_editor_settings( [], $context );

		// A preview shows what a template holds; it is not an editor for any one post type. Without a post
		// in the context, filters on 'allowed_block_types_all' that restrict blocks per post type would
		// leave the template's own blocks disallowed, and blocks that check this render a warning instead.
		$settings['allowedBlockTypes'] = true;

		wp_enqueue_script(
			self::HANDLE,
			ABET_ASSETS_URL . self::ENTRY . '.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? ABET_VERSION,
			true
		);
		wp_add_inline_script(
			self::HANDLE,
			'window.abetListPreview = ' . wp_json_encode( [ 'settings' => $settings ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);

		if ( function_exists( 'get_block_editor_server_block_settings' ) ) {
			wp_add_inline_script(
				'wp-blocks',
				'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ');'
			);
		}
		wp_add_inline_script(
			'wp-blocks',
			sprintf( 'wp.blocks.setCategories( %s );', wp_json_encode( get_block_categories( $context ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) ),
			'after'
		);

		// The blocks' editor scripts register them; their styles are not wanted on this page. Themes and
		// plugins also enqueue front-end and editor-canvas CSS on this hook, which would restyle the whole
		// admin screen. The preview iframes get the styles they need from the editor settings instead.
		$styles_before = wp_styles()->queue;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing core's own hook, as core does for the Customizer's block editor.
		do_action( 'enqueue_block_editor_assets' );
		foreach ( array_diff( wp_styles()->queue, $styles_before ) as $handle ) {
			wp_dequeue_style( $handle );
		}

		wp_enqueue_style( 'wp-block-editor' );
		if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.css' ) ) {
			wp_enqueue_style( self::HANDLE, ABET_ASSETS_URL . self::ENTRY . '.css', [], $asset['version'] ?? ABET_VERSION );
		}
	}
}
