<?php
// includes/class-calendar.php

class Massage_Booking_Calendar {
    
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $access_token;
    
    public function __construct() {
        $settings = new Massage_Booking_Settings();
        $this->client_id = $settings->get_setting('ms_client_id');
        $this->client_secret = $settings->get_setting('ms_client_secret');
        $this->tenant_id = $settings->get_setting('ms_tenant_id');
    }
    
    public function create_event($appointment) {
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        // Format start and end times
        $start_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
        $end_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
        
        // Create event object
        $event = [
            'subject' => 'Massage - ' . $appointment['duration'] . ' min (' . $appointment['full_name'] . ')',
            'body' => [
                'contentType' => 'text',
                'content' => "Client: {$appointment['full_name']}\nEmail: {$appointment['email']}\nPhone: {$appointment['phone']}\nFocus Areas: " . 
                            (is_array($appointment['focus_areas']) ? implode(', ', $appointment['focus_areas']) : $appointment['focus_areas']) . 
                            "\nPressure: {$appointment['pressure_preference']}\nSpecial Requests: {$appointment['special_requests']}"
            ],
            'start' => [
                'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'America/New_York' // Use your timezone
            ],
            'end' => [
                'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                'timeZone' => 'America/New_York'
            ],
            'showAs' => 'busy'
        ];
        
        // Call Microsoft Graph API to create event
        $response = wp_remote_post(
            'https://graph.microsoft.com/v1.0/me/events',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($event)
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 400) {
            return new WP_Error(
                'graph_api_error',
                isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error',
                ['status' => $status_code]
            );
        }
        
        return $body;
    }
    
    public function delete_event($event_id) {
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        // Call Microsoft Graph API to delete event
        $response = wp_remote_request(
            "https://graph.microsoft.com/v1.0/me/events/{$event_id}",
            [
                'method' => 'DELETE',
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token
                ]
            ]
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 400) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return new WP_Error(
                'graph_api_error',
                isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error',
                ['status' => $status_code]
            );
        }
        
        return true;
    }
    
    private function get_access_token() {
        // If we already have a token, return it
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // If required settings are missing, return false
        if (!$this->client_id || !$this->client_secret || !$this->tenant_id) {
            return false;
        }
        
        // Check for cached token
        $token_data = get_transient('massage_booking_ms_token');
        if ($token_data) {
            $this->access_token = $token_data;
            return $this->access_token;
        }
        
        // Get new token
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            
            // Cache the token (expires_in is in seconds)
            set_transient('massage_booking_ms_token', $this->access_token, $body['expires_in'] - 300);
            
            return $this->access_token;
        }
        
        return false;
    }
}