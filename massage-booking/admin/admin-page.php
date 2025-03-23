<?php
/**
 * Consolidated Admin Page for Massage Booking System
 * 
 * This file combines functionality from:
 * - admin-fix.php
 * - admin-menu-fix.php
 * - appointments-page-fix.php
 * - admin-appointments-fix.php
 * 
 * Handles all admin-related functionality including menu structure, 
 * appointments display, and admin interface fixes.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Register clean menu structure
 */
function massage_booking_clean_admin_menu() {
    // Remove default actions that might be causing duplicates
    remove_action('admin_menu', 'massage_booking_admin_menu');
    remove_action('admin_menu', 'massage_booking_add_debug_menu');
    remove_action('admin_menu', 'massage_booking_add_debug_page');
    remove_action('admin_menu', 'massage_booking_add_email_verification_menu');
    remove_action('admin_menu', 'massage_booking_appointments_admin_page');
    remove_action('admin_menu', 'add_reset_ms_auth_page');
    
    // Create clean menu structure
    add_menu_page(
        'Massage Booking',
        'Massage Booking',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Add submenu items in proper order
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

// Hook our clean menu with a high priority to override others
add_action('admin_menu', 'massage_booking_clean_admin_menu', 999);

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
 * Appointments page content
 */
function massage_booking_appointments_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    echo '<div class="wrap massage-booking-admin">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    
    // Check if database class exists
    if (!class_exists('Massage_Booking_Database')) {
        echo '<div class="notice notice-error"><p>Error: Database class not found.</p></div>';
        echo '</div>';
        return;
    }
    
    global $wpdb;
    $appointments_table = $wpdb->prefix . 'massage_appointments';
    
    // Check if appointments table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") === $appointments_table;
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p>Error: Appointments table not found. Please deactivate and reactivate the plugin.</p></div>';
        echo '</div>';
        return;
    }
    
    // Handle actions
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
    
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
    
    // Initialize database
    $db = new Massage_Booking_Database();
    
    // If get_all_appointments method doesn't exist, use direct database query
    if (!method_exists($db, 'get_all_appointments')) {
        // Let's implement a minimal version here
        $appointments = $wpdb->get_results("SELECT * FROM $appointments_table ORDER BY appointment_date DESC, start_time DESC");
        
        // Add necessary properties
        foreach ($appointments as $key => $appointment) {
            $appointments[$key]->client_name = $appointment->full_name;
            $appointments[$key]->client_email = $appointment->email;
            $appointments[$key]->client_phone = $appointment->phone;
        }
    } else {
        // Get appointments using database class method
        $appointments = $db->get_all_appointments();
    }
    
    // Apply filters if present
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    
    if (!empty($status_filter) || !empty($date_from) || !empty($date_to)) {
        // Get filtered appointments if the method exists
        if (method_exists($db, 'get_all_appointments')) {
            $appointments = $db->get_all_appointments($status_filter, $date_from, $date_to);
        } else {
            // Basic filtering with direct queries if the method doesn't exist
            $where_clauses = [];
            $query_args = [];
            
            if (!empty($status_filter)) {
                $where_clauses[] = "status = %s";
                $query_args[] = $status_filter;
            }
            
            if (!empty($date_from)) {
                $where_clauses[] = "appointment_date >= %s";
                $query_args[] = $date_from;
            }
            
            if (!empty($date_to)) {
                $where_clauses[] = "appointment_date <= %s";
                $query_args[] = $date_to;
            }
            
            if (!empty($where_clauses)) {
                $query = "SELECT * FROM $appointments_table WHERE " . implode(" AND ", $where_clauses) . 
                         " ORDER BY appointment_date DESC, start_time DESC";
                $prepared_query = $wpdb->prepare($query, $query_args);
                $appointments = $wpdb->get_results($prepared_query);
                
                // Add necessary properties
                foreach ($appointments as $key => $appointment) {
                    $appointments[$key]->client_name = $appointment->full_name;
                    $appointments[$key]->client_email = $appointment->email;
                    $appointments[$key]->client_phone = $appointment->phone;
                }
            }
        }
    }
    
    // Display appointments list
    if (empty($appointments)) {
        echo '<p>No appointments found.</p>';
    } else {
        // Add filter options
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="massage-booking-appointments">';
        
        // Status filter
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        echo '<option value="confirmed" ' . selected($status_filter, 'confirmed', false) . '>Confirmed</option>';
        echo '<option value="pending" ' . selected($status_filter, 'pending', false) . '>Pending</option>';
        echo '<option value="cancelled" ' . selected($status_filter, 'cancelled', false) . '>Cancelled</option>';
        echo '</select>';
        
        // Date range
        echo ' From: <input type="date" name="date_from" class="date-picker" value="' . esc_attr($date_from) . '"> ';
        echo ' To: <input type="date" name="date_to" class="date-picker" value="' . esc_attr($date_to) . '"> ';
        
        echo '<input type="submit" class="button" value="Filter">';
        
        // Add reset filters button if filters are active
        if (!empty($status_filter) || !empty($date_from) || !empty($date_to)) {
            echo ' <a href="?page=massage-booking-appointments" class="button">Reset Filters</a>';
        }
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        // Appointments table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Client Name</th><th>Date</th><th>Time</th><th>Duration</th><th>Status</th><th>Actions</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($appointments as $appointment) {
            echo '<tr>';
            echo '<td>' . esc_html($appointment->id) . '</td>';
            echo '<td>' . esc_html($appointment->client_name) . '</td>';
            
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
    }
    
    echo '</div>'; // End wrap
}

/**
 * Display details for a single appointment
 */
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

/**
 * Dashboard page content
 */
function massage_booking_dashboard_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $appointments_table = $wpdb->prefix . 'massage_appointments';
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Check if appointments table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") === $appointments_table;
    
    ?>
    <div class="wrap massage-booking-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if (!$table_exists): ?>
        <div class="notice notice-error">
            <p><strong>Error:</strong> The appointments database table does not exist. Please deactivate and reactivate the plugin to create it.</p>
        </div>
        <?php else: ?>
        
        <?php
        // Get upcoming appointments
        if (class_exists('Massage_Booking_Database')) {
            $db = new Massage_Booking_Database();
            
            if (method_exists($db, 'get_upcoming_appointments')) {
                $upcoming_appointments = $db->get_upcoming_appointments(5);
            } else {
                // Fallback if method doesn't exist
                $upcoming_appointments = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $appointments_table 
                        WHERE appointment_date >= %s AND status = 'confirmed'
                        ORDER BY appointment_date ASC, start_time ASC
                        LIMIT 5",
                        $today
                    )
                );
                
                // Add necessary properties
                foreach ($upcoming_appointments as $key => $appointment) {
                    $upcoming_appointments[$key]->client_name = $appointment->full_name;
                }
            }
            
            // Get counts for statistics
            $total_appointments = $wpdb->get_var("SELECT COUNT(*) FROM $appointments_table");
            $upcoming_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $appointments_table WHERE appointment_date >= %s",
                $today
            ));
            $today_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $appointments_table WHERE appointment_date = %s",
                $today
            ));
            
            // Get pending appointments count
            $pending_count = 0;
            if (method_exists($db, 'count_appointments_by_status')) {
                $pending_count = $db->count_appointments_by_status('pending');
            } else {
                $pending_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $appointments_table WHERE status = %s",
                    'pending'
                ));
            }
        ?>
        
        <div class="dashboard-stats">
            <div class="stat-box">
                <h2>Today's Appointments</h2>
                <span class="stat-number"><?php echo esc_html($today_count); ?></span>
            </div>
            <div class="stat-box">
                <h2>Pending Appointments</h2>
                <span class="stat-number"><?php echo esc_html($pending_count); ?></span>
            </div>
            <div class="stat-box">
                <h2>Upcoming Appointments</h2>
                <span class="stat-number"><?php echo esc_html($upcoming_count); ?></span>
            </div>
            <div class="stat-box">
                <h2>Total Appointments</h2>
                <span class="stat-number"><?php echo esc_html($total_appointments); ?></span>
            </div>
        </div>
        
        <div class="dashboard-section">
            <h2>Upcoming Appointments</h2>
            
            <?php if (!empty($upcoming_appointments)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_appointments as $appointment) : ?>
                            <tr>
                                <td><?php echo esc_html($appointment->client_name); ?></td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($appointment->appointment_date))); ?></td>
                                <td><?php echo esc_html(date('g:i a', strtotime($appointment->start_time))); ?></td>
                                <td><?php echo esc_html($appointment->duration); ?> min</td>
                                <td><span class="status-<?php echo esc_attr($appointment->status); ?>"><?php echo esc_html(ucfirst($appointment->status)); ?></span></td>
                                <td>
                                    <a href="?page=massage-booking-appointments&action=view&id=<?php echo esc_attr($appointment->id); ?>" class="button button-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No upcoming appointments.</p>
            <?php endif; ?>
            
            <p>
                <a href="?page=massage-booking-appointments" class="button button-primary">View All Appointments</a>
            </p>
        </div>
        
        <?php
        } // End if class exists
        ?>
        
        <div class="dashboard-section">
            <h2>System Information</h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th style="width: 200px;">Plugin Version</th>
                        <td><?php echo MASSAGE_BOOKING_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version</th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Database Status</th>
                        <td>
                            <?php echo $table_exists ? 
                                '<span style="color: green;">Connected</span>' : 
                                '<span style="color: red;">Error: Appointments table not found</span>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Booking Page</th>
                        <td>
                            <?php 
                            $booking_page_id = get_option('massage_booking_page_id');
                            if ($booking_page_id) {
                                echo '<a href="' . get_permalink($booking_page_id) . '" target="_blank">' . 
                                    get_the_title($booking_page_id) . '</a>';
                            } else {
                                echo '<span style="color: red;">Not set - Please create a page with the Massage Booking Form template</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Calendar Integration</th>
                        <td>
                            <?php 
                            $is_calendar_configured = false;
                            if (class_exists('Massage_Booking_MS_Graph_Auth')) {
                                $ms_graph_auth = new Massage_Booking_MS_Graph_Auth();
                                $is_calendar_configured = method_exists($ms_graph_auth, 'has_valid_token') ? 
                                    $ms_graph_auth->has_valid_token() : 
                                    !empty(get_option('massage_booking_ms_refresh_token'));
                            }
                            
                            echo $is_calendar_configured ? 
                                '<span style="color: green;">Connected</span>' : 
                                '<span style="color: orange;">Not Connected</span> <a href="?page=massage-booking-settings#integration-settings" class="button button-small">Configure</a>';
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="dashboard-section">
            <h2>Quick Links</h2>
            <a href="?page=massage-booking-settings" class="button button-primary">Manage Settings</a>
            <a href="?page=massage-booking-logs" class="button button-primary">View Audit Logs</a>
            <?php if (class_exists('Massage_Booking_Backup')): ?>
                <?php do_action('massage_booking_admin_backup_button'); ?>
            <?php endif; ?>
            <a href="?page=massage-booking-debug" class="button button-primary">Debug Tools</a>
        </div>
        <?php endif; // End table exists check ?>
    </div>
    <?php
}

/**
 * Add admin stylesheet and scripts
 */
function massage_booking_admin_scripts() {
    // Only on our plugin pages
    $screen = get_current_screen();
    if (strpos($screen->id, 'massage-booking') !== false) {
        wp_enqueue_style('massage-booking-admin-style', MASSAGE_BOOKING_PLUGIN_URL . 'admin/css/admin-style.css', array(), MASSAGE_BOOKING_VERSION);
        wp_enqueue_script('massage-booking-admin-script', MASSAGE_BOOKING_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), MASSAGE_BOOKING_VERSION, true);
        
        // Pass data to JS
        wp_localize_script('massage-booking-admin-script', 'massageBookingAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('massage_booking_admin')
        ));
    }
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
                 esc_html($appointment->client_name);
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    echo '<p><a href="' . admin_url('admin.php?page=massage-booking-appointments') . '" class="button">Manage All Appointments</a></p>';
    
    // Add nonce for AJAX refresh
    echo '<input type="hidden" id="massage_booking_nonce" value="' . wp_create_nonce('massage_booking_admin') . '">';
}

/**
 * Fix for potential include/require statements in plugin files
 */
function massage_booking_prevent_template_includes() {
    if (is_admin()) {
        // Define constant to prevent includes in admin
        if (!defined('MASSAGE_BOOKING_IS_ADMIN')) {
            define('MASSAGE_BOOKING_IS_ADMIN', true);
        }
    }
}
add_action('init', 'massage_booking_prevent_template_includes', 1);

/**
 * Make sure our admin pages display properly
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
 * Check shortcode rendering in admin - safer approach
 */
function massage_booking_modify_shortcode_callbacks() {
    global $shortcode_tags;
    
    if (is_admin() && is_array($shortcode_tags)) {
        // Check if our shortcodes exist and modify them to return empty in admin
        $shortcode_names = array('massage_booking_form', 'massage_business_hours', 'massage_services');
        
        foreach ($shortcode_names as $name) {
            if (isset($shortcode_tags[$name])) {
                add_filter('shortcode_atts_' . $name, function($out, $pairs, $atts) {
                    if (is_admin()) {
                        return array(); // Return empty array in admin
                    }
                    return $out;
                }, 10, 3);
            }
        }
    }
}
add_action('admin_init', 'massage_booking_modify_shortcode_callbacks');

/**
 * Add a visible MS Auth reset link to the main Settings page
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
 * Unregister duplicate actions for AJAX handlers
 */
function massage_booking_clean_ajax_handlers() {
    // Identify and clean up duplicate AJAX handlers for appointment forms
    $handlers_to_check = [
        'massage_booking_handle_appointment',
        'massage_booking_simple_appointment_handler',
        'massage_booking_improved_appointment_handler',
        'massage_booking_unified_appointment_handler'
    ];
    
    $action_name = 'massage_booking_create_appointment';
    
    foreach ($handlers_to_check as $handler) {
        if (function_exists($handler)) {
            // For logged in users
            if (has_action('wp_ajax_' . $action_name, $handler)) {
                remove_action('wp_ajax_' . $action_name, $handler);
            }
            
            // For non-logged in users
            if (has_action('wp_ajax_nopriv_' . $action_name, $handler)) {
                remove_action('wp_ajax_nopriv_' . $action_name, $handler);
            }
        }
    }
    
    // Re-add the unified handler only if it exists
    if (function_exists('massage_booking_unified_appointment_handler')) {
        add_action('wp_ajax_' . $action_name, 'massage_booking_unified_appointment_handler');
        add_action('wp_ajax_nopriv_' . $action_name, 'massage_booking_unified_appointment_handler');
    }
}

// Clean up AJAX handlers on init with high priority
add_action('init', 'massage_booking_clean_ajax_handlers', 999);

/**
 * Helper function to log initialization status
 */
function massage_booking_log_fixed_init() {
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('massage_booking_debug_log_detail')) {
        massage_booking_debug_log_detail(
            'Fixed admin components initialized', 
            [
                'version' => MASSAGE_BOOKING_VERSION,
                'timestamp' => current_time('mysql')
            ], 
            'info', 
            'ADMIN'
        );
    }
}
add_action('admin_init', 'massage_booking_log_fixed_init', 999);