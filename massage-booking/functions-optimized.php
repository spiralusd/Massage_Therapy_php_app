<?php
/**
 * Optimized functions for massage-booking.php
 * 
 * Add these functions to your main plugin file or include this file from there.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a nonce for booking form requests
 * Adds rate limiting for security
 */
function massage_booking_generate_nonce() {
    // Verify the request
    check_ajax_referer('wp_rest', '_wpnonce');
    
    // Get the action type (what nonce is being generated for)
    $booking_action = isset($_POST['booking_action']) ? sanitize_text_field($_POST['booking_action']) : '';
    
    // Rate limiting - prevent abuse
    $client_ip = massage_booking_get_client_ip();
    $rate_key = 'massage_booking_rate_' . md5($client_ip);
    $rate_count = get_transient($rate_key);
    
    if ($rate_count === false) {
        // First request in time period
        set_transient($rate_key, 1, 60); // 1 minute time window
    } else if ($rate_count >= 10) {
        // Too many requests
        wp_send_json_error(['message' => 'Rate limit exceeded. Please try again later.'], 429);
        exit;
    } else {
        // Increment the count
        set_transient($rate_key, $rate_count + 1, 60);
    }
    
    // Generate the nonce
    $nonce = wp_create_nonce('massage_booking_' . $booking_action);
    
    // Send response
    wp_send_json_success(['nonce' => $nonce]);
}
add_action('wp_ajax_generate_booking_nonce', 'massage_booking_generate_nonce');
add_action('wp_ajax_nopriv_generate_booking_nonce', 'massage_booking_generate_nonce');

/**
 * Verify a booking request nonce
 * 
 * @param string $action The action being performed
 * @param string $nonce The nonce to verify
 * @return bool True if nonce is valid, false otherwise
 */
function massage_booking_verify_nonce($action, $nonce) {
    return wp_verify_nonce($nonce, 'massage_booking_' . $action);
}

/**
 * Enhanced AJAX endpoint for real-time slot availability
 * Includes nonce verification and rate limiting
 */
function massage_booking_check_slot_availability() {
    // Get parameters
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
    $request_nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    
    // Validate required parameters
    if (empty($date) || empty($time)) {
        wp_send_json_error(['message' => 'Missing required parameters']);
        return;
    }
    
    // Verify nonce
    if (!massage_booking_verify_nonce('check_slot_availability', $request_nonce)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        return;
    }
    
    // Rate limiting
    $client_ip = massage_booking_get_client_ip();
    $rate_key = 'massage_booking_rate_slot_' . md5($client_ip);
    $rate_count = get_transient($rate_key);
    
    if ($rate_count === false) {
        // First request in time period
        set_transient($rate_key, 1, 60); // 1 minute time window
    } else if ($rate_count >= 20) {
        // Too many requests
        wp_send_json_error(['message' => 'Rate limit exceeded. Please try again later.'], 429);
        return;
    } else {
        // Increment the count
        set_transient($rate_key, $rate_count + 1, 60);
    }
    
    // Use the database class to check availability
    $db = new Massage_Booking_Database();
    $is_available = $db->check_slot_availability($date, $time, $duration);
    
    // Get settings for break time
    $settings = new Massage_Booking_Settings();
    $break_time = intval($settings->get_setting('break_time', 15));
    
    // Log the availability check for HIPAA compliance
    $audit_log = new Massage_Booking_Audit_Log();
    $audit_log->log_action('check_availability', get_current_user_id(), null, 'slot', [
        'date' => $date,
        'time' => $time,
        'duration' => $duration,
        'available' => $is_available
    ]);
    
    // Calculate end time
    $datetime = new DateTime($date . ' ' . $time);
    $end_datetime = clone $datetime;
    $end_datetime->modify('+' . intval($duration) . ' minutes');
    
    wp_send_json_success([
        'available' => $is_available,
        'message' => $is_available ? 'Time slot is available' : 'This time slot is no longer available',
        'startTime' => $time,
        'endTime' => $end_datetime->format('H:i'),
        'duration' => $duration,
        'breakTime' => $break_time
    ]);
}
add_action('wp_ajax_check_slot_availability', 'massage_booking_check_slot_availability');
add_action('wp_ajax_nopriv_check_slot_availability', 'massage_booking_check_slot_availability');

/**
 * Add HIPAA compliance headers to all plugin pages
 */
function massage_booking_add_security_headers() {
    // Only apply to plugin pages
    global $post;
    if (!is_object($post)) {
        return;
    }
    
    $booking_page_id = get_option('massage_booking_page_id');
    if (!$booking_page_id || $post->ID != $booking_page_id) {
        return;
    }
    
    // Security headers
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; frame-ancestors \'none\'');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Prevent caching of sensitive data
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
add_action('send_headers', 'massage_booking_add_security_headers');

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function massage_booking_get_client_ip() {
    $ip_address = '';
    
    // Check for proxy forwarded IP
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_addresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip_address = trim($ip_addresses[0]);
    } else if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }
    
    return sanitize_text_field($ip_address);
}

/**
 * Improved template loading function that fixes admin issues
 * 
 * @param string $template The template path
 * @return string The modified template path
 */
function massage_booking_load_template_admin_fix($template) {
    // NEVER apply in admin area under any circumstances
    if (is_admin()) {
        return $template;
    }
    
    global $post;
    
    // Ensure post is valid
    if (!is_object($post)) {
        return $template;
    }
    
    // Check for our template
    $our_template = MASSAGE_BOOKING_PLUGIN_DIR . 'public/templates/page-booking.php';
    $template_meta = get_post_meta($post->ID, '_wp_page_template', true);
    
    if (is_page() && ($template_meta === $our_template || $template_meta === 'page-booking.php')) {
        // Log that we're loading our template (for debugging)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Massage Booking: Loading template from admin fix function: ' . $our_template);
        }
        
        return $our_template;
    }
    
    return $template;
}

/**
 * Register and enqueue optimized form assets
 */
function massage_booking_register_optimized_assets() {
    // Only enqueue on the booking form page
    if (is_page_template('page-booking.php') || 
        (is_page() && get_post_meta(get_the_ID(), '_wp_page_template', true) === MASSAGE_BOOKING_PLUGIN_DIR . 'public/templates/page-booking.php')) {
        
        // Register CSS
        wp_register_style(
            'massage-booking-form-style',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/css/booking-form.css',
            array(),
            MASSAGE_BOOKING_VERSION
        );
        
        // Check if minified versions exist
        $js_path = MASSAGE_BOOKING_PLUGIN_DIR . 'public/js/';
        $use_minified = file_exists($js_path . 'booking-form.min.js') && file_exists($js_path . 'api-connector.min.js');
        
        // Register JS
        wp_register_script(
            'massage-booking-form-script',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . ($use_minified ? 'booking-form.min.js' : 'booking-form.js'),
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Register API connector
        wp_register_script(
            'massage-booking-api-connector',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . ($use_minified ? 'api-connector.min.js' : 'api-connector.js'),
            array('jquery', 'massage-booking-form-script'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Enqueue everything
        wp_enqueue_style('massage-booking-form-style');
        wp_enqueue_script('massage-booking-form-script');
        wp_enqueue_script('massage-booking-api-connector');
        
        // Pass WordPress data to JS
        wp_localize_script('massage-booking-api-connector', 'massageBookingAPI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('massage-booking/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'siteUrl' => get_site_url(),
            'isLoggedIn' => is_user_logged_in(),
            'sessionTimeout' => apply_filters('massage_booking_session_timeout', 20 * 60), // 20 minutes in seconds
            'version' => MASSAGE_BOOKING_VERSION
        ));
    }
}

// Replace the existing enqueue function
remove_action('wp_enqueue_scripts', 'massage_booking_register_assets');
add_action('wp_enqueue_scripts', 'massage_booking_register_optimized_assets');

/**
 * Check system requirements on plugin activation
 */
function massage_booking_check_requirements() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires PHP 7.0 or higher. Please upgrade your PHP version.');
    }
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WordPress 5.0 or higher. Please upgrade your WordPress installation.');
    }
    
    // Check for required PHP extensions
    $required_extensions = array('openssl', 'json', 'mbstring');
    $missing_extensions = array();
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (!empty($missing_extensions)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires the following PHP extensions: ' . implode(', ', $missing_extensions) . '. Please contact your hosting provider to enable them.');
    }
    
    // Test encryption
    $encryption = new Massage_Booking_Encryption();
    if (!$encryption->test_encryption()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires proper encryption support. Please check your server configuration or contact your hosting provider.');
    }
}