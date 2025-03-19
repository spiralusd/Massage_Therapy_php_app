<?php
// includes/class-database.php

class Massage_Booking_Database {
    
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Appointments table
        $appointments_table = $wpdb->prefix . 'massage_appointments';
        $sql = "CREATE TABLE $appointments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            full_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
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
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Special dates table
        $special_dates_table = $wpdb->prefix . 'massage_special_dates';
        $sql2 = "CREATE TABLE $special_dates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            type varchar(20) NOT NULL,
            available tinyint(1) DEFAULT 0,
            start_time time,
            end_time time,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }
    
    public function get_appointments($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'massage_appointments';
        
        $defaults = [
            'status' => 'confirmed',
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'appointment_date',
            'order' => 'ASC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = [];
        $where[] = $wpdb->prepare("status = %s", $args['status']);
        
        if ($args['date_from']) {
            $where[] = $wpdb->prepare("appointment_date >= %s", $args['date_from']);
        }
        
        if ($args['date_to']) {
            $where[] = $wpdb->prepare("appointment_date <= %s", $args['date_to']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
        $prepared = $wpdb->prepare($query, $args['limit'], $args['offset']);
        
        return $wpdb->get_results($prepared, ARRAY_A);
    }
    
public function create_appointment($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'massage_appointments';
    
    // Initialize encryption
    $encryption = new Massage_Booking_Encryption();
    
    // Encrypt sensitive data
    $encrypted_data = [
        'full_name' => $encryption->encrypt(sanitize_text_field($data['full_name'])),
        'email' => $encryption->encrypt(sanitize_email($data['email'])),
        'phone' => $encryption->encrypt(sanitize_text_field($data['phone'])),
        'appointment_date' => $data['appointment_date'], // Dates don't need encryption
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'duration' => intval($data['duration']),
        'focus_areas' => $encryption->encrypt(is_array($data['focus_areas']) ? 
            implode(',', array_map('sanitize_text_field', $data['focus_areas'])) : 
            sanitize_text_field($data['focus_areas'])),
        'pressure_preference' => $encryption->encrypt(sanitize_text_field($data['pressure_preference'])),
        'special_requests' => $encryption->encrypt(sanitize_textarea_field($data['special_requests'])),
        'status' => 'confirmed',
        'calendar_event_id' => isset($data['calendar_event_id']) ? $data['calendar_event_id'] : '',
    ];
    
    $result = $wpdb->insert($table, $encrypted_data);
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

// Add a method to decrypt when retrieving appointments
public function get_appointment($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'massage_appointments';
    
    $appointment = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
        ARRAY_A
    );
    
    if (!$appointment) {
        return false;
    }
    
    // Initialize encryption
    $encryption = new Massage_Booking_Encryption();
    
    // Decrypt sensitive fields
    $appointment['full_name'] = $encryption->decrypt($appointment['full_name']);
    $appointment['email'] = $encryption->decrypt($appointment['email']);
    $appointment['phone'] = $encryption->decrypt($appointment['phone']);
    $appointment['focus_areas'] = $encryption->decrypt($appointment['focus_areas']);
    $appointment['pressure_preference'] = $encryption->decrypt($appointment['pressure_preference']);
    $appointment['special_requests'] = $encryption->decrypt($appointment['special_requests']);
    
    return $appointment;
}
    
// Add a method to decrypt when retrieving appointments
public function get_appointment($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'massage_appointments';
    
    $appointment = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
        ARRAY_A
    );
    
    if (!$appointment) {
        return false;
    }
    
    // Initialize encryption
    $encryption = new Massage_Booking_Encryption();
    
    // Decrypt sensitive fields
    $appointment['full_name'] = $encryption->decrypt($appointment['full_name']);
    $appointment['email'] = $encryption->decrypt($appointment['email']);
    $appointment['phone'] = $encryption->decrypt($appointment['phone']);
    $appointment['focus_areas'] = $encryption->decrypt($appointment['focus_areas']);
    $appointment['pressure_preference'] = $encryption->decrypt($appointment['pressure_preference']);
    $appointment['special_requests'] = $encryption->decrypt($appointment['special_requests']);
    
    return $appointment;
}
}