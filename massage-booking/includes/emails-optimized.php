<?php
class Massage_Booking_Emails {
    /**
     * Log for email sending attempts
     * @var array
     */
    private $email_log = [];
    
    /**
     * Send confirmation email to client with enhanced verification
     *
     * @param array $appointment_data Appointment details
     * @return array Verification results
     */
    public function send_client_confirmation($appointment_data) {
        // Start logging
        $this->email_log = [];
        
        // Validate email address
        if (!is_email($appointment_data['email'])) {
            $this->log_email_attempt(false, 'Invalid email address');
            return $this->get_email_verification_results();
        }
        
        // Get settings
        $settings = new Massage_Booking_Settings();
        $business_name = $settings->get_setting('business_name', 'Massage Therapy Practice');
        $business_email = $settings->get_setting('business_email', get_option('admin_email'));
        
        // Format date and time for email
        $date = new DateTime($appointment_data['appointment_date']);
        $formatted_date = $date->format('l, F j, Y');
        
        $start_time = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['start_time']);
        $formatted_time = $start_time->format('g:i A');
        
        // Client confirmation email
        $client_subject = 'Your Massage Appointment Confirmation';
        $client_message = $this->create_email_html($appointment_data, $business_name, $formatted_date, $formatted_time);
        
        $client_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $business_name . ' <' . $business_email . '>'
        ];
        
        // Attempt to send email
        $send_result = wp_mail($appointment_data['email'], $client_subject, $client_message, $client_headers);
        
        // Log the attempt
        $this->log_email_attempt($send_result, $send_result ? 'Email sent successfully' : 'Email sending failed');
        
        // Store appointment details in transient for thank you page
        set_transient('massage_booking_confirmation_' . get_current_user_id(), $appointment_data, HOUR_IN_SECONDS);
        
        return $this->get_email_verification_results();
    }
    
    /**
     * Create HTML email template
     *
     * @param array $appointment_data Appointment details
     * @param string $business_name Business name
     * @param string $formatted_date Formatted appointment date
     * @param string $formatted_time Formatted appointment time
     * @return string HTML email content
     */
    private function create_email_html($appointment_data, $business_name, $formatted_date, $formatted_time) {
        // Handle focus areas
        $focus_areas = is_array($appointment_data['focus_areas']) 
            ? implode(', ', $appointment_data['focus_areas']) 
            : $appointment_data['focus_areas'];
        
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Appointment Confirmation</h2>
                <p>Dear {$appointment_data['full_name']},</p>
                <p>Your massage therapy appointment has been confirmed:</p>
                
                <div style='background-color: #f7f7f7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Date:</strong> {$formatted_date}</p>
                    <p><strong>Time:</strong> {$formatted_time}</p>
                    <p><strong>Duration:</strong> {$appointment_data['duration']} minutes</p>
                    <p><strong>Focus Areas:</strong> {$focus_areas}</p>
                    <p><strong>Pressure Preference:</strong> {$appointment_data['pressure_preference']}</p>
                </div>
                
                <p>If you need to reschedule or cancel, please contact us at least 24 hours in advance.</p>
                <p>Thank you for booking with us!</p>
                
                <p style='margin-top: 30px;'>
                    Regards,<br>
                    {$business_name}
                </p>
            </div>
        ";
    }
    
    /**
     * Send therapist notification email
     *
     * @param array $appointment_data Appointment details
     * @return array Verification results
     */
    public function send_therapist_notification($appointment_data) {
        // Reset email log
        $this->email_log = [];
        
        // Get settings
        $settings = new Massage_Booking_Settings();
        $business_name = $settings->get_setting('business_name', 'Massage Therapy Practice');
        $business_email = $settings->get_setting('business_email', get_option('admin_email'));
        
        // Format date and time
        $date = new DateTime($appointment_data['appointment_date']);
        $formatted_date = $date->format('l, F j, Y');
        
        $start_time = new DateTime($appointment_data['appointment_date'] . ' ' . $appointment_data['start_time']);
        $formatted_time = $start_time->format('g:i A');
        
        // Focus areas
        $focus_areas = is_array($appointment_data['focus_areas']) 
            ? implode(', ', $appointment_data['focus_areas']) 
            : $appointment_data['focus_areas'];
            
        $admin_subject = 'New Massage Appointment Booked';
        $admin_message = $this->create_admin_email_html($appointment_data, $business_name, $formatted_date, $formatted_time, $focus_areas);
        
        $admin_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Booking System <' . $business_email . '>'
        ];
        
        // Attempt to send email
        $send_result = wp_mail($business_email, $admin_subject, $admin_message, $admin_headers);
        
        // Log the attempt
        $this->log_email_attempt($send_result, $send_result ? 'Therapist notification sent' : 'Therapist notification failed');
        
        return $this->get_email_verification_results();
    }
    
    /**
     * Create HTML email template for admin
     *
     * @param array $appointment_data Appointment details
     * @param string $business_name Business name
     * @param string $formatted_date Formatted appointment date
     * @param string $formatted_time Formatted appointment time
     * @param string $focus_areas Focus areas
     * @return string HTML email content
     */
    private function create_admin_email_html($appointment_data, $business_name, $formatted_date, $formatted_time, $focus_areas) {
        return "
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
                    <p><strong>Pressure Preference:</strong> {$appointment_data['pressure_preference']}</p>
                    <p><strong>Special Requests:</strong> {$appointment_data['special_requests']}</p>
                </div>
            </div>
        ";
    }
    
    /**
     * Log email sending attempts
     *
     * @param bool $success Whether email was sent successfully
     * @param string $message Description of the email attempt
     */
    private function log_email_attempt($success, $message) {
        $this->email_log[] = [
            'timestamp' => current_time('mysql'),
            'success' => $success,
            'message' => $message
        ];
        
        // If audit log exists, log the email attempt
        if (class_exists('Massage_Booking_Audit_Log')) {
            $audit_log = new Massage_Booking_Audit_Log();
            $audit_log->log_action(
                'email_attempt', 
                get_current_user_id(), 
                null, 
                'email', 
                [
                    'success' => $success,
                    'message' => $message
                ]
            );
        }
    }
    
    /**
     * Get email verification results
     *
     * @return array Verification results
     */
    public function get_email_verification_results() {
        return [
            'success' => empty(array_filter($this->email_log, function($log) { return !$log['success']; })),
            'log' => $this->email_log
        ];
    }
    
    /**
     * Validate email settings
     *
     * @return array Email configuration test results
     */
    public function validate_email_configuration() {
        $settings = new Massage_Booking_Settings();
        $business_email = $settings->get_setting('business_email', get_option('admin_email'));
        
        $test_subject = 'Massage Booking Email Configuration Test';
        $test_message = 'This is a test email to verify your email configuration.';
        
        $result = wp_mail($business_email, $test_subject, $test_message);
        
        return [
            'success' => $result,
            'business_email' => $business_email,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * Diagnose email sending issues
     *
     * @return array Detailed email diagnostics
     */
    public function diagnose_email_issues() {
        $diagnostics = [
            'php_mail_enabled' => function_exists('mail'),
            'wp_mail_function' => function_exists('wp_mail'),
            'wordpress_email_config' => [
                'admin_email' => get_option('admin_email'),
                'from_email' => get_option('from_email'),
                'from_name' => get_option('from_name')
            ],
            'php_mail_configuration' => [
                'sendmail_path' => ini_get('sendmail_path'),
                'smtp_host' => ini_get('smtp_host'),
                'smtp_port' => ini_get('smtp_port')
            ]
        ];

        // Test email sending capabilities
        try {
            $test_result = $this->validate_email_configuration();
            $diagnostics['test_email_result'] = $test_result;
        } catch (Exception $e) {
            $diagnostics['test_email_error'] = $e->getMessage();
        }

        return $diagnostics;
    }
}
       