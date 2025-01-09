<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (!is_user_logged_in()) {
	echo esc_html__('You must be logged in to view this page.', 'emagicone-affiliate-for-woocommerce');
	return;
}

?>
<section class="page-row">

	<h2><?php echo esc_html__('Affiliate Dashboard', 'emagicone-affiliate-for-woocommerce'); ?></h2>
	<div class="emagicone-affiliate-dashboard">


		<!-- Tab links -->
		<div class="tab">
			<button class="tablinks" onclick="openTab(event, 'Information')"><?php echo esc_html__('Affiliate Information', 'emagicone-affiliate-for-woocommerce'); ?></button>
			<button class="tablinks" onclick="openTab(event, 'Products')"><?php echo esc_html__('Products', 'emagicone-affiliate-for-woocommerce'); ?></button>
			<button class="tablinks" onclick="openTab(event, 'Statistics')"><?php echo esc_html__('Statistics', 'emagicone-affiliate-for-woocommerce'); ?></button>
			<button class="tablinks" onclick="openTab(event, 'Transactions')"><?php echo esc_html__('Payouts', 'emagicone-affiliate-for-woocommerce'); ?></button>
			<?php
			$logout_url = wc_logout_url();
			echo '<a href="' . esc_url($logout_url) . '">Logout</a>';
			?>
		</div>

		<div id="Information" class="tabcontent">
			<h4><?php echo esc_html__('Affiliate Information', 'emagicone-affiliate-for-woocommerce'); ?></h4>
			<?php
			// Assume $user_id is the current user's ID
			$user_id      = get_current_user_id();
			$affiliate_id = get_user_meta($user_id, 'affiliate_account_id', true);
			// Access static methods from the Emagicone_Affiliate_Plugin class
			$user_earnings      = Emagicone_Affiliate_Plugin::get_user_total_earnings($affiliate_id); // Function to get total earnings
			$earnings_paid      = Emagicone_Affiliate_Plugin::get_user_earnings_paid($affiliate_id); // Function to get earnings paid
			$earnings_in_review = Emagicone_Affiliate_Plugin::get_user_earnings_in_review($affiliate_id); // Function to get earnings in review
			$user_campaign_id   = Emagicone_Affiliate_Plugin::get_user_current_campaign($user_id); // Function to get current campaign ID
			$user_campaign_name = Emagicone_Affiliate_Plugin::get_campaign_name_by_id($user_campaign_id);

			$user_coupon_code = get_user_meta($user_id, 'affiliate_coupon_code', true); // Retrieve the user's coupon code
			echo '<p>' . esc_html($user_campaign_name) . '</p>';
			// Display the user's personal coupon code(s)
			if (!empty($user_coupon_code)) {
				if (is_array($user_coupon_code)) {
					echo '<p>' . esc_html__('Affiliate Coupon Codes:', 'emagicone-affiliate-for-woocommerce') . ' ';
					foreach ($user_coupon_code as $coupon_code) {
						$coupon = new WC_Coupon($coupon_code);
						$discount_type = $coupon->get_discount_type();
						$amount = $coupon->get_amount();

						// Format the display based on the discount type
						if ($discount_type == 'percent') {
							$display = $coupon_code . ' - (' . $amount . '%)';
						} elseif ($discount_type == 'fixed_cart' || $discount_type == 'fixed_product') {
							$display = $coupon_code . ' - ($' . $amount . ')';
						} else {
							$display = $coupon_code;  // Default display if discount type is unknown
						}
						echo '<strong>' . esc_html($display) . '</strong>, ';
					}
					echo '</p>';
				} else {
					$coupon = new WC_Coupon($user_coupon_code);
					$discount_type = $coupon->get_discount_type();
					$amount = $coupon->get_amount();

					// Format the display based on the discount type
					if ($discount_type == 'percent') {
						$display = $user_coupon_code . ' - ' . $amount . '%';
					} elseif ($discount_type == 'fixed_cart' || $discount_type == 'fixed_product') {
						$display = $user_coupon_code . ' - $' . $amount;
					} else {
						$display = $user_coupon_code;  // Default display if discount type is unknown
					}

					echo '<p>' . esc_html__('Affiliate Coupon Code:', 'emagicone-affiliate-for-woocommerce') . ' <strong>' . esc_html($display) . '</strong></p>';
				}
			} else {
				echo '<p>' . esc_html__('You do not have a personal coupon code.', 'emagicone-affiliate-for-woocommerce') . '</p>';
			}


			echo '<p>' . esc_html__('Total Earnings:', 'emagicone-affiliate-for-woocommerce') . ' ' . wc_price($user_earnings) /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns escaped HTML */ . '</p>';
			echo '<p>' . esc_html__('Paid:', 'emagicone-affiliate-for-woocommerce') . ' ' . wc_price($earnings_paid) /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns escaped HTML */ . '</p>';
			echo '<p>' . esc_html__('Pending:', 'emagicone-affiliate-for-woocommerce') . ' ' . wc_price($earnings_in_review) /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns escaped HTML */ . '</p>';



			?>
			<div class="payments-wrap">
				<h4><?php esc_html_e('Payments', 'emagicone-affiliate-for-woocommerce'); ?></h4>
				<?php
				$user_id              = get_current_user_id();
				$affiliate_id         = get_user_meta($user_id, 'affiliate_account_id', true);
				$latest_payout        = Emagicone_Affiliate_Plugin::get_latest_payout_request($affiliate_id); // Method to retrieve transactions
				$payout_request_nonce = wp_create_nonce('payout_request_nonce'); // Create the nonce
				$total_earnings       = Emagicone_Affiliate_Plugin::get_user_total_earnings($affiliate_id);
				$earnings_paid        = Emagicone_Affiliate_Plugin::get_user_earnings_paid($affiliate_id); // Function to get earnings paid
				$payout_cancel_nonce  = wp_create_nonce('payout_cancel_nonce');
				$is_disabled          = $total_earnings - $earnings_paid < 100;
				?>

				<div id="payoutRequestForm" style="display: flex; justify-content: space-between;">
					<div style="flex: 1;">
						<?php if ($latest_payout && $latest_payout->status == 'requested') : ?>
							<p><?php esc_html_e('Your latest payout request is currently being reviewed.', 'emagicone-affiliate-for-woocommerce'); ?></p>
							<button id="cancelPayoutBtn" data-payout-id="<?php echo esc_attr($latest_payout->id); ?>"><?php esc_html_e('Cancel Request', 'emagicone-affiliate-for-woocommerce'); ?>
								<span class="loader" style="display: none;"></span>
							</button>
						<?php else : ?>
							<p><?php echo $is_disabled ? esc_html__('Minimum payout amount $100 is not reached.', 'emagicone-affiliate-for-woocommerce') : esc_html__('You can request your payout now. Please contact us via email contact@emagicone.com to speed-up the payment.', 'emagicone-affiliate-for-woocommerce'); ?></p>
							<button id="requestPayoutBtn" <?php echo $is_disabled ? 'disabled' : ''; ?> class="<?php echo $is_disabled ? 'disabledButton' : ''; ?>"><?php esc_html_e('Request Payout', 'emagicone-affiliate-for-woocommerce'); ?>
								<span class="loader" style="display: none;"></span>
							</button>

						<?php endif; ?>
					</div>

					<div style="flex: 1;">
						<!--<label for="paypalEmail"><?php esc_html_e('PayPal Email:', 'emagicone-affiliate-for-woocommerce'); ?></label>-->
						<?php
						$paypal_email = get_user_meta($user_id, 'paypal_email', true);
						?>
						<input type="email" id="paypalEmail" name="paypalEmail" value="<?php echo esc_attr($paypal_email); ?>" placeholder="<?php esc_html_e('Enter your PayPal email', 'emagicone-affiliate-for-woocommerce'); ?>" style="width: 80%;" required>
						<button type="button" id="savePaypalEmail" style="margin-top: 15px;"><?php esc_html_e('Save', 'emagicone-affiliate-for-woocommerce'); ?></button>
					</div>
				</div>
			</div>
		</div>



		<div id="Products" class="tabcontent">
			<h4><?php esc_html_e('Products', 'emagicone-affiliate-for-woocommerce'); ?></h4>
			<table class="affiliate-products-table">
				<thead>
					<tr>
						<th></th>
						<th><?php esc_html_e('Product', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Price', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Link', 'emagicone-affiliate-for-woocommerce'); ?></th>
					</tr>

				</thead>
				<tbody>
					<?php
					$selected_products = get_option('emagicone_affiliate_selected_products', array()) ?: array();

					if (!empty($selected_products)) {
						$args = array(
							'post_type'      => 'product',
							'posts_per_page' => -1,
							'post__in'       => $selected_products,
						);
					} else {
						// If no products are selected, deliberately set an impossible condition
						$args = array(
							'post_type'      => 'product',
							'posts_per_page' => -1,
							'post__in'       => array(0),  // There is no product with ID 0, hence no products will be returned
						);
					}


					$loop = new WP_Query($args);

					if ($loop->have_posts()) {
						while ($loop->have_posts()) :
							$loop->the_post();
							global $product;
							echo '<tr class="affiliate-product">';
							echo '<td class="affiliate-product-image">' . get_the_post_thumbnail($loop->post->ID, 'shop_catalog') /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, get_the_post_thumbnail returns properly escaped HTML */ . '</td>';
							echo '<td class="affiliate-product-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></td>';
							echo '<td class="affiliate-product-price">' . $product->get_price_html() /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, get_price_html returns properly escaped HTML */ . '</td>';
							echo '<td class="affiliate-product-link"><button data-producturl="' . esc_url(get_permalink()) . '" onclick="openModal(' . esc_attr(get_the_ID()) . ', this)">' . esc_html__('Get Link', 'emagicone-affiliate-for-woocommerce') . '</button></td>';
							echo '</tr>';


						endwhile;
					} else {
						echo '<tr><td colspan="4">' . esc_html__('No products found', 'emagicone-affiliate-for-woocommerce') . '</td></tr>';
					}



					wp_reset_postdata();
					?>
				</tbody>
			</table>
			<!-- Popup Modal for Getting Affiliate Link -->
			<div id="affiliateLinkModal" class="modal">
				<div class="modal-content">
					<span class="close">&times;</span>
					<h4><?php echo esc_html__('Generate Affiliate Link', 'emagicone-affiliate-for-woocommerce'); ?></h4>
					<label for="trafficSource"><?php echo esc_html__('Traffic Source:', 'emagicone-affiliate-for-woocommerce'); ?> <small><?php echo esc_html__('A-z,0-9 and "_", ".", "-" are allowed', 'emagicone-affiliate-for-woocommerce'); ?></small></label>
					<form id="affiliateLinkForm" style="display:flex;gap:20px;">
						<input type="text" id="trafficSource" name="trafficSource">
						<input type="hidden" id="productId" name="productId">
						<input type="hidden" id="productUrl" name="productUrl">
						<button type="button" onclick="generateAffiliateLink()">
							<?php echo esc_html__('Generate Link', 'emagicone-affiliate-for-woocommerce'); ?>
							<span class="loader" style="display: none;"></span>
						</button>
					</form>
					<div id="generatedLink"></div>
				</div>
			</div>


		</div>



		<div id="Statistics" class="tabcontent">
			<h4><?php esc_html_e('Performance by Link', 'emagicone-affiliate-for-woocommerce'); ?></h4>
			<table class="data-grid">
				<thead>
					<tr>
						<th><?php echo esc_html__('Campaign Name', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php echo esc_html__('Traffic Source', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php echo esc_html__('Hits', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php echo esc_html__('Orders', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php echo esc_html__('Earned Commissions', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th><?php echo esc_html__('Conversion (%)', 'emagicone-affiliate-for-woocommerce'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php

					global $wpdb;
					$user_id      = get_current_user_id();
					$affiliate_id = get_user_meta($user_id, 'affiliate_account_id', true);

					$results = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						"
                                        SELECT 
                                            ac.name AS campaign_name,
                                            al.traffic_source,
                                            IFNULL(hit_counts.hits, 0) AS hits,
                                            IFNULL(order_counts.orders, 0) AS orders,
                                            IFNULL(order_stats.earned_commissions, 0) AS earned_commissions,
                                            ROUND(IFNULL(order_counts.orders / NULLIF(hit_counts.hits, 0) * 100, 0), 2) AS conversion
                                        FROM 
                                            {$wpdb->prefix}affiliate_links al
                                        LEFT JOIN (
                                            SELECT 
                                                av.affiliate_link_id, 
                                                av.campaign_id,
                                                COUNT(av.id) AS hits
                                            FROM 
                                                {$wpdb->prefix}affiliate_visits av
                                            WHERE 
                                                av.affiliate_id = %d
                                            GROUP BY 
                                                av.affiliate_link_id, av.campaign_id
                                        ) AS hit_counts ON al.id = hit_counts.affiliate_link_id
                                        LEFT JOIN (
                                            SELECT 
                                                aos.affiliate_link_id, 
                                                COUNT(DISTINCT aos.order_id) AS orders
                                            FROM 
                                                {$wpdb->prefix}affiliate_order_stats aos
                                            WHERE 
                                                aos.affiliate_id = %d
                                            GROUP BY 
                                                aos.affiliate_link_id
                                        ) AS order_counts ON al.id = order_counts.affiliate_link_id
                                        LEFT JOIN (
                                            SELECT 
                                                aos.affiliate_link_id, 
                                                SUM(aos.commission_amount) AS earned_commissions
                                            FROM 
                                                {$wpdb->prefix}affiliate_order_stats aos
                                            WHERE 
                                                aos.affiliate_id = %d
                                            GROUP BY 
                                                aos.affiliate_link_id
                                        ) AS order_stats ON al.id = order_stats.affiliate_link_id
                                        LEFT JOIN 
                                            {$wpdb->prefix}affiliate_campaigns ac ON hit_counts.campaign_id = ac.campaign_id
                                        WHERE 
                                            al.affiliate_id = %d AND hit_counts.campaign_id > 0
                                        GROUP BY 
                                            al.traffic_source, ac.name
                                        ",
						$affiliate_id,
						$affiliate_id,
						$affiliate_id,
						$affiliate_id
					));


					// var_dump($results);
					if (count($results)) {
						foreach ($results as $row) :
					?>
							<tr>
								<td><?php echo esc_html($row->campaign_name); ?></td>
								<td><?php echo esc_html($row->traffic_source); ?></td>
								<td><?php echo intval($row->hits); ?></td>
								<td><?php echo intval($row->orders); ?></td>
								<td><?php echo wc_price($row->earned_commissions);/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns properly escaped HTML */ ?></td>
								<td><?php echo esc_html($row->conversion); ?></td>
							</tr>
						<?php endforeach;
					} else { ?>
						<tr>
							<td colspan="6"><?php echo esc_html__('No data about link statistics', 'emagicone-affiliate-for-woocommerce'); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<h4><?php esc_html_e('Performance by Coupon', 'emagicone-affiliate-for-woocommerce'); ?></h4>
			<table class="data-grid">
				<thead>
					<tr>
						<th width="70%"><?php echo esc_html__('Coupon Code', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th width="10%"><?php echo esc_html__('Orders', 'emagicone-affiliate-for-woocommerce'); ?></th>
						<th width="20%"><?php echo esc_html__('Earned Commissions', 'emagicone-affiliate-for-woocommerce'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php

					$coupon_results = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						"
                                        SELECT 
                                            acu.coupon_code,
                                            COUNT(DISTINCT aos.order_id) AS orders,
                                            SUM(aos.commission_amount) AS earned_commissions
                                        FROM 
                                            {$wpdb->prefix}affiliate_coupon_usage acu
                                        JOIN 
                                            {$wpdb->prefix}affiliate_order_stats aos ON acu.order_id = aos.order_id
                                        WHERE 
                                            acu.user_id = %d
                                        GROUP BY 
                                            acu.coupon_code
                                    ",
						$affiliate_id
					));
					if (count($coupon_results)) {
						foreach ($coupon_results as $coupon_row) :
					?>
							<tr>
								<td><?php echo esc_html($coupon_row->coupon_code); ?></td>
								<td><?php echo intval($coupon_row->orders); ?></td>
								<td><?php echo  wc_price($coupon_row->earned_commissions);/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns properly escaped HTML */ ?></td>
							</tr>
						<?php endforeach;
					} else { ?>
						<tr>
							<td colspan="3">
								<?php echo esc_html__('No data about coupon usage', 'emagicone-affiliate-for-woocommerce'); ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>



		<div id="Transactions" class="tabcontent">
			<h4><?php esc_html_e('Payouts', 'emagicone-affiliate-for-woocommerce'); ?></h4>
			<?php
			$user_id      = get_current_user_id();
			$affiliate_id = get_user_meta($user_id, 'affiliate_account_id', true);
			$transactions = Emagicone_Affiliate_Plugin::get_user_transactions($affiliate_id); // Method to retrieve transactions

			if (!empty($transactions)) :
			?>
				<table class="transactions-table">
					<thead>
						<tr>
							<th><?php echo esc_html__('Amount', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php echo esc_html__('Type', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php echo esc_html__('Date', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php echo esc_html__('Status', 'emagicone-affiliate-for-woocommerce'); ?></th>
							<th><?php echo esc_html__('Transaction ID', 'emagicone-affiliate-for-woocommerce'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($transactions as $transaction) : ?>
							<tr>
								<td><?php echo  wc_price($transaction->amount); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is safe, wc_price returns properly escaped HTML */ ?></td>
								<td><?php echo esc_html($transaction->action_type); ?></td>
								<td><?php echo esc_html(gmdate('Y-m-d H:i:s', strtotime($transaction->action_date))); ?></td>
								<td><?php echo esc_html($transaction->status); ?></td>
								<td><?php echo esc_html($transaction->admin_notes); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php echo esc_html__('No transactions found.', 'emagicone-affiliate-for-woocommerce'); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>