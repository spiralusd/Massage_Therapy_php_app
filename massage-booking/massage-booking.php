<?php
/**
 * Plugin Name: Massage Booking System
 * Description: HIPAA-compliant booking system for massage therapy
 * Version: 1.1.0
 * Author: Darrin Jackson/Spiral Powered Records
 * Text Domain: massage-booking
 * Requires at least: 5.7
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Define plugin constants with added security checks
defined('ABSPATH') || exit;

// Plugin version and paths with more robust definition
if (!defined('MASSAGE_BOOKING_VERSION')) {
    define('MASSAGE_BOOKING_VERSION', '1.1.0');
}

if (!defined('MASSAGE_BOOKING_PLUGIN_DIR')) {
    define('MASSAGE_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('MASSAGE_BOOKING_PLUGIN_URL')) {
    define('MASSAGE_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Flag to prevent duplicate class loading
if (!defined('MASSAGE_BOOKING_LOADED')) {
    define('MASSAGE_BOOKING_LOADED', true);
}

/**
 * Centralized error logging method
 */
function massage_booking_log_error($message, $context = 'general') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Massage Booking [{$context}]: {$message}");
    }
}

/**
 * Enhanced system requirements check
 */
function massage_booking_check_requirements() {
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');
    $errors = [];

    // PHP Version Check
    if (version_compare($php_version, '7.4', '<')) {
        $errors[] = "PHP 7.4+ required. Current version: {$php_version}";
    }

    // WordPress Version Check
    if (version_compare($wp_version, '5.7', '<')) {
        $errors[] = "WordPress 5.7+ required. Current version: {$wp_version}";
    }

    // Required Extensions
    $required_extensions = ['openssl', 'json', 'mbstring'];
    $missing_extensions = array_filter($required_extensions, function($ext) {
        return !extension_loaded($ext);
    });

    if (!empty($missing_extensions)) {
        $errors[] = "Missing PHP extensions: " . implode(', ', $missing_extensions);
    }

    if (!empty($errors)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<h1>Massage Booking System - Activation Error</h1>' .
            '<p>' . implode('<br>', $errors) . '</p>' .
            '<p>Please resolve these issues to activate the plugin.</p>'
        );
    }
}

// Load the fixes
if (file_exists(plugin_dir_path(__FILE__) . 'massage-booking-fixes.php')) {
    require_once plugin_dir_path(__FILE__) . 'massage-booking-fixes.php';
}

// Include the function fix patch to prevent function redeclaration
require_once plugin_dir_path(__FILE__) . 'function-fix.php';

/**
 * Improved file loading with error handling and include guards
 */
function massage_booking_load_files() {
    // Track loaded files to prevent duplicate inclusion
    static $loaded_files = [];
    
    $required_files = [
        'includes/class-settings.php',
        'includes/class-encryption-optimized.php',
        'includes/class-database-optimized.php',
        'includes/class-audit-log-optimized.php',
        'includes/class-emails.php',
        'includes/class-appointments.php',
        'admin/settings-page.php'
    ];

    foreach ($required_files as $file) {
        $full_path = MASSAGE_BOOKING_PLUGIN_DIR . $file;
        
        // Skip if already loaded
        if (isset($loaded_files[$full_path])) {
            continue;
        }
        
        if (file_exists($full_path)) {
            // Mark as loaded before including to prevent recursion
            $loaded_files[$full_path] = true;
            
            require_once $full_path;
        } else {
            massage_booking_log_error("Required file missing: {$file}", 'initialization');
        }
    }
}

/**
 * More robust activation hook
 */
function massage_booking_activate() {
    massage_booking_check_requirements();
    
    // Ensure files are loaded before activation tasks
    massage_booking_load_files();

    // Create tables
    if (class_exists('Massage_Booking_Database')) {
        $database = new Massage_Booking_Database();
        $database->create_tables();
    }

    // Set default settings
    if (class_exists('Massage_Booking_Settings')) {
        $settings = new Massage_Booking_Settings();
        $settings->set_defaults();
    }

    // Log activation for audit purposes
    if (class_exists('Massage_Booking_Audit_Log')) {
        $audit_log = new Massage_Booking_Audit_Log();
        $audit_log->log_action('plugin_activated', get_current_user_id());
    }

    // Create backup handler if needed
    if (class_exists('Massage_Booking_Backup')) {
        $backup = new Massage_Booking_Backup();
        $backup->schedule_backups();
    }

    // Ensure rewrite rules are refreshed
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'massage_booking_activate');

/**
 * More comprehensive deactivation hook
 */
function massage_booking_deactivate() {
    // Log deactivation
    if (class_exists('Massage_Booking_Audit_Log')) {
        $audit_log = new Massage_Booking_Audit_Log();
        $audit_log->log_action('plugin_deactivated', get_current_user_id());
    }

    // Remove scheduled events
    wp_clear_scheduled_hook('massage_booking_backup_event');

    // Unschedule backups if class exists
    if (class_exists('Massage_Booking_Backup')) {
        $backup = new Massage_Booking_Backup();
        $backup->unschedule_backups();
    }

    // Clear rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'massage_booking_deactivate');

/**
 * Main plugin initialization
 */
function massage_booking_init() {
    // Load translation files
    load_plugin_textdomain('massage-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Load required files
    massage_booking_load_files();
}
add_action('init', 'massage_booking_init', 1);

/**
 * Register REST API endpoints
 */
function massage_booking_register_rest_routes() {
    if (class_exists('Massage_Booking_Appointments')) {
        $appointments = new Massage_Booking_Appointments();
        $appointments->register_rest_routes();
    }
}
add_action('rest_api_init', 'massage_booking_register_rest_routes');

/**
 * Dashboard page for admin
 
function massage_booking_dashboard_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if database class exists
    if (!class_exists('Massage_Booking_Database')) {
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div class="notice notice-error"><p>Error: Database class not found.</p></div>';
        echo '</div>';
        return;
    }
    
    $db = new Massage_Booking_Database();
    
    // Get upcoming appointments
    $upcoming_appointments = method_exists($db, 'get_upcoming_appointments') ? 
        $db->get_upcoming_appointments(5) : array();
    
    // Get appointment counts
    $pending_count = method_exists($db, 'count_appointments_by_status') ? 
        $db->count_appointments_by_status('pending') : 0;
    
    $confirmed_count = method_exists($db, 'count_appointments_by_status') ? 
        $db->count_appointments_by_status('confirmed') : 0;
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    echo '<div class="wrap massage-booking-admin">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    
    // Quick stats cards
    echo '<div class="dashboard-stats">';
    
    // Pending appointments
    echo '<div class="stat-card">';
    echo '<h3>Pending Appointments</h3>';
    echo '<div class="stat-number">' . esc_html($pending_count) . '</div>';
    if ($pending_count > 0) {
        echo '<a href="?page=massage-booking-appointments&status=pending" class="button">View All</a>';
    }
    echo '</div>';
    
    // Today's appointments
    $today_count = method_exists($db, 'get_all_appointments') ? 
        count($db->get_all_appointments('confirmed', $today, $today)) : 0;
    
    echo '<div class="stat-card">';
    echo '<h3>Today\'s Appointments</h3>';
    echo '<div class="stat-number">' . esc_html($today_count) . '</div>';
    if ($today_count > 0) {
        echo '<a href="?page=massage-booking-appointments&status=confirmed&date_from=' . esc_attr($today) . '&date_to=' . esc_attr($today) . '" class="button">View All</a>';
    }
    echo '</div>';
    
    // Tomorrow's appointments
    $tomorrow_count = method_exists($db, 'get_all_appointments') ? 
        count($db->get_all_appointments('confirmed', $tomorrow, $tomorrow)) : 0;
    
    echo '<div class="stat-card">';
    echo '<h3>Tomorrow\'s Appointments</h3>';
    echo '<div class="stat-number">' . esc_html($tomorrow_count) . '</div>';
    if ($tomorrow_count > 0) {
        echo '<a href="?page=massage-booking-appointments&status=confirmed&date_from=' . esc_attr($tomorrow) . '&date_to=' . esc_attr($tomorrow) . '" class="button">View All</a>';
    }
    echo '</div>';
    
    // Total confirmed
    echo '<div class="stat-card">';
    echo '<h3>Confirmed Appointments</h3>';
    echo '<div class="stat-number">' . esc_html($confirmed_count) . '</div>';
    echo '<a href="?page=massage-booking-appointments&status=confirmed" class="button">View All</a>';
    echo '</div>';
    
    echo '</div>'; // dashboard-stats
    
    // Upcoming appointments section
    echo '<div class="dashboard-section">';
    echo '<h2>Upcoming Appointments</h2>';
    
    if (empty($upcoming_appointments)) {
        echo '<p>No upcoming appointments.</p>';
    } else {
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Client</th><th>Date</th><th>Time</th><th>Duration</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($upcoming_appointments as $appointment) {
            echo '<tr>';
            echo '<td>' . esc_html($appointment->client_name ?? '') . '</td>';
            
            // Format date
            $date = new DateTime($appointment->appointment_date);
            echo '<td>' . esc_html($date->format('M j, Y')) . '</td>';
            
            // Format time
            $start_time = new DateTime($appointment->start_time);
            echo '<td>' . esc_html($start_time->format('g:i a')) . '</td>';
            
            echo '<td>' . esc_html($appointment->duration) . ' min</td>';
            
            echo '<td><a href="?page=massage-booking-appointments&action=view&id=' . esc_attr($appointment->id) . '" class="button button-small">View</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '</div>'; // dashboard-section
    
    // Quick links section
    echo '<div class="dashboard-section">';
    echo '<h2>Quick Links</h2>';
    echo '<div class="quick-links">';
    echo '<a href="?page=massage-booking-appointments" class="button">All Appointments</a>';
    echo '<a href="?page=massage-booking-settings" class="button">Settings</a>';
    
    // Only show these links if the features are available
    if (class_exists('Massage_Booking_Calendar') || file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar-optimized.php')) {
        echo '<a href="?page=massage-booking-settings#integration-settings" class="button">Calendar Integration</a>';
    }
    
    if (class_exists('Massage_Booking_Audit_Log') || file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log-optimized.php')) {
        echo '<a href="?page=massage-booking-logs" class="button">Audit Logs</a>';
    }
    
    echo '</div>'; // quick-links
    echo '</div>'; // dashboard-section
    
    echo '</div>'; // wrap
} */

// Check for admin-page.php file and include it if it exists
if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-page.php')) {
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-page.php';
}

// Initialize plugin components
add_action('plugins_loaded', function() {
    // Skip if already loaded
    if (!defined('MASSAGE_BOOKING_LOADED')) {
        return;
    }
    
    // Version check and updates
    $current_version = get_option('massage_booking_version');
    if ($current_version !== MASSAGE_BOOKING_VERSION) {
        // Perform any necessary migrations or updates
        update_option('massage_booking_version', MASSAGE_BOOKING_VERSION);
    }

    // Load optional modules only if they're not already loaded
    $optional_modules = [
        'includes/class-ms-graph-auth.php',
        'includes/class-calendar-optimized.php',
        'includes/emails-optimized.php',
        'includes/database-extension.php',
        'includes/thank-you-page-integration.php',
        'includes/class-backup.php'
    ];
    
    foreach ($optional_modules as $module) {
        $full_path = MASSAGE_BOOKING_PLUGIN_DIR . $module;
        if (file_exists($full_path)) {
            // Define a unique constant for each module to prevent duplicates
            $module_const = 'MASSAGE_BOOKING_' . strtoupper(basename($module, '.php')) . '_LOADED';
            if (!defined($module_const)) {
                define($module_const, true);
                require_once $full_path;
            }
        }
    }
});

// Add admin menu
add_action('admin_menu', 'massage_booking_clean_admin_menu', 999);

/*
// Clean admin menu function
function massage_booking_clean_admin_menu() {
    // Create main menu
    add_menu_page(
        'Massage Booking',           // Page title
        'Massage Booking',            // Menu title
        'manage_options',             // Capability required
        'massage-booking',            // Menu slug
        'massage_booking_dashboard_page', // Callback function to display page
        'dashicons-calendar-alt',     // Icon
        30                            // Position
    );
    
    // Add submenu pages
    add_submenu_page(
        'massage-booking',            // Parent slug
        'Dashboard',                  // Page title
        'Dashboard',                  // Menu title
        'manage_options',             // Capability
        'massage-booking',            // Menu slug
        'massage_booking_dashboard_page' // Callback
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
} */

// Prevent direct file access to key plugin files
foreach ([
    'includes/class-settings.php',
    'includes/class-encryption-optimized.php',
    'includes/class-database-optimized.php',
    'admin/settings-page.php',
    'public/booking-form.php',
    'includes/class-appointments.php'
] as $file) {
    add_filter('plugin_file_' . plugin_basename(MASSAGE_BOOKING_PLUGIN_DIR . $file), function() {
        die('Access denied');
    });
}

// Optional: Add debug tools in development
if (defined('WP_DEBUG') && WP_DEBUG) {
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'debug.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'debug.php';
    }
}