<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . '../admin/class-emagicone-affiliate-admin.php';
require_once plugin_dir_path(__FILE__) . 'class-emagicone-affiliate-user-profile.php';
require_once plugin_dir_path(__FILE__) . 'class-emagicone-affiliate-account.php';
require_once plugin_dir_path(__FILE__) . 'class-emagicone-affiliate-signup.php';

class Emagicone_Affiliate_Plugin
{

        private $admin;
	private $user_profile;
	private $affiliate_account;
	private $signupForm;

	private static $page_title;
	private static $page_slug;

	public function __construct()
	{
		self::$page_title = __('Affiliate Signup', 'emagicone-affiliate-for-woocommerce');
		self::$page_slug  = 'affiliate-signup';
		add_action('init', array($this, 'init'));
		$this->admin             = new Emagicone_Affiliate_Admin();
		$this->user_profile      = new Emagicone_Affiliate_User_Profile();
		$this->affiliate_account = new Emagicone_Affiliate_Account();
		$this->signupForm        = new Emagicone_Affiliate_Signup();
		add_action('woocommerce_thankyou', array($this, 'handle_order_placement'));
		add_action('woocommerce_checkout_order_processed', array($this, 'handle_order_placement'));
		add_action('woocommerce_order_status_completed', array($this, 'record_coupon_usage_for_affiliate'));
		add_action('wp_ajax_request_payout', array($this, 'ajax_handle_payout_request'));
		add_action('wp_ajax_save_paypal_email', array($this, 'ajax_save_paypal_email'));
		add_action('wp_ajax_cancel_payout_request', array($this, 'ajax_cancel_payout_request'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
		add_action('wp_trash_post', array($this, 'handle_coupon_trashing'), 10, 1);
		// Add custom setting in WooCommerce 'Advanced' settings
		add_filter('woocommerce_get_settings_advanced', array($this, 'add_affiliate_signup_page_setting'), 10, 2);
	}


	public function enqueue_styles()
	{
		wp_enqueue_style(
			'affiliate-global-style',
			plugins_url('../assets/css/affiliate-global.css', __FILE__),
			array(),
			'1.0.0',
			'all'
		);
	}

	public function add_affiliate_signup_page_setting($settings)
	{

		$settings[] = array(
			'title'    => __('Affiliate Page Setup', 'emagicone-affiliate-for-woocommerce'),
			'type'     => 'title',
			'desc'     => __('Setup pages for your affiliate system.', 'emagicone-affiliate-for-woocommerce'),
			'id'       => 'affiliate_page_options'
		);
		$settings[] = array(
			'title'    => __('Affiliate Signup Page', 'emagicone-affiliate-for-woocommerce'),
			'desc'     => __('Page for affiliates to sign up.', 'emagicone-affiliate-for-woocommerce'),
			'id'       => 'woocommerce_affiliate_signup_page_id',
			'type'     => 'single_select_page',
			'default'  => '',
			'class'    => 'wc-enhanced-select-nostd',
			'css'      => 'min-width:300px;',
			'desc_tip' => true,
		);
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'affiliate_page_options'
		);

		return $settings;
	}

	public static function create_affiliate_signup_page()
	{
		if (is_multisite()) {
			// Get all blog IDs; consider using switch_to_blog() if needed
			$blog_ids = get_sites(array('fields' => 'ids'));
			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				self::create_page_for_site();
				restore_current_blog();
			}
		} else {
			self::create_page_for_site();
		}
	}

	private static function create_page_for_site()
	{
		// Check if the page already exists
		$page_id = get_option('woocommerce_affiliate_signup_page_id');
		if (!$page_id) {
			$parent_page_id  = get_option('woocommerce_myaccount_page_id');
			$page_content    = '[emagicone_affiliate_signup]';
			$current_user_id = get_current_user_id(); // Consider setting to a static user or admin
			$new_page        = array(
				'post_title'   => self::$page_title,
				'post_content' => $page_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => self::$page_slug,
				'post_parent'  => $parent_page_id ? $parent_page_id : 0,
				'post_author'  => $current_user_id,
			);

			$page_id = wp_insert_post($new_page, true);
			if ($page_id && !is_wp_error($page_id)) {
				update_option('woocommerce_affiliate_signup_page_id', $page_id);
			}
		}
	}


	public function init()
	{

		// Hook into WooCommerce order completion
		add_action('wp_ajax_save_or_get_affiliate_link', array($this, 'handle_save_or_get_affiliate_link'));
		add_action('woocommerce_order_status_completed', array($this, 'record_affiliate_order_stats'));
		add_action('wp', array($this, 'track_affiliate_visit'));
		// Enqueue the styles and scripts
		wp_enqueue_script('emagicone-affiliate-cookies', plugin_dir_url(__FILE__) . '../assets/js/affiliate-cookies.js', array(), '1.0.0', true);
	}

	// Get user campaign ID
	public static function get_user_current_campaign($affiliate_id)
	{
		$current_campaign_id = 1;

		// Get the currently assigned campaign ID
		$current_user_campaign_id = get_user_meta($affiliate_id, 'campaign_id', true);

		if ($current_user_campaign_id) {
			$current_campaign_id = $current_user_campaign_id;
		}

		return $current_campaign_id;
	}

	// Add a method to get the campaign name by its ID
	public static function get_campaign_name_by_id($campaign_id)
	{
		global $wpdb;
		return $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}affiliate_campaigns WHERE campaign_id = %d",
				$campaign_id
			)
		);
	}

	//db call ok; no-cache ok
	public static function get_latest_payout_request($affiliate_id)
	{
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}affiliate_payouts WHERE affiliate_id = %d ORDER BY action_date DESC, id DESC LIMIT 1",
				$affiliate_id
			)
		);
	}

	public function ajax_handle_payout_request()
	{
		check_ajax_referer('payout_request_nonce', 'nonce'); // Verifying the nonce

		$affiliate_id = get_user_meta(get_current_user_id(), 'affiliate_account_id', true);

		// Access the total earnings and the last paid amount of the affiliate
		$total_earnings   = self::get_user_total_earnings($affiliate_id);
		$last_paid_amount = self::get_last_paid_amount($affiliate_id);

		// Calculate the earnings available for the new payout
		$earnings_for_payout = $total_earnings - $last_paid_amount;

		if ($affiliate_id) {
			if ($earnings_for_payout >= 100) { // Minimum payout amount is $100
				$this->handle_payout_request($affiliate_id, $earnings_for_payout);
				wp_send_json_success(__('Payout request submitted.', 'emagicone-affiliate-for-woocommerce'));
			} else {
				wp_send_json_error(__('Minimum payout amount not reached.', 'emagicone-affiliate-for-woocommerce'));
			}
		} else {
			wp_send_json_error(__('You do not have permission to perform this action.', 'emagicone-affiliate-for-woocommerce'));
		}
	}


	public function ajax_save_paypal_email()
	{
		check_ajax_referer('save_paypal_email_nonce', 'nonce'); // Verifying the nonce

		//$affiliate_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $user_id              = get_current_user_id();
		$paypal_email = isset($_POST['paypal_email']) ? sanitize_email($_POST['paypal_email']) : '';

		if (empty($paypal_email) || !is_email($paypal_email)) {
			wp_send_json_error(array('message' => 'Invalid PayPal email address.'));
			return;
		}

		$updated = update_user_meta($user_id, 'paypal_email', $paypal_email);

		if ($updated) {
			wp_send_json_success(array('message' => 'PayPal email saved successfully.'));
		} else {
			wp_send_json_error(array('message' => 'Failed to save PayPal email.'));
		}
	}

	private static function get_last_paid_amount($affiliate_id)
	{
		global $wpdb;

		// Get the total paid amount
		$last_paid = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}affiliate_payouts WHERE affiliate_id = %d AND status = 'approved'",
				$affiliate_id
			)
		);

		return $last_paid ? $last_paid : 0;
	}

	public function ajax_cancel_payout_request()
	{
		check_ajax_referer('payout_cancel_nonce', 'nonce'); // Verifying the nonce

		$payout_id = isset($_POST['payout_id']) ? intval($_POST['payout_id']) : 0;

		$affiliate_id = get_user_meta(get_current_user_id(), 'affiliate_account_id', true);

		if ($affiliate_id) {
			$this->cancel_payout_request($payout_id);
			wp_send_json_success(__('Payout request canceled.', 'emagicone-affiliate-for-woocommerce'));
		} else {
			wp_send_json_error(__('You do not have permission to perform this action.', 'emagicone-affiliate-for-woocommerce'));
		}
	}

	public function handle_payout_request($affiliate_id, $amount)
	{
		global $wpdb;
		$table_name         = $wpdb->prefix . 'affiliate_payouts';
		$request_identifier = uniqid('payout_');  // Generate a unique identifier
		$total_earnings     = self::get_user_total_earnings($affiliate_id); // Get the total earnings of the affiliate

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_name,
			array(
				'affiliate_id'            => $affiliate_id,
				'amount'                  => $amount,
				'action_date'             => current_time('mysql'),
				'action_type'             => 'request',
				'status'                  => 'requested',
				'request_identifier'      => $request_identifier,
				'total_earned_at_request' => $total_earnings,
			),
			array('%d', '%f', '%s', '%s', '%s', '%s', '%f')
		);

		// Send email notification to the admin
		$admin_email = get_option('admin_email'); // Get the admin email address from WordPress settings
		$subject     = __('New Affiliate Payout Request', 'emagicone-affiliate-for-woocommerce');

		// Start building the message with translatable strings
		$message  = __('A new affiliate payout request has been made.', 'emagicone-affiliate-for-woocommerce') . "\n\n";
		/* translators: %s: Affiliate ID */
		$message .= sprintf(__('Affiliate ID: %s', 'emagicone-affiliate-for-woocommerce'), $affiliate_id) . "\n";
		/* translators: %s: Formatted amount in USD */
		$message .= sprintf(__('Amount: $%s', 'emagicone-affiliate-for-woocommerce'), number_format($amount, 2)) . "\n\n";
		$message .= __('Please review the request in the admin panel.', 'emagicone-affiliate-for-woocommerce');

		// Use wp_mail() to send the email
		wp_mail($admin_email, $subject, $message);
	}

	public function cancel_payout_request($payout_id)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'affiliate_payouts';

		// Retrieve the necessary information from the original payout request
		$original = $wpdb->get_row($wpdb->prepare("SELECT affiliate_id, amount, request_identifier FROM {$wpdb->prefix}affiliate_payouts WHERE id = %d", $payout_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ($original) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name,
				array(
					'affiliate_id'       => $original->affiliate_id,
					'amount'             => $original->amount,
					'action_date'        => current_time('mysql'),
					'action_type'        => 'cancel',
					'status'             => 'canceled',
					'request_identifier' => $original->request_identifier,
				),
				array('%d', '%f', '%s', '%s', '%s', '%s')
			);
		}
	}


	public static function get_user_transactions($affiliate_id)
	{
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}affiliate_payouts WHERE affiliate_id = %d ORDER BY action_date DESC, id DESC",
				$affiliate_id
			)
		);
	}

	public static function get_user_earnings_paid($affiliate_id)
	{
		global $wpdb;
		$earnings_paid = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$wpdb->prefix}affiliate_payouts WHERE affiliate_id = %d AND status = 'approved'",
				$affiliate_id
			)
		);
		return $earnings_paid ?: 0;
	}


	public static function get_user_earnings_in_review($affiliate_id)
	{
		global $wpdb;

		// Get the latest request identifier for the 'requested' status
		$latest_request = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT request_identifier, amount FROM {$wpdb->prefix}affiliate_payouts 
                                    WHERE affiliate_id = %d AND status = 'requested' 
                                    ORDER BY action_date DESC LIMIT 1",
				$affiliate_id
			)
		);

		if ($latest_request) {
			// Check if this request identifier has any 'approved' or 'canceled' actions
			$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_payouts
                                        WHERE request_identifier = %s AND (status = 'approved' OR status = 'canceled' OR status = 'declined')",
					$latest_request->request_identifier
				)
			);

			// If no approved or canceled actions are found, return the amount of the latest request
			if ($count == 0) {
				return $latest_request->amount;
			}
		}

		return 0;
	}



	public function record_coupon_usage_for_affiliate($order_id)
	{

		$order        = wc_get_order($order_id);
		$used_coupons = $order->get_used_coupons();

		foreach ($used_coupons as $coupon_code) {
			$user_id = $this->get_user_by_coupon_code($coupon_code); // Function to get user ID by coupon code
			if ($user_id) {
				$affiliate_id = get_user_meta($user_id, 'affiliate_account_id', true);
				global $wpdb;
				$table_name_coupon_usage = $wpdb->prefix . 'affiliate_coupon_usage';

				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$table_name_coupon_usage,
					array(
						'coupon_code' => $coupon_code,
						'user_id'     => $affiliate_id,
						'order_id'    => $order_id,
						'usage_time'  => current_time('mysql'),
					),
					array('%s', '%d', '%d', '%s')
				);
			}
		}
	}

	// Need to rebuilt in future
	/*public function get_user_by_coupon_code( $coupon_code ) {
		$args = array(
			'meta_key' => 'affiliate_coupon_code',
			'fields'   => 'all',  // Retrieve all user fields; adjust as necessary
		);

		$users = get_users( $args );
		foreach ( $users as $user ) {
			$user_coupon_codes = get_user_meta( $user->ID, 'affiliate_coupon_code', true );
			if ( is_array( $user_coupon_codes ) && in_array( strtolower( $coupon_code ), array_map( 'strtolower', $user_coupon_codes ) ) ) {
				// If the coupon codes are stored in an array and the coupon code is found, ignoring case
				return $user->ID;  // Return the user ID
			} elseif ( $user_coupon_codes == $coupon_code ) {
				// If there's a single coupon code and it matches
				return $user->ID;  // Return the user ID
			}
		}

		return false;  // Return false if no users found with this coupon code
	}*/

	public function get_user_by_coupon_code($coupon_code)
	{
		global $wpdb;

		// Sanitize the coupon code before using it in the query
		$coupon_code = sanitize_text_field($coupon_code);

		// Execute the SQL query
		$user_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT user_id FROM {$wpdb->usermeta}
                WHERE meta_key = 'affiliate_coupon_code'
                AND LOWER(meta_value) = LOWER(%s)",
			$coupon_code
		));

		return $user_id ? $user_id : false;
	}

	public function handle_coupon_trashing($post_id)
	{
		global $wpdb;

		if ('shop_coupon' === get_post_type($post_id)) {
			$coupon_code = strtolower(get_post($post_id)->post_title);  // Assuming the coupon code is stored in the post title

			// Sanitize the coupon code before using it in the query
			$coupon_code = sanitize_text_field($coupon_code);

			// Execute the SQL query to get user IDs
			$user_ids = $wpdb->get_col($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT user_id FROM {$wpdb->usermeta}
                    WHERE meta_key = 'affiliate_coupon_code'
                    AND LOWER(meta_value) LIKE %s",
				'%' . $wpdb->esc_like($coupon_code) . '%'
			));

			// Iterate through each user and update their coupon data
			foreach ($user_ids as $user_id) {
				$user_coupons = get_user_meta($user_id, 'affiliate_coupon_code', true);

				if (!is_array($user_coupons)) {
					$user_coupons = array($user_coupons);  // Ensure array format
				}

				// Remove the trashed coupon code
				$updated_coupons = array_filter($user_coupons, function ($code) use ($coupon_code) {
					return $code !== $coupon_code;
				});

				// Update the user meta with the new array of coupons
				update_user_meta($user_id, 'affiliate_coupon_code', $updated_coupons);
			}
		}
	}

	public function track_affiliate_visit()
	{
		// @codingStandardsIgnoreLine
		if (isset($_GET['aw_affiliate'])) {

			global $wpdb;
			$visit_url = urldecode(home_url(add_query_arg(null, null))); // Current URL
			// @codingStandardsIgnoreLine
			//$aw_affiliate               = isset( $_GET['aw_affiliate'] ) ? json_decode( base64_decode( $_GET['aw_affiliate'] ), true ) : array();


			// Sanitize the base64-encoded string
			// @codingStandardsIgnoreLine
			$aw_affiliate_raw = isset($_GET['aw_affiliate']) ? sanitize_text_field($_GET['aw_affiliate']) : '';
			$aw_affiliate = array();

			if ($aw_affiliate_raw) {
				// Decode and validate the JSON data
				$decoded_aw_affiliate = json_decode(base64_decode($aw_affiliate_raw), true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_aw_affiliate)) {
					$aw_affiliate = $decoded_aw_affiliate;
				} else {
					// Optionally handle the case where the JSON data is invalid
					error_log("Invalid JSON data received in aw_affiliate parameter.");
				}
			}



			$campaign_id                = isset($aw_affiliate['campaign_id']) ? intval($aw_affiliate['campaign_id']) : 0;
			$affiliate_id               = isset($aw_affiliate['account_id']) ? intval($aw_affiliate['account_id']) : 0;
			$traffic_source = isset($aw_affiliate['traffic_source']) ? sanitize_text_field($aw_affiliate['traffic_source']) : '';

			$affiliate_link_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}affiliate_links WHERE affiliate_id = %d  AND traffic_source = %s AND campaign_id = %s",
					$affiliate_id,
					$traffic_source,
					$campaign_id
				)
			);

			$table_name_visits = $wpdb->prefix . 'affiliate_visits';

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_name_visits,
				array(
					'campaign_id'       => $campaign_id,
					'affiliate_id'      => $affiliate_id,
					'traffic_source'    => $traffic_source,
					'visit_url'         => $visit_url,
					'time'              => current_time('mysql'),
					'affiliate_link_id' => $affiliate_link_id,
				),
				array('%d', '%d', '%s', '%s', '%s', '%d')
			);
		}
	}

	public function handle_save_or_get_affiliate_link()
	{
		check_ajax_referer('affiliate_link_nonce', 'security');

		// Sanitize and validate the input
		$affiliate_id   = isset($_POST['affiliate_id']) ? intval($_POST['affiliate_id']) : 0;
		$campaign_id    = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
		$product_id     = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		$traffic_source = isset($_POST['traffic_source']) ? sanitize_text_field($_POST['traffic_source']) : '';
		$affiliate_link = isset($_POST['affiliate_link']) ? esc_url_raw($_POST['affiliate_link']) : '';
		$aw_affiliate   = isset($_POST['aw_affiliate']) ? sanitize_text_field($_POST['aw_affiliate']) : '';

		if (!$affiliate_id) {
			wp_send_json_error('Something went wrong. Please contact our support for assistance. <a href="/contact-us/">Contact us</a>');
		}

		// Ensure the current user is the one making the request
		if (intval(get_user_meta(get_current_user_id(), 'affiliate_account_id', true)) !== $affiliate_id) {
			wp_send_json_error('User ID mismatch');
		}

		// Call the method to save or get the affiliate link
		$result_link = self::save_or_get_affiliate_link($affiliate_id, $product_id, $traffic_source, $affiliate_link, $campaign_id, $aw_affiliate);

		// Return the link
		wp_send_json_success($result_link);
	}

	public static function save_or_get_affiliate_link($affiliate_id, $product_id, $traffic_source, $generated_link, $campaign_id, $aw_affiliate)
	{
		global $wpdb;
		$table_affiliate_links = $wpdb->prefix . 'affiliate_links';

		// Check if the link already exists
		$existing_link = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}affiliate_links WHERE affiliate_id = %d AND product_id = %d AND traffic_source = %s AND campaign_id = %s",
				$affiliate_id,
				$product_id,
				$traffic_source,
				$campaign_id
			)
		);

		if ($existing_link) {
			// Link already exists, return it
			return $generated_link;
		} else {
			// Save the new link
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_affiliate_links,
				array(
					'affiliate_id'   => $affiliate_id,
					'product_id'     => $product_id,
					'traffic_source' => $traffic_source,
					'campaign_id'    => $campaign_id,
					'affiliate_link' => $aw_affiliate,
				),
				array('%d', '%d', '%s', '%s', '%s')
			);
			return $generated_link;
		}
	}


	public static function get_user_total_earnings($affiliate_id)
	{
		global $wpdb;

		$total_earnings      = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT SUM(commission_amount) FROM {$wpdb->prefix}affiliate_order_stats WHERE affiliate_id = %d",
				$affiliate_id
			)
		);
		return $total_earnings;
	}



	private static function get_commission_rate_by_campaign($campaign_id)
	{
		global $wpdb;

		$commission_rate = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT commission_value FROM {$wpdb->prefix}affiliate_campaigns WHERE campaign_id = %d",
				$campaign_id
			)
		);

		return $commission_rate ? $commission_rate : 0; // Return 0 if no rate found
	}


	public function handle_order_placement($order_id)
	{
		// Check if the affiliate cookie is set
		if (isset($_COOKIE['affiliate_aw_affiliate'])) {
			// Retrieve the order object
			$order = wc_get_order($order_id);

			// Perform actions with the order, like adding order meta
			$affiliate_id = sanitize_text_field($_COOKIE['affiliate_aw_affiliate']);
			$order->update_meta_data('affiliate_aw_affiliate_id', $affiliate_id);
			$order->save();
		}
	}

	public function record_affiliate_order_stats($order_id)
	{
		global $wpdb;
		$order          = wc_get_order($order_id);
		$affiliate_data = $order->get_meta('affiliate_aw_affiliate_id');

		if (!empty($affiliate_data)) {
			$decodedContent = json_decode(base64_decode($affiliate_data), true);

			$affiliate_id   = isset($decodedContent['account_id']) ? intval($decodedContent['account_id']) : 0;
			$campaign_id    = isset($decodedContent['campaign_id']) ? intval($decodedContent['campaign_id']) : 0;
			$traffic_source = isset($decodedContent['traffic_source']) ? $decodedContent['traffic_source'] : '';

			if ($affiliate_id > 0) {
				// Check if order stats already recorded for this order
				$existing_stats = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}affiliate_order_stats WHERE order_id = %d",
						$order_id
					)
				);

				// If stats for this order not recorded yet, then proceed
				if ($existing_stats == 0) {
					$buyer_id = $order->get_customer_id();

					$commission_rate   = self::get_commission_rate_by_campaign($campaign_id); // Fetch the commission rate
					$commission_amount = ($order->get_total() * $commission_rate) / 100;

					// Check if the link already exists
					$affiliate_link_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prepare(
							"SELECT id FROM {$wpdb->prefix}affiliate_links WHERE affiliate_id = %d AND traffic_source = %s AND campaign_id = %s",
							$affiliate_id,
							$traffic_source,
							$campaign_id
						)
					);

					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->prefix . 'affiliate_order_stats',
						array(
							'affiliate_id'      => $affiliate_id,
							'order_id'          => $order_id,
							'buyer_id'          => $buyer_id,
							'commission_amount' => $commission_amount,
							'affiliate_link_id' => $affiliate_link_id,
						),
						array('%d', '%d', '%d', '%f', '%d')
					);
				}
			}
		}
	}
}
