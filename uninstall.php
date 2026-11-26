<?php
/**
 * Removes the walkthrough's data when the plugin is uninstalled.
 *
 * Only what the walkthrough keeps goes: per user, and its switch in the settings. The templates and
 * the other settings stay, so a site that reinstalls the plugin finds its templates as it left them.
 *
 * @package Acato\Block_Editor_Templates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The plugin's autoloader is not loaded on uninstall.
require_once __DIR__ . '/src/admin/class-settings.php';
require_once __DIR__ . '/src/admin/class-tour.php';

\Acato\Block_Editor_Templates\Admin\Tour::uninstall();
