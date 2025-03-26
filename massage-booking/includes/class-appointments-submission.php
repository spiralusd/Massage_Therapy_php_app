<?php
/**
 * Comprehensive Appointment Submission Handler for Massage Booking System
 */
class Massage_Booking_Appointments_Submission {
    public function __construct() {
        // Register AJAX actions for both logged-in and non-logged-in users
        add_action('wp_ajax_massage_booking_create_appointment', [$this, 'create_appointment']);
        add_action('wp_ajax_nopriv_massage_booking_create_appointment', [$this, 'create_appointment']);
        
        // Register REST API routes for appointment submission
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Register REST API routes for appointment submission
     */
    public function register_rest_routes() {
        register_rest_route('massage-booking/v1', '/appointments', [
            'methods' => ['POST', 'OPTIONS'],
            'callback' => [$this, 'create_appointment_rest'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * REST API entry point for appointment creation
     */
    public function create_appointment_rest($request) {
        // Handle preflight requests for CORS
        if ($request->get_method() === 'OPTIONS') {
            return rest_ensure_response('OK');
        }

        // Merge POST data from the request
        $data = $request->get_params();
        $_POST = array_merge($_POST, $data);

        // Use existing AJAX method
        return $this->create_appointment(true);
    }

    /**
     * Enhanced appointment creation with comprehensive error handling
     * 
     * @param bool $is_rest Optional flag to indicate REST API call
     * @return WP_REST_Response|mixed
     */
    public function create_appointment($is_rest = false) {
        // Use a more robust nonce check
        $nonce = $is_rest ? 
            $this->get_rest_nonce_from_request() : 
            $_POST['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            $error = [
                'message' => 'Invalid or missing security token',
                'code' => 'invalid_nonce'
            ];
            return $this->send_error_response($error, $is_rest);
        }

        // Start logging submission attempt
        $this->log_submission_attempt();

        try {
            // Validate required fields
            $this->validate_submission_data($_POST);

            // Prepare appointment data
            $appointment_data = $this->sanitize_appointment_data($_POST);

            // Check slot availability
            $this->check_slot_availability($appointment_data);

            // Create appointment in database
            $db = new Massage_Booking_Database();
            $appointment_id = $db->create_appointment($appointment_data);

            if (!$appointment_id) {
                throw new Exception('Failed to create appointment in database', 500);
            }

            // Attempt calendar sync
            $this->sync_with_calendar($appointment_data, $appointment_id);

            // Send confirmation emails
            $this->send_confirmation_emails($appointment_data);

            // Store appointment data in transient for thank you page
            $this->store_appointment_for_confirmation($appointment_data, $appointment_id);

            // Prepare response with thank you page
            $thank_you_page_id = get_option('massage_booking_thank_you_page_id');
            $redirect_url = $thank_you_page_id ? get_permalink($thank_you_page_id) : home_url();

            // Log successful submission
            $this->log_submission_success($appointment_id);

            // Return success response
            return $this->send_success_response([
                'appointment_id' => $appointment_id,
                'redirect' => $redirect_url
            ], $is_rest);

        } catch (Exception $e) {
            // Log and return error
            $this->log_submission_error($e);
            $error = [
                'message' => $e->getMessage(),
                'code' => $e->getCode() ?: 'submission_error'
            ];
            return $this->send_error_response($error, $is_rest);
        }
    }

    /**
     * Validate submission data
     * 
     * @param array $data Submission data to validate
     * @throws Exception If validation fails
     */
    private function validate_submission_data($data) {
        $required_fields = [
            'fullName', 'email', 'phone', 
            'appointmentDate', 'startTime', 
            'duration', 'pressurePreference'
        ];

        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field", 400);
            }
        }

        // Email validation
        if (!is_email($data['email'])) {
            throw new Exception('Invalid email address', 400);
        }

        // Date validation
        $appointment_date = sanitize_text_field($data['appointmentDate']);
        $date = DateTime::createFromFormat('Y-m-d', $appointment_date);
        if (!$date || $date->format('Y-m-d') !== $appointment_date) {
            throw new Exception('Invalid date format', 400);
        }

        // Time validation
        $start_time = sanitize_text_field($data['startTime']);
        $time_pattern = '/^([01]\d|2[0-3]):([0-5]\d)$/';
        if (!preg_match($time_pattern, $start_time)) {
            throw new Exception('Invalid time format', 400);
        }
    }

    /**
     * Sanitize and prepare appointment data
     * 
     * @param array $data Raw submission data
     * @return array Sanitized appointment data
     */
    private function sanitize_appointment_data($data) {
        // Safely decode focus areas
        $focus_areas = isset($data['focusAreas']) ? 
            (is_array($data['focusAreas']) ? $data['focusAreas'] : json_decode($data['focusAreas'], true)) : 
            [];

        // Prepare appointment data
        return [
            'full_name' => sanitize_text_field($data['fullName']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'appointment_date' => sanitize_text_field($data['appointmentDate']),
            'start_time' => sanitize_text_field($data['startTime']),
            'end_time' => sanitize_text_field($data['endTime'] ?? $this->calculate_end_time($data['startTime'], $data['duration'])),
            'duration' => intval($data['duration']),
            'focus_areas' => $focus_areas,
            'pressure_preference' => sanitize_text_field($data['pressurePreference']),
            'special_requests' => sanitize_textarea_field($data['specialRequests'] ?? '')
        ];
    }

    /**
     * Calculate end time based on start time and duration
     * 
     * @param string $start_time Start time in H:i format
     * @param int $duration Duration in minutes
     * @return string End time in H:i format
     */
    private function calculate_end_time($start_time, $duration) {
        $start = DateTime::createFromFormat('H:i', $start_time);
        
        if (!$start) {
            // Fallback to current time if parsing fails
            $start = new DateTime();
        }
        
        $end = clone $start;
        $end->modify("+{$duration} minutes");
        
        return $end->format('H:i');
    }

    /**
     * Check slot availability before booking
     * 
     * @param array $appointment_data Prepared appointment data
     * @throws Exception If slot is not available
     */
    private function check_slot_availability($appointment_data) {
        $db = new Massage_Booking_Database();
        
        if (!method_exists($db, 'check_slot_availability')) {
            // If method doesn't exist, skip availability check
            return;
        }
        
        $is_available = $db->check_slot_availability(
            $appointment_data['appointment_date'], 
            $appointment_data['start_time'], 
            $appointment_data['duration']
        );
        
        if (!$is_available) {
            throw new Exception('Selected time slot is no longer available', 409);
        }
    }

    /**
     * Sync appointment with calendar (if integration is available)
     * 
     * @param array $appointment_data Prepared appointment data
     * @param int $appointment_id Created appointment ID
     */
    private function sync_with_calendar($appointment_data, $appointment_id) {
        // Check if calendar integration is available
        if (class_exists('Massage_Booking_Calendar')) {
            try {
                $calendar = new Massage_Booking_Calendar();
                if (method_exists($calendar, 'create_event')) {
                    $calendar->create_event($appointment_data);
                }
            } catch (Exception $e) {
                // Log calendar sync error but don't prevent booking
                error_log('Calendar sync failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Send confirmation emails
     * 
     * @param array $appointment_data Prepared appointment data
     */
    private function send_confirmation_emails($appointment_data) {
        // Check if email handling is available
        if (class_exists('Massage_Booking_Emails')) {
            $emails = new Massage_Booking_Emails();
            
            try {
                // Send client confirmation
                $emails->send_client_confirmation($appointment_data);
                
                // Send therapist notification
                $emails->send_therapist_notification($appointment_data);
            } catch (Exception $e) {
                // Log email sending errors
                error_log('Email sending failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Store appointment data in transient for thank you page
     * 
     * @param array $appointment_data Prepared appointment data
     * @param int $appointment_id Created appointment ID
     */
    private function store_appointment_for_confirmation($appointment_data, $appointment_id) {
        // Store data for 1 hour
        set_transient(
            'massage_booking_confirmation_' . get_current_user_id(), 
            array_merge($appointment_data, ['id' => $appointment_id]), 
            HOUR_IN_SECONDS
        );
    }

    /**
     * Log submission attempt
     */
    private function log_submission_attempt() {
        if (function_exists('massage_booking_debug_log')) {
            massage_booking_debug_log(
                'Appointment submission attempt', 
                $_POST, 
                'info', 
                'SUBMISSION'
            );
        }
    }

    /**
     * Log successful submission
     * 
     * @param int $appointment_id Created appointment ID
     */
    private function log_submission_success($appointment_id) {
        if (function_exists('massage_booking_debug_log')) {
            massage_booking_debug_log(
                'Appointment submitted successfully', 
                ['appointment_id' => $appointment_id], 
                'info', 
                'SUBMISSION'
            );
        }
    }

    /**
     * Log submission error
     * 
     * @param Exception $e Error that occurred
     */
    private function log_submission_error($e) {
        if (function_exists('massage_booking_debug_log')) {
            massage_booking_debug_log(
                'Appointment submission error', 
                [
                    'message' => $e->getMessage(), 
                    'code' => $e->getCode()
                ], 
                'error', 
                'SUBMISSION'
            );
        }
    }

    /**
     * Get nonce from REST API request
     * 
     * @return string Nonce value
     */
    private function get_rest_nonce_from_request() {
        $headers = getallheaders();
        return $headers['X-WP-Nonce'] ?? '';
    }

    /**
     * Send success response
     * 
     * @param array $data Response data
     * @param bool $is_rest Whether this is a REST API call
     * @return WP_REST_Response|mixed
     */
    private function send_success_response($data, $is_rest = false) {
        if ($is_rest) {
            return rest_ensure_response([
                'success' => true,
                'data' => $data
            ]);
        }
        
        wp_send_json_success($data);
    }

    /**
     * Send error response
     * 
     * @param array $error Error details
     * @param bool $is_rest Whether this is a REST API call
     * @return WP_REST_Response|mixed
     */
    private function send_error_response($error, $is_rest = false) {
        if ($is_rest) {
            return rest_ensure_response([
                'success' => false,
                'data' => $error
            ]);
        }
        
        wp_send_json_error($error);
    }
}

// Initialize the submission handler
add_action('plugins_loaded', function() {
    new Massage_Booking_Appointments_Submission();
});