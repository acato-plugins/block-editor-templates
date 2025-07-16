=== Block Editor Templates ===
Contributors: acato, rockfire
Tags: block editor, gutenberg, block templates
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Templates for the WordPress Block Editor.

== Description ==

WordPress offers the ability to [register block templates for the block editor programmatically](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/). This plugin adds a UI to the WP Admin to add block templates without having to be able to program. So if you want every new item of a specific post type to start with a default set of blocks, you can make it happen with this plugin. No programming skills required!

Furthermore, for classic themes (unfortunately not for block themes), it adds the option to edit the content of post type archives and taxonomy archives.

== Installation ==

After installation and activation: Go to wp-admin > Block Templates, here you have the option to choose between:

1. **Post Type Templates**: These are templates that are used whenever a post of the specified post type is being created. There are templates for each of the public post types registered on your site.
1. **Post Type Archive Template**: These are templates that are used whenever an archive page of the specified post type is being requested. There are templates for each of the public post types registered on your site, plus a General Template that will be used when there is no active Post Type Archive Template for the currently requested post type archive.
1. **Taxonomy Archive Templates**: These are templates that are used whenever an archive page of the specified Taxonomy is being requested. There are templates for each of the public Taxonomies registered on your site, plus a General Template that will be used when there is no active Taxonomy Archive Template for the currently requested Taxonomy archive.

The last two are only available if you use a classic theme, they are not supported for block themes.

== Frequently Asked Questions ==

= I only want to use a template for one post type / post type archive or taxonomy archive, is that possible? =

Yes! By default all templates are created as concepts. Only when you publish them they become active.

= I activated a template, but don't want to use it anymore. How can I deactivate it? =

Simply change the template form published to concept, or delete it. It will no longer be active.

= If I delete a template, will I be able to restore it at a later point? =

Yes! If you delete a template, just as with regular posts you can get it from the trash and restore it. If you permanently delete it, a new clean template will automatically be created as concept.

= The archive templates (for post types and taxonomies) use a php template provided by the plugin, I want to customize it, is that possible? =

Yes! You can create your own template inside your (sub)theme. Simply name it one of the following ways:
- abet-<post_type_slug>-archive.php: for an archive page for a specific post type.
- abet-posttype-archive.php: for all archive pages for post types.
- abet-<taxonomy-slug>-archive.php: for an archive page for a specific taxonomy.
- abet-taxonomy-archive.php: for all archive pages for taxonomies.
- abet-archive.php: for all archive pages.

= You say that some functionality is only supported for classic themes and not for block themes, but what is the difference? =

You can read more about the distinction between these types of themes in [the WordPress Developer Resources](https://developer.wordpress.org/themes/getting-started/what-is-a-theme/#theme-types).

== Changelog ==

= 1.0.4 =
Release Date: May 26th, 2025

First public version.
