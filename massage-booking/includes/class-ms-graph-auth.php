<?php
/**
 * Enhanced Microsoft Graph Delegated Authentication Handler
 */
class Massage_Booking_MS_Graph_Auth {
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $redirect_uri;
    private $debug = false;

    public function __construct() {
        $settings = new Massage_Booking_Settings();
        $this->client_id = $settings->get_setting('ms_client_id');
        $this->client_secret = $settings->get_setting('ms_client_secret');
        $this->tenant_id = $settings->get_setting('ms_tenant_id');
        $this->redirect_uri = admin_url('admin.php?page=massage-booking-ms-auth');
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Initialize authentication hooks
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_auth_page']);
        add_action('admin_init', [$this, 'handle_microsoft_auth']);
        
        // Add disconnect action
        add_action('wp_ajax_massage_booking_disconnect_ms_graph', [$this, 'handle_disconnect']);
    }

    /**
     * Add hidden authentication page
     */
    public function add_auth_page() {
        add_submenu_page(
            null, 
            'Microsoft Graph Authentication', 
            'Microsoft Graph Authentication', 
            'manage_options', 
            'massage-booking-ms-auth', 
            [$this, 'render_auth_page']
        );
    }

    /**
     * Render authentication page
     */
    public function render_auth_page() {
        // This page will be automatically handled by handle_microsoft_auth()
        echo '<div class="wrap"><h1>Authenticating with Microsoft Graph...</h1></div>';
    }

    /**
     * Generate Microsoft login URL
     */
    public function generate_login_url() {
        $scopes = urlencode('offline_access openid profile User.Read Calendars.ReadWrite');
        
        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?'.
            'client_id=%s&'.
            'response_type=code&'.
            'redirect_uri=%s&'.
            'response_mode=query&'.
            'scope=%s&'.
            'prompt=consent&'. // This ensures you get a refresh token
            'state=%s',
            $this->tenant_id,
            $this->client_id,
            urlencode($this->redirect_uri),
            $scopes,
            wp_create_nonce('ms_graph_auth_state')
        );
    }

    /**
     * Handle Microsoft authentication callback
     */
    public function handle_microsoft_auth() {
        // Only process on our specific page and for admins
        if (!is_admin() || 
            !isset($_GET['page']) || 
            $_GET['page'] !== 'massage-booking-ms-auth') {
            return;
        }

        // Check for authorization code
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return;
        }

        // Verify state to prevent CSRF
        if (!wp_verify_nonce($_GET['state'], 'ms_graph_auth_state')) {
            wp_die('Authentication failed: Invalid state');
        }

        // Exchange authorization code for tokens
        $tokens = $this->exchange_code_for_tokens($_GET['code']);

        if (is_wp_error($tokens)) {
            // Handle error
            wp_die($tokens->get_error_message());
        }

        // Save tokens securely
        $this->save_tokens($tokens);

        // Redirect back to settings page
        wp_redirect(admin_url('admin.php?page=massage-booking-settings&ms_auth=success'));
        exit;
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchange_code_for_tokens($code) {
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirect_uri
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ms_token_error', 'Failed to obtain tokens: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('ms_token_error', $body['error_description']);
        }

        return $body;
    }

    /**
     * Save tokens securely
     */
    private function save_tokens($tokens) {
        // Encrypt sensitive tokens before storing
        if (class_exists('Massage_Booking_Encryption')) {
            $encryption = new Massage_Booking_Encryption();
            
            update_option('massage_booking_ms_access_token', 
                $encryption->encrypt($tokens['access_token'])
            );
            
            update_option('massage_booking_ms_refresh_token', 
                $encryption->encrypt($tokens['refresh_token'])
            );
        } else {
            // Fallback if encryption class is unavailable
            update_option('massage_booking_ms_access_token', $tokens['access_token']);
            update_option('massage_booking_ms_refresh_token', $tokens['refresh_token']);
        }
        
        // Store token expiration
        update_option('massage_booking_ms_token_expiry', 
            time() + intval($tokens['expires_in'])
        );
        
        // Log successful authentication
        if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
            massage_booking_debug_log_detail('Microsoft Graph authentication successful', [
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'scope' => isset($tokens['scope']) ? $tokens['scope'] : 'unknown'
            ], 'info', 'MS_AUTH');
        }
    }

    /**
     * Check if we have a valid token
     * 
     * @return bool True if we have a valid token or refresh token
     */
    public function has_valid_token() {
        $token_expiry = get_option('massage_booking_ms_token_expiry');
        $refresh_token = get_option('massage_booking_ms_refresh_token');
        
        // If we have a valid token, return true
        if ($token_expiry && $token_expiry > time()) {
            return true;
        }
        
        // If we have a refresh token, we can get a new token
        if ($refresh_token) {
            return true;
        }
        
        return false;
    }

    /**
     * Get an access token (refreshing if necessary)
     * 
     * @return string|bool Access token or false
     */
    public function get_access_token() {
        $token_expiry = get_option('massage_booking_ms_token_expiry');
        $encrypted_access_token = get_option('massage_booking_ms_access_token');
        
        // Decrypt access token if encryption is available
        $access_token = $encrypted_access_token;
        if (class_exists('Massage_Booking_Encryption') && $encrypted_access_token) {
            $encryption = new Massage_Booking_Encryption();
            $access_token = $encryption->decrypt($encrypted_access_token);
        }
        
        // If token is still valid, return it
        if ($token_expiry && $token_expiry > time() && $access_token) {
            return $access_token;
        }
        
        // Try to refresh the token
        return $this->refresh_access_token();
    }

    /**
     * Refresh access token
     * 
     * @return string|bool New access token or false
     */
    public function refresh_access_token() {
        $encrypted_refresh_token = get_option('massage_booking_ms_refresh_token');
        
        if (!$encrypted_refresh_token) {
            if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
                massage_booking_debug_log_detail('Failed to refresh token - no refresh token available', [], 'error', 'MS_AUTH');
            }
            return false;
        }

        // Decrypt refresh token if encryption is available
        $refresh_token = $encrypted_refresh_token;
        if (class_exists('Massage_Booking_Encryption')) {
            $encryption = new Massage_Booking_Encryption();
            $refresh_token = $encryption->decrypt($encrypted_refresh_token);
        }
        
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'scope' => 'offline_access Calendars.ReadWrite'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
                massage_booking_debug_log_detail('Failed to refresh token - network error', [
                    'error' => $response->get_error_message()
                ], 'error', 'MS_AUTH');
            }
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            // Save new tokens
            $this->save_tokens($body);
            
            if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
                massage_booking_debug_log_detail('Token refreshed successfully', [], 'info', 'MS_AUTH');
            }
            
            return $body['access_token'];
        }
        
        if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
            massage_booking_debug_log_detail('Failed to refresh token - API error', [
                'error' => isset($body['error']) ? $body['error'] : 'Unknown error',
                'description' => isset($body['error_description']) ? $body['error_description'] : 'No description'
            ], 'error', 'MS_AUTH');
        }

        return false;
    }
    
    /**
     * Handle disconnection of Microsoft Graph
     */
    public function handle_disconnect() {
        // Verify request
        check_ajax_referer('massage_booking_disconnect_ms_graph');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        // Delete tokens
        delete_option('massage_booking_ms_access_token');
        delete_option('massage_booking_ms_refresh_token');
        delete_option('massage_booking_ms_token_expiry');
        
        // Log disconnection
        if ($this->debug && function_exists('massage_booking_debug_log_detail')) {
            massage_booking_debug_log_detail('Microsoft Graph disconnected by user', [], 'info', 'MS_AUTH');
        }
        
        wp_send_json_success('Disconnected successfully');
    }
}

// Initialize the authentication handler
add_action('plugins_loaded', function() {
    $ms_graph_auth = new Massage_Booking_MS_Graph_Auth();
    $ms_graph_auth->init();
});