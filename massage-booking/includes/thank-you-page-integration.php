<?php
/**
 * This is a replacement for the thank-you-page-integration.php file
 * It prevents function redeclaration by adding a function_exists check
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Register thank you page template
function massage_booking_register_thank_you_template($page_templates) {
    $page_templates['page-thank-you.php'] = 'Massage Booking Thank You';
    return $page_templates;
}
add_filter('theme_page_templates', 'massage_booking_register_thank_you_template');

/**
 * Redirect to thank you page after successful booking
 *
 * @param int $appointment_id Newly created appointment ID
 * @param array $appointment_data Appointment details
 */
function massage_booking_redirect_after_booking($appointment_id, $appointment_data) {
    // Get the thank you page
    $thank_you_page_id = get_option('massage_booking_thank_you_page_id');
    
    if (!$thank_you_page_id) {
        // Try to find an existing thank you page
        $existing_page = get_pages([
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-thank-you.php'
        ]);
        
        if (!empty($existing_page)) {
            $thank_you_page_id = $existing_page[0]->ID;
        } else {
            // Create a new thank you page if it doesn't exist
            $thank_you_page_id = wp_insert_post([
                'post_title' => 'Appointment Confirmation',
                'post_content' => 'Thank you for booking your appointment!',
                'post_status' => 'publish',
                'post_type' => 'page',
                'page_template' => 'page-thank-you.php'
            ]);
            
            // Save the page ID in options
            update_option('massage_booking_thank_you_page_id', $thank_you_page_id);
        }
    }
    
    // Redirect to thank you page
    if ($thank_you_page_id) {
        wp_redirect(get_permalink($thank_you_page_id));
        exit;
    }
}
add_action('massage_booking_after_appointment_created', 'massage_booking_redirect_after_booking', 10, 2);

/**
 * Add email verification admin menu
 */
function massage_booking_add_email_verification_menu() {
    add_submenu_page(
        'massage-booking',
        'Email Verification',
        'Email Verification',
        'manage_options',
        'massage-booking-email-verify',
        'massage_booking_email_verification_page'
    );
}
add_action('admin_menu', 'massage_booking_add_email_verification_menu');

/**
 * Email verification admin page
 * This function now checks if it already exists before declaring it
 */
if (!function_exists('massage_booking_email_verification_page')) {
    function massage_booking_email_verification_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Instantiate emails class
        $emails = new Massage_Booking_Emails();
        
        // Perform email configuration test if requested
        $test_results = null;
        if (isset($_POST['test_email_config']) && check_admin_referer('massage_booking_email_test')) {
            $test_results = $emails->validate_email_configuration();
        }
        
        // Get email diagnostics
        $diagnostics = $emails->diagnose_email_issues();
        
        ?>
        <div class="wrap massage-booking-admin">
            <h1>Email Verification</h1>
            
            <div class="email-diagnostics">
                <h2>Email Configuration Diagnostics</h2>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Configuration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PHP Mail Function</td>
                            <td><?php echo $diagnostics['php_mail_enabled'] ? 'Enabled ✓' : 'Disabled ✗'; ?></td>
                        </tr>
                        <tr>
                            <td>WordPress Mail Function</td>
                            <td><?php echo $diagnostics['wp_mail_function'] ? 'Available ✓' : 'Not Available ✗'; ?></td>
                        </tr>
                        <tr>
                            <td>Admin Email</td>
                            <td><?php echo esc_html($diagnostics['wordpress_email_config']['admin_email']); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if ($test_results): ?>
                    <div class="email-test-results">
                        <h3>Email Test Results</h3>
                        <p>
                            <strong>Result:</strong> 
                            <?php if ($test_results['success']): ?>
                                <span style="color: green;">Test Email Sent Successfully ✓</span>
                            <?php else: ?>
                                <span style="color: red;">Test Email Failed ✗</span>
                            <?php endif; ?>
                        </p>
                        <p><strong>Sent To:</strong> <?php echo esc_html($test_results['business_email']); ?></p>
                        <p><strong>Timestamp:</strong> <?php echo esc_html($test_results['timestamp']); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('massage_booking_email_test'); ?>
                    <input type="submit" name="test_email_config" class="button button-primary" value="Test Email Configuration">
                </form>
            </div>
            
            <div class="advanced-diagnostics">
                <h2>Advanced Diagnostics</h2>
                <pre><?php print_r($diagnostics); ?></pre>
            </div>
        </div>
        <?php
    }
}

/**
 * Register admin styles for email verification page
 */
function massage_booking_email_verification_styles() {
    $screen = get_current_screen();
    if ($screen->id === 'massage-booking_page_massage-booking-email-verify') {
        wp_enqueue_style('massage-booking-admin-style', MASSAGE_BOOKING_PLUGIN_URL . 'admin/css/admin-style.css', array(), MASSAGE_BOOKING_VERSION);
    }
}
add_action('admin_enqueue_scripts', 'massage_booking_email_verification_styles');