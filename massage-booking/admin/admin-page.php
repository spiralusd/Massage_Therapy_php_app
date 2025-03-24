<?php
/**
 * Comprehensive Admin Page for Massage Booking System
 * 
 * Combines all functionalities from previous implementations
 * with enhanced stability and feature support
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Prevent multiple inclusions and function redefinitions
if (!function_exists('massage_booking_clean_admin_menu')) {
    function massage_booking_clean_admin_menu() {
        // First remove any existing menus to prevent duplicates
        remove_menu_page('massage-booking');
        global $submenu;
        if (isset($submenu['massage-booking'])) {
            unset($submenu['massage-booking']);
        }
        
        // Create main menu
        add_menu_page(
            'Massage Booking',           // Page title
            'Massage Booking',           // Menu title
            'manage_options',            // Capability required
            'massage-booking',           // Menu slug
            'massage_booking_dashboard_page', // Callback function
            'dashicons-calendar-alt',    // Icon
            30                           // Position
        );

        // Add submenu pages
        add_submenu_page(
            'massage-booking',           // Parent slug
            'Dashboard',                 // Page title
            'Dashboard',                 // Menu title
            'manage_options',            // Capability
            'massage-booking',           // Menu slug (same as parent to make it the default page)
            'massage_booking_dashboard_page' // Callback
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
        
        // Email verification utility
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
        
        // MS Graph Auth Reset (hidden in regular menu)
        add_submenu_page(
            null, // No parent - makes it hidden
            'Reset MS Auth',
            'Reset MS Auth',
            'manage_options',
            'massage-booking-reset-ms-auth',
            'reset_ms_auth_page'
        );
    }
    
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
     * Add pending appointments count to admin menu
     */
    function massage_booking_appointments_count_fix() {
        global $submenu;
        
        if (!isset($submenu['massage-booking'])) {
            return;
        }
        
        // Only continue if the database class exists
        if (!class_exists('Massage_Booking_Database')) {
            return;
        }
        
        // Get pending appointments count
        $db = new Massage_Booking_Database();
        if (method_exists($db, 'count_appointments_by_status')) {
            $pending_count = $db->count_appointments_by_status('pending');
            
            if ($pending_count > 0) {
                // Add count to Appointments menu
                foreach ($submenu['massage-booking'] as $key => $menu_item) {
                    if ($menu_item[2] === 'massage-booking-appointments') {
                        $submenu['massage-booking'][$key][0] .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
                        break;
                    }
                }
            }
        }
    }
    add_action('admin_menu', 'massage_booking_appointments_count_fix', 9999);

    /**
     * Logs page content (Placeholder function)
     */
    if (!function_exists('massage_booking_logs_page')) {
        function massage_booking_logs_page() {
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
            }

            // Check if Audit Log class exists
            if (!class_exists('Massage_Booking_Audit_Log')) {
                echo '<div class="wrap massage-booking-admin">';
                echo '<h1>Audit Logs</h1>';
                echo '<div class="notice notice-error"><p>Audit Log functionality is not available.</p></div>';
                echo '</div>';
                return;
            }

            $audit_log = new Massage_Booking_Audit_Log();

            // Pagination
            $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
            $per_page = 50;
            $offset = ($page - 1) * $per_page;

            // Filtering options
            $action_filter = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
            $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
            $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

            // Prepare query arguments
            $query_args = [
                'limit' => $per_page,
                'offset' => $offset,
                'orderby' => 'created_at',
                'order' => 'DESC'
            ];

            // Apply filters
            if (!empty($action_filter)) {
                $query_args['action'] = $action_filter;
            }

            if (!empty($date_from)) {
                $query_args['date_from'] = $date_from;
            }

            if (!empty($date_to)) {
                $query_args['date_to'] = $date_to;
            }

            // Get logs
            $logs = $audit_log->get_logs($query_args);

            // Get total log count for pagination
            $total_logs_args = $query_args;
            unset($total_logs_args['limit'], $total_logs_args['offset']);
            $total_logs = count($audit_log->get_logs($total_logs_args));
            $total_pages = ceil($total_logs / $per_page);

            ?>
            <div class="wrap massage-booking-admin">
                <h1>Audit Logs</h1>

                <form method="get" action="">
                    <input type="hidden" name="page" value="massage-booking-logs">

                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <select name="action">
                                <option value="">All Actions</option>
                                <?php 
                                // Get unique actions for dropdown
                                $unique_actions = array_unique(array_column($logs, 'action'));
                                foreach ($unique_actions as $action): 
                                ?>
                                    <option value="<?php echo esc_attr($action); ?>" <?php selected($action_filter, $action); ?>>
                                        <?php echo esc_html($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">

                            <input type="submit" class="button" value="Filter">

                            <?php if (!empty($action_filter) || !empty($date_from) || !empty($date_to)): ?>
                                <a href="?page=massage-booking-logs" class="button">Reset Filters</a>
                            <?php endif; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links([
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $page
                                ]);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (empty($logs)): ?>
                    <p>No audit logs found.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Object</th>
                                <th>Details</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log['action']); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($log['user_id'])) {
                                            $user = get_userdata($log['user_id']);
                                            echo esc_html($user ? $user->display_name : 'User #' . $log['user_id']);
                                        } else {
                                            echo 'System';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $object_info = [];
                                        if (!empty($log['object_id'])) {
                                            $object_info[] = 'ID: ' . esc_html($log['object_id']);
                                        }
                                        if (!empty($log['object_type'])) {
                                            $object_info[] = 'Type: ' . esc_html($log['object_type']);
                                        }
                                        echo implode(' ', $object_info);
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (is_array($log['details'])) {
                                            echo '<pre>' . esc_html(json_encode($log['details'], JSON_PRETTY_PRINT)) . '</pre>';
                                        } else {
                                            echo esc_html($log['details'] ?? 'No details');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $timestamp = isset($log['created_at']) ? $log['created_at'] : $log['timestamp'];
                                        echo esc_html(date('M j, Y g:i a', strtotime($timestamp))); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /**
     * Email verification page (Placeholder function)
     */
    if (!function_exists('massage_booking_email_verification_page')) {
        function massage_booking_email_verification_page() {
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
            }

            // Instantiate emails class
            $emails = new Massage_Booking_Emails();

            // Perform email configuration test if requested
            $test_results = null;
            if (isset($_POST['test_email_config']) && check_admin_referer('massage_booking_email_test')) {
                $test_results = $emails->validate_email_configuration();
            }

            // Get email diagnostics
            $diagnostics = $emails->diagnose_email_issues();

            ?>
            <div class="wrap massage-booking-admin">
                <h1>Email Verification</h1>

                <div class="email-diagnostics">
                    <h2>Email Configuration Diagnostics</h2>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Configuration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PHP Mail Function</td>
                                <td><?php echo $diagnostics['php_mail_enabled'] ? 'Enabled ✓' : 'Disabled ✗'; ?></td>
                            </tr>
                            <tr>
                                <td>WordPress Mail Function</td>
                                <td><?php echo $diagnostics['wp_mail_function'] ? 'Available ✓' : 'Not Available ✗'; ?></td>
                            </tr>
                            <tr>
                                <td>Admin Email</td>
                                <td><?php echo esc_html($diagnostics['wordpress_email_config']['admin_email']); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <?php if ($test_results): ?>
                        <div class="email-test-results">
                            <h3>Email Test Results</h3>
                            <p>
                                <strong>Result:</strong> 
                                <?php if ($test_results['success']): ?>
                                    <span style="color: green;">Test Email Sent Successfully ✓</span>
                                <?php else: ?>
                                    <span style="color: red;">Test Email Failed ✗</span>
                                <?php endif; ?>
                            </p>
                            <p><strong>Sent To:</strong> <?php echo esc_html($test_results['business_email']); ?></p>
                            <p><strong>Timestamp:</strong> <?php echo esc_html($test_results['timestamp']); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <?php wp_nonce_field('massage_booking_email_test'); ?>
                        <input type="submit" name="test_email_config" class="button button-primary" value="Test Email Configuration">
                    </form>
                </div>

                <div class="advanced-diagnostics">
                    <h2>Advanced Diagnostics</h2>
                    <pre><?php print_r($diagnostics); ?></pre>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Debug page (Placeholder function)
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
     * Reset MS Auth page (Placeholder function)
     */
    function reset_ms_auth_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'massage-booking'));
        }
        
        ?>
        <div class="wrap massage-booking-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="card">
                <h2>Reset Microsoft Authentication</h2>
                <p>Microsoft Graph authentication reset functionality coming soon.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page (Placeholder function)
     
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

    // Use the display_appointment_details function from the original implementation
    if (!function_exists('display_appointment_details')) {
        function display_appointment_details($appointment_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            
            // Get appointment data
            $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $appointment_id));
            
            if (!$appointment) {
                echo '<div class="notice notice-error"><p>Appointment not found.</p></div>';
                return;
            }
            
            // Try to decrypt data if encryption class exists
            if (class_exists('Massage_Booking_Encryption')) {
                $encryption = new Massage_Booking_Encryption();
                
                try {
                    $full_name = $encryption->decrypt($appointment->full_name);
                    $email = $encryption->decrypt($appointment->email);
                    $phone = $encryption->decrypt($appointment->phone);
                    
                    // Focus areas, pressure preference, and special requests
                    $focus_areas = property_exists($appointment, 'focus_areas') ? $encryption->decrypt($appointment->focus_areas) : '';
                    $pressure_preference = property_exists($appointment, 'pressure_preference') ? $encryption->decrypt($appointment->pressure_preference) : '';
                    $special_requests = property_exists($appointment, 'special_requests') ? $encryption->decrypt($appointment->special_requests) : '';
                } catch (Exception $e) {
                    // If decryption fails, use the encrypted values
                    $full_name = $appointment->full_name;
                    $email = $appointment->email;
                    $phone = $appointment->phone;
                    $focus_areas = property_exists($appointment, 'focus_areas') ? $appointment->focus_areas : '';
                    $pressure_preference = property_exists($appointment, 'pressure_preference') ? $appointment->pressure_preference : '';
                    $special_requests = property_exists($appointment, 'special_requests') ? $appointment->special_requests : '';
                }
            } else {
                // No encryption, use as is
                $full_name = $appointment->full_name;
                $email = $appointment->email;
                $phone = $appointment->phone;
                $focus_areas = property_exists($appointment, 'focus_areas') ? $appointment->focus_areas : '';
                $pressure_preference = property_exists($appointment, 'pressure_preference') ? $appointment->pressure_preference : '';
                $special_requests = property_exists($appointment, 'special_requests') ? $appointment->special_requests : '';
            }
            
            // Format date and time
            $date = new DateTime($appointment->appointment_date);
            $start_time = new DateTime($appointment->start_time);
            $end_time = new DateTime($appointment->end_time);
            
            echo '<div class="wrap massage-booking-admin">';
            echo '<h1>Appointment Details</h1>';
            
            echo '<p><a href="?page=massage-booking-appointments" class="button">&laquo; Back to Appointments</a></p>';
            
            echo '<div class="dashboard-section">';
            echo '<h2>Appointment #' . esc_html($appointment->id) . '</h2>';
            
            // Status with actions
            echo '<div class="appointment-status">';
            echo '<p>Status: <strong>' . esc_html(ucfirst($appointment->status)) . '</strong></p>';
            
            echo '<div class="appointment-actions">';
            if ($appointment->status === 'pending') {
                echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=confirm&id=' . $appointment->id, 'massage_booking_confirm_' . $appointment->id) . '" class="button button-primary">Confirm Appointment</a> ';
            }
            
            if ($appointment->status !== 'cancelled') {
                echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=cancel&id=' . $appointment->id, 'massage_booking_cancel_' . $appointment->id) . '" class="button">Cancel Appointment</a> ';
            }
            
            echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=delete&id=' . $appointment->id, 'massage_booking_delete_' . $appointment->id) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this appointment?\');">Delete Appointment</a>';
            echo '</div>'; // End appointment-actions
            echo '</div>'; // End appointment-status
            
            echo '<table class="widefat" style="margin-top: 20px;">';
            echo '<tr><th style="width: 200px;">Client Name</th><td>' . esc_html($full_name) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html($email) . '</td></tr>';
            echo '<tr><th>Phone</th><td>' . esc_html($phone) . '</td></tr>';
            echo '<tr><th>Date</th><td>' . esc_html($date->format('F j, Y')) . '</td></tr>';
            echo '<tr><th>Time</th><td>' . esc_html($start_time->format('g:i a')) . ' - ' . esc_html($end_time->format('g:i a')) . '</td></tr>';
            echo '<tr><th>Duration</th><td>' . esc_html($appointment->duration) . ' minutes</td></tr>';
            
            if (!empty($focus_areas)) {
                echo '<tr><th>Focus Areas</th><td>' . esc_html($focus_areas) . '</td></tr>';
            }
            
            if (!empty($pressure_preference)) {
                echo '<tr><th>Pressure Preference</th><td>' . esc_html($pressure_preference) . '</td></tr>';
            }
            
            if (!empty($special_requests)) {
                echo '<tr><th>Special Requests</th><td>' . esc_html($special_requests) . '</td></tr>';
            }
            
            echo '</table>';
            echo '</div>'; // End dashboard-section
            
            // Calendar Event section if applicable
            if (!empty($appointment->calendar_event_id) && class_exists('Massage_Booking_Calendar')) {
                echo '<div class="dashboard-section">';
                echo '<h2>Calendar Event</h2>';
                echo '<p>This appointment is linked to calendar event ID: ' . esc_html($appointment->calendar_event_id) . '</p>';
                
                // Add calendar sync button
                echo '<form method="post">';
                wp_nonce_field('massage_booking_sync_calendar');
                echo '<input type="hidden" name="appointment_id" value="' . esc_attr($appointment->id) . '">';
                echo '<input type="submit" name="massage_booking_sync_calendar" class="button" value="Sync with Calendar">';
                echo '</form>';
                
                echo '</div>'; // End dashboard-section
            }
            
            // Audit log for this appointment if available
            if (class_exists('Massage_Booking_Audit_Log')) {
                $audit_log = new Massage_Booking_Audit_Log();
                
                if (method_exists($audit_log, 'get_logs')) {
                    $logs = $audit_log->get_logs([
                        'object_id' => $appointment_id,
                        'object_type' => 'appointment',
                        'limit' => 10,
                        'orderby' => 'created_at',
                        'order' => 'DESC'
                    ]);
                    
                    if (!empty($logs)) {
                        echo '<div class="dashboard-section">';
                        echo '<h2>Appointment History</h2>';
                        
                        echo '<table class="widefat striped">';
                        echo '<thead><tr><th>Action</th><th>User</th><th>Date & Time</th><th>Details</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($logs as $log) {
                            echo '<tr>';
                            echo '<td>' . esc_html($log['action']) . '</td>';
                            
                            // User info
                            if (!empty($log['user_id'])) {
                                $user_name = isset($log['user_name']) ? $log['user_name'] : 'User #' . $log['user_id'];
                                echo '<td>' . esc_html($user_name) . '</td>';
                            } else {
                                echo '<td>System</td>';
                            }
                            
                            // Format date
                            $log_date = isset($log['created_at']) ? new DateTime($log['created_at']) : new DateTime();
                            echo '<td>' . esc_html($log_date->format('M j, Y g:i a')) . '</td>';
                            
                            // Format details
                            if (is_array($log['details'])) {
                                echo '<td><pre style="white-space: pre-wrap; max-width: 300px; overflow: auto;">' . esc_html(json_encode($log['details'], JSON_PRETTY_PRINT)) . '</pre></td>';
                            } else {
                                echo '<td>' . esc_html($log['details']) . '</td>';
                            }
                            
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                        echo '</div>'; // End dashboard-section
                    }
                }
            }
            
            echo '</div>'; // End wrap
        }
    }
*/
    /**
     * Add admin stylesheet and scripts
     */
    function massage_booking_admin_scripts($hook) {
        // Only on our plugin pages
        if (strpos($hook, 'massage-booking') === false) {
            return;
        }
        
        wp_enqueue_style('massage-booking-admin-style', MASSAGE_BOOKING_PLUGIN_URL . 'admin/css/admin-style.css', array(), MASSAGE_BOOKING_VERSION);
        wp_enqueue_script('massage-booking-admin-script', MASSAGE_BOOKING_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), MASSAGE_BOOKING_VERSION, true);
        
        // Pass data to JS
        wp_localize_script('massage-booking-admin-script', 'massageBookingAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('massage_booking_admin')
        ));
    }
    add_action('admin_enqueue_scripts', 'massage_booking_admin_scripts');

    /**
     * Function to get pending appointments count via AJAX (for admin dashboard widget)
     */
    function massage_booking_get_pending_count() {
        check_ajax_referer('massage_booking_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $db = new Massage_Booking_Database();
        $count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('pending') : 0;
        
        wp_send_json_success(['count' => $count]);
    }
    add_action('wp_ajax_massage_booking_get_pending_count', 'massage_booking_get_pending_count');

    /**
     * Add dashboard widget
     */
    function massage_booking_add_dashboard_widget() {
        wp_add_dashboard_widget(
            'massage_booking_dashboard_widget',
            'Massage Booking Summary',
            'massage_booking_dashboard_widget_content'
        );
    }
    add_action('wp_dashboard_setup', 'massage_booking_add_dashboard_widget');

    /**
     * Dashboard widget content
     */
    function massage_booking_dashboard_widget_content() {
        if (!class_exists('Massage_Booking_Database')) {
            echo '<p>Error: Database class not loaded.</p>';
            return;
        }
        
        $db = new Massage_Booking_Database();
        
        // Get appointment counts
        $pending_count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('pending') : 0;
        
        $confirmed_count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('confirmed') : 0;
        
        $cancelled_count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('cancelled') : 0;
        
        // Get upcoming appointments
        $upcoming_appointments = method_exists($db, 'get_upcoming_appointments') ? 
            $db->get_upcoming_appointments(5) : array();
        
        echo '<h4>Appointment Statistics</h4>';
        echo '<ul>';
        echo '<li>Pending: <strong id="massage-booking-pending-count">' . $pending_count . '</strong></li>';
        echo '<li>Confirmed: <strong>' . $confirmed_count . '</strong></li>';
        echo '<li>Cancelled: <strong>' . $cancelled_count . '</strong></li>';
        echo '</ul>';
        
        if (!empty($upcoming_appointments)) {
            echo '<h4>Upcoming Appointments</h4>';
            echo '<ul class="massage-booking-upcoming">';
            
            foreach ($upcoming_appointments as $appointment) {
                echo '<li>';
                echo date('M j, Y', strtotime($appointment->appointment_date)) . ' at ' . 
                     date('g:ia', strtotime($appointment->start_time)) . ' - ' . 
                     esc_html($appointment->client_name ?? $appointment->full_name);
                echo '</li>';
            }
            
            echo '</ul>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=massage-booking-appointments') . '" class="button">Manage All Appointments</a></p>';
        
        // Add nonce for AJAX refresh
        echo '<input type="hidden" id="massage_booking_nonce" value="' . wp_create_nonce('massage_booking_admin') . '">';
    }

    /**
     * Prevent template includes in admin
     */
    function massage_booking_prevent_template_includes() {
        if (is_admin()) {
            if (!defined('MASSAGE_BOOKING_IS_ADMIN')) {
                define('MASSAGE_BOOKING_IS_ADMIN', true);
            }
        }
    }
    add_action('init', 'massage_booking_prevent_template_includes', 1);

    /**
     * Ensure admin pages display properly
     */
    function massage_booking_ensure_admin_pages() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'massage-booking') !== false) {
            // Add a class to our admin pages
            add_filter('admin_body_class', function($classes) {
                return $classes . ' massage-booking-admin';
            });
            
            // Make sure our scripts and styles load properly
            add_action('admin_enqueue_scripts', function() {
                wp_enqueue_style('massage-booking-admin-style', MASSAGE_BOOKING_PLUGIN_URL . 'admin/css/admin-style.css', array(), MASSAGE_BOOKING_VERSION);
                wp_enqueue_script('massage-booking-admin-script', MASSAGE_BOOKING_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), MASSAGE_BOOKING_VERSION, true);
            });
        }
    }
    add_action('admin_init', 'massage_booking_ensure_admin_pages');

    /**
     * Add MS Auth reset link to settings page
     */
    function massage_booking_add_reset_link_to_settings($settings_fields) {
        // Add a note to the integration settings section
        if (isset($settings_fields['integration_settings'])) {
            $settings_fields['integration_settings']['fields']['ms_auth_reset_note'] = array(
                'label' => 'Auth Reset',
                'type' => 'html',
                'html' => '<a href="' . admin_url('admin.php?page=massage-booking-reset-ms-auth') . '" class="button">Reset Microsoft Auth</a>
                          <p class="description">Use this if you need to troubleshoot Microsoft Graph authentication issues.</p>'
            );
        }
        
        return $settings_fields;
    }
    add_filter('massage_booking_settings_fields', 'massage_booking_add_reset_link_to_settings');

    /**
     * Helper function to log initialization status
     */
    function massage_booking_log_admin_init() {
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('massage_booking_debug_log_detail')) {
            massage_booking_debug_log_detail(
                'Admin components initialized', 
                [
                    'version' => defined('MASSAGE_BOOKING_VERSION') ? MASSAGE_BOOKING_VERSION : 'Unknown',
                    'timestamp' => current_time('mysql')
                ], 
                'info', 
                'ADMIN'
            );
        }
    }
    add_action('admin_init', 'massage_booking_log_admin_init', 999);

    /**
     * Plugin action links
     */
    function massage_booking_plugin_action_links($links) {
        $custom_links = array(
            '<a href="' . admin_url('admin.php?page=massage-booking') . '">Dashboard</a>',
            '<a href="' . admin_url('admin.php?page=massage-booking-settings') . '">Settings</a>'
        );
        return array_merge($custom_links, $links);
    }
    add_filter('plugin_action_links_massage-booking/massage-booking.php', 'massage_booking_plugin_action_links');
    
    /**
 * Appointments page content with full functionality
 */
    function massage_booking_appointments_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if database class exists
        if (!class_exists('Massage_Booking_Database')) {
            echo '<div class="wrap massage-booking-admin">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>Error: Database class not found.</p></div>';
            echo '</div>';
            return;
        }

        global $wpdb;
        $appointments_table = $wpdb->prefix . 'massage_appointments';

        // Check if appointments table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") === $appointments_table;

        if (!$table_exists) {
            echo '<div class="wrap massage-booking-admin">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>Error: Appointments table not found. Please deactivate and reactivate the plugin.</p></div>';
            echo '</div>';
            return;
        }

        // Handle actions
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

        // Action handling
        if (!empty($action) && !empty($appointment_id)) {
            $db = new Massage_Booking_Database();

            // View single appointment
            if ($action === 'view') {
                display_appointment_details($appointment_id);
                return;
            }

            // Handle status changes
            if (in_array($action, ['confirm', 'cancel', 'delete']) && wp_verify_nonce($nonce, 'massage_booking_' . $action . '_' . $appointment_id)) {
                if ($action === 'confirm' && method_exists($db, 'update_appointment_status')) {
                    $db->update_appointment_status($appointment_id, 'confirmed');
                    echo '<div class="notice notice-success"><p>Appointment confirmed successfully.</p></div>';
                } else if ($action === 'cancel' && method_exists($db, 'update_appointment_status')) {
                    $db->update_appointment_status($appointment_id, 'cancelled');
                    echo '<div class="notice notice-success"><p>Appointment cancelled successfully.</p></div>';
                } else if ($action === 'delete' && method_exists($db, 'delete_appointment')) {
                    $db->delete_appointment($appointment_id);
                    echo '<div class="notice notice-success"><p>Appointment deleted successfully.</p></div>';
                }
            }
        }

        // Pagination
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20; // Number of appointments per page
        $offset = ($page - 1) * $per_page;

        // Filtering options
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Build query
        $where_clauses = [];
        $query_args = [];

        // Status filter
        if (!empty($status_filter)) {
            $where_clauses[] = "status = %s";
            $query_args[] = $status_filter;
        }

        // Date range filter
        if (!empty($date_from)) {
            $where_clauses[] = "appointment_date >= %s";
            $query_args[] = $date_from;
        }

        if (!empty($date_to)) {
            $where_clauses[] = "appointment_date <= %s";
            $query_args[] = $date_to;
        }

        // Search filter
        if (!empty($search)) {
            $where_clauses[] = "(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }

        // Construct base query
        $base_query = "SELECT * FROM $appointments_table";
        $count_query = "SELECT COUNT(*) FROM $appointments_table";

        // Add WHERE clause if needed
        if (!empty($where_clauses)) {
            $where_sql = " WHERE " . implode(" AND ", $where_clauses);
            $base_query .= $where_sql;
            $count_query .= $where_sql;
        }

        // Add ordering
        $base_query .= " ORDER BY appointment_date DESC, start_time DESC";

        // Add pagination
        $base_query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        // Prepare and execute queries
        $query = !empty($query_args) ? $wpdb->prepare($base_query, $query_args) : $base_query;
        $count_query = !empty($query_args) ? $wpdb->prepare($count_query, $query_args) : $count_query;

        // Get appointments
        $appointments = $wpdb->get_results($query);

        // Get total count for pagination
        $total_appointments = $wpdb->get_var($count_query);
        $total_pages = ceil($total_appointments / $per_page);

        // Start output
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';

        // Appointments filter form
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="massage-booking-appointments">';

        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';

        // Status dropdown
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        $statuses = ['confirmed', 'pending', 'cancelled'];
        foreach ($statuses as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . 
                 selected($status_filter, $status, false) . '>' . 
                 esc_html(ucfirst($status)) . '</option>';
        }
        echo '</select>';

        // Date range inputs
        echo ' From: <input type="date" name="date_from" value="' . esc_attr($date_from) . '">';
        echo ' To: <input type="date" name="date_to" value="' . esc_attr($date_to) . '">';

        // Search input
        echo ' <input type="search" name="s" placeholder="Search..." value="' . esc_attr($search) . '">';

        // Submit button
        echo ' <input type="submit" class="button" value="Filter">';

        // Reset button if any filter is active
        if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)) {
            echo ' <a href="?page=massage-booking-appointments" class="button">Reset Filters</a>';
        }

        echo '</div>'; // alignleft actions

        // Pagination links
        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $page
            ]);
            echo '</div>';
        }

        echo '</div>'; // tablenav top

        echo '</form>';

        // Appointments table
        if (empty($appointments)) {
            echo '<p>No appointments found.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>ID</th><th>Client Name</th><th>Date</th><th>Time</th><th>Duration</th><th>Status</th><th>Actions</th>';
            echo '</tr></thead><tbody>';

            foreach ($appointments as $appointment) {
                echo '<tr>';
                echo '<td>' . esc_html($appointment->id) . '</td>';

                // Try to decrypt name if possible
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

                // Format date
                $date = new DateTime($appointment->appointment_date);
                echo '<td>' . esc_html($date->format('M j, Y')) . '</td>';

                // Format time
                $start_time = new DateTime($appointment->start_time);
                echo '<td>' . esc_html($start_time->format('g:i a')) . '</td>';

                echo '<td>' . esc_html($appointment->duration) . ' min</td>';

                // Status with styling
                $status_class = '';
                switch ($appointment->status) {
                    case 'confirmed':
                        $status_class = 'status-confirmed';
                        break;
                    case 'pending':
                        $status_class = 'status-pending';
                        break;
                    case 'cancelled':
                        $status_class = 'status-cancelled';
                        break;
                }
                echo '<td><span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($appointment->status)) . '</span></td>';

                // Actions
                echo '<td>';
                echo '<a href="?page=massage-booking-appointments&action=view&id=' . esc_attr($appointment->id) . '" class="button button-small">View</a> ';

                if ($appointment->status === 'pending') {
                    echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=confirm&id=' . $appointment->id, 'massage_booking_confirm_' . $appointment->id) . '" class="button button-small">Confirm</a> ';
                }

                if ($appointment->status !== 'cancelled') {
                    echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=cancel&id=' . $appointment->id, 'massage_booking_cancel_' . $appointment->id) . '" class="button button-small">Cancel</a> ';
                }

                echo '<a href="' . wp_nonce_url('?page=massage-booking-appointments&action=delete&id=' . $appointment->id, 'massage_booking_delete_' . $appointment->id) . '" class="button button-small" onclick="return confirm(\'Are you sure you want to delete this appointment?\');">Delete</a>';

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Bottom pagination
            if ($total_pages > 1) {
                echo '<div class="tablenav bottom">';
                echo '<div class="tablenav-pages">';
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ]);
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</div>'; // End wrap
    }
}
// Register the menu once with high priority
add_action('admin_menu', 'massage_booking_clean_admin_menu', 9999);