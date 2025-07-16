<?php
/**
 * Autoload all plugin classes
 *
 * @package     Acato\Block_Editor_Templates
 */

namespace Acato\Block_Editor_Templates;

/**
 * Autoload all plugin classes.
 *
 * This class autoloads the plugin classes when they are being used.
 *
 * @package     Acato\Block_Editor_Templates
 * @author      Richard Korthuis <richardkorthuis@acato.nl>
 */
class Autoloader {
	/**
	 * Autoload classes related to this plugin.
	 *
	 * @param string $class_name The requested class.
	 *
	 * @return void
	 */
	public static function autoload( $class_name ) {
		$file_path = explode( '\\', $class_name );
		if ( 'Acato' === $file_path[0] && isset( $file_path[1] ) && 'Block_Editor_Templates' === $file_path[1] ) {
			$file_name = strtolower( $file_path[ count( $file_path ) - 1 ] );
			unset( $file_path[ count( $file_path ) - 1 ] );
			unset( $file_path[1] );
			unset( $file_path[0] );
			$subdir = '';
			if ( count( $file_path ) ) {
				$subdir = strtolower( implode( DIRECTORY_SEPARATOR, $file_path ) );
			}
			$subdir         .= DIRECTORY_SEPARATOR;
			$file_name       = str_ireplace( '_', '-', $file_name );
			$file_name_parts = explode( '-', $file_name );
			switch ( $file_name_parts[ count( $file_name_parts ) - 1 ] ) {
				case 'trait':
				case 'interface':
					$type = $file_name_parts[ count( $file_name_parts ) - 1 ];
					unset( $file_name_parts[ count( $file_name_parts ) - 1 ] );
					$file_name = $type . '-' . implode( '-', $file_name_parts ) . '.php';
					break;
				default:
					$file_name = 'class-' . $file_name . '.php';
			}
			require_once plugin_dir_path( __DIR__ ) . 'src' . DIRECTORY_SEPARATOR . $subdir . $file_name;
		}
	}
}
