<?php
/**
 * A lock across requests.
 *
 * @package    Acato\Block_Editor_Templates
 */

namespace Acato\Block_Editor_Templates;

/**
 * A lock held as a row in an options table, so two requests never do the same one-off work at once.
 *
 * Taking it is atomic: INSERT IGNORE on the unique option_name, where add_option() first reads and
 * then writes. A lock left behind by a request that died is taken over once it is older than its time
 * limit; that takeover is atomic too, because it only succeeds for the request whose UPDATE still
 * finds the old time.
 *
 * @package    Acato\Block_Editor_Templates
 */
class Lock {

	/**
	 * Take a lock.
	 *
	 * @param string      $name  The lock's option name.
	 * @param int         $ttl   Seconds after which a lock left behind may be taken over.
	 * @param string|null $table The options table, or null for the current site's.
	 *
	 * @return bool True when this request holds the lock.
	 */
	public static function acquire( $name, $ttl = 300, $table = null ) {
		global $wpdb;

		$table = $table ? $table : $wpdb->options;
		$now   = (string) time();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- An atomic lock needs direct, uncached queries; the table name comes from WordPress.
		if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $now ) ) ) {
			return true;
		}

		$locked_at = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", $name ) );
		if ( null === $locked_at || (int) $locked_at > time() - $ttl ) {
			return false;
		}

		$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, $name, $locked_at ) );
		// phpcs:enable

		return 1 === $taken;
	}

	/**
	 * Release a lock.
	 *
	 * @param string      $name  The lock's option name.
	 * @param string|null $table The options table, or null for the current site's.
	 *
	 * @return void
	 */
	public static function release( $name, $table = null ) {
		global $wpdb;

		$wpdb->delete( $table ? $table : $wpdb->options, [ 'option_name' => $name ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- See self::acquire().
	}
}
