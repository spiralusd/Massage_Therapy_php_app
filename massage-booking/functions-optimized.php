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
    // Send JSON response with proper headers
    header('Content-Type: application/json');
    
    // Get parameters
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
    $request_nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    
    // Validate required parameters
    if (empty($date) || empty($time)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    // Verify nonce if provided
    if (!empty($request_nonce) && !massage_booking_verify_nonce('check_slot_availability', $request_nonce)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Security check failed'
        ]);
        exit;
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
        echo json_encode([
            'success' => false, 
            'message' => 'Rate limit exceeded. Please try again later.'
        ]);
        exit;
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
    
    // Success response
    echo json_encode([
        'success' => true,
        'available' => $is_available,
        'message' => $is_available ? 'Time slot is available' : 'This time slot is no longer available',
        'startTime' => $time,
        'endTime' => $end_datetime->format('H:i'),
        'duration' => $duration,
        'breakTime' => $break_time
    ]);
    exit;
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
        
        // Make sure jQuery is loaded first
        wp_enqueue_script('jquery');
        
        // Register CSS
        wp_register_style(
            'massage-booking-form-style',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/css/booking-form.css',
            array(),
            MASSAGE_BOOKING_VERSION
        );
        
        // Check if minified versions exist
        $js_path = MASSAGE_BOOKING_PLUGIN_DIR . 'public/js/';
        $use_minified = file_exists($js_path . 'booking-form-optimized.min.js') && file_exists($js_path . 'api-connector-optimized.min.js');
        
        // Register JS
        wp_register_script(
            'massage-booking-form-script',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . ($use_minified ? 'booking-form-optimized.min.js' : 'booking-form-optimized.js'),
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Register API connector
        wp_register_script(
            'massage-booking-api-connector',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . ($use_minified ? 'api-connector-optimized.min.js' : 'api-connector-optimized.js'),
            array('jquery', 'massage-booking-form-script'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Register jQuery form handler 
        wp_register_script(
            'massage-booking-jquery-form',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/jquery-form-handler.js',
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Enqueue everything
        wp_enqueue_style('massage-booking-form-style');
        wp_enqueue_script('massage-booking-form-script');
        wp_enqueue_script('massage-booking-api-connector');
        wp_enqueue_script('massage-booking-jquery-form');
        
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
    if (class_exists('Massage_Booking_Encryption')) {
        $encryption = new Massage_Booking_Encryption();
        if (!$encryption->test_encryption()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('This plugin requires proper encryption support. Please check your server configuration or contact your hosting provider.');
        }
    }
}

/**
 * Custom REST API response handler to ensure proper JSON formatting
 */
function massage_booking_json_handler($data, $server, $request) {
    // Make sure we're dealing with a JSON response
    if (!defined('REST_REQUEST') || !REST_REQUEST) {
        return $data;
    }
    
    // Set appropriate headers
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // Return the data as is, WordPress REST API will handle JSON encoding
    return $data;
}
add_filter('rest_pre_serve_request', 'massage_booking_json_handler', 10, 3);

/**
 * Handle appointment submissions with better error handling
 */
function massage_booking_handle_appointment() {
    // Verify nonce
    if (!check_ajax_referer('massage_booking_submit', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        exit;
    }
    
    // Get and sanitize data
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid JSON data: ' . json_last_error_msg()], 400);
        exit;
    }
    
    // Validate required fields
    $required_fields = ['fullName', 'email', 'phone', 'appointmentDate', 'startTime', 'duration'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            wp_send_json_error(['message' => 'Missing required field: ' . $field], 400);
            exit;
        }
    }
    
    // Sanitize and validate email
    $email = sanitize_email($data['email']);
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Invalid email address'], 400);
        exit;
    }
    
    // Create appointment in database
    try {
        $db = new Massage_Booking_Database();
        
        // Prepare appointment data
        $appointment_data = [
            'full_name' => sanitize_text_field($data['fullName']),
            'email' => $email,
            'phone' => sanitize_text_field($data['phone']),
            'appointment_date' => sanitize_text_field($data['appointmentDate']),
            'start_time' => sanitize_text_field($data['startTime']),
            'end_time' => !empty($data['endTime']) ? sanitize_text_field($data['endTime']) : '',
            'duration' => intval($data['duration']),
            'focus_areas' => !empty($data['focusAreas']) ? $data['focusAreas'] : [],
            'pressure_preference' => !empty($data['pressurePreference']) ? sanitize_text_field($data['pressurePreference']) : '',
            'special_requests' => !empty($data['specialRequests']) ? sanitize_textarea_field($data['specialRequests']) : '',
            'status' => 'confirmed',
        ];
        
        // Check if the slot is still available
        if (!$db->check_slot_availability($appointment_data['appointment_date'], $appointment_data['start_time'], $appointment_data['duration'])) {
            wp_send_json_error(['message' => 'This time slot is no longer available'], 409);
            exit;
        }
        
        // Add to Office 365 Calendar if configured
        if (class_exists('Massage_Booking_Calendar') && massage_booking_is_calendar_configured()) {
            $calendar = new Massage_Booking_Calendar();
            $event_result = $calendar->create_event($appointment_data);
            
            if (!is_wp_error($event_result) && isset($event_result['id'])) {
                $appointment_data['calendar_event_id'] = $event_result['id'];
            }
        }
        
        // Save appointment
        $appointment_id = $db->create_appointment($appointment_data);
        
        if (!$appointment_id) {
            wp_send_json_error(['message' => 'Failed to save appointment'], 500);
            exit;
        }
        
        // Send confirmation emails
        if (class_exists('Massage_Booking_Emails')) {
            $emails = new Massage_Booking_Emails();
            $emails->send_client_confirmation($appointment_data);
            $emails->send_therapist_notification($appointment_data);
        }
        
        // Fire action hook for additional processing
        do_action('massage_booking_after_appointment_created', $appointment_id, $appointment_data);
        
        // Return success response
        wp_send_json_success([
            'appointment_id' => $appointment_id,
            'message' => 'Appointment booked successfully.'
        ]);
        
    } catch (Exception $e) {
        // Log the error
        error_log('Appointment creation error: ' . $e->getMessage());
        
        // Return error response
        wp_send_json_error([
            'message' => 'Error processing appointment: ' . $e->getMessage()
        ], 500);
    }
}
add_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_handle_appointment');
add_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_handle_appointment');

/**
 * Check if calendar integration is configured
 * 
 * @return bool True if configured, false otherwise
 */
function massage_booking_is_calendar_configured() {
    $settings = new Massage_Booking_Settings();
    return (
        $settings->get_setting('ms_client_id') && 
        $settings->get_setting('ms_client_secret') && 
        $settings->get_setting('ms_tenant_id')
    );
}

/**
 * Fix for handling REST API requests with invalid content
 * This handles potential issues with the JSON parsing error
 */
function massage_booking_rest_pre_dispatch($result, $server, $request) {
    // Skip for internal requests
    if (defined('DOING_INTERNAL') && DOING_INTERNAL) {
        return $result;
    }
    
    // Only handle our plugin endpoints
    $route = $request->get_route();
    if (strpos($route, '/massage-booking/') !== 0) {
        return $result;
    }
    
    // Add additional error handling for POST requests
    if ($request->get_method() === 'POST') {
        // Force proper JSON content-type header for our endpoints
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        // Add custom validation for our endpoints if needed
        // This is a good place to add additional checks or logging
        
        // Log endpoint access for HIPAA compliance
        if (class_exists('Massage_Booking_Audit_Log')) {
            $audit_log = new Massage_Booking_Audit_Log();
            $audit_log->log_action('rest_api_access', get_current_user_id(), null, 'api', [
                'route' => $route,
                'method' => $request->get_method(),
                'ip' => massage_booking_get_client_ip()
            ]);
        }
    }
    
    return $result;
}
add_filter('rest_pre_dispatch', 'massage_booking_rest_pre_dispatch', 10, 3);

/**
 * Register custom REST API response formats
 * Ensures proper output formatting for all endpoints
 */
function massage_booking_rest_ensure_response($response, $handler, $request) {
    // Skip for non-massage-booking endpoints
    $route = $request->get_route();
    if (strpos($route, '/massage-booking/') !== 0) {
        return $response;
    }
    
    // Make sure the response is properly encoded
    if (is_wp_error($response)) {
        // Log errors for HIPAA compliance
        error_log('REST API Error: ' . $response->get_error_message());
        
        // Return formatted error response
        return rest_ensure_response([
            'success' => false,
            'message' => $response->get_error_message()
        ]);
    }
    
    // For successful responses, ensure we have a consistent format
    $data = $response->get_data();
    
    // If data isn't already wrapped with success indicator, wrap it
    if (!isset($data['success'])) {
        $response->set_data([
            'success' => true,
            'data' => $data
        ]);
    }
    
    return $response;
}
add_filter('rest_request_after_callbacks', 'massage_booking_rest_ensure_response', 10, 3);

function massage_booking_simple_appointment_handler() {
    // Basic setup and debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't output to browser, but still log errors
    
    // Force JSON response
    header('Content-Type: application/json');
    
    // Log incoming request for debugging
    error_log('Massage Booking AJAX request received: ' . print_r($_POST, true));
    
    // Verify security nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
        wp_send_json_error(array('message' => 'Security verification failed'));
        exit;
    }
    
    // Get required fields with validation
    $full_name = isset($_POST['fullName']) ? sanitize_text_field($_POST['fullName']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $appointment_date = isset($_POST['appointmentDate']) ? sanitize_text_field($_POST['appointmentDate']) : '';
    $start_time = isset($_POST['startTime']) ? sanitize_text_field($_POST['startTime']) : '';
    $end_time = isset($_POST['endTime']) ? sanitize_text_field($_POST['endTime']) : '';
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
    
    // Handle focus areas - be extra careful with JSON processing
    $focus_areas = array();
    if (isset($_POST['focusAreas']) && !empty($_POST['focusAreas'])) {
        try {
            // Handle potential double-encoding issues
            $focus_areas_raw = stripslashes($_POST['focusAreas']);
            $decoded = json_decode($focus_areas_raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $focus_areas = array_map('sanitize_text_field', $decoded);
            } else {
                // If not valid JSON, treat as comma-separated string
                $focus_areas = explode(',', sanitize_text_field($focus_areas_raw));
            }
        } catch (Exception $e) {
            error_log('Error parsing focus areas: ' . $e->getMessage());
            // Use empty array as fallback
            $focus_areas = array();
        }
    }
    
    $pressure_preference = isset($_POST['pressurePreference']) ? sanitize_text_field($_POST['pressurePreference']) : '';
    $special_requests = isset($_POST['specialRequests']) ? sanitize_textarea_field($_POST['specialRequests']) : '';
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($phone) || empty($appointment_date) || empty($start_time) || empty($duration)) {
        wp_send_json_error(array('message' => 'Please fill in all required fields'));
        exit;
    }
    
    // Validate email
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address'));
        exit;
    }
    
    try {
        // Load dependencies with error handling
        if (!class_exists('Massage_Booking_Database')) {
            require_once(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database-optimized.php');
        }
        
        $db = new Massage_Booking_Database();
        
        // Prepare appointment data
        $appointment_data = array(
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'appointment_date' => $appointment_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'duration' => $duration,
            'focus_areas' => $focus_areas,
            'pressure_preference' => $pressure_preference,
            'special_requests' => $special_requests,
            'status' => 'confirmed',
        );
        
        // Check slot availability
        if (!$db->check_slot_availability($appointment_data['appointment_date'], $appointment_data['start_time'], $appointment_data['duration'])) {
            wp_send_json_error(array('message' => 'This time slot is no longer available. Please choose another time.'));
            exit;
        }
        
        // Save appointment to database
        $appointment_id = $db->create_appointment($appointment_data);
        
        if (!$appointment_id) {
            wp_send_json_error(array('message' => 'Failed to save appointment to database'));
            exit;
        }
        
        // Send confirmation emails if class exists
        if (class_exists('Massage_Booking_Emails')) {
            $emails = new Massage_Booking_Emails();
            $client_email_result = $emails->send_client_confirmation($appointment_data);
            $admin_email_result = $emails->send_therapist_notification($appointment_data);
            
            // Log email results but don't fail if emails don't send
            if (!$client_email_result || !$admin_email_result) {
                error_log('Warning: Failed to send some confirmation emails for appointment #' . $appointment_id);
            }
        }
        
        // Success! Return appointment details
        wp_send_json_success(array(
            'appointment_id' => $appointment_id,
            'message' => 'Your appointment has been booked successfully!'
        ));
        
    } catch (Exception $e) {
        // Log the detailed error
        error_log('Appointment creation error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
        
        // Return user-friendly error
        wp_send_json_error(array(
            'message' => 'An error occurred while processing your appointment. Please try again or contact us directly.'
        ));
    }
    
    // Always terminate properly
    wp_die();
}

// Register AJAX actions for both logged-in and non-logged-in users
add_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
add_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
?>