=== Block Editor Templates ===
Contributors: acato, rockfire, paulacato, rmpel, eyalacato
Tags: block editor, gutenberg, block templates
Requires at least: 5.0
Tested up to: 7.0.1
Requires PHP: 7.2
Stable tag: 1.1.2
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
1. **Special Templates**: Here you can find templates for special situations, for example the 404 page.

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

= I'm a developer. How do I let a block's content be used as placeholder text in a template? =

Give the block a `{base}Placeholder` attribute (e.g. `content` → `contentPlaceholder`) of the same type as the content attribute, and render it as the placeholder in the block's edit component. The plugin then automatically adds a "Use content as placeholder" toggle to that block; you do not add the `textAsPlaceholder` attribute yourself. See `docs/placeholder-support.md` for a full walkthrough.

= I found a typo, or a translation that doesn't read well. What should I do? =

Please tell us, we'd like to fix it. Small wording mistakes are easy to miss, and we would much rather hear about them than leave them in. For the English source text, open a topic on [the support forum](https://wordpress.org/support/plugin/block-editor-templates/) or an issue on [GitHub](https://github.com/acato-plugins/block-editor-templates/issues), and mention where you saw the text. Translations are maintained by the WordPress polyglots teams on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/block-editor-templates/), where you can suggest a better phrasing in your own language directly.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.]( https://patchstack.com/database/vdp/398f3310-d285-4489-ae3b-07b8ab344119 )

== Changelog ==

= 1.1.2 =
Release Date: July 29th, 2026

Chore: Translations are no longer bundled with the plugin. They are now maintained on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/block-editor-templates/), where WordPress picks them up as language packs automatically.
Chore: Removed the call to load_plugin_textdomain(). Since WordPress 4.6 plugins hosted on WordPress.org no longer need it; WordPress loads the translations when they are needed.

= 1.1.1 =
Release Date: July 28th, 2026

Feature: Added Flemish (nl_BE), French (fr_FR) and German (de_DE) translations.
Fix: The translations shipped with the plugin are now actually loaded. Without this, all PHP strings stayed in English even though a Dutch (nl_NL) translation was bundled in 1.1.0.
Chore: Added the missing contributors to the plugin's contributor list.
Chore: Completed the Dutch (nl_NL) translation, so it can be imported into translate.wordpress.org.

= 1.1.0 =
Release Date: July 14th, 2026

Feature: New posts can now be prefilled with a template's content. This is configurable per template and shown in a "Prefill new posts" column in the templates list.
Feature: Added advanced placeholder options: a "Use content as placeholder" toggle on supported blocks, an in-editor placeholder badge, and per-attribute validation. See docs/placeholder-support.md.
Feature: The template editor now inherits the target post type's allowed block types.
Feature: Special pages (such as the 404 page) are now data-driven and filterable by developers.
Feature: Added a Dutch (nl_NL) translation.
Fix: Fixed missing content when a block template is applied to a new post.
Fix: Fixed the 404 template lookup for classic themes.
Fix: Guarded wp_is_block_theme() so the plugin keeps working on the declared WordPress 5.0 minimum.

= 1.0.7 =
Release Date: March 9th, 2026

Feature: Added a view link to the templates list for archive and special templates, so you can easily view the template after publishing it.

= 1.0.6 =
Release Date: January 12th, 2026

Feature: Added support for customizing the 404-page template, when not using a block theme.

= 1.0.5 =
Release Date: July 30th, 2025

Fix: Changes in the plugin for posting to WordPress.org include using get_posts instead of a direct DB query for checking the existence of templates. This however had the effect of sometimes not detecting existing templates, which caused the plugin to create new templates even if one already existed. This has been fixed by using the correct query parameters in get_posts.

= 1.0.4 =
Release Date: May 26th, 2025

First public version.
