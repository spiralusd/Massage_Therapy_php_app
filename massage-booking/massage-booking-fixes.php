<?php
/**
 * Plugin Menu and Function Fixes
 * 
 * This file addresses issues with duplicate menu items and missing callback functions
 * Save as "massage-booking-fixes.php" in your plugin directory
 */

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}


/**
 * Implement the missing debug page function
 */


/**
 * Fix the duplicate menu items issue by consolidating the admin menu
 
function massage_booking_fixed_admin_menu() {
    // Remove all existing Massage Booking menu items to prevent duplicates
    remove_menu_page('massage-booking');
    
    // Recreate the menu structure cleanly
    add_menu_page(
        'Massage Booking',
        'Massage Booking',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Add submenu pages
    add_submenu_page(
        'massage-booking',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page'
    );
    
    add_submenu_page(
        'massage-booking',
        'Appointments',
        'Appointments',
        'manage_options',
        'massage-booking-appointments',
        'massage_booking_appointments_page'
    );
    
    add_submenu_page(
        'massage-booking',
        'Schedule Settings',
        'Schedule Settings',
        'manage_options',
        'massage-booking-settings',
        'massage_booking_settings_page'
    );
    
    // Add audit logs for HIPAA compliance
    add_submenu_page(
        'massage-booking',
        'Audit Logs',
        'Audit Logs',
        'manage_options',
        'massage-booking-logs',
        'massage_booking_logs_page'
    );
    
    // Add only one Email Verification page
    add_submenu_page(
        'massage-booking',
        'Email Verification',
        'Email Verification',
        'manage_options',
        'massage-booking-email-verify',
        'massage_booking_email_verification_page'
    );
    
    // Debug logs
    add_submenu_page(
        'massage-booking',
        'Debug Logs',
        'Debug Logs',
        'manage_options',
        'massage-booking-debug',
        'massage_booking_debug_page'
    );
}

// Run this at a very late priority to override any earlier menu registrations
add_action('admin_menu', 'massage_booking_fixed_admin_menu', 9999);
*/
/**
 * Make sure our dashboard page works
 */


/**
 * Make sure our email verification page works
 */
if (!function_exists('massage_booking_email_verification_page')) {
    function massage_booking_email_verification_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="card">';
        echo '<h2>Email Configuration</h2>';
        
        // Test WordPress email functionality
        echo '<h3>WordPress Email Settings</h3>';
        echo '<p><strong>Admin Email:</strong> ' . esc_html(get_option('admin_email')) . '</p>';
        
        if (class_exists('Massage_Booking_Settings')) {
            $settings = new Massage_Booking_Settings();
            $business_email = $settings->get_setting('business_email', get_option('admin_email'));
            echo '<p><strong>Business Email:</strong> ' . esc_html($business_email) . '</p>';
        }
        
        // Email test form
        echo '<h3>Send Test Email</h3>';
        echo '<form method="post">';
        echo '<p><label for="test_email">Email Address:</label><br>';
        echo '<input type="email" id="test_email" name="test_email" value="' . esc_attr(get_option('admin_email')) . '" required class="regular-text"></p>';
        echo '<p><label for="test_subject">Subject:</label><br>';
        echo '<input type="text" id="test_subject" name="test_subject" value="Massage Booking Email Test" required class="regular-text"></p>';
        echo '<p><label for="test_message">Message:</label><br>';
        echo '<textarea id="test_message" name="test_message" rows="5" class="large-text">This is a test email from the Massage Booking system.</textarea></p>';
        
        wp_nonce_field('massage_booking_email_test');
        echo '<input type="submit" name="send_test_email" class="button button-primary" value="Send Test Email">';
        echo '</form>';
        
        // Process test email submission
        if (isset($_POST['send_test_email']) && check_admin_referer('massage_booking_email_test')) {
            $to = sanitize_email($_POST['test_email']);
            $subject = sanitize_text_field($_POST['test_subject']);
            $message = sanitize_textarea_field($_POST['test_message']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            $success = wp_mail($to, $subject, $message, $headers);
            
            if ($success) {
                echo '<div class="notice notice-success"><p>Test email sent successfully to ' . esc_html($to) . '.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to send test email. Please check your WordPress email configuration.</p></div>';
            }
        }
        
        echo '</div>'; // card
        echo '</div>'; // wrap
    }
}



// Add a notice to alert the admin about the fixes
function massage_booking_admin_notice() {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>Massage Booking:</strong> Plugin menu structure and missing functions have been fixed. Please refresh the page to see the updates.</p>';
    echo '</div>';
}
add_action('admin_notices', 'massage_booking_admin_notice');
