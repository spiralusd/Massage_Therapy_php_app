<?php
/**
 * Integration File for Massage Booking System
 * 
 * This file should be included from the main plugin file to properly integrate
 * the optimized functions and templates.
 * 
 * @package Massage_Booking
 * @since 1.0.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrate optimized functions and remove conflicting ones
 */
function massage_booking_integrate_optimized_code() {
    // Remove original asset registration
    remove_action('wp_enqueue_scripts', 'massage_booking_register_assets');
    
    // Add optimized asset registration
    add_action('wp_enqueue_scripts', 'massage_booking_register_optimized_assets');
    
    // Remove original template loading and add the optimized version
    remove_filter('template_include', 'massage_booking_load_template');
    add_filter('template_include', 'massage_booking_load_template_admin_fix');
    
    // Override existing AJAX endpoints with optimized versions
    remove_action('wp_ajax_check_slot_availability', 'massage_booking_check_slot_availability');
    remove_action('wp_ajax_nopriv_check_slot_availability', 'massage_booking_check_slot_availability');
    
    add_action('wp_ajax_check_slot_availability', 'massage_booking_check_slot_availability_optimized');
    add_action('wp_ajax_nopriv_check_slot_availability', 'massage_booking_check_slot_availability_optimized');
}
add_action('plugins_loaded', 'massage_booking_integrate_optimized_code', 20);

/**
 * Define the optimized slot availability checker if it doesn't exist
 */
if (!function_exists('massage_booking_check_slot_availability_optimized')) {
    function massage_booking_check_slot_availability_optimized() {
        // If functions-optimized.php defines this function, this won't be used
        // Otherwise, it provides a fallback
        
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
        if (!empty($request_nonce) && !wp_verify_nonce($request_nonce, 'massage_booking_check_slot_availability')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }
        
        // Use the database class to check availability
        $db = new Massage_Booking_Database();
        $is_available = $db->check_slot_availability($date, $time, $duration);
        
        // Get settings for break time
        $settings = new Massage_Booking_Settings();
        $break_time = intval($settings->get_setting('break_time', 15));
        
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
}
/**
 * Fix for script loading conflicts
 */
function massage_booking_fix_script_conflicts() {
    // Remove specific lingering handlers to prevent conflicts
    remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_handle_appointment');
    remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_handle_appointment');
    remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
    remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
    remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_improved_appointment_handler');
    remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_improved_appointment_handler');
}
add_action('init', 'massage_booking_fix_script_conflicts', 5);
?>