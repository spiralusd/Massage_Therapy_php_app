<?php
/**
 * Simplified Settings Page Rendering Function
 */
function massage_booking_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    // Start output
    echo '<div class="wrap">';
    echo '<h1>Schedule Settings</h1>';
    
    // Basic error handling
    if (!class_exists('Massage_Booking_Settings')) {
        echo '<div class="notice notice-error"><p>Settings class not found.</p></div>';
        echo '</div>';
        return;
    }

    // Try to create settings instance with error handling
    try {
        $settings = new Massage_Booking_Settings();
        
        // Start form
        echo '<form method="post" action="options.php">';
        
        // WordPress settings fields
        settings_fields('massage_booking_settings');
        do_settings_sections('massage_booking_settings');
        
        // Submit button
        submit_button('Save Settings');
        
        echo '</form>';
    } catch (Exception $e) {
        // Catch any instantiation errors
        echo '<div class="notice notice-error"><p>Error creating settings: ' . 
             esc_html($e->getMessage()) . '</p></div>';
    }
    
    echo '</div>'; // wrap
}

/**
 * Register settings to prevent potential errors
 */
function massage_booking_register_settings() {
    register_setting('massage_booking_settings', 'massage_booking_settings');
}
add_action('admin_init', 'massage_booking_register_settings');