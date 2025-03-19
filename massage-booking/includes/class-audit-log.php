<?php
class Massage_Booking_Audit_Log {
    public function log_action($action, $user_id = null, $object_id = null, $object_type = null, $details = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_audit_log';
        
        // If no user ID provided, try to get current user
        if (is_null($user_id) && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        
        // Get IP address
        $ip_address = $this->get_client_ip();
        
        // Serialize details if it's an array
        if (is_array($details)) {
            $details = serialize($details);
        }
        
        // Insert log entry
        $wpdb->insert(
            $table,
            [
                'action' => sanitize_text_field($action),
                'user_id' => $user_id,
                'object_id' => $object_id,
                'object_type' => $object_type,
                'details' => $details,
                'ip_address' => $ip_address,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    // Helper function to get client IP
    private function get_client_ip() {
        $ip_address = '';
        
        // Check for proxy forwarded IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_addresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_addresses[0]);
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip_address);
    }
    
    // Create the audit log table
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'massage_audit_log';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            user_id bigint(20),
            object_id bigint(20),
            object_type varchar(50),
            details longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY user_id (user_id),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}