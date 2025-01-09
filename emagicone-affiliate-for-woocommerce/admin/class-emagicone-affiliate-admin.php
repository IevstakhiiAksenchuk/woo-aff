<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Emagicone_Affiliate_Admin
{


	public function __construct()
	{
		// Initialize admin-related hooks here
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_post_emagicone_add_campaign', array($this, 'handle_add_campaign'));
		add_action('admin_post_emagicone_edit_campaign', array($this, 'handle_edit_campaign'));
		add_action('admin_post_emagicone_delete_campaign', array($this, 'handle_delete_campaign'));
		add_action('wp_ajax_handle_payout_action', array($this, 'ajax_handle_payout_action'));
		add_action('admin_init', array($this, 'display_settings_panel_fields'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_select2'));
		add_action('admin_post_emagicone_add_user_coupon', array($this, 'handle_add_user_coupon'));
		add_action('admin_init', array($this, 'handle_delete_user_coupon'));
		add_action('admin_init', array($this, 'handle_campaign_update'));
	}

	public function add_admin_menu()
	{
		// Main menu
		add_menu_page(
			__('EmagicOne Affiliate', 'emagicone-affiliate-for-woocommerce'),
			__('Affiliate', 'emagicone-affiliate-for-woocommerce'),
			'manage_options',
			'emagicone-affiliate-main',
			array($this, 'main_menu_page'),
			plugins_url('/assets/images/emagicone.png', __DIR__),
			6
		);

		// Rename the first submenu to "Products"
		add_submenu_page(
			'emagicone-affiliate-main',                      // The slug name for the parent menu (or the file name of a standard WordPress admin page)
			__('Products', 'emagicone-affiliate-for-woocommerce'),          // The text to be displayed in the title tags of the page when the menu is selected
			__('Products', 'emagicone-affiliate-for-woocommerce'),          // The text to be used for the menu
			'manage_options',                                // The capability required for this item to be displayed to the user
			'emagicone-affiliate-main',                      // The slug name to refer to this submenu by (should be unique for this submenu)
			array($this, 'main_menu_page')                 // The function to be called to output the content for this submenu
		);

		// Submenu for Campaigns
		add_submenu_page(
			'emagicone-affiliate-main',
			__('Affiliate Campaigns', 'emagicone-affiliate-for-woocommerce'),
			__('Campaigns', 'emagicone-affiliate-for-woocommerce'),
			'manage_options',
			'emagicone-affiliate-campaigns',
			array($this, 'campaigns_settings_page')
		);

		add_submenu_page(
			'emagicone-affiliate-main',
			__('User Campaigns', 'emagicone-affiliate-for-woocommerce'),
			__('Affiliates', 'emagicone-affiliate-for-woocommerce'),
			'manage_options',  // or 'read_demo_plugin_page' if you have a custom capability set up
			'emagicone-affiliate-user-campaigns',
			array($this, 'display_user_campaigns_page')
		);

		// Submenu for Payout Requests
		add_submenu_page(
			'emagicone-affiliate-main',
			__('Payout Requests', 'emagicone-affiliate-for-woocommerce'),
			__('Payouts', 'emagicone-affiliate-for-woocommerce'),
			'manage_options',
			'affiliate-payout-requests',
			array($this, 'display_payout_requests')
		);

		// Submenu for Coupons
		add_submenu_page(
			'emagicone-affiliate-main',
			__('User Coupons', 'emagicone-affiliate-for-woocommerce'),
			__('Coupons', 'emagicone-affiliate-for-woocommerce'),
			'manage_options',
			'emagicone-affiliate-user-coupons',
			array($this, 'display_user_coupons_page')
		);
	}


	// Main menu page callback
	public function main_menu_page()
	{
?>
		<div class="wrap">
			<form method="post" action="options.php">
				<?php
				settings_fields('section');
				do_settings_sections('emagicone-affiliate-options');
				submit_button();
				?>
			</form>
		</div>
	<?php
	}

	public function enqueue_select2($hook)
	{
		wp_enqueue_style('emagicone_affiliate_select2_css', EMAGICONE_AFFILIATE_BASE_URL . 'assets/css/select2/select2.min.css', false, '4.0.13');
		wp_enqueue_script('emagicone_affiliate_select2_js', EMAGICONE_AFFILIATE_BASE_URL . 'assets/js/select2/select2.min.js', array('jquery'), '4.0.13', true);
		wp_enqueue_style('emagicone_affiliate_admin_css', EMAGICONE_AFFILIATE_BASE_URL . 'assets/css/affiliate-admin.css', false, '0.1.0');
		wp_enqueue_script('emagicone_affiliate_admin_js', EMAGICONE_AFFILIATE_BASE_URL . 'assets/js/affiliate-admin.js', array('jquery'), '0.1.0', true);

		wp_localize_script('emagicone_affiliate_admin_js', 'affiliateAdmin', array(
			'nonce' => wp_create_nonce('handle_payout_action_nonce')
		));

		// Adding inline styles to the select2 stylesheet
		$inline_style = "
        #select-all, #deselect-all {
            margin: 5px;
            padding: 5px 10px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            cursor: pointer;
        }
        #select-all:hover, #deselect-all:hover {
            background-color: #e7e7e7;
        }
        .select2-container{width:100% !important;}
    ";
		wp_add_inline_style('emagicone_affiliate_select2_css', $inline_style);

		// Initialize Select2 for specific element
		wp_add_inline_script('emagicone_affiliate_select2_js', 'jQuery(document).ready(function($) { $("#selected_products").select2(); });');

		// Additional JavaScript for select-all and deselect-all functionality
		$script = "
        jQuery(document).ready(function($) {
            $('#select-all').click(function(){
                $('#selected_products > option').prop('selected', 'selected');
                $('#selected_products').trigger('change');
            });
            $('#deselect-all').click(function(){
                $('#selected_products > option').prop('selected', false);
                $('#selected_products').trigger('change');
            });
        });
    ";
		wp_add_inline_script('emagicone_affiliate_select2_js', $script);
	}



	public function handle_campaign_update()
	{
		if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['campaign_id']) && is_array($_POST['campaign_id'])) {
			// Verify nonce for security
			if (check_admin_referer('update_user_campaigns', 'user_campaigns_nonce')) {
				// Check if the current user has the capability to edit users
				if (current_user_can('edit_users')) {
					global $wpdb;
					// Check if the data is already cached
					$valid_campaign_ids = wp_cache_get('valid_campaign_ids', 'my_plugin_cache_group');

					if (false === $valid_campaign_ids) {
						// Data is not cached, fetch it from the database
						$valid_campaign_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
							"SELECT campaign_id FROM {$wpdb->prefix}affiliate_campaigns"
						);

						// Cache the data for future use
						wp_cache_set('valid_campaign_ids', $valid_campaign_ids, 'my_plugin_cache_group');
					}


					foreach ($_POST['campaign_id'] as $user_id => $campaign_id) {
						$user_id = intval($user_id); // Sanitize user ID
						$campaign_id = intval($campaign_id); // Sanitize campaign ID
						// Check if the submitted campaign ID is valid
						if (in_array($campaign_id, $valid_campaign_ids)) {
							update_user_meta($user_id, 'campaign_id', $campaign_id);
						} else {
							// Optionally handle the case where an invalid campaign ID is submitted
						}
					}

					// Optionally, add an admin notice or redirect to show success
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-success is-dismissible"><p>Campaigns updated successfully.</p></div>';
						}
					);
				} else {
					// User does not have the required capability
					wp_die('You do not have permission to edit user campaigns.');
				}
			} else {
				// Nonce verification failed
				wp_die('Security check failed; please try again.');
			}
		}
	}


	public function display_user_campaigns_page()
	{
		global $wpdb;

		// Handle search
		if (isset($_GET['user_campaign_nonce']) && wp_verify_nonce(sanitize_text_field($_GET['user_campaign_nonce']), 'user_campaign_search')) {
			$search_query = isset($_GET['s']) ? sanitize_text_field(trim(wp_unslash($_GET['s']))) : '';
			$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		} else {
			// Handle the case where the nonce is not set or invalid
			$search_query = '';
			$current_page = 1;
		}
		$per_page     = 10;  // Number of users per page

		// Fetch users with pagination and optional search
		$user_args  = array(
			'number'         => $per_page,
			'paged'          => $current_page,
			'role__in'   => array('Affiliate'),
			'search'         => '*' . esc_attr($search_query) . '*',
			'search_columns' => array('user_login', 'user_email', 'user_nicename'),
			'fields'         => 'all_with_meta',
		);
		$user_query = new WP_User_Query($user_args);
		$users      = $user_query->get_results();


		// Check if the data is already cached
		$campaigns = wp_cache_get('affiliate_campaigns_data', 'my_plugin_cache_group');

		if (false === $campaigns) {
			// Data is not cached, fetch it from the database
			$campaigns = $wpdb->get_results("SELECT campaign_id, name FROM {$wpdb->prefix}affiliate_campaigns"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			// Cache the data for future use
			wp_cache_set('affiliate_campaigns_data', $campaigns, 'my_plugin_cache_group');
		}

		echo '<div class="wrap"><h1>' . esc_html__('Affiliates', 'emagicone-affiliate-for-woocommerce') . '</h1>';

	?>
		<form method="get">
			<input type="hidden" name="page" value="emagicone-affiliate-user-campaigns">
			<input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php echo esc_html__('Search users...', 'emagicone-affiliate-for-woocommerce'); ?>" style="margin-bottom: 15px;">
			<?php wp_nonce_field('user_campaign_search', 'user_campaign_nonce'); ?>
			<input type="submit" value="<?php echo esc_html__('Search', 'emagicone-affiliate-for-woocommerce'); ?>" class="button">
		</form>
		<?php

		echo '<form method="post" action="">';
		wp_nonce_field('update_user_campaigns', 'user_campaigns_nonce'); // Nonce field

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__('User', 'emagicone-affiliate-for-woocommerce') . '</th><th>' . esc_html__('Registration Date', 'emagicone-affiliate-for-woocommerce') . '</th><th>' . esc_html__('Assigned Campaign', 'emagicone-affiliate-for-woocommerce') . '</th></tr></thead>';
		echo '<tbody>';

		foreach ($users as $user) {
			$current_campaign_id = get_user_meta($user->ID, 'campaign_id', true) ?: 1; // Default campaign ID if empty
			$registration_date = date_i18n(get_option('date_format'), strtotime($user->user_registered)); // Format the registration date based on the site's date format

			echo '<tr>';
			echo '<td>' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</td>';
			echo '<td>' . esc_html($registration_date) . '</td>'; // Display the formatted registration date
			//echo '<td>' . esc_html($affiliate_link) . '</td>'; // Display the link hash
			echo '<td>';
			echo '<select name="campaign_id[' . esc_attr($user->ID) . ']">';
			foreach ($campaigns as $campaign) {
				echo '<option value="' . esc_attr($campaign->campaign_id) . '"' . selected($current_campaign_id, $campaign->campaign_id, false) . '>' . esc_html($campaign->name) . '</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<input type="submit" class="button button-primary" value="' . esc_html__('Update All', 'emagicone-affiliate-for-woocommerce') . '" style="margin-top: 15px;">';
		echo '</form>'; // Form end
		echo '</div>';

		// Pagination links
		echo '<div class="pagination">';
		echo esc_html(paginate_links(
			array(
				'base'     => add_query_arg('paged', '%#%'),
				'format'   => '?paged=%#%',
				'current'  => $current_page,
				'total'    => ceil($user_query->total_users / $per_page),
				'add_args' => array('s' => $search_query), // Preserve other query strings like search
			)
		));
		echo '</div>';
	}


	public function display_product_dropdown()
	{
		$args = array(
			'limit'  => -1,
			'status' => 'publish',
			// 'type'   => 'simple',
		);

		$products = wc_get_products($args);
		$selected_products = get_option('emagicone_affiliate_selected_products', array());
		if (!is_array($selected_products)) {
			$selected_products = array();
		}

		echo '<select name="emagicone_affiliate_selected_products[]" id="selected_products" class="affiliate-dropdown" multiple>';
		foreach ($products as $product) {
			echo '<option value="' . esc_attr($product->get_id()) . '"';
			echo in_array($product->get_id(), $selected_products) ? ' selected="selected"' : '';
			echo '>' . esc_html($product->get_name()) . '</option>';
		}

		echo '</select>';
		echo '<button type="button" id="select-all">' . esc_html__('Select All', 'emagicone-affiliate-for-woocommerce') . '</button>';
		echo '<button type="button" id="deselect-all">' . esc_html__('Deselect All', 'emagicone-affiliate-for-woocommerce') . '</button>';
	}


	public function display_settings_panel_fields()
	{
		add_settings_section('emagicone_affiliate_section', esc_html__('Affiliate Products Settings', 'emagicone-affiliate-for-woocommerce'), null, 'emagicone-affiliate-options');
		add_settings_field('emagicone_affiliate_selected_products', esc_html__('Select Products', 'emagicone-affiliate-for-woocommerce'), array($this, 'display_product_dropdown'), 'emagicone-affiliate-options', 'emagicone_affiliate_section');
		add_settings_field('emagicone_affiliate_use_recaptcha', esc_html__('Enable Recaptcha', 'emagicone-affiliate-for-woocommerce'), array($this, 'display_use_recaptcha_checkbox'), 'emagicone-affiliate-options', 'emagicone_affiliate_section');
		register_setting('emagicone_affiliate_options', 'emagicone_affiliate_selected_products');
                register_setting('emagicone_affiliate_options', 'emagicone_affiliate_use_recaptcha');
                register_setting('emagicone_affiliate_options', 'emagicone_affiliate_recaptcha_api_key');
                register_setting('emagicone_affiliate_options', 'emagicone_affiliate_recaptcha_secret_key');

	}

	public function display_use_recaptcha_checkbox()
	{
		$checked = get_option('emagicone_affiliate_use_recaptcha', '');
		$api_key_value    = get_option('emagicone_affiliate_recaptcha_api_key', '');
		$secret_key_value = get_option('emagicone_affiliate_recaptcha_secret_key', '');
		echo '<input type="checkbox" id="use_recaptcha" name="emagicone_affiliate_use_recaptcha"' . checked(1, $checked, false) . ' value="1">';
		echo '
                    <tr class="recaptcha-fields"><th scope="row">Recaptcha API Key</th><td>
                        <input type="text" id="recaptcha_api_key" name="emagicone_affiliate_recaptcha_api_key" value="' . esc_attr($api_key_value) . '">
                    </td></tr>
                    <tr class="recaptcha-fields"><th scope="row">Recaptcha Secret Key</th><td>
                        <input type="text" id="recaptcha_secret_key" name="emagicone_affiliate_recaptcha_secret_key" value="' . esc_attr($secret_key_value) . '">
                    </td></tr>
                ';
	}

	public function display_payout_requests()
	{
		global $wpdb;
		$payout_requests = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        ap1.*,
                        IF(ap1.action_type = %s, %s, ap1.status) AS effective_status
                    FROM 
                        {$wpdb->prefix}affiliate_payouts ap1
                    INNER JOIN 
                        (SELECT 
                            request_identifier, 
                            MAX(id) AS max_id 
                        FROM 
                            {$wpdb->prefix}affiliate_payouts
                        GROUP BY 
                            request_identifier) ap2 
                    ON 
                        ap1.request_identifier = ap2.request_identifier AND ap1.id = ap2.max_id
                    ORDER BY 
                        ap1.action_date ASC, ap1.id DESC",
                    'cancel', 'canceled'
                ));


		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('Affiliate Payout Requests', 'emagicone-affiliate-for-woocommerce') . '</h1>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>ID</th><th>Affiliate</th><th>Amount</th><th>Date</th><th>Status</th><th>Admin Notes</th><th>Actions</th></tr></thead>';
		echo '<tbody>';

		foreach ($payout_requests as $request) {
                    
                        $user_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'affiliate_account_id' AND meta_value = %s",
					$request->affiliate_id
				)
			);
			$paypal_email = get_user_meta($user_id, 'paypal_email', true);
			if (empty($paypal_email)) {
				$paypal_email = 'not set';
			}
			echo '<tr>';
			echo '<td>' . esc_html($request->id) . '</td>';
			echo '<td>' . esc_html($paypal_email) . '</td>';
			echo '<td>' . wp_kses_post(wc_price($request->amount)) . '</td>';
			echo '<td>' . esc_html($request->action_date) . '</td>';
			echo '<td>' . esc_html($request->effective_status) . '</td>';
			echo '<td>' . esc_html($request->admin_notes) . '</td>';
			if ($request->status === 'requested') {
				echo '<td>';
				echo '<input type="text" id="admin_notes_' . esc_attr($request->id) . '" placeholder="Admin notes" style="margin-bottom:2px;">';
				echo '<button class="button approve-payout" data-id="' . esc_attr($request->id) . '">Paid</button>';
				echo '<button class="button decline-payout" data-id="' . esc_attr($request->id) . '">Decline</button>';
				echo '</td>';
			} else {
				echo '<td></td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	public function campaigns_settings_page()
	{
		global $wpdb;
		$campaigns = wp_cache_get('affiliate_campaigns_data', 'my_plugin_cache_group');

		if (false === $campaigns) {
			// Data is not cached, fetch it from the database
			$campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}affiliate_campaigns"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			// Cache the data for future use
			wp_cache_set('affiliate_campaigns_data', $campaigns, 'my_plugin_cache_group');
		}

		// @codingStandardsIgnoreLine
		$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
		// @codingStandardsIgnoreLine
		if ($action == 'edit' && isset($_GET['campaign_id'])) {
			// @codingStandardsIgnoreLine
			$campaign_id      = intval($_GET['campaign_id']);
			$campaign_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}affiliate_campaigns WHERE campaign_id = %d", $campaign_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			if ($campaign_to_edit) {
				// Display the edit form with pre-filled data
		?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=emagicone-affiliate-campaigns')); ?>" class="button emagicone-back-button"><?php esc_html_e('Back to Campaigns', 'emagicone-affiliate-for-woocommerce'); ?></a>
				<div class="wrap">
					<h1><?php esc_html_e('Edit Campaign', 'emagicone-affiliate-for-woocommerce'); ?></h1>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="emagicone_edit_campaign">
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign_to_edit->campaign_id); ?>">
						<?php wp_nonce_field('emagicone_edit_campaign_action', 'emagicone_edit_campaign_nonce'); ?>

						<table class="form-table">
							<tr>
								<th scope="row"><label for="campaign_name"><?php esc_html_e('Campaign Name:', 'emagicone-affiliate-for-woocommerce'); ?></label></th>
								<td><input type="text" name="campaign_name" id="campaign_name" value="<?php echo esc_attr($campaign_to_edit->name); ?>" class="regular-text" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="campaign_desc"><?php esc_html_e('Description:', 'emagicone-affiliate-for-woocommerce'); ?></label></th>
								<td><textarea name="campaign_desc" id="campaign_desc" rows="5" class="large-text"><?php echo esc_textarea($campaign_to_edit->description); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="commission_value"><?php esc_html_e('Commission, %:', 'emagicone-affiliate-for-woocommerce'); ?></label></th>
								<td><input type="number" name="commission_value" id="commission_value" value="<?php echo esc_attr($campaign_to_edit->commission_value); ?>" class="small-text" step="0.01" required></td>
							</tr>
						</table>

						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_html_e('Update Campaign', 'emagicone-affiliate-for-woocommerce'); ?>">
						</p>
					</form>
				</div>

			<?php
				return; // Stop further rendering to only show the edit form
			}
		} elseif ($action === 'add_new') {

			?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=emagicone-affiliate-campaigns')); ?>" class="button emagicone-back-button"><?php esc_html_e('Back to Campaigns', 'emagicone-affiliate-for-woocommerce'); ?></a>
			<div class="emagicone-form-wrap">
				<!-- Form for Adding New Campaign -->
				<h2><?php esc_html_e('Add New Campaign', 'emagicone-affiliate-for-woocommerce'); ?></h2>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="emagicone_add_campaign">
					<?php wp_nonce_field('emagicone_add_campaign_action', 'emagicone_add_campaign_nonce'); ?>

					<p>
						<label for="campaign_name"><?php esc_html_e('Campaign Name:', 'emagicone-affiliate-for-woocommerce'); ?></label><br>
						<input type="text" name="campaign_name" id="campaign_name" required>
					</p>
					<p>
						<label for="campaign_desc"><?php esc_html_e('Description:', 'emagicone-affiliate-for-woocommerce'); ?></label><br>
						<textarea name="campaign_desc" id="campaign_desc"></textarea>
					</p>
					<p>
						<label for="commission_value"><?php esc_html_e('Commission, %:', 'emagicone-affiliate-for-woocommerce'); ?></label><br>
						<input type="number" name="commission_value" id="commission_value" step="0.01" required>
					</p>
					<input type="submit" value="<?php esc_attr_e('Add Campaign', 'emagicone-affiliate-for-woocommerce'); ?>">
				</form>
			</div>

		<?php

		} else {
		?>


			<div class="wrap">
				<h1><?php esc_html_e('Affiliate Campaigns', 'emagicone-affiliate-for-woocommerce'); ?></h1>
				<a href="<?php echo esc_url(admin_url('admin.php?page=emagicone-affiliate-campaigns&action=add_new')); ?>" class="button button-primary" style="margin-bottom: 10px;">Add New Campaign</a>

				<table class="wp-list-table widefat fixed striped emagicone-affiliate-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Campaign Name', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Description', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Commission, %:', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Actions', 'emagicone-affiliate-for-woocommerce'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($campaigns as $campaign) { ?>
							<tr>
								<td><?php echo esc_html($campaign->name); ?></td>
								<td><?php echo esc_html($campaign->description); ?></td>
								<td><?php echo esc_html($campaign->commission_value); ?></td>
								<td>
									<?php if ($campaign->campaign_id != 1) { ?>
										<a href="
										<?php
										echo esc_url(
											add_query_arg(
												array(
													'action' => 'edit',
													'campaign_id' => $campaign->campaign_id,
												)
											)
										);
										?>
													" class="button">Edit</a>
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
											<input type="hidden" name="action" value="emagicone_delete_campaign">
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->campaign_id); ?>">
											<?php wp_nonce_field('emagicone_delete_campaign_action', 'emagicone_delete_campaign_nonce'); ?>
											<input type="submit" class="button" value="<?php esc_html_e('Delete', 'emagicone-affiliate-for-woocommerce'); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this campaign?', 'emagicone-affiliate-for-woocommerce')); ?>');">
										</form>
									<?php } else { ?>
										<?php // _e('Default Campaign, can\'t be removed', 'emagicone-affiliate-for-woocommerce'); 
										?>
										<a href="
										<?php
										echo esc_url(
											add_query_arg(
												array(
													'action' => 'edit',
													'campaign_id' => $campaign->campaign_id,
												)
											)
										);
										?>
													" class="button">Edit</a>
									<?php } ?>
								</td>

							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>

		<?php
		}
	}

	public function handle_edit_campaign()
	{
		if (!current_user_can('manage_options') || !check_admin_referer('emagicone_edit_campaign_action', 'emagicone_edit_campaign_nonce')) {
			wp_die('You are not allowed to perform this action.');
		}

		global $wpdb;
		$table_name_campaigns = $wpdb->prefix . 'affiliate_campaigns';

		// Sanitize and validate inputs
		$campaign_id      = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
		$campaign_name    = isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '';
		$campaign_desc    = isset($_POST['campaign_desc']) ? sanitize_textarea_field($_POST['campaign_desc']) : '';
		$commission_value = isset($_POST['commission_value']) ? floatval($_POST['commission_value']) : 0.0;

		// Update the campaign in the database
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_name_campaigns,
			array(
				'name'             => $campaign_name,
				'description'      => $campaign_desc,
				'commission_value' => $commission_value,
			),
			array('campaign_id' => $campaign_id),
			array('%s', '%s', '%f'),
			array('%d')
		);

		// Redirect back to the settings page
		wp_redirect(add_query_arg('page', 'emagicone-affiliate-campaigns', admin_url('admin.php')));
		exit;
	}


	public function handle_delete_campaign()
	{
		if (!current_user_can('manage_options') || !isset($_POST['campaign_id']) || !check_admin_referer('emagicone_delete_campaign_action', 'emagicone_delete_campaign_nonce')) {
			wp_die('You are not allowed to perform this action.');
		}

		global $wpdb;
		$table_name_campaigns = $wpdb->prefix . 'affiliate_campaigns';
		$campaign_id          = intval($_POST['campaign_id']);

		// Delete the campaign from the database
		$wpdb->delete($table_name_campaigns, array('campaign_id' => $campaign_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		// Redirect back to the settings page with a success message
		wp_redirect(
			add_query_arg(
				array(
					'page'    => 'emagicone-affiliate-campaigns',
					'message' => 'deleted',
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	public function ajax_handle_payout_action()
	{
		check_ajax_referer('handle_payout_action_nonce', 'nonce');

		$payout_id   = isset($_POST['payout_id']) ? intval($_POST['payout_id']) : 0;
		$action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
		$admin_notes = isset($_POST['admin_notes']) ? sanitize_text_field($_POST['admin_notes']) : '';

		if (current_user_can('manage_options')) {
			$status = ($action_type === 'approve') ? 'approved' : 'declined';
			$this->update_payout_request($payout_id, $status, $admin_notes);
			wp_send_json_success('Payout ' . $action_type . 'd successfully.');
		} else {
			wp_send_json_error('You do not have permission to perform this action.');
		}
	}

	public function update_payout_request($payout_id, $status, $admin_notes = '')
	{
		global $wpdb;
		$table_affiliate_payouts = $wpdb->prefix . 'affiliate_payouts';

		$original = $wpdb->get_row($wpdb->prepare("SELECT affiliate_id, amount, request_identifier FROM {$wpdb->prefix}affiliate_payouts WHERE id = %d", $payout_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ($original) {
			$action_type = ($status === 'approved') ? 'approve' : 'decline';

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_affiliate_payouts,
				array(
					'affiliate_id'       => $original->affiliate_id,
					'amount'             => $original->amount,
					'action_date'        => current_time('mysql'),
					'action_type'        => $action_type,
					'status'             => $status,
					'admin_notes'        => $admin_notes,
					'request_identifier' => $original->request_identifier,
				),
				array('%d', '%f', '%s', '%s', '%s', '%s', '%s')
			);
		}
	}

	public function handle_add_campaign()
	{
		if (!current_user_can('manage_options') || !check_admin_referer('emagicone_add_campaign_action', 'emagicone_add_campaign_nonce')) {
			wp_die('You are not allowed to perform this action.');
		}

		global $wpdb;
		$table_name_campaigns = $wpdb->prefix . 'affiliate_campaigns';

		// Sanitize and validate inputs
		$campaign_name    = sanitize_text_field($_POST['campaign_name']);
		$campaign_desc    = sanitize_textarea_field($_POST['campaign_desc']);
		$commission_value = floatval($_POST['commission_value']);

		// Insert into database
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_name_campaigns,
			array(
				'name'             => $campaign_name,
				'description'      => $campaign_desc,
				'commission_value' => $commission_value,
			),
			array('%s', '%s', '%f')
		);

		// Redirect back to the settings page
		wp_redirect(add_query_arg('page', 'emagicone-affiliate-campaigns', admin_url('admin.php')));
		exit;
	}

	public function generate_coupons_for_users($user_id = null)
	{
		// Get all users
		if ($user_id) {
			// Generate coupon for a specific user
			$users = array(get_user_by('id', $user_id));
			if (!$users[0]) {
				// Handle case where user does not exist
				return false;
			}
		} else {
			// Get all users
			$users = get_users(
				array(
					'role__not_in' => array('Administrator', 'Editor'), // Use the correct role names
				)
			);
		}

		foreach ($users as $user) {
			// Generate coupon code for each user
			$coupon_code = $this->generate_coupon_code($user->ID);
			// Check if the coupon already exists
			if (!wc_get_coupon_id_by_code($coupon_code)) {
				// Coupon does not exist, create a new one
				$coupon = new WC_Coupon();
				$coupon->set_code($coupon_code);

				// Set coupon properties
				$coupon->set_discount_type('percent');
				$coupon->set_amount(15); // 15% off
				$coupon->set_individual_use(true);
				$coupon->set_exclude_sale_items(false);
				$coupon->set_usage_limit(100); // Corrected: Limit usage to 1 time per user
				$coupon->set_usage_limit_per_user(1); // Corrected: Limit usage to 1 time per user
				// Set the expiry date
				$expiry_date = '2030-12-31';
				$coupon->set_date_expires(strtotime($expiry_date));

				// Save the coupon
				$coupon->save();

				// Update user meta for affiliate_coupon_code
				$existing_coupons = get_user_meta($user->ID, 'affiliate_coupon_code', true);
				if (empty($existing_coupons)) {
					// No existing coupons, add the first one as an array
					update_user_meta($user->ID, 'affiliate_coupon_code', array($coupon_code));
				} else {
					if (is_string($existing_coupons)) {
						// Convert string to array and add the new coupon code
						$existing_coupons = array($existing_coupons, $coupon_code);
					} elseif (is_array($existing_coupons)) {
						// Simply add the new coupon code to the array
						$existing_coupons[] = $coupon_code;
					}
					update_user_meta($user->ID, 'affiliate_coupon_code', $existing_coupons);
				}
			}
		}
		if (is_admin()) {
			// Display success message
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Coupons have been generated successfully.', 'emagicone-affiliate-for-woocommerce')) . '</p></div>';
		}
	}

	public function generate_coupon_code($user_id)
	{
		// Retrieve the user by their ID
		$user = get_user_by('id', $user_id);

		if ($user) {
			// Remove any characters that are not letters or numbers from the username
			$sanitized_username = preg_replace('/[^a-zA-Z0-9]/', '', $user->user_login);

			// Ensure we get up to the first three alphanumeric characters from the username
			$username_prefix = substr($sanitized_username, 0, 3);

			// Check if the sanitized username has less than 3 characters
			if (strlen($username_prefix) < 3) {
				// Handle the case where the sanitized username is too short
				// Use a default prefix and append the user ID
				$username_prefix = 'DEF';
			}

			// Generate the coupon code
			return strtolower($username_prefix) . $user_id . '15-off';
		}

		// Return a default or error code if the user doesn't exist
		return 'ERROR' . $user_id;
	}


	public function coupon_exists($coupon_code)
	{
		global $wpdb;

		// Sanitize the coupon code before using it in the query
		$coupon_code = sanitize_text_field($coupon_code);

		// Execute the SQL query
		$coupon_post_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'shop_coupon'
                AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = 'code'
                    AND meta_value = %s
                )
                LIMIT 1",
			$coupon_code
		));

		// Check if a post ID was returned (coupon exists) or not
		return !empty($coupon_post_id);
	}

	private function get_coupons()
	{
		$args    = array(
			'posts_per_page' => -1,
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
		);
		$coupons = get_posts($args);

		return array_map(
			function ($post) {
				return new WC_Coupon($post->ID);
			},
			$coupons
		);
	}


	public function display_user_coupons_page()
	{
		global $wpdb;
		$users = get_users(
			array(
				'role__in' => array('Affiliate'), // Use the correct role names
			)
		);
		echo '<div class="wrap"><h1>' . esc_html__('User Coupons', 'emagicone-affiliate-for-woocommerce') . '</h1>';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . esc_html__('User', 'emagicone-affiliate-for-woocommerce') . '</th><th>' . esc_html__('Coupon Code', 'emagicone-affiliate-for-woocommerce') . '</th><th>' . esc_html__('Discount Details', 'emagicone-affiliate-for-woocommerce') . '</th><th>' . esc_html__('Actions', 'emagicone-affiliate-for-woocommerce') . '</th></tr></thead>';
		echo '<tbody>';

		// Fetch existing coupons
		$coupons = $this->get_coupons();
		$coupons_lookup = [];

		// Create a lookup array to easily access coupon objects by code
		foreach ($coupons as $coupon) {
			$coupons_lookup[$coupon->get_code()] = $coupon;
		}

		foreach ($users as $user) {
			$user_coupons = get_user_meta($user->ID, 'affiliate_coupon_code', true);
			if (!is_array($user_coupons)) {
				$user_coupons = array($user_coupons);
			}
			foreach ($user_coupons as $coupon_code) {
				echo '<tr>';
				echo '<td>' . esc_html($user->user_email) . '</td>';
				echo '<td>' . esc_html($coupon_code) . '</td>';
				echo '<td>';
				if (isset($coupons_lookup[$coupon_code])) {
					$coupon = $coupons_lookup[$coupon_code];
					$discount_type = $coupon->get_discount_type();
					$amount = $coupon->get_amount();

					if ($discount_type == 'percent') {
						echo esc_html($amount . '%');
					} elseif ($discount_type == 'fixed_cart' || $discount_type == 'fixed_product') {
						echo '$' . esc_html($amount);
					}
				} else {
					echo 'No details';
				}
				echo '</td>';
				echo '<td>';
				// Uncomment the following line if editing is needed
				// echo '<a href="' . esc_url(admin_url('admin.php?page=emagicone-affiliate-user-coupons&edit_coupon=' . $coupon_code)) . '" class="button">' . esc_html__('Edit', 'emagicone-affiliate-for-woocommerce') . '</a> ';
				echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?page=emagicone-affiliate-user-coupons&action=delete_coupon&coupon_code=' . $coupon_code . '&user_id=' . $user->ID), 'delete_coupon_' . $coupon_code)) . '" class="button">' . esc_html__('Delete', 'emagicone-affiliate-for-woocommerce') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';


		// Add coupon form
		echo '<h2>' . esc_html__('Add Coupon', 'emagicone-affiliate-for-woocommerce') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		wp_nonce_field('emagicone_add_user_coupon_action', 'emagicone_add_user_coupon_nonce');
		echo '<input type="hidden" name="action" value="emagicone_add_user_coupon">';

		// User select
		echo '<select name="user_id">';
		foreach ($users as $user) {
			echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->user_email) . '</option>';
		}
		echo '</select>';

		// Coupon select
		echo '<select name="coupon_code">';
		foreach ($coupons as $coupon) {
			$discount_type = $coupon->get_discount_type();  // Get the type of discount
			$amount = $coupon->get_amount();                // Get the discount amount

			// Format the display based on the discount type
			if ($discount_type == 'percent') {
				$display = $coupon->get_code() . ' - ' . $amount . '%';
			} elseif ($discount_type == 'fixed_cart' || $discount_type == 'fixed_product') {
				$display =  $coupon->get_code()  . ' - $' . $amount;
			} else {
				$display =  $coupon->get_code();  // Default display if discount type is unknown
			}

			// Output the option
			echo '<option value="' . esc_attr($coupon->get_code()) . '">' . esc_html($display) . '</option>';
		}
		echo '</select>';

		echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Add Coupon', 'emagicone-affiliate-for-woocommerce') . '">';
		echo '</form>';

		echo '</div>';



		?>
		<div class="wrap">
			<h1><?php esc_html_e('Coupon Generator', 'emagicone-affiliate-for-woocommerce'); ?></h1>
			<p><?php esc_html_e('Automatically generate coupons and assign them to new affiliates.', 'emagicone-affiliate-for-woocommerce'); ?></p>

			<!-- Button to trigger coupon generation -->
			<form method="post" action="">
				<input type="submit" name="generate_coupons" id="generate_coupons" class="button button-primary" value="<?php esc_html_e('Generate Coupons', 'emagicone-affiliate-for-woocommerce'); ?>">
				<?php wp_nonce_field('generate_coupons_action', 'generate_coupons_nonce'); ?>
			</form>

			<?php
			// Check if the button is clicked and nonce is verified
			if (isset($_POST['generate_coupons']) && check_admin_referer('generate_coupons_action', 'generate_coupons_nonce')) {
				// Call function to generate coupons
				$this->generate_coupons_for_users();
			}
			?>
		</div>

<?php

	}

	public function handle_add_user_coupon()
	{
		if (!check_admin_referer('emagicone_add_user_coupon_action', 'emagicone_add_user_coupon_nonce')) {
			wp_die('You are not allowed to perform this action.');
		}

		$user_id     = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';

		if (!empty($coupon_code) && $user_id > 0) {
			$existing_coupons = get_user_meta($user_id, 'affiliate_coupon_code', true);
			if (!is_array($existing_coupons)) {
				$existing_coupons = empty($existing_coupons) ? array() : array($existing_coupons);
			}
			if (!in_array($coupon_code, $existing_coupons)) {
				$existing_coupons[] = $coupon_code;
				update_user_meta($user_id, 'affiliate_coupon_code', $existing_coupons);
			}
		}

		wp_redirect(add_query_arg('page', 'emagicone-affiliate-user-coupons', admin_url('admin.php')));
		exit;
	}

	public function handle_delete_user_coupon()
	{
		if (!isset($_GET['action']) || $_GET['action'] !== 'delete_coupon') {
			return; // Exit if it's not the delete coupon action
		}

		$coupon_code  = isset($_GET['coupon_code']) ? sanitize_text_field($_GET['coupon_code']) : '';
		$nonce_action = 'delete_coupon_' . $coupon_code;

		if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_action) || empty($coupon_code)) {
			wp_die('You are not allowed to perform this action.');
		}

		$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

		if ($user_id > 0) {
			$existing_coupons = get_user_meta($user_id, 'affiliate_coupon_code', true);
			if (!is_array($existing_coupons)) {
				$existing_coupons = empty($existing_coupons) ? array() : array($existing_coupons);
			}
			if (($key = array_search($coupon_code, $existing_coupons)) !== false) {
				unset($existing_coupons[$key]);
				update_user_meta($user_id, 'affiliate_coupon_code', array_values($existing_coupons)); // Re-index the array
			}
		}

		wp_redirect(add_query_arg('page', 'emagicone-affiliate-user-coupons', admin_url('admin.php')));
		exit;
	}
}
