<?php
/**
 * Appointments Page Fix
 * 
 * This file provides a clean implementation of the Appointments page
 * to fix the duplicate entries issue.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Appointments page content
 * 
 * IMPORTANT: Only define the function if it doesn't already exist
 * to prevent the redeclaration error
 */
if (!function_exists('massage_booking_appointments_page')) {
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
            echo ' From: <input type="date" name="date_from" class="date-picker"> ';
            echo ' To: <input type="date" name="date_to" class="date-picker"> ';
            
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
}

/**
 * Display details for a single appointment
 * Only define if it doesn't already exist
 */
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