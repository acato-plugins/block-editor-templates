=== Block Editor Templates ===
Contributors: acato, rockfire, paulacato, rmpel, eyalacato
Tags: block editor, gutenberg, block templates
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
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

= Is there a walkthrough? =

Yes. A short walkthrough shows you around the Post Type Templates list: the templates, publishing, Prefill new posts, the Preview, Created and Modified columns, the archive and special templates (classic themes only) and Settings. It never starts by itself. Start it from the **Start walkthrough** link of the plugin on the Plugins screen, tagged **New** while there is something you have not seen, or from the **Take the walkthrough** button next to the title of the Post Type Templates list. After an update that adds to the walkthrough, the link on the Plugins screen shows only what is new.

Don't want it? Switch off **Offer the walkthrough** under Block Templates > Settings, for a site or, on a multisite network, for every site that follows the network settings, and the link and the button go. In code, `define( 'ABET_DISABLE_WALKTHROUGH', true );` or the `abet_walkthrough` filter does the same.

= I found a typo, or a translation that doesn't read well. What should I do? =

Please tell us, we'd like to fix it. Small wording mistakes are easy to miss, and we would much rather hear about them than leave them in. For the English source text, open a topic on [the support forum](https://wordpress.org/support/plugin/block-editor-templates/) or an issue on [GitHub](https://github.com/acato-plugins/block-editor-templates/issues), and mention where you saw the text. Translations are maintained by the WordPress polyglots teams on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/block-editor-templates/), where you can suggest a better phrasing in your own language directly.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.]( https://patchstack.com/database/vdp/398f3310-d285-4489-ae3b-07b8ab344119 )

== Screenshots ==

1. The Post Type Templates list, with a preview of each template, whether it prefills new posts, and who created and last modified it.
2. Editing the template for Posts: the "Post Type Template" panel switches prefilling new posts on or off, and "Template for" shows which post type it belongs to.
3. A template made with "Add New" chooses what it is for before it is used.
4. A new post starts with the blocks and text of the Posts template.
5. Block Templates > Settings: the roles that may create, edit, publish and delete templates, with WordPress and custom roles to choose from.
6. On a classic theme, the 404 page shows the content of its Special Template, laid out by the theme.

== Changelog ==

= 1.3.0 =
Release Date: unreleased

Feature: Block Templates > Settings sets the roles that may create, edit, publish and delete templates. Each can hold several roles, any role of the site, custom roles included, grouped as WordPress and custom roles, with a search field. A user qualifies with one of the chosen roles; administrators always do. The default is Editor. On a multisite network a super admin sets the default for every site, and a site can use its own.
Feature: "Add New" for templates, for the roles that may create them. A new template gets a "Template for" choice in the editor (a post type, taxonomy, special page or the General Template), fixed once saved.
Feature: The network settings page looks and works like the site's settings page, and a site that follows the network settings links to it for a super admin.
Change: The template post types have their own capabilities (abet_template / abet_templates) instead of those of posts. The 'acato/block_editor_templates/post_type/capability_type' filter still overrides them; the role settings then no longer apply. Editors can now open the Block Templates menu by default; pick Administrator on the settings page to keep it as before.

Performance (BET-1): The plugin no longer checks on every request, front end included, that a template exists for every post type, taxonomy and special page. It checks in the admin only, and only when one of those, the theme type or the plugin version changed, or a template was changed or deleted. Two admin requests at the same time can no longer create the same template twice. This removes about 35 database queries from every page view.
Performance (BET-2): The block templates of the post types are built only for the block editor and the post types REST endpoint it asks, not on every request.
Performance (BET-3): Which archive and 404 template applies is remembered until a template changes, instead of being looked up on every archive and 404 page view.
Fix: After a template was unpublished or deleted, a site with a persistent object cache could keep using it, or create its template again, for up to an hour.
Chore: A database version per network (per site on a single site), so data stored by an older version is brought up to date once, for every site of the network, before the new code uses it.
Chore: Builds with Node 26.
Chore: Requires PHP 7.4, the minimum WordPress itself requires. PHP 7.2 and 7.3 no longer receive security fixes.
Fix: A Taxonomy Archive Template whose taxonomy cannot be linked to no longer gets a broken "View" link in the list.
Security: Templates are visible to logged-in users only. The template REST endpoints refuse visitors who are not logged in, and template content no longer shows in the post types REST endpoint for them. Archive and 404 pages still show their template to everyone.
Feature: A template for a post type or taxonomy is only edited, published or deleted by users who may also edit that post type (such as edit_products for products) or manage that taxonomy's terms, on top of the role settings. Filter: acato/block_editor_templates/target_capability.
Security: Template access fails closed: visitors who are not logged in never get template access, and a chosen role a site does not have lets nobody extra through.
Fix: Only published Post Type Templates are applied to new posts, as documented. A draft, pending or private template no longer takes effect, and its content no longer shows in the post types REST endpoint.
Fix: Each post type, taxonomy and special page can have one template. The server refuses a second one, and the editor no longer offers it.
Fix: Special Templates moved to the trash are created again as drafts, like the other templates, and they get the same states in the list.
Fix: The settings page shows what was saved: a role that cannot be chosen is dropped on save and no longer stays visible.
Performance: The capability check and the settings it reads are worked out once per request. On a multisite network the settings pages read only each site's role definitions instead of loading every site's options.
Performance: The editor builds only the template of the post type it opens. The network upgrade runs once, under a lock, and only for someone who is logged in, as does the template check. Template list previews no longer make rows jump, and their View links cost one query per taxonomy instead of one per row.
Chore: Requires WordPress 6.6, which the editor panels need.
Fix: Archive and 404 templates keep their form fields, such as the input of a Search block. Their output is no longer filtered a second time; the content is filtered when it is saved, as for posts.
Fix: Archive and 404 templates sit in the theme's content area (.entry-content), so classic themes such as Twenty Twenty-One apply their width and spacing.

= 1.2.0 =
Release Date: November 10th, 2026

Feature: The template lists show a preview of each template's blocks, rendered by the block editor itself so custom blocks look as they do in the editor. A tall template scrolls inside its thumbnail, with the mouse or the keyboard.
Feature: The Date column of the template lists is called "Created" and shows who created the template under its date. A new "Modified" column shows when a template was last modified and by whom.
Feature: "All creators" and "All modifiers" filters on the template lists.
Feature: Block Templates > Settings switches the preview, the author under "Created" and the "Modified" column on or off. On a multisite network a super admin sets the default under Network Admin > Settings > Block Editor Templates, and each site can follow it or use its own settings.
Feature: The editor of a Post Type Template says at the top when the template prefills new posts.
Fix: Saving a template in the block editor now records who saved it, which WordPress itself only does for the classic edit screen and Quick Edit.
Fix: The help text of the "Prefill new posts" column is a real tooltip that also opens on keyboard focus, instead of a title attribute that browsers show late or not at all.
Chore: Builds with Node 24.

= 1.1.4 =
Release Date: September 7th, 2026

Fix: The plugin no longer asks whether the active theme is a block theme before WordPress has registered the theme directory. Asked that early the answer is always "not a block theme", so on a block theme the archive and special-page templates were registered even though a block theme provides those itself. WordPress 6.8 and newer also reported the premature question as a "called incorrectly" notice on every request.

= 1.1.3 =
Release Date: August 17th, 2026

Fix: Text typed into a block *after* "Use content as placeholder" was switched on is now stored as placeholder text in full. Only the first character used to be moved into the placeholder attribute, while the rest stayed behind as real content — so a template prefilled new posts with that content instead of showing the hint. Templates in that state are repaired when they are opened.

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

== Upgrade Notice ==

= 1.3.0 =
Template access now follows Block Templates > Settings; the default role is Editor. Requires WordPress 6.6 and PHP 7.4.
