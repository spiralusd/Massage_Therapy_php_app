<?php
class Massage_Booking_Emails {
    
    /**
     * Send confirmation email to client
     */
    public function send_client_confirmation($appointment_data) {
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
        $client_message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Appointment Confirmation</h2>
                <p>Dear {$appointment_data['full_name']},</p>
                <p>Your massage therapy appointment has been confirmed:</p>
                
                <div style='background-color: #f7f7f7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Date:</strong> {$formatted_date}</p>
                    <p><strong>Time:</strong> {$formatted_time}</p>
                    <p><strong>Duration:</strong> {$appointment_data['duration']} minutes</p>
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
        
        return wp_mail($appointment_data['email'], $client_subject, $client_message, $client_headers);
    }
    
    /**
     * Send notification email to therapist
     */
    public function send_therapist_notification($appointment_data) {
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
                    <p><strong>Pressure Preference:</strong> {$appointment_data['pressure_preference']}</p>
                    <p><strong>Special Requests:</strong> {$appointment_data['special_requests']}</p>
                </div>
            </div>
        ";
        
        $admin_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Booking System <' . $business_email . '>'
        ];
        
        return wp_mail($business_email, $admin_subject, $admin_message, $admin_headers);
    }
}