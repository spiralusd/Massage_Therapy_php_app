<?php
/**
 * Massage Booking Debug Helper
 * 
 * This file can be included in massage-booking.php to provide
 * additional debugging capabilities.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Define debug constant if not already defined
if (!defined('MASSAGE_BOOKING_DEBUG')) {
    define('MASSAGE_BOOKING_DEBUG', WP_DEBUG);
}

/**
 * Helper function for debug logging
 */
function massage_booking_debug_log($message, $data = null) {
    if (MASSAGE_BOOKING_DEBUG) {
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $data_string = print_r($data, true);
            } else {
                $data_string = $data;
            }
            $log_message = $message . ': ' . $data_string;
        } else {
            $log_message = $message;
        }
        
        error_log('MASSAGE BOOKING DEBUG: ' . $log_message);
    }
}

/**
 * Add debugging info to footer on booking pages (admin only)
 */
function massage_booking_debug_footer() {
    if (!MASSAGE_BOOKING_DEBUG || !current_user_can('manage_options')) {
        return;
    }
    
    // Only on booking pages
    if (!is_page_template('page-booking.php') && 
        !(is_page() && get_post_meta(get_the_ID(), '_wp_page_template', true) === MASSAGE_BOOKING_PLUGIN_DIR . 'public/templates/page-booking.php')) {
        return;
    }
    
    // Generate debug info
    $debug_info = array(
        'Plugin Version' => MASSAGE_BOOKING_VERSION,
        'WordPress Version' => get_bloginfo('version'),
        'PHP Version' => PHP_VERSION,
        'jQuery Version' => 'Check console for "jQuery.fn.jquery"',
        'Template' => get_post_meta(get_the_ID(), '_wp_page_template', true),
        'AJAX URL' => admin_url('admin-ajax.php'),
        'REST URL' => rest_url('massage-booking/v1/'),
        'User Logged In' => is_user_logged_in() ? 'Yes' : 'No',
        'Scripts Registered' => array(
            'jquery' => wp_script_is('jquery', 'registered') ? 'Yes' : 'No',
            'massage-booking-form-script' => wp_script_is('massage-booking-form-script', 'registered') ? 'Yes' : 'No',
            'massage-booking-jquery-form' => wp_script_is('massage-booking-jquery-form', 'registered') ? 'Yes' : 'No',
            'massage-booking-api-connector' => wp_script_is('massage-booking-api-connector', 'registered') ? 'Yes' : 'No'
        ),
        'Scripts Enqueued' => array(
            'jquery' => wp_script_is('jquery', 'enqueued') ? 'Yes' : 'No',
            'massage-booking-form-script' => wp_script_is('massage-booking-form-script', 'enqueued') ? 'Yes' : 'No',
            'massage-booking-jquery-form' => wp_script_is('massage-booking-jquery-form', 'enqueued') ? 'Yes' : 'No',
            'massage-booking-api-connector' => wp_script_is('massage-booking-api-connector', 'enqueued') ? 'Yes' : 'No'
        )
    );
    
    // Output debug info for admin users
    echo '<div class="massage-booking-debug" style="background: #f8f9fa; border: 1px solid #ddd; margin: 20px; padding: 15px; position: relative; z-index: 9999;">';
    echo '<h3>Massage Booking Debug Info</h3>';
    echo '<button onclick="document.querySelector(\'.debug-details\').style.display = document.querySelector(\'.debug-details\').style.display === \'none\' ? \'block\' : \'none\';" style="margin-bottom: 10px;">Toggle Details</button>';
    echo '<div class="debug-details" style="display: none;">';
    echo '<ul>';
    
    foreach ($debug_info as $key => $value) {
        echo '<li><strong>' . esc_html($key) . ':</strong> ';
        if (is_array($value)) {
            echo '<ul>';
            foreach ($value as $subkey => $subvalue) {
                echo '<li><strong>' . esc_html($subkey) . ':</strong> ' . esc_html($subvalue) . '</li>';
            }
            echo '</ul>';
        } else {
            echo esc_html($value);
        }
        echo '</li>';
    }
    
    echo '</ul>';
    
    // Add simple tester
    echo '<div class="debug-tools" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
    echo '<h4>Debug Tools</h4>';
    echo '<button onclick="testAjax()">Test AJAX Connection</button>';
    echo '<div id="ajax-test-result" style="margin-top: 10px; padding: 10px; background: #eee;"></div>';
    echo '</div>';
    
    // Add test script
    echo '<script>
    function testAjax() {
        var result = document.getElementById("ajax-test-result");
        result.innerHTML = "Testing AJAX connection...";
        
        jQuery.ajax({
            url: "' . admin_url('admin-ajax.php') . '",
            type: "POST",
            data: {
                action: "massage_booking_debug_test",
                nonce: "' . wp_create_nonce('massage_booking_debug_test') . '"
            },
            success: function(response) {
                result.innerHTML = "<strong>Success!</strong> Server responded: " + JSON.stringify(response);
            },
            error: function(xhr, status, error) {
                result.innerHTML = "<strong>Error!</strong> " + error + "<br>Status: " + status + "<br>Response: " + xhr.responseText;
            }
        });
        
        // Also print jQuery version
        console.log("jQuery version: " + jQuery.fn.jquery);
    }
    </script>';
    
    echo '</div>'; // .debug-details
    echo '</div>'; // .massage-booking-debug
}
add_action('wp_footer', 'massage_booking_debug_footer', 999);

/**
 * AJAX test endpoint
 */
function massage_booking_debug_test_ajax() {
    check_ajax_referer('massage_booking_debug_test', 'nonce');
    
    wp_send_json_success(array(
        'message' => 'AJAX connection successful',
        'time' => current_time('mysql'),
        'php_version' => PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => MASSAGE_BOOKING_VERSION
    ));
}
add_action('wp_ajax_massage_booking_debug_test', 'massage_booking_debug_test_ajax');
add_action('wp_ajax_nopriv_massage_booking_debug_test', 'massage_booking_debug_test_ajax');
