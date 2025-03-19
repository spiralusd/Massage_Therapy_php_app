<?php
/**
 * Calendar Integration Class - Optimized Version
 * 
 * Provides integration with Office 365 Calendar for appointment management.
 * 
 * @package Massage_Booking
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Calendar {
    /**
     * Microsoft Graph API client ID
     *
     * @var string
     */
    private $client_id;
    
    /**
     * Microsoft Graph API client secret
     *
     * @var string
     */
    private $client_secret;
    
    /**
     * Microsoft Graph API tenant ID
     *
     * @var string
     */
    private $tenant_id;
    
    /**
     * Microsoft Graph API access token
     *
     * @var string
     */
    private $access_token;
    
    /**
     * Microsoft Graph API endpoint
     *
     * @var string
     */
    private $graph_endpoint = 'https://graph.microsoft.com/v1.0/';
    
    /**
     * Calendar timezone
     *
     * @var string
     */
    private $timezone;
    
    /**
     * Constructor
     * 
     * Initializes calendar integration with settings
     */
    public function __construct() {
        // Get settings
        $settings = new Massage_Booking_Settings();
        $this->client_id = $settings->get_setting('ms_client_id');
        $this->client_secret = $settings->get_setting('ms_client_secret');
        $this->tenant_id = $settings->get_setting('ms_tenant_id');
        
        // Get timezone from WordPress
        $this->timezone = wp_timezone_string();
        
        // Log calendar initialization
        $this->log_calendar_action('initialize', [
            'status' => $this->is_configured() ? 'configured' : 'not_configured'
        ]);
    }
    
    /**
     * Check if calendar integration is configured
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        return (!empty($this->client_id) && !empty($this->client_secret) && !empty($this->tenant_id));
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
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        // Get access token
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        try {
            // Format start and end times
            $start_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            $end_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
            
            // Handle focus areas
            $focus_areas = '';
            if (isset($appointment['focus_areas'])) {
                $focus_areas = is_array($appointment['focus_areas']) 
                    ? implode(', ', $appointment['focus_areas']) 
                    : $appointment['focus_areas'];
            }
            
            // Create event object
            $event = [
                'subject' => 'Massage - ' . $appointment['duration'] . ' min (' . $appointment['full_name'] . ')',
                'body' => [
                    'contentType' => 'text',
                    'content' => "Client: {$appointment['full_name']}\nEmail: {$appointment['email']}\nPhone: {$appointment['phone']}\nFocus Areas: {$focus_areas}\nPressure: {$appointment['pressure_preference']}\nSpecial Requests: {$appointment['special_requests']}"
                ],
                'start' => [
                    'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ],
                'end' => [
                    'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ],
                'showAs' => 'busy',
                'reminder' => [
                    'reminderMinutesBeforeStart' => 15
                ]
            ];
            
            // Call Microsoft Graph API to create event
            $response = wp_remote_post(
                $this->graph_endpoint . 'me/events',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode($event),
                    'timeout' => 15
                ]
            );
            
            // Handle response errors
            if (is_wp_error($response)) {
                $this->log_calendar_action('create_event_error', [
                    'error' => $response->get_error_message(),
                    'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
                ]);
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                $this->log_calendar_action('create_event_error', [
                    'status_code' => $status_code,
                    'error' => $error_message,
                    'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
                ]);
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            // Log successful event creation
            $this->log_calendar_action('create_event_success', [
                'event_id' => $body['id'],
                'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
            ]);
            
            return $body;
        } catch (Exception $e) {
            $this->log_calendar_action('create_event_exception', [
                'error' => $e->getMessage(),
                'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
            ]);
            
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
        
        // Get access token
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        try {
            // Call Microsoft Graph API to delete event
            $response = wp_remote_request(
                $this->graph_endpoint . "me/events/{$event_id}",
                [
                    'method' => 'DELETE',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token
                    ],
                    'timeout' => 15
                ]
            );
            
            // Handle response errors
            if (is_wp_error($response)) {
                $this->log_calendar_action('delete_event_error', [
                    'error' => $response->get_error_message(),
                    'event_id' => $event_id
                ]);
                return $response;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                
                $this->log_calendar_action('delete_event_error', [
                    'status_code' => $status_code,
                    'error' => $error_message,
                    'event_id' => $event_id
                ]);
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            // Log successful event deletion
            $this->log_calendar_action('delete_event_success', [
                'event_id' => $event_id
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->log_calendar_action('delete_event_exception', [
                'error' => $e->getMessage(),
                'event_id' => $event_id
            ]);
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    }
    
    /**
     * Update calendar event
     *
     * @param string $event_id Event ID
     * @param array $appointment Updated appointment data
     * @return array|WP_Error Updated event data on success, WP_Error on failure
     */
    public function update_event($event_id, $appointment) {
        // Check if calendar is configured
        if (!$this->is_configured()) {
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        // Get access token
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        try {
            // Format start and end times
            $start_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
            $end_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
            
            // Handle focus areas
            $focus_areas = '';
            if (isset($appointment['focus_areas'])) {
                $focus_areas = is_array($appointment['focus_areas']) 
                    ? implode(', ', $appointment['focus_areas']) 
                    : $appointment['focus_areas'];
            }
            
            // Create event update object
            $event = [
                'subject' => 'Massage - ' . $appointment['duration'] . ' min (' . $appointment['full_name'] . ')',
                'body' => [
                    'contentType' => 'text',
                    'content' => "Client: {$appointment['full_name']}\nEmail: {$appointment['email']}\nPhone: {$appointment['phone']}\nFocus Areas: {$focus_areas}\nPressure: {$appointment['pressure_preference']}\nSpecial Requests: {$appointment['special_requests']}"
                ],
                'start' => [
                    'dateTime' => $start_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ],
                'end' => [
                    'dateTime' => $end_datetime->format('Y-m-d\TH:i:s'),
                    'timeZone' => $this->timezone
                ]
            ];
            
            // Call Microsoft Graph API to update event
            $response = wp_remote_request(
                $this->graph_endpoint . "me/events/{$event_id}",
                [
                    'method' => 'PATCH',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode($event),
                    'timeout' => 15
                ]
            );
            
            // Handle response errors
            if (is_wp_error($response)) {
                $this->log_calendar_action('update_event_error', [
                    'error' => $response->get_error_message(),
                    'event_id' => $event_id,
                    'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
                ]);
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                $this->log_calendar_action('update_event_error', [
                    'status_code' => $status_code,
                    'error' => $error_message,
                    'event_id' => $event_id,
                    'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
                ]);
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            // Log successful event update
            $this->log_calendar_action('update_event_success', [
                'event_id' => $event_id,
                'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
            ]);
            
            return $body;
        } catch (Exception $e) {
            $this->log_calendar_action('update_event_exception', [
                'error' => $e->getMessage(),
                'event_id' => $event_id,
                'appointment_id' => isset($appointment['id']) ? $appointment['id'] : null
            ]);
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    }
    
    /**
     * Get event details
     *
     * @param string $event_id Event ID
     * @return array|WP_Error Event data on success, WP_Error on failure
     */
    public function get_event($event_id) {
        // Check if calendar is configured
        if (!$this->is_configured()) {
            return new WP_Error('calendar_not_configured', 'Calendar integration is not configured');
        }
        
        // Get access token
        if (!$this->get_access_token()) {
            return new WP_Error('auth_error', 'Failed to authenticate with Microsoft Graph');
        }
        
        try {
            // Call Microsoft Graph API to get event
            $response = wp_remote_get(
                $this->graph_endpoint . "me/events/{$event_id}",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->access_token
                    ],
                    'timeout' => 15
                ]
            );
            
            // Handle response errors
            if (is_wp_error($response)) {
                $this->log_calendar_action('get_event_error', [
                    'error' => $response->get_error_message(),
                    'event_id' => $event_id
                ]);
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code >= 400) {
                $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                $this->log_calendar_action('get_event_error', [
                    'status_code' => $status_code,
                    'error' => $error_message,
                    'event_id' => $event_id
                ]);
                
                return new WP_Error(
                    'graph_api_error',
                    $error_message,
                    ['status' => $status_code]
                );
            }
            
            return $body;
        } catch (Exception $e) {
            $this->log_calendar_action('get_event_exception', [
                'error' => $e->getMessage(),
                'event_id' => $event_id
            ]);
            
            return new WP_Error('calendar_exception', $e->getMessage());
        }
    }
    
   /**
 * Get access token for Microsoft Graph API with Delegated Authentication
 * 
 * @return string|false Access token or false on failure
 */
private function get_access_token() {
    // If we already have a token, return it
    if ($this->access_token) {
        return $this->access_token;
    }
    
    // If required settings are missing, return false
    if (!$this->is_configured()) {
        $this->log_calendar_action('token_error', [
            'error' => 'Calendar integration not configured',
            'client_id' => $this->client_id ? 'set' : 'unset',
            'client_secret' => $this->client_secret ? 'set' : 'unset',
            'tenant_id' => $this->tenant_id ? 'set' : 'unset'
        ]);
        return false;
    }
    
    // Check for cached token and expiry
    $encrypted_access_token = get_option('massage_booking_ms_access_token');
    $token_expiry = get_option('massage_booking_ms_token_expiry');
    
    // Decrypt access token
    $encryption = new Massage_Booking_Encryption();
    $access_token = $encrypted_access_token ? $encryption->decrypt($encrypted_access_token) : null;
    
    // Check if token is still valid
    if ($access_token && $token_expiry > time()) {
        $this->access_token = $access_token;
        return $this->access_token;
    }
    
    // Try to refresh the token
    try {
        // Get refresh token
        $encrypted_refresh_token = get_option('massage_booking_ms_refresh_token');
        $refresh_token = $encrypted_refresh_token ? $encryption->decrypt($encrypted_refresh_token) : null;
        
        if (!$refresh_token) {
            $this->log_calendar_action('token_error', [
                'error' => 'No refresh token available',
                'action' => 'Token refresh'
            ]);
            return false;
        }
        
        // Prepare token refresh request
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'scope' => 'https://graph.microsoft.com/.default'
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            $this->log_calendar_action('token_error', [
                'error' => $response->get_error_message(),
                'action' => 'Refresh token request'
            ]);
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code >= 400 || !isset($body['access_token'])) {
            $error_message = isset($body['error_description']) 
                ? $body['error_description'] 
                : 'Unknown token refresh error';
            
            $this->log_calendar_action('token_error', [
                'status_code' => $status_code,
                'error' => $error_message,
                'response_body' => $body
            ]);
            return false;
        }
        
        // Store new tokens
        $new_access_token = $body['access_token'];
        $new_refresh_token = $body['refresh_token'] ?? $refresh_token;
        
        // Encrypt and store tokens
        update_option('massage_booking_ms_access_token', 
            $encryption->encrypt($new_access_token)
        );
        
        update_option('massage_booking_ms_refresh_token', 
            $encryption->encrypt($new_refresh_token)
        );
        
        // Set new token expiry
        update_option('massage_booking_ms_token_expiry', 
            time() + intval($body['expires_in'])
        );
        
        $this->access_token = $new_access_token;
        return $this->access_token;
    } catch (Exception $e) {
        $this->log_calendar_action('token_exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}
    
    /**
     * Log calendar action for debugging and monitoring
     *
     * @param string $action Action performed
     * @param array $details Action details
     * @return void
     */
    private function log_calendar_action($action, $details = []) {
        // Log to audit log if available
        if (class_exists('Massage_Booking_Audit_Log')) {
            $audit_log = new Massage_Booking_Audit_Log();
            $audit_log->log_action('calendar_' . $action, get_current_user_id(), null, 'calendar', $details);
        }
        
        // Also log to error log for critical errors
        if (in_array($action, ['token_error', 'token_exception', 'create_event_error', 'create_event_exception'])) {
            error_log('Calendar error: ' . $action . ' - ' . wp_json_encode($details));
        }
    }
}