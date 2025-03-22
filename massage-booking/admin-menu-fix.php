<?php
/**
 * Admin Menu Structure Fix
 * 
 * This file fixes duplicate menu items and improves the admin menu organization.
 * Add this to your plugin's root directory and include it in massage-booking.php
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Register clean menu structure
 */
function massage_booking_clean_admin_menu() {
    // Remove default actions that might be causing duplicates
    remove_action('admin_menu', 'massage_booking_admin_menu');
    remove_action('admin_menu', 'massage_booking_add_debug_menu');
    remove_action('admin_menu', 'massage_booking_add_debug_page');
    remove_action('admin_menu', 'massage_booking_add_email_verification_menu');
    remove_action('admin_menu', 'massage_booking_appointments_admin_page');
    remove_action('admin_menu', 'add_reset_ms_auth_page');
    
    // Create clean menu structure
    add_menu_page(
        'Massage Booking',
        'Massage Booking',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Add submenu items in proper order
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
    
    // Email verification utility
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
    
    // MS Graph Auth Reset (hidden in regular menu)
    add_submenu_page(
        null, // No parent - makes it hidden
        'Reset MS Auth',
        'Reset MS Auth',
        'manage_options',
        'massage-booking-reset-ms-auth',
        'reset_ms_auth_page'
    );
}

// Hook our clean menu with a high priority to override others
add_action('admin_menu', 'massage_booking_clean_admin_menu', 999);

/**
 * Add a visible MS Auth reset link to the main Settings page
 */
function massage_booking_add_reset_link_to_settings($settings_fields) {
    // Add a note to the integration settings section
    if (isset($settings_fields['integration_settings'])) {
        $settings_fields['integration_settings']['fields']['ms_auth_reset_note'] = array(
            'label' => 'Auth Reset',
            'type' => 'html',
            'html' => '<a href="' . admin_url('admin.php?page=massage-booking-reset-ms-auth') . '" class="button">Reset Microsoft Auth</a>
                      <p class="description">Use this if you need to troubleshoot Microsoft Graph authentication issues.</p>'
        );
    }
    
    return $settings_fields;
}
add_filter('massage_booking_settings_fields', 'massage_booking_add_reset_link_to_settings');

/**
 * Add pending appointments count to admin menu
 */
function massage_booking_appointments_count_fix() {
    global $submenu;
    
    if (!isset($submenu['massage-booking'])) {
        return;
    }
    
    // Only continue if the database class exists
    if (!class_exists('Massage_Booking_Database')) {
        return;
    }
    
    // Get pending appointments count
    $db = new Massage_Booking_Database();
    if (method_exists($db, 'count_appointments_by_status')) {
        $pending_count = $db->count_appointments_by_status('pending');
        
        if ($pending_count > 0) {
            // Add count to Appointments menu
            foreach ($submenu['massage-booking'] as $key => $menu_item) {
                if ($menu_item[2] === 'massage-booking-appointments') {
                    $submenu['massage-booking'][$key][0] .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
                    break;
                }
            }
        }
    }
}
add_action('admin_menu', 'massage_booking_appointments_count_fix', 9999);