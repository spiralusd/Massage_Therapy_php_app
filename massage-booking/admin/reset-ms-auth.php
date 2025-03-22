<?php
/**
 * Reset Microsoft Graph Authentication
 * 
 * Add this to your functions.php or a temporary plugin file,
 * then access it at /wp-admin/admin.php?page=massage-booking-reset-ms-auth
 */

// Only run if admin
add_action('admin_menu', 'add_reset_ms_auth_page');

function add_reset_ms_auth_page() {
    add_submenu_page(
        'massage-booking',
        'Reset MS Auth',
        'Reset MS Auth',
        'manage_options',
        'massage-booking-reset-ms-auth',
        'reset_ms_auth_page'
    );
}

function reset_ms_auth_page() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    $action_taken = false;
    $message = '';
    
    // Process reset if confirmed
    if (isset($_POST['confirm_reset']) && isset($_POST['reset_ms_auth_nonce']) && 
        wp_verify_nonce($_POST['reset_ms_auth_nonce'], 'reset_ms_auth')) {
        
        // Delete the tokens
        delete_option('massage_booking_ms_access_token');
        delete_option('massage_booking_ms_refresh_token');
        delete_option('massage_booking_ms_token_expiry');
        
        // Log the action if debug logger is available
        if (function_exists('massage_booking_debug_log')) {
            massage_booking_debug_log('Microsoft Graph authentication tokens reset by admin', 
                ['user_id' => get_current_user_id()], 
                'info', 
                'MS_AUTH'
            );
        }
        
        $action_taken = true;
        $message = 'Microsoft Graph authentication tokens have been reset. You can now reconnect from the Settings page.';
    }
    
    // Output page
    ?>
    <div class="wrap">
        <h1>Reset Microsoft Graph Authentication</h1>
        
        <?php if ($action_taken): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=massage-booking-settings'); ?>" class="button button-primary">
                    Go to Settings Page
                </a>
            </p>
        <?php else: ?>
            <div class="card">
                <h2>Reset Authentication Tokens</h2>
                <p>This will delete all Microsoft Graph authentication tokens, requiring you to reconnect your Microsoft account.</p>
                <p><strong>Warning:</strong> Calendar integration will stop working until you reconnect.</p>
                
                <form method="post">
                    <?php wp_nonce_field('reset_ms_auth', 'reset_ms_auth_nonce'); ?>
                    <p>
                        <label>
                            <input type="checkbox" name="confirm_reset" value="1" required>
                            I understand that this will reset the Microsoft Graph authentication
                        </label>
                    </p>
                    <p>
                        <input type="submit" class="button button-primary" value="Reset Authentication">
                        <a href="<?php echo admin_url('admin.php?page=massage-booking'); ?>" class="button">Cancel</a>
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}