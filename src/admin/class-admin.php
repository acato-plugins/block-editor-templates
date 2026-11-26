<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

use Acato\Block_Editor_Templates\Lock;
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
	 * The help text of the "Prefill new posts" column, printed hidden in the footer once the
	 * column has been added, as the description of its help button.
	 *
	 * @var string
	 */
	private static $default_content_help = '';

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Admin|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Option holding the signature of the post types, taxonomies and special pages the template posts
	 * were last synchronised for (see self::sync_template_posts()).
	 *
	 * @var string
	 */
	const SYNC_OPTION = 'abet_template_sync';

	/**
	 * Option used as a lock, so two admin requests never create the same template posts twice.
	 *
	 * @var string
	 */
	const SYNC_LOCK = 'abet_template_sync_lock';

	/**
	 * Transient holding the resolved archive and special page template post IDs (see self::lookup()).
	 *
	 * @var string
	 */
	const LOOKUP_TRANSIENT = 'abet_template_lookup';

	/**
	 * The post types of this plugin, also on a block theme (where post_types() leaves some out).
	 *
	 * @var string[]
	 */
	const TEMPLATE_POST_TYPES = [ 'block-templates', 'pt-arch-templates', 'tax-arch-templates', 'special-templates' ];

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
		add_action( 'init', [ self::class, 'register_post_types' ] );
		add_action( 'init', [ self::class, 'register_template_meta' ] );
		// Creating the template posts only has to happen in the admin, and only when what they are
		// created for changed, not on every request (BET-1).
		add_action( 'admin_init', [ self::class, 'sync_template_posts' ] );
		// The block templates are only read by the editor and by the post types REST endpoint it asks,
		// so build them there and not on every request (BET-2).
		add_action( 'load-post.php', [ self::class, 'register_block_templates_for_screen' ] );
		add_action( 'load-post-new.php', [ self::class, 'register_block_templates_for_screen' ] );
		add_filter( 'rest_pre_dispatch', [ self::class, 'refuse_anonymous_template_requests' ], 5, 3 );
		add_filter( 'rest_pre_dispatch', [ self::class, 'register_block_templates_for_rest' ], 10, 3 );
		add_action( 'clean_post_cache', [ self::class, 'flush_template_caches' ], 10, 2 );
		// The REST API writes meta after the post (and after clean_post_cache), so also forget the
		// caches when what a template is for is written.
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
			add_action( $hook, [ self::class, 'flush_on_template_for_change' ], 10, 3 );
		}
		add_filter( 'add_post_metadata', [ self::class, 'refuse_taken_target' ], 10, 4 );
		foreach ( self::TEMPLATE_POST_TYPES as $post_type ) {
			add_filter( 'rest_pre_insert_' . $post_type, [ self::class, 'refuse_taken_target_in_rest' ], 10, 2 );
		}
		add_filter( 'update_post_metadata', [ self::class, 'refuse_taken_target' ], 10, 4 );
		add_filter( 'map_meta_cap', [ self::class, 'lock_template_for' ], 10, 4 );
		add_filter( 'default_content', [ self::class, 'set_default_content' ], 10, 2 );
		add_action( 'admin_notices', [ self::class, 'stale_template_notice' ] );
		add_action( 'admin_post_abet_trash_stale_template', [ self::class, 'trash_stale_template' ] );
		add_action( 'admin_post_abet_trash_all_stale_templates', [ self::class, 'trash_all_stale_templates' ] );
		add_filter( 'post_row_actions', [ self::class, 'remove_row_actions' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_template_for_assets' ] );
		add_action( 'enqueue_block_assets', [ self::class, 'enqueue_block_editor_styles' ] );
		add_action( 'admin_menu', [ self::class, 'admin_menu' ] );
		add_filter( 'display_post_states', [ self::class, 'add_display_post_states' ], 10, 2 );
		add_filter( 'allowed_block_types_all', [ self::class, 'inherit_allowed_block_types' ], PHP_INT_MAX, 2 );
		add_filter( 'manage_block-templates_posts_columns', [ self::class, 'add_default_content_column' ] );
		add_action( 'manage_block-templates_posts_custom_column', [ self::class, 'render_default_content_column' ], 10, 2 );
		add_action( 'admin_footer-edit.php', [ self::class, 'print_default_content_help' ] );

		add_action( 'after_setup_theme', [ self::class, 'register_classic_theme_hooks' ] );
	}

	/**
	 * Register the hooks that only apply to classic themes.
	 *
	 * Archives and special pages are something a block theme provides for itself, so the plugin should
	 * stay out of them there.
	 *
	 * Registered from a hook rather than from the constructor, because the constructor runs while
	 * WordPress is still including plugins and the theme it would have to ask about is not registered
	 * yet. Asked that early, WordPress always answers "not a block theme" (and since 6.8 it says out
	 * loud that it was asked too early), so these hooks used to be registered on block themes as well.
	 *
	 * after_setup_theme is the first moment the theme is known, and it is still early: everything below
	 * runs on init or later.
	 *
	 * @since 1.1.4
	 *
	 * @return void
	 */
	public static function register_classic_theme_hooks() {
		if ( self::is_block_theme() ) {
			return;
		}

		add_filter( 'archive_template', [ self::class, 'get_custom_archive' ] );

		// Load the template for special pages (e.g. the 404 page).
		add_filter( 'template_include', [ self::class, 'set_special_template' ], 99 );
	}

	/**
	 * Whether the active theme is a block theme.
	 *
	 * Guards wp_is_block_theme(), which only exists since WordPress 5.9, so the plugin keeps working on
	 * the WordPress 5.0 minimum it declares support for: without the function the site cannot be a block
	 * theme, so treating it as classic is the correct fallback.
	 *
	 * Do not call this before after_setup_theme: until the theme directory is registered there is no
	 * theme to answer for.
	 *
	 * @return bool True when the active theme is a block theme.
	 */
	private static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
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
					'description'          => _x( 'Templates that are applied to each new post of the matching post type.', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype',
					'for'                  => 'post_type',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
				'pt-arch-templates'  => [
					'single'               => _x( 'Post Type Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Post Type Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Templates that render the archive page of the matching post type.', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_posttype_archive',
					'for'                  => 'post_type',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'tax-arch-templates' => [
					'single'               => _x( 'Taxonomy Archive Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Taxonomy Archive Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Templates that render the archive page of the matching taxonomy.', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_taxonomy_archive',
					'for'                  => 'taxonomy',
					'general_template'     => true,
					'only_for_has_archive' => true,
				],
				'special-templates'  => [
					'single'               => _x( 'Special Template', 'posttype single name global used', 'block-editor-templates' ),
					'plural'               => _x( 'Special Templates', 'posttype plural name global used', 'block-editor-templates' ),
					'description'          => _x( 'Templates for special pages, such as the 404 page.', 'posttype description', 'block-editor-templates' ),
					'meta_field'           => '_template_for_special',
					'for'                  => 'special',
					'general_template'     => false,
					'only_for_has_archive' => false,
				],
			];
			if ( self::is_block_theme() ) {
				unset( $post_types['pt-arch-templates'], $post_types['tax-arch-templates'], $post_types['special-templates'] );
			}
		}

		return $post_types;
	}

	/**
	 * Register the block templates, for one post type or for all of them.
	 *
	 * WordPress only reads a post type's template in the block editor (wp-admin/edit-form-blocks.php)
	 * and in the post types REST endpoint, so this runs when one of those is loaded rather than on
	 * every request, and only for the post type that is asked for. It does the work for a post type
	 * once per request.
	 *
	 * Only published templates are applied: a template is created as a draft and becomes active when
	 * it is published, which is what the publish role controls. That also keeps unpublished template
	 * content out of the post types REST endpoint, which anyone may read.
	 *
	 * @param string $only_post_type The post type to register the template of, or '' for all.
	 *
	 * @return void
	 */
	public static function register_block_templates( $only_post_type = '' ) {
		static $registered = [];

		if ( isset( $registered['*'] ) || isset( $registered[ $only_post_type ] ) ) {
			return;
		}
		$registered[ '' === $only_post_type ? '*' : $only_post_type ] = true;

		self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		$post_ids = self::get_post_type_template_posts();

		// Load the template posts and their meta in two queries instead of two per template.
		_prime_post_caches( $post_ids, false, true );

		foreach ( $post_ids as $post_id ) {
			$post_type = (string) get_post_meta( $post_id, '_template_for_posttype', true );
			if ( '' !== $only_post_type && $post_type !== $only_post_type ) {
				continue;
			}

			// Templates that prefill new posts through default_content (see self::set_default_content())
			// must not also register $object->template, which rebuilds each block via createBlock and
			// therefore drops markup-sourced content such as a heading's or paragraph's text.
			if ( self::use_default_content( $post_id ) ) {
				continue;
			}

			$object = get_post_type_object( $post_type );
			$post   = get_post( $post_id );
			if ( ! $object || ! $post || 'publish' !== $post->post_status || ! has_blocks( $post->post_content ) ) {
				continue;
			}

			$template = self::blocks_to_template( parse_blocks( $post->post_content ) );
			if ( count( $template ) ) {
				$object->template = $template;
			}
		}
	}

	/**
	 * Register the block template of the post type the editor is opening.
	 *
	 * @return void
	 */
	public static function register_block_templates_for_screen() {
		global $typenow;

		if ( is_string( $typenow ) && '' !== $typenow ) {
			self::register_block_templates( $typenow );
		}
	}

	/**
	 * Register the block templates before the post types REST endpoint answers.
	 *
	 * The endpoint returns each post type's template, which the editor asks for: one post type for
	 * /wp/v2/types/<type>, all of them for /wp/v2/types.
	 *
	 * @param mixed            $result  The response so far, passed through unchanged.
	 * @param \WP_REST_Server  $server  The REST server.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed The unchanged response.
	 */
	public static function register_block_templates_for_rest( $result, $server, $request ) {
		// Template content is for logged-in users only (see self::refuse_anonymous_template_requests()).
		if ( ! is_user_logged_in() ) {
			return $result;
		}

		$route = (string) $request->get_route();

		if ( preg_match( '#^/wp/v2/types/([^/]+)#', $route, $matches ) ) {
			self::register_block_templates( sanitize_key( $matches[1] ) );
		} elseif ( 0 === strpos( $route, '/wp/v2/types' ) ) {
			self::register_block_templates();
		}

		return $result;
	}

	/**
	 * Refuse REST requests for templates from visitors who are not logged in.
	 *
	 * Templates can hold text that is not meant for the public, even once published: their content
	 * reaches visitors only as part of the pages they render (an archive, the 404 page) or as the
	 * start of a new post. WordPress would otherwise let anyone read published template posts and
	 * their meta through /wp/v2/<template post type>, because the post types are shown in REST for
	 * the block editor.
	 *
	 * @param mixed            $result  The response so far.
	 * @param \WP_REST_Server  $server  The REST server.
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return mixed The response so far, or an error for an anonymous template request.
	 */
	public static function refuse_anonymous_template_requests( $result, $server, $request ) {
		if ( null !== $result || is_user_logged_in() ) {
			return $result;
		}

		$bases = [];
		foreach ( array_keys( self::post_types() ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( $object ) {
				$bases[] = preg_quote( $object->rest_namespace . '/' . ( $object->rest_base ? $object->rest_base : $post_type ), '#' );
			}
		}

		if ( $bases && preg_match( '#^/(' . implode( '|', $bases ) . ')(/|$)#', (string) $request->get_route() ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you need to be logged in to see templates.', 'block-editor-templates' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return $result;
	}

	/**
	 * Forget what is cached about the template posts when one of them changes.
	 *
	 * Clears the cached template IDs, the resolved archive and special page templates, and the sync
	 * signature, so the next admin request checks again that every template post exists (for example
	 * after one was deleted).
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post.
	 *
	 * @return void
	 */
	public static function flush_template_caches( $post_id, $post ) {
		if ( ! in_array( $post->post_type, self::TEMPLATE_POST_TYPES, true ) ) {
			return;
		}

		foreach ( [ '_template_for_posttype', '_template_for_posttype_archive', '_template_for_taxonomy_archive' ] as $meta_field ) {
			wp_cache_delete( 'abet_posts_with_meta_' . md5( $meta_field ) );
		}
		delete_transient( self::LOOKUP_TRANSIENT );
		delete_option( self::SYNC_OPTION );
	}

	/**
	 * Forget the template caches when the "Template for" meta of a template is added, changed or removed.
	 *
	 * @param int|int[] $meta_ids  The meta ID(s).
	 * @param int       $object_id The post ID.
	 * @param string    $meta_key  The meta key.
	 *
	 * @return void
	 */
	public static function flush_on_template_for_change( $meta_ids, $object_id, $meta_key ) {
		$settings = self::post_types()[ (string) get_post_type( (int) $object_id ) ] ?? null;
		$post     = get_post( (int) $object_id );

		if ( $settings && $post && $settings['meta_field'] === $meta_key ) {
			self::flush_template_caches( $post->ID, $post );
		}
	}

	/**
	 * Refuse to give a template a target that another template of the same kind already has.
	 *
	 * One template per post type, taxonomy or special page: with two, which one applies would depend
	 * on their dates. The plugin's own sync only creates templates for targets without one, so it is
	 * never refused. A trashed template does not hold its target.
	 *
	 * @param null|bool $check      Null to let WordPress write the meta, anything else to stop it.
	 * @param int       $object_id  The post ID.
	 * @param string    $meta_key   The meta key.
	 * @param mixed     $meta_value The new value.
	 *
	 * @return null|bool False to refuse the write, otherwise $check unchanged.
	 */
	public static function refuse_taken_target( $check, $object_id, $meta_key, $meta_value ) {
		if ( null !== $check ) {
			return $check;
		}

		return self::is_target_taken( (string) get_post_type( (int) $object_id ), $meta_key, $meta_value, (int) $object_id ) ? false : $check;
	}

	/**
	 * Whether another, non-trashed template of a template post type is already for a target.
	 *
	 * @param string $post_type  The template post type.
	 * @param string $meta_key   The meta key being written; only its post type's "Template for" field counts.
	 * @param mixed  $target     The target.
	 * @param int    $exclude_id The template being saved, 0 for a new one.
	 *
	 * @return bool
	 */
	private static function is_target_taken( $post_type, $meta_key, $target, $exclude_id ) {
		$settings = self::post_types()[ $post_type ] ?? null;
		if ( ! $settings || $settings['meta_field'] !== $meta_key || ! is_scalar( $target ) || '' === (string) $target ) {
			return false;
		}

		$taken = get_posts(
			[
				'post_type'    => $post_type,
				'post_status'  => 'any',
				'fields'       => 'ids',
				'numberposts'  => 1,
				'post__not_in' => $exclude_id ? [ $exclude_id ] : [],
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only when a template's target is written.
				'meta_key'     => $meta_key,
				'meta_value'   => (string) $target,
			]
		);

		return (bool) $taken;
	}

	/**
	 * Refuse a REST save that gives a template a target another template already has, before
	 * anything is written, with a message the editor shows.
	 *
	 * The meta write itself is stopped by self::refuse_taken_target(), but by then the REST API has already
	 * saved the post and can only report a generic error.
	 *
	 * @param \stdClass|\WP_Error $prepared_post The post about to be saved.
	 * @param \WP_REST_Request    $request       The request.
	 *
	 * @return \stdClass|\WP_Error The post, or an error when the target is taken.
	 */
	public static function refuse_taken_target_in_rest( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$settings = self::post_types()[ (string) ( $prepared_post->post_type ?? '' ) ] ?? null;
		$meta     = $request->get_param( 'meta' );
		if ( ! $settings || ! is_array( $meta ) || ! isset( $meta[ $settings['meta_field'] ] ) ) {
			return $prepared_post;
		}

		if ( self::is_target_taken( (string) $prepared_post->post_type, (string) $settings['meta_field'], $meta[ $settings['meta_field'] ], (int) ( $prepared_post->ID ?? 0 ) ) ) {
			return new \WP_Error(
				'abet_template_target_taken',
				__( 'Another template is already for this. Choose something else, or edit that template.', 'block-editor-templates' ),
				[ 'status' => 400 ]
			);
		}

		return $prepared_post;
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
	 * Apply the target post type's allowed blocks to its Post Type Template editor.
	 *
	 * A Post Type Template (the 'block-templates' post type) is edited in its own editor, so block
	 * restrictions registered for the target post type via 'allowed_block_types_all' do not normally
	 * apply and every block would be selectable. This re-runs that filter with a context that mimics
	 * the target post type, so the template offers exactly the blocks the real post type allows.
	 *
	 * @param bool|string[]            $allowed_block_types Allowed block types, or true for all of them.
	 * @param \WP_Block_Editor_Context $context             The current block editor context.
	 *
	 * @return bool|string[] The (possibly restricted) allowed block types.
	 */
	public static function inherit_allowed_block_types( $allowed_block_types, $context ) {
		static $running = false;

		if ( $running || ! $context instanceof \WP_Block_Editor_Context || ! $context->post instanceof WP_Post ) {
			return $allowed_block_types;
		}

		if ( 'block-templates' !== $context->post->post_type ) {
			return $allowed_block_types;
		}

		$target_post_type = get_post_meta( $context->post->ID, '_template_for_posttype', true );
		if ( empty( $target_post_type ) || ! post_type_exists( $target_post_type ) ) {
			return $allowed_block_types;
		}

		// Re-run the filter with a context that looks like the target post type, so restrictions
		// registered for that post type apply here too. The static guard prevents infinite recursion.
		$proxy_context = new \WP_Block_Editor_Context(
			[
				'name' => $context->name,
				'post' => new \WP_Post(
					(object) [
						'ID'        => 0,
						'post_type' => $target_post_type,
					]
				),
			]
		);

		$running = true;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Deliberately re-applying WordPress core's own filter, not defining a plugin hook.
		$allowed_block_types = apply_filters( 'allowed_block_types_all', $allowed_block_types, $proxy_context );
		$running             = false;

		return $allowed_block_types;
	}

	/**
	 * Register the per-template "prefill new posts" setting so it can be edited from the block editor.
	 *
	 * @return void
	 */
	public static function register_template_meta() {
		foreach ( self::post_types() as $post_type => $settings ) {
			register_post_meta(
				$post_type,
				(string) $settings['meta_field'],
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => static function ( $value ) use ( $post_type ) {
						$value = sanitize_key( (string) $value );

						return array_key_exists( $value, self::template_for_choices( $post_type ) ) ? $value : '';
					},
					// Once chosen it is fixed, see self::lock_template_for().
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				]
			);
		}

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
	 * Refuse to change what a template is for, once it is set.
	 *
	 * It is chosen once, for a template made with Add New; the templates the plugin creates have it
	 * from the start. Changing it would leave the old target without a template, so the sync would
	 * create another. do_not_allow stops super admins too, where a meta auth_callback does not.
	 *
	 * @param string[] $caps    The primitive capabilities the check needs.
	 * @param string   $cap     The capability being checked.
	 * @param int      $user_id The user ID.
	 * @param mixed[]  $args    For the meta capabilities: the post ID and the meta key.
	 *
	 * @return string[] The capabilities, or do_not_allow.
	 */
	public static function lock_template_for( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, [ 'edit_post_meta', 'add_post_meta', 'delete_post_meta' ], true ) || empty( $args[0] ) || empty( $args[1] ) ) {
			return $caps;
		}

		$settings = self::post_types()[ (string) get_post_type( (int) $args[0] ) ] ?? null;
		if ( $settings && $settings['meta_field'] === $args[1] && '' !== (string) get_post_meta( (int) $args[0], (string) $args[1], true ) ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}

	/**
	 * What a template of a template post type can be for: the General Template, where the post type
	 * has one, and every target of self::template_targets().
	 *
	 * @param string $post_type The template post type.
	 *
	 * @return array<string, string> Values with their names.
	 */
	public static function template_for_choices( $post_type ) {
		$settings = self::post_types()[ $post_type ] ?? null;
		$choices  = [];

		if ( $settings && $settings['general_template'] ) {
			$choices['general_template'] = __( 'General Template', 'block-editor-templates' );
		}

		return $choices + self::template_targets( $post_type );
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

		if ( ! self::$registered_blocks ) {
			self::$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		}

		$post_ids = self::get_post_type_template_posts();
		_prime_post_caches( $post_ids, false, true );

		foreach ( $post_ids as $post_id ) {
			if ( ! self::use_default_content( $post_id ) || get_post_meta( $post_id, '_template_for_posttype', true ) !== $post->post_type ) {
				continue;
			}

			$template_post = get_post( $post_id );
			// A draft template is not applied: the plugin only uses published templates.
			if ( ! $template_post || 'publish' !== $template_post->post_status || ! has_blocks( $template_post->post_content ) ) {
				break;
			}

			// parse_blocks() -> serialize_blocks() round-trips the original markup as-is, so
			// markup-sourced content (heading/paragraph text) is preserved with no HTML parsing.
			$prefilled = serialize_blocks( self::apply_placeholders( parse_blocks( $template_post->post_content ) ) );

			// Fall back to the original content rather than blanking the post if serialization yields nothing.
			return '' !== trim( $prefilled ) ? $prefilled : $content;
		}

		return $content;
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
					$placeholder_name = $attribute_name . 'Placeholder';

					if ( ! isset( $attributes[ $placeholder_name ] ) ) {
						continue;
					}

					if ( isset( $block['attrs'][ $attribute_name ] ) ) {
						$block['attrs'][ $placeholder_name ] = $block['attrs'][ $attribute_name ];
						unset( $block['attrs'][ $attribute_name ] );
						continue;
					}

					// Markup-sourced content (source: html / rich-text) is no attribute, so the editor
					// mirrors it into its "...Placeholder" counterpart instead. Should a template have
					// been saved before the editor could empty the block itself, the same text is still
					// in the markup and would be copied as real content, so remove it here as well.
					$block = self::strip_placeholder_from_markup( $block, $block['attrs'][ $placeholder_name ] ?? '' );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::apply_placeholders( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Remove placeholder text from a block's own markup.
	 *
	 * Only an element's complete text content is removed (">text<" becomes "><"), so a placeholder that
	 * happens to be a substring of the block's content leaves the markup untouched.
	 *
	 * @param array<mixed> $block The block as provided by parse_blocks().
	 * @param mixed        $text  The placeholder text to remove.
	 *
	 * @return array<mixed> The block with the placeholder text removed from its markup.
	 */
	private static function strip_placeholder_from_markup( $block, $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $block;
		}

		$needle      = '>' . $text . '<';
		$replacement = '><';

		if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
			$block['innerHTML'] = self::replace_first( $block['innerHTML'], $needle, $replacement );
		}

		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $index => $chunk ) {
				if ( is_string( $chunk ) ) {
					$block['innerContent'][ $index ] = self::replace_first( $chunk, $needle, $replacement );
				}
			}
		}

		return $block;
	}

	/**
	 * Replace the first occurrence of a substring.
	 *
	 * @param string $haystack    The string to search in.
	 * @param string $needle      The substring to replace.
	 * @param string $replacement The replacement.
	 *
	 * @return string The resulting string, unchanged when the needle does not occur.
	 */
	private static function replace_first( $haystack, $needle, $replacement ) {
		$position = strpos( $haystack, $needle );

		if ( false === $position ) {
			return $haystack;
		}

		return substr_replace( $haystack, $replacement, $position, strlen( $needle ) );
	}

	/**
	 * Enqueue assets for dynamic blocks for the admin.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		// Only load on the block-templates editor (new and existing posts), not on the list screen.
		// get_queried_object_id() is unreliable in wp-admin, so rely on the current screen instead.
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || 'block-templates' !== $screen->post_type ) {
			return;
		}

		if ( ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.js' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Surface a missing build instead of enqueuing a 404.
			error_log( 'block-editor-templates-admin (admin.js) isn`t found. Forgot to run `npm run build`?' );

			return;
		}

		$script_asset = self::script_asset();

		wp_enqueue_script(
			'block-editor-templates-admin',
			ABET_ASSETS_URL . 'admin.js',
			$script_asset['dependencies'] ?? [],
			$script_asset['version'] ?? ABET_VERSION,
			false
		);

		// Load the editor UI's JavaScript translations from the WordPress.org language pack.
		wp_set_script_translations( 'block-editor-templates-admin', 'block-editor-templates' );
	}

	/**
	 * Load the "Template for" panel in the editor of every template post type.
	 *
	 * A template made with Add New is not for anything yet; the panel lets its author choose what. Once
	 * chosen, or for the templates the plugin creates itself, it only says what the template is for.
	 *
	 * @return void
	 */
	public static function enqueue_template_for_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! array_key_exists( $screen->post_type, self::post_types() ) || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . 'template-for.js' ) ) {
			return;
		}

		$post_type  = $screen->post_type;
		$meta_field = (string) self::post_types()[ $post_type ]['meta_field'];
		$taken      = array_count_values( array_map( 'strval', self::get_created_templates( $meta_field ) ) );
		$choices    = [];

		foreach ( self::template_for_choices( $post_type ) as $value => $label ) {
			$choices[] = [
				'value' => (string) $value,
				'label' => $label,
				'taken' => ! empty( $taken[ (string) $value ] ),
			];
		}

		$asset  = self::script_asset( 'template-for' );
		$handle = 'block-editor-templates-template-for';

		wp_enqueue_script( $handle, ABET_ASSETS_URL . 'template-for.js', $asset['dependencies'] ?? [], $asset['version'] ?? ABET_VERSION, true );
		wp_set_script_translations( $handle, 'block-editor-templates' );
		wp_add_inline_script(
			$handle,
			'window.abetTemplateFor = ' . wp_json_encode(
				[
					'metaKey' => $meta_field,
					'choices' => $choices,
				],
				JSON_HEX_TAG | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
	}

	/**
	 * Enqueue the placeholder badge styling into the block editor canvas.
	 *
	 * The badge is drawn on block wrappers, which live inside the editor's iframed canvas since
	 * WordPress 6.3. Styles enqueued on admin_enqueue_scripts only reach the outer admin document, so
	 * the badge stylesheet must go through enqueue_block_assets instead - that is the hook whose styles
	 * WordPress injects into the canvas iframe. The is_admin() guard keeps it out of the front end.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_styles() {
		if ( ! is_admin() || ! file_exists( ABET_ABSPATH . ABET_ASSETS_DIR . 'admin.css' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'block-templates' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'block-editor-templates-admin',
			ABET_ASSETS_URL . 'admin.css',
			[],
			self::script_asset()['version'] ?? ABET_VERSION
		);
	}

	/**
	 * Load the build manifest that `npm run build` writes next to the compiled assets.
	 *
	 * The manifest's `version` is a content hash covering every file in the entry chunk - the JavaScript
	 * and the extracted CSS alike - so it changes on any rebuild that changes the output, in every
	 * environment, and stays stable otherwise. That makes it a better cache-buster than a plugin version
	 * bump or a file modification time: it never busts the cache needlessly and never keeps it too long.
	 *
	 * @param string $entry The build entry name, e.g. 'admin' or 'list-preview'.
	 *
	 * @return array{dependencies?: non-empty-string[], version?: string} The manifest, or an empty array when absent.
	 */
	public static function script_asset( $entry = 'admin' ) {
		$path = ABET_ABSPATH . ABET_ASSETS_DIR . $entry . '.asset.php';

		return file_exists( $path ) ? require $path : [];
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
	 * Make sure every template post exists, when something it depends on changed.
	 *
	 * Runs on admin_init. The template posts are only edited in the admin, and the front end only uses
	 * published templates, which an editor publishes in the admin, so the front end never needs this.
	 *
	 * The posts are only checked when the signature of what they are created for (the post types,
	 * taxonomies, special pages, theme type and plugin version) differs from the one stored after the
	 * last check, or when a template post changed since (see self::flush_template_caches()). Otherwise
	 * this costs no queries: the signature is an autoloaded option.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function sync_template_posts() {
		// admin_init also fires for anonymous admin-ajax.php and admin-post.php requests; leave the
		// work to someone who is logged in, so a visitor never triggers it.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$signature = self::sync_signature();
		if ( get_option( self::SYNC_OPTION ) === $signature || ! Lock::acquire( self::SYNC_LOCK ) ) {
			return;
		}

		try {
			self::create_post_type_posts();
			if ( ! self::is_block_theme() ) {
				self::create_taxonomy_posts();
				self::create_special_pages();
			}
			update_option( self::SYNC_OPTION, $signature, true );
		} finally {
			Lock::release( self::SYNC_LOCK );
		}
	}

	/**
	 * A hash of everything the template posts are created for.
	 *
	 * @return string The signature.
	 */
	private static function sync_signature() {
		$post_types = [];
		foreach ( self::registered_post_types() as $slug => $object ) {
			$post_types[ $slug ] = [ (bool) $object->has_archive, self::uses_block_editor( $slug ) ];
		}

		$parts = [
			'version'    => ABET_VERSION,
			'block'      => self::is_block_theme(),
			'post_types' => $post_types,
		];
		if ( ! $parts['block'] ) {
			$parts['taxonomies'] = array_values( get_taxonomies( [ 'public' => true ] ) );
			$parts['special']    = array_map( 'strval', array_keys( self::special_pages() ) );
		}

		return md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * The post types that can get a template: public built-in ones and all custom ones exposed to the
	 * REST API, except attachments and the plugin's own post types.
	 *
	 * @return array<string, \WP_Post_Type> The post type objects, keyed by slug.
	 */
	private static function registered_post_types() {
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

		return $registered_post_types;
	}

	/**
	 * What templates of a template post type can be for, apart from the General Template.
	 *
	 * The same list the sync creates templates for, and the "Template for" choices in the editor.
	 *
	 * @param string $slug The template post type.
	 *
	 * @return array<string, string> Post type, taxonomy or special page slugs, with their names.
	 */
	public static function template_targets( $slug ) {
		$settings = self::post_types()[ $slug ] ?? null;
		$targets  = [];

		if ( ! $settings ) {
			return $targets;
		}

		if ( 'post_type' === $settings['for'] ) {
			foreach ( self::registered_post_types() as $post_type => $object ) {
				if ( true === $settings['only_for_has_archive'] && false === $object->has_archive ) {
					continue;
				}
				// A Post Type Template is edited in the block editor, so only offer it for post types
				// that actually use the block editor. show_in_rest alone does not imply editor support.
				if ( false === $settings['general_template'] && ! self::uses_block_editor( $post_type ) ) {
					continue;
				}
				$targets[ $post_type ] = (string) $object->labels->name;
			}
		} elseif ( 'taxonomy' === $settings['for'] ) {
			foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy => $object ) {
				$targets[ $taxonomy ] = (string) $object->labels->name;
			}
		} elseif ( 'special' === $settings['for'] ) {
			foreach ( self::special_pages() as $special => $config ) {
				$targets[ (string) $special ] = (string) ( $config['name'] ?? $special );
			}
		}

		return $targets;
	}

	/**
	 * The meta values (what a template is for) of the existing template posts with a given meta field.
	 *
	 * Not cached: it is only asked when the template posts are synchronised (where a stale answer would
	 * create a template twice) and when a template is opened in the editor.
	 *
	 * @param string $meta_field The meta field, such as '_template_for_posttype'.
	 *
	 * @return string[] The meta values.
	 */
	public static function get_created_templates( $meta_field ) {
		$post_ids = get_posts(
			[
				'numberposts' => -1,
				'post_type'   => self::TEMPLATE_POST_TYPES,
				'post_status' => 'any',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only runs when the template posts are synchronised, or a template is edited.
				'meta_key'    => $meta_field,
				'fields'      => 'ids',
			]
		);

		// One query for the meta of all of them, instead of one per post.
		update_meta_cache( 'post', $post_ids );

		$created_templates = [];
		foreach ( $post_ids as $post_id ) {
			$meta_value = get_post_meta( $post_id, $meta_field, true );
			if ( ! empty( $meta_value ) ) {
				$created_templates[] = $meta_value;
			}
		}

		return $created_templates;
	}

	/**
	 * Create the General Template of a template post type when it is missing.
	 *
	 * @param string   $slug              The template post type.
	 * @param string   $meta_field        The meta field that says what a template is for.
	 * @param string[] $created_templates The meta values of the existing templates.
	 *
	 * @return string[] The meta values without the General Template.
	 */
	private static function ensure_general_template( $slug, $meta_field, $created_templates ) {
		if ( ! in_array( 'general_template', $created_templates, true ) ) {
			wp_insert_post(
				[
					'post_type'   => $slug,
					'post_title'  => __( '_General Template', 'block-editor-templates' ),
					'post_status' => 'draft',
					'meta_input'  => [
						$meta_field => 'general_template',
					],
				]
			);
		}

		return array_values( array_diff( $created_templates, [ 'general_template' ] ) );
	}

	/**
	 * Create the missing templates of one template post type: one per target (see
	 * self::template_targets()), plus its General Template where it has one.
	 *
	 * A target counts as having a template while any non-trashed template is for it, so a template
	 * moved to the trash is created again, as a new draft.
	 *
	 * @param string $slug The template post type.
	 *
	 * @return void
	 */
	private static function sync_template_type( $slug ) {
		$settings = self::post_types()[ $slug ] ?? null;
		if ( ! $settings ) {
			return;
		}

		$meta_field = (string) $settings['meta_field'];
		$created    = array_map( 'strval', self::get_created_templates( $meta_field ) );

		if ( $settings['general_template'] ) {
			$created = self::ensure_general_template( $slug, $meta_field, $created );
		}

		foreach ( self::template_targets( $slug ) as $target => $label ) {
			if ( in_array( (string) $target, $created, true ) ) {
				continue;
			}

			wp_insert_post(
				[
					'post_type'   => $slug,
					'post_title'  => $label,
					'post_status' => 'draft',
					'meta_input'  => [
						$meta_field => (string) $target,
					],
				]
			);
		}
	}

	/**
	 * Create the missing Post Type Templates and Post Type Archive Templates.
	 *
	 * Called by self::sync_template_posts().
	 *
	 * @return void
	 */
	public static function create_post_type_posts() {
		self::sync_template_types( 'post_type' );
	}

	/**
	 * Create the missing Taxonomy Archive Templates.
	 *
	 * Called by self::sync_template_posts().
	 *
	 * @return void
	 */
	public static function create_taxonomy_posts() {
		self::sync_template_types( 'taxonomy' );
	}

	/**
	 * Create every template post type's missing templates for one kind of target.
	 *
	 * @param string $kind 'post_type', 'taxonomy' or 'special' (the 'for' of self::post_types()).
	 *
	 * @return void
	 */
	private static function sync_template_types( $kind ) {
		foreach ( self::post_types() as $slug => $settings ) {
			if ( $kind === $settings['for'] ) {
				self::sync_template_type( $slug );
			}
		}
	}

	/**
	 * Definitions of the special pages that can have a template.
	 *
	 * Each entry is keyed by its slug (stored in the _template_for_special meta) and describes how the
	 * page is created, previewed and rendered:
	 *  - name:      Label used for the generated template post.
	 *  - condition: Callable returning true on the front-end request this page represents (e.g. 'is_404').
	 *  - template:  Template file to load, resolved via the theme first and then the plugin's templates/ dir.
	 *  - preview:   Front-end preview URL, or false when there is none.
	 *
	 * Extend this through the 'abet_special_pages' filter to add your own special pages.
	 *
	 * @return array<string, array<string, mixed>> The special page definitions, keyed by slug.
	 */
	public static function special_pages() {
		return (array) apply_filters(
			'abet_special_pages',
			[
				'404' => [
					'name'      => __( '404 page', 'block-editor-templates' ),
					'condition' => 'is_404',
					'template'  => 'abet-404.php',
					'preview'   => home_url( '/abet-404-preview' ),
				],
			]
		);
	}

	/**
	 * Create the missing Special Templates.
	 *
	 * Called by self::sync_template_posts().
	 *
	 * @return void
	 */
	public static function create_special_pages() {
		self::sync_template_types( 'special' );
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
			if ( 'general_template' === self::template_for( $post ) ) {
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

			/**
			 * Filters the capability_type of the post_type.
			 *
			 * Allow overriding the base capability type for finer access control.
			 *
			 * Since 1.3.0 the default is the plugin's own [ 'abet_template', 'abet_templates' ], whose
			 * capabilities follow the minimum roles on Block Templates > Settings. Return anything
			 * else and those settings no longer apply to the post type.
			 *
			 * @param string|array $capability_type The capability type as defined by WordPress, [ 'abet_template', 'abet_templates' ] by default.
			 *                                      Filter can return a string or a 2-element array.
			 *                                      See function get_post_type_capabilities for extensive documentation.
			 * @param string       $post_type_slug  The post type for which the capability is overridden.
			 *
			 * @since 1.0.0
			 * @see   get_post_type_capabilities
			 */
			$capability_type = apply_filters( 'acato/block_editor_templates/post_type/capability_type', Capabilities::CAPABILITY_TYPE, $post_type_slug );

			$args = [
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
				'capability_type'     => $capability_type,
				'capabilities'        => [
					// Add New follows the "create" role (see Capabilities). With another capability
					// type from the filter it stays blocked, as before the role settings existed.
					'create_posts' => Capabilities::CAPABILITY_TYPE === $capability_type ? 'create_abet_templates' : 'do_not_allow',
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
		// Whoever may edit templates (the "edit" role on Block Templates > Settings) gets the menu.
		add_menu_page( 'Block Templates', 'Block Templates', self::edit_capability( 'block-templates' ), 'edit.php?post_type=block-templates', '', 'dashicons-media-code' );
		foreach ( self::post_types() as $slug => $settings ) {
			add_submenu_page( 'edit.php?post_type=block-templates', (string) $settings['plural'], (string) $settings['plural'], self::edit_capability( $slug ), 'edit.php?post_type=' . $slug );
		}
	}

	/**
	 * The capability that edits the templates of a template post type.
	 *
	 * @param string $post_type The template post type.
	 *
	 * @return string The capability.
	 */
	private static function edit_capability( $post_type ) {
		$object = get_post_type_object( $post_type );

		return $object ? (string) $object->cap->edit_posts : 'manage_options';
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
			// ?post_type[]=... reaches here as an array; core has already limited it to registered types.
			if ( ! is_string( $meta_value ) ) {
				return $archive_template;
			}
			$templates = [
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

		$abet_template_post = self::lookup(
			$post_type . ':' . $meta_value,
			static function () use ( $post_type, $meta_key, $meta_value ) {
				$posts = get_posts(
					[
						'fields'     => 'ids',
						'post_type'  => $post_type,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The answer is cached, see self::lookup().
						'meta_query' => [
							[
								'key'     => $meta_key,
								'value'   => [ 'general_template', $meta_value ],
								'compare' => 'IN',
							],
						],
					]
				);

				if ( count( $posts ) < 2 ) {
					return $posts ? $posts[0] : 0;
				}

				// Both a General Template and one for this archive: the specific one wins.
				update_meta_cache( 'post', $posts );
				foreach ( $posts as $post_id ) {
					if ( 'general_template' !== get_post_meta( $post_id, $meta_key, true ) ) {
						return $post_id;
					}
				}

				return 0;
			}
		);

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
		$settings = self::post_types()[ (string) get_post_type( $post ) ] ?? null;
		if ( ! $settings ) {
			return $post_states;
		}

		$target = self::template_for( $post );

		if ( 'general_template' === $target ) {
			return $post_states;
		}

		// Made with Add New, and not yet told what it is for.
		if ( '' === $target ) {
			$post_states['abet_unlinked'] = esc_html__( 'Not in use: choose “Template for”', 'block-editor-templates' );

			return $post_states;
		}

		switch ( $settings['for'] ) {
			case 'taxonomy':
				$exists = taxonomy_exists( $target );
				break;
			case 'special':
				$exists = array_key_exists( $target, self::special_pages() );
				break;
			default:
				$exists = post_type_exists( $target );
		}

		if ( ! $exists ) {
			$post_states['deleted'] = esc_html__( 'Item is deleted', 'block-editor-templates' );
		}

		return $post_states;
	}

	/**
	 * What a template is for: the value of its post type's "Template for" meta field.
	 *
	 * @param WP_Post $post The template post.
	 *
	 * @return string A post type, taxonomy or special page slug, 'general_template', or '' when unset.
	 */
	private static function template_for( $post ) {
		$settings = self::post_types()[ $post->post_type ] ?? null;

		return $settings ? (string) get_post_meta( $post->ID, (string) $settings['meta_field'], true ) : '';
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

		$help = __( 'When enabled, new posts of this post type start with this template’s blocks and the content entered in them. When disabled, new posts get only the empty block structure. Only applies when creating a post in the WordPress admin.', 'block-editor-templates' );

		// A help button that shows the text as a tooltip on hover and keyboard focus (styled in
		// list-preview.scss, dismissed with Escape by List_Table). The text is drawn from data-tip so
		// it stays out of the header's own text, which Screen Options uses as the column's label;
		// screen readers get it as the button's description from a hidden copy in the footer.
		$columns['abet_default_content'] = sprintf(
			'%1$s <button type="button" class="abet-help" aria-label="%2$s" aria-describedby="abet-help-default-content" data-tip="%3$s"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span></button>',
			esc_html__( 'Prefill new posts', 'block-editor-templates' ),
			esc_attr__( 'About “Prefill new posts”', 'block-editor-templates' ),
			esc_attr( $help )
		);

		self::$default_content_help = $help;

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Print the hidden description of the "Prefill new posts" help button.
	 *
	 * @return void
	 */
	public static function print_default_content_help() {
		if ( '' === self::$default_content_help ) {
			return;
		}

		printf( '<p id="abet-help-default-content" hidden>%s</p>', esc_html( self::$default_content_help ) );
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
				// One term link per taxonomy, however many rows show it.
				static $term_links = [];
				if ( ! array_key_exists( $meta_value, $term_links ) ) {
					$terms = get_terms(
						[
							'taxonomy'               => $meta_value,
							'number'                 => 1,
							'hide_empty'             => false,
							'update_term_meta_cache' => false,
						]
					);

					// get_term_link() returns a WP_Error for a term it cannot link to, which would end
					// up as the row's "View" link; this method promises a URL or false.
					$link                      = ( empty( $terms ) || is_wp_error( $terms ) ) ? false : get_term_link( $terms[0] );
					$term_links[ $meta_value ] = is_wp_error( $link ) ? false : $link;
				}

				return $term_links[ $meta_value ];

			case 'special-templates':
				$meta_value    = get_post_meta( $post->ID, '_template_for_special', true );
				$special_pages = self::special_pages();

				return $special_pages[ $meta_value ]['preview'] ?? false;

			default:
				return false;
		}
	}

	/**
	 * Load the template for the special page matching the current request, if any.
	 *
	 * Walks the special pages (see self::special_pages()), and for the first one whose condition
	 * matches the current request it looks up its published template post (by the _template_for_special
	 * meta) and returns its template file, resolved via the theme first and then the plugin's templates/ dir.
	 *
	 * @param string $template The template WordPress resolved for this request.
	 *
	 * @return string The template to use.
	 */
	public static function set_special_template( $template ) {
		global $abet_template_post;

		foreach ( self::special_pages() as $special_page_slug => $config ) {
			$condition = $config['condition'] ?? null;
			if ( ! is_callable( $condition ) || ! call_user_func( $condition ) ) {
				continue;
			}

			$post_id = self::get_special_template_post_id( (string) $special_page_slug );
			if ( ! $post_id ) {
				break;
			}

			$abet_template_post = get_post( $post_id );

			$template_file = $config['template'] ?? '';
			// The file comes from the abet_special_pages filter: keep it inside the templates folder.
			if ( ! is_string( $template_file ) || 0 !== validate_file( $template_file ) ) {
				break;
			}
			$located = $template_file ? locate_template( [ $template_file ] ) : '';
			if ( $located ) {
				return $located;
			}

			if ( $template_file && file_exists( ABET_ABSPATH . 'templates/' . $template_file ) ) {
				return ABET_ABSPATH . 'templates/' . $template_file;
			}

			break;
		}

		return $template;
	}

	/**
	 * Get the ID of the published template post for a special page slug.
	 *
	 * @param string $slug The special page slug as stored in the _template_for_special meta.
	 *
	 * @return int The post ID, or 0 when there is no published template.
	 */
	private static function get_special_template_post_id( $slug ) {
		return self::lookup(
			'special-templates:' . $slug,
			static function () use ( $slug ) {
				$posts = get_posts(
					[
						'fields'         => 'ids',
						'post_type'      => 'special-templates',
						'posts_per_page' => 1,
						'post_status'    => 'publish',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The answer is cached, see self::lookup().
						'meta_key'       => '_template_for_special',
						'meta_value'     => $slug,
					]
				);

				return $posts ? reset( $posts ) : 0;
			}
		);
	}

	/**
	 * Get a resolved template post ID from the lookup cache, resolving and storing it when missing.
	 *
	 * All answers share one transient without expiry, which WordPress autoloads, so a cached answer
	 * costs no query, with or without a persistent object cache. "No template" is cached too (as 0).
	 * The transient is cleared whenever a template post changes (see self::flush_template_caches()).
	 *
	 * @param string   $key     What is looked up, such as 'special-templates:404'.
	 * @param callable $resolve Returns the template post ID, or 0 when there is none.
	 *
	 * @return int The template post ID, or 0.
	 */
	private static function lookup( $key, $resolve ) {
		$lookups = get_transient( self::LOOKUP_TRANSIENT );
		if ( ! is_array( $lookups ) ) {
			$lookups = [];
		}

		if ( ! array_key_exists( $key, $lookups ) ) {
			$lookups[ $key ] = (int) call_user_func( $resolve );
			set_transient( self::LOOKUP_TRANSIENT, $lookups );
		}

		return (int) $lookups[ $key ];
	}
}
