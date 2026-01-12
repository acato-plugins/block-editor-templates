<?php
/**
 * The plugin bootstrap file
 *
 * @package           Acato\Block_Editor_Templates
 *
 * @wordpress-plugin
 * Plugin Name:       Block Editor Templates
 * Plugin URI:        https://www.acato.nl
 * Description:       Templates for the WordPress Block Editor.
 * Version:           1.0.6
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Acato
 * Author URI:        https://www.acato.nl
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl.html
 * Text Domain:       block-editor-templates
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ABET_VERSION', '1.0.6' );

require_once plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'class-autoloader.php';
spl_autoload_register( [ '\Acato\Block_Editor_Templates\Autoloader', 'autoload' ] );

// Make sure global functions are loaded.
$abet_plugin_function_files = glob( plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . '*.php' );
if ( $abet_plugin_function_files ) {
	foreach ( $abet_plugin_function_files as $abet_plugin_function_file ) {
		require_once $abet_plugin_function_file;
	}
}

/**
 * Begins execution of the plugin.
 */
new \Acato\Block_Editor_Templates\Plugin();
