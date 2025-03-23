<?php
/**
 * Enhanced Debug System for Massage Booking Plugin
 * 
 * Provides configurable debug logging with WordPress admin interface
 * FIXED VERSION: Removed function redeclarations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Debug {
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Debug enabled flag
     */
    private $debug_enabled = false;
    
    /**
     * Log file path
     */
    private $log_file = '';
    
    /**
     * Debug contexts to log
     */
    private $debug_contexts = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Define log file path
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/massage-booking-debug.log';
        
        // Load settings
        $settings = new Massage_Booking_Settings();
        $this->debug_enabled = $settings->get_setting('debug_enabled', false);
        
        // Get enabled debug contexts
        $this->debug_contexts = $settings->get_setting('debug_contexts', [
            'CALENDAR', 'MS_AUTH', 'FORM', 'ERROR'
        ]);
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Register hooks for admin functionality
     */
    private function register_hooks() {
        // Add debug settings to settings page
        add_filter('massage_booking_settings_fields', [$this, 'add_debug_settings']);
        
        // Register admin menu for viewing logs
        add_action('admin_menu', [$this, 'add_debug_menu']);
        
        // Register AJAX endpoints for log viewing and clearing
        add_action('wp_ajax_massage_booking_class_view_debug_log', [$this, 'ajax_view_debug_log']);
        add_action('wp_ajax_massage_booking_class_clear_debug_log', [$this, 'ajax_clear_debug_log']);
    }
    
    /**
     * Add debug settings to settings page
     */
    public function add_debug_settings($settings_fields) {
        // Add debug section to settings
        $settings_fields['debug_settings'] = [
            'title' => 'Debug Settings',
            'fields' => [
                'debug_enabled' => [
                    'label' => 'Enable Debug Logging',
                    'type' => 'checkbox',
                    'default' => false,
                    'description' => 'Enable detailed debug logging for troubleshooting.'
                ],
                'debug_contexts' => [
                    'label' => 'Debug Contexts',
                    'type' => 'multicheck',
                    'options' => [
                        'CALENDAR' => 'Calendar Integration',
                        'MS_AUTH' => 'Microsoft Authentication',
                        'FORM' => 'Booking Form',
                        'ERROR' => 'Errors',
                        'ADMIN' => 'Admin Operations',
                        'API' => 'API Requests',
                        'DATABASE' => 'Database Operations'
                    ],
                    'default' => ['CALENDAR', 'MS_AUTH', 'FORM', 'ERROR'],
                    'description' => 'Select which components to include in debug logs.'
                ]
            ]
        ];
        
        return $settings_fields;
    }
    
    /**
     * Add debug menu to admin
     */
    public function add_debug_menu() {
        add_submenu_page(
            'massage-booking',
            'Debug Logs',
            'Debug Logs',
            'manage_options',
            'massage-booking-class-debug',
            [$this, 'render_debug_page']
        );
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap massage-booking-admin">
            <h1>Massage Booking Debug Logs</h1>
            
            <div class="card">
                <h2>Debug Status</h2>
                <p>
                    <strong>Debug Mode:</strong> 
                    <?php if ($this->debug_enabled): ?>
                        <span style="color:green;">Enabled</span>
                    <?php else: ?>
                        <span style="color:red;">Disabled</span> 
                        <a href="<?php echo admin_url('admin.php?page=massage-booking-settings#debug-settings'); ?>" class="button button-small">Enable Debug</a>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Debug Log File:</strong> 
                    <?php echo esc_html($this->log_file); ?>
                    <?php if (file_exists($this->log_file)): ?>
                        <span style="color:green;">(Exists)</span>
                    <?php else: ?>
                        <span style="color:red;">(Not Created Yet)</span>
                    <?php endif; ?>
                </p>
                <p>
                    <strong>Enabled Contexts:</strong> 
                    <?php echo implode(', ', $this->debug_contexts); ?>
                </p>
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
                            <?php foreach ($this->debug_contexts as $context): ?>
                                <option value="<?php echo esc_attr($context); ?>"><?php echo esc_html($context); ?></option>
                            <?php endforeach; ?>
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
                    <?php wp_nonce_field('massage_booking_class_test_log'); ?>
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
                    action: 'massage_booking_class_view_debug_log',
                    _wpnonce: '<?php echo wp_create_nonce('massage_booking_class_view_debug_log'); ?>'
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
                        action: 'massage_booking_class_clear_debug_log',
                        _wpnonce: '<?php echo wp_create_nonce('massage_booking_class_clear_debug_log'); ?>'
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
                window.location.href = ajaxurl + '?action=massage_booking_class_view_debug_log&download=1&_wpnonce=<?php echo wp_create_nonce('massage_booking_class_view_debug_log'); ?>';
            });
            
            // Test log form
            $('#test-log-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'massage_booking_class_test_log',
                    message: $('#test-log-message').val(),
                    context: $('#test-log-context').val(),
                    level: $('#test-log-level').val(),
                    _wpnonce: '<?php echo wp_nonce_field('massage_booking_class_test_log'); ?>'
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
    
    /**
     * AJAX handler for viewing debug log
     */
    public function ajax_view_debug_log() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        check_ajax_referer('massage_booking_class_view_debug_log');
        
        // Download log if requested
        if (isset($_GET['download'])) {
            $this->download_debug_log();
            exit;
        }
        
        // Check if log file exists
        if (!file_exists($this->log_file)) {
            echo '<p>Debug log file does not exist yet.</p>';
            wp_die();
        }
        
        // Get log content
        $log_content = file_get_contents($this->log_file);
        
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
            
            // Highlight contexts
            foreach ($this->debug_contexts as $context) {
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
    private function download_debug_log() {
        if (!file_exists($this->log_file)) {
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
        header('Content-Length: ' . filesize($this->log_file));
        
        // Clear output buffer
        ob_clean();
        flush();
        
        // Output file
        readfile($this->log_file);
        exit;
    }
    
    /**
     * AJAX handler for clearing debug log
     */
    public function ajax_clear_debug_log() {
        // Security check
        check_ajax_referer('massage_booking_class_clear_debug_log');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        // Clear log file
        if (file_exists($this->log_file)) {
            // Add a header line to the log
            $header = '[' . date('Y-m-d H:i:s') . '] [INFO] Log cleared by admin user: ' . wp_get_current_user()->user_login . "\n";
            file_put_contents($this->log_file, $header);
            wp_send_json_success(['message' => 'Log cleared successfully']);
        } else {
            wp_send_json_error(['message' => 'Log file does not exist']);
        }
    }
    
    /**
     * AJAX handler for creating test log entries
     */
    public function ajax_test_log() {
        // Security check
        check_ajax_referer('massage_booking_class_test_log');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        // Get parameters
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Test log entry';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'TEST';
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'debug';
        
        // Create log entry
        $this->log($message, ['source' => 'admin_test'], $level, $context);
        
        wp_send_json_success(['message' => 'Test log entry created successfully']);
    }
    
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $level Log level (debug, info, warning, error)
     * @param string $context Log context
     */
    public function log($message, $data = [], $level = 'debug', $context = '') {
        // Check if debugging is enabled
        if (!$this->is_debug_enabled($context)) {
            return;
        }
        
        // Format data
        $data_string = '';
        if (!empty($data)) {
            if (is_array($data) || is_object($data)) {
                $data_string = ' | Data: ' . print_r($data, true);
            } else {
                $data_string = ' | Data: ' . $data;
            }
        }
        
        // Get caller information
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? $backtrace[1] : $backtrace[0];
        $caller_info = '';
        
        if (isset($caller['file']) && isset($caller['line'])) {
            $file = basename($caller['file']);
            $line = $caller['line'];
            $caller_info = " | Called from: {$file}:{$line}";
        }
        
        // Add context prefix
        $context_prefix = !empty($context) ? "[{$context}] " : "";
        
        // Normalize log level
        $level = strtoupper($level);
        if (!in_array($level, ['DEBUG', 'INFO', 'WARNING', 'ERROR'])) {
            $level = 'DEBUG';
        }
        
        // Prepare log entry
        $log_entry = sprintf(
            "[%s] [%s] %s%s%s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $context_prefix,
            $message,
            $data_string,
            $caller_info
        );
        
        // Write to log file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND);
    }
    
    /**
     * Check if debugging is enabled for a context
     *
     * @param string $context Context to check
     * @return bool True if debugging is enabled
     */
    public function is_debug_enabled($context = '') {
        // If debugging is disabled globally, return false
        if (!$this->debug_enabled) {
            return false;
        }
        
        // If no context provided or no contexts restricted, return true
        if (empty($context) || empty($this->debug_contexts)) {
            return true;
        }
        
        // Check if context is in enabled contexts
        return in_array($context, $this->debug_contexts);
    }
    
    /**
     * Global logging function
     *
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $level Log level
     * @param string $context Log context
     */
    public static function log_message($message, $data = [], $level = 'debug', $context = '') {
        $instance = self::get_instance();
        $instance->log($message, $data, $level, $context);
    }
}

// Initialize the debug system
add_action('plugins_loaded', function() {
    Massage_Booking_Debug::get_instance();
});

// Register class debug test log action
add_action('wp_ajax_massage_booking_class_test_log', function() {
    $debug = Massage_Booking_Debug::get_instance();
    $debug->ajax_test_log();
});

/**
 * Class-specific enhanced logging
 */
function massage_booking_class_enhanced_debug_log($message, $data = [], $level = 'debug', $context = '') {
    Massage_Booking_Debug::log_message($message, $data, $level, $context);
}

/**
 * Specific enhanced logging functions for different contexts
 */
function massage_booking_class_enhanced_calendar_debug($message, $data = []) {
    massage_booking_class_enhanced_debug_log($message, $data, 'debug', 'CALENDAR');
}

function massage_booking_class_enhanced_error_log($message, $data = []) {
    massage_booking_class_enhanced_debug_log($message, $data, 'error', 'ERROR');
}

function massage_booking_class_enhanced_form_debug($message, $data = []) {
    massage_booking_class_enhanced_debug_log($message, $data, 'debug', 'FORM');
}

function massage_booking_class_enhanced_log_detail($message, $data = null, $log_level = 'info', $context = '') {
    massage_booking_class_enhanced_debug_log($message, $data, $log_level, $context);
}