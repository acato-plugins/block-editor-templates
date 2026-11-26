<?php
/**
 * Database upgrades between plugin versions.
 *
 * @package    Acato\Block_Editor_Templates
 */

namespace Acato\Block_Editor_Templates;

use Acato\Block_Editor_Templates\Admin\Admin;

/**
 * Brings the data stored by an older version of the plugin up to date.
 *
 * The database version is stored once per network (a network option; on a single site that is an
 * ordinary option). When it is older than DB_VERSION, each newer upgrade step runs once, in order, for
 * every site of the network, and the stored version is raised after each step.
 *
 * It runs on admin_init at priority 1: before the plugin writes anything that depends on the stored
 * data (the template sync runs on admin_init at priority 10). The front end only reads and caches, so
 * it never has to wait for an upgrade, and it is spared the query that reading a network option costs
 * on a network without a persistent object cache.
 *
 * To add a step: raise DB_VERSION, add a method upgrade_to_<version>() and list it in steps(). A step
 * runs for one site at a time, with that site switched to.
 *
 * @package    Acato\Block_Editor_Templates
 */
class Upgrade {

	/**
	 * The database version this code expects. A network without a stored version is at version 1.
	 *
	 * @var int
	 */
	const DB_VERSION = 2;

	/**
	 * Network option holding the database version.
	 *
	 * @var string
	 */
	const OPTION = 'abet_db_version';

	/**
	 * The singleton instance of this class.
	 *
	 * @var Upgrade|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Upgrade The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Upgrade();
		}

		return self::$instance;
	}

	/**
	 * Register the hooks.
	 */
	private function __construct() {
		add_action( 'admin_init', [ self::class, 'maybe_upgrade' ], 1 );
	}

	/**
	 * Run the upgrade steps the network has not had yet.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$version = (int) get_site_option( self::OPTION, 1 );

		if ( $version >= self::DB_VERSION ) {
			return;
		}

		$steps = self::steps();
		for ( $step = $version + 1; $step <= self::DB_VERSION; $step++ ) {
			if ( isset( $steps[ $step ] ) ) {
				self::for_each_site( $steps[ $step ] );
			}
			// Store every step, so an interrupted upgrade continues where it stopped.
			update_site_option( self::OPTION, $step );
		}
	}

	/**
	 * The upgrade steps, keyed by the database version they bring a site to.
	 *
	 * @return array<int, callable> The steps.
	 */
	private static function steps() {
		return [
			2 => [ self::class, 'upgrade_to_2' ],
		];
	}

	/**
	 * Run a callback for every site of the network, or once on a single site.
	 *
	 * @param callable $callback The callback, run with the site switched to.
	 *
	 * @return void
	 */
	private static function for_each_site( $callback ) {
		if ( ! is_multisite() ) {
			call_user_func( $callback );

			return;
		}

		foreach ( get_sites(
			[
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			]
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			call_user_func( $callback );
			restore_current_blog();
		}
	}

	/**
	 * Version 2 (plugin 1.3.0): the template posts are synchronised from the admin only, and the
	 * archive and special page templates are looked up through a transient.
	 *
	 * Older versions cached template post IDs in the object cache for an hour without clearing them
	 * when a template changed. With a persistent object cache those entries can be stale, so they are
	 * removed. The new sync signature and lookup transient do not exist yet; they are cleared anyway, so
	 * the first admin request of each site checks all of its template posts once.
	 *
	 * @return void
	 */
	public static function upgrade_to_2() {
		foreach ( [ '_template_for_posttype', '_template_for_posttype_archive', '_template_for_taxonomy_archive' ] as $meta_field ) {
			wp_cache_delete( 'abet_posts_with_meta_' . md5( $meta_field ) );
		}

		delete_transient( Admin::LOOKUP_TRANSIENT );
		delete_option( Admin::SYNC_OPTION );
	}

	/**
	 * Remove the database version and the template sync data, on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_site_option( self::OPTION );

		self::for_each_site(
			static function () {
				delete_option( 'abet_template_sync' );
				delete_option( 'abet_template_sync_lock' );
				delete_transient( 'abet_template_lookup' );
			}
		);
	}
}
