<?php
/**
 * Admin Page for Massage Booking System
 * Merged version combining existing functionality with improved styling and structure
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Add admin menu
function massage_booking_admin_menu() {
    // Main menu item
    add_menu_page(
        'Massage Booking',
        'Massage Booking',
        'manage_options',
        'massage-booking',
        'massage_booking_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Submenu items
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
    
    // Additional menu for audit logs (HIPAA compliance)
    add_submenu_page(
        'massage-booking',
        'Audit Logs',
        'Audit Logs',
        'manage_options',
        'massage-booking-logs',
        'massage_booking_logs_page'
    );
}
add_action('admin_menu', 'massage_booking_admin_menu');

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

// Dashboard page
function massage_booking_dashboard_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $appointments_table = $wpdb->prefix . 'massage_appointments';
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Get upcoming appointments
    $upcoming_appointments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $appointments_table 
            WHERE appointment_date >= %s
            ORDER BY appointment_date ASC, start_time ASC
            LIMIT 5",
            $today
        ),
        ARRAY_A
    );
    
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
    
    // Initialize encryption for decrypting data
    if (class_exists('Massage_Booking_Encryption')) {
        require_once(MASSAGE_BOOKING_PLUGIN_DIR . 'includes/class-encryption-optimized.php');
        $encryption = new Massage_Booking_Encryption();
    } else {
        // Fallback if encryption class doesn't exist or is differently named
        $encryption = null;
    }
    
    // Check if database table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$appointments_table'") === $appointments_table;
    
    ?>
    <div class="wrap massage-booking-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if (!$table_exists): ?>
        <div class="notice notice-error">
            <p><strong>Error:</strong> The appointments database table does not exist. Please deactivate and reactivate the plugin to create it.</p>
        </div>
        <?php else: ?>
        
        <div class="dashboard-stats">
            <div class="stat-box">
                <h2>Today's Appointments</h2>
                <span class="stat-number"><?php echo esc_html($today_count); ?></span>
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
            
            <?php if ($upcoming_appointments) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_appointments as $appointment) : 
                            // Safely decrypt data if encryption class is available
                            $client_name = $encryption ? $encryption->decrypt($appointment['full_name']) : $appointment['full_name'];
                            $client_phone = $encryption ? $encryption->decrypt($appointment['phone']) : $appointment['phone'];
                        ?>
                            <tr>
                                <td><?php echo esc_html($client_name); ?></td>
                                <td><?php echo esc_html(date('F j, Y', strtotime($appointment['appointment_date']))); ?></td>
                                <td><?php echo esc_html(date('g:i A', strtotime($appointment['start_time']))); ?></td>
                                <td><?php echo esc_html($appointment['duration']); ?> min</td>
                                <td><?php echo esc_html($client_phone); ?></td>
                                <td>
                                    <a href="?page=massage-booking-appointments&action=view&id=<?php echo esc_attr($appointment['id']); ?>" class="button button-small">View</a>
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
                </tbody>
            </table>
        </div>
        
        <div class="dashboard-section">
            <h2>Quick Links</h2>
            <a href="?page=massage-booking-settings" class="button button-primary">Manage Schedule Settings</a>
            <a href="?page=massage-booking-logs" class="button button-primary">View Audit Logs</a>
            <?php if (class_exists('Massage_Booking_Backup')): ?>
                <?php do_action('massage_booking_admin_backup_button'); ?>
            <?php endif; ?>
        </div>
        <?php endif; // End table exists check ?>
    </div>
    <?php
}

// Appointments page
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
        echo '<option value="confirmed">Confirmed</option>';
        echo '<option value="pending">Pending</option>';
        echo '<option value="cancelled">Cancelled</option>';
        echo '</select>';
        
        // Date range
        echo ' From: <input type="date" name="date_from"> ';
        echo ' To: <input type="date" name="date_to"> ';
        
        echo '<input type="submit" class="button" value="Filter">';
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
    
    echo '</div>'; // End wrap
}

function massage_booking_logs_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $logs_table = $wpdb->prefix . 'massage_audit_log';
    
    // Ensure the Audit_Log class is loaded
    if (!class_exists('Massage_Booking_Audit_Log')) {
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div class="notice notice-error"><p>Error: Audit Log class not found. Please check your plugin files.</p></div>';
        echo '</div>';
        return;
    }
    
    // Instantiate audit log class
    $audit_log = new Massage_Booking_Audit_Log();
    
    // Make sure schema is up to date
    $audit_log->check_schema();
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
    
    echo '<div class="wrap massage-booking-admin">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p>Error: Audit log table does not exist. Please deactivate and reactivate the plugin.</p></div>';
        
        // Add a button to create the table
        echo '<form method="post">';
        wp_nonce_field('massage_booking_create_audit_table');
        echo '<p><input type="submit" name="massage_booking_create_audit_table" class="button button-primary" value="Create Audit Log Table"></p>';
        echo '</form>';
        
        if (isset($_POST['massage_booking_create_audit_table']) && check_admin_referer('massage_booking_create_audit_table')) {
            $audit_log->create_table();
            echo '<div class="notice notice-success"><p>Audit log table created successfully. Please refresh the page.</p></div>';
        }
        
        echo '</div>';
        return;
    }
    
    // Handle filters
    $filter_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';
    $filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : '';
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    
    // Build filter conditions for the count query
    $where = [];
    $where_args = [];
    
    if (!empty($filter_action)) {
        $where[] = 'action = %s';
        $where_args[] = $filter_action;
    }
    
    if (!empty($filter_user)) {
        $where[] = 'user_id = %d';
        $where_args[] = $filter_user;
    }
    
    // Check which date column exists
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $logs_table");
    $date_col = in_array('created_at', $columns) ? 'created_at' : 'timestamp';
    
    if (!empty($filter_date_from)) {
        $where[] = "$date_col >= %s";
        $where_args[] = $filter_date_from . ' 00:00:00';
    }
    
    if (!empty($filter_date_to)) {
        $where[] = "$date_col <= %s";
        $where_args[] = $filter_date_to . ' 23:59:59';
    }
    
    // Get total count
    if (!empty($where)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM $logs_table $where_sql";
        $prepared_query = $wpdb->prepare($query, $where_args);
        $total_logs = $wpdb->get_var($prepared_query);
    } else {
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
    }
    
    // Pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $total_pages = ceil($total_logs / $per_page);
    
    // Build arguments for get_logs
    $log_args = [
        'limit' => $per_page,
        'offset' => $offset,
        'orderby' => $date_col,
        'order' => 'DESC'
    ];
    
    if (!empty($filter_action)) {
        $log_args['action'] = $filter_action;
    }
    
    if (!empty($filter_user)) {
        $log_args['user_id'] = $filter_user;
    }
    
    if (!empty($filter_date_from)) {
        $log_args['date_from'] = $filter_date_from . ' 00:00:00';
    }
    
    if (!empty($filter_date_to)) {
        $log_args['date_to'] = $filter_date_to . ' 23:59:59';
    }
    
    // Get logs using the audit log class
    $logs = $audit_log->get_logs($log_args);
    
    // Get all distinct actions for filter dropdown
    $actions = $wpdb->get_col("SELECT DISTINCT action FROM $logs_table ORDER BY action ASC");
    
    // Get users for filter dropdown
    $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $logs_table WHERE user_id IS NOT NULL AND user_id > 0");
    $users = [];
    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $users[$user_id] = $user->display_name . ' (' . $user->user_login . ')';
        }
    }
    
    echo '<div class="dashboard-section">';
    echo '<p>Showing audit logs for HIPAA compliance. Total entries: ' . esc_html($total_logs) . '</p>';
    
    // Filter form
    echo '<form method="get" action="' . admin_url('admin.php') . '" class="audit-log-filters">';
    echo '<input type="hidden" name="page" value="massage-booking-logs">';
    echo '<div class="tablenav top">';
    echo '<div class="alignleft actions">';
    
    // Action filter
    echo '<select name="filter_action">';
    echo '<option value="">All Actions</option>';
    foreach ($actions as $action) {
        echo '<option value="' . esc_attr($action) . '" ' . selected($filter_action, $action, false) . '>' . esc_html($action) . '</option>';
    }
    echo '</select>';
    
    // User filter
    echo '<select name="filter_user">';
    echo '<option value="">All Users</option>';
    foreach ($users as $id => $name) {
        echo '<option value="' . esc_attr($id) . '" ' . selected($filter_user, $id, false) . '>' . esc_html($name) . '</option>';
    }
    echo '</select>';
    
    // Date range
    echo '<input type="date" name="filter_date_from" value="' . esc_attr($filter_date_from) . '" placeholder="From date">';
    echo '<input type="date" name="filter_date_to" value="' . esc_attr($filter_date_to) . '" placeholder="To date">';
    
    echo '<input type="submit" class="button" value="Filter">';
    if (!empty($filter_action) || !empty($filter_user) || !empty($filter_date_from) || !empty($filter_date_to)) {
        echo ' <a href="' . admin_url('admin.php?page=massage-booking-logs') . '" class="button">Reset</a>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</form>';
    
    if (!empty($logs)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Action</th><th>User</th><th>Date & Time</th><th>IP Address</th><th>Details</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log['id']) . '</td>';
            echo '<td>' . esc_html($log['action']) . '</td>';
            
            // User info
            if (!empty($log['user_id'])) {
                $user_name = isset($log['user_name']) ? $log['user_name'] : 'User #' . $log['user_id'];
                echo '<td>' . esc_html($user_name) . '</td>';
            } else {
                echo '<td>System</td>';
            }
            
            // Format date nicely - try created_at first, fall back to timestamp
            if (isset($log['created_at'])) {
                $date = new DateTime($log['created_at']);
            } else if (isset($log['timestamp'])) {
                $date = new DateTime($log['timestamp']);
            } else {
                $date = new DateTime();
            }
            
            $formatted_date = $date->format('M j, Y g:i a');
            echo '<td>' . esc_html($formatted_date) . '</td>';
            
            echo '<td>' . esc_html($log['ip_address']) . '</td>';
            
            // Format details
            if (is_array($log['details'])) {
                echo '<td><pre style="white-space: pre-wrap; max-width: 300px; overflow: auto;">' . esc_html(json_encode($log['details'], JSON_PRETTY_PRINT)) . '</pre></td>';
            } else {
                echo '<td>' . esc_html($log['details']) . '</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';
            echo '<span class="pagination-links">';
            
            // First page link
            if ($page > 1) {
                echo '<a class="first-page button" href="' . esc_url(add_query_arg(['paged' => 1, 'filter_action' => $filter_action, 'filter_user' => $filter_user, 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to])) . '"><span aria-hidden="true">«</span></a>';
            }
            
            // Previous page link
            if ($page > 1) {
                echo '<a class="prev-page button" href="' . esc_url(add_query_arg(['paged' => $page - 1, 'filter_action' => $filter_action, 'filter_user' => $filter_user, 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to])) . '"><span aria-hidden="true">‹</span></a>';
            }
            
            echo '<span class="paging-input">' . $page . ' of <span class="total-pages">' . $total_pages . '</span></span>';
            
            // Next page link
            if ($page < $total_pages) {
                echo '<a class="next-page button" href="' . esc_url(add_query_arg(['paged' => $page + 1, 'filter_action' => $filter_action, 'filter_user' => $filter_user, 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to])) . '"><span aria-hidden="true">›</span></a>';
            }
            
            // Last page link
            if ($page < $total_pages) {
                echo '<a class="last-page button" href="' . esc_url(add_query_arg(['paged' => $total_pages, 'filter_action' => $filter_action, 'filter_user' => $filter_user, 'filter_date_from' => $filter_date_from, 'filter_date_to' => $filter_date_to])) . '"><span aria-hidden="true">»</span></a>';
            }
            
            echo '</span>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<p>No audit logs found.</p>';
    }
    
    echo '</div>'; // End dashboard-section
    
    // Add cleanup section for admin
    $settings = new Massage_Booking_Settings();
    $retention_days = $settings->get_setting('audit_log_retention_days', 90);
    
    echo '<div class="dashboard-section">';
    echo '<h2>Audit Log Maintenance</h2>';
    echo '<p>Audit logs are automatically cleaned up after ' . esc_html($retention_days) . ' days.</p>';
    
    // Add manual cleanup form
    echo '<form method="post" action="">';
    wp_nonce_field('massage_booking_audit_cleanup');
    echo '<p>';
    echo '<input type="number" name="cleanup_days" value="' . esc_attr($retention_days) . '" min="30" max="365" class="small-text"> ';
    echo '<input type="submit" name="massage_booking_audit_cleanup" class="button" value="Clean up logs older than this many days">';
    echo '</p>';
    echo '</form>';
    
    // Process cleanup if requested
    if (isset($_POST['massage_booking_audit_cleanup']) && check_admin_referer('massage_booking_audit_cleanup')) {
        $days = isset($_POST['cleanup_days']) ? intval($_POST['cleanup_days']) : $retention_days;
        if ($days >= 30) {
            $deleted = $audit_log->cleanup_old_logs($days);
            echo '<div class="notice notice-success inline"><p>Cleaned up ' . esc_html($deleted) . ' log entries older than ' . esc_html($days) . ' days.</p></div>';
        }
    }
    
    // Add test log entry button (for debugging)
    echo '<form method="post" action="" style="margin-top: 20px;">';
    wp_nonce_field('massage_booking_test_log');
    echo '<p>';
    echo '<input type="submit" name="massage_booking_test_log" class="button" value="Create Test Log Entry">';
    echo ' <em>This will create a test log entry for debugging purposes.</em>';
    echo '</p>';
    echo '</form>';
    
    // Create test log if requested
    if (isset($_POST['massage_booking_test_log']) && check_admin_referer('massage_booking_test_log')) {
        $result = $audit_log->log_action('test_action', get_current_user_id(), null, 'test', [
            'test_value' => 'This is a test log entry',
            'created_at' => current_time('mysql'),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
        ]);
        
        if ($result) {
            echo '<div class="notice notice-success inline"><p>Test log entry created successfully with ID: ' . esc_html($result) . '</p></div>';
        } else {
            echo '<div class="notice notice-error inline"><p>Failed to create test log entry.</p></div>';
        }
    }
    
    echo '</div>'; // End dashboard-section
    echo '</div>'; // End wrap
}
