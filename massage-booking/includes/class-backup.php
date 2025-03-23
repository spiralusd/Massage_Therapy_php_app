<?php
class Massage_Booking_Backup {
    /**
     * Schedule weekly backups
     */
    public function schedule_backups() {
        if (!wp_next_scheduled('massage_booking_backup_event')) {
            wp_schedule_event(time(), 'weekly', 'massage_booking_backup_event');
        }
    }
    
    /**
     * Unschedule backups when plugin is deactivated
     */
    public function unschedule_backups() {
        $timestamp = wp_next_scheduled('massage_booking_backup_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'massage_booking_backup_event');
        }
    }
    
    /**
     * Create database backup
     */
    public function create_backup() {
        global $wpdb;
        
        // Create backup directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/massage-booking-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
            
            // Create .htaccess to protect backup directory
            $htaccess_content = "Deny from all";
            file_put_contents($backup_dir . '/.htaccess', $htaccess_content);
        }
        
        // Create backup filename with date
        $backup_file = $backup_dir . '/backup-' . date('Y-m-d-H-i-s') . '.sql';
        
        // Get appointment table data
        $appointments_table = $wpdb->prefix . 'massage_appointments';
        $appointments = $wpdb->get_results("SELECT * FROM $appointments_table", ARRAY_A);
        
        // Get audit log data
        $audit_log_table = $wpdb->prefix . 'massage_audit_log';
        $audit_logs = $wpdb->get_results("SELECT * FROM $audit_log_table", ARRAY_A);
        
        // Start building SQL file content
        $sql_content = "-- Massage Booking Backup\n";
        $sql_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Add appointments table structure
        $sql_content .= "-- Appointments Table Structure\n";
        $table_structure = $wpdb->get_results("SHOW CREATE TABLE $appointments_table", ARRAY_A);
        if (!empty($table_structure)) {
            $sql_content .= $table_structure[0]['Create Table'] . ";\n\n";
        }
        
        // Add appointments data
        $sql_content .= "-- Appointments Data\n";
        foreach ($appointments as $appointment) {
            $fields = array_map(function($value) use ($wpdb) {
                return $wpdb->prepare("%s", $value);
            }, $appointment);
            
            $sql_content .= "INSERT INTO $appointments_table VALUES (" . implode(', ', $fields) . ");\n";
        }
        
        // Add audit log table structure
        $sql_content .= "\n-- Audit Log Table Structure\n";
        $table_structure = $wpdb->get_results("SHOW CREATE TABLE $audit_log_table", ARRAY_A);
        if (!empty($table_structure)) {
            $sql_content .= $table_structure[0]['Create Table'] . ";\n\n";
        }
        
        // Add audit log data
        $sql_content .= "-- Audit Log Data\n";
        foreach ($audit_logs as $log) {
            $fields = array_map(function($value) use ($wpdb) {
                return $wpdb->prepare("%s", $value);
            }, $log);
            
            $sql_content .= "INSERT INTO $audit_log_table VALUES (" . implode(', ', $fields) . ");\n";
        }
        
        // Write to file
        file_put_contents($backup_file, $sql_content);
        
        // Encrypt the backup file for HIPAA compliance
        $this->encrypt_backup_file($backup_file);
        
        // Log the backup
        $audit_log = new Massage_Booking_Audit_Log();
        $audit_log->log_action('backup_created', 0, 0, 'backup', 'Database backup created');
        
        // Clean up old backups (keep only last 10)
        $this->cleanup_old_backups($backup_dir);
        
        return true;
    }
    
    /**
     * Encrypt backup file
     */
    private function encrypt_backup_file($file_path) {
        // Initialize encryption
        require_once(plugin_dir_path(__FILE__) . 'class-encryption.php');
        $encryption = new Massage_Booking_Encryption();
        
        // Read file content
        $content = file_get_contents($file_path);
        
        // Encrypt content
        $encrypted_content = $encryption->encrypt($content);
        
        // Write encrypted content back to file
        file_put_contents($file_path . '.enc', $encrypted_content);
        
        // Remove original file
        unlink($file_path);
    }
    
    /**
     * Clean up old backups, keeping only the last 10
     */
    private function cleanup_old_backups($backup_dir) {
        $backup_files = glob($backup_dir . '/backup-*.sql.enc');
        
        // Sort by filename (which includes date and time)
        usort($backup_files, function($a, $b) {
            return strcmp($b, $a); // Reverse order to get newest first
        });
        
        // Keep only the last 10 backups
        if (count($backup_files) > 10) {
            for ($i = 10; $i < count($backup_files); $i++) {
                unlink($backup_files[$i]);
            }
        }
    }
}