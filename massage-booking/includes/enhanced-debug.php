<?php
/**
 * Enhanced Debug Logging for Calendar Integration
 * 
 * Add this to your massage-booking/debug.php file or include it separately
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Define debug log file path
if (!defined('MASSAGE_BOOKING_LOG_FILE')) {
    define('MASSAGE_BOOKING_LOG_FILE', WP_CONTENT_DIR . '/massage-booking-debug.log');
}

/**
 * Advanced debug logging function with more details
 */
function massage_booking_debug_log_detail($message, $data = null, $log_level = 'info', $context = '') {
    $should_log = defined('WP_DEBUG') && WP_DEBUG;
    
    // Allow specific context filtering (if needed)
    $debug_contexts = defined('MASSAGE_BOOKING_DEBUG_CONTEXTS') ? explode(',', MASSAGE_BOOKING_DEBUG_CONTEXTS) : [];
    if (!empty($debug_contexts) && !empty($context) && !in_array($context, $debug_contexts)) {
        $should_log = false;
    }
    
    if (!$should_log) {
        return;
    }
    
    // Format data for logging
    $data_string = '';
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $data_string = ' | Data: ' . print_r($data, true);
        } else {
            $data_string = ' | Data: ' . $data;
        }
    }
    
    // Get call stack info for better debugging
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]) ? $backtrace[1] : $backtrace[0];
    $caller_info = '';
    
    if (isset($caller['file']) && isset($caller['line'])) {
        $file = basename($caller['file']);
        $line = $caller['line'];
        $caller_info = " | Called from: {$file}:{$line}";
    }
    
    // Add context prefix if provided
    $context_prefix = !empty($context) ? "[{$context}] " : "";
    
    // Prepare log entry
    $log_entry = sprintf(
        "[%s] [%s] %s%s%s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($log_level),
        $context_prefix,
        $message,
        $data_string,
        $caller_info
    );
    
    // Write to debug.log
    error_log($log_entry);
    
    // Also write to our custom log file for easier access
    file_put_contents(
        MASSAGE_BOOKING_LOG_FILE,
        $log_entry,
        FILE_APPEND
    );
}

/**
 * Calendar specific debug logger
 */
function massage_booking_calendar_debug($message, $data = null) {
    massage_booking_debug_log_detail($message, $data, 'debug', 'CALENDAR');
}

/**
 * Error specific debug logger
 */
function massage_booking_error_log($message, $data = null) {
    massage_booking_debug_log_detail($message, $data, 'error', 'ERROR');
}

/**
 * Debug helper specifically for form submissions
 */
function massage_booking_form_debug($message, $data = null) {
    massage_booking_debug_log_detail($message, $data, 'debug', 'FORM');
}

/**
 * Register an AJAX endpoint to view the debug log in admin
 */
function massage_booking_register_debug_endpoint() {
    add_action('wp_ajax_massage_booking_view_debug_log', 'massage_booking_view_debug_log');
}
add_action('init', 'massage_booking_register_debug_endpoint');

/**
 * AJAX handler to view debug log
 */
function massage_booking_view_debug_log() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    // Check if log file exists
    if (!file_exists(MASSAGE_BOOKING_LOG_FILE)) {
        echo '<p>Debug log file does not exist yet.</p>';
        wp_die();
    }
    
    // Get log content
    $log_content = file_get_contents(MASSAGE_BOOKING_LOG_FILE);
    
    // Output log with basic styling
    echo '<style>
        .debug-log-viewer {
            background: #f1f1f1;
            padding: 20px;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ccc;
        }
        .log-error { color: #dc3545; font-weight: bold; }
        .log-info { color: #0275d8; }
        .log-debug { color: #5cb85c; }
    </style>';
    
    echo '<div class="debug-log-viewer">';
    
    if (empty($log_content)) {
        echo 'Log file is empty.';
    } else {
        // Highlight different log levels
        $log_content = preg_replace('/\[ERROR\]/', '<span class="log-error">[ERROR]</span>', $log_content);
        $log_content = preg_replace('/\[INFO\]/', '<span class="log-info">[INFO]</span>', $log_content);
        $log_content = preg_replace('/\[DEBUG\]/', '<span class="log-debug">[DEBUG]</span>', $log_content);
        
        echo $log_content;
    }
    
    echo '</div>';
    
    // Add controls
    echo '<p><button id="clear-log" class="button button-secondary">Clear Log</button></p>';
    
    // Add JS for clearing log
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#clear-log').on('click', function() {
            if (confirm('Are you sure you want to clear the debug log?')) {
                $.post(ajaxurl, {
                    action: 'massage_booking_clear_debug_log',
                    nonce: '<?php echo wp_create_nonce('massage_booking_clear_debug_log'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('.debug-log-viewer').html('Log cleared successfully.');
                    } else {
                        alert('Failed to clear log: ' + response.data.message);
                    }
                });
            }
        });
    });
    </script>
    <?php
    
    wp_die();
}

/**
 * Register AJAX endpoint to clear the debug log
 */
add_action('wp_ajax_massage_booking_clear_debug_log', 'massage_booking_clear_debug_log');

/**
 * AJAX handler to clear debug log
 */
function massage_booking_clear_debug_log() {
    // Security check
    if (!current_user_can('manage_options') || !check_ajax_referer('massage_booking_clear_debug_log', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    
    // Clear log file
    if (file_exists(MASSAGE_BOOKING_LOG_FILE)) {
        file_put_contents(MASSAGE_BOOKING_LOG_FILE, '');
        wp_send_json_success(['message' => 'Log cleared successfully']);
    } else {
        wp_send_json_error(['message' => 'Log file does not exist']);
    }
}

/**
 * Add debug viewing page to admin menu
 */
function massage_booking_add_debug_page() {
    add_submenu_page(
        'massage-booking',
        'Debug Logs',
        'Debug Logs',
        'manage_options',
        'massage-booking-debug',
        'massage_booking_debug_page'
    );
}
add_action('admin_menu', 'massage_booking_add_debug_page');

/**
 * Render the debug page
 */
function massage_booking_debug_page() {
    ?>
    <div class="wrap">
        <h1>Massage Booking Debug Logs</h1>
        
        <div class="card">
            <h2>View Debug Log</h2>
            <p>This page shows the debug log for the Massage Booking plugin.</p>
            
            <div id="debug-log-container">
                <p>Loading debug log...</p>
            </div>
            
            <p><button id="refresh-log" class="button button-primary">Refresh Log</button></p>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Test Calendar Integration</h2>
            <p>Use this form to test the Office 365 calendar integration.</p>
            
            <form id="test-calendar-form">
                <?php wp_nonce_field('massage_booking_test_calendar'); ?>
                <button type="submit" class="button button-primary">Test Calendar Connection</button>
            </form>
            
            <div id="calendar-test-result" style="margin-top: 10px; padding: 15px; background: #f1f1f1; display: none;"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        function loadDebugLog() {
            $('#debug-log-container').html('<p>Loading debug log...</p>');
            
            $.post(ajaxurl, {
                action: 'massage_booking_view_debug_log'
            }, function(response) {
                $('#debug-log-container').html(response);
            });
        }
        
        // Load debug log when page loads
        loadDebugLog();
        
        // Refresh button
        $('#refresh-log').on('click', function() {
            loadDebugLog();
        });
        
        // Test calendar integration
        $('#test-calendar-form').on('submit', function(e) {
            e.preventDefault();
            
            const resultContainer = $('#calendar-test-result');
            resultContainer.html('<p>Testing calendar integration...</p>').show();
            
            $.post(ajaxurl, {
                action: 'massage_booking_test_calendar',
                nonce: $('[name="_wpnonce"]', this).val()
            }, function(response) {
                if (response.success) {
                    resultContainer.html('<p style="color: green;">✓ Calendar integration is working!</p>' +
                        '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                } else {
                    resultContainer.html('<p style="color: red;">✗ Calendar integration failed:</p>' +
                        '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                }
            }).fail(function() {
                resultContainer.html('<p style="color: red;">✗ Request failed. Check server logs.</p>');
            });
        });
    });
    </script>
    <?php
}

/**
 * Register AJAX endpoint to test calendar integration
 */
add_action('wp_ajax_massage_booking_test_calendar', 'massage_booking_test_calendar');

/**
 * AJAX handler to test calendar integration
 */
function massage_booking_test_calendar() {
    // Security check
    if (!current_user_can('manage_options') || !check_ajax_referer('massage_booking_test_calendar', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    
    // Check if calendar class exists
    if (!class_exists('Massage_Booking_Calendar')) {
        wp_send_json_error(['message' => 'Calendar class not found']);
        return;
    }
    
    try {
        $calendar = new Massage_Booking_Calendar();
        
        // Check if calendar is configured
        if (!method_exists($calendar, 'is_configured') || !$calendar->is_configured()) {
            $settings = new Massage_Booking_Settings();
            $client_id = $settings->get_setting('ms_client_id');
            $client_secret = $settings->get_setting('ms_client_secret');
            $tenant_id = $settings->get_setting('ms_tenant_id');
            
            wp_send_json_error([
                'message' => 'Calendar is not configured',
                'settings' => [
                    'client_id_set' => !empty($client_id),
                    'client_secret_set' => !empty($client_secret),
                    'tenant_id_set' => !empty($tenant_id)
                ]
            ]);
            return;
        }
        
        // Try to get access token (if method exists)
        $access_token = false;
        if (method_exists($calendar, 'get_access_token')) {
            $access_token = $calendar->get_access_token();
        } else {
            // Try alternative token access
            $settings = new Massage_Booking_Settings();
            $client_id = $settings->get_setting('ms_client_id');
            $client_secret = $settings->get_setting('ms_client_secret');
            $tenant_id = $settings->get_setting('ms_tenant_id');
            
            $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
            
            $response = wp_remote_post($token_url, [
                'body' => [
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials'
                ]
            ]);
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['access_token'])) {
                    $access_token = $body['access_token'];
                }
            }
        }
        
        if (!$access_token) {
            wp_send_json_error([
                'message' => 'Failed to get access token',
                'token_info' => [
                    'access_token_exists' => !empty(get_option('massage_booking_ms_access_token')),
                    'refresh_token_exists' => !empty(get_option('massage_booking_ms_refresh_token')),
                    'token_expiry' => get_option('massage_booking_ms_token_expiry')
                ]
            ]);
            return;
        }
        
        // Success - token was obtained
        wp_send_json_success([
            'message' => 'Calendar integration token obtained successfully',
            'token' => 'Token obtained successfully'
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Exception occurred',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Add AJAX endpoint for form debugging
 */
add_action('wp_ajax_massage_booking_debug_form_submission', 'massage_booking_debug_form_submission');
add_action('wp_ajax_nopriv_massage_booking_debug_form_submission', 'massage_booking_debug_form_submission');

/**
 * Debug form submission
 */
function massage_booking_debug_form_submission() {
    // Log all data for debugging
    massage_booking_form_debug('Form submission debug endpoint hit', $_POST);
    
    // Return debug info
    wp_send_json_success([
        'message' => 'Debug data logged',
        'time' => current_time('mysql')
    ]);
}
?>