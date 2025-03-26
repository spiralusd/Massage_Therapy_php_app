<?php
/**
 * Massage Booking Plugin Function Fix
 * 
 * This patch fixes the function redeclaration issue between
 * massage-booking.php and admin-page.php
 * 
 * How to use:
 * 1. Save this file as function-fix.php in your massage-booking plugin directory
 * 2. Include this file at the TOP of your massage-booking.php file
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * Check if a function already exists before declaring it
 * This prevents the fatal error from function redeclaration
 */
if (!function_exists('massage_booking_dashboard_page')) {
    /**
     * Dashboard page for admin
     */
    function massage_booking_dashboard_page() {
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
        
        $db = new Massage_Booking_Database();
        
        // Get upcoming appointments
        $upcoming_appointments = method_exists($db, 'get_upcoming_appointments') ? 
            $db->get_upcoming_appointments(5) : array();
        
        // Get appointment counts
        $pending_count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('pending') : 0;
        
        $confirmed_count = method_exists($db, 'count_appointments_by_status') ? 
            $db->count_appointments_by_status('confirmed') : 0;
        
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        
        // Quick stats cards
        echo '<div class="dashboard-stats">';
        
        // Pending appointments
        echo '<div class="stat-card">';
        echo '<h3>Pending Appointments</h3>';
        echo '<div class="stat-number">' . esc_html($pending_count) . '</div>';
        if ($pending_count > 0) {
            echo '<a href="?page=massage-booking-appointments&status=pending" class="button">View All</a>';
        }
        echo '</div>';
        
        // Today's appointments
        $today_count = method_exists($db, 'get_all_appointments') ? 
            count($db->get_all_appointments('confirmed', $today, $today)) : 0;
        
        echo '<div class="stat-card">';
        echo '<h3>Today\'s Appointments</h3>';
        echo '<div class="stat-number">' . esc_html($today_count) . '</div>';
        if ($today_count > 0) {
            echo '<a href="?page=massage-booking-appointments&status=confirmed&date_from=' . esc_attr($today) . '&date_to=' . esc_attr($today) . '" class="button">View All</a>';
        }
        echo '</div>';
        
        // Tomorrow's appointments
        $tomorrow_count = method_exists($db, 'get_all_appointments') ? 
            count($db->get_all_appointments('confirmed', $tomorrow, $tomorrow)) : 0;
        
        echo '<div class="stat-card">';
        echo '<h3>Tomorrow\'s Appointments</h3>';
        echo '<div class="stat-number">' . esc_html($tomorrow_count) . '</div>';
        if ($tomorrow_count > 0) {
            echo '<a href="?page=massage-booking-appointments&status=confirmed&date_from=' . esc_attr($tomorrow) . '&date_to=' . esc_attr($tomorrow) . '" class="button">View All</a>';
        }
        echo '</div>';
        
        // Total confirmed
        echo '<div class="stat-card">';
        echo '<h3>Confirmed Appointments</h3>';
        echo '<div class="stat-number">' . esc_html($confirmed_count) . '</div>';
        echo '<a href="?page=massage-booking-appointments&status=confirmed" class="button">View All</a>';
        echo '</div>';
        
        echo '</div>'; // dashboard-stats
        
        // Upcoming appointments section
        echo '<div class="dashboard-section">';
        echo '<h2>Upcoming Appointments</h2>';
        
        if (empty($upcoming_appointments)) {
            echo '<p>No upcoming appointments.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>Client</th><th>Date</th><th>Time</th><th>Duration</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($upcoming_appointments as $appointment) {
                echo '<tr>';
                echo '<td>' . esc_html($appointment->client_name ?? '') . '</td>';
                
                // Format date
                $date = new DateTime($appointment->appointment_date);
                echo '<td>' . esc_html($date->format('M j, Y')) . '</td>';
                
                // Format time
                $start_time = new DateTime($appointment->start_time);
                echo '<td>' . esc_html($start_time->format('g:i a')) . '</td>';
                
                echo '<td>' . esc_html($appointment->duration) . ' min</td>';
                
                echo '<td><a href="?page=massage-booking-appointments&action=view&id=' . esc_attr($appointment->id) . '" class="button button-small">View</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>'; // dashboard-section
        
        // Quick links section
        echo '<div class="dashboard-section">';
        echo '<h2>Quick Links</h2>';
        echo '<div class="quick-links">';
        echo '<a href="?page=massage-booking-appointments" class="button">All Appointments</a>';
        echo '<a href="?page=massage-booking-settings" class="button">Settings</a>';
        
        // Only show these links if the features are available
        if (class_exists('Massage_Booking_Calendar') || file_exists(plugin_dir_path(__FILE__) . 'includes/class-calendar-optimized.php')) {
            echo '<a href="?page=massage-booking-settings#integration-settings" class="button">Calendar Integration</a>';
        }
        
        if (class_exists('Massage_Booking_Audit_Log') || file_exists(plugin_dir_path(__FILE__) . 'includes/class-audit-log-optimized.php')) {
            echo '<a href="?page=massage-booking-logs" class="button">Audit Logs</a>';
        }
        
        echo '</div>'; // quick-links
        echo '</div>'; // dashboard-section
        
        echo '</div>'; // wrap
    }
}

/**
 * Create a centralized function loader to prevent multiple declarations
 */
// Add a flag to ensure the function is only loaded once
if (!function_exists('massage_booking_load_admin_functions')) {
    /**
     * Centralized function loader for admin pages with smart prioritization
     */
    function massage_booking_load_admin_functions() {
        // Static flag to prevent multiple executions
        static $admin_functions_loaded = false;
        
        if ($admin_functions_loaded) {
            return;
        }
        
        $admin_functions_loaded = true;
        
        // List of functions to potentially load
        $admin_functions = [
            'massage_booking_dashboard_page',
            'massage_booking_appointments_page',
            'massage_booking_settings_page',
            'massage_booking_logs_page',
            'massage_booking_debug_page',
            'massage_booking_email_verification_page',
            'reset_ms_auth_page',
            'display_appointment_details'
        ];
        
        // Possible source files with priority order
        $source_files = [
            plugin_dir_path(__FILE__) . 'admin/admin-page.php',
            plugin_dir_path(__FILE__) . 'massage-booking-fixes.php'
        ];
        
        // Functions to prioritize in the latest implementation
        $priority_functions = [
            'massage_booking_email_verification_page',
            'massage_booking_logs_page'
        ];
        
        foreach ($admin_functions as $function) {
            // Skip if function already exists
            if (function_exists($function)) {
                // For priority functions, ensure they use the latest implementation
                if (in_array($function, $priority_functions)) {
                    // Remove existing hooks
                    remove_action('admin_menu', $function, 9);
                } else {
                    continue;
                }
            }
            
            // Try loading from source files
            foreach ($source_files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    
                    // If function is now defined, break inner loop
                    if (function_exists($function)) {
                        break;
                    }
                }
            }
        }
    }
}

function remove_header_footer_for_booking_page() {
    if (is_page_template('page-booking.php') || is_page_template('page-booking-direct.php')) {
        remove_all_actions('get_header');
        remove_all_actions('get_footer');
    }
}
add_action('template_redirect', 'remove_header_footer_for_booking_page');

// Register the function loader with a unique priority
//add_action('admin_menu', 'massage_booking_load_admin_functions', 9);