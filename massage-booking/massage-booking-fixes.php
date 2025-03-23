<?php
/**
 * Plugin Menu and Function Fixes
 * 
 * This file addresses issues with duplicate menu items and missing callback functions
 * Save as "massage-booking-fixes.php" in your plugin directory
 */

// Prevent direct file access
if (!defined('WPINC')) {
    die;
}

/**
 * Implement the missing appointments page function
 */
if (!function_exists('massage_booking_appointments_page')) {
    function massage_booking_appointments_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'massage_appointments';
        
        // Check if the appointments table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        if (!$table_exists) {
            echo '<div class="notice notice-error"><p>Appointments table does not exist. Please deactivate and reactivate the plugin.</p></div>';
            echo '</div>';
            return;
        }
        
        // Get appointments from the database
        $appointments = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY appointment_date DESC, start_time DESC LIMIT 20");
        
        if (empty($appointments)) {
            echo '<p>No appointments found.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Client Name</th><th>Date</th><th>Time</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($appointments as $appointment) {
                echo '<tr>';
                echo '<td>' . esc_html($appointment->id) . '</td>';
                
                // Get client name - handle encrypted data if needed
                $client_name = $appointment->full_name;
                if (class_exists('Massage_Booking_Encryption')) {
                    $encryption = new Massage_Booking_Encryption();
                    try {
                        $decrypted_name = $encryption->decrypt($appointment->full_name);
                        if ($decrypted_name) {
                            $client_name = $decrypted_name;
                        }
                    } catch (Exception $e) {
                        // Use original name if decryption fails
                    }
                }
                
                echo '<td>' . esc_html($client_name) . '</td>';
                
                // Format date and time
                $date = date('M j, Y', strtotime($appointment->appointment_date));
                $time = date('g:i a', strtotime($appointment->start_time));
                
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>' . esc_html($time) . '</td>';
                echo '<td>' . esc_html($appointment->duration) . ' min</td>';
                echo '<td>' . esc_html(ucfirst($appointment->status)) . '</td>';
                
                echo '<td>';
                echo '<a href="?page=massage-booking-appointments&action=view&id=' . esc_attr($appointment->id) . '" class="button button-small">View</a> ';
                echo '</td>';
                
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
}

/**
 * Implement the missing debug page function
 */
if (!function_exists('massage_booking_debug_page')) {
    function massage_booking_debug_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="card">';
        echo '<h2>Debug Information</h2>';
        
        echo '<h3>System Info</h3>';
        echo '<p><strong>PHP Version:</strong> ' . phpversion() . '</p>';
        echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
        echo '<p><strong>Plugin Version:</strong> ' . (defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : 'Unknown') . '</p>';
        
        echo '<h3>Database Tables</h3>';
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'massage_appointments',
            $wpdb->prefix . 'massage_audit_log',
            $wpdb->prefix . 'massage_special_dates'
        ];
        
        echo '<ul>';
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            echo '<li>' . esc_html($table) . ': ' . ($exists ? '<span style="color:green">Exists</span>' : '<span style="color:red">Missing</span>') . '</li>';
        }
        echo '</ul>';
        
        echo '<h3>Plugin Files</h3>';
        if (defined('MASSAGE_BOOKING_PLUGIN_DIR')) {
            $key_files = [
                'massage-booking.php',
                'includes/class-settings.php',
                'includes/class-database-optimized.php',
                'includes/class-encryption-optimized.php',
                'includes/class-audit-log-optimized.php',
                'admin/admin-page.php'
            ];
            
            echo '<ul>';
            foreach ($key_files as $file) {
                $exists = file_exists(MASSAGE_BOOKING_PLUGIN_DIR . $file);
                echo '<li>' . esc_html($file) . ': ' . ($exists ? '<span style="color:green">Exists</span>' : '<span style="color:red">Missing</span>') . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>MASSAGE_BOOKING_PLUGIN_DIR is not defined</p>';
        }
        
        echo '</div>'; // card
        echo '</div>'; // wrap
    }
}

/**
 * Fix the duplicate menu items issue by consolidating the admin menu
 
function massage_booking_fixed_admin_menu() {
    // Remove all existing Massage Booking menu items to prevent duplicates
    remove_menu_page('massage-booking');
    
    // Recreate the menu structure cleanly
    add_menu_page(
        'Massage Booking',
        'Massage Booking',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Add submenu pages
    add_submenu_page(
        'massage-booking',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page'
    );
    
    add_submenu_page(
        'massage-booking',
        'Appointments',
        'Appointments',
        'manage_options',
        'massage-booking-appointments',
        'massage_booking_appointments_page'
    );
    
    add_submenu_page(
        'massage-booking',
        'Schedule Settings',
        'Schedule Settings',
        'manage_options',
        'massage-booking-settings',
        'massage_booking_settings_page'
    );
    
    // Add audit logs for HIPAA compliance
    add_submenu_page(
        'massage-booking',
        'Audit Logs',
        'Audit Logs',
        'manage_options',
        'massage-booking-logs',
        'massage_booking_logs_page'
    );
    
    // Add only one Email Verification page
    add_submenu_page(
        'massage-booking',
        'Email Verification',
        'Email Verification',
        'manage_options',
        'massage-booking-email-verify',
        'massage_booking_email_verification_page'
    );
    
    // Debug logs
    add_submenu_page(
        'massage-booking',
        'Debug Logs',
        'Debug Logs',
        'manage_options',
        'massage-booking-debug',
        'massage_booking_debug_page'
    );
}

// Run this at a very late priority to override any earlier menu registrations
add_action('admin_menu', 'massage_booking_fixed_admin_menu', 9999);
*/
/**
 * Make sure our dashboard page works
 */
if (!function_exists('massage_booking_dashboard_page')) {
    function massage_booking_dashboard_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="dashboard-stats">';
        echo '<div class="stat-card">';
        echo '<h3>Total Appointments</h3>';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'massage_appointments';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            echo '<div class="stat-number">' . esc_html($total_count) . '</div>';
        } else {
            echo '<div class="stat-number">0</div>';
            echo '<p>Appointments table not found. Please deactivate and reactivate the plugin.</p>';
        }
        
        echo '</div>'; // stat-card
        echo '</div>'; // dashboard-stats
        
        echo '<div class="dashboard-section">';
        echo '<h2>Quick Links</h2>';
        echo '<div class="quick-links">';
        echo '<a href="?page=massage-booking-appointments" class="button">Manage Appointments</a> ';
        echo '<a href="?page=massage-booking-settings" class="button">Settings</a> ';
        echo '<a href="?page=massage-booking-debug" class="button">Debug Information</a>';
        echo '</div>'; // quick-links
        echo '</div>'; // dashboard-section
        
        echo '</div>'; // wrap
    }
}

/**
 * Make sure our logs page works
 */
if (!function_exists('massage_booking_logs_page')) {
    function massage_booking_logs_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'massage_audit_log';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            echo '<div class="notice notice-error"><p>Audit log table does not exist. Please deactivate and reactivate the plugin.</p></div>';
        } else {
            // Get latest logs
            $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 20");
            
            if (empty($logs)) {
                echo '<p>No audit logs found.</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>ID</th><th>Action</th><th>User</th><th>Date/Time</th><th>Details</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($logs as $log) {
                    echo '<tr>';
                    echo '<td>' . esc_html($log->id) . '</td>';
                    echo '<td>' . esc_html($log->action) . '</td>';
                    
                    // Get user name if available
                    $user_name = 'System';
                    if (!empty($log->user_id)) {
                        $user = get_userdata($log->user_id);
                        if ($user) {
                            $user_name = $user->display_name;
                        } else {
                            $user_name = 'User #' . $log->user_id;
                        }
                    }
                    
                    echo '<td>' . esc_html($user_name) . '</td>';
                    
                    // Format date
                    $created_at = isset($log->created_at) ? $log->created_at : (isset($log->timestamp) ? $log->timestamp : '');
                    $date = !empty($created_at) ? date('M j, Y g:i a', strtotime($created_at)) : 'Unknown';
                    
                    echo '<td>' . esc_html($date) . '</td>';
                    echo '<td>' . esc_html($log->details ?: 'No details') . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
        }
        
        echo '</div>'; // wrap
    }
}

/**
 * Make sure our email verification page works
 */
if (!function_exists('massage_booking_email_verification_page')) {
    function massage_booking_email_verification_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        echo '<div class="card">';
        echo '<h2>Email Configuration</h2>';
        
        // Test WordPress email functionality
        echo '<h3>WordPress Email Settings</h3>';
        echo '<p><strong>Admin Email:</strong> ' . esc_html(get_option('admin_email')) . '</p>';
        
        if (class_exists('Massage_Booking_Settings')) {
            $settings = new Massage_Booking_Settings();
            $business_email = $settings->get_setting('business_email', get_option('admin_email'));
            echo '<p><strong>Business Email:</strong> ' . esc_html($business_email) . '</p>';
        }
        
        // Email test form
        echo '<h3>Send Test Email</h3>';
        echo '<form method="post">';
        echo '<p><label for="test_email">Email Address:</label><br>';
        echo '<input type="email" id="test_email" name="test_email" value="' . esc_attr(get_option('admin_email')) . '" required class="regular-text"></p>';
        echo '<p><label for="test_subject">Subject:</label><br>';
        echo '<input type="text" id="test_subject" name="test_subject" value="Massage Booking Email Test" required class="regular-text"></p>';
        echo '<p><label for="test_message">Message:</label><br>';
        echo '<textarea id="test_message" name="test_message" rows="5" class="large-text">This is a test email from the Massage Booking system.</textarea></p>';
        
        wp_nonce_field('massage_booking_email_test');
        echo '<input type="submit" name="send_test_email" class="button button-primary" value="Send Test Email">';
        echo '</form>';
        
        // Process test email submission
        if (isset($_POST['send_test_email']) && check_admin_referer('massage_booking_email_test')) {
            $to = sanitize_email($_POST['test_email']);
            $subject = sanitize_text_field($_POST['test_subject']);
            $message = sanitize_textarea_field($_POST['test_message']);
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            
            $success = wp_mail($to, $subject, $message, $headers);
            
            if ($success) {
                echo '<div class="notice notice-success"><p>Test email sent successfully to ' . esc_html($to) . '.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to send test email. Please check your WordPress email configuration.</p></div>';
            }
        }
        
        echo '</div>'; // card
        echo '</div>'; // wrap
    }
}

/**
 * Make sure our settings page works
 */
if (!function_exists('massage_booking_settings_page')) {
    function massage_booking_settings_page() {
        // Check if we should use the existing function
        if (function_exists('massage_booking_settings_page_original')) {
            return massage_booking_settings_page_original();
        }
        
        // Fallback implementation
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        // Basic settings form
        echo '<form method="post" action="options.php">';
        settings_fields('massage_booking_settings');
        do_settings_sections('massage_booking_settings');
        
        // Business Information
        echo '<h2>Business Information</h2>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row"><label for="business_name">Business Name</label></th>';
        echo '<td><input name="massage_booking_business_name" type="text" id="business_name" value="' . esc_attr(get_option('massage_booking_business_name', 'Massage Therapy Practice')) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row"><label for="business_email">Business Email</label></th>';
        echo '<td><input name="massage_booking_business_email" type="email" id="business_email" value="' . esc_attr(get_option('massage_booking_business_email', get_option('admin_email'))) . '" class="regular-text"></td>';
        echo '</tr>';
        
        echo '</table>';
        
        submit_button('Save Settings');
        echo '</form>';
        
        echo '</div>'; // wrap
        
        // Register settings
        register_setting('massage_booking_settings', 'massage_booking_business_name');
        register_setting('massage_booking_settings', 'massage_booking_business_email');
    }
    
    // Only run if the original function exists
    if (function_exists('massage_booking_settings_page')) {
        function massage_booking_settings_page_original() {
            return massage_booking_settings_page();
        }
    }
}

// Add a notice to alert the admin about the fixes
function massage_booking_admin_notice() {
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p><strong>Massage Booking:</strong> Plugin menu structure and missing functions have been fixed. Please refresh the page to see the updates.</p>';
    echo '</div>';
}
add_action('admin_notices', 'massage_booking_admin_notice');
