<?php
/**
 * Plugin Integration Fix
 * 
 * This file provides a clean way to integrate all the fixed components.
 * Add this to the plugin root directory and include it in massage-booking.php
 * after all the required files are loaded.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Initialize the fixed components
 * This function hooks into plugins_loaded to ensure all classes are available
 */
function massage_booking_init_fixed_components() {
    // Check if we're in WordPress admin area
    if (is_admin()) {
        // Register clean menu structure (this will automatically handle duplicates)
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin-menu-fix.php';
        
        // Use the fixed appointments page function
        if (!function_exists('massage_booking_appointments_page')) {
            require_once MASSAGE_BOOKING_PLUGIN_DIR . 'appointments-page-fix.php';
        }
        
        // Clean up any duplicate hooks
        if (function_exists('massage_booking_appointments_admin_page')) {
            remove_action('admin_menu', 'massage_booking_appointments_admin_page');
        }
        
        // Clean up debug menu hooks
        if (function_exists('massage_booking_add_debug_page')) {
            remove_action('admin_menu', 'massage_booking_add_debug_page');
        }
        
        if (function_exists('massage_booking_add_debug_menu')) {
            remove_action('admin_menu', 'massage_booking_add_debug_menu');
        }
        
        // Clean up email verification menu hooks
        if (function_exists('massage_booking_add_email_verification_menu')) {
            remove_action('admin_menu', 'massage_booking_add_email_verification_menu');
        }
        
        // Clean up MS Auth hooks
        if (function_exists('add_reset_ms_auth_page')) {
            remove_action('admin_menu', 'add_reset_ms_auth_page');
        }
    }
}

// Initialize fixes after plugins are loaded to ensure all classes are available
add_action('plugins_loaded', 'massage_booking_init_fixed_components', 999);

/**
 * Unregister duplicate actions for AJAX handlers
 */
function massage_booking_clean_ajax_handlers() {
    // Identify and clean up duplicate AJAX handlers for appointment forms
    $handlers_to_check = [
        'massage_booking_handle_appointment',
        'massage_booking_simple_appointment_handler',
        'massage_booking_improved_appointment_handler',
        'massage_booking_unified_appointment_handler'
    ];
    
    $action_name = 'massage_booking_create_appointment';
    
    foreach ($handlers_to_check as $handler) {
        if (function_exists($handler)) {
            // For logged in users
            if (has_action('wp_ajax_' . $action_name, $handler)) {
                remove_action('wp_ajax_' . $action_name, $handler);
            }
            
            // For non-logged in users
            if (has_action('wp_ajax_nopriv_' . $action_name, $handler)) {
                remove_action('wp_ajax_nopriv_' . $action_name, $handler);
            }
        }
    }
    
    // Re-add the unified handler only if it exists
    if (function_exists('massage_booking_unified_appointment_handler')) {
        add_action('wp_ajax_' . $action_name, 'massage_booking_unified_appointment_handler');
        add_action('wp_ajax_nopriv_' . $action_name, 'massage_booking_unified_appointment_handler');
    }
}

// Clean up AJAX handlers on init with high priority
add_action('init', 'massage_booking_clean_ajax_handlers', 999);

/**
 * Helper function to log initialization status
 */
function massage_booking_log_fixed_init() {
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('massage_booking_debug_log_detail')) {
        massage_booking_debug_log_detail(
            'Fixed plugin components initialized', 
            [
                'version' => MASSAGE_BOOKING_VERSION,
                'timestamp' => current_time('mysql')
            ], 
            'info', 
            'ADMIN'
        );
    }
}
add_action('admin_init', 'massage_booking_log_fixed_init', 999);