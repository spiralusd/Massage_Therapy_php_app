<?php
/**
 * Microsoft Graph Delegated Authentication Handler
 */
class Massage_Booking_MS_Graph_Auth {
    private $client_id;
    private $client_secret;
    private $tenant_id;
    private $redirect_uri;

    public function __construct() {
        $settings = new Massage_Booking_Settings();
        $this->client_id = $settings->get_setting('ms_client_id');
        $this->client_secret = $settings->get_setting('ms_client_secret');
        $this->tenant_id = $settings->get_setting('ms_tenant_id');
        $this->redirect_uri = admin_url('admin.php?page=massage-booking-ms-auth');
    }

    /**
     * Initialize authentication hooks
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_auth_page']);
        add_action('admin_init', [$this, 'handle_microsoft_auth']);
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
            return new WP_Error('ms_token_error', 'Failed to obtain tokens');
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
        $encryption = new Massage_Booking_Encryption();
        
        update_option('massage_booking_ms_access_token', 
            $encryption->encrypt($tokens['access_token'])
        );
        
        update_option('massage_booking_ms_refresh_token', 
            $encryption->encrypt($tokens['refresh_token'])
        );
        
        // Store token expiration
        update_option('massage_booking_ms_token_expiry', 
            time() + intval($tokens['expires_in'])
        );
    }

    /**
     * Refresh access token
     */
    public function refresh_access_token() {
        $encryption = new Massage_Booking_Encryption();
        $encrypted_refresh_token = get_option('massage_booking_ms_refresh_token');
        
        if (!$encrypted_refresh_token) {
            return false;
        }

        $refresh_token = $encryption->decrypt($encrypted_refresh_token);
        
        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
        
        $response = wp_remote_post($token_url, [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            // Save new tokens
            $this->save_tokens($body);
            return $body['access_token'];
        }

        return false;
    }
}

// Initialize the authentication handler
add_action('plugins_loaded', function() {
    $ms_graph_auth = new Massage_Booking_MS_Graph_Auth();
    $ms_graph_auth->init();
});