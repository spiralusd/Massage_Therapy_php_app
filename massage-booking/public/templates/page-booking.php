<?php
/**
 * Template Name: Massage Booking Form
 *
 * A custom page template that displays the massage booking form
 * with no WordPress header or footer.
 */

// Exit if accessed directly or from admin
if (!defined('WPINC') || is_admin()) {
    exit;
}

// Force full output buffering to capture and modify entire page output
ob_start();

// Disable admin bar and theme template loading
add_filter('show_admin_bar', '__return_false');
remove_action('wp_head', '_admin_bar_bump_cb');

// Completely override template loading
add_filter('template_include', function($template) {
    return get_stylesheet_directory() . '/page-booking.php';
});

// Prevent theme's header and footer from loading
add_action('get_header', function() {
    remove_all_actions('wp_head');
}, 99);

add_action('get_footer', function() {
    remove_all_actions('wp_footer');
}, 99);

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    
    <!-- Enhanced debugging for form submission issues -->
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
    <script>
        console.log('Initializing booking form debugging...');
        window.formSubmissionDebug = {
            events: [],
            log: function(event, data) {
                this.events.push({
                    time: new Date(),
                    event: event,
                    data: data
                });
                console.log('Form Debug:', event, data);
            }
        };
    </script>
    <?php endif; ?>
    
    <?php wp_head(); ?>
    
    <style>
        /* Critical CSS for the booking form */
        :root {
            --primary-color: #4a6fa5;
            --primary-light: rgba(74, 111, 165, 0.1);
            --primary-dark: #3a5a84;
            --secondary-color: #98c1d9;
            --accent-color: #ee6c4d;
            --light-color: #f8f9fa;
            --dark-color: #293241;
            --error-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .booking-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .booking-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .booking-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        
        .booking-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* Hide WordPress elements */
        #wpadminbar, 
        #masthead, 
        #colophon, 
        .site-header, 
        .site-footer, 
        .main-navigation,
        .entry-header,
        .entry-footer,
        header.site-header,
        footer.site-footer,
        nav.main-navigation,
        nav.secondary-navigation,
        aside.widget-area,
        .wp-site-blocks {
            display: none !important;
        }
        
        /* Form loading overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Ensure core WordPress elements are visible and fixed */
        .form-group {
            margin-bottom: 25px !important; 
        }
        
        label {
            display: block !important;
            margin-bottom: 8px !important;
            font-weight: 600 !important;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        textarea,
        select {
            width: 100% !important;
            padding: 12px !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            font-size: 16px !important;
            box-sizing: border-box !important;
        }
    </style>
    
    <?php
    // Check if any scripts or styles are missing and add them
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    
    if (!wp_style_is('massage-booking-form-style', 'enqueued')) {
        wp_enqueue_style(
            'massage-booking-form-style',
            plugin_dir_url(dirname(__FILE__)) . '/public/css/booking-form.css',
            array(),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1'
        );
    }
    
    // Check for form API connectivity scripts
    if (!wp_script_is('massage-booking-form-script', 'enqueued')) {
        wp_enqueue_script(
            'massage-booking-form-script',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/booking-form.js',
            array('jquery'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            true
        );
    }
    
    if (!wp_script_is('massage-booking-form-initializer', 'enqueued')) {
        wp_enqueue_script(
            'massage-booking-form-initializer',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/form-initializer.js',
            array('jquery'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            true
        );
    }
    
    if (!wp_script_is('massage-booking-api-connector', 'enqueued')) {
        wp_enqueue_script(
            'massage-booking-api-connector',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/api-connector.js',
            array('jquery', 'massage-booking-form-script'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            true
        );
        
        // Pass WordPress data to JavaScript - critical for form submission
        wp_localize_script('massage-booking-api-connector', 'massageBookingAPI', array(
            'restUrl' => esc_url_raw(rest_url('massage-booking/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'siteUrl' => get_site_url(),
            'isLoggedIn' => is_user_logged_in() ? 'yes' : 'no',
            'version' => defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            'formAction' => 'massage_booking_create_appointment'
        ));
    }
    
    // Add cross-browser fix script if not already loaded
    if (!wp_script_is('massage-booking-cross-browser-fix', 'enqueued')) {
        wp_enqueue_script(
            'massage-booking-cross-browser-fix',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/cross-browser-fix.js',
            array('jquery', 'massage-booking-form-script', 'massage-booking-api-connector'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            true
        );
    }
    
    // Add API patch script if not already loaded
    if (!wp_script_is('massage-booking-api-connector-patch', 'enqueued')) {
        wp_enqueue_script(
            'massage-booking-api-connector-patch',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/api-connector-patch.js',
            array('jquery', 'massage-booking-api-connector'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : '1.1.1',
            true
        );
    }
    
    // In debug mode, add the troubleshooter
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_enqueue_script(
            'massage-booking-troubleshooter',
            plugin_dir_url(dirname(__FILE__)) . '/public/js/api-troubleshooter.js',
            array('jquery', 'massage-booking-api-connector'),
            defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : time(),
            true
        );
    }
    ?>
</head>

<body <?php body_class('massage-booking-template'); ?>>
    <?php wp_body_open(); ?>
    
    <div class="booking-container">
        <div class="booking-header">
            <h1><?php echo get_option('massage_booking_business_name', 'Massage Therapy Appointment Booking'); ?></h1>
            <p>Schedule your appointment below</p>
        </div>
        
        <?php 
        // Debug information for admins
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')): 
        ?>
        <div style="margin-bottom: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; font-size: 12px;">
            <h3>Form Loading Debug (Admin Only)</h3>
            <ul>
                <li>jQuery: <?php echo wp_script_is('jquery', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>CSS: <?php echo wp_style_is('massage-booking-form-style', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>Form JS: <?php echo wp_script_is('massage-booking-form-script', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>API Connector: <?php echo wp_script_is('massage-booking-api-connector', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>Form Function: <?php echo function_exists('massage_booking_display_form') ? 'Yes ✅' : 'No ❌'; ?></li>
                <li>REST API Base: <?php echo esc_url_raw(rest_url('massage-booking/v1/')); ?></li>
                <li>AJAX URL: <?php echo admin_url('admin-ajax.php'); ?></li>
                <li>Thank You Page: <?php echo get_option('massage_booking_thank_you_page_id') ? 'Set ✅ (ID: ' . get_option('massage_booking_thank_you_page_id') . ')' : 'Not Set ❌'; ?></li>
            </ul>
        </div>
        <?php endif; ?>
        
        <div id="bookingFormContainer">
            <?php
            // Load the booking form
            if (function_exists('massage_booking_display_form')) {
                echo massage_booking_display_form();
            } else {
                // Try to include the booking form file if it exists
                $form_path = plugin_dir_path(dirname(__FILE__)) . 'public/booking-form.php';
                if (file_exists($form_path)) {
                    include_once($form_path);
                    if (function_exists('massage_booking_display_form')) {
                        echo massage_booking_display_form();
                    } else {
                        echo '<div class="form-error-message">';
                        echo '<p>Error: The booking form functionality could not be loaded.</p>';
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            echo '<p>Debug info: The <code>massage_booking_display_form</code> function is missing. ';
                            echo 'Please check if the plugin is properly activated and the file <code>public/booking-form.php</code> is being loaded.</p>';
                        }
                        
                        echo '</div>';
                    }
                } else {
                    echo '<div class="form-error-message">';
                    echo '<p>Error: The booking form file could not be found.</p>';
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo '<p>Debug info: Could not find the file at <code>' . esc_html($form_path) . '</code>. ';
                        echo 'Please check if the plugin is properly installed and activated.</p>';
                    }
                    
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="booking-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo get_option('massage_booking_business_name', 'Massage Therapy Practice'); ?>. All rights reserved.</p>
            <p>This booking system is HIPAA compliant. Your information is secure and encrypted.</p>
        </div>
    </div>
    
    <?php
    // Add form submission debugging for admins
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')):
    ?>
    <div id="form-debug-panel" style="position: fixed; bottom: 0; right: 0; background: #f5f5f5; border: 1px solid #ccc; padding: 10px; max-width: 400px; max-height: 300px; overflow: auto; z-index: 9999; display: none;">
        <h4>Form Submission Debug</h4>
        <div id="debug-log"></div>
        <button id="close-debug">Close</button>
    </div>
    <?php endif; ?>
    
    <?php wp_footer(); ?>
    
    <script>
    jQuery(document).ready(function($) {
        console.log("DOM ready - Initializing booking form.");
        
        <?php if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')): ?>
        // Debug panel for admins
        $('#bookingFormContainer').append('<button id="show-debug" style="position: fixed; bottom: 0; right: 0; z-index: 9998;">Debug</button>');
        $('#show-debug').on('click', function() {
            $('#form-debug-panel').toggle();
            updateDebugPanel();
        });
        $('#close-debug').on('click', function() {
            $('#form-debug-panel').hide();
        });
        
        function updateDebugPanel() {
            var debugLog = $('#debug-log');
            debugLog.empty();
            
            // Display API info
            debugLog.append('<h5>API Information</h5>');
            if (typeof massageBookingAPI !== 'undefined') {
                debugLog.append('<p>massageBookingAPI available ✅</p>');
                for (var key in massageBookingAPI) {
                    var value = key === 'nonce' ? '****' : massageBookingAPI[key];
                    debugLog.append('<p><strong>' + key + ':</strong> ' + value + '</p>');
                }
            } else {
                debugLog.append('<p>massageBookingAPI not available ❌</p>');
            }
            
            // Display available functions
            debugLog.append('<h5>Functions</h5>');
            var functions = ['loadSettings', 'fetchAvailableTimeSlots', 'updateSummary'];
            functions.forEach(function(funcName) {
                var available = typeof window[funcName] === 'function';
                debugLog.append('<p>' + funcName + ': ' + (available ? '✅' : '❌') + '</p>');
            });
            
            // Form events
            if (window.formSubmissionDebug) {
                debugLog.append('<h5>Events</h5>');
                window.formSubmissionDebug.events.forEach(function(event) {
                    debugLog.append('<p>' + event.time.toLocaleTimeString() + ': ' + event.event + '</p>');
                });
            }
        }
        
        // Monitor form submission
        $('#appointmentForm').on('submit', function(e) {
            if (window.formSubmissionDebug) {
                window.formSubmissionDebug.log('Form submitted', {
                    form: this,
                    formData: $(this).serialize()
                });
            }
        });
        <?php endif; ?>
        
        // Hide WordPress elements
        $('header, footer, .site-header, .site-footer, #masthead, #colophon, #wpadminbar').hide();
        
        // Initialize radio buttons with better handling
        $('.radio-option').each(function() {
            // If the radio button is checked initially, add selected class
            if ($(this).find('input[type="radio"]').is(':checked')) {
                $(this).addClass('selected');
            }
            
            $(this).on('click', function() {
                // Find all radio options and remove selected class
                $('.radio-option').removeClass('selected');
                
                // Add selected class to clicked option
                $(this).addClass('selected');
                
                // Check the radio input
                $(this).find('input[type="radio"]').prop('checked', true);
                
                // Update booking summary if it exists
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
                
                // If date is selected, update time slots
                var selectedDate = $('#appointmentDate').val();
                if (selectedDate) {
                    var duration = $(this).find('input[type="radio"]').val();
                    if (typeof window.fetchAvailableTimeSlots === 'function') {
                        window.fetchAvailableTimeSlots(selectedDate, duration);
                    }
                }
            });
        });
        
        // Initialize checkbox options with better handling
        $('.checkbox-option').each(function() {
            // If the checkbox is checked initially, add selected class
            if ($(this).find('input[type="checkbox"]').is(':checked')) {
                $(this).addClass('selected');
            }
            
            $(this).on('click', function() {
                // Toggle selected class
                $(this).toggleClass('selected');
                
                // Toggle checkbox
                var checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));
                
                // Update booking summary if it exists
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
            });
        });
        
        // Initialize date picker to trigger time slot updates
        $('#appointmentDate').on('change', function() {
            var selectedDate = $(this).val();
            if (selectedDate) {
                var duration = $('input[name="duration"]:checked').val();
                if (typeof window.fetchAvailableTimeSlots === 'function') {
                    window.fetchAvailableTimeSlots(selectedDate, duration);
                } else {
                    console.error('fetchAvailableTimeSlots function not available');
                }
            }
        });
        
        // Set minimum date to today
        const today = new Date();
        $('#appointmentDate').attr('min', today.toISOString().split('T')[0]);
        
        // Load settings when page loads (if available)
        if (typeof window.loadSettings === 'function') {
            window.loadSettings().catch(error => {
                console.error('Failed to load settings:', error);
            });
        } else {
            console.error('loadSettings function not available');
            // Try to initialize API connector manually
            if (typeof window._apiConnectorInitialized === 'undefined') {
                console.log('Manually initializing API connector');
                window._apiConnectorInitialized = true;
                // Dispatch form init event
                var event = new CustomEvent('form_initialized', { 
                    detail: { form: document.getElementById('appointmentForm') } 
                });
                document.dispatchEvent(event);
            }
        }
        
        // Ensure form submission is properly captured
        $('#appointmentForm').on('submit', function(e) {
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            console.log('Form submitted');
            if (window.formSubmissionDebug) {
                window.formSubmissionDebug.log('Native form submit event captured');
            }
            <?php endif; ?>
            
            // The api-connector.js should handle the submission
            // This is just a fallback in case it fails
            if (typeof window.validateForm === 'function') {
                if (!window.validateForm()) {
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    console.log('Form validation failed');
                    if (window.formSubmissionDebug) {
                        window.formSubmissionDebug.log('Form validation failed');
                    }
                    <?php endif; ?>
                    return false;
                }
            }
        });
    });
    </script>
</body>
</html><?php
// Output the buffer
echo ob_get_clean();
// Stop execution to prevent the theme's footer from loading
exit;
?>