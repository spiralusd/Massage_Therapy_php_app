<?php
/**
 * Fixed Calendar Integration for Office 365
 * 
 * This file fixes the authentication issue with Microsoft Graph API.
 * Problem: Using /me endpoint with client credentials flow, which is incompatible.
 * Solution: Use delegated authentication flow via MS_Graph_Auth class.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Calendar {
    /**
     * Microsoft Graph API auth handler
     */
    private $ms_graph_auth;
    
    /**
     * Calendar user email
     */
    private $user_email;
    
    /**
     * Microsoft Graph API endpoint
     */
    private $graph_endpoint = 'https://graph.microsoft.com/v1.0/';
    
    /**
     * Calendar timezone
     */
    private $timezone;
    
    /**
     * Debug mode
     */
    private $debug = false;
    
    /**
     * Constructor
     * Initializes calendar integration with settings
     */
    public function __construct() {
        // Get settings
        $settings = new Massage_Booking_Settings();
        
        // Use MS_Graph_Auth class for delegated authentication
        $this->ms_graph_auth = new Massage_Booking_MS_Graph_Auth();
        
        // Get business email for calendar operations
        $this->user_email = $settings->get_setting('business_email', get_option('admin_email'));
        
        // Enable debug mode if WP_DEBUG is enabled
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        // Set timezone to WordPress timezone
        $this->timezone = $this->get_calendar_timezone();
        
        // Log initialization if debugging is enabled
        if ($this->debug) {
            $this->log_action('initialize', [
                'configured' => $this->is_configured(),
                'timezone' => $this->timezone,
                'user_email' => $this->user_email
            ]);
        }
    }
    
    /**
     * Get the appropriate calendar timezone
     */
    private function get_calendar_timezone() {
        // Try to get WordPress timezone
        $wp_timezone = wp_timezone_string();
        
        // Default to Eastern Time (common US timezone)
        if (empty($wp_timezone) || !$this->is_valid_timezone($wp_timezone)) {
            return 'America/New_York';
        }
        
        return $wp_timezone;
    }
    
    /**
     * Check if a timezone string is valid
     *
     * @param string $timezone Timezone to check
     * @return bool True if valid
     */
    private function is_valid_timezone($timezone) {
        // List of known problematic timezone formats
        $invalid_formats = ['+00:00', 'UTC+0', 'UTC'];
        
        if (in_array($timezone, $invalid_formats)) {
            return false;
        }
        
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if calendar integration is configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        // Check if access token can be obtained
        if (method_exists($this->ms_graph_auth, 'has_valid_token')) {
            return $this->ms_graph_auth->has_valid_token();
        }
        
        // Fallback to checking if refresh token exists
        return !empty(get_option('massage_booking_ms_refresh_token'));
    }
    
    /**
     * Create calendar event for appointment
     *
     * @param array $appointment Appointment data
     * @return array|WP_Error Event data on success, WP_Error on failure
     */
    public function create_event($appointment) {
        // Check if calendar is configured
        if (!$this->is_configured()) {
            if ($this->debug) {
                $this->log_action('create_event_error', [
                    'error' => 'Calendar integration is not configured'
                ]);
            }
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        try {
            // Validate required fields
            if (empty($appointment['full_name']) || empty($appointment['appointment_date']) || 
                empty($appointment['start_time']) || empty($appointment['duration'])) {
                
                if ($this->debug) {
                    $this->log_action('create_event_validation_error', [
                        'error' => 'Missing required appointment data',
                        'appointment' => $appointment
                    ]);
                }
                return new WP_Error('invalid_data', 'Missing required appointment data');
            }
            
            // Format start and end times
            $start_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            
            // Calculate end time if not provided
            if (empty($appointment['end_time'])) {
                $end_datetime = clone $start_datetime;
                $end_datetime->modify('+' . intval($appointment['duration']) . ' minutes');
                $appointment['end_time'] = $end_datetime->format('H:i');
            } else {
                $end_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
            }
            
            // Sanitize appointment data for event creation
            $sanitized_name = sanitize_text_field($appointment['full_name']);
            $sanitized_email = isset($appointment['email']) ? sanitize_email($appointment['email']) : '';
            $sanitized_phone = isset($appointment['phone']) ? sanitize_text_field($appointment['phone']) : '';
            $sanitized_duration = intval($appointment['duration']);
            
            // Handle focus areas safely
            $focus_areas = '';
            if (isset($appointment['focus_areas'])) {
                if (is_array($appointment['focus_areas'])) {
                    $sanitized_areas = array_map('sanitize_text_field', $appointment['focus_areas']);
                    $focus_areas = implode(', ', $sanitized_areas);
                } else {
                    $focus_areas = sanitize_text_field($appointment['focus_areas']);
                }
            }
            
            // Handle pressure preference safely
            $pressure_preference = isset($appointment['pressure_preference']) ? 
                sanitize_text_field($appointment['pressure_preference']) : '';
            
            // Handle special requests safely
            $special_requests = isset($appointment['special_requests']) ? 
                sanitize_textarea_field($appointment['special_requests']) : '';
            
            // Create event object
            $event = [
                'subject' => 'Massage - ' . $sanitized_duration . ' min (' . $sanitized_name . ')',
                'body' => [
                    'contentType' => 'text',
                    'content' => "Client: {$sanitized_name}\nEmail: {$sanitized_email}\nPhone: {$sanitized_phone}\nFocus Areas: {$focus_areas}\nPressure: {$pressure_preference}\nSpecial Requests: {$special_requests}"
                ],
                'start' => [
                    'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ],
                'end' => [
                    'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ],
                'showAs' => 'busy'
            ];
            
            // Get access token using delegated authentication
            $access_token = $this->ms_graph_auth->get_access_token();
            
            if (!$access_token) {
                if ($this->debug) {
                    $this->log_action('create_event_auth_error', [
                        'error' => 'Failed to get access token'
                    ]);
                }
                return new WP_Error('auth_error', 'Failed to get access token');
            }
            
            if ($this->debug) {
                $this->log_action('create_event_request', [
                    'event' => $event,
                    'token' => substr($access_token, 0, 10) . '...',
                    'endpoint' => $this->graph_endpoint . 'me/events'
                ]);
            }
            
            // Call Microsoft Graph API to create event
            $response = wp_remote_post(
                $this->graph_endpoint . 'me/events',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode($event),
                    'timeout' => 15
                ]
            );
            
            if (is_wp_error($response)) {
                if ($this->debug) {
                    $this->log_action('create_event_wp_error', [
                        'error' => $response->get_error_message(),
                        'code' => $response->get_error_code()
                    ]);
                }
                return $response;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $body = json_decode($response_body, true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($this->debug) {
                $this->log_action('create_event_response', [
                    'status_code' => $status_code,
                    'response' => $response_body
                ]);
            }
            
            if ($status_code >= 400) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
                
                // When getting auth errors, try to refresh token
                if ($status_code === 401 || (isset($body['error']['code']) && $body['error']['code'] === 'InvalidAuthenticationToken')) {
                    if ($this->ms_graph_auth->refresh_access_token()) {
                        // Try the request again with the new token
                        return $this->create_event($appointment);
                    }
                }
                
                if ($this->debug) {
                    $this->log_action('create_event_api_error', [
                        'status_code' => $status_code,
                        'error_message' => $error_message,
                        'body' => $body
                    ]);
                }
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            // Log successful event creation
            if ($this->debug) {
                $this->log_action('create_event_success', [
                    'event_id' => isset($body['id']) ? $body['id'] : 'unknown',
                    'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
                ]);
            }
            
            return $body;
        } catch (Exception $e) {
            if ($this->debug) {
                $this->log_action('create_event_exception', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    }
    
    /**
     * Delete calendar event
     *
     * @param string $event_id Event ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_event($event_id) {
        // Check if calendar is configured
        if (!$this->is_configured()) {
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        try {
            // Validate event ID
            if (empty($event_id)) {
                return new WP_Error('invalid_event_id', 'Event ID is required');
            }
            
            // Get access token using delegated authentication
            $access_token = $this->ms_graph_auth->get_access_token();
            
            if (!$access_token) {
                if ($this->debug) {
                    $this->log_action('delete_event_auth_error', [
                        'error' => 'Failed to get access token'
                    ]);
                }
                return new WP_Error('auth_error', 'Failed to get access token');
            }
            
            // Call Microsoft Graph API to delete event
            $response = wp_remote_request(
                $this->graph_endpoint . "me/events/" . sanitize_text_field($event_id),
                [
                    'method' => 'DELETE',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token
                    ],
                    'timeout' => 15
                ]
            );
            
            if (is_wp_error($response)) {
                if ($this->debug) {
                    $this->log_action('delete_event_error', [
                        'error' => $response->get_error_message(),
                        'event_id' => $event_id
                    ]);
                }
                return $response;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                
                // When getting auth errors, try to refresh token
                if ($status_code === 401 || (isset($body['error']['code']) && $body['error']['code'] === 'InvalidAuthenticationToken')) {
                    if ($this->ms_graph_auth->refresh_access_token()) {
                        // Try the request again with the new token
                        return $this->delete_event($event_id);
                    }
                }
                
                if ($this->debug) {
                    $this->log_action('delete_event_api_error', [
                        'status_code' => $status_code,
                        'error' => $error_message,
                        'event_id' => $event_id
                    ]);
                }
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            // Log successful event deletion
            if ($this->debug) {
                $this->log_action('delete_event_success', [
                    'event_id' => $event_id
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                $this->log_action('delete_event_exception', [
                    'error' => $e->getMessage(),
                    'event_id' => $event_id
                ]);
            }
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    }
    
    /**
     * Log action for debugging
     * Simple logging method for calendar actions
     * 
     * @param string $action Action being performed
     * @param array $data Additional data to log
     */
    private function log_action($action, $data = []) {
        if (!$this->debug) {
            return;
        }
        
        // If massage_booking_calendar_debug function exists, use it
        if (function_exists('massage_booking_calendar_debug')) {
            massage_booking_calendar_debug($action, $data);
            return;
        }
        
        // Otherwise, use error_log as fallback
        error_log('Calendar Action: ' . $action . ' - Data: ' . wp_json_encode($data));
    }
}
?>