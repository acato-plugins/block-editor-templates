<?php
/**
 * Function to display the Archive Template content.
 *
 * This function can be used to display the Archive template content, without having to alter the_loop.
 *
 * @since      2.0.0
 *
 * @package    Acato/Block_Editor_Templates
 * @subpackage Acato/Block_Editor_Templates/Functions
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */

if ( ! function_exists( 'abet_the_content' ) ) {
	/**
	 * Displays the post content for a template post.
	 *
	 * Function partly copied from /wp-includes/post-template.php the_content.
	 *
	 * @param string $more_link_text Optional. Content for when there is more text.
	 * @param bool   $strip_teaser   Optional. Strip teaser content before the more text. Default false.
	 *
	 * @return void
	 */
	function abet_the_content( $more_link_text = null, $strip_teaser = false ) {
		global $abet_template_post;

		$content = get_the_content( $more_link_text, $strip_teaser, $abet_template_post );

		/**
		 * Filters the post content.
		 *
		 * @since 0.71
		 *
		 * @param string $content Content of the current post.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );

		echo wp_kses_post( $content );
	}
}
