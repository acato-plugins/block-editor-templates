<?php
/**
 * Who may create, edit and publish templates.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Admin
 */

namespace Acato\Block_Editor_Templates\Admin;

/**
 * Grants the template capabilities by the minimum roles set on Block Templates > Settings.
 *
 * The template post types use their own capability type (abet_template / abet_templates), so these
 * capabilities never touch those of posts or pages. They are not stored on any role: they are granted
 * when asked for (user_has_cap), from the minimum role of each setting.
 *
 * Each setting holds one or more minimum roles; meeting one of them is enough. Any role of the site
 * that can work in the editor (edit_posts) can be one, custom roles too. A user meets a minimum role
 * when they have that role, or at least the same WordPress publishing
 * capabilities (see self::PUBLISHING_CAPABILITIES). Capabilities that plugins add are left out of the
 * comparison: they say nothing about rank, and roles edited over the years often carry a few stray
 * ones. Administrators (manage_options) always qualify.
 * A minimum role the site does not have lets only administrators through, so a network default naming
 * a role that only exists on other sites never opens a site up.
 *
 * - Edit:    the Block Templates menu, and editing templates, also published ones and other people's.
 * - Publish: publishing templates. Also needs the edit role.
 * - Create:  Add New. Also needs the edit role.
 * - Delete:  moving templates to the trash and deleting them. Also needs the edit role.
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
	const WORDPRESS_ROLES = [ 'administrator', 'editor', 'author', 'contributor' ];

	/**
	 * The WordPress capabilities that rank roles: those of posts, pages, uploads, comments and
	 * categories, and manage_options.
	 *
	 * @var string[]
	 */
	const PUBLISHING_CAPABILITIES = [
		'manage_options',
		'moderate_comments',
		'manage_categories',
		'unfiltered_html',
		'upload_files',
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_private_posts',
		'read_private_posts',
		'publish_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',
		'edit_pages',
		'edit_others_pages',
		'edit_published_pages',
		'edit_private_pages',
		'read_private_pages',
		'publish_pages',
		'delete_pages',
		'delete_others_pages',
		'delete_published_pages',
		'delete_private_pages',
	];

	/**
	 * The minimum role before anyone changes the settings.
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
	}

	/**
	 * Grant the template capabilities a user meets the minimum roles for.
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
				if ( ! self::meets_any( $allcaps, (array) $user->roles, Settings::roles( $setting ) ) ) {
					$allcaps[ $cap ] = false;
					break;
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Whether a user meets at least one of the minimum roles of a setting.
	 *
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @param string[]            $roles   The user's roles.
	 * @param string[]            $minimum The minimum roles.
	 *
	 * @return bool
	 */
	private static function meets_any( $allcaps, $roles, $minimum ) {
		foreach ( $minimum as $role ) {
			if ( self::meets( $allcaps, $roles, $role ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a user meets a minimum role.
	 *
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @param string[]            $roles   The user's roles.
	 * @param string              $role    The minimum role.
	 *
	 * @return bool
	 */
	private static function meets( $allcaps, $roles, $role ) {
		if ( ! empty( $allcaps['manage_options'] ) || in_array( $role, $roles, true ) ) {
			return true;
		}

		// A role the site does not have, or one without any publishing capability left (edited after
		// it was chosen, or defined differently on this site than on the site the network default was
		// chosen on), cannot be ranked: only its own members and administrators meet it. Treating an
		// empty list as met would let every user through, logged-out visitors included.
		$required = self::role_capabilities( $role );
		if ( ! $required ) {
			return false;
		}

		foreach ( $required as $capability ) {
			if ( empty( $allcaps[ $capability ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The WordPress publishing capabilities a role of the current site has.
	 *
	 * @param string $role The role.
	 *
	 * @return string[]|null The capabilities, or null when the site has no such role.
	 */
	private static function role_capabilities( $role ) {
		static $cache = [];

		$key = get_current_blog_id() . ':' . $role;
		if ( ! array_key_exists( $key, $cache ) ) {
			$object        = get_role( $role );
			$cache[ $key ] = $object ? array_values( array_intersect( self::PUBLISHING_CAPABILITIES, array_keys( array_filter( $object->capabilities ) ) ) ) : null;
		}

		return $cache[ $key ];
	}

	/**
	 * The roles that can be picked as a minimum: those that can work in the editor (edit_posts). First
	 * the WordPress roles, from the highest down, then the custom roles by name.
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
			if ( empty( $role['capabilities']['edit_posts'] ) ) {
				continue;
			}

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
	 * Whether a role can be picked as a minimum.
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
	 * Which site a role was picked from does not decide anything: each site checks the chosen role
	 * against its own definition when a capability is asked for (see self::meets()).
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
