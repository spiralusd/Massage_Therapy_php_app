<?php
/**
 * Plugin Name: Massage Booking System
 * Description: HIPAA-compliant booking system for massage therapy
 * Version: 1.0.6
 * Author: Darrin Jackson/Spiral Powered Records
 * Text Domain: massage-booking
 */

// Version history:
// 1.0.0 - Initial release
// 1.0.1 - Bug fixes and performance improvements
// 1.0.2 - Enhanced security and optimization
// 1.0.3 - Added thank you page, email verification, and diagnostics features
// 1.0.4 - Minor Changes, fixed Json
// 1.0.5 - Bug fixes, improved appointments page
// 1.0.6 - Admin debug panel

/**
 * Changelog for version 1.0.3:
 * - Added thank you page template for appointment confirmation
 * - Implemented email verification and diagnostics page
 * - Enhanced email logging and tracking
 * - Added action hook for post-appointment creation
 * - Improved admin email configuration testing
 */

/**
 * Version: 1.0.5
 * Changelog:
 * - Fixed timezone compatibility issue with Microsoft Graph API
 * - Resolved JSON parsing errors in form submission
 * - Improved time slot loading and selection
 * - Enhanced error handling and user feedback
 * - Added additional HIPAA-compliant logging
 * - Improved form responsiveness on mobile devices
 */

/**
 *  Version: 1.0.5.1
 *  Changelog
 *  -Small update to fix calender intergration
*/

/**
 * Version: 1.0.6
 * Changelog:
 * - Fixed Microsoft Graph calendar integration authentication issue
 * - Added enhanced debug system with admin interface
 * - Added ability to enable/disable debugging from settings
 * - Added context-based filtering for debug logs
 * - Added tool to reset Microsoft Graph authentication
 * - Improved error handling in calendar operations
 * - Enhanced MS Graph authentication with better token management
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MASSAGE_BOOKING_VERSION', '1.0.6'); // Match the plugin header version
define('MASSAGE_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASSAGE_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if we're in WordPress admin area
 * Excludes AJAX calls which can happen in admin or front-end
 */
function massage_booking_is_admin_area() {
    return is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX);
}

// Critical files needed first - Database, settings, and encryption
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-settings.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption-optimized.php';

// Debug utilities should load early
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'debug.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/enhanced-debug.php';

// Audit log system
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log-optimized.php';

// Load optimized functions and integration
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'functions-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'integration.php';

// Database extension (should load after database class)
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/database-extension.php';

// MS Auth Reset tool (admin only)
if (is_admin()) {
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/reset-ms-auth.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin-fix.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-page.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/settings-page.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-appointments-fix.php';
}

// Microsoft Graph Authentication Handler (needed for calendar)
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-ms-graph-auth.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar-optimized.php';

// Always include the appointments class with REST API endpoints
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-appointments.php';

// Email and notification systems
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/emails-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/thank-you-page-integration.php';

// Frontend-specific files
if (!massage_booking_is_admin_area()) {
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/booking-form.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/shortcodes.php';
}

// Enhanced debug
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-debug.php';

// Activation hook
register_activation_hook(__FILE__, 'massage_booking_activate');
function massage_booking_activate() {
    // Check system requirements
    massage_booking_check_requirements();
    
    // Create database tables
    $database = new Massage_Booking_Database();
    $database->create_tables();
    
    // Set default settings
    $settings = new Massage_Booking_Settings();
    $settings->set_defaults();
    
    // Create audit log table
    $audit_log = new Massage_Booking_Audit_Log();
    $audit_log->create_table();
    
    // Clear permalinks
    flush_rewrite_rules();
    
    // Log activation
    $audit_log->log_action('plugin_activated', get_current_user_id());
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'massage_booking_deactivate');
function massage_booking_deactivate() {
    // Log deactivation
    $audit_log = new Massage_Booking_Audit_Log();
    $audit_log->log_action('plugin_deactivated', get_current_user_id());
    
    // Clear scheduled events
    $timestamp = wp_next_scheduled('massage_booking_backup_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'massage_booking_backup_event');
    }
    
    // Clear permalinks
    flush_rewrite_rules();
}

// Uninstall hook (called when plugin is deleted)
register_uninstall_hook(__FILE__, 'massage_booking_uninstall');
function massage_booking_uninstall() {
    // This function will be called statically, so we don't have access to instance methods
    // Only remove data if user opted in (check settings)
    $remove_data = get_option('massage_booking_remove_data_on_uninstall', false);
    
    if ($remove_data) {
        global $wpdb;
        
        // Remove database tables
        $tables = array(
            $wpdb->prefix . 'massage_appointments',
            $wpdb->prefix . 'massage_special_dates',
            $wpdb->prefix . 'massage_audit_log'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove all options
        $options = $wpdb->get_results(
            "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'massage_booking_%'"
        );
        
        foreach ($options as $option) {
            delete_option($option->option_name);
        }
    }
}

// Add backup functionality
function massage_booking_register_backup() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-backup.php';
    $backup = new Massage_Booking_Backup();
    
    // Schedule backup on activation
    register_activation_hook(__FILE__, array($backup, 'schedule_backups'));
    
    // Unschedule backup on deactivation
    register_deactivation_hook(__FILE__, array($backup, 'unschedule_backups'));
    
    // Hook backup function to scheduled event
    add_action('massage_booking_backup_event', array($backup, 'create_backup'));
    
    // Add backup now button to admin
    add_action('massage_booking_admin_backup_button', function() {
        echo '<form method="post">';
        wp_nonce_field('massage_booking_backup_now');
        echo '<input type="submit" name="massage_booking_backup_now" class="button" value="Backup Database Now">';
        echo '</form>';
    });
    
    // Handle backup now request
    if (isset($_POST['massage_booking_backup_now']) && check_admin_referer('massage_booking_backup_now')) {
        $backup->create_backup();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Backup created successfully.</p></div>';
        });
    }
}
add_action('plugins_loaded', 'massage_booking_register_backup');

/**
 * Register custom REST API endpoints
 */
function massage_booking_register_rest_api() {
    $appointments = new Massage_Booking_Appointments();
    $appointments->register_rest_routes();
}
add_action('rest_api_init', 'massage_booking_register_rest_api');

/**
 * Plugin initialization
 */
function massage_booking_init() {
    // Load text domain for translations
    load_plugin_textdomain('massage-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'massage_booking_init');

/**
 * Debug function to log template loading
 */
function massage_booking_debug_template_loading() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        global $post;
        if (is_object($post)) {
            $template = get_post_meta($post->ID, '_wp_page_template', true);
            error_log('Massage Booking Debug - Page ID: ' . $post->ID . ', Template: ' . $template);
        }
    }
}
add_action('wp', 'massage_booking_debug_template_loading');

/**
 * Add CSS to hide the booking form in admin
 */
function massage_booking_admin_css_fix() {
    if (is_admin()) {
        echo '<style>
            body.wp-admin #appointmentForm,
            body.wp-admin form#appointmentForm,
            body.wp-admin .booking-form-container {
                display: none !important;
            }
        </style>';
    }
}
add_action('admin_head', 'massage_booking_admin_css_fix');

/**
 * Force admin template reset to prevent template display issues in admin
 */
function massage_booking_force_admin_template_reset() {
    if (is_admin() && !defined('DOING_AJAX')) {
        global $post;
        if (is_object($post) && get_post_meta($post->ID, '_wp_page_template', true) === MASSAGE_BOOKING_PLUGIN_DIR . 'public/templates/page-booking.php') {
            // Force reset the template to default in admin
            update_post_meta($post->ID, '_wp_page_template_temp', get_post_meta($post->ID, '_wp_page_template', true));
            update_post_meta($post->ID, '_wp_page_template', 'default');
            
            // Add action to restore original template for frontend
            add_action('shutdown', function() use ($post) {
                if (get_post_meta($post->ID, '_wp_page_template_temp', true)) {
                    update_post_meta($post->ID, '_wp_page_template', get_post_meta($post->ID, '_wp_page_template_temp', true));
                    delete_post_meta($post->ID, '_wp_page_template_temp');
                }
            });
        }
    }
}
add_action('admin_init', 'massage_booking_force_admin_template_reset');

// Version Check functionality
function massage_booking_check_version() {
    $current_version = get_option('massage_booking_version');
    
    if ($current_version !== MASSAGE_BOOKING_VERSION) {
        // Perform any necessary updates or migrations
        update_option('massage_booking_version', MASSAGE_BOOKING_VERSION);
        
        // Log version update
        if (class_exists('Massage_Booking_Audit_Log')) {
            $audit_log = new Massage_Booking_Audit_Log();
            $audit_log->log_action('plugin_version_update', get_current_user_id(), null, 'plugin', [
                'old_version' => $current_version,
                'new_version' => MASSAGE_BOOKING_VERSION
            ]);
        }
    }
}
add_action('plugins_loaded', 'massage_booking_check_version');

/**
 * Clear any conflicting AJAX handlers when plugin loads
 * This ensures we don't have duplicate handlers causing issues
 */
function massage_booking_clear_conflicting_handlers() {
    // Check if functions from various files exist and remove potential conflicts
    if (function_exists('massage_booking_handle_appointment')) {
        remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_handle_appointment');
        remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_handle_appointment');
    }
    
    if (function_exists('massage_booking_simple_appointment_handler')) {
        remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
        remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_simple_appointment_handler');
    }
    
    if (function_exists('massage_booking_improved_appointment_handler')) {
        remove_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_improved_appointment_handler');
        remove_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_improved_appointment_handler');
    }
}
add_action('init', 'massage_booking_clear_conflicting_handlers', 1); // Priority 1 to run before others

require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin-menu-fix.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'appointments-page-fix.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'plugin-integration-fix.php';