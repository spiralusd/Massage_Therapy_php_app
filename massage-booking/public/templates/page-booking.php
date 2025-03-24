<?php
/**
 * Template Name: Massage Booking Form
 *
 * A custom page template that displays the massage booking form.
 * This is a fixed version that ensures content is properly displayed
 * and includes debug tools for development environments.
 */

// Exit if accessed directly or from admin
if (!defined('WPINC') || is_admin()) {
    exit;
}

// Force full output buffering to capture and modify entire page output
ob_start();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        /* Critical CSS for the booking form */
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #98c1d9;
            --accent-color: #ee6c4d;
            --light-color: #f8f9fa;
            --dark-color: #293241;
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
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .radio-group, .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .radio-option, .checkbox-option {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1 1 200px;
        }
        
        .radio-option:hover, .checkbox-option:hover {
            border-color: var(--primary-color);
        }
        
        .radio-option.selected, .checkbox-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(74, 111, 165, 0.1);
        }
        
        .radio-option input, .checkbox-option input {
            margin-right: 10px;
        }
        
        .price {
            font-weight: bold;
            color: var(--accent-color);
            margin-left: 5px;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: block;
            margin: 30px auto 0;
        }
        
        button:hover {
            background-color: #3a5a84;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .time-slot {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            border-color: var(--primary-color);
        }
        
        .time-slot.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .time-slot.unavailable {
            background-color: #f1f1f1;
            color: #999;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .privacy-notice {
            margin-top: 30px;
            font-size: 14px;
            color: #666;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
        }
        
        .summary {
            margin-top: 20px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .summary.visible {
            display: block;
        }
        
        /* Form error message */
        .form-error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
        
        /* Loading overlay */
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
        
        /* Debug panel styling */
        #debug-controls {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        
        #debugInfo {
            display: none;
            margin-top: 10px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            background: #fff;
            padding: 10px;
            border: 1px solid #ddd;
        }
        
        @media (max-width: 768px) {
            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .radio-group, .checkbox-group {
                flex-direction: column;
            }
            
            .radio-option, .checkbox-option {
                flex: 1 1 100%;
            }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            MASSAGE_BOOKING_PLUGIN_URL . 'public/css/booking-form.css',
            array(),
            MASSAGE_BOOKING_VERSION
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
            <h3>Resource Loading Debug (Admin Only)</h3>
            <ul>
                <li>jQuery: <?php echo wp_script_is('jquery', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>CSS: <?php echo wp_style_is('massage-booking-form-style', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>Form JS: <?php echo wp_script_is('massage-booking-form-script', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>API Connector: <?php echo wp_script_is('massage-booking-api-connector', 'enqueued') ? 'Loaded ✅' : 'Not Loaded ❌'; ?></li>
                <li>Function exists: <?php echo function_exists('massage_booking_display_form') ? 'Yes ✅' : 'No ❌'; ?></li>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php
        // Load the booking form
        if (function_exists('massage_booking_display_form')) {
            massage_booking_display_form();
        } else {
            echo '<div class="form-error-message">';
            echo '<p>Error: The booking form functionality is not available.</p>';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<p>Debug info: The <code>massage_booking_display_form</code> function is missing. ';
                echo 'Please check if the plugin is properly activated and the file <code>public/booking-form.php</code> is being loaded.</p>';
            }
            
            echo '</div>';
        }
        ?>
        
        <div class="booking-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo get_option('massage_booking_business_name', 'Massage Therapy Practice'); ?>. All rights reserved.</p>
            <p>This booking system is HIPAA compliant. Your information is secure and encrypted.</p>
        </div>
    </div>
    
    <?php
    // Debug toggle (only in development)
    if (defined('WP_DEBUG') && WP_DEBUG):
    ?>
    <div id="debug-controls">
        <button id="toggleDebug" class="button">Toggle Debug Mode</button>
        <div id="debugInfo">
            <h4>Debug Information</h4>
            <div id="debugContent"></div>
        </div>
    </div>
    
    <script>
        // Debug mode toggle script
        document.addEventListener('DOMContentLoaded', function() {
            // Log initial form status to console
            console.log('DOM loaded. Form exists:', !!document.getElementById('appointmentForm'));
            
            // Check if API connector initialized properly
            if (typeof massageBookingAPI === 'undefined') {
                console.error('massageBookingAPI data is not available');
            } else {
                console.log('massageBookingAPI is available:', massageBookingAPI);
            }
            
            const toggleBtn = document.getElementById('toggleDebug');
            const debugInfo = document.getElementById('debugInfo');
            const debugContent = document.getElementById('debugContent');
            
            let debugMode = false;
            
            // Toggle debug panel
            toggleBtn.addEventListener('click', function() {
                debugMode = !debugMode;
                debugInfo.style.display = debugMode ? 'block' : 'none';
                toggleBtn.textContent = debugMode ? 'Hide Debug Info' : 'Toggle Debug Mode';
                
                if (debugMode) {
                    // Collect debug information
                    const formState = {
                        fields: {},
                        options: {},
                        timeSlots: {},
                        scripts: {
                            'jQuery': typeof jQuery !== 'undefined',
                            'massageBookingAPI': typeof massageBookingAPI !== 'undefined',
                            'fetchAvailableTimeSlots': typeof window.fetchAvailableTimeSlots === 'function',
                            'updateSummary': typeof window.updateSummary === 'function'
                        },
                        elements: {
                            'appointmentForm': !!document.getElementById('appointmentForm'),
                            'timeSlots': !!document.getElementById('timeSlots'),
                            'bookingSummary': !!document.getElementById('bookingSummary'),
                            'formElement': document.querySelector('form') ? document.querySelector('form').id : 'No form found'
                        }
                    };
                    
                    // Get form field values
                    document.querySelectorAll('#appointmentForm input, #appointmentForm select, #appointmentForm textarea').forEach(el => {
                        if (el.id) {
                            formState.fields[el.id] = el.value;
                        }
                    });
                    
                    // Get selected radio/checkbox options
                    document.querySelectorAll('#appointmentForm input[type="radio"]:checked, #appointmentForm input[type="checkbox"]:checked').forEach(el => {
                        formState.options[el.name] = el.value;
                    });
                    
                    // Get time slot information
                    const selectedSlot = document.querySelector('.time-slot.selected');
                    if (selectedSlot) {
                        formState.timeSlots.selected = {
                            time: selectedSlot.textContent,
                            dataTime: selectedSlot.getAttribute('data-time'),
                            dataEndTime: selectedSlot.getAttribute('data-end-time')
                        };
                    }
                    
                    // Show debug info
                    debugContent.innerHTML = '<pre>' + JSON.stringify(formState, null, 2) + '</pre>';
                    
                    // Add a hook for time slot fetching
                    if (typeof window.fetchAvailableTimeSlots === 'function') {
                        const originalFetch = window.fetchAvailableTimeSlots;
                        window.fetchAvailableTimeSlots = function(date, duration) {
                            debugContent.innerHTML += `<div>API Call: Fetching slots for ${date}, duration ${duration}</div>`;
                            return originalFetch(date, duration);
                        };
                    }
                }
            });
            
            // Auto-recovery if form not found
            if (!document.getElementById('appointmentForm')) {
                console.warn('Form not found - attempting auto-recovery');
                // Try to find any form and assign the ID
                const forms = document.querySelectorAll('form');
                if (forms.length > 0) {
                    forms[0].id = 'appointmentForm';
                    console.log('Auto-recovery: ID assigned to found form', forms[0]);
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <?php
    // We need to manually enqueue our scripts in the correct order
    if (!wp_script_is('massage-booking-form-script', 'enqueued')) {
        // Enqueue base form script
        wp_enqueue_script(
            'massage-booking-form-script',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/booking-form.js',
            array('jquery'),
            MASSAGE_BOOKING_VERSION,
            true
        );
    }
    
    if (!wp_script_is('massage-booking-api-connector', 'enqueued')) {
        // Enqueue API connector
        wp_enqueue_script(
            'massage-booking-api-connector',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-connector.js',
            array('jquery', 'massage-booking-form-script'),
            MASSAGE_BOOKING_VERSION,
            true
        );
        
        // Pass WordPress data to JavaScript
        wp_localize_script('massage-booking-api-connector', 'massageBookingAPI', array(
            'restUrl' => esc_url_raw(rest_url('massage-booking/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'siteUrl' => get_site_url(),
            'isLoggedIn' => is_user_logged_in() ? 'yes' : 'no',
            'version' => MASSAGE_BOOKING_VERSION
        ));
        
        // Add the debug toggle module here (step 4)
        if (defined('WP_DEBUG') && WP_DEBUG) {
        wp_enqueue_script(
            'massage-booking-debug-toggle',
            MASSAGE_BOOKING_PLUGIN_URL . 'public/js/debug-toggle.js',
            array('jquery', 'massage-booking-form-script'),
            MASSAGE_BOOKING_VERSION,
            true
        );
    }
    ?>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log("jQuery document ready event fired");
        
        // Initialize radio buttons
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
        
        // Initialize checkbox options
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
        }
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html><?php
// Output the buffer
echo ob_get_clean();
// Stop execution to prevent the theme's footer from loading
exit;
?>

