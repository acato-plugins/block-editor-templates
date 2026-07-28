<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @package    Acato\Block_Editor_Templates
 */

namespace Acato\Block_Editor_Templates;

use Acato\Block_Editor_Templates\Admin\Admin;


/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    Acato\Block_Editor_Templates
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */
class Plugin {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Define the locale, and set the hooks for the admin area and the public-facing side of the site.
	 */
	public function __construct() {
		$this->define_constants();

		add_action( 'init', [ self::class, 'load_textdomain' ], 0 );

		Admin::get_instance();
	}

	/**
	 * Load the plugin's translations from the bundled languages directory.
	 *
	 * WordPress.org language packs are picked up automatically, but the
	 * translations shipped with the plugin itself are only used when their
	 * location is registered. Hooked to `init` at priority 0 so the textdomain
	 * is available to every other `init` callback.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'block-editor-templates', false, plugin_basename( ABET_ABSPATH ) . '/languages' );
	}

	/**
	 * Define constants used by the plugin.
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'ABET_ABSPATH' ) ) {
			define( 'ABET_ABSPATH', plugin_dir_path( __DIR__ ) );
		}
		if ( ! defined( 'ABET_ASSETS_DIR' ) ) {
			define( 'ABET_ASSETS_DIR', 'build/' );
		}
		if ( ! defined( 'ABET_ASSETS_URL' ) ) {
			define( 'ABET_ASSETS_URL', esc_url( trailingslashit( plugins_url( '', __DIR__ ) ) . ABET_ASSETS_DIR ) );
		}
	}
}
