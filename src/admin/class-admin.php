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
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_post_type_posts' ], 100 );
		add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'register_block_templates' ], 999 );
		add_filter( 'post_row_actions', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'remove_row_actions' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'enqueue_admin_assets' ] );
		add_action( 'admin_menu', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'admin_menu' ] );
		add_filter( 'display_post_states', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'add_display_post_states' ], 10, 2 );

		if ( ! wp_is_block_theme() ) {
			add_action( 'init', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'create_taxonomy_posts' ], 100 );
			add_filter( 'archive_template', [ 'Acato\Block_Editor_Templates\Admin\Admin', 'get_custom_archive' ] );
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
			];
			if ( wp_is_block_theme() ) {
				unset( $post_types['pt-arch-templates'], $post_types['tax-arch-templates'] );
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

		// Get all templates.
		$cache_key      = 'abet_posts_with_meta_' . md5( '_template_for_posttype' );
		$template_posts = wp_cache_get( $cache_key );

		if ( false === $template_posts ) {
			$template_posts = get_posts(
				[
					'numberposts' => -1,
					'post_type'   => 'any',
					'post_status' => 'any',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- This is cached.
					'meta_key'    => '_template_for_posttype',
					'fields'      => 'ids',
				]
			);
			wp_cache_set( $cache_key, $template_posts, '', HOUR_IN_SECONDS );
		}

		foreach ( $template_posts as $post_id ) {
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

			// Get all posts that have the meta field.
			// Get all posts that have the meta field.
			$cache_key            = 'abet_posts_with_meta_' . md5( (string) $settings['meta_field'] );
			$posts_with_templates = wp_cache_get( $cache_key );

			if ( false === $posts_with_templates ) {
				$posts_with_templates = get_posts(
					[
						'numberposts' => -1,
						'post_type'   => 'any',
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
						'post_type'   => 'any',
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
				'supports'            => [ 'title', 'editor' ],
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
				'exclude_from_search' => false,
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
			global $post;

			$post_type  = 'pt-arch-templates';
			$meta_key   = '_template_for_posttype_archive';
			$meta_value = get_post_type( $post );
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
}
