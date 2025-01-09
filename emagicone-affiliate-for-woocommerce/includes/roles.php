<?php

/**
 * This file handles the creation and removal of the 'affiliate' role.
 * It includes multisite compatibility to ensure that roles are managed
 * appropriately across all sites in the network.
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Creates the 'affiliate' role by cloning the capabilities of the 'subscriber' role.
 * This function handles both single site and multisite environments.
 */
function emagicone_affiliate_create_role()
{
	if (is_multisite()) {
		$blog_ids = get_sites(array('fields' => 'ids'));
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			emagicone_affiliate_add_role();
			restore_current_blog();
		}
	} else {
		emagicone_affiliate_add_role();
	}
}

/**
 * Helper function to add the 'affiliate' role with 'subscriber' capabilities.
 */
function emagicone_affiliate_add_role()
{
	$subscriber = get_role('subscriber');
	if ($subscriber) {  // Check if the subscriber role exists
		$subscriber_caps = $subscriber->capabilities;
		add_role('affiliate', __('Affiliate', 'emagicone-affiliate-for-woocommerce'), $subscriber_caps);
	}
}

/**
 * Removes the 'affiliate' role and reassigns users to the 'subscriber' role.
 * This function handles both single site and multisite environments.
 */
function emagicone_affiliate_remove_role()
{
	if (is_multisite()) {
		$blog_ids = get_sites(array('fields' => 'ids'));
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			emagicone_affiliate_delete_role();
			restore_current_blog();
		}
	} else {
		emagicone_affiliate_delete_role();
	}
}

/**
 * Helper function to delete the 'affiliate' role and reassign affected users to 'subscriber'.
 */
function emagicone_affiliate_delete_role()
{
	$users = get_users(array('role' => 'affiliate'));
	foreach ($users as $user) {
		$user->set_role('subscriber');
	}
	remove_role('affiliate');
}
