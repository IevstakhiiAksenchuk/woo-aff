<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Emagicone_Affiliate_Signup
{

    public function __construct()
    {
        add_shortcode('emagicone_affiliate_signup', array($this, 'render_signup_form'));
        add_action('template_redirect', array($this, 'custom_redirect_logged_in_users_from_notes'));
        add_action('woocommerce_login_form_end', array($this, 'add_custom_link_after_lost_password'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
    }

    public function add_custom_link_after_lost_password()
    {
        echo '<p class="woocommerce-register affiliate-register"><a href="/my-account/affiliate-signup/">' . esc_html__('Register as Affiliate', 'emagicone-affiliate-for-woocommerce') . '</a></p>';
    }

    public function custom_redirect_logged_in_users_from_notes()
    {
        if (is_page('affiliate-signup') && is_user_logged_in()) {
            wp_redirect(get_permalink(get_option('woocommerce_myaccount_page_id')));
            exit;
        }
    }

    public function enqueue_scripts_and_styles()
    {
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'emagicone_affiliate_signup')) {
            wp_enqueue_script('password-strength-meter');
            wp_enqueue_script('zxcvbn');
            wp_enqueue_style('emagicone_affiliate_signup_css', EMAGICONE_AFFILIATE_BASE_URL . 'assets/css/affiliate-signup.css', array(), '0.1.0');
            wp_enqueue_script('emagicone_affiliate_signup_js', EMAGICONE_AFFILIATE_BASE_URL . 'assets/js/affiliate-signup.js', array('jquery'), '0.1.0', true);

            $use_recaptcha = get_option('emagicone_affiliate_use_recaptcha', '');
            $recaptcha_site_key = get_option('emagicone_affiliate_recaptcha_api_key', '');

            if ('1' === $use_recaptcha && !empty($recaptcha_site_key)) {
                wp_localize_script('emagicone_affiliate_signup_js', 'emagiconeAffiliate', array(
                    'recaptchaSiteKey' => $recaptcha_site_key,
                    'useRecaptcha' => $use_recaptcha
                ));
                // Enqueue reCAPTCHA v3 script separately
                wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $recaptcha_site_key, array(), '0.1.0', true);
            }
        }
    }

    public function render_signup_form()
    {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('You are already registered and logged in.', 'emagicone-affiliate-for-woocommerce') . '</p>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emagicone_affiliate_signup_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['emagicone_affiliate_signup_nonce']), 'affiliate_signup')) {
            $this->handle_form_submission();
            return;
        }


        $my_account_page_url = wc_get_page_permalink('myaccount');

        if (!$my_account_page_url) {
            // Fallback URL if the My Account page isn't set in WooCommerce settings
            $my_account_page_url = site_url('/my-account/'); // Default or custom URL
        }

        $use_recaptcha = get_option('emagicone_affiliate_use_recaptcha', '');
        $recaptcha_site_key = get_option('emagicone_affiliate_recaptcha_api_key', '');

        ob_start();
?>
        <div class="row">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <?php
                // Sanitize the REQUEST_URI
                $current_url = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
                ?>

                <form id="affiliate_signup_form" method="post" action="<?php echo esc_url($current_url); ?>">
                    <p>
                        <label for="emagicone_firstname"><?php esc_html_e('First Name:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="text" name="emagicone_firstname" id="emagicone_firstname" required>
                    </p>
                    <p>
                        <label for="emagicone_lastname"><?php esc_html_e('Last Name:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="text" name="emagicone_lastname" id="emagicone_lastname" required>
                    </p>
                    <p>
                        <label for="emagicone_email"><?php esc_html_e('Email:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="email" name="emagicone_email" id="emagicone_email" required>
                    </p>
                    <p>
                        <label for="emagicone_password"><?php esc_html_e('Password:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="password" name="emagicone_password" id="emagicone_password" required>
                        <span class='pass-hint'>Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! ? $ % ^ & )</span>
                        <span id="password_strength"></span>
                    </p>
                    <p>
                        <label for="emagicone_confirm_password"><?php esc_html_e('Confirm Password:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="password" name="emagicone_confirm_password" id="emagicone_confirm_password" required>
                    </p>
                    <p>
                        <label for="emagicone_website"><?php esc_html_e('Website:', 'emagicone-affiliate-for-woocommerce'); ?></label>
                        <input type="url" name="emagicone_website" id="emagicone_website">
                    </p>
                    <?php
                    wp_nonce_field('affiliate_signup', 'emagicone_affiliate_signup_nonce');
                    ?>
                    <input type="submit" id="form_submit_button" name="submit_button" disabled value="<?php esc_html_e('Sign Up', 'emagicone-affiliate-for-woocommerce'); ?>">

                    <p class="woocommerce-login-as-affiliate"><?php esc_html_e('Have an account?', 'emagicone-affiliate-for-woocommerce'); ?> <a href="<?php echo esc_url($my_account_page_url); ?>"><?php esc_html_e('Login as Affiliate', 'emagicone-affiliate-for-woocommerce'); ?></a></p>

                </form>
            </div>
            <div class="col-sm-3"></div>
        </div>
<?php
        return ob_get_clean();
    }

    public function get_next_external_account_id()
    {
        global $wpdb;
        $result = $wpdb->get_var("SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM $wpdb->usermeta WHERE meta_key = 'affiliate_account_id'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $result ? (int) $result + 1 : 1; // If no existing IDs, start at 1, otherwise increment
    }

    private function validate_recaptcha($recaptcha_response)
    {
        $secret_key = get_option('emagicone_affiliate_recaptcha_secret_key', '');
        if (empty($secret_key)) {
            return false; // Fail validation if no secret key is set
        }
        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'body' => array(
                    'secret' => $secret_key,
                    'response' => $recaptcha_response,
                ),
            )
        );

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        // Check the score and success flag. You might need to adjust the score threshold according to your needs.
        return !empty($result['success']) && $result['score'] >= 0.5;
    }

    private function isPasswordStrong($password)
    {
        $minLength = 12;
        $containsUppercase = preg_match('/[A-Z]/', $password);
        $containsLowercase = preg_match('/[a-z]/', $password);
        $containsDigit = preg_match('/\d/', $password);
        $containsSpecial = preg_match('/[^a-zA-Z\d]/', $password);

        if (strlen($password) < $minLength) {
            return false;
        }

        if (!$containsUppercase || !$containsLowercase || !$containsDigit || !$containsSpecial) {
            return false;
        }

        return true;
    }

    private function handle_form_submission()
    {
        if (!isset($_POST['emagicone_firstname'], $_POST['emagicone_lastname'], $_POST['emagicone_email'], $_POST['emagicone_password'], $_POST['emagicone_confirm_password']) || !wp_verify_nonce(sanitize_text_field($_POST['emagicone_affiliate_signup_nonce']), 'affiliate_signup')) {
            echo '<p class="emagicone-error">' . esc_html__('Invalid form submission.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        $my_account_page_url = wc_get_page_permalink('myaccount');

        if (!$my_account_page_url) {
            // Fallback URL if the My Account page isn't set in WooCommerce settings
            $my_account_page_url = site_url('/my-account/'); // Default or custom URL
        }

        $firstname = sanitize_text_field($_POST['emagicone_firstname']);
        $lastname = sanitize_text_field($_POST['emagicone_lastname']);
        $email = sanitize_email($_POST['emagicone_email']);
        $password = sanitize_text_field($_POST['emagicone_password']);
        $confirm_password = sanitize_text_field($_POST['emagicone_confirm_password']);
        $website = isset($_POST['emagicone_website']) ? esc_url_raw($_POST['emagicone_website']) : '';

        // Check the password strength
        $password_strength = $this->isPasswordStrong($password);
        if (!$password_strength) {
            echo '<p class="emagicone-error">' . esc_html__('Password is not strong enough. Please use a stronger password. It should include letters uppercase and lowercase, digits, special characters and should be minimum 12 characters length', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        if ($password !== $confirm_password) {
            echo '<p class="emagicone-error">' . esc_html__('Passwords do not match.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        if (!is_email($email)) {
            echo '<p class="emagicone-error">' . esc_html__('Invalid email address.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        // Check if Recaptcha is enabled and validate response
        $use_recaptcha = get_option('emagicone_affiliate_use_recaptcha', '');
        if ('1' === $use_recaptcha && (!isset($_POST['g-recaptcha-response']) || !$this->validate_recaptcha(sanitize_text_field($_POST['g-recaptcha-response'])))) {
            echo '<p class="emagicone-error">' . esc_html__('reCAPTCHA verification failed, please try again.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        // Check if email already exists
        if (email_exists($email)) {
            echo '<p class="emagicone-error">' . esc_html__('An account with this email address already exists.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        // Check if the username already exists
        if (username_exists($email)) {
            echo '<p class="emagicone-error">' . esc_html__('An account with this username already exists.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            return;
        }

        $userdata = array(
            'user_login' => $email,
            'user_pass' => $password,
            'user_email' => $email,
            'first_name' => $firstname,
            'last_name' => $lastname,
            'role' => 'affiliate',
        );

        $user_id = wp_insert_user($userdata);

        if (!is_wp_error($user_id)) {
            if (!empty($website)) {
                update_user_meta($user_id, 'website', $website);
            }

            // Get the next unique external account ID
            $next_id = $this->get_next_external_account_id();
            update_user_meta($user_id, 'affiliate_account_id', $next_id);

            // Manually trigger the new account email
            wc()->mailer()->emails['WC_Email_Customer_New_Account']->trigger($user_id);

            $admin_instance = new Emagicone_Affiliate_Admin();
            $admin_instance->generate_coupons_for_users($user_id);
            echo '<div class="row">';
            echo '<div class="col-sm-3"></div>';
            echo '<div class="emagicone-success col-sm-6">';
            echo '<p>' . esc_html__('Thank you for registering with us. Please check your email for further instructions on how to complete your account setup.', 'emagicone-affiliate-for-woocommerce') . '</p>';
            if ($my_account_page_url) {
                /* translators: %1$s: Start link tag, %2$s: End link tag */
                echo '<p>' . sprintf(esc_html__('If you have any questions, feel free to %1$slog in%2$s and contact our support team.', 'emagicone-affiliate-for-woocommerce'), '<a href="' . esc_url($my_account_page_url) . '">', '</a>') . '</p>';
            }
            echo '</div>';
            echo '<div class="col-sm-3"></div>';
            echo '</div>';
        } else {
            echo '<p class="emagicone-error">' . esc_html__('Something went wrong. Contact us for assistance.', 'emagicone-affiliate-for-woocommerce') . '</p>';
        }
    }
}
