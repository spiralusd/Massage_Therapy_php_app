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

/**
 * Improved file loading with error handling
 */
function massage_booking_load_files() {
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
        if (file_exists($full_path)) {
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

// Initialize plugin components
add_action('plugins_loaded', function() {
    // Version check and updates
    $current_version = get_option('massage_booking_version');
    if ($current_version !== MASSAGE_BOOKING_VERSION) {
        // Perform any necessary migrations or updates
        update_option('massage_booking_version', MASSAGE_BOOKING_VERSION);
    }
});

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
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'debug.php';
}