<?php
/**
 * Plugin Name: Massage Booking System
 * Description: HIPAA-compliant booking system for massage therapy
 * Version: 1.0.7
 * Author: Darrin Jackson/Spiral Powered Records
 * Text Domain: massage-booking
 */

// Version history:
// 1.0.0 - Initial release
// 1.0.1 - Bug fixes and performance improvements
// 1.0.2 - Enhanced security and optimization
// 1.0.3 - Added thank you page, email verification, and diagnostics features
// 1.0.4 - Minor Changes, fixed JSON handling
// 1.0.5 - Bug fixes, improved appointments page
// 1.0.6 - Admin debug panel
// 1.0.7 - Code restructure, fixed function redeclaration issues

/**
 * Changelog for version 1.0.7:
 * - Fixed fatal error related to function redeclaration
 * - Consolidated fix files into main functionality
 * - Improved plugin loading sequence
 * - Streamlined code structure
 * - Enhanced MS Graph authentication
 * - Fixed database extension handling
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MASSAGE_BOOKING_VERSION', '1.0.7');
define('MASSAGE_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASSAGE_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if we're in WordPress admin area
 * Excludes AJAX calls which can happen in admin or front-end
 */
function massage_booking_is_admin_area() {
    return is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX);
}

/**
 * Load required files in the correct order
 */
function massage_booking_load_files() {
    // Critical files needed first - Database, settings, and encryption
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-settings.php';
    
    // Choose optimized encryption if available
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption-optimized.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption-optimized.php';
    } else {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption.php';
    }
    
    // Choose optimized database if available
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database-optimized.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database-optimized.php';
    } else {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-database.php';
        
        // Load database extension if main class doesn't have all required methods
        if (class_exists('Massage_Booking_Database') && !method_exists('Massage_Booking_Database', 'get_all_appointments')) {
            require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/database-extension.php';
        }
    }
    
    // Debug utilities should load early
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'debug.php';
    
    // Audit log system - choose optimized version if available
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log-optimized.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log-optimized.php';
    } else {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-audit-log.php';
    }
    
    // MS Auth handler (needed for calendar integration)
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-ms-graph-auth.php';
    
    
    // Calendar functionality - choose optimized version if available
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar-optimized.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar-optimized.php';
    } else {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-calendar.php';
    }
    
    // Always include the appointments class with REST API endpoints
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-appointments.php';
    
    // Email and notification systems
    if (file_exists(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/emails-optimized.php')) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/emails-optimized.php';
    } else {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-emails.php';
    }
    
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/thank-you-page-integration.php';
    
    // Admin-only files
    if (is_admin()) {
        
        // Admin pages
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/admin-page.php';
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'admin/settings-page.php';
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'reset-ms-auth.php';
    }
    
    // Frontend-specific files
    if (!massage_booking_is_admin_area()) {
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/booking-form.php';
        require_once MASSAGE_BOOKING_PLUGIN_DIR . 'public/shortcodes.php';
    }
    
    // Backup functionality
    require_once MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-backup.php';
    
    // All integration fixes are now consolidated within this main file
}

// Load plugin files
massage_booking_load_files();

/**
 * Plugin initialization
 */
function massage_booking_init() {
    // Load text domain for translations
    load_plugin_textdomain('massage-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Add this to prevent form execution in admin area
    if (is_admin()) {
        add_action('admin_head', 'massage_booking_admin_css_fix');
    }
}
add_action('init', 'massage_booking_init');

/**
 * Add CSS to hide the booking form in admin
 */
function massage_booking_admin_css_fix() {
    echo '<style>
        body.wp-admin #appointmentForm,
        body.wp-admin form#appointmentForm,
        body.wp-admin .booking-form-container {
            display: none !important;
        }
    </style>';
}

/**
 * Register custom REST API endpoints
 */
function massage_booking_register_rest_api() {
    if (class_exists('Massage_Booking_Appointments')) {
        $appointments = new Massage_Booking_Appointments();
        $appointments->register_rest_routes();
    }
}
add_action('rest_api_init', 'massage_booking_register_rest_api');

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
add_filter('template_include', 'massage_booking_load_template_admin_fix');

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
        $use_minified = file_exists($js_path . 'booking-form-min.js') && file_exists($js_path . 'api-connector-min.js');
        
        // Register JS - use optimized versions when available
        $form_script = file_exists($js_path . 'booking-form-optimized.js') ? 'booking-form-optimized.js' : 'booking-form.js';
        $api_script = file_exists($js_path . 'api-connector-optimized.js') ? 'api-connector-optimized.js' : 'api-connector.js';
        
        // Use minified versions if available and requested
        if ($use_minified) {
            $form_script = 'booking-form-min.js';
            $api_script = 'api-connector-min.js';
        }
        
        wp_register_script(
            'massage-booking-form-script',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . $form_script,
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Register API connector
        wp_register_script(
            'massage-booking-api-connector',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/' . $api_script,
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
        if (method_exists($encryption, 'test_encryption') && !$encryption->test_encryption()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('This plugin requires proper encryption support. Please check your server configuration or contact your hosting provider.');
        }
    }
}

/**
 * Activation hook
 */
function massage_booking_activate() {
    // Check system requirements
    massage_booking_check_requirements();
    
    // Create database tables
    if (class_exists('Massage_Booking_Database')) {
        $database = new Massage_Booking_Database();
        $database->create_tables();
    }
    
    // Set default settings
    if (class_exists('Massage_Booking_Settings')) {
        $settings = new Massage_Booking_Settings();
        $settings->set_defaults();
    }
    
    // Create audit log table
    if (class_exists('Massage_Booking_Audit_Log')) {
        $audit_log = new Massage_Booking_Audit_Log();
        if (method_exists($audit_log, 'create_table')) {
            $audit_log->create_table();
        }
        
        // Log activation
        $audit_log->log_action('plugin_activated', get_current_user_id());
    }
    
    // Clear permalinks
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'massage_booking_activate');

/**
 * Deactivation hook
 */
function massage_booking_deactivate() {
    // Log deactivation
    if (class_exists('Massage_Booking_Audit_Log')) {
        $audit_log = new Massage_Booking_Audit_Log();
        $audit_log->log_action('plugin_deactivated', get_current_user_id());
    }
    
    // Clear scheduled events
    $timestamp = wp_next_scheduled('massage_booking_backup_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'massage_booking_backup_event');
    }
    
    // Clear permalinks
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'massage_booking_deactivate');

/**
 * Uninstall hook (called when plugin is deleted)
 */
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
register_uninstall_hook(__FILE__, 'massage_booking_uninstall');

/**
 * Add backup functionality
 */
function massage_booking_register_backup() {
    if (!class_exists('Massage_Booking_Backup')) {
        return;
    }
    
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
 * Version Check functionality
 */
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
 * AJAX handlers for appointment creation
 * This unified appointment handler replaces multiple conflicting handlers
 */
function massage_booking_unified_appointment_handler() {
    // Start output buffering to capture any unexpected output
    ob_start();
    
    // Debug logging if enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Appointment submission received: ' . print_r($_POST, true));
    }
    
    try {
        // Force JSON content type for response
        header('Content-Type: application/json');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_rest')) {
            throw new Exception('Security verification failed');
        }
        
        // Extract and validate required fields
        $required_fields = ['fullName', 'email', 'phone', 'appointmentDate', 'startTime', 'duration'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
        }
        
        // Sanitize and collect data
        $appointment_data = [
            'full_name' => sanitize_text_field($_POST['fullName']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'appointment_date' => sanitize_text_field($_POST['appointmentDate']),
            'start_time' => sanitize_text_field($_POST['startTime']),
            'duration' => intval($_POST['duration']),
            'status' => 'confirmed',
        ];
        
        // Handle end time
        if (isset($_POST['endTime']) && !empty($_POST['endTime'])) {
            $appointment_data['end_time'] = sanitize_text_field($_POST['endTime']);
        } else {
            // Calculate end time
            $datetime = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['start_time']);
            $end_datetime = clone $datetime;
            $end_datetime->modify('+' . $appointment_data['duration'] . ' minutes');
            $appointment_data['end_time'] = $end_datetime->format('H:i');
        }
        
        // Handle focus areas
        if (isset($_POST['focusAreas'])) {
            $focus_areas_raw = $_POST['focusAreas'];
            
            // Handle different formats of focusAreas (string or array)
            if (is_string($focus_areas_raw)) {
                // Try to decode JSON string
                $decoded = json_decode(stripslashes($focus_areas_raw), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $appointment_data['focus_areas'] = array_map('sanitize_text_field', $decoded);
                } else {
                    // Handle comma-separated string
                    $appointment_data['focus_areas'] = array_map('trim', explode(',', sanitize_text_field($focus_areas_raw)));
                }
            } elseif (is_array($focus_areas_raw)) {
                $appointment_data['focus_areas'] = array_map('sanitize_text_field', $focus_areas_raw);
            } else {
                $appointment_data['focus_areas'] = [];
            }
        } else {
            $appointment_data['focus_areas'] = [];
        }
        
        // Handle optional fields
        $appointment_data['pressure_preference'] = isset($_POST['pressurePreference']) ? 
            sanitize_text_field($_POST['pressurePreference']) : '';
            
        $appointment_data['special_requests'] = isset($_POST['specialRequests']) ? 
            sanitize_textarea_field($_POST['specialRequests']) : '';
        
        // Make sure necessary classes are loaded
        if (!class_exists('Massage_Booking_Database')) {
            throw new Exception('Required database class not found');
        }
        
        // Check slot availability
        $db = new Massage_Booking_Database();
        if (method_exists($db, 'check_slot_availability') && 
            !$db->check_slot_availability($appointment_data['appointment_date'], $appointment_data['start_time'], $appointment_data['duration'])) {
            throw new Exception('This time slot is no longer available. Please choose another time.');
        }
        
        // Try to add to calendar if configured
        $calendar_event_id = null;
        
        if (class_exists('Massage_Booking_Calendar')) {
            $calendar = new Massage_Booking_Calendar();
            
            if (method_exists($calendar, 'is_configured') && $calendar->is_configured()) {
                $event_result = $calendar->create_event($appointment_data);
                
                if (!is_wp_error($event_result) && isset($event_result['id'])) {
                    $calendar_event_id = $event_result['id'];
                    $appointment_data['calendar_event_id'] = $calendar_event_id;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Calendar event created with ID: ' . $calendar_event_id);
                    }
                }
            }
        }
        
        // Save appointment to database
        $appointment_id = $db->create_appointment($appointment_data);
        
        if (!$appointment_id) {
            // If calendar event was created but appointment saving failed, try to delete it
            if ($calendar_event_id && class_exists('Massage_Booking_Calendar')) {
                $calendar = new Massage_Booking_Calendar();
                if (method_exists($calendar, 'delete_event')) {
                    $calendar->delete_event($calendar_event_id);
                }
            }
            
            throw new Exception('Failed to save appointment to database');
        }
        
        // Send emails if possible
        $emails_sent = ['client' => false, 'admin' => false];
        
        if (class_exists('Massage_Booking_Emails')) {
            $emails = new Massage_Booking_Emails();
            
            try {
                $emails_sent['client'] = $emails->send_client_confirmation($appointment_data);
            } catch (Exception $e) {
                error_log('Failed to send client confirmation email: ' . $e->getMessage());
            }
            
            try {
                $emails_sent['admin'] = $emails->send_therapist_notification($appointment_data);
            } catch (Exception $e) {
                error_log('Failed to send admin notification email: ' . $e->getMessage());
            }
        }
        
        // Trigger after-creation hooks
        do_action('massage_booking_after_appointment_created', $appointment_id, $appointment_data);
        
        // Clean output buffer
        $output = ob_get_clean();
        if (!empty($output) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Unexpected output in appointment handler: ' . $output);
        }
        
        // Return success
        wp_send_json_success([
            'appointment_id' => $appointment_id,
            'message' => 'Your appointment has been booked successfully!',
            'calendar_added' => !empty($calendar_event_id),
            'emails_sent' => $emails_sent
        ]);
        
    } catch (Exception $e) {
        // Log the error
        error_log('Error in massage_booking_unified_appointment_handler: ' . $e->getMessage());
        
        // Clean output buffer
        $output = ob_get_clean();
        if (!empty($output)) {
            error_log('Unexpected output in appointment handler error path: ' . $output);
        }
        
        // Return error
        wp_send_json_error([
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 400
        ]);
    }
    
    // Just in case we didn't exit earlier
    die();
}

// Register the unified appointment handler
add_action('wp_ajax_massage_booking_create_appointment', 'massage_booking_unified_appointment_handler');
add_action('wp_ajax_nopriv_massage_booking_create_appointment', 'massage_booking_unified_appointment_handler');

/**
 * Improved slot availability checker to handle different request formats
 */
function massage_booking_robust_slot_availability() {
    try {
        // Force JSON content type
        header('Content-Type: application/json');
        
        // Extract parameters from POST or GET
        $date = isset($_REQUEST['date']) ? sanitize_text_field($_REQUEST['date']) : '';
        $time = isset($_REQUEST['time']) ? sanitize_text_field($_REQUEST['time']) : '';
        $duration = isset($_REQUEST['duration']) ? intval($_REQUEST['duration']) : 60;
        
        // Validate required parameters
        if (empty($date)) {
            wp_send_json_error(['message' => 'Missing required date parameter']);
            return;
        }
        
        if (empty($time)) {
            // If no specific time requested, return all slots for the date
            if (class_exists('Massage_Booking_Appointments')) {
                $appointments = new Massage_Booking_Appointments();
                $available_slots = $appointments->get_available_slots(
                    (object)['get_params' => function() use ($date, $duration) { 
                        return ['date' => $date, 'duration' => $duration]; 
                    }]
                );
                wp_send_json($available_slots);
                return;
            } else {
                wp_send_json_error(['message' => 'Missing required time parameter']);
                return;
            }
        }
        
        // Check availability for specific time
        $db = new Massage_Booking_Database();
        $is_available = method_exists($db, 'check_slot_availability') ? 
            $db->check_slot_availability($date, $time, $duration) : true;
        
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
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => $e->getMessage(),
            'code' => $e->getCode() ?: 400
        ]);
    }
}

// Add the robust slot availability handler
add_action('wp_ajax_check_slot_availability', 'massage_booking_robust_slot_availability');
add_action('wp_ajax_nopriv_check_slot_availability', 'massage_booking_robust_slot_availability');