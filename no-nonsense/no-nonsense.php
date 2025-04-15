<?php
/*
Plugin Name: No Nonsense
Plugin URI: https://nononsensewp.com
Description: The fastest, cleanest way to get rid of the parts of WordPress you don't need.
Version: 3.6.3
Requires at least: 4.9
Requires PHP: 7.0
Author: Room 34 Creative Services, LLC
Author URI: https://room34.com
License: GPLv2
Text Domain: no-nonsense
Domain Path: /i18n/languages/
*/

/*
  Copyright 2025 Room 34 Creative Services, LLC (email: info@room34.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


// Don't load directly
if (!defined('ABSPATH')) { exit; }


// Silently kill any XML-RPC request ASAP, if "Also kill any incoming XML-RPC request" is set
if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && get_option('r34nono_xmlrpc_disabled')) {
	$r34nono_xmlrpc_disabled_options = get_option('r34nono_xmlrpc_disabled_options');
	if (is_array($r34nono_xmlrpc_disabled_options) && !empty($r34nono_xmlrpc_disabled_options['kill_requests'])) {
		status_header(403); exit;
	}
}


// Load required files
require_once(plugin_dir_path(__FILE__) . 'functions.php');
require_once(plugin_dir_path(__FILE__) . 'class-r34nono.php');


// Initialize plugin functionality
function r34nono_plugins_loaded() {

	// Instantiate class
	global $r34nono;
	$r34nono = new R34NoNo();
	
	// Conditionally run update function
	if (is_admin() && version_compare(get_option('r34nono_version'), $r34nono->version, '<')) { r34nono_update(); }
	
}
add_action('plugins_loaded', 'r34nono_plugins_loaded');


// Load text domain for translations
function r34nono_load_plugin_textdomain() {
	load_plugin_textdomain('no-nonsense', false, basename(plugin_dir_path(__FILE__)) . '/i18n/languages/');
}
add_action('init', 'r34nono_load_plugin_textdomain', 1 - PHP_INT_MAX);


// Force loading of embedded translations instead of community translations
function r34nono_load_textdomain_mofile($mofile, $domain) {
	if ($domain == 'no-nonsense' && strpos($mofile, WP_LANG_DIR . '/plugins') !== false) {
		$locale = apply_filters('plugin_locale', determine_locale(), $domain);
		$locales = r34nono_i18n_locales();
		// Only replace the locales we have translated directly within the plugin
		if (is_array($locales) && in_array($locale, $locales)) {
			$mofile = plugin_dir_path(__FILE__) . '/i18n/languages/' . $domain . '-' . $locale . '.mo';
		}
	}
	return $mofile;
}
add_filter('load_textdomain_mofile', 'r34nono_load_textdomain_mofile', 10, 2);


// Get list of translated locales
function r34nono_i18n_locales() {
	$locales = get_option('r34nono_i18n_locales');
	if (empty($locales)) {
		$locales = array();
		if (!function_exists('list_files')) {
			include_once(ABSPATH . 'wp-admin/includes/file.php');
		}
		$files = list_files(plugin_dir_path(__FILE__) . '/i18n/languages', 1);
		foreach ((array)$files as $file) {
			if (pathinfo($file, PATHINFO_EXTENSION) == 'mo') {
				$locales[] = str_replace('no-nonsense-', '', pathinfo($file, PATHINFO_FILENAME));
			}
		}
		update_option('r34nono_i18n_locales', $locales);
	}
	return $locales;
}


// Install
function r34nono_install() {
	global $r34nono;

	// Flush rewrite rules
	flush_rewrite_rules();
	
	// Remember previous version
	$previous_version = get_option('r34nono_version');
	update_option('r34nono_previous_version', $previous_version);
	
	// Set version
	if (isset($r34nono->version)) {
		update_option('r34nono_version', $r34nono->version);
	}

	// Admin notice with link to settings
	$notices = get_option('r34nono_deferred_admin_notices', array());
	$notices[] = array(
		/* translators: 1. HTML tags and plugin name (do not translate) 2. HTML tag and dynamic link URL 3. HTML tags */
		'content' => '<p>' . sprintf(__('Thank you for installing %1$s. To get started, please visit the %2$sSettings%3$s page.', 'no-nonsense'), '<strong>No Nonsense</strong>', '<a href="' . admin_url('options-general.php?page=no-nonsense') . '"><strong>', '</strong></a>') . '</p>',
		'status' => 'info'
	);
	update_option('r34nono_deferred_admin_notices', $notices);
	
}
register_activation_hook(__FILE__, 'r34nono_install');


// Updates
function r34nono_update() {
	global $r34nono;
	
	// Remember previous version
	$previous_version = get_option('r34nono_version');
	update_option('r34nono_previous_version', $previous_version);
	
	// Update version
	if (isset($r34nono->version)) {
		update_option('r34nono_version', $r34nono->version);
	}
	
	// Version-specific updates
	if (version_compare($previous_version, '1.4.0', '<')) {
		if (get_option('r34nono_xmlrpc_disabled', null) !== null && get_option('r34nono_xmlrpc_enabled')) {
			update_option('r34nono_xmlrpc_disabled', get_option('r34nono_xmlrpc_enabled'));
			delete_option('r34nono_xmlrpc_enabled');
		}
		if (get_option('r34nono_login_replace_wp_logo_link', null) !== null && get_option('r34nono_login_remove_wp_logo')) {
			update_option('r34nono_login_replace_wp_logo_link', get_option('r34nono_login_remove_wp_logo'));
			delete_option('r34nono_login_remove_wp_logo');
		}
	}
	
}


// Deferred install/update admin notices
function r34nono_deferred_admin_notices() {
	if ($notices = get_option('r34nono_deferred_admin_notices', array())) {
		foreach ((array)$notices as $notice) {
			echo '<div class="notice notice-' . esc_attr($notice['status']) . ' is-dismissible r34nono-admin-notice">' . wp_kses_post($notice['content']) . '</div>';
		}
	}
	delete_option('r34nono_deferred_admin_notices');
}
add_action('admin_notices', 'r34nono_deferred_admin_notices');
