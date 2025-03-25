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
 * Enqueue all required scripts and styles for the booking form
 * Ensures proper loading order and dependencies
 */
function massage_booking_enqueue_scripts() {
    // Only enqueue on pages with the booking form
    if (!is_page_template('page-booking.php') && 
        !has_shortcode(get_the_content(), 'massage_booking_form') && 
        !is_page(get_option('massage_booking_page_id'))) {
        return;
    }

    // Get plugin version for cache busting
    $version = defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1';
    
    // 1. Enqueue the base styles
    wp_enqueue_style(
        'massage-booking-form-style', 
        MASSAGE_BOOKING_PLUGIN_URL . 'public/css/booking-form.css',
        array(),
        $version
    );
    
    // 2. Enqueue cross-browser styles with higher priority
    wp_enqueue_style(
        'massage-booking-compatibility-styles',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/css/cross-browser-styles.css',
        array('massage-booking-form-style'),
        $version
    );
    
    // 3. Ensure jQuery is loaded
    wp_enqueue_script('jquery');
    
    // 4. Enqueue the base form script
    wp_enqueue_script(
        'massage-booking-form-script',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/booking-form.js',
        array('jquery'),
        $version,
        true
    );
    
    // 5. Enqueue the minified form script as fallback
    wp_enqueue_script(
        'massage-booking-form-min-script',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/booking-form-min.js',
        array('jquery', 'massage-booking-form-script'),
        $version,
        true
    );
    
    // 6. Enqueue API connector
    wp_enqueue_script(
        'massage-booking-api-connector',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-connector.js',
        array('jquery', 'massage-booking-form-script'),
        $version,
        true
    );
    
    // 7. Pass WordPress data to JavaScript
    wp_localize_script('massage-booking-api-connector', 'massageBookingAPI', array(
        'restUrl' => esc_url_raw(rest_url('massage-booking/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'siteUrl' => get_site_url(),
        'isLoggedIn' => is_user_logged_in() ? 'yes' : 'no',
        'version' => $version
    ));
    
    // 8. Enqueue jQuery form handler
    wp_enqueue_script(
        'massage-booking-jquery-form',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/jquery-form-handler.js',
        array('jquery', 'massage-booking-api-connector'),
        $version,
        true
    );
    
    // 9. Enqueue cross-browser compatibility script
    wp_enqueue_script(
        'massage-booking-compatibility',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/cross-browser-fix.js',
        array('jquery', 'massage-booking-form-script', 'massage-booking-api-connector'),
        $version,
        true
    );
    
    // 10. Enqueue API patch with highest priority to ensure it loads last
    wp_enqueue_script(
        'massage-booking-api-patch',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-connector-patch.js',
        array('jquery', 'massage-booking-api-connector', 'massage-booking-compatibility'),
        $version . '.' . time(), // Force reload to avoid caching
        true
    );
    
    // 11. Enqueue API troubleshooter in debug mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_enqueue_script(
            'massage-booking-troubleshooter',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-troubleshooter.js',
            array('jquery', 'massage-booking-api-patch'),
            $version . '.' . time(),
            true
        );
    }
    
    // 12. Optional: Add inline script to ensure massageBookingAPI is available
    wp_add_inline_script('massage-booking-api-connector', '
        // Ensure API data is available
        if (typeof massageBookingAPI === "undefined") {
            console.warn("massageBookingAPI not initialized by WordPress, creating fallback");
            window.massageBookingAPI = {
                restUrl: "/wp-json/massage-booking/v1/",
                nonce: "",
                ajaxUrl: "/wp-admin/admin-ajax.php",
                siteUrl: "' . esc_url(get_site_url()) . '",
                isLoggedIn: "' . (is_user_logged_in() ? 'yes' : 'no') . '",
                version: "' . esc_js($version) . '",
                isFallback: true
            };
        }
    ');
}
add_action('wp_enqueue_scripts', 'massage_booking_enqueue_scripts', 20);

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
 * Add nonce generation for booking operations
 */
function massage_booking_generate_nonce() {
    if (!isset($_POST['booking_action'])) {
        wp_send_json_error(['message' => 'Missing booking action']);
    }
    
    $action = sanitize_text_field($_POST['booking_action']);
    $nonce = wp_create_nonce('massage_booking_' . $action);
    
    wp_send_json_success(['nonce' => $nonce]);
}
add_action('wp_ajax_generate_booking_nonce', 'massage_booking_generate_nonce');
add_action('wp_ajax_nopriv_generate_booking_nonce', 'massage_booking_generate_nonce');

/**
 * Diagnostic function to check if API is accessible
 */
function massage_booking_test_api() {
    // Verify nonce
    check_ajax_referer('massage_booking_test', 'nonce');
    
    $result = [
        'success' => true,
        'timestamp' => current_time('mysql'),
        'plugin_version' => defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : 'unknown',
        'wp_version' => get_bloginfo('version'),
        'site_url' => get_site_url(),
        'is_rest_enabled' => massage_booking_is_rest_enabled()
    ];
    
    wp_send_json_success($result);
}
add_action('wp_ajax_massage_booking_test_api', 'massage_booking_test_api');
add_action('wp_ajax_nopriv_massage_booking_test_api', 'massage_booking_test_api');

/**
 * Check if REST API is enabled and accessible
 */
function massage_booking_is_rest_enabled() {
    $url = get_rest_url(null, 'massage-booking/v1/settings');
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    return $status_code < 500; // Even a 404 means REST is working, just endpoint missing
}

function massage_booking_create_api_troubleshooter() {
    // Only in debug mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $troubleshooter_path = MASSAGE_BOOKING_PLUGIN_DIR . 'public/js/api-troubleshooter.js';
    
    // Create file if it doesn't exist
    if (!file_exists($troubleshooter_path)) {
        // Troubleshooter code content from artifact
        $troubleshooter_content = <<<'EOT'
/**
 * Massage Booking API Troubleshooter
 * 
 * This script diagnoses issues with the API connector and attempts to fix
 * common problems. Add this to your page right before the closing </body> tag.
 */

(function() {
    'use strict';
    
    // Wait for page to fully load
    window.addEventListener('load', function() {
        console.log('API Troubleshooter running...');
        
        // Check if we're on a booking page
        if (!document.querySelector('.massage-booking-container') && 
            !document.querySelector('#appointmentForm') &&
            !document.querySelector('form.booking-form')) {
            console.log('Not on a booking page. Troubleshooter not needed.');
            return;
        }
        
        // Run diagnostics after a short delay
        setTimeout(runDiagnostics, 1000);
    });
    
    /**
     * Run diagnostics on the API and form
     */
    function runDiagnostics() {
        console.log('Running API diagnostics...');
        
        const issues = [];
        
        // Check for form
        const form = document.getElementById('appointmentForm');
        if (!form) {
            issues.push('Form with ID "appointmentForm" not found.');
            
            // Try to find and fix the form
            const possibleForms = document.querySelectorAll(
                'form.booking-form, ' + 
                '.massage-booking-container form, ' + 
                'form'
            );
            
            if (possibleForms.length > 0) {
                issues.push('Found a form without ID. Fixing...');
                possibleForms[0].id = 'appointmentForm';
            } else {
                issues.push('No forms found on the page!');
            }
        }
        
        // Check for massageBookingAPI object
        if (typeof massageBookingAPI === 'undefined') {
            issues.push('massageBookingAPI object is missing.');
            
            // Check if wp_localize_script was called
            if (document.body.textContent.includes('massage_booking_api_connector')) {
                issues.push('wp_localize_script may have failed.');
            }
            
            // Create fallback API configuration
            window.massageBookingAPI = {
                restUrl: '/wp-json/massage-booking/v1/',
                nonce: '',
                ajaxUrl: '/wp-admin/admin-ajax.php',
                isFallback: true
            };
            
            issues.push('Created fallback API configuration.');
        } else {
            // Check API object properties
            if (!massageBookingAPI.restUrl) {
                issues.push('massageBookingAPI.restUrl is missing.');
                massageBookingAPI.restUrl = '/wp-json/massage-booking/v1/';
            }
            
            if (!massageBookingAPI.ajaxUrl) {
                issues.push('massageBookingAPI.ajaxUrl is missing.');
                massageBookingAPI.ajaxUrl = '/wp-admin/admin-ajax.php';
            }
        }
        
        // Check for jQuery
        if (typeof jQuery === 'undefined') {
            issues.push('jQuery is not loaded.');
        }
        
        // Check for essential functions
        const requiredFunctions = [
            'fetchAvailableTimeSlots',
            'updateSummary',
            'loadSettings'
        ];
        
        requiredFunctions.forEach(function(funcName) {
            if (typeof window[funcName] !== 'function') {
                issues.push(`Function ${funcName} is missing.`);
                
                // Create placeholder for missing functions
                window[funcName] = window[funcName] || function() {
                    console.warn(`Placeholder for ${funcName} called.`);
                    return Promise.resolve({});
                };
            }
        });
        
        // Log diagnostic results
        if (issues.length > 0) {
            console.warn('API Troubleshooter found issues:');
            issues.forEach(issue => console.warn('- ' + issue));
            
            // Add visible message for admins
            if (isAdmin()) {
                showAdminMessage(issues);
            }
            
            // Try to fix the issues
            fixIssues();
        } else {
            console.log('API Troubleshooter: No issues found.');
        }
    }
    
    /**
     * Attempt to fix identified issues
     */
    function fixIssues() {
        console.log('Attempting to fix issues...');
        
        // Ensure the form has the correct ID
        const form = document.getElementById('appointmentForm') || 
                     document.querySelector('form.booking-form') || 
                     document.querySelector('.massage-booking-container form') ||
                     document.querySelector('form');
                     
        if (form && form.id !== 'appointmentForm') {
            form.id = 'appointmentForm';
            console.log('Form ID corrected to "appointmentForm"');
        }
        
        // If API connector is missing but jQuery is available, try to initialize
        if (typeof jQuery !== 'undefined') {
            // Simulate the API connector initialization
            if (!window._apiConnectorInitialized && form) {
                window._apiConnectorInitialized = true;
                
                // Try to load settings
                if (typeof window.loadSettings === 'function') {
                    window.loadSettings().catch(error => {
                        console.warn('Failed to load settings:', error);
                    });
                }
                
                console.log('Initialized API connector manually');
            }
        }
        
        // If date picker exists, make sure it has change event handler
        const datePicker = document.getElementById('appointmentDate');
        if (datePicker) {
            datePicker.removeEventListener('change', dateChangeHandler);
            datePicker.addEventListener('change', dateChangeHandler);
            console.log('Date picker event handler fixed');
        }
    }
    
    /**
     * Date change event handler
     */
    function dateChangeHandler() {
        if (this.value && typeof window.fetchAvailableTimeSlots === 'function') {
            const duration = document.querySelector('input[name="duration"]:checked')?.value || '60';
            window.fetchAvailableTimeSlots(this.value, duration).catch(error => {
                console.warn('Error fetching time slots:', error);
            });
        }
    }
    
    /**
     * Check if current user appears to be an admin
     */
    function isAdmin() {
        return document.body.classList.contains('logged-in') && 
               (document.body.classList.contains('admin-bar') || 
                document.getElementById('wpadminbar'));
    }
    
    /**
     * Show admin message about issues
     */
    function showAdminMessage(issues) {
        // Create message container
        const messageContainer = document.createElement('div');
        messageContainer.style.position = 'fixed';
        messageContainer.style.top = '32px';
        messageContainer.style.right = '10px';
        messageContainer.style.padding = '15px';
        messageContainer.style.background = '#f8d7da';
        messageContainer.style.border = '1px solid #f5c6cb';
        messageContainer.style.borderRadius = '4px';
        messageContainer.style.maxWidth = '300px';
        messageContainer.style.zIndex = '9999';
        messageContainer.style.fontSize = '12px';
        
        // Add message content
        messageContainer.innerHTML = `
            <h4 style="margin-top:0;">Massage Booking API Issues</h4>
            <p>The following issues were detected:</p>
            <ul style="padding-left:20px;margin-bottom:10px;">
                ${issues.map(issue => `<li>${issue}</li>`).join('')}
            </ul>
            <p>Auto-fixing has been attempted. Check console for details.</p>
            <button id="dismiss-api-message" style="padding:5px 10px;margin-top:10px;">Dismiss</button>
        `;
        
        // Add to page
        document.body.appendChild(messageContainer);
        
        // Add dismiss handler
        document.getElementById('dismiss-api-message').addEventListener('click', function() {
            messageContainer.remove();
        });
        
        // Auto-dismiss after 30 seconds
        setTimeout(function() {
            if (document.body.contains(messageContainer)) {
                messageContainer.remove();
            }
        }, 30000);
    }
})();
EOT;

        // Save the file
        file_put_contents($troubleshooter_path, $troubleshooter_content);
    }
    
    // Check if API patch file needs to be updated
    $patch_path = MASSAGE_BOOKING_PLUGIN_DIR . 'public/js/api-connector-patch.js';
    if (!file_exists($patch_path) || filesize($patch_path) < 4000) {
        // Patch file content from artifact
        $patch_content = <<<'EOT'
/**
 * API Connector Compatibility Patch
 * 
 * This patch addresses issues with API connector initialization and
 * event handling across different browsers.
 * 
 * How to use: Include this script after api-connector.js but before the
 * closing </body> tag on your booking page.
 */

(function() {
    'use strict';
    
    // Check for proper initialization on page load
    window.addEventListener('load', function() {
        // Only run on booking pages
        if (!document.querySelector('.massage-booking-container') && 
            !document.querySelector('.booking-container') && 
            !document.getElementById('appointmentForm')) {
            return;
        }
        
        console.log('API Connector patch loaded');
        
        // Listen for custom events from the cross-browser compatibility script
        document.addEventListener('mb_form_initialized', function(event) {
            console.log('Form initialized event received');
            initializeApi(event.detail.form);
        });
        
        document.addEventListener('mb_reinitialize_api', function(event) {
            console.log('API reinitialization requested');
            initializeApi(event.detail.form);
        });
        
        // Check if API is properly initialized
        checkApiInitialization();
    });
    
    /**
     * Initialize the API connector with the form
     */
    function initializeApi(form) {
        if (!form) return;
        
        // Only initialize if not already done
        if (window._apiConnectorInitialized) return;
        
        // Check if WordPress API data exists
        if (typeof massageBookingAPI === 'undefined') {
            console.error('WordPress API data not available');
            
            // Try to create a fallback
            window.massageBookingAPI = window.massageBookingAPI || {
                restUrl: '/wp-json/massage-booking/v1/',
                nonce: '',
                ajaxUrl: '/wp-admin/admin-ajax.php'
            };
            
            console.log('Created fallback API configuration');
            return;
        }
        
        // Mark as initialized
        window._apiConnectorInitialized = true;
        
        console.log('API connector manually initialized');
        
        // Attempt to load settings if the function exists
        if (typeof window.loadSettings === 'function') {
            window.loadSettings()
                .then(function() {
                    console.log('Settings loaded successfully');
                    
                    // Attempt to restore any form state
                    restoreFormState();
                })
                .catch(function(error) {
                    console.error('Failed to load settings:', error);
                });
        }
    }
    
    /**
     * Check if the API is properly initialized
     */
    function checkApiInitialization() {
        // Verify essential API functions
        setTimeout(function() {
            if (!window._apiConnectorInitialized) {
                // Check for form
                const form = document.getElementById('appointmentForm');
                if (!form) {
                    console.warn('No form found with ID appointmentForm');
                    
                    // Try to find and set the form ID
                    const possibleForms = document.querySelectorAll(
                        'form.booking-form, ' + 
                        '.massage-booking-container form, ' + 
                        '.booking-form-container form, ' + 
                        'form'
                    );
                    
                    if (possibleForms.length > 0) {
                        possibleForms[0].id = 'appointmentForm';
                        console.log('Form ID assigned to:', possibleForms[0]);
                        initializeApi(possibleForms[0]);
                    }
                    
                    return;
                }
                
                // Try to re-initialize
                if (typeof jQuery !== 'undefined') {
                    console.log('Attempting auto-initialization of API connector');
                    initializeApi(form);
                }
            }
            
            // Patch the time slot fetching function if it's still the placeholder
            if (typeof window.fetchAvailableTimeSlots === 'function' && 
                window.fetchAvailableTimeSlots.toString().includes('placeholder')) {
                console.log('Patching fetchAvailableTimeSlots function');
                patchTimeSlotFunction();
            }
        }, 1500);
    }
    
    /**
     * Patch the time slot function with a more resilient implementation
     */
    function patchTimeSlotFunction() {
        // Only patch if necessary
        if (window._timeSlotFunctionPatched) return;
        
        // Override with our implementation
        window.fetchAvailableTimeSlots = async function(date, duration) {
            console.log(`Patched function fetching time slots for ${date} with duration ${duration}`);
            
            const slotsContainer = document.getElementById('timeSlots');
            if (!slotsContainer) return { available: false, slots: [] };
            
            slotsContainer.innerHTML = '<p>Loading available times...</p>';
            slotsContainer.classList.add('loading');
            
            try {
                // Try to make the API request
                if (typeof massageBookingAPI === 'undefined') {
                    throw new Error('API configuration not available');
                }
                
                const timeSlotsUrl = `${massageBookingAPI.restUrl}available-slots?date=${encodeURIComponent(date)}&duration=${encodeURIComponent(duration)}`;
                
                const response = await fetch(timeSlotsUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': massageBookingAPI.nonce,
                        'Accept': 'application/json'
                    }
                });
                
                // Check for response issues
                if (!response.ok) {
                    throw new Error(`HTTP error: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    // Try to extract JSON from potential HTML response
                    const text = await response.text();
                    const jsonMatch = text.match(/\{.*\}/s);
                    if (jsonMatch) {
                        data = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new Error('Invalid JSON response');
                    }
                }
                
                // Update the UI
                updateTimeSlots(slotsContainer, data);
                return data;
            } catch (error) {
                console.error('Error fetching time slots:', error);
                slotsContainer.classList.remove('loading');
                slotsContainer.innerHTML = `
                    <p>Error loading available times. Please try again.</p>
                    <button type="button" class="retry-button">Retry</button>
                `;
                
                // Add retry functionality
                const retryButton = slotsContainer.querySelector('.retry-button');
                if (retryButton) {
                    retryButton.addEventListener('click', function() {
                        window.fetchAvailableTimeSlots(date, duration);
                    });
                }
                
                return { available: false, slots: [] };
            }
        };
        
        window._timeSlotFunctionPatched = true;
    }
    
    /**
     * Update time slots display
     */
    function updateTimeSlots(container, data) {
        container.innerHTML = '';
        container.classList.remove('loading');
        
        // Check if slots are available
        if (!data || !data.available || !data.slots || data.slots.length === 0) {
            container.innerHTML = '<p>No appointments available on this date.</p>';
            return;
        }
        
        // Create time slot elements
        data.slots.forEach(slot => {
            const slotElement = document.createElement('div');
            slotElement.className = 'time-slot';
            slotElement.setAttribute('data-time', slot.startTime);
            slotElement.setAttribute('data-end-time', slot.endTime);
            slotElement.setAttribute('role', 'option');
            slotElement.setAttribute('aria-selected', 'false');
            slotElement.textContent = slot.displayTime;
            
            // Add click event to select this time slot
            slotElement.addEventListener('click', function() {
                // Clear previous selections
                document.querySelectorAll('.time-slot').forEach(s => {
                    s.classList.remove('selected');
                    s.setAttribute('aria-selected', 'false');
                });
                
                // Select this slot
                this.classList.add('selected');
                this.setAttribute('aria-selected', 'true');
                
                // Update booking summary
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
                
                // Save to session storage
                saveTimeSlotSelection(slot.startTime, slot.endTime);
            });
            
            // Make the slot keyboard accessible
            slotElement.setAttribute('tabindex', '0');
            slotElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
            
            container.appendChild(slotElement);
        });
    }
    
    /**
     * Save time slot selection to session storage
     */
    function saveTimeSlotSelection(startTime, endTime) {
        try {
            const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
            formData.selectedTimeSlot = startTime;
            formData.selectedEndTime = endTime;
            sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
        } catch (e) {
            console.warn('Could not save to session storage:', e);
        }
    }
    
    /**
     * Restore form state from session storage
     */
    function restoreFormState() {
        try {
            const savedData = sessionStorage.getItem('massageBookingFormData');
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Restore date if it was selected
            if (formData.appointmentDate) {
                const dateInput = document.getElementById('appointmentDate');
                if (dateInput) {
                    dateInput.value = formData.appointmentDate;
                    
                    // Trigger date input event to load time slots
                    const event = new Event('change', { bubbles: true });
                    dateInput.dispatchEvent(event);
                }
            }
            
            // Restore other fields will be handled by the main script
        } catch (e) {
            console.warn('Failed to restore form state:', e);
        }
    }
})();
EOT;

        // Save the file
        file_put_contents($patch_path, $patch_content);
    }
}
add_action('plugins_loaded', 'massage_booking_create_api_troubleshooter');

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
