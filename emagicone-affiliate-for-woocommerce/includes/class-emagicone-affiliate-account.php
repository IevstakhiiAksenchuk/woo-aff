<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Emagicone_Affiliate_Account
{


	public function __construct()
	{

		// Add new menu items to My Account page
		add_filter('woocommerce_account_menu_items', array($this, 'add_affiliate_menu_item'));

		// Handle the display of the new endpoint content
		add_action('woocommerce_account_affiliate_endpoint', array($this, 'affiliate_content'));

		// Register new endpoint
		add_action('init', array($this, 'add_affiliate_endpoint'));
                
                // Enqueue the styles and scripts
                add_action('wp_enqueue_scripts', array($this, 'enqueue_affiliate_dashboard_assets'));
	}
        
        public function enqueue_affiliate_dashboard_assets() {
            
                wp_register_style('emagicone-affiliate-dashboard-new', plugin_dir_url(__DIR__) . 'assets/css/affiliate-dashboard.css', array(), '1.0.0');
                wp_enqueue_style('emagicone-affiliate-dashboard-new');

                wp_register_script('emagicone-affiliate-dashboard-new', plugin_dir_url(__DIR__) . 'assets/js/affiliate-dashboard.js', array('jquery'), '1.0.0', true);
                wp_enqueue_script('emagicone-affiliate-dashboard-new');

                wp_register_script('emagicone-affiliate-dashboard-inline', plugin_dir_url(__DIR__) . 'assets/js/affiliate-dashboard-inline.js', array('jquery'), '1.0.0', true);
                wp_enqueue_script('emagicone-affiliate-dashboard-inline');

                $translation_array = array(
                    'ajax_url'              => admin_url('admin-ajax.php'),
                    'nonce'                 => wp_create_nonce('affiliate_link_nonce'),
                    'currentAffUserId'      => get_user_meta(get_current_user_id(), 'affiliate_account_id', true),
                    'currentUserCampaignId' => Emagicone_Affiliate_Plugin::get_user_current_campaign(get_current_user_id()),
                    'payoutRequestNonce'    => wp_create_nonce('payout_request_nonce'),
                    'payoutCancelNonce'     => wp_create_nonce('payout_cancel_nonce'),
                    'savePaypalEmailNonce'  => wp_create_nonce('save_paypal_email_nonce'),
                );
                wp_localize_script('emagicone-affiliate-dashboard-inline', 'affiliate_data', $translation_array);

                $inline_script = "
                    var payoutRequestNonce = " . wp_json_encode($translation_array['payoutRequestNonce']) . ";
                    var payoutCancelNonce = " . wp_json_encode($translation_array['payoutCancelNonce']) . ";
                    var save_paypal_email_nonce = " . wp_json_encode($translation_array['savePaypalEmailNonce']) . ";
                    var ajaxurl = " . wp_json_encode($translation_array['ajax_url']) . ";
                ";
                wp_add_inline_script('emagicone-affiliate-dashboard-inline', $inline_script);
            
        }

	// Add a new menu item to the My Account navigation
	public function add_affiliate_menu_item($items)
	{
		// Remove the logout item
		$logout = $items['customer-logout'];
		unset($items['customer-logout']);

		// Add your custom menu item
		$items['affiliate'] = __('Affiliate', 'emagicone-affiliate-for-woocommerce');

		// Re-add the logout item so it appears at the end
		$items['customer-logout'] = $logout;

		return $items;
	}


	// Handle the content for the "Affiliate" endpoint
	public function affiliate_content()
	{
		// Include the file that contains the content of the Affiliate section
		include_once plugin_dir_path(__FILE__) . '/views/affiliate-dashboard.php';
	}

	// Add new endpoint for the "Affiliate" section
	public function add_affiliate_endpoint()
	{
		add_rewrite_endpoint('affiliate', EP_ROOT | EP_PAGES);
	}
}
