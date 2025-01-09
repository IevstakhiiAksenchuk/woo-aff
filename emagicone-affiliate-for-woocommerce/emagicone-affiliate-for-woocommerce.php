<?php

/**
 * Plugin Name: Affiliate Sales Tracking by Coupon and Link with Simple Affiliate for WooCommerce
 * Description: Affiliate plugin for WooCommerce by eMagicOne provides affiliates with the opportunity to earn income by sharing affiliate links or/and discounts. One-time payment, no recurring fees. WPML and Multi-store support available.
 * Version: 0.1.0
 * Author: Ievstakhii
 * Author URI: https://emagicone.com/
 * Text Domain: emagicone-affiliate-for-woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Define plugin base URL
define('EMAGICONE_AFFILIATE_BASE_URL', plugin_dir_url(__FILE__));
// Define plugin base directory
define('EMAGICONE_AFFILIATE_BASE_DIR', plugin_dir_path(__FILE__));

function emagicone_aff_check_is_woocommerce_active()
{
	if (is_multisite()) {
		// Includes checking network activated plugins.
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$active_plugins = array_merge(
			get_option('active_plugins', array()),
			array_keys(get_site_option('active_sitewide_plugins', array()))
		);
	} else {
		$active_plugins = get_option('active_plugins');
	}

	return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

// Check if WooCommerce is active
if (emagicone_aff_check_is_woocommerce_active()) {

	require_once EMAGICONE_AFFILIATE_BASE_DIR . 'includes/class-emagicone-affiliate-plugin.php';
	require_once EMAGICONE_AFFILIATE_BASE_DIR . 'includes/setup.php';
	require_once EMAGICONE_AFFILIATE_BASE_DIR . 'includes/roles.php';


	new Emagicone_Affiliate_Plugin();

	function emagicone_affiliate_plugin_activate_signup_page()
	{
		Emagicone_Affiliate_Plugin::create_affiliate_signup_page();
	}

	function emagicone_affiliate_assign_account_id_to_users()
	{
		$affiliate_signup = new EmagicOne_Affiliate_Signup();

		// Get all users except subscribers
		$args  = array(
			'role__not_in' => array('subscriber'), // Exclude subscribers
		);
		$users = get_users($args);

		foreach ($users as $user) {
			// Check if the user already has an affiliate_account_id
			$affiliate_account_id = get_user_meta($user->ID, 'affiliate_account_id', true);

			if (empty($affiliate_account_id)) {
				// Assign an affiliate_account_id using the get_next_external_account_id function
				$new_affiliate_account_id = $affiliate_signup->get_next_external_account_id();

				update_user_meta($user->ID, 'affiliate_account_id', $new_affiliate_account_id);
			}
		}
	}

	// Flush rewrite rules on plugin activation
	function emagicone_affiliate_flush()
	{
		flush_rewrite_rules();
	}

	// Flush rewrite rules on plugin deactivation
	function emagicone_affiliate_plugin_deactivate()
	{
		flush_rewrite_rules();
	}

	function emagicone_affiliate_activate_plugin_func()
	{
		set_transient('emagicone_flush_rewrite_rules', true);
		emagicone_affiliate_assign_account_id_to_users();
		emagicone_affiliate_plugin_activate_signup_page();
		emagicone_affiliate_create_role();
		emagicone_affiliate_install();
		emagicone_affiliate_flush();
		set_transient('emagicone_flush_rewrite_rules', true);
	}

	// Add a high-priority action on 'init' to check and flush rewrite rules if necessary
	add_action(
		'init',
		function () {
			if (get_transient('emagicone_flush_rewrite_rules')) {
				flush_rewrite_rules();
				delete_transient('emagicone_flush_rewrite_rules');
			}
		},
		1
	);

	register_activation_hook(__FILE__, 'emagicone_affiliate_activate_plugin_func');
	register_deactivation_hook(__FILE__, 'emagicone_affiliate_plugin_deactivate');
	register_deactivation_hook(__FILE__, 'emagicone_affiliate_plugin_deactivate_signup_page');
	register_deactivation_hook(__FILE__, 'emagicone_affiliate_remove_role');

	add_action('plugins_loaded', 'emagicone_affiliate_load_textdomain');

	function emagicone_affiliate_add_documentation_link($links, $file)
	{
		if ($file == plugin_basename(__FILE__)) {
			// Change the URL to the actual documentation URL
			$doc_link = '<a href="https://woocommerce-affiliate.emagicone.com/" target="_blank" >Help</a>';
			array_push($links, $doc_link); // Adds the link to the beginning of the array
		}
		return $links;
	}

	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'emagicone_affiliate_add_documentation_link', 10, 2);
} else {
	add_action('admin_notices', 'emagicone_affiliate_missing_woocommerce_notice');
}

function emagicone_affiliate_missing_woocommerce_notice()
{
	echo '<div class="notice notice-warning is-dismissible">';
	esc_html_e('Please install WooCommerce to use EmagicOne Affiliate Plugin', 'emagicone-affiliate-for-woocommerce');
	echo '</div>';
}
