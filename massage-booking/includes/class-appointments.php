<?php
// includes/class-appointments.php

// Check if class already exists to prevent redeclaration
if (!class_exists('Massage_Booking_Appointments')) {

class Massage_Booking_Appointments {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function register_rest_routes() {
        register_rest_route('massage-booking/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_public_settings'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('massage-booking/v1', '/available-slots', [
            'methods' => 'GET',
            'callback' => [$this, 'get_available_slots'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('massage-booking/v1', '/appointments', [
            'methods' => 'POST',
            'callback' => [$this, 'create_appointment'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function get_public_settings() {
        $settings = new Massage_Booking_Settings();
        return rest_ensure_response($settings->get_public_settings());
    }
    
    public function get_available_slots($request) {
        $date = sanitize_text_field($request->get_param('date'));
        $duration = intval($request->get_param('duration')) ?: 60;
        
        if (!$date) {
            return new WP_Error('missing_date', 'Date parameter is required', ['status' => 400]);
        }
        
        $db = new Massage_Booking_Database();
        $settings = new Massage_Booking_Settings();
        
        $appointment_date = new DateTime($date);
        $day_of_week = $appointment_date->format('w'); // 0 (Sunday) to 6 (Saturday)
        
        // Check if this day is available
        $working_days = $settings->get_setting('working_days', ['1', '2', '3', '4', '5']);
        if (!in_array($day_of_week, $working_days)) {
            return rest_ensure_response([
                'available' => false,
                'slots' => []
            ]);
        }
        
        // Get day schedule
        $day_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day_name = $day_names[$day_of_week];
        $schedule = $settings->get_setting('schedule', []);
        $day_schedule = $schedule[$day_name] ?? [];
        
        if (empty($day_schedule)) {
            return rest_ensure_response([
                'available' => false,
                'slots' => []
            ]);
        }
        
        // Get existing appointments for this date
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'massage_appointments';
        $date_str = $appointment_date->format('Y-m-d');
        
        $existing_appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_time, end_time, duration FROM $appointments_table WHERE appointment_date = %s AND status = 'confirmed'",
                $date_str
            ),
            ARRAY_A
        );
        
        // Calculate available time slots
        $break_time = $settings->get_setting('break_time', 15);
        $interval = $settings->get_setting('time_slot_interval', 30);
        
        $available_slots = [];
        
        foreach ($day_schedule as $block) {
            $start_time = new DateTime($date . ' ' . $block['from']);
            $end_time = new DateTime($date . ' ' . $block['to']);
            
            // End time needs to be early enough to fit the appointment
            $max_start_time = clone $end_time;
            $max_start_time->modify('-' . ($duration + $break_time) . ' minutes');
            
            if ($max_start_time <= $start_time) {
                // This block is too short for the requested duration
                continue;
            }
            
            $current = clone $start_time;
            
            while ($current <= $max_start_time) {
                $slot_end = clone $current;
                $slot_end->modify('+' . $duration . ' minutes');
                
                $is_available = true;
                
                // Check if slot overlaps with existing appointments
                foreach ($existing_appointments as $appointment) {
                    $appt_start = new DateTime($date . ' ' . $appointment['start_time']);
                    $appt_end = new DateTime($date . ' ' . $appointment['end_time']);
                    
                    // Add break time to appointment end
                    $appt_end_with_break = clone $appt_end;
                    $appt_end_with_break->modify('+' . $break_time . ' minutes');
                    
                    // Check for overlap
                    if (
                        ($current >= $appt_start && $current < $appt_end_with_break) ||
                        ($slot_end > $appt_start && $slot_end <= $appt_end_with_break) ||
                        ($current <= $appt_start && $slot_end >= $appt_end_with_break)
                    ) {
                        $is_available = false;
                        break;
                    }
                }
                
                if ($is_available) {
                    $available_slots[] = [
                        'startTime' => $current->format('H:i'),
                        'endTime' => $slot_end->format('H:i'),
                        'displayTime' => $current->format('g:i A')
                    ];
                }
                
                // Move to next interval
                $current->modify('+' . $interval . ' minutes');
            }
        }
        
        return rest_ensure_response([
            'available' => true,
            'slots' => $available_slots
        ]);
    }
    
    public function create_appointment($request) {
        $params = $request->get_params();
        
        // Validate required fields
        $required_fields = ['fullName', 'email', 'phone', 'appointmentDate', 'startTime', 'duration'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_Error('missing_field', 'Missing required field: ' . $field, ['status' => 400]);
            }
        }
        
        // Prepare appointment data
        $appointment_data = [
            'full_name' => sanitize_text_field($params['fullName']),
            'email' => sanitize_email($params['email']),
            'phone' => sanitize_text_field($params['phone']),
            'appointment_date' => sanitize_text_field($params['appointmentDate']),
            'start_time' => sanitize_text_field($params['startTime']),
            'end_time' => isset($params['endTime']) ? sanitize_text_field($params['endTime']) : $this->calculate_end_time($params['startTime'], $params['duration']),
            'duration' => intval($params['duration']),
            'focus_areas' => isset($params['focusAreas']) ? $params['focusAreas'] : [],
            'pressure_preference' => isset($params['pressurePreference']) ? sanitize_text_field($params['pressurePreference']) : '',
            'special_requests' => isset($params['specialRequests']) ? sanitize_textarea_field($params['specialRequests']) : ''
        ];
        
        // Check if the slot is still available
        if (!$this->check_slot_availability($appointment_data)) {
            return new WP_Error('slot_unavailable', 'This time slot is no longer available', ['status' => 409]);
        }
        
        // Add to Office 365 Calendar if configured
        $calendar_event_id = '';
        if ($this->is_calendar_configured()) {
            $calendar = new Massage_Booking_Calendar();
            $event_result = $calendar->create_event($appointment_data);
            
            if (!is_wp_error($event_result) && isset($event_result['id'])) {
                $calendar_event_id = $event_result['id'];
                $appointment_data['calendar_event_id'] = $calendar_event_id;
            }
        }
        
        // Save appointment
        $db = new Massage_Booking_Database();
        $appointment_id = $db->create_appointment($appointment_data);
        
        if (!$appointment_id) {
            // If calendar event was created but appointment saving failed, delete the event
            if ($calendar_event_id) {
                $calendar = new Massage_Booking_Calendar();
                $calendar->delete_event($calendar_event_id);
            }
            
            return new WP_Error('db_error', 'Failed to save appointment', ['status' => 500]);
        }
        
        // Send confirmation emails - use class-emails.php only if it exists
        $this->send_confirmation_emails($appointment_data);
        do_action('massage_booking_after_appointment_created', $appointment_id, $appointment_data);
        
        return rest_ensure_response([
            'success' => true,
            'appointment_id' => $appointment_id
        ]);
    }
    
    private function calculate_end_time($start_time, $duration) {
        $datetime = new DateTime('2000-01-01 ' . $start_time);
        $datetime->modify('+' . intval($duration) . ' minutes');
        return $datetime->format('H:i');
    }
    
    private function check_slot_availability($appointment_data) {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'massage_appointments';
        
        // Check for overlapping appointments
        $query = $wpdb->prepare(
            "SELECT id FROM $appointments_table 
            WHERE appointment_date = %s 
            AND status = 'confirmed'
            AND (
                (start_time <= %s AND end_time > %s) OR
                (start_time < %s AND end_time >= %s) OR
                (start_time >= %s AND start_time < %s)
            )",
            $appointment_data['appointment_date'],
            $appointment_data['start_time'],
            $appointment_data['start_time'],
            $appointment_data['end_time'],
            $appointment_data['end_time'],
            $appointment_data['start_time'],
            $appointment_data['end_time']
        );
        
        $overlapping = $wpdb->get_var($query);
        
        return !$overlapping;
    }
    
    private function is_calendar_configured() {
        $settings = new Massage_Booking_Settings();
        return (
            $settings->get_setting('ms_client_id') &&
            $settings->get_setting('ms_client_secret') &&
            $settings->get_setting('ms_tenant_id')
        );
    }
    
    private function send_confirmation_emails($appointment_data) {
        // Only use Massage_Booking_Emails class if it exists
        if (class_exists('Massage_Booking_Emails')) {
            $emails = new Massage_Booking_Emails();
            
            // Send email to client
            $emails->send_client_confirmation($appointment_data);
            
            // Send email to therapist/business owner
            $emails->send_therapist_notification($appointment_data);
            
            return true;
        } else {
            // Fallback email sending if the class doesn't exist
            $settings = new Massage_Booking_Settings();
            $business_name = $settings->get_setting('business_name', 'Massage Therapy Practice');
            $business_email = $settings->get_setting('business_email', get_option('admin_email'));
            
            // Format date and time for email
            $date = new DateTime($appointment_data['appointment_date']);
            $formatted_date = $date->format('l, F j, Y');
            
            $start_time = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['start_time']);
            $formatted_time = $start_time->format('g:i A');
            
            // Format focus areas
            $focus_areas = is_array($appointment_data['focus_areas']) 
                ? implode(', ', $appointment_data['focus_areas']) 
                : $appointment_data['focus_areas'];
                
            // Get pressure preference safely
            $pressure_preference = isset($appointment_data['pressure_preference']) 
                ? $appointment_data['pressure_preference'] 
                : 'Medium';
            
            // Client confirmation email
            $client_subject = 'Your Massage Appointment Confirmation';
            $client_message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Appointment Confirmation</h2>
                    <p>Dear {$appointment_data['full_name']},</p>
                    <p>Your massage therapy appointment has been confirmed:</p>
                    
                    <div style='background-color: #f7f7f7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Date:</strong> {$formatted_date}</p>
                        <p><strong>Time:</strong> {$formatted_time}</p>
                        <p><strong>Duration:</strong> {$appointment_data['duration']} minutes</p>
                        <p><strong>Focus Areas:</strong> {$focus_areas}</p>
                        <p><strong>Pressure Preference:</strong> {$pressure_preference}</p>
                    </div>
                    
                    <p>If you need to reschedule or cancel, please contact us at least 24 hours in advance.</p>
                    <p>Thank you for booking with us!</p>
                    
                    <p style='margin-top: 30px;'>
                        Regards,<br>
                        {$business_name}
                    </p>
                </div>
            ";
            
            $client_headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $business_name . ' <' . $business_email . '>'
            ];
            
            wp_mail($appointment_data['email'], $client_subject, $client_message, $client_headers);
            
            // Therapist notification email
            $admin_subject = 'New Massage Appointment';
            $admin_message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>New Appointment Notification</h2>
                    <p>A new appointment has been scheduled:</p>
                    
                    <div style='background-color: #f7f7f7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Client:</strong> {$appointment_data['full_name']}</p>
                        <p><strong>Email:</strong> {$appointment_data['email']}</p>
                        <p><strong>Phone:</strong> {$appointment_data['phone']}</p>
                        <p><strong>Date:</strong> {$formatted_date}</p>
                        <p><strong>Time:</strong> {$formatted_time}</p>
                        <p><strong>Duration:</strong> {$appointment_data['duration']} minutes</p>
                        <p><strong>Focus Areas:</strong> {$focus_areas}</p>
                        <p><strong>Pressure Preference:</strong> {$pressure_preference}</p>
                        <p><strong>Special Requests:</strong> {$appointment_data['special_requests']}</p>
                    </div>
                </div>
            ";
            
            $admin_headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Booking System <' . $business_email . '>'
            ];
            
            wp_mail($business_email, $admin_subject, $admin_message, $admin_headers);
            
            return true;
        }
    }
}

} // End of class_exists check

// Initialize class only once
if (!isset($GLOBALS['massage_booking_appointments_instance']) && class_exists('Massage_Booking_Appointments')) {
    $GLOBALS['massage_booking_appointments_instance'] = new Massage_Booking_Appointments();
}