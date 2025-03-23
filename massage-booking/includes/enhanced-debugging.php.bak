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
                nonce: '<?php echo wp_create_nonce('massage_booking_test_calendar'); ?>'
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
        if (!$calendar->is_configured()) {
            wp_send_json_error([
                'message' => 'Calendar is not configured',
                'settings' => [
                    'client_id_set' => !empty($calendar->get_client_id()),
                    'client_secret_set' => !empty($calendar->get_client_secret()),
                    'tenant_id_set' => !empty($calendar->get_tenant_id())
                ]
            ]);
            return;
        }
        
        // Try to get access token
        $access_token = $calendar->get_access_token(true); // true to force debug output
        
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
        
        // Try to create test event
        $test_event = [
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '555-123-4567',
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'duration' => 60,
            'focus_areas' => ['Test Area'],
            'pressure_preference' => 'Medium',
            'special_requests' => 'This is a test event'
        ];
        
        $event_result = $calendar->create_event($test_event, true); // true to indicate test mode
        
        if (is_wp_error($event_result)) {
            wp_send_json_error([
                'message' => 'Failed to create test event',
                'error' => $event_result->get_error_message(),
                'error_data' => $event_result->get_error_data()
            ]);
            return;
        }
        
        // Success
        wp_send_json_success([
            'message' => 'Calendar integration is working',
            'token' => 'Token obtained successfully',
            'event' => 'Test event created successfully'
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
 * Modify Massage_Booking_Calendar class to add debugging methods
 * This code should run after the class is loaded
 */
function massage_booking_add_calendar_debug_methods() {
    if (class_exists('Massage_Booking_Calendar')) {
        // Only add these methods if they don't already exist
        if (!method_exists('Massage_Booking_Calendar', 'get_client_id')) {
            // Add accessor methods for testing
            add_filter('massage_booking_calendar_get_client_id', function() {
                $settings = new Massage_Booking_Settings();
                return $settings->get_setting('ms_client_id');
            });
            
            add_filter('massage_booking_calendar_get_client_secret', function() {
                $settings = new Massage_Booking_Settings();
                return $settings->get_setting('ms_client_secret');
            });
            
            add_filter('massage_booking_calendar_get_tenant_id', function() {
                $settings = new Massage_Booking_Settings();
                return $settings->get_setting('ms_tenant_id');
            });
        }
    }
}
add_action('plugins_loaded', 'massage_booking_add_calendar_debug_methods', 30);

// Add these functions to the Massage_Booking_Calendar class
if (class_exists('Massage_Booking_Calendar')) {
    // Monkey patch the calendar class to add these methods
    Massage_Booking_Calendar::prototype('get_client_id', function() {
        return apply_filters('massage_booking_calendar_get_client_id', $this->client_id);
    });
    
    Massage_Booking_Calendar::prototype('get_client_secret', function() {
        return apply_filters('massage_booking_calendar_get_client_secret', $this->client_secret);
    });
    
    Massage_Booking_Calendar::prototype('get_tenant_id', function() {
        return apply_filters('massage_booking_calendar_get_tenant_id', $this->tenant_id);
    });
    
    // Override get_access_token to include debug parameter
    Massage_Booking_Calendar::prototype('get_access_token', function($debug = false) {
        // Original method with added debugging
        if ($debug) {
            massage_booking_calendar_debug('Getting access token', [
                'client_id_set' => !empty($this->client_id),
                'client_secret_set' => !empty($this->client_secret),
                'tenant_id_set' => !empty($this->tenant_id)
            ]);
        }
        
        // If we already have a token, return it
        if ($this->access_token) {
            if ($debug) {
                massage_booking_calendar_debug('Using existing token from instance');
            }
            return $this->access_token;
        }
        
        // If required settings are missing, return false
        if (!$this->is_configured()) {
            if ($debug) {
                massage_booking_calendar_debug('Calendar not configured', [
                    'client_id' => $this->client_id ? 'set' : 'unset',
                    'client_secret' => $this->client_secret ? 'set' : 'unset',
                    'tenant_id' => $this->tenant_id ? 'set' : 'unset'
                ]);
            }
            return false;
        }
        
        // Check for cached token
        $token_data = get_transient('massage_booking_ms_token');
        if ($token_data) {
            $this->access_token = $token_data;
            if ($debug) {
                massage_booking_calendar_debug('Using cached token from transient');
            }
            return $this->access_token;
        }
        
        // Get new token
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        if ($debug) {
            massage_booking_calendar_debug('Requesting new token', [
                'token_url' => $token_url
            ]);
        }
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ]
        ]);
        
        if (is_wp_error($response)) {
            if ($debug) {
                massage_booking_error_log('Token request failed', [
                    'error' => $response->get_error_message()
                ]);
            }
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($debug) {
            massage_booking_calendar_debug('Token response received', [
                'status_code' => $status_code,
                'body_keys' => array_keys($body)
            ]);
        }
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            
            // Cache the token (expires_in is in seconds)
            set_transient('massage_booking_ms_token', $this->access_token, $body['expires_in'] - 300);
            
            if ($debug) {
                massage_booking_calendar_debug('New token obtained and cached', [
                    'expires_in' => $body['expires_in']
                ]);
            }
            
            return $this->access_token;
        }
        
        if ($debug) {
            massage_booking_error_log('Failed to get token', [
                'response' => $body,
                'status_code' => $status_code
            ]);
        }
        
        return false;
    });
    
    // Add method for test event creation
    Massage_Booking_Calendar::prototype('create_event', function($appointment, $is_test = false) {
        if ($is_test) {
            massage_booking_calendar_debug('Creating test event', [
                'appointment' => $appointment
            ]);
        }
        
        // Check if calendar is configured
        if (!$this->is_configured()) {
            if ($is_test) {
                massage_booking_error_log('Calendar not configured');
            }
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        // Get access token
        if (!$this->get_access_token($is_test)) {
            if ($is_test) {
                massage_booking_error_log('Failed to get access token');
            }
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        try {
            // Format start and end times
            $start_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            $end_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
            
            // Create event object
            $event = [
                'subject' => 'Massage - ' . $appointment['duration'] . ' min (' . $appointment['full_name'] . ')',
                'body' => [
                    'contentType' => 'text',
                    'content' => "Client: {$appointment['full_name']}\nEmail: {$appointment['email']}\nPhone: {$appointment['phone']}\nFocus Areas: " . 
                                (is_array($appointment['focus_areas']) ? implode(', ', $appointment['focus_areas']) : $appointment['focus_areas']) . 
                                "\nPressure: {$appointment['pressure_preference']}\nSpecial Requests: {$appointment['special_requests']}"
                ],
                'start' => [
                    'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => 'America/New_York' // Using specific timezone
                ],
                'end' => [
                    'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => 'America/New_York'
                ],
                'showAs' => 'busy'
            ];
            
            if ($is_test) {
                massage_booking_calendar_debug('Event data prepared', [
                    'event' => $event
                ]);
            }
            
            // Call Microsoft Graph API to create event
            $response = wp_remote_post(
                'https://graph.microsoft.com/v1.0/me/events',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode($event)
                ]
            );
            
            if ($is_test) {
                if (is_wp_error($response)) {
                    massage_booking_error_log('Event creation failed', [
                        'error' => $response->get_error_message()
                    ]);
                } else {
                    massage_booking_calendar_debug('Event creation response', [
                        'status_code' => wp_remote_retrieve_response_code($response),
                        'body' => json_decode(wp_remote_retrieve_body($response), true)
                    ]);
                }
            }
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                if ($is_test) {
                    massage_booking_error_log('Event creation API error', [
                        'status_code' => $status_code,
                        'error' => isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error'
                    ]);
                }
                
                return new WP_Error(
                    'graph_api_error',
                    isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error',
                    ['status' => $status_code]
                );
            }
            
            if ($is_test) {
                massage_booking_calendar_debug('Event created successfully', [
                    'event_id' => $body['id']
                ]);
            }
            
            return $body;
        } catch (Exception $e) {
            if ($is_test) {
                massage_booking_error_log('Exception in event creation', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    });
}

// Helper function to add methods to a class at runtime (prototype pattern)
if (!function_exists('prototype')) {
    function prototype($function_name, $function_implementation) {
        // This is a placeholder function that will be overridden
    }
}

// Override Massage_Booking_Calendar class to add debugging methods
class_exists('Massage_Booking_Calendar') && !function_exists('Massage_Booking_Calendar::prototype') && function($class_name) {
    $class_name::prototype = function($method_name, $implementation) use ($class_name) {
        if (!method_exists($class_name, $method_name)) {
            add_filter("massage_booking_{$method_name}", function($null, ...$args) use ($implementation) {
                return call_user_func($implementation, ...$args);
            }, 10, 999);
            
            $class_name::${$method_name} = function(...$args) use ($method_name) {
                return apply_filters("massage_booking_{$method_name}", null, ...$args);
            };
        }
    };
}('Massage_Booking_Calendar');

/**
 * Add debug log to appointment form processing
 * This will help trace the issue with calendar integration
 */
add_filter('massage_booking_after_appointment_created', function($appointment_id, $appointment_data) {
    massage_booking_debug_log_detail('Appointment created, ID: ' . $appointment_id, $appointment_data, 'info', 'APPOINTMENT');
    
    // Log calendar integration attempt
    if (class_exists('Massage_Booking_Calendar') && isset($appointment_data['calendar_event_id'])) {
        massage_booking_calendar_debug('Calendar event ID for appointment', [
            'appointment_id' => $appointment_id,
            'calendar_event_id' => $appointment_data['calendar_event_id']
        ]);
    } else {
        massage_booking_calendar_debug('No calendar event ID for appointment', [
            'appointment_id' => $appointment_id,
            'calendar_class_exists' => class_exists('Massage_Booking_Calendar')
        ]);
    }
}, 10, 2);

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
