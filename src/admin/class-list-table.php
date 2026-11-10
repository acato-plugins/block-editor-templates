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
 * Adds the Preview, Created by and Modified by columns to the template list tables.
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
	 * The query argument that filters the list on the user who last modified a template.
	 * Filtering on the creator uses WordPress' own 'author' argument.
	 */
	const MODIFIED_BY_ARG = 'abet_modified_by';

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
		add_action( 'init', [ self::class, 'register_post_type_hooks' ], 20 );
	}

	/**
	 * Hook in per template post type: the columns, and recording who saves in the block editor.
	 *
	 * The columns are registered here rather than on the list screen only, because Quick Edit
	 * re-renders its row in an AJAX request (wp_ajax_inline_save()), which has no current screen.
	 * Without them that row would lose its cells and the rest would shift under the wrong headers.
	 *
	 * @return void
	 */
	public static function register_post_type_hooks() {
		foreach ( array_keys( Admin::post_types() ) as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', [ self::class, 'add_columns' ], 20 );
			add_action( 'manage_' . $post_type . '_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
			add_action( 'rest_after_insert_' . $post_type, [ self::class, 'record_last_editor' ] );
		}
	}

	/**
	 * Store the current user as the last editor of a template saved through the REST API.
	 *
	 * WordPress records the last editor ('_edit_last') only when a post is saved from the classic
	 * edit screen, Quick Edit or bulk edit. The block editor saves through the REST API, which does
	 * not, so without this "Modified by" would never know who changed a template there.
	 *
	 * @param WP_Post $post The saved template.
	 *
	 * @return void
	 */
	public static function record_last_editor( $post ) {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( $post->ID, '_edit_last', $user_id );
		}
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

		add_filter( 'manage_' . $screen->id . '_sortable_columns', [ self::class, 'sortable_columns' ] );
		add_action( 'restrict_manage_posts', [ self::class, 'render_user_filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'filter_on_modified_by' ] );
		add_filter( 'posts_clauses', [ self::class, 'sort_on_user' ], 10, 2 );

		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_list_assets' ] );
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
	 * Add the enabled columns: the preview before the title, the people before the date.
	 *
	 * @param array<string, string> $columns The existing list-table columns.
	 *
	 * @return array<string, string>
	 */
	public static function add_columns( $columns ) {
		$result = [];
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key && self::preview_enabled() ) {
				$result['abet_preview'] = __( 'Preview', 'block-editor-templates' );
			}

			if ( 'date' === $key ) {
				$result = array_merge( $result, self::people_columns() );
			}

			$result[ $key ] = $label;
		}

		// No date column (removed by another plugin): add the people columns at the end.
		return $result + self::people_columns();
	}

	/**
	 * Make the people columns sortable, by the user's name.
	 *
	 * @param array<string, mixed> $columns The sortable columns.
	 *
	 * @return array<string, mixed>
	 */
	public static function sortable_columns( $columns ) {
		$columns['abet_created_by']  = [ 'abet_created_by', false ];
		$columns['abet_modified_by'] = [ 'abet_modified_by', false ];

		return $columns;
	}

	/**
	 * Sort the list on the name of the user who created or last modified a template.
	 *
	 * Ties, and templates whose user is unknown, fall back to the creation or modification date.
	 *
	 * @param array<string, string> $clauses The query clauses.
	 * @param \WP_Query             $query   The query.
	 *
	 * @return array<string, string>
	 */
	public static function sort_on_user( $clauses, $query ) {
		if ( ! $query->is_main_query() ) {
			return $clauses;
		}

		global $wpdb;

		$orderby = $query->get( 'orderby' );
		$order   = 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC';

		if ( 'abet_created_by' === $orderby ) {
			$clauses['join']   .= " LEFT JOIN {$wpdb->users} AS abet_creator ON abet_creator.ID = {$wpdb->posts}.post_author";
			$clauses['orderby'] = "abet_creator.display_name {$order}, {$wpdb->posts}.post_date DESC";
		} elseif ( 'abet_modified_by' === $orderby ) {
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS abet_edit_last ON abet_edit_last.post_id = {$wpdb->posts}.ID AND abet_edit_last.meta_key = '_edit_last'"
				. " LEFT JOIN {$wpdb->users} AS abet_modifier ON abet_modifier.ID = abet_edit_last.meta_value";
			$clauses['orderby'] = "abet_modifier.display_name {$order}, {$wpdb->posts}.post_modified DESC";
		}

		return $clauses;
	}

	/**
	 * The enabled Created by / Modified by columns.
	 *
	 * @return array<string, string>
	 */
	private static function people_columns() {
		$columns = [];
		if ( Settings::enabled( 'created_by' ) ) {
			$columns['abet_created_by'] = __( 'Created by', 'block-editor-templates' );
		}
		if ( Settings::enabled( 'modified_by' ) ) {
			$columns['abet_modified_by'] = __( 'Modified by', 'block-editor-templates' );
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
				self::render_user( (int) $post->post_author, $post, 'author' );
				break;
			case 'abet_modified_by':
				self::prime_users();
				self::render_user( (int) get_post_meta( $post->ID, '_edit_last', true ), $post, self::MODIFIED_BY_ARG );
				printf(
					'<br /><span class="abet-list-meta">%s</span>',
					esc_html( self::format_date( get_the_modified_date( '', $post ), get_the_modified_time( '', $post ) ) )
				);
				break;
		}
	}

	/**
	 * Format a date and time the way the list's Date column does.
	 *
	 * @param string|int|false $date The formatted date.
	 * @param string|int|false $time The formatted time.
	 *
	 * @return string
	 */
	private static function format_date( $date, $time ) {
		return sprintf(
			/* translators: 1: date, 2: time. */
			__( '%1$s at %2$s', 'block-editor-templates' ),
			(string) $date,
			(string) $time
		);
	}

	/**
	 * Render the preview cell: the stored block markup, which the script renders with BlockPreview.
	 *
	 * The cell is a link to the editor and also the scroll container of a tall template. It stays
	 * in the tab order, so keyboard users can focus it and scroll it with the arrow keys, and it is
	 * named after the template, because the rendered preview inside is not readable text.
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
			'<a class="abet-template-preview" href="%1$s" aria-label="%2$s" data-content="%3$s"></a>',
			esc_url( (string) $edit_link ),
			esc_attr(
				sprintf(
					/* translators: %s: template title. */
					__( 'Preview of “%s”, opens the editor', 'block-editor-templates' ),
					get_the_title( $post )
				)
			),
			esc_attr( $post->post_content )
		);
	}

	/**
	 * Render a user's name, linked to the list filtered on that user.
	 *
	 * @param int     $user_id   The user ID, or 0 when unknown.
	 * @param WP_Post $post      The template post.
	 * @param string  $query_arg The filter: 'author' (created by) or self::MODIFIED_BY_ARG.
	 *
	 * @return void
	 */
	private static function render_user( $user_id, $post, $query_arg ) {
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
						$query_arg  => $user->ID,
					],
					admin_url( 'edit.php' )
				)
			),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Add "All creators" / "All modifiers" filters next to the date filter.
	 *
	 * Each filter shows only when its column is on, and lists only the users who created or last
	 * modified a template of this post type.
	 *
	 * @param string $post_type The post type of the list.
	 * @param string $which     'top' or 'bottom' table navigation.
	 *
	 * @return void
	 */
	public static function render_user_filters( $post_type, $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		global $wpdb;

		if ( Settings::enabled( 'created_by' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A handful of rows, only on this admin screen.
			$creators = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'auto-draft', 'trash' )", $post_type ) );
			self::render_user_filter(
				'author',
				$creators,
				__( 'Filter by creator', 'block-editor-templates' ),
				__( 'All creators', 'block-editor-templates' )
			);
		}

		if ( Settings::enabled( 'modified_by' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A handful of rows, only on this admin screen.
			$modifiers = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_edit_last' AND p.post_type = %s AND p.post_status NOT IN ( 'auto-draft', 'trash' )", $post_type ) );
			self::render_user_filter(
				self::MODIFIED_BY_ARG,
				$modifiers,
				__( 'Filter by modifier', 'block-editor-templates' ),
				__( 'All modifiers', 'block-editor-templates' )
			);
		}
	}

	/**
	 * Render one user filter dropdown.
	 *
	 * @param string             $name     The query argument.
	 * @param array<int, string> $user_ids The user IDs to offer.
	 * @param string             $label    The (visually hidden) label.
	 * @param string             $all      The "no filter" option.
	 *
	 * @return void
	 */
	private static function render_user_filter( $name, $user_ids, $label, $all ) {
		$user_ids = array_filter( array_map( 'absint', $user_ids ) );
		if ( $user_ids ) {
			cache_users( $user_ids );
		}

		$users = array_filter( array_map( 'get_userdata', $user_ids ) );
		if ( ! $users ) {
			return;
		}

		usort(
			$users,
			static function ( $a, $b ) {
				return strcasecmp( $a->display_name, $b->display_name );
			}
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$current = isset( $_GET[ $name ] ) ? absint( $_GET[ $name ] ) : 0;
		$id      = 'abet-filter-' . $name;
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<option value=""><?php echo esc_html( $all ); ?></option>
			<?php foreach ( $users as $user ) : ?>
				<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( $current, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply the "Modified by" filter to the list query.
	 *
	 * @param \WP_Query $query The query.
	 *
	 * @return void
	 */
	public static function filter_on_modified_by( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$user_id = isset( $_GET[ self::MODIFIED_BY_ARG ] ) ? absint( $_GET[ self::MODIFIED_BY_ARG ] ) : 0;
		if ( ! $user_id || ! $query->is_main_query() || ! Settings::enabled( 'modified_by' ) ) {
			return;
		}

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = [
			'key'   => '_edit_last',
			'value' => (string) $user_id,
		];
		$query->set( 'meta_query', $meta_query );
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
	}

	/**
	 * Load the list styles (columns, thumbnails, help tooltips) on every template list.
	 *
	 * Also lets Escape close a help tooltip (WCAG 1.4.13): it hides while the pointer or focus
	 * stays on its button, and comes back the next time.
	 *
	 * @return void
	 */
	public static function enqueue_list_assets() {
		if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . self::ENTRY . '.css' ) ) {
			wp_enqueue_style( self::HANDLE, ABET_ASSETS_URL . self::ENTRY . '.css', [], Admin::script_asset( self::ENTRY )['version'] ?? ABET_VERSION );
		}

		wp_add_inline_script(
			'common',
			"document.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' !== event.key ) {
					return;
				}
				document.querySelectorAll( '.abet-help:hover, .abet-help:focus' ).forEach( function ( button ) {
					button.classList.add( 'is-dismissed' );
					var reset = function () {
						if ( ! button.matches( ':hover, :focus' ) ) {
							button.classList.remove( 'is-dismissed' );
						}
					};
					button.addEventListener( 'mouseleave', reset );
					button.addEventListener( 'blur', reset );
				} );
			} );"
		);
	}
}
