<?php
/**
 * Database Extension - Adds missing methods to Massage_Booking_Database class
 * 
 * This file should be included after the main database class to add the
 * required methods for appointments management.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if the main database class exists before extending it
 */
if (class_exists('Massage_Booking_Database') && !method_exists('Massage_Booking_Database', 'get_all_appointments')) {
    /**
     * Extend the main database class with missing methods
     */
    class Massage_Booking_Database_Extension extends Massage_Booking_Database {
        
        /**
         * Get all appointments
         *
         * @param string|null $status Filter by status (optional)
         * @param string|null $date_from Start date in Y-m-d format (optional)
         * @param string|null $date_to End date in Y-m-d format (optional)
         * @return array Array of appointment objects
         */
        public function get_all_appointments($status = null, $date_from = null, $date_to = null) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            
            // Build query
            $query = "SELECT * FROM $table";
            $where = [];
            $params = [];
            
            // Add status filter if provided
            if (!is_null($status)) {
                $where[] = "status = %s";
                $params[] = sanitize_text_field($status);
            }
            
            // Add date range filters if provided
            if (!is_null($date_from)) {
                $where[] = "appointment_date >= %s";
                $params[] = sanitize_text_field($date_from);
            }
            
            if (!is_null($date_to)) {
                $where[] = "appointment_date <= %s";
                $params[] = sanitize_text_field($date_to);
            }
            
            // Add WHERE clause if we have conditions
            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }
            
            // Add ORDER BY
            $query .= " ORDER BY appointment_date DESC, start_time DESC";
            
            // Prepare the query if we have parameters
            if (!empty($params)) {
                $query = $wpdb->prepare($query, $params);
            }
            
            // Get results
            $appointments = $wpdb->get_results($query);
            
            // Decrypt sensitive data if needed
            if (!empty($appointments)) {
                // Initialize encryption if available
                if (class_exists('Massage_Booking_Encryption')) {
                    $encryption = new Massage_Booking_Encryption();
                    
                    foreach ($appointments as $key => $appointment) {
                        // Convert to object if not already
                        $appointment = (object)$appointment;
                        
                        // Add decoded client name for display
                        try {
                            $appointment->client_name = $encryption->decrypt($appointment->full_name);
                            $appointment->client_email = $encryption->decrypt($appointment->email);
                            $appointment->client_phone = $encryption->decrypt($appointment->phone);
                        } catch (Exception $e) {
                            // If decryption fails, use the encrypted values
                            $appointment->client_name = $appointment->full_name;
                            $appointment->client_email = $appointment->email;
                            $appointment->client_phone = $appointment->phone;
                        }
                        
                        $appointments[$key] = $appointment;
                    }
                } else {
                    // If encryption class is not available, just copy the fields
                    foreach ($appointments as $key => $appointment) {
                        // Convert to object if not already
                        $appointment = (object)$appointment;
                        
                        $appointment->client_name = $appointment->full_name;
                        $appointment->client_email = $appointment->email;
                        $appointment->client_phone = $appointment->phone;
                        
                        $appointments[$key] = $appointment;
                    }
                }
            }
            
            return $appointments;
        }
        
        /**
         * Count appointments by status
         *
         * @param string $status Status to count
         * @return int Appointment count
         */
        public function count_appointments_by_status($status) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            $status = sanitize_text_field($status);
            
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s",
                $status
            ));
        }
        
        /**
         * Get upcoming appointments
         *
         * @param int $limit Maximum number of appointments to return
         * @param string $status Status filter (default: 'confirmed')
         * @return array Array of appointment objects
         */
        public function get_upcoming_appointments($limit = 5, $status = 'confirmed') {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            $today = date('Y-m-d');
            
            $query = $wpdb->prepare(
                "SELECT * FROM $table WHERE appointment_date >= %s AND status = %s 
                ORDER BY appointment_date ASC, start_time ASC LIMIT %d",
                $today,
                $status,
                intval($limit)
            );
            
            $appointments = $wpdb->get_results($query);
            
            // Decrypt sensitive data if needed
            if (!empty($appointments)) {
                // Initialize encryption if available
                if (class_exists('Massage_Booking_Encryption')) {
                    $encryption = new Massage_Booking_Encryption();
                    
                    foreach ($appointments as $key => $appointment) {
                        // Convert to object if not already
                        $appointment = (object)$appointment;
                        
                        // Add decoded client name for display
                        try {
                            $appointment->client_name = $encryption->decrypt($appointment->full_name);
                            $appointment->client_email = $encryption->decrypt($appointment->email);
                            $appointment->client_phone = $encryption->decrypt($appointment->phone);
                        } catch (Exception $e) {
                            // If decryption fails, use the encrypted values
                            $appointment->client_name = $appointment->full_name;
                            $appointment->client_email = $appointment->email;
                            $appointment->client_phone = $appointment->phone;
                        }
                        
                        $appointments[$key] = $appointment;
                    }
                } else {
                    // If encryption class is not available, just copy the fields
                    foreach ($appointments as $key => $appointment) {
                        // Convert to object if not already
                        $appointment = (object)$appointment;
                        
                        $appointment->client_name = $appointment->full_name;
                        $appointment->client_email = $appointment->email;
                        $appointment->client_phone = $appointment->phone;
                        
                        $appointments[$key] = $appointment;
                    }
                }
            }
            
            return $appointments;
        }
        
        /**
         * Update appointment status
         *
         * @param int $appointment_id Appointment ID
         * @param string $status New status
         * @return bool Success or failure
         */
        public function update_appointment_status($appointment_id, $status) {
            global $wpdb;
            $table = $wpdb->prefix . 'massage_appointments';
            
            $result = $wpdb->update(
                $table,
                ['status' => sanitize_text_field($status)],
                ['id' => intval($appointment_id)],
                ['%s'],
                ['%d']
            );
            
            // Log the action if audit log class exists
            if (class_exists('Massage_Booking_Audit_Log')) {
                $audit_log = new Massage_Booking_Audit_Log();
                $audit_log->log_action(
                    'appointment_status_update',
                    get_current_user_id(),
                    $appointment_id,
                    'appointment',
                    ['new_status' => $status]
                );
            }
            
            return $result !== false;
        }
    }
    
    /**
     * Replace the database class with our extended version
     */
    function massage_booking_replace_database_class() {
        global $massage_booking_database;
        
        // If the global database instance exists, replace it
        if (isset($massage_booking_database) && is_object($massage_booking_database)) {
            $massage_booking_database = new Massage_Booking_Database_Extension();
        }
    }
    add_action('plugins_loaded', 'massage_booking_replace_database_class', 20);
}
