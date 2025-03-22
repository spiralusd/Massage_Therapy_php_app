<?php
/**
 * Fix for Debug Files Conflict
 * 
 * This patch fixes the conflict between debug.php and enhanced-debug.php
 * where the same functions are defined twice.
 * 
 * Replace enhanced-debug.php with this file.
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

// Only register these AJAX endpoints if they don't already exist
if (!function_exists('massage_booking_register_debug_endpoint')) {
    /**
     * Register AJAX endpoint to view debug log
     */
    function massage_booking_register_debug_endpoint() {
        add_action('wp_ajax_massage_booking_view_debug_log', 'massage_booking_enhanced_view_debug_log');
    }
    add_action('init', 'massage_booking_register_debug_endpoint');

    /**
     * AJAX handler to view debug log
     */
    function massage_booking_enhanced_view_debug_log() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        check_ajax_referer('massage_booking_view_debug_log');
        
        // Download log if requested
        if (isset($_GET['download'])) {
            massage_booking_download_debug_log();
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
    
    /**
     * Download debug log file
     */
    function massage_booking_download_debug_log() {
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
}

// Only add these functions if they don't already exist
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