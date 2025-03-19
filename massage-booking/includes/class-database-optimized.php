<?php
/**
 * Database Management Class - Optimized Version
 * 
 * Handles database operations for the massage booking system
 * including creating tables, retrieving and storing appointments with encryption.
 * 
 * @package Massage_Booking
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Database {
    
    /**
     * Create database tables for the plugin
     * 
     * @return void
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Appointments table
        $appointments_table = $wpdb->prefix . 'massage_appointments';
        $sql = "CREATE TABLE $appointments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(100) NOT NULL,
            appointment_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            duration int NOT NULL,
            focus_areas text,
            pressure_preference varchar(50),
            special_requests text,
            status varchar(20) DEFAULT 'confirmed',
            calendar_event_id varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20),
            ip_address varchar(45),
            PRIMARY KEY  (id),
            KEY appointment_date (appointment_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Special dates table (for holidays, special hours, etc.)
        $special_dates_table = $wpdb->prefix . 'massage_special_dates';
        $sql2 = "CREATE TABLE $special_dates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            type varchar(20) NOT NULL,
            available tinyint(1) DEFAULT 0,
            start_time time,
            end_time time,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20),
            PRIMARY KEY  (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";

        // Audit log table (for HIPAA compliance)
        $audit_log_table = $wpdb->prefix . 'massage_audit_log';
        $sql3 = "CREATE TABLE $audit_log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            user_id bigint(20),
            object_id bigint(20),
            object_type varchar(50),
            details longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY user_id (user_id),
            KEY object_id (object_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Execute the SQL
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
        dbDelta($sql3);
    }
    
    /**
     * Get appointments based on criteria
     * 
     * @param array $args Query arguments
     * @return array Array of appointments
     */
    public function get_appointments($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        // Default arguments
        $defaults = [
            'status' => 'confirmed',
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'appointment_date',
            'order' => 'ASC',
            'decrypt' => false // Whether to decrypt sensitive data
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_clauses = [];
        $query_args = [];
        
        // Status filter
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $query_args[] = $args['status'];
        }
        
        // Date range filter
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'appointment_date >= %s';
            $query_args[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'appointment_date <= %s';
            $query_args[] = $args['date_to'];
        }
        
        // Build full WHERE clause
        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Sanitize orderby and order
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'appointment_date ASC';
        
        // Add LIMIT and OFFSET
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        // Build and prepare final query
        $query = "SELECT * FROM $table $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $query_args[] = $limit;
        $query_args[] = $offset;
        
        $prepared_query = $wpdb->prepare($query, $query_args);
        
        // Get results
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        // Decrypt sensitive data if requested
        if ($args['decrypt'] && !empty($results)) {
            $encryption = new Massage_Booking_Encryption();
            
            foreach ($results as $key => $appointment) {
                $results[$key] = $this->decrypt_appointment_data($appointment, $encryption);
            }
        }
        
        return $results;
    }
    
    /**
     * Create a new appointment with encrypted sensitive data
     * 
     * @param array $data Appointment data
     * @return int|false Appointment ID on success, false on failure
     */
    public function create_appointment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        try {
            // Initialize encryption
            $encryption = new Massage_Booking_Encryption();
            
            // Validate and sanitize input data
            $sanitized_data = $this->sanitize_appointment_data($data);
            
            // Encrypt sensitive data
            $encrypted_data = $this->encrypt_appointment_data($sanitized_data, $encryption);
            
            // Add metadata
            $encrypted_data['created_at'] = current_time('mysql');
            $encrypted_data['created_by'] = get_current_user_id() ?: 0;
            $encrypted_data['ip_address'] = $this->get_client_ip();
            
            // Insert into database
            $result = $wpdb->insert($table, $encrypted_data);
            
            if ($result === false) {
                // Log database error
                error_log('Database error in create_appointment: ' . $wpdb->last_error);
                return false;
            }
            
            $appointment_id = $wpdb->insert_id;
            
            // Log the action for HIPAA compliance
            $this->log_appointment_action('create', $appointment_id);
            
            return $appointment_id;
        } catch (Exception $e) {
            error_log('Exception in create_appointment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a single appointment by ID with decrypted data
     * 
     * @param int $id Appointment ID
     * @return array|false Appointment data or false on failure
     */
    public function get_appointment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        $appointment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)),
            ARRAY_A
        );
        
        if (!$appointment) {
            return false;
        }
        
        // Initialize encryption
        $encryption = new Massage_Booking_Encryption();
        
        // Decrypt sensitive fields
        $appointment = $this->decrypt_appointment_data($appointment, $encryption);
        
        // Log the access for HIPAA compliance
        $this->log_appointment_action('view', $id);
        
        return $appointment;
    }
    
    /**
     * Update an appointment
     * 
     * @param int $id Appointment ID
     * @param array $data Updated appointment data
     * @return bool Success or failure
     */
    public function update_appointment($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        try {
            // Get existing appointment for comparison
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)),
                ARRAY_A
            );
            
            if (!$existing) {
                return false;
            }
            
            // Initialize encryption
            $encryption = new Massage_Booking_Encryption();
            
            // Validate and sanitize input data
            $sanitized_data = $this->sanitize_appointment_data($data);
            
            // Encrypt sensitive data
            $encrypted_data = $this->encrypt_appointment_data($sanitized_data, $encryption);
            
            // Update in database
            $result = $wpdb->update(
                $table, 
                $encrypted_data,
                ['id' => intval($id)]
            );
            
            if ($result === false) {
                // Log database error
                error_log('Database error in update_appointment: ' . $wpdb->last_error);
                return false;
            }
            
            // Log the action for HIPAA compliance
            $this->log_appointment_action('update', $id);
            
            return true;
        } catch (Exception $e) {
            error_log('Exception in update_appointment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete an appointment
     * 
     * @param int $id Appointment ID
     * @return bool Success or failure
     */
    public function delete_appointment($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        $result = $wpdb->delete(
            $table,
            ['id' => intval($id)],
            ['%d']
        );
        
        if ($result) {
            // Log the action for HIPAA compliance
            $this->log_appointment_action('delete', $id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize appointment data
     * 
     * @param array $data Raw appointment data
     * @return array Sanitized data
     * @throws Exception If invalid data is provided
     */
    private function sanitize_appointment_data($data) {
        $sanitized = [];
        
        // Text fields with length limits
        $text_fields = [
            'full_name' => 100,
            'email' => 100,
            'phone' => 20,
            'pressure_preference' => 50,
            'status' => 20,
            'calendar_event_id' => 255
        ];
        
        foreach ($text_fields as $field => $max_length) {
            if (isset($data[$field])) {
                // Sanitize and truncate
                $value = sanitize_text_field($data[$field]);
                $sanitized[$field] = substr($value, 0, $max_length);
            }
        }
        
        // Special request (textarea)
        if (isset($data['special_requests'])) {
            $sanitized['special_requests'] = sanitize_textarea_field(
                substr($data['special_requests'], 0, 1000)
            );
        }
        
        // Email validation
        if (isset($sanitized['email']) && !is_email($sanitized['email'])) {
            throw new Exception('Invalid email address');
        }
        
        // Date validation
        if (isset($data['appointment_date'])) {
            $date = sanitize_text_field($data['appointment_date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $sanitized['appointment_date'] = $date;
            } else {
                throw new Exception('Invalid date format');
            }
        }
        
        // Time validation
        $time_fields = ['start_time', 'end_time'];
        foreach ($time_fields as $field) {
            if (isset($data[$field])) {
                $time = sanitize_text_field($data[$field]);
                if (preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $time)) {
                    $sanitized[$field] = $time;
                } else {
                    throw new Exception('Invalid time format');
                }
            }
        }
        
        // Duration (integer)
        if (isset($data['duration'])) {
            $sanitized['duration'] = intval($data['duration']);
        }
        
        // Focus areas (array or comma-separated string)
        if (isset($data['focus_areas'])) {
            if (is_array($data['focus_areas'])) {
                $sanitized['focus_areas'] = implode(',', array_map('sanitize_text_field', $data['focus_areas']));
            } else {
                $sanitized['focus_areas'] = sanitize_text_field($data['focus_areas']);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Encrypt appointment data
     * 
     * @param array $data Sanitized appointment data
     * @param Massage_Booking_Encryption $encryption Encryption instance
     * @return array Encrypted data
     */
    private function encrypt_appointment_data($data, $encryption) {
        $encrypted = $data;
        
        // Fields to encrypt
        $sensitive_fields = ['full_name', 'email', 'phone', 'focus_areas', 'pressure_preference', 'special_requests'];
        
        // Encrypt sensitive fields
        foreach ($sensitive_fields as $field) {
            if (isset($encrypted[$field])) {
                $encrypted[$field] = $encryption->encrypt($encrypted[$field]);
            }
        }
        
        return $encrypted;
    }
    
    /**
     * Decrypt appointment data
     * 
     * @param array $data Encrypted appointment data
     * @param Massage_Booking_Encryption $encryption Encryption instance
     * @return array Decrypted data
     */
    private function decrypt_appointment_data($data, $encryption) {
        $decrypted = $data;
        
        // Fields to decrypt
        $sensitive_fields = ['full_name', 'email', 'phone', 'focus_areas', 'pressure_preference', 'special_requests'];
        
        // Decrypt sensitive fields
        foreach ($sensitive_fields as $field) {
            if (isset($decrypted[$field])) {
                try {
                    $decrypted[$field] = $encryption->decrypt($decrypted[$field]);
                } catch (Exception $e) {
                    // If decryption fails, use empty string
                    $decrypted[$field] = '';
                    error_log('Decryption error for field ' . $field . ': ' . $e->getMessage());
                }
            }
        }
        
        return $decrypted;
    }
    
    /**
     * Log appointment action for HIPAA compliance
     * 
     * @param string $action Action performed (create, view, update, delete)
     * @param int $appointment_id Appointment ID
     * @return void
     */
    private function log_appointment_action($action, $appointment_id) {
        // Check if audit log class exists
        if (!class_exists('Massage_Booking_Audit_Log')) {
            return;
        }
        
        // Create audit log entry
        $audit_log = new Massage_Booking_Audit_Log();
        $audit_log->log_action(
            'appointment_' . $action,
            get_current_user_id(),
            $appointment_id,
            'appointment'
        );
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
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
     * Check if a time slot is available
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param int $duration Duration in minutes
     * @return bool True if available, false if not
     */
    public function check_slot_availability($date, $time, $duration) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        // Calculate end time
        $datetime = new DateTime($date . ' ' . $time);
        $end_datetime = clone $datetime;
        $end_datetime->modify('+' . intval($duration) . ' minutes');
        $end_time = $end_datetime->format('H:i');
        
        // Get settings for break time
        $settings = new Massage_Booking_Settings();
        $break_time = intval($settings->get_setting('break_time', 15));
        
        // Check for overlapping appointments
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE appointment_date = %s 
            AND status = 'confirmed'
            AND (
                (start_time <= %s AND end_time > %s) OR
                (start_time < %s AND end_time >= %s) OR
                (start_time >= %s AND start_time < %s)
            )",
            $date,
            $time,
            $time,
            $end_time,
            $end_time,
            $time,
            $end_time
        );
        
        $overlapping = (int) $wpdb->get_var($query);
        
        // Also check special dates table for holidays or custom hours
        $special_dates_table = $wpdb->prefix . 'massage_special_dates';
        $query = $wpdb->prepare(
            "SELECT * FROM $special_dates_table 
            WHERE date = %s",
            $date
        );
        
        $special_date = $wpdb->get_row($query);
        
        // If it's a holiday or unavailable day
        if ($special_date && !$special_date->available) {
            return false;
        }
        
        // If it's a day with custom hours, check if the time is within those hours
        if ($special_date && $special_date->available) {
            $start_within_hours = $time >= $special_date->start_time && $time < $special_date->end_time;
            $end_within_hours = $end_time > $special_date->start_time && $end_time <= $special_date->end_time;
            
            if (!$start_within_hours || !$end_within_hours) {
                return false;
            }
        }
        
        return !$overlapping;
    }
}