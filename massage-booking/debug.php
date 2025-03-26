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
 */

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


/**
 * Debug Admin Interface Enhancement
 * 
 * ADD THIS CODE TO THE END OF YOUR EXISTING debug.php FILE
 */

// Add admin menu for viewing logs if not already defined
if (!function_exists('massage_booking_add_debug_page')) {
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
}

// Add the debug page renderer if not already defined
if (!function_exists('massage_booking_debug_page')) {
    /**
     * Render the debug page
     */
    function massage_booking_debug_page() {
        // Define debug log file path if not defined
        if (!defined('MASSAGE_BOOKING_LOG_FILE')) {
            // Use WordPress upload directory if possible
            $upload_dir = wp_upload_dir();
            define('MASSAGE_BOOKING_LOG_FILE', $upload_dir['basedir'] . '/massage-booking-debug.log');
        }
        
        // Get debug enabled status
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        
        // Get specific debug contexts if defined
        $debug_contexts = defined('MASSAGE_BOOKING_DEBUG_CONTEXTS') ? 
            explode(',', MASSAGE_BOOKING_DEBUG_CONTEXTS) : [];
        
        ?>
        <div class="wrap massage-booking-admin">
            <h1>Massage Booking Debug Logs</h1>
            
            <div class="card">
                <h2>Debug Status</h2>
                <p>
                    <strong>Debug Mode:</strong> 
                    <?php if ($debug_enabled): ?>
                        <span style="color:green;">Enabled</span>
                    <?php else: ?>
                        <span style="color:red;">Disabled</span> 
                        <em>To enable, add <code>define('WP_DEBUG', true);</code> to your wp-config.php</em>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Debug Log File:</strong> 
                    <?php echo esc_html(MASSAGE_BOOKING_LOG_FILE); ?>
                    <?php if (file_exists(MASSAGE_BOOKING_LOG_FILE)): ?>
                        <span style="color:green;">(Exists)</span>
                    <?php else: ?>
                        <span style="color:red;">(Not Created Yet)</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($debug_contexts)): ?>
                <p>
                    <strong>Filtered Contexts:</strong> 
                    <?php echo implode(', ', $debug_contexts); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>View Debug Log</h2>
                <p>This page shows the debug log for the Massage Booking plugin.</p>
                
                <div id="debug-log-container">
                    <p>Loading debug log...</p>
                </div>
                
                <p>
                    <button id="refresh-log" class="button button-primary">Refresh Log</button>
                    <button id="clear-log" class="button button-secondary">Clear Log</button>
                    <button id="download-log" class="button">Download Log</button>
                </p>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Create Test Log Entry</h2>
                <p>Use this form to create a test log entry for debugging purposes.</p>
                
                <form id="test-log-form">
                    <p>
                        <label for="test-log-message">Log Message:</label><br>
                        <input type="text" id="test-log-message" name="message" value="This is a test log entry" class="regular-text">
                    </p>
                    <p>
                        <label for="test-log-context">Context:</label><br>
                        <select id="test-log-context" name="context">
                            <option value="TEST">TEST</option>
                            <option value="CALENDAR">CALENDAR</option>
                            <option value="MS_AUTH">MS_AUTH</option>
                            <option value="FORM">FORM</option>
                            <option value="ERROR">ERROR</option>
                        </select>
                    </p>
                    <p>
                        <label for="test-log-level">Log Level:</label><br>
                        <select id="test-log-level" name="level">
                            <option value="debug">Debug</option>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                    </p>
                    <?php wp_nonce_field('massage_booking_test_log'); ?>
                    <button type="submit" class="button button-primary">Create Test Log Entry</button>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Function to load debug log
            function loadDebugLog() {
                $('#debug-log-container').html('<p>Loading debug log...</p>');
                
                $.post(ajaxurl, {
                    action: 'massage_booking_view_debug_log',
                    _wpnonce: '<?php echo wp_create_nonce('massage_booking_view_debug_log'); ?>'
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
            
            // Clear log button
            $('#clear-log').on('click', function() {
                if (confirm('Are you sure you want to clear the debug log?')) {
                    $.post(ajaxurl, {
                        action: 'massage_booking_clear_debug_log',
                        _wpnonce: '<?php echo wp_create_nonce('massage_booking_clear_debug_log'); ?>'
                    }, function(response) {
                        if (response.success) {
                            loadDebugLog();
                            alert('Debug log cleared successfully.');
                        } else {
                            alert('Failed to clear debug log: ' + response.data.message);
                        }
                    });
                }
            });
            
            // Download log button
            $('#download-log').on('click', function() {
                window.location.href = ajaxurl + '?action=massage_booking_view_debug_log&download=1&_wpnonce=<?php echo wp_create_nonce('massage_booking_view_debug_log'); ?>';
            });
            
            // Test log form
            $('#test-log-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'massage_booking_test_log',
                    message: $('#test-log-message').val(),
                    context: $('#test-log-context').val(),
                    level: $('#test-log-level').val(),
                    _wpnonce: $('[name="_wpnonce"]', this).val()
                }, function(response) {
                    if (response.success) {
                        alert('Test log entry created successfully.');
                        loadDebugLog();
                    } else {
                        alert('Failed to create test log entry: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Add AJAX handler for viewing debug log if not already defined
if (!function_exists('massage_booking_view_debug_log')) {
    /**
     * AJAX handler to view debug log
     */
    function massage_booking_view_debug_log() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        check_ajax_referer('massage_booking_view_debug_log');
        
        // Define log file path if not defined
        if (!defined('MASSAGE_BOOKING_LOG_FILE')) {
            $upload_dir = wp_upload_dir();
            define('MASSAGE_BOOKING_LOG_FILE', $upload_dir['basedir'] . '/massage-booking-debug.log');
        }
        
        // Download log if requested
        if (isset($_GET['download'])) {
            if (!file_exists(MASSAGE_BOOKING_LOG_FILE)) {
                wp_die('Debug log file does not exist.');
            }
            
            // Set headers for download
            header('Content-Description: File Transfer');
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename=massage-booking-debug-' . date('Y-m-d') . '.log');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize(MASSAGE_BOOKING_LOG_FILE));
            
            // Clear output buffer
            ob_clean();
            flush();
            
            // Output file
            readfile(MASSAGE_BOOKING_LOG_FILE);
            exit;
        }
        
        // Check if log file exists
        if (!file_exists(MASSAGE_BOOKING_LOG_FILE)) {
            echo '<p>Debug log file does not exist yet.</p>';
            wp_die();
        }
        
        // Get log content
        $log_content = file_get_contents(MASSAGE_BOOKING_LOG_FILE);
        
        // Output log with styling
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
                font-size: 12px;
                line-height: 1.5;
            }
            .log-error { color: #dc3545; font-weight: bold; }
            .log-warning { color: #ffc107; font-weight: bold; }
            .log-info { color: #0275d8; }
            .log-debug { color: #5cb85c; }
            .log-context { color: #6610f2; font-weight: bold; }
        </style>';
        
        echo '<div class="debug-log-viewer">';
        
        if (empty($log_content)) {
            echo 'Log file is empty.';
        } else {
            // Highlight different log levels and contexts
            $log_content = htmlspecialchars($log_content);
            $log_content = preg_replace('/\[ERROR\]/', '<span class="log-error">[ERROR]</span>', $log_content);
            $log_content = preg_replace('/\[WARNING\]/', '<span class="log-warning">[WARNING]</span>', $log_content);
            $log_content = preg_replace('/\[INFO\]/', '<span class="log-info">[INFO]</span>', $log_content);
            $log_content = preg_replace('/\[DEBUG\]/', '<span class="log-debug">[DEBUG]</span>', $log_content);
            
            // Highlight common contexts
            $contexts = ['CALENDAR', 'MS_AUTH', 'FORM', 'ERROR', 'TEST', 'API', 'DATABASE'];
            foreach ($contexts as $context) {
                $log_content = preg_replace('/\['.$context.'\]/', '<span class="log-context">['.$context.']</span>', $log_content);
            }
            
            echo $log_content;
        }
        
        echo '</div>';
        
        wp_die();
    }
    add_action('wp_ajax_massage_booking_view_debug_log', 'massage_booking_view_debug_log');
}

// Add AJAX handler for clearing debug log if not already defined
if (!function_exists('massage_booking_clear_debug_log')) {
    /**
     * AJAX handler to clear debug log
     */
    function massage_booking_clear_debug_log() {
        // Security check
        check_ajax_referer('massage_booking_clear_debug_log');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        // Define log file path if not defined
        if (!defined('MASSAGE_BOOKING_LOG_FILE')) {
            $upload_dir = wp_upload_dir();
            define('MASSAGE_BOOKING_LOG_FILE', $upload_dir['basedir'] . '/massage-booking-debug.log');
        }
        
        // Clear log file
        if (file_exists(MASSAGE_BOOKING_LOG_FILE)) {
            // Add a header line to the log
            $header = '[' . date('Y-m-d H:i:s') . '] [INFO] Log cleared by admin user: ' . wp_get_current_user()->user_login . "\n";
            file_put_contents(MASSAGE_BOOKING_LOG_FILE, $header);
            wp_send_json_success(['message' => 'Log cleared successfully']);
        } else {
            wp_send_json_error(['message' => 'Log file does not exist']);
        }
    }
    add_action('wp_ajax_massage_booking_clear_debug_log', 'massage_booking_clear_debug_log');
}

// Add AJAX handler for creating test log entries if not already defined
if (!function_exists('massage_booking_test_log')) {
    /**
     * AJAX handler for creating test log entries
     */
    function massage_booking_test_log() {
        // Security check
        check_ajax_referer('massage_booking_test_log');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        // Get parameters
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Test log entry';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'TEST';
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'debug';
        
        // Create log entry using existing function
        if (function_exists('massage_booking_debug_log')) {
            massage_booking_debug_log($message, ['source' => 'admin_test'], $level, $context);
            wp_send_json_success(['message' => 'Test log entry created successfully']);
        } else {
            wp_send_json_error(['message' => 'Debug log function not available']);
        }
    }
    add_action('wp_ajax_massage_booking_test_log', 'massage_booking_test_log');
}
?>