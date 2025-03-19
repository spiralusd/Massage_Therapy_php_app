<?php
/**
 * Admin-Specific Fix for Booking Form Display
 * 
 * This file fixes the issue of the booking form displaying in the WordPress admin area.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Prevent booking template from loading in admin
 */
function massage_booking_admin_fix() {
    // Check if we're in the admin area
    if (is_admin()) {
        // Prevent original booking form from loading in admin area
        add_action('admin_enqueue_scripts', function() {
            // Dequeue any form-related scripts and styles in admin
            wp_dequeue_style('massage-booking-form-style');
            wp_dequeue_script('massage-booking-form-script');
            wp_dequeue_script('massage-booking-api-connector');
        }, 100);
    }
}
add_action('admin_init', 'massage_booking_admin_fix', 5);

/**
 * Fix for potential include/require statements in plugin files
 */
function massage_booking_prevent_template_includes() {
    if (is_admin()) {
        // Define constant to prevent includes in admin
        if (!defined('MASSAGE_BOOKING_IS_ADMIN')) {
            define('MASSAGE_BOOKING_IS_ADMIN', true);
        }
    }
}
add_action('init', 'massage_booking_prevent_template_includes', 1);

/**
 * Safe version that won't interfere with admin pages
 */
function massage_booking_admin_safe_fix() {
    // Only in admin and only on relevant pages
    if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'massage-booking') !== false) {
        // Add specific admin-only styles for our plugin pages
        echo '<style>
            .massage-booking-admin table.widefat {
                margin-top: 15px;
            }
            .massage-booking-admin .form-table th {
                width: 200px;
            }
        </style>';
    }
}
add_action('admin_head', 'massage_booking_admin_safe_fix', 1);

// Check shortcode rendering in admin - safer approach
function massage_booking_modify_shortcode_callbacks() {
    global $shortcode_tags;
    
    if (is_admin() && is_array($shortcode_tags)) {
        // Check if our shortcodes exist and modify them to return empty in admin
        $shortcode_names = array('massage_booking_form', 'massage_business_hours', 'massage_services');
        
        foreach ($shortcode_names as $name) {
            if (isset($shortcode_tags[$name])) {
                add_filter('shortcode_atts_' . $name, function($out, $pairs, $atts) {
                    if (is_admin()) {
                        return array(); // Return empty array in admin
                    }
                    return $out;
                }, 10, 3);
            }
        }
    }
}
add_action('admin_init', 'massage_booking_modify_shortcode_callbacks');

/**
 * Make sure our admin pages display properly
 */
function massage_booking_ensure_admin_pages() {
    if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'massage-booking') !== false) {
        // Add a class to our admin pages
        add_filter('admin_body_class', function($classes) {
            return $classes . ' massage-booking-admin';
        });
        
        // Make sure our scripts and styles load properly
        add_action('admin_enqueue_scripts', function() {
            wp_enqueue_style('massage-booking-admin-style', MASSAGE_BOOKING_PLUGIN_URL . 'admin/css/admin-style.css', array(), MASSAGE_BOOKING_VERSION);
            wp_enqueue_script('massage-booking-admin-script', MASSAGE_BOOKING_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), MASSAGE_BOOKING_VERSION, true);
        });
    }
}
add_action('admin_init', 'massage_booking_ensure_admin_pages');