<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

function emagicone_affiliate_install()
{
	global $wpdb;
	if (is_multisite()) {
		$original_blog_id = get_current_blog_id(); // Store the current blog ID
		$blog_ids         = get_sites(array('fields' => 'ids')); // Get all blog IDs
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id); // Switch to each blog
			emagicone_affiliate_create_tables(); // Create tables for each blog
			emagicone_affiliate_insert_initial_data(); // Insert initial data for each blog
			restore_current_blog(); // Restore the original blog
		}
	} else {
		emagicone_affiliate_create_tables(); // Single site installation
		emagicone_affiliate_insert_initial_data();
	}
	add_action('wpmu_new_blog', 'emagicone_affiliate_new_site_created', 10, 6); // Hook into new site creation
}

function emagicone_affiliate_create_tables()
{
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$tables_sql      = array(
		"CREATE TABLE `{$wpdb->prefix}affiliate_payouts` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `affiliate_id` mediumint(9) NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `action_date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `action_type` varchar(20) NOT NULL,
            `status` varchar(20) NOT NULL,
            `admin_notes` text,
            `request_identifier` varchar(255) NOT NULL,
            `total_earned_at_request` decimal(10,2) NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;",
		"CREATE TABLE `{$wpdb->prefix}affiliate_coupon_usage` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `coupon_code` text NOT NULL,
            `user_id` mediumint(9) NOT NULL,
            `order_id` bigint(20) NOT NULL,
            `usage_time` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;",
		"CREATE TABLE `{$wpdb->prefix}affiliate_visits` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `campaign_id` mediumint(9) NOT NULL,
            `affiliate_id` mediumint(9) NOT NULL,
            `traffic_source` text NOT NULL,
            `affiliate_link_id` INT NOT NULL,
            `visit_url` text NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;",
		"CREATE TABLE `{$wpdb->prefix}affiliate_links` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `affiliate_id` mediumint(9) NOT NULL,
            `product_id` bigint(20) NOT NULL,
            `traffic_source` text NOT NULL,
            `campaign_id` INT NOT NULL,
            `affiliate_link` text NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;",
		"CREATE TABLE `{$wpdb->prefix}affiliate_campaigns` (
            `campaign_id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `name` text NOT NULL,
            `description` text,
            `commission_value` float NOT NULL,
            `discount` float NOT NULL DEFAULT 0,
            `earning_range` text,
            `is_default` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`campaign_id`)
        ) $charset_collate;",
		"CREATE TABLE `{$wpdb->prefix}affiliate_order_stats` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `affiliate_id` mediumint(9) NOT NULL,
            `order_id` bigint(20) NOT NULL,
            `buyer_id` bigint(20) NOT NULL,
            `commission_amount` float NOT NULL,
            `affiliate_link_id` INT NOT NULL,
            `date_recorded` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `comments` text NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;",
	);

	foreach ($tables_sql as $sql) {
		dbDelta($sql);
		if ($wpdb->last_error) {
			error_log('Database error when creating tables: ' . $wpdb->last_error);
		}
	}
}

function emagicone_affiliate_insert_initial_data()
{
	global $wpdb;
	$initial_campaigns = array(
		array(
			'name'             => 'Default (10% commission)',
			'description'      => 'This is the default campaign offering a 10% commission rate.',
			'commission_value' => 10.0,
		),
	);

	foreach ($initial_campaigns as $campaign) {
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_campaigns WHERE name = %s",
				$campaign['name']
			)
		);

		if (!$exists) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}affiliate_campaigns",
				array(
					'name'             => $campaign['name'],
					'description'      => $campaign['description'],
					'commission_value' => $campaign['commission_value'],
				)
			);
			if ($wpdb->last_error) {
				error_log('Database error when inserting initial campaigns: ' . $wpdb->last_error);
			} else {
				// Register strings for translation with WPML
				emagicone_affiliate_register_string_for_translation('emagicone-affiliate-for-woocommerce', 'campaign_name_' . $campaign['name'], $campaign['name']);
				emagicone_affiliate_register_string_for_translation('emagicone-affiliate-for-woocommerce', 'campaign_description_' . $campaign['name'], $campaign['description']);
			}
		}
	}
}

function emagicone_affiliate_register_string_for_translation($context, $name, $value)
{
	if (function_exists('icl_register_string')) {
		icl_register_string($context, $name, $value);
	}
}

function emagicone_affiliate_new_site_created($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
	switch_to_blog($blog_id);
	emagicone_affiliate_create_tables();
	emagicone_affiliate_insert_initial_data();
	restore_current_blog();
}


function emagicone_affiliate_plugin_deactivate_signup_page()
{
	if (is_multisite()) {
		$blog_ids = get_sites(array('fields' => 'ids'));
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			emagicone_affiliate_delete_affiliate_page();
			restore_current_blog();
		}
	} else {
		emagicone_affiliate_delete_affiliate_page();
	}
}

function emagicone_affiliate_delete_affiliate_page()
{
	$page_id = get_option('woocommerce_affiliate_signup_page_id');
	if ($page_id) {
		wp_delete_post($page_id, true); // True bypasses trash and permanently deletes
		delete_option('woocommerce_affiliate_signup_page_id');
		if (is_wp_error(wp_delete_post($page_id, true))) {
			error_log('Error deleting page: ' . wp_delete_post($page_id, true)->get_error_message());
		}
	}
}

function emagicone_affiliate_load_textdomain()
{
	load_plugin_textdomain('emagicone-affiliate-for-woocommerce', false, basename(__DIR__) . '/languages/');
}
