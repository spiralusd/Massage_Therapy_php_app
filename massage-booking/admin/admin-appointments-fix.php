<?php
/**
 * Fix for Appointments Admin Page
 * 
 * This file should be placed in the plugin's admin directory and included in massage-booking.php
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Add appointments admin page
function massage_booking_appointments_admin_page() {
    add_submenu_page(
        'massage-booking',
        'Appointments',
        'Appointments',
        'manage_options',
        'massage-booking-appointments',
        'massage_booking_appointments_page_content'
    );
}
add_action('admin_menu', 'massage_booking_appointments_admin_page');

// Appointments page content
function massage_booking_appointments_page_content() {
    // Ensure database class is loaded
    if (!class_exists('Massage_Booking_Database')) {
        echo '<div class="notice notice-error"><p>Error: Database class not loaded.</p></div>';
        return;
    }
    
    $db = new Massage_Booking_Database();
    
    // Process actions if any
    if (isset($_GET['action']) && isset($_GET['appointment_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_text_field($_GET['action']);
        $appointment_id = intval($_GET['appointment_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        if (wp_verify_nonce($nonce, 'massage_booking_' . $action . '_' . $appointment_id)) {
            switch ($action) {
                case 'confirm':
                    $db->update_appointment_status($appointment_id, 'confirmed');
                    echo '<div class="notice notice-success"><p>Appointment confirmed successfully.</p></div>';
                    break;
                    
                case 'cancel':
                    $db->update_appointment_status($appointment_id, 'cancelled');
                    echo '<div class="notice notice-success"><p>Appointment cancelled successfully.</p></div>';
                    break;
                    
                case 'delete':
                    $db->delete_appointment($appointment_id);
                    echo '<div class="notice notice-success"><p>Appointment deleted successfully.</p></div>';
                    break;
            }
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    }
    
    // Get all appointments
    $appointments = $db->get_all_appointments();
    
    // Display appointments
    ?>
    <div class="wrap">
        <h1>Appointments Management</h1>
        
        <?php if (empty($appointments)): ?>
            <p>No appointments found.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo esc_html($appointment->id); ?></td>
                            <td><?php echo esc_html($appointment->client_name); ?></td>
                            <td><?php echo esc_html($appointment->client_email); ?></td>
                            <td><?php echo esc_html($appointment->client_phone); ?></td>
                            <td><?php echo esc_html($appointment->appointment_date); ?></td>
                            <td><?php echo esc_html($appointment->start_time); ?></td>
                            <td><?php echo esc_html($appointment->duration); ?> min</td>
                            <td><?php echo esc_html(ucfirst($appointment->status)); ?></td>
                            <td>
                                <?php if ($appointment->status === 'pending'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=massage-booking-appointments&action=confirm&appointment_id=' . $appointment->id), 'massage_booking_confirm_' . $appointment->id); ?>" class="button button-small">Confirm</a>
                                <?php endif; ?>
                                
                                <?php if ($appointment->status !== 'cancelled'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=massage-booking-appointments&action=cancel&appointment_id=' . $appointment->id), 'massage_booking_cancel_' . $appointment->id); ?>" class="button button-small">Cancel</a>
                                <?php endif; ?>
                                
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=massage-booking-appointments&action=delete&appointment_id=' . $appointment->id), 'massage_booking_delete_' . $appointment->id); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this appointment?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Add appointments count to admin menu
function massage_booking_add_appointments_count() {
    global $submenu;
    
    if (!isset($submenu['massage-booking'])) {
        return;
    }
    
    // Ensure database class is loaded
    if (!class_exists('Massage_Booking_Database')) {
        return;
    }
    
    $db = new Massage_Booking_Database();
    $pending_count = $db->count_appointments_by_status('pending');
    
    if ($pending_count > 0) {
        foreach ($submenu['massage-booking'] as $key => $menu_item) {
            if ($menu_item[2] === 'massage-booking-appointments') {
                $submenu['massage-booking'][$key][0] .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
                break;
            }
        }
    }
}
add_action('admin_menu', 'massage_booking_add_appointments_count', 999);

// Function to get pending appointments count via AJAX (for admin dashboard widget)
function massage_booking_get_pending_count() {
    check_ajax_referer('massage_booking_admin', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $db = new Massage_Booking_Database();
    $count = $db->count_appointments_by_status('pending');
    
    wp_send_json_success(['count' => $count]);
}
add_action('wp_ajax_massage_booking_get_pending_count', 'massage_booking_get_pending_count');

// Add dashboard widget
function massage_booking_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'massage_booking_dashboard_widget',
        'Massage Booking Summary',
        'massage_booking_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'massage_booking_add_dashboard_widget');

// Dashboard widget content
function massage_booking_dashboard_widget_content() {
    if (!class_exists('Massage_Booking_Database')) {
        echo '<p>Error: Database class not loaded.</p>';
        return;
    }
    
    $db = new Massage_Booking_Database();
    $pending_count = $db->count_appointments_by_status('pending');
    $confirmed_count = $db->count_appointments_by_status('confirmed');
    $cancelled_count = $db->count_appointments_by_status('cancelled');
    
    $upcoming_appointments = $db->get_upcoming_appointments(5);
    
    echo '<h4>Appointment Statistics</h4>';
    echo '<ul>';
    echo '<li>Pending: <strong>' . $pending_count . '</strong></li>';
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
}

// Add class to modify get_all_appointments function if it doesn't exist
if (class_exists('Massage_Booking_Database') && !method_exists('Massage_Booking_Database', 'get_all_appointments')) {
    class Massage_Booking_Database_Extension extends Massage_Booking_Database {
        public function get_all_appointments() {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY appointment_date DESC, start_time DESC");
        }
        
        public function count_appointments_by_status($status) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            $status = sanitize_text_field($status);
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", $status));
        }
        
        public function get_upcoming_appointments($limit = 5) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            $today = date('Y-m-d');
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE appointment_date >= %s AND status = 'confirmed' ORDER BY appointment_date ASC, start_time ASC LIMIT %d",
                $today,
                $limit
            ));
        }
        
        public function update_appointment_status($appointment_id, $status) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            
            return $wpdb->update(
                $table,
                ['status' => sanitize_text_field($status)],
                ['id' => intval($appointment_id)],
                ['%s'],
                ['%d']
            );
        }
        
        public function delete_appointment($appointment_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            
            return $wpdb->delete(
                $table,
                ['id' => intval($appointment_id)],
                ['%d']
            );
        }
    }
    
    // Replace the database class with our extension
    function massage_booking_replace_database_class() {
        global $massage_booking_database;
        if (isset($massage_booking_database) && is_object($massage_booking_database)) {
            $massage_booking_database = new Massage_Booking_Database_Extension();
        }
    }
    add_action('init', 'massage_booking_replace_database_class', 99);
}
