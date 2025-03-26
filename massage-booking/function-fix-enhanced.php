<?php
/**
 * Enhanced Function Conflict Resolution for Massage Booking Plugin
 * 
 * This file resolves function redeclaration conflicts between multiple files.
 * Save as function-fix-enhanced.php in the plugin's root directory.
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Track loaded functions to prevent duplication
global $massage_booking_loaded_functions;
if (!isset($massage_booking_loaded_functions)) {
    $massage_booking_loaded_functions = array();
}

/**
 * Safe function declaration helper
 * 
 * @param string $function_name Function name to check
 * @param callable $implementation Function implementation
 * @return bool Whether the function was defined
 */
function massage_booking_safe_define_function($function_name, $implementation) {
    global $massage_booking_loaded_functions;
    
    // Skip if already loaded
    if (isset($massage_booking_loaded_functions[$function_name]) || function_exists($function_name)) {
        return false;
    }
    
    // Register the function
    $massage_booking_loaded_functions[$function_name] = true;
    
    // Define the function dynamically
    eval("function {$function_name}() { 
        return call_user_func_array(\$GLOBALS['massage_booking_loaded_functions']['{$function_name}'], func_get_args()); 
    }");
    
    // Store the actual implementation
    $massage_booking_loaded_functions[$function_name] = $implementation;
    
    return true;
}

/**
 * Load admin functions safely
 */
function massage_booking_enhanced_load_admin_functions() {
    // List of admin-related functions that might be redeclared
    $admin_functions = array(
        'massage_booking_dashboard_page',
        'massage_booking_appointments_page',
        'massage_booking_settings_page',
        'massage_booking_logs_page',
        'massage_booking_debug_page',
        'massage_booking_email_verification_page',
        'reset_ms_auth_page',
        'display_appointment_details'
    );
    
    // List of source files that might declare these functions
    $source_files = array(
        'admin/admin-page.php',
        'massage-booking-fixes.php',
        'admin/settings-page.php',
        'debug.php'
    );
    
    // Extract functions from source files
    foreach ($source_files as $file) {
        $full_path = plugin_dir_path(__FILE__) . $file;
        
        if (file_exists($full_path)) {
            // Get file contents
            $content = file_get_contents($full_path);
            
            // Loop through admin functions
            foreach ($admin_functions as $function) {
                // Check if function is in this file
                if (function_exists($function)) {
                    // Already defined, skip
                    continue;
                }
                
                // Check if this file contains the function
                if (preg_match('/function\s+' . preg_quote($function) . '\s*\(/i', $content)) {
                    // Include the file - it will define the function
                    include_once $full_path;
                    break; // Break inner loop once file is included
                }
            }
        }
    }
}

/**
 * Check for callback functions in shortcodes
 */
function massage_booking_register_shortcode_callbacks() {
    // Make sure shortcode functions exist
    if (!function_exists('massage_booking_form_shortcode')) {
        // Define function only if it doesn't exist
        function massage_booking_form_shortcode($atts) {
            ob_start();
            if (function_exists('massage_booking_display_form')) {
                massage_booking_display_form();
            } else {
                echo '<p>Error: Booking form function not available.</p>';
            }
            return ob_get_clean();
        }
    }
    
    if (!function_exists('massage_booking_business_hours_shortcode')) {
        // Placeholder implementation for business hours shortcode
        function massage_booking_business_hours_shortcode($atts) {
            $atts = shortcode_atts(array(
                'title' => 'Business Hours',
                'show_closed_days' => 'true',
            ), $atts, 'massage_business_hours');
            
            // Get settings
            $settings = new Massage_Booking_Settings();
            $schedule = $settings->get_setting('schedule', array());
            $working_days = $settings->get_setting('working_days', array('1', '2', '3', '4', '5'));
            
            ob_start();
            
            // Implementation from shortcodes.php
            include_once plugin_dir_path(__FILE__) . 'public/templates/business-hours-template.php';
            
            return ob_get_clean();
        }
    }
    
    if (!function_exists('massage_booking_services_shortcode')) {
        // Placeholder implementation for services shortcode
        function massage_booking_services_shortcode($atts) {
            $atts = shortcode_atts(array(
                'title' => 'Our Services',
                'show_prices' => 'true',
                'button_text' => 'Book Now',
                'button_link' => '',
            ), $atts, 'massage_services');
            
            // Get settings
            $settings = new Massage_Booking_Settings();
            $durations = $settings->get_setting('durations', array('60', '90', '120'));
            $prices = $settings->get_setting('prices', array(
                '60' => 95,
                '90' => 125,
                '120' => 165
            ));
            
            ob_start();
            
            // Implementation from shortcodes.php
            include_once plugin_dir_path(__FILE__) . 'public/templates/services-template.php';
            
            return ob_get_clean();
        }
    }
}

/**
 * Fix shortcode registration
 */
function massage_booking_fix_shortcodes() {
    // Register shortcode callbacks
    massage_booking_register_shortcode_callbacks();
    
    // Register shortcodes
    add_shortcode('massage_booking_form', 'massage_booking_form_shortcode');
    add_shortcode('massage_business_hours', 'massage_booking_business_hours_shortcode');
    add_shortcode('massage_services', 'massage_booking_services_shortcode');
}

/**
 * Initialize debug logger safely
 */
function massage_booking_init_debug_logger() {
    if (!function_exists('massage_booking_debug_log')) {
        function massage_booking_debug_log($message, $data = null, $level = 'debug', $context = 'general') {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }
            
            $log_level = strtoupper($level);
            $context = strtoupper($context);
            
            $data_string = '';
            if ($data !== null) {
                if (is_array($data) || is_object($data)) {
                    $data_string = print_r($data, true);
                } else {
                    $data_string = (string) $data;
                }
                $log_message = "[{$log_level}] [{$context}] {$message}: {$data_string}";
            } else {
                $log_message = "[{$log_level}] [{$context}] {$message}";
            }
            
            error_log("MASSAGE BOOKING: {$log_message}");
        }
    }
}

/**
 * Initialize the enhanced function fix
 */
function massage_booking_init_enhanced_function_fix() {
    // Initialize debug logger
    massage_booking_init_debug_logger();
    
    // Load admin functions
    add_action('admin_menu', 'massage_booking_enhanced_load_admin_functions', 5);
    
    // Fix shortcodes
    add_action('init', 'massage_booking_fix_shortcodes', 12);
    
    // Log that we've initialized the enhanced fix
    if (function_exists('massage_booking_debug_log')) {
        massage_booking_debug_log('Enhanced function fix initialized', null, 'info', 'SYSTEM');
    }
}

// Run the enhanced function fix
massage_booking_init_enhanced_function_fix();