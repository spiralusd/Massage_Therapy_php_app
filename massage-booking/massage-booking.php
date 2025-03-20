<?php
/**
 * Plugin Name: Massage Booking System
 * Description: HIPAA-compliant booking system for massage therapy
 * Version: 1.0.5
 * Author: Darrin Jackson/Spiral Powered Records
 * Text Domain: massage-booking
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Version history:
// 1.0.0 - Initial release
// 1.0.1 - Bug fixes and performance improvements
// 1.0.2 - Enhanced security and optimization
// 1.0.3 - Added thank you page, email verification, and diagnostics features
// 1.0.4 - Minor Changes, fixed Json
// 1.0.5 - Bug fixes, improved appointments page

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

// Define plugin constants
define('MASSAGE_BOOKING_VERSION', '1.0.5'); // Match the plugin header version
define('MASSAGE_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASSAGE_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if we're in WordPress admin area
 * Excludes AJAX calls which can happen in admin or front-end
 */
function massage_booking_is_admin_area() {
    return is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX);
}

// Include core files needed everywhere
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-settings.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/database-extension.php';

// Include optimized functions file
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'functions-optimized.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'integration.php';

// Include context-specific files
if (massage_booking_is_admin_area()) {
    // Admin-only includes
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin-fix.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-page.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/settings-page.php';
} else {
    // Front-end only includes
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/booking-form.php';
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/shortcodes.php';
}

// Always include the appointments class with REST API endpoints
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-appointments.php';
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar-optimized.php';

// Include the new email verification integration
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/thank-you-page-integration.php';

// Include Microsoft Graph Authentication Handler
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-ms-graph-auth.php';

// Include the emails class
require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/emails-optimized.php';

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

/**
 * Improved template loading function
 */
function massage_booking_load_template($template) {
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
    
    if (is_page() && $template_meta === $our_template) {
        // Log that we're loading our template (for debugging)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Massage Booking: Loading template ' . $our_template);
        }
        
        return $our_template;
    }
    
    return $template;
}

// Add template filter (don't try to remove it first as it wasn't added yet)
add_filter('template_include', 'massage_booking_load_template');

function massage_booking_load_template_safe($template) {
    // NEVER apply in admin area
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
    
    if (is_page() && $template_meta === $our_template) {
        return $our_template;
    }
    
    return $template;
}

// Add this after massage_booking_load_template function is defined
add_filter('template_include', 'massage_booking_load_template_safe', 20);

/**
 * Register and enqueue assets (original version - now handled by optimized version)
 * Kept for reference but not hooked
 */
function massage_booking_register_assets() {
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
        
        // Register JS
        wp_register_script(
            'massage-booking-form-script',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/booking-form-optimized.js',
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Register API connector
        wp_register_script(
            'massage-booking-api-connector',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-connector-optimized.js',
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
// Don't add the original action - the optimized version will be used instead
// The remove_action call is in functions-optimized.php

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
    
    // Register post types or taxonomies if needed
    // (none for this plugin)
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
// Add the missing hook for the admin template reset function
add_action('admin_init', 'massage_booking_force_admin_template_reset');

//Version Check functionality
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