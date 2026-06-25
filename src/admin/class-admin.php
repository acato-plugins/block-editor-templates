<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

use WP_Post;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the admin-specific functionality of the plugin.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */
class Admin {

	/**
	 * An Array of block registered within this WordPress instance.
	 *
	 * @var \WP_Block_Type[] $registered_blocks
	 */
	private static $registered_blocks;

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Admin|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Admin The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Admin();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_post_types' ] );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_template_meta' ] );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_post_type_posts' ], 100 );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_block_templates' ], 999 );
		add_filter( 'default_content', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'set_default_content' ], 10, 2 );
		add_filter( 'wp_insert_post_data', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'prefill_on_insert' ], 10, 4 );
		add_action( 'admin_notices', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'stale_template_notice' ] );
		add_action( 'admin_post_abet_trash_stale_template', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'trash_stale_template' ] );
		add_action( 'admin_post_abet_trash_all_stale_templates', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'trash_all_stale_templates' ] );
		add_filter( 'post_row_actions', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'remove_row_actions' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'enqueue_admin_assets' ] );
		add_action( 'admin_menu', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'admin_menu' ] );
		add_filter( 'display_post_states', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'add_display_post_states' ], 10, 2 );
		add_filter( 'manage_block-templates_posts_columns', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'add_default_content_column' ] );
		add_action( 'manage_block-templates_posts_custom_column', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'render_default_content_column' ], 10, 2 );

		if ( ! wp_is_block_theme() ) {
			add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_taxonomy_posts' ], 100 );
			add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_special_pages' ], 100 );
			add_filter( 'archive_template', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'get_custom_archive' ] );

			// Set default 404 post template.
			add_filter( 'template_include', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'set_404_template' ], 99 );
		}
	}

	/**
	 * Post type definitions.
	 *
	 * @since 1.0.4
	 *
	 * @return array<string, array<string, string|boolean>>
	 */
	public static function post_types() {
		static $post_types;

		if ( ! $post_types ) {
			$post_types = [
				'block-templates'    => [
					'single'               => _x( 'Post Type Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Post Type Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Post Type Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype',
					'for'                  => 'post_type',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
				'pt-arch-templates'  => [
					'single'               => _x( 'Post Type Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Post Type Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Post Type Archive Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype_archive',
					'for'                  => 'post_type',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'tax-arch-templates' => [
					'single'               => _x( 'Taxonomy Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Taxonomy Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Taxonomy Archive Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_taxonomy_archive',
					'for'                  => 'taxonomy',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'special-templates'  => [
					'single'               => _x( 'Special Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Special Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Special Templates', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_special',
					'for'                  => 'special',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
			];
			if ( wp_is_block_theme() ) {
				unset( $post_types['pt-arch-templates'], $post_types['tax-arch-templates'], $post_types['special-templates'] );
			}
		}

		return $post_types;
	}

	/**
	 * Register block templates for all post types.
	 *
	 * @return void
	 */
	public static function register_block_templates() {
		self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			// Templates that prefill new posts through default_content (see self::set_default_content())
			// must not also register $object->template, which rebuilds each block via createBlock and
			// therefore drops markup-sourced content such as a heading's or paragraph's text.
			if ( self::use_default_content( $post_id ) ) {
				continue;
			}

			$post_type = get_post_meta( $post_id, '_template_for_posttype', true );
			$object    = get_post_type_object( $post_type );

			if ( ! $object ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( $post && has_blocks( $post->post_content ) ) {
				$blocks   = parse_blocks( $post->post_content );
				$template = self::blocks_to_template( $blocks );

				if ( count( $template ) ) {
					$object->template = $template;
				}
			}
		}
	}

	/**
	 * Convert Gutenberg blocks to a block template.
	 *
	 * @param array<mixed> $blocks An array of blocks as provide by parse_blocks().
	 *
	 * @return array<mixed> A block template.
	 */
	private static function blocks_to_template( $blocks ) {
		$template = [];
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( isset( $block['attrs']['textAsPlaceholder'], self::$registered_blocks[ $block['blockName'] ] ) && $block['attrs']['textAsPlaceholder'] ) {
				$attributes = self::$registered_blocks[ $block['blockName'] ]->get_attributes();
				foreach ( $attributes as $attribute_name => $attribute ) {
					if ( isset( $attributes[ $attribute_name . 'Placeholder' ], $block['attrs'][ $attribute_name ] ) ) {
						$block['attrs'][ $attribute_name . 'Placeholder' ] = $block['attrs'][ $attribute_name ];
						unset( $block['attrs'][ $attribute_name ] );
					}
				}
			}

			$sub_template = [
				$block['blockName'],
				$block['attrs'] ?? [],
				self::blocks_to_template( $block['innerBlocks'] ),
				$block['innerHTML'] ?? '',
				$block['innerContent'] ?? [],
			];
			$template[]   = $sub_template;
		}

		return $template;
	}

	/**
	 * Register the per-template "prefill new posts" setting so it can be edited from the block editor.
	 *
	 * @return void
	 */
	public static function register_template_meta() {
		register_post_meta(
			'block-templates',
			'_abet_use_default_content',
			[
				'type'          => 'boolean',
				'default'       => false,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			]
		);
	}

	/**
	 * Whether a given template should prefill new posts with its content instead of registering a post-type template.
	 *
	 * Driven by the per-template '_abet_use_default_content' setting (off by default), read directly so the
	 * editor's value is authoritative.
	 *
	 * @param int $post_id The template post ID.
	 *
	 * @return bool True to use the default_content approach, false to register $object->template.
	 */
	private static function use_default_content( $post_id ) {
		return (bool) get_post_meta( $post_id, '_abet_use_default_content', true );
	}

	/**
	 * Get the (cached) IDs of all post-type template posts.
	 *
	 * @return int[] The template post IDs.
	 */
	private static function get_post_type_template_posts() {
		$cache_key      = 'abet_posts_with_meta_' . md5( '_template_for_posttype' );
		$template_posts = wp_cache_get( $cache_key );

		if ( false === $template_posts ) {
			$template_posts = get_posts(
				[
					'numberposts' => -1,
					'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
					'post_status' => 'any',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
					'meta_key'    => '_template_for_posttype',
					'fields'      => 'ids',
				]
			);
			wp_cache_set( $cache_key, $template_posts, '', HOUR_IN_SECONDS );
		}

		return $template_posts ? $template_posts : [];
	}

	/**
	 * Prefill a new post with its post-type template content.
	 *
	 * Unlike registering $object->template (which the editor rebuilds with createBlock, dropping
	 * any markup-sourced attributes such as a heading's or paragraph's text), this copies the
	 * template's blocks verbatim into the new post. serialize_blocks() round-trips the original
	 * markup, so sourced content is preserved without having to read it back out of the HTML.
	 *
	 * @param string  $content The default post content.
	 * @param WP_Post $post    The post being created.
	 *
	 * @return string The (possibly prefilled) default content.
	 */
	public static function set_default_content( $content, $post ) {
		// Only act on an empty new post.
		if ( ! empty( $content ) || ! $post instanceof WP_Post || empty( $post->post_type ) ) {
			return $content;
		}

		$prefilled = self::get_prefill_content_for_post_type( $post->post_type );

		// Fall back to the original content rather than blanking the post if no prefill is available.
		return '' !== $prefilled ? $prefilled : $content;
	}

	/**
	 * Prefill new posts inserted outside the admin "Add New" flow (REST, wp_insert_post(), WP-CLI).
	 *
	 * The 'default_content' filter only fires for get_default_post_to_edit(), so posts created via
	 * the REST API, wp_insert_post(), or WP-CLI would otherwise miss the prefill. wp_insert_post_data
	 * is the single hook every insertion path converges on. The guards below keep the override
	 * conservative: never touch updates, never overwrite content the caller supplied, never act on
	 * revisions or the template post types themselves, and skip auto-draft inserts because those have
	 * already passed through 'default_content'. Integrators can opt out per-call with the
	 * 'abet_prefill_on_insert' filter.
	 *
	 * @param array<string, mixed> $data        Sanitized post data ready for insertion.
	 * @param array<string, mixed> $postarr     The sanitized $postarr passed to wp_insert_post().
	 * @param array<string, mixed> $unsanitized The raw $postarr passed to wp_insert_post().
	 * @param bool                 $update      Whether this is an update of an existing post.
	 *
	 * @return array<string, mixed> The (possibly prefilled) post data.
	 */
	public static function prefill_on_insert( $data, $postarr, $unsanitized, $update ) {
		if ( $update ) {
			return $data;
		}

		if ( ! empty( $data['post_content'] ) ) {
			return $data;
		}

		if ( empty( $data['post_type'] ) ) {
			return $data;
		}

		// The admin "Add New" path already prefilled through 'default_content' before reaching here.
		if ( 'auto-draft' === ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		// Never prefill the template posts themselves or revision rows.
		if ( in_array( $data['post_type'], [ 'revision', 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ], true ) ) {
			return $data;
		}

		/**
		 * Filter whether this insert should be prefilled with the post-type template.
		 *
		 * @param bool                 $should  True to allow the prefill, false to skip it.
		 * @param array<string, mixed> $data    The sanitized post data about to be inserted.
		 * @param array<string, mixed> $postarr The raw post data passed to wp_insert_post().
		 */
		if ( ! apply_filters( 'abet_prefill_on_insert', true, $data, $postarr ) ) {
			return $data;
		}

		$prefilled = self::get_prefill_content_for_post_type( $data['post_type'] );
		if ( '' !== $prefilled ) {
			// wp_insert_post() unslashes $data['post_content'] again, so re-slash for parity.
			$data['post_content'] = wp_slash( $prefilled );
		}

		return $data;
	}

	/**
	 * Programmatically copy a post type's template into an existing, empty post.
	 *
	 * Intended for integrators (importers, sync jobs, headless workflows) that want the prefill
	 * without relying on self::prefill_on_insert() inferring intent from an empty content field.
	 * Does nothing when the post already has content or no template is configured.
	 *
	 * @param int $post_id The post to prefill.
	 *
	 * @return bool True when content was written, false otherwise.
	 */
	public static function prefill_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || '' !== trim( (string) $post->post_content ) ) {
			return false;
		}

		$prefilled = self::get_prefill_content_for_post_type( $post->post_type );
		if ( '' === $prefilled ) {
			return false;
		}

		$result = wp_update_post(
			[
				'ID'           => $post->ID,
				'post_content' => $prefilled,
			],
			true
		);

		return ! is_wp_error( $result ) && 0 !== (int) $result;
	}

	/**
	 * Resolve the prefill content for a given post type.
	 *
	 * Returns an empty string when no template is configured, the configured template is a draft, or
	 * the template contains no blocks. parse_blocks() -> serialize_blocks() round-trips the original
	 * markup as-is, so markup-sourced content (heading/paragraph text) is preserved without having
	 * to read it back out of the HTML.
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return string The serialized block markup to prefill with, or an empty string.
	 */
	private static function get_prefill_content_for_post_type( $post_type ) {
		if ( '' === (string) $post_type ) {
			return '';
		}

		if ( ! self::$registered_blocks ) {
			self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		}

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			if ( ! self::use_default_content( $post_id ) || get_post_meta( $post_id, '_template_for_posttype', true ) !== $post_type ) {
				continue;
			}

			$template_post = get_post( $post_id );
			// A draft template is not applied: the plugin only uses published templates.
			if ( ! $template_post || 'publish' !== $template_post->post_status || ! has_blocks( $template_post->post_content ) ) {
				return '';
			}

			$prefilled = serialize_blocks( self::apply_placeholders( parse_blocks( $template_post->post_content ) ) );

			return '' !== trim( $prefilled ) ? $prefilled : '';
		}

		return '';
	}

	/**
	 * Move the value of placeholder-enabled attributes into their "...Placeholder" counterpart.
	 *
	 * Mirrors the textAsPlaceholder handling of self::blocks_to_template() so the verbatim copy
	 * keeps showing the configured text as a placeholder rather than as real content.
	 *
	 * @param array<mixed> $blocks Blocks as provided by parse_blocks().
	 *
	 * @return array<mixed> The blocks with placeholder attributes applied.
	 */
	private static function apply_placeholders( $blocks ) {
		foreach ( $blocks as &$block ) {
			if ( empty( $block['blockName'] ) || ! isset( self::$registered_blocks[ $block['blockName'] ] ) ) {
				continue;
			}

			if ( ! empty( $block['attrs']['textAsPlaceholder'] ) ) {
				$attributes = self::$registered_blocks[ $block['blockName'] ]->get_attributes();
				foreach ( $attributes as $attribute_name => $attribute ) {
					if ( isset( $attributes[ $attribute_name . 'Placeholder' ], $block['attrs'][ $attribute_name ] ) ) {
						$block['attrs'][ $attribute_name . 'Placeholder' ] = $block['attrs'][ $attribute_name ];
						unset( $block['attrs'][ $attribute_name ] );
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::apply_placeholders( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Enqueue assets for dynamic blocks for the admin.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		if ( 'block-templates' === get_post_type( get_queried_object_id() ) ) {
			$script_asset_path = ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.asset.php';
			if ( file_exists( $script_asset_path ) ) {
				$script_asset = require $script_asset_path;
			} else {
				$script_asset = [
					'dependencies' => [],
					'version'      => ABET_VERSION,
				];
			}

			if ( file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.js' ) ) {
				wp_enqueue_script(
					'block-editor-templates-admin',
					esc_url( ABET_ASSETS_URL ) . 'admin.js',
					$script_asset['dependencies'],
					$script_asset['version'],
					false
				);
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'block-editor-templates-admin (admin.js) isn`t found. Forgot to run `npm run build`?' );
			}
		}
	}

	/**
	 * Whether a post type is edited with the block editor.
	 *
	 * The show_in_rest flag does not by itself imply block-editor support, so a post type can be exposed
	 * to the REST API yet have no editor. Use WordPress' own check when it is available and fall back
	 * to the block editor's minimum requirements (REST support and an editor) otherwise.
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return bool True when the post type uses the block editor.
	 */
	private static function uses_block_editor( $post_type ) {
		// Taxonomy archive templates store a taxonomy slug, not a post type, so guard against those.
		if ( ! post_type_exists( $post_type ) ) {
			return false;
		}

		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			return use_block_editor_for_post_type( $post_type );
		}

		// Before WordPress 6.1 use_block_editor_for_post_type() lived in wp-admin/includes/post.php, which
		// is not loaded yet on the 'init' hook where create_post_type_posts() runs. In that case fall back
		// to the same requirements it checks (REST support and an editor), minus its filter.
		$object = get_post_type_object( $post_type );

		return $object && $object->show_in_rest && post_type_supports( $post_type, 'editor' );
	}

	/**
	 * Find Post Type Template posts created for a post type that no longer uses the block editor.
	 *
	 * Unregistered post types are skipped on purpose (e.g. a temporarily-deactivated plugin), so the
	 * editor is only nudged about templates that exist for a post type which is present but editor-less.
	 *
	 * @return array<int, string> Map of post ID to template title.
	 */
	private static function get_stale_post_type_templates() {
		$stale = [];

		foreach ( self::get_post_type_template_posts() as $post_id ) {
			if ( 'block-templates' !== get_post_type( $post_id ) || 'trash' === get_post_status( $post_id ) ) {
				continue;
			}

			$post_type = get_post_meta( $post_id, '_template_for_posttype', true );
			if ( empty( $post_type ) || 'general_template' === $post_type ) {
				continue;
			}

			if ( post_type_exists( $post_type ) && ! self::uses_block_editor( $post_type ) ) {
				$stale[ $post_id ] = get_the_title( $post_id );
			}
		}

		return $stale;
	}

	/**
	 * Show an admin notice for stale Post Type Templates, with a per-template "Move to Trash" button.
	 *
	 * The notice leaves the decision to the editor; nothing is trashed automatically.
	 *
	 * @return void
	 */
	public static function stale_template_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-block-templates' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success count for display.
		$trashed = isset( $_GET['abet_trashed'] ) ? absint( wp_unslash( $_GET['abet_trashed'] ) ) : 0;
		if ( $trashed > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of templates moved to the trash. */
				esc_html( sprintf( _n( '%d template moved to the trash.', '%d templates moved to the trash.', $trashed, 'block-editor-templates' ), $trashed ) )
			);
		}

		// Only list templates the current user is actually allowed to trash.
		$stale = [];
		foreach ( self::get_stale_post_type_templates() as $post_id => $title ) {
			if ( current_user_can( 'delete_post', $post_id ) ) {
				$stale[ $post_id ] = $title;
			}
		}
		if ( empty( $stale ) ) {
			return;
		}

		echo '<div class="notice notice-error">';

		if ( count( $stale ) > 1 ) {
			// Multiple templates: an intro line followed by a real list, so the relationship is conveyed semantically.
			printf( '<p>%s</p>', esc_html__( 'These Post Type Templates exist for post types that no longer use the block editor. You can move them to the trash:', 'block-editor-templates' ) );
			echo '<ul style="list-style:disc;margin-left:20px;">';
			foreach ( $stale as $post_id => $title ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts are escaped in the helpers.
				printf( '<li>%1$s &mdash; %2$s</li>', esc_html( self::stale_template_label( $post_id, $title ) ), self::stale_template_trash_link( $post_id, $title ) );
			}
			echo '</ul>';

			// Bulk action. The handler re-derives the stale set server-side, so only templates without
			// a block editor are trashed regardless of what is submitted.
			printf(
				'<p><a href="%1$s" class="button button-link-delete">%2$s</a></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=abet_trash_all_stale_templates' ), 'abet_trash_all_stale_templates' ) ),
				esc_html__( 'Move all to Trash', 'block-editor-templates' )
			);
		} else {
			// A single template reads better as a sentence; a one-item list is just noise for screen readers.
			reset( $stale );
			$post_id = key( $stale );
			$title   = current( $stale );

			printf(
				'<p>%1$s %2$s</p>',
				esc_html(
					sprintf(
						/* translators: %s: template title. */
						__( 'The Post Type Template “%s” exists for a post type that no longer uses the block editor.', 'block-editor-templates' ),
						self::stale_template_label( $post_id, $title )
					)
				),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns escaped anchor markup (esc_url/esc_html).
				self::stale_template_trash_link( $post_id, $title )
			);
		}

		echo '</div>';
	}

	/**
	 * Build the human label for a stale template, falling back to the post ID when it has no title.
	 *
	 * @param int    $post_id The template post ID.
	 * @param string $title   The template post title.
	 *
	 * @return string The label.
	 */
	private static function stale_template_label( $post_id, $title ) {
		/* translators: %d: template post ID. */
		return '' !== trim( (string) $title ) ? (string) $title : sprintf( __( 'Template #%d', 'block-editor-templates' ), $post_id );
	}

	/**
	 * Build a nonce-protected "Move to Trash" link for a stale template.
	 *
	 * The visible text is identical for every template, so the template name is exposed to assistive
	 * technology through screen-reader-text. That keeps each link uniquely identifiable when navigating
	 * by links, without repeating the name visually.
	 *
	 * @param int    $post_id The template post ID.
	 * @param string $title   The template post title.
	 *
	 * @return string Escaped anchor markup.
	 */
	private static function stale_template_trash_link( $post_id, $title ) {
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'abet_trash_stale_template',
					'post'   => $post_id,
				],
				admin_url( 'admin-post.php' )
			),
			'abet_trash_stale_template_' . $post_id
		);

		return sprintf(
			'<a href="%1$s" class="button-link-delete">%2$s<span class="screen-reader-text">%3$s</span></a>',
			esc_url( $url ),
			esc_html__( 'Move to Trash', 'block-editor-templates' ),
			esc_html( ': ' . self::stale_template_label( $post_id, $title ) )
		);
	}

	/**
	 * Handle the "Move to Trash" action from self::stale_template_notice().
	 *
	 * @return void
	 */
	public static function trash_stale_template() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( ! $post_id || 'block-templates' !== get_post_type( $post_id ) || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to trash this template.', 'block-editor-templates' ) );
		}

		check_admin_referer( 'abet_trash_stale_template_' . $post_id );

		wp_trash_post( $post_id );

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'    => 'block-templates',
					'abet_trashed' => 1,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "Move all to Trash" action from self::stale_template_notice().
	 *
	 * The stale set is re-derived here rather than taken from the request, so only Post Type Templates
	 * whose post type no longer uses the block editor are trashed, and only those the user may delete.
	 *
	 * @return void
	 */
	public static function trash_all_stale_templates() {
		check_admin_referer( 'abet_trash_all_stale_templates' );

		$trashed = 0;
		foreach ( self::get_stale_post_type_templates() as $post_id => $title ) {
			if ( current_user_can( 'delete_post', $post_id ) ) {
				wp_trash_post( $post_id );
				++$trashed;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'    => 'block-templates',
					'abet_trashed' => $trashed,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Create a block template for each registered post type and also an Archive Template for each registered post type
	 * that has an archive.
	 *
	 * @return void
	 */
	public static function create_post_type_posts() {
		// Get all registered post types.
		$registered_post_types = array_merge(
			get_post_types(
				[
					'_builtin'     => true,
					'public'       => true,
					'show_in_rest' => true,
				],
				'objects'
			),
			get_post_types(
				[
					'_builtin'     => false,
					'show_in_rest' => true,
				],
				'objects'
			)
		);
		unset( $registered_post_types['attachment'] );
		foreach ( self::post_types() as $slug => $settings ) {
			unset( $registered_post_types[ $slug ] );
		}

		foreach ( self::post_types() as $slug => $settings ) {
			if ( 'post_type' !== $settings['for'] ) {
				continue;
			}

			$filtered_registered_post_types = $registered_post_types;
			if ( true === $settings['only_for_has_archive'] ) {
				foreach ( $filtered_registered_post_types as $pt_slug => $obj ) {
					if ( false === $obj->has_archive ) {
						unset( $filtered_registered_post_types[ $pt_slug ] );
					}
				}
			}

			$filtered_registered_post_types = array_keys( $filtered_registered_post_types );

			// A Post Type Template is edited in the block editor, so only offer it for post types
			// that actually use the block editor. show_in_rest alone does not imply editor support.
			if ( false === $settings['general_template'] ) {
				$filtered_registered_post_types = array_values( array_filter( $filtered_registered_post_types, [ self::class, 'uses_block_editor' ] ) );
			}

			// Get all posts that have the meta field.
			// Get all posts that have the meta field.
			$cache_key            = 'abet_posts_with_meta_' . md5( (string) $settings['meta_field'] );
			$posts_with_templates = wp_cache_get( $cache_key );

			if ( false === $posts_with_templates ) {
				$posts_with_templates = get_posts(
					[
						'numberposts' => -1,
						'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
						'post_status' => 'any',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
						'meta_key'    => (string) $settings['meta_field'],
						'fields'      => 'ids',
					]
				);
				wp_cache_set( $cache_key, $posts_with_templates, '', HOUR_IN_SECONDS );
			}

			// Extract the meta values.
			$created_templates = [];
			foreach ( $posts_with_templates as $post_id ) {
				$meta_value = get_post_meta( $post_id, (string) $settings['meta_field'], true );
				if ( ! empty( $meta_value ) ) {
					$created_templates[] = $meta_value;
				}
			}

			if ( $settings['general_template'] ) {
				if ( ! in_array( 'general_template', $created_templates, true ) ) {
					wp_insert_post(
						[
							'post_type'   => $slug,
							'post_title'  => __( '_General Template', 'block-editor-templates' ),
							'post_status' => 'draft',
							'meta_input'  => [
								$settings['meta_field'] => 'general_template',
							],
						]
					);
				}
				unset( $created_templates[ array_search( 'general_template', $created_templates, true ) ] );
			}

			$difference = array_merge( array_diff( $filtered_registered_post_types, $created_templates ), array_diff( $created_templates, $filtered_registered_post_types ) );
			if ( count( $difference ) ) {
				foreach ( $difference as $post_type ) {
					if ( in_array( $post_type, $filtered_registered_post_types, true ) ) {
						// We need to create a new post.
						$obj = get_post_type_object( $post_type );
						wp_insert_post(
							[
								'post_type'   => $slug,
								'post_title'  => $obj->labels->name,
								'post_status' => 'draft',
								'meta_input'  => [
									$settings['meta_field'] => $post_type,
								],
							]
						);
					}
				}
			}
		}
	}

	/**
	 * Create an Archive Template for each registered taxonomy.
	 *
	 * @return void
	 */
	public static function create_taxonomy_posts() {
		// Get all registered taxonomies.
		$registered_taxonomies = get_taxonomies( [ 'public' => true ] );
		$registered_taxonomies = array_values( $registered_taxonomies );

		foreach ( self::post_types() as $slug => $settings ) {
			if ( 'taxonomy' !== $settings['for'] ) {
				continue;
			}
			// Get all posts that have the meta field.
			$cache_key            = 'abet_posts_with_meta_' . md5( (string) $settings['meta_field'] );
			$posts_with_templates = wp_cache_get( $cache_key );

			if ( false === $posts_with_templates ) {
				$posts_with_templates = get_posts(
					[
						'numberposts' => -1,
						'post_type'   => [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ],
						'post_status' => 'any',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
						'meta_key'    => (string) $settings['meta_field'],
						'fields'      => 'ids',
					]
				);
				wp_cache_set( $cache_key, $posts_with_templates, '', HOUR_IN_SECONDS );
			}

			// Extract the meta values.
			$created_templates = [];
			foreach ( $posts_with_templates as $post_id ) {
				$meta_value = get_post_meta( $post_id, (string) $settings['meta_field'], true );
				if ( ! empty( $meta_value ) ) {
					$created_templates[] = $meta_value;
				}
			}

			if ( $settings['general_template'] ) {
				if ( ! in_array( 'general_template', $created_templates, true ) ) {
					wp_insert_post(
						[
							'post_type'   => $slug,
							'post_title'  => __( '_General Template', 'block-editor-templates' ),
							'post_status' => 'draft',
							'meta_input'  => [
								$settings['meta_field'] => 'general_template',
							],
						]
					);
				}
				unset( $created_templates[ array_search( 'general_template', $created_templates, true ) ] );
			}

			$difference = array_merge( array_diff( $registered_taxonomies, $created_templates ), array_diff( $created_templates, $registered_taxonomies ) );
			if ( count( $difference ) ) {
				foreach ( $difference as $taxonomy ) {
					if ( in_array( $taxonomy, $registered_taxonomies, true ) ) {
						// We need to create a new post.
						$obj = get_taxonomy( $taxonomy );
						if ( $obj ) {
							wp_insert_post(
								[
									'post_type'   => $slug,
									'post_title'  => $obj->labels->name,
									'post_status' => 'draft',
									'meta_input'  => [
										$settings['meta_field'] => $taxonomy,
									],
								]
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Create a special page for each registered special page.
	 *
	 * @return void
	 */
	public static function create_special_pages() {
		global $wpdb;

		$special_pages = [
			'404' => __( '404 page', 'block-editor-templates' ),
		];

		// Get all created templates.
		$created_templates = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s", '_template_for_special' ),
			ARRAY_A
		);
		$created_templates = array_column( $created_templates, 'meta_value' );

		foreach ( $special_pages as $special_page_slug => $special_page_name ) {
			// Force the slug to be a string.
			$special_page_slug = (string) $special_page_slug;

			if ( ! in_array( $special_page_slug, $created_templates, true ) ) {
				wp_insert_post(
					[
						'post_type'   => 'special-templates',
						'post_title'  => $special_page_name,
						'post_status' => 'draft',
						'meta_input'  => [
							'_template_for_special' => $special_page_slug,
						],
					]
				);
			}
		}
	}

	/**
	 * Remove trash option from row actions.
	 *
	 * See: https://wordpress.stackexchange.com/a/295184
	 *
	 * @param string[] $actions An array of row action links.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return string[]
	 */
	public static function remove_row_actions( $actions, $post ) {
		if ( array_key_exists( $post->post_type, self::post_types() ) ) {
			unset( $actions['clone'] );

			// If the post is the General template, then remove the trash link.
			if (
				'general_template' === get_post_meta( $post->ID, '_template_for_posttype_archive', true )
				|| 'general_template' === get_post_meta( $post->ID, '_template_for_taxonomy_archive', true )
			) {
				unset( $actions['trash'] );
			}

			// Replace the view link with a link to the actual frontend page, or remove it.
			$preview_link = 'publish' === $post->post_status && ! empty( $post->post_content ) ? self::get_template_preview_link( $post ) : false;
			if ( $preview_link ) {
				$actions['view'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $preview_link ),
					esc_html__( 'View', 'block-editor-templates' )
				);
			} else {
				unset( $actions['view'] );
			}
		}

		return $actions;
	}

	/**
	 * Register the post types for this plugin.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		foreach ( self::post_types() as $post_type_slug => $settings ) {
			$post_type_single = (string) $settings['single'];
			$post_type_plural = (string) $settings['plural'];

			$labels = [
				'name'               => $post_type_single,
				'singular_name'      => $post_type_single,
				'add_new'            => __( 'Add New', 'block-editor-templates' ),
				/* translators: %s: CPT name */
				'add_new_item'       => sprintf( __( 'Add New %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'edit_item'          => sprintf( __( 'Edit %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'new_item'           => sprintf( __( 'New %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'all_items'          => sprintf( __( 'All %s', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'view_item'          => sprintf( __( 'View %s', 'block-editor-templates' ), $post_type_single ),
				/* translators: %s: CPT name */
				'search_items'       => sprintf( __( 'Search %s', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'not_found'          => sprintf( __( 'No %s found', 'block-editor-templates' ), $post_type_plural ),
				/* translators: %s: CPT name */
				'not_found_in_trash' => sprintf( __( 'No %s found in trash', 'block-editor-templates' ), $post_type_plural ),
				'parent_item_colon'  => '',
				'menu_name'          => $post_type_single,
			];
			$args   = [
				'label'               => $post_type_single,
				'description'         => (string) $settings['description'],
				'labels'              => $labels,
				// 'custom-fields' lets the REST API save registered post meta, such as the per-template
				// '_abet_use_default_content' setting on block-templates (see self::register_template_meta()).
				'supports'            => [ 'title', 'editor', 'custom-fields' ],
				'taxonomies'          => [],
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'menu_position'       => 100,
				// 5 - below Posts,       10 - below Media,       15 - below Links,
				// 20 - below Pages,       25 - below comments,    60 - below first separator,
				// 65 - below Plugins,     70 - below Users,       75 - below Tools,
				// 80 - below Settings,    100 - below second separator.
				'menu_icon'           => 'dashicons-media-code',
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'can_export'          => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				/**
				 * Filters the capability_type of the post_type.
				 *
				 * Allow overriding the base capability type for finer access control.
				 *
				 * @since 1.0.0
				 *
				 * @param string|array $capability_type The capability type as defined by WordPress, 'post' by default.
				 *                                      Filter can return a string or a 2-element array.
				 *                                      See function get_post_type_capabilities for extensive documentation.
				 * @param string $post_type_slug The post type for which the capability is overridden.
				 *
				 * @see   get_post_type_capabilities
				 */
				'capability_type'     => apply_filters( 'acato/block_editor_templates/post_type/capability_type', 'post', $post_type_slug ),
				// See: https://stackoverflow.com/a/16675677 .
				'capabilities'        => [
					'create_posts' => 'do_not_allow',
				],
				'map_meta_cap'        => true,
				'show_in_rest'        => true,
			];
			register_post_type( $post_type_slug, $args );
		}
	}

	/**
	 * Add an admin menu for the Block templates.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_menu_page( 'Block Templates', 'Block Templates', 'manage_options', 'edit.php?post_type=block-templates', '', 'dashicons-media-code' );
		foreach ( self::post_types() as $slug => $settings ) {
			add_submenu_page( 'edit.php?post_type=block-templates', (string) $settings['plural'], (string) $settings['plural'], 'manage_options', 'edit.php?post_type=' . $slug );
		}
	}

	/**
	 * Check if a custom Archive Template is available and if so return the path to the correct template file.
	 *
	 * @param string $archive_template The current archive template.
	 *
	 * @return string
	 */
	public static function get_custom_archive( $archive_template ) {
		global $abet_template_post;

		if ( is_post_type_archive() ) {
			global $wp_query;

			$post_type  = 'pt-arch-templates';
			$meta_key   = '_template_for_posttype_archive';
			$meta_value = $wp_query->get( 'post_type' );
			$templates  = [
				'abet-' . $meta_value . '-archive.php',
				'abet-posttype-archive.php',
			];
		} elseif ( is_tax() || is_category() || is_tag() ) {
			global $wp_query;

			$tax        = $wp_query->get_queried_object();
			$post_type  = 'tax-arch-templates';
			$meta_key   = '_template_for_taxonomy_archive';
			$meta_value = $tax->taxonomy;
			$templates  = [
				'abet-' . $meta_value . '-archive.php',
				'abet-taxonomy-archive.php',
			];
		} else {
			return $archive_template;
		}

		$cache_key = 'abet_posts_' . md5( $post_type . $meta_key . $meta_value );
		$posts     = wp_cache_get( $cache_key );

		if ( false === $posts ) {
			$posts = get_posts(
				[
					'fields'     => 'ids',
					'post_type'  => $post_type,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- It is cached.
					'meta_query' => [
						[
							'key'     => $meta_key,
							'value'   => [ 'general_template', $meta_value ],
							'compare' => 'IN',
						],
					],
				]
			);
			wp_cache_set( $cache_key, $posts, '', HOUR_IN_SECONDS );
		}

		$abet_template_post = false;
		switch ( count( $posts ) ) {
			case 0:
				return $archive_template;
			case 1:
				$abet_template_post = $posts[0];
				break;
			default:
				foreach ( $posts as $_post ) {
					$meta = get_post_meta( $_post, $meta_key, true );
					if ( 'general_template' !== $meta ) {
						$abet_template_post = $_post;
						break 2;
					}
				}

				return $archive_template;
		}
		if ( $abet_template_post ) {
			$templates[] = 'abet-archive.php';
			$template    = locate_template( $templates );

			if ( ! $template ) {
				$template = plugin_dir_path( dirname( __DIR__ ) ) . 'templates/abet-archive.php';
			}

			return $template;
		}

		return $archive_template;
	}

	/**
	 * Add a display state to the post list.
	 *
	 * @param string[] $post_states An array of post states.
	 * @param WP_Post  $post        The post object.
	 *
	 * @return string[]
	 */
	public static function add_display_post_states( $post_states, $post ) {
		$post_type = get_post_type( $post );

		// Check if we are on the correct post type.
		if ( ! in_array( $post_type, [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates' ], true ) ) {
			return $post_states;
		}

		switch ( $post_type ) {
			default:
			case 'block-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_posttype', true );
				$item_exists = post_type_exists( $item_type );
				break;
			case 'pt-arch-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_posttype_archive', true );
				$item_exists = post_type_exists( $item_type );
				break;
			case 'tax-arch-templates':
				$item_type   = get_post_meta( $post->ID, '_template_for_taxonomy_archive', true );
				$item_exists = taxonomy_exists( $item_type );
				break;
		}

		// Check if the post is a general template.
		if ( 'general_template' === $item_type ) {
			return $post_states;
		}

		// Check if the post type exists.
		if ( ! $item_exists ) {
			$post_states['deleted'] = esc_html__( 'Item is deleted', 'block-editor-templates' );
		}

		return $post_states;
	}

	/**
	 * Add a "Prefill new posts" column to the block-templates list table.
	 *
	 * @param array<string, string> $columns The existing list-table columns.
	 *
	 * @return array<string, string> The columns with the prefill column added before the date.
	 */
	public static function add_default_content_column( $columns ) {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );

		$help = __( 'Copy this template into new posts (keeping heading and paragraph text). Only applies when creating a post in the WordPress admin.', 'block-editor-templates' );

		// The icon is a decorative mouse-hover hint (title); the help text is exposed to assistive
		// technology as real text via screen-reader-text so it does not depend on the tooltip.
		$columns['abet_default_content'] = sprintf(
			'%1$s <span class="dashicons dashicons-editor-help" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom;cursor:help;" aria-hidden="true" title="%2$s"></span><span class="screen-reader-text">%3$s</span>',
			esc_html__( 'Prefill new posts', 'block-editor-templates' ),
			esc_attr( $help ),
			esc_html( $help )
		);

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render the "Prefill new posts" column for a block-templates row.
	 *
	 * @param string $column  The current column key.
	 * @param int    $post_id The current post ID.
	 *
	 * @return void
	 */
	public static function render_default_content_column( $column, $post_id ) {
		if ( 'abet_default_content' !== $column ) {
			return;
		}

		if ( self::use_default_content( $post_id ) ) {
			printf( '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">%s</span>', esc_html__( 'Prefill enabled', 'block-editor-templates' ) );
		} else {
			printf( '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>', esc_html__( 'Prefill disabled', 'block-editor-templates' ) );
		}
	}

	/**
	 * Get the frontend preview link for a template post.
	 *
	 * @param WP_Post $post The template post.
	 *
	 * @return string|false The preview URL, or false if not available.
	 */
	private static function get_template_preview_link( $post ) {
		switch ( $post->post_type ) {
			case 'pt-arch-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_posttype_archive', true );
				if ( 'general_template' === $meta_value || empty( $meta_value ) ) {
					return false;
				}
				return get_post_type_archive_link( $meta_value );

			case 'tax-arch-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_taxonomy_archive', true );
				if ( 'general_template' === $meta_value || empty( $meta_value ) ) {
					return false;
				}
				$terms = get_terms(
					[
						'taxonomy'   => $meta_value,
						'number'     => 1,
						'hide_empty' => false,
					]
				);
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					return get_term_link( $terms[0] );
				}
				return false;

			case 'special-templates':
				$meta_value = get_post_meta( $post->ID, '_template_for_special', true );
				if ( '404' === $meta_value ) {
					return home_url( '/abet-404-preview' );
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Set the 404 template.
	 *
	 * @param string $template The template to use.
	 *
	 * @return string The template to use.
	 */
	public static function set_404_template( $template ) {
		global $abet_template_post;

		// Check if we are on a 404 page.
		if ( ! is_404() ) {
			return $template;
		}

		$abet_template_404 = get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => 'special-templates',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'post_name'      => '404',
			]
		);

		if ( $abet_template_404 ) {
			$post_id            = reset( $abet_template_404 );
			$abet_template_post = get_post( $post_id );

			$templates[] = 'abet-404.php';
			$template    = locate_template( $templates );

			if ( ! $template ) {
				$template = ABET_ABSPATH . 'templates/abet-404.php';
			}

			return $template;
		}

		return get_404_template();
	}
}
