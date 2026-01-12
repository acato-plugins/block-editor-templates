<?php
/**
 * 404 template.
 *
 * This template is used to display the created 404 page.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Templates
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Tell WordPress to send proper 404 headers.
status_header( 404 );
nocache_headers();

get_header();
abet_the_content();
get_footer();
