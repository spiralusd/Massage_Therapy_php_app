<?php
/**
 * Settings Page for Massage Booking System
 * Merged version combining existing functionality with improved styling and structure
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Register settings
 */
function massage_booking_register_settings() {
    register_setting('massage_booking_settings', 'massage_booking_settings');
}
add_action('admin_init', 'massage_booking_register_settings');

/**
 * Settings page content
 */
function massage_booking_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if settings class exists
    if (!class_exists('Massage_Booking_Settings')) {
        echo '<div class="wrap massage-booking-admin">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<div class="notice notice-error"><p>Error: Settings class not found.</p></div>';
        echo '</div>';
        return;
    }
    
    
    $settings = new Massage_Booking_Settings();
    
    // Save settings if form submitted
    if (isset($_POST['massage_booking_save_settings']) && check_admin_referer('massage_booking_settings')) {
        // Working days
        $working_days = isset($_POST['working_days']) ? $_POST['working_days'] : [];
        $settings->update_setting('working_days', $working_days);
        
        // Break time
        $break_time = isset($_POST['break_time']) ? intval($_POST['break_time']) : 15;
        $settings->update_setting('break_time', $break_time);
        
        // Time slot interval
        $time_slot_interval = isset($_POST['time_slot_interval']) ? intval($_POST['time_slot_interval']) : 30;
        $settings->update_setting('time_slot_interval', $time_slot_interval);
        
        // Available durations
        $durations = isset($_POST['durations']) ? $_POST['durations'] : ['60', '90', '120'];
        $settings->update_setting('durations', $durations);
        
        // Service prices
        $prices = [];
        if (isset($_POST['price']) && is_array($_POST['price'])) {
            foreach ($_POST['price'] as $duration => $price) {
                $prices[$duration] = floatval($price);
            }
        }
        $settings->update_setting('prices', $prices);
        
        // Business information
        $settings->update_setting('business_name', sanitize_text_field($_POST['business_name']));
        $settings->update_setting('business_email', sanitize_email($_POST['business_email']));
        
        // Handle schedule settings (more complex)
        $schedule = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $schedule[$day] = [];
            
            if (isset($_POST['schedule'][$day]) && is_array($_POST['schedule'][$day])) {
                foreach ($_POST['schedule'][$day] as $block) {
                    if (!empty($block['from']) && !empty($block['to'])) {
                        $schedule[$day][] = [
                            'from' => sanitize_text_field($block['from']),
                            'to' => sanitize_text_field($block['to'])
                        ];
                    }
                }
            }
        }
        
        $settings->update_setting('schedule', $schedule);
        
        // Office 365 integration settings
        if (isset($_POST['ms_client_id'])) {
            $settings->update_setting('ms_client_id', sanitize_text_field($_POST['ms_client_id']));
            $settings->update_setting('ms_client_secret', sanitize_text_field($_POST['ms_client_secret']));
            $settings->update_setting('ms_tenant_id', sanitize_text_field($_POST['ms_tenant_id']));
        }
        
        // HIPAA Compliance Settings
        if (isset($_POST['enable_audit_log'])) {
            $settings->update_setting('enable_audit_log', isset($_POST['enable_audit_log']));
            $settings->update_setting('audit_log_retention_days', intval($_POST['audit_log_retention_days'] ?? 90));
        }
        
        // Data removal on uninstall
        $settings->update_setting('remove_data_on_uninstall', isset($_POST['remove_data_on_uninstall']));
        
        // Update booking page option
        if (isset($_POST['booking_page_id'])) {
            update_option('massage_booking_page_id', intval($_POST['booking_page_id']));
        }
        
        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }
    
    // Get current settings
    $current = $settings->get_all_settings();
    
    // Set default values if not set
    $current['working_days'] = isset($current['working_days']) ? $current['working_days'] : ['1', '2', '3', '4', '5'];
    $current['break_time'] = isset($current['break_time']) ? $current['break_time'] : 15;
    $current['time_slot_interval'] = isset($current['time_slot_interval']) ? $current['time_slot_interval'] : 30;
    $current['durations'] = isset($current['durations']) ? $current['durations'] : ['60', '90', '120'];
    $current['prices'] = isset($current['prices']) ? $current['prices'] : ['60' => 95, '90' => 125, '120' => 165];
    $current['business_name'] = isset($current['business_name']) ? $current['business_name'] : get_bloginfo('name');
    $current['business_email'] = isset($current['business_email']) ? $current['business_email'] : get_bloginfo('admin_email');
    $current['schedule'] = isset($current['schedule']) ? $current['schedule'] : [];
    $current['enable_audit_log'] = isset($current['enable_audit_log']) ? $current['enable_audit_log'] : true;
    $current['audit_log_retention_days'] = isset($current['audit_log_retention_days']) ? $current['audit_log_retention_days'] : 90;
    
    // Default business hours if not set
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (!isset($current['schedule'][$day]) || empty($current['schedule'][$day])) {
            $current['schedule'][$day] = [['from' => '09:00', 'to' => '18:00']];
        }
    }
    
    // Booking page ID
    $booking_page_id = get_option('massage_booking_page_id', 0);
    
    
    
    ?>
    <div class="wrap massage-booking-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('massage_booking_settings'); ?>
            
            <div class="nav-tab-wrapper">
                <a href="#general-settings" class="nav-tab nav-tab-active">General</a>
                <a href="#schedule-settings" class="nav-tab">Schedule</a>
                <a href="#services-settings" class="nav-tab">Services</a>
                <a href="#working-hours" class="nav-tab">Working Hours</a>
                <a href="#integration-settings" class="nav-tab">Integrations</a>
                <a href="#security-settings" class="nav-tab">Security & Privacy</a>
            </div>
            
            <!-- General Settings Tab -->
            <div id="general-settings" class="tab-content">
                <h2 class="title">General Settings</h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Business Name</th>
                        <td>
                            <input type="text" name="business_name" value="<?php echo esc_attr($current['business_name']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Business Email</th>
                        <td>
                            <input type="email" name="business_email" value="<?php echo esc_attr($current['business_email']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Booking Page</th>
                        <td>
                            <select name="booking_page_id" class="regular-text">
                                <option value="">Select a page...</option>
                                <?php
                                $pages = get_pages();
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . selected($booking_page_id, $page->ID, false) . '>' . $page->post_title . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Select the page that displays your booking form.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Schedule Settings Tab -->
            <div id="schedule-settings" class="tab-content" style="display:none;">
                <h2 class="title">Schedule Settings</h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Working Days</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Working Days</legend>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="0" <?php checked(in_array('0', $current['working_days'])); ?>>
                                    Sunday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="1" <?php checked(in_array('1', $current['working_days'])); ?>>
                                    Monday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="2" <?php checked(in_array('2', $current['working_days'])); ?>>
                                    Tuesday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="3" <?php checked(in_array('3', $current['working_days'])); ?>>
                                    Wednesday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="4" <?php checked(in_array('4', $current['working_days'])); ?>>
                                    Thursday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="5" <?php checked(in_array('5', $current['working_days'])); ?>>
                                    Friday
                                </label><br>
                                <label>
                                    <input type="checkbox" name="working_days[]" value="6" <?php checked(in_array('6', $current['working_days'])); ?>>
                                    Saturday
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Break Time Between Appointments</th>
                        <td>
                            <select name="break_time">
                                <option value="0" <?php selected($current['break_time'], 0); ?>>No break</option>
                                <option value="5" <?php selected($current['break_time'], 5); ?>>5 minutes</option>
                                <option value="10" <?php selected($current['break_time'], 10); ?>>10 minutes</option>
                                <option value="15" <?php selected($current['break_time'], 15); ?>>15 minutes</option>
                                <option value="20" <?php selected($current['break_time'], 20); ?>>20 minutes</option>
                                <option value="30" <?php selected($current['break_time'], 30); ?>>30 minutes</option>
                            </select>
                            <p class="description">Amount of time to leave between appointments for privacy and preparation.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Time Slot Interval</th>
                        <td>
                            <select name="time_slot_interval">
                                <option value="15" <?php selected($current['time_slot_interval'], 15); ?>>15 minutes</option>
                                <option value="30" <?php selected($current['time_slot_interval'], 30); ?>>30 minutes</option>
                                <option value="60" <?php selected($current['time_slot_interval'], 60); ?>>60 minutes</option>
                            </select>
                            <p class="description">The interval for appointment start times.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Services Settings Tab -->
            <div id="services-settings" class="tab-content" style="display:none;">
                <h2 class="title">Services</h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Available Durations</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Available Durations</legend>
                                <label>
                                    <input type="checkbox" name="durations[]" value="60" <?php checked(in_array('60', $current['durations'])); ?>>
                                    60 Minutes
                                </label>
                                <input type="number" name="price[60]" value="<?php echo esc_attr($current['prices']['60'] ?? 95); ?>" min="0" step="5" class="small-text"> $<br>
                                
                                <label>
                                    <input type="checkbox" name="durations[]" value="90" <?php checked(in_array('90', $current['durations'])); ?>>
                                    90 Minutes
                                </label>
                                <input type="number" name="price[90]" value="<?php echo esc_attr($current['prices']['90'] ?? 125); ?>" min="0" step="5" class="small-text"> $<br>
                                
                                <label>
                                    <input type="checkbox" name="durations[]" value="120" <?php checked(in_array('120', $current['durations'])); ?>>
                                    120 Minutes
                                </label>
                                <input type="number" name="price[120]" value="<?php echo esc_attr($current['prices']['120'] ?? 165); ?>" min="0" step="5" class="small-text"> $
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Working Hours Tab -->
            <div id="working-hours" class="tab-content" style="display:none;">
                <h2 class="title">Working Hours</h2>
                
                <?php
                $days = [
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday'
                ];
                
                foreach ($days as $day_key => $day_name) :
                    $day_blocks = $current['schedule'][$day_key] ?? [];
                    if (empty($day_blocks)) {
                        $day_blocks = [['from' => '09:00', 'to' => '18:00']];
                    }
                ?>
                    <h3><?php echo esc_html($day_name); ?></h3>
                    <div class="schedule-blocks" id="schedule-<?php echo esc_attr($day_key); ?>">
                        <?php foreach ($day_blocks as $i => $block) : ?>
                            <div class="schedule-block">
                                <input type="time" name="schedule[<?php echo esc_attr($day_key); ?>][<?php echo esc_attr($i); ?>][from]" value="<?php echo esc_attr($block['from']); ?>">
                                to
                                <input type="time" name="schedule[<?php echo esc_attr($day_key); ?>][<?php echo esc_attr($i); ?>][to]" value="<?php echo esc_attr($block['to']); ?>">
                                <button type="button" class="button remove-block">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button add-block" data-day="<?php echo esc_attr($day_key); ?>">Add Time Block</button>
                    <br><br>
                <?php endforeach; ?>
            </div>
            
            <!-- Integration Settings Tab -->
             <div id="integration-settings" class="tab-content" style="display:none;">
                <h2 class="title">Office 365 Integration</h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Microsoft Client ID</th>
                        <td>
                            <input type="text" name="ms_client_id" value="<?php echo esc_attr($settings->get_setting('ms_client_id', '')); ?>" class="regular-text">
             </td>
                </tr>
                <tr>
                    <th scope="row">Microsoft Client Secret</th>
                    <td>
                        <input type="password" name="ms_client_secret" value="<?php echo esc_attr($settings->get_setting('ms_client_secret', '')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Microsoft Tenant ID</th>
                    <td>
                        <input type="text" name="ms_tenant_id" value="<?php echo esc_attr($settings->get_setting('ms_tenant_id', '')); ?>" class="regular-text">
                    </td>
                </tr>
                <?php
                // Check if Microsoft Graph authentication is already connected
                $is_ms_connected = get_option('massage_booking_ms_refresh_token');
                
                // If MS Graph Auth class exists, generate login URL
                $login_url = '';
                if (class_exists('Massage_Booking_MS_Graph_Auth')) {
                    $ms_graph_auth = new Massage_Booking_MS_Graph_Auth();
                    $login_url = $ms_graph_auth->generate_login_url();
                }
                
                // Add a new row for Microsoft Graph connection status
                echo '<tr>';
                echo '<th scope="row">Microsoft Graph Connection</th>';
                echo '<td>';
                if ($is_ms_connected) {
                    echo '<p><strong>Status:</strong> <span style="color: green;">Connected ✓</span></p>';
                    echo '<a href="' . esc_url($login_url) . '" class="button">Reconnect Account</a>';
                    echo ' <button id="disconnect-ms-graph" class="button button-secondary">Disconnect Account</button>';
                } else {
                    echo '<p><strong>Status:</strong> <span style="color: red;">Not Connected ✗</span></p>';
                    echo '<a href="' . esc_url($login_url) . '" class="button button-primary">Connect to Microsoft Graph</a>';
                }
                echo '<p class="description">Connect your Microsoft Graph account to sync appointments with your calendar.</p>';
                echo '</td>';
                echo '</tr>';
                ?>
            </table>
            
            <script>
            jQuery(document).ready(function($) {
                $('#disconnect-ms-graph').on('click', function() {
                    if (confirm('Are you sure you want to disconnect your Microsoft Graph account?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'massage_booking_disconnect_ms_graph',
                                _wpnonce: '<?php echo wp_create_nonce('massage_booking_disconnect_ms_graph'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Failed to disconnect: ' + response.data);
                                }
                            }
                        });
                    }
                });
            });
            </script>
        </div>
            
            <!-- Security & Privacy Tab -->
            <div id="security-settings" class="tab-content" style="display:none;">
                <h2 class="title">Security & Privacy Settings</h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Enable Audit Log</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_audit_log" <?php checked($current['enable_audit_log']); ?>>
                                Log all system activities for HIPAA compliance
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Audit Log Retention</th>
                        <td>
                            <input type="number" name="audit_log_retention_days" value="<?php echo esc_attr($current['audit_log_retention_days']); ?>" min="30" max="365" class="small-text"> days
                            <p class="description">How long to keep audit logs before automatic deletion.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Data Removal</th>
                        <td>
                            <label>
                                <input type="checkbox" name="remove_data_on_uninstall" <?php checked($settings->get_setting('remove_data_on_uninstall', false)); ?>>
                                Remove all plugin data when uninstalling
                            </label>
                            <p class="description"><strong>Warning:</strong> This will permanently delete all appointments and settings when the plugin is deleted.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="massage_booking_save_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Hide all tabs
            $('.tab-content').hide();
            
            // Remove active class
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Add active class to clicked tab
            $(this).addClass('nav-tab-active');
            
            // Show the corresponding tab
            var target = $(this).attr('href');
            $(target).show();
        });
        
        // Add new time block
        $('.add-block').on('click', function() {
            var day = $(this).data('day');
            var blocksContainer = $('#schedule-' + day);
            var blockCount = blocksContainer.children().length;
            
            var newBlock = '<div class="schedule-block">' +
                           '<input type="time" name="schedule[' + day + '][' + blockCount + '][from]" value="09:00">' +
                           ' to ' +
                           '<input type="time" name="schedule[' + day + '][' + blockCount + '][to]" value="18:00">' +
                           ' <button type="button" class="button remove-block">Remove</button>' +
                           '</div>';
            
            blocksContainer.append(newBlock);
        });
        
        // Remove time block
        $(document).on('click', '.remove-block', function() {
            $(this).closest('.schedule-block').remove();
        });
    });
    </script>
    <?php
}