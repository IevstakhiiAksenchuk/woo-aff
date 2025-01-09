<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Emagicone_Affiliate_User_Profile
{

	public function __construct()
	{
		// Add hooks for user profile fields
		add_action('show_user_profile', array($this, 'display_campaign_field'));
		add_action('edit_user_profile', array($this, 'display_campaign_field'));
		add_action('personal_options_update', array($this, 'save_campaign_field'));
		add_action('edit_user_profile_update', array($this, 'save_campaign_field'));
	}

	// Display campaign ID dropdown in user profile
	public function display_campaign_field($user)
	{
		global $wpdb;
		if (in_array('affiliate', (array) $user->roles) || user_can($user, 'edit_posts')) {
			$campaigns       = $wpdb->get_results("SELECT campaign_id, name FROM {$wpdb->prefix}affiliate_campaigns"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			// Register campaign names for translation
			foreach ($campaigns as $campaign) {
				if (function_exists('icl_register_string')) {
					icl_register_string('emagicone-affiliate-for-woocommerce', 'campaign_name_' . $campaign->campaign_id, $campaign->name);
				}
			}

			echo '<h3>' . esc_html__('Affiliate Settings', 'emagicone-affiliate-for-woocommerce') . '</h3>';
			echo '<table class="form-table"><tr><th><label for="campaign_id">' . esc_html__('Campaign ID', 'emagicone-affiliate-for-woocommerce') . '</label></th><td>';
			wp_nonce_field('update_campaign_id', 'campaign_nonce'); // Adding nonce field
			echo '<select name="campaign_id" id="campaign_id">';
			echo '<option value="">' . esc_html__('Select a Campaign', 'emagicone-affiliate-for-woocommerce') . '</option>';

			// Display campaigns with possible translation
			foreach ($campaigns as $campaign) {
				$translated_name = function_exists('icl_t') ? icl_t('emagicone-affiliate-for-woocommerce', 'campaign_name_' . $campaign->campaign_id, $campaign->name) : $campaign->name;
				$selected = selected($current_campaign_id, $campaign->campaign_id, false);
				echo "<option value='" . esc_attr($campaign->campaign_id) . "' " . esc_attr($selected) . ">" . esc_html($translated_name) . '</option>';
			}

			echo '</select></td></tr></table>';
		}
	}


	// Save campaign ID from user profile
	public function save_campaign_field($user_id)
	{
		if (!current_user_can('edit_user', $user_id) || !isset($_POST['campaign_nonce'], $_POST['campaign_id']) || !wp_verify_nonce(sanitize_text_field($_POST['campaign_nonce']), 'update_campaign_id')) {
			return false;
		}

		// Save the data
		update_user_meta($user_id, 'campaign_id', sanitize_text_field($_POST['campaign_id']));
	}
}
