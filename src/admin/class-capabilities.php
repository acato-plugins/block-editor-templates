<?php
/**
 * Who may create, edit and publish templates.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

/**
 * Grants the template capabilities by the roles chosen on Block Templates > Settings.
 *
 * The template post types use their own capability type (abet_template / abet_templates), so these
 * capabilities never touch those of posts or pages. They are not stored on any role: they are granted
 * when asked for (user_has_cap), from the roles chosen for each setting.
 *
 * Each setting holds one or more roles; having one of them is enough. Any role of the site can be
 * chosen, custom roles too: the site admin decides. Administrators (manage_options) always qualify.
 * A chosen role the site does not have lets nobody extra through.
 *
 * - Edit:    the Block Templates menu, and editing templates, also published ones and other people's.
 * - Publish: publishing templates. Also needs the edit role.
 * - Create:  Add New. Also needs the edit role.
 * - Delete:  moving templates to the trash and deleting them. Also needs the edit role.
 *
 * On top of that, a template for a post type or taxonomy also needs the capability to work with that
 * post type or taxonomy (see self::require_target_capability()).
 *
 * A super admin passes every check, as everywhere in WordPress.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */
class Capabilities {

	/**
	 * The WordPress roles, from the highest down. Shown first, as a group of their own.
	 *
	 * @var string[]
	 */
	const WORDPRESS_ROLES = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

	/**
	 * The role chosen before anyone changes the settings, besides administrators.
	 *
	 * @var string
	 */
	const DEFAULT_ROLE = 'editor';

	/**
	 * The capability type of the template post types: singular and plural.
	 *
	 * @var string[]
	 */
	const CAPABILITY_TYPE = [ 'abet_template', 'abet_templates' ];

	/**
	 * The primitive template capabilities, each with the role setting(s) a user must meet for it.
	 *
	 * @var array<string, string[]>
	 */
	const CAPABILITIES = [
		'edit_abet_templates'             => [ 'edit_role' ],
		'edit_others_abet_templates'      => [ 'edit_role' ],
		'edit_published_abet_templates'   => [ 'edit_role' ],
		'edit_private_abet_templates'     => [ 'edit_role' ],
		'read_private_abet_templates'     => [ 'edit_role' ],
		'publish_abet_templates'          => [ 'edit_role', 'publish_role' ],
		'create_abet_templates'           => [ 'edit_role', 'create_role' ],
		'delete_abet_templates'           => [ 'edit_role', 'delete_role' ],
		'delete_others_abet_templates'    => [ 'edit_role', 'delete_role' ],
		'delete_published_abet_templates' => [ 'edit_role', 'delete_role' ],
		'delete_private_abet_templates'   => [ 'edit_role', 'delete_role' ],
	];

	/**
	 * The singleton instance of this class.
	 *
	 * @var Capabilities|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Capabilities The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Capabilities();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 */
	private function __construct() {
		add_filter( 'user_has_cap', [ self::class, 'grant' ], 10, 4 );
		add_filter( 'map_meta_cap', [ self::class, 'require_target_capability' ], 10, 4 );
	}

	/**
	 * Also require the capability of what a template is for to edit, publish or delete it.
	 *
	 * The template for products is then only managed by users who can edit products (edit_products,
	 * or whatever the post type maps edit_posts to), the template for an archive of a taxonomy only by
	 * users who can manage its terms. This comes on top of the role settings. General and special
	 * templates, and templates whose target is no longer registered (so they can still be trashed),
	 * need nothing more.
	 *
	 * @param string[] $caps    The primitive capabilities the check needs.
	 * @param string   $cap     The capability being checked.
	 * @param int      $user_id The user ID.
	 * @param mixed[]  $args    For the post capabilities: the post ID.
	 *
	 * @return string[] The capabilities, with the target's capability added.
	 */
	public static function require_target_capability( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, [ 'edit_post', 'delete_post', 'publish_post' ], true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$post_type = (string) get_post_type( (int) $args[0] );
		if ( ! isset( Admin::post_types()[ $post_type ] ) ) {
			return $caps;
		}

		$target_capability = self::target_capability( $post_type, (string) get_post_meta( (int) $args[0], (string) Admin::post_types()[ $post_type ]['meta_field'], true ) );
		if ( '' !== $target_capability && ! in_array( $target_capability, $caps, true ) ) {
			$caps[] = $target_capability;
		}

		return $caps;
	}

	/**
	 * The capability a user needs for what a template is for.
	 *
	 * @param string $post_type The template post type.
	 * @param string $target    What the template is for: a post type, a taxonomy, a special page or
	 *                          'general_template'.
	 *
	 * @return string The capability, or '' when there is none to add.
	 */
	public static function target_capability( $post_type, $target ) {
		$settings   = Admin::post_types()[ $post_type ] ?? null;
		$capability = '';

		if ( $settings && '' !== $target ) {
			if ( 'post_type' === $settings['for'] ) {
				$object     = get_post_type_object( $target );
				$capability = $object ? (string) $object->cap->edit_posts : '';
			} elseif ( 'taxonomy' === $settings['for'] ) {
				$object     = get_taxonomy( $target );
				$capability = $object ? (string) $object->cap->manage_terms : '';
			}
		}

		/**
		 * Filters the capability a user needs, besides the template capabilities, to edit, publish or
		 * delete a template for a target.
		 *
		 * @param string $capability The capability: the post type's edit_posts or the taxonomy's
		 *                           manage_terms, '' for none.
		 * @param string $post_type  The template post type.
		 * @param string $target     What the template is for.
		 *
		 * @since 1.3.0
		 */
		return (string) apply_filters( 'acato/block_editor_templates/target_capability', $capability, $post_type, $target );
	}

	/**
	 * Grant the template capabilities a user has one of the chosen roles for.
	 *
	 * Only does work when one of the template capabilities is asked for. A visitor who is not logged
	 * in never gets one, and an administrator (manage_options) always does, without reading the
	 * settings.
	 *
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @param string[]            $caps    The primitive capabilities being checked.
	 * @param mixed[]             $args    The original check.
	 * @param \WP_User            $user    The user.
	 *
	 * @return array<string, bool> The capabilities, with the template ones the user qualifies for.
	 */
	public static function grant( $allcaps, $caps, $args, $user ) {
		foreach ( $caps as $cap ) {
			if ( ! isset( self::CAPABILITIES[ $cap ] ) ) {
				continue;
			}

			if ( ! $user->exists() ) {
				$allcaps[ $cap ] = false;
				continue;
			}

			$allcaps[ $cap ] = true;
			if ( ! empty( $allcaps['manage_options'] ) ) {
				continue;
			}

			foreach ( self::CAPABILITIES[ $cap ] as $setting ) {
				if ( ! self::has_any( (array) $user->roles, Settings::roles( $setting ) ) ) {
					$allcaps[ $cap ] = false;
					break;
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Whether a user has at least one of the roles chosen for a setting.
	 *
	 * @param string[] $roles  The user's roles.
	 * @param string[] $chosen The chosen roles.
	 *
	 * @return bool
	 */
	private static function has_any( $roles, $chosen ) {
		return (bool) array_intersect( $roles, $chosen );
	}

	/**
	 * The roles that can be chosen: every role of the site. First the WordPress roles, from the highest
	 * down, then the custom roles by name.
	 *
	 * @param bool $network True for the network default: the roles of every site in the network.
	 *
	 * @return array<int, array{value: string, label: string, group: string}> The group is 'WordPress' or 'custom'.
	 */
	public static function role_choices( $network = false ) {
		static $cache = [];

		$network = $network && is_multisite();
		$key     = $network ? 'network' : (string) get_current_blog_id();
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$roles = $network ? self::network_roles() : wp_roles()->roles;

		$wordpress = [];
		$custom    = [];
		foreach ( $roles as $slug => $role ) {
			$choice = [
				'value' => (string) $slug,
				'label' => translate_user_role( (string) $role['name'] ),
			];

			$rank = array_search( (string) $slug, self::WORDPRESS_ROLES, true );
			if ( false !== $rank ) {
				$wordpress[ $rank ] = $choice + [ 'group' => 'wordpress' ];
			} else {
				$custom[] = $choice + [ 'group' => 'custom' ];
			}
		}

		ksort( $wordpress );
		usort(
			$custom,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['label'], $b['label'] );
			}
		);

		$cache[ $key ] = array_merge( array_values( $wordpress ), $custom );

		return $cache[ $key ];
	}

	/**
	 * The titles of the groups of role_choices().
	 *
	 * @return array<string, string>
	 */
	public static function role_groups() {
		return [
			'wordpress' => _x( 'WordPress', 'group of user roles', 'block-editor-templates' ),
			'custom'    => _x( 'Custom roles', 'group of user roles', 'block-editor-templates' ),
		];
	}

	/**
	 * Whether a role can be chosen.
	 *
	 * @param string $role    The role.
	 * @param bool   $network True for the network default.
	 *
	 * @return bool
	 */
	public static function is_choice( $role, $network = false ) {
		return in_array( $role, wp_list_pluck( self::role_choices( $network ), 'value' ), true );
	}

	/**
	 * The roles of every site in the network, the first definition of each role winning.
	 *
	 * Reads only each site's user_roles option with one small query per site. get_blog_option() would
	 * switch to every site and load all of its autoloaded options, which on a large network costs
	 * far more memory and time than the settings page can afford. role_choices() keeps the answer
	 * for the rest of the request.
	 *
	 * Which site a role was picked from does not decide anything: on each site, only users with that
	 * role on that site qualify.
	 *
	 * @return array<string, array{name: string, capabilities: array<string, bool>}>
	 */
	private static function network_roles() {
		global $wpdb;

		$roles = [];
		foreach ( get_sites(
			[
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			]
		) as $site_id ) {
			$prefix = $wpdb->get_blog_prefix( (int) $site_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One option of another site; the table name comes from WordPress itself.
			$value      = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$prefix}options WHERE option_name = %s", $prefix . 'user_roles' ) );
			$site_roles = maybe_unserialize( (string) $value );
			if ( is_array( $site_roles ) ) {
				$roles += $site_roles;
			}
		}

		return $roles;
	}
}
