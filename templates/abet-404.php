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
?>
<?php // .entry-content is where classic themes set the content width and spacing. ?>
<article class="abet-template hentry">
	<div class="entry-content">
		<?php abet_the_content(); ?>
	</div>
</article>
<?php
get_footer();
