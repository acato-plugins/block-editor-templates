<?php
/**
 * Archive template.
 *
 * This template is used to display Archive Templates.
 *
 * @package    Acato\Block_Editor_Templates
 * @subpackage Acato\Block_Editor_Templates\Templates
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
