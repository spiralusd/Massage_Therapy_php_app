<?php
/**
 * Audit Log Class - Optimized Version
 * 
 * Provides HIPAA-compliant audit logging for all system actions.
 * Fixed to properly work with the database schema and admin page display.
 * 
 * @package Massage_Booking
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Audit_Log {
    /**
     * Log an action in the system
     *
     * @param string $action Action being performed
     * @param int|null $user_id User ID performing the action (or null for system/anonymous)
     * @param int|null $object_id ID of object being acted upon (optional)
     * @param string|null $object_type Type of object being acted upon (optional)
     * @param mixed|null $details Additional details about the action (optional)
     * @return int|false The log entry ID or false on failure
     */
    public function log_action($action, $user_id = null, $object_id = null, $object_type = null, $details = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_audit_log';
        
        // If no user ID provided, try to get current user
        if (is_null($user_id) && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        
        // Get IP address and other client information
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Sanitize action name
        $action = sanitize_text_field($action);
        
        // Handle details - could be array, object, or scalar
        if (is_array($details) || is_object($details)) {
            $details = wp_json_encode($details);
        } elseif (!is_null($details)) {
            $details = sanitize_text_field($details);
        }
        
        // Current timestamp
        $now = current_time('mysql');
        
        // Prepare data for insertion
        $data = [
            'action' => $action,
            'user_id' => $user_id,
            'object_id' => $object_id,
            'object_type' => $object_type ? sanitize_text_field($object_type) : null,
            'details' => $details,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => $now,
            'timestamp' => $now // Adding for backwards compatibility
        ];
        
        // Insert log entry
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            error_log('Failed to log audit action: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get audit log entries based on criteria
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public function get_logs($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_audit_log';
        
        // Default arguments
        $defaults = [
            'action' => null,
            'user_id' => null,
            'object_id' => null,
            'object_type' => null,
            'date_from' => null,
            'date_to' => null,
            'ip_address' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_clauses = [];
        $query_args = [];
        
        // Filter by action
        if (!empty($args['action'])) {
            $where_clauses[] = 'action = %s';
            $query_args[] = $args['action'];
        }
        
        // Filter by user
        if (!is_null($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $query_args[] = intval($args['user_id']);
        }
        
        // Filter by object
        if (!is_null($args['object_id'])) {
            $where_clauses[] = 'object_id = %d';
            $query_args[] = intval($args['object_id']);
        }
        
        // Filter by object type
        if (!empty($args['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $query_args[] = $args['object_type'];
        }
        
        // Filter by IP address
        if (!empty($args['ip_address'])) {
            $where_clauses[] = 'ip_address = %s';
            $query_args[] = $args['ip_address'];
        }
        
        // Date range filter - try both created_at and timestamp columns for compatibility
        if (!empty($args['date_from'])) {
            // Check if the table has created_at or timestamp or both
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
            if (in_array('created_at', $columns)) {
                $where_clauses[] = 'created_at >= %s';
            } else if (in_array('timestamp', $columns)) {
                $where_clauses[] = 'timestamp >= %s';
            }
            $query_args[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            // Check if the table has created_at or timestamp or both
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
            if (in_array('created_at', $columns)) {
                $where_clauses[] = 'created_at <= %s';
            } else if (in_array('timestamp', $columns)) {
                $where_clauses[] = 'timestamp <= %s';
            }
            $query_args[] = $args['date_to'];
        }
        
        // Build full WHERE clause
        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Check if orderby column exists and use appropriate column
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
        $orderby_col = $args['orderby'];
        
        // If requested column doesn't exist but has an equivalent, swap it
        if (!in_array($orderby_col, $columns)) {
            if ($orderby_col === 'created_at' && in_array('timestamp', $columns)) {
                $orderby_col = 'timestamp';
            } else if ($orderby_col === 'timestamp' && in_array('created_at', $columns)) {
                $orderby_col = 'created_at';
            }
        }
        
        // Sanitize orderby and order
        $orderby = sanitize_sql_orderby($orderby_col . ' ' . $args['order']) ?: 'id DESC';
        
        // Add LIMIT and OFFSET
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        // Build and prepare final query
        $query = "SELECT * FROM $table $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $query_args[] = $limit;
        $query_args[] = $offset;
        
        $prepared_query = $wpdb->prepare($query, $query_args);
        
        // Execute query
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Process results to format details JSON if needed
        foreach ($results as &$log) {
            if (!empty($log['details']) && $this->is_json($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
            
            // Add user display name if available
            if (!empty($log['user_id'])) {
                $user = get_userdata($log['user_id']);
                if ($user) {
                    $log['user_name'] = $user->display_name;
                }
            }
            
            // Ensure consistency in datetime field naming
            if (isset($log['timestamp']) && !isset($log['created_at'])) {
                $log['created_at'] = $log['timestamp'];
            } else if (isset($log['created_at']) && !isset($log['timestamp'])) {
                $log['timestamp'] = $log['created_at'];
            }
        }
        
        return $results;
    }
    
    /**
     * Create the audit log table
     *
     * @return void
     */
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
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY user_id (user_id),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if the table exists but lacks the timestamp column
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        if (!in_array('timestamp', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER created_at");
            
            // Update existing rows to set timestamp = created_at
            $wpdb->query("UPDATE $table_name SET timestamp = created_at WHERE timestamp = '0000-00-00 00:00:00'");
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
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
    
    /**
     * Clean up old audit logs
     *
     * @param int $days Number of days to keep (default 365)
     * @return int Number of rows deleted
     */
    public function cleanup_old_logs($days = 365) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_audit_log';
        
        // Calculate cutoff date
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Check which date column exists
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
        $date_col = in_array('created_at', $columns) ? 'created_at' : 'timestamp';
        
        // Delete old records
        $query = $wpdb->prepare(
            "DELETE FROM $table WHERE $date_col < %s",
            $cutoff_date
        );
        
        $deleted = $wpdb->query($query);
        
        // Log the cleanup
        $this->log_action('audit_log_cleanup', null, null, 'audit_log', [
            'days_kept' => $days,
            'records_deleted' => $deleted
        ]);
        
        return $deleted;
    }
    
    /**
     * Check if a string is valid JSON
     *
     * @param string $string String to check
     * @return bool True if valid JSON, false otherwise
     */
    private function is_json($string) {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    
    /**
     * Check audit log database schema and update if needed
     *
     * @return bool True if schema is correct, false otherwise
     */
    public function check_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_audit_log';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        
        if (!$table_exists) {
            // Create table if it doesn't exist
            $this->create_table();
            return true;
        }
        
        // Check columns
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
        $required_columns = [
            'id', 'action', 'user_id', 'object_id', 'object_type', 
            'details', 'ip_address', 'user_agent'
        ];
        
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            // Missing required columns
            error_log('Audit log table missing columns: ' . implode(', ', $missing_columns));
            return false;
        }
        
        // Check if either created_at or timestamp exists
        if (!in_array('created_at', $columns) && !in_array('timestamp', $columns)) {
            // Add created_at column
            $wpdb->query("ALTER TABLE $table ADD created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL");
            
            // Set all rows to current time
            $wpdb->query("UPDATE $table SET created_at = '" . current_time('mysql') . "'");
            
            // Add index
            $wpdb->query("ALTER TABLE $table ADD KEY created_at (created_at)");
        }
        
        // If the table has created_at but not timestamp, add timestamp
        if (in_array('created_at', $columns) && !in_array('timestamp', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER created_at");
            
            // Update existing rows to set timestamp = created_at
            $wpdb->query("UPDATE $table SET timestamp = created_at");
        }
        
        // If the table has timestamp but not created_at, add created_at
        if (in_array('timestamp', $columns) && !in_array('created_at', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL AFTER user_agent");
            
            // Update existing rows to set created_at = timestamp
            $wpdb->query("UPDATE $table SET created_at = timestamp");
            
            // Add index
            $wpdb->query("ALTER TABLE $table ADD KEY created_at (created_at)");
        }
        
        return true;
    }
}