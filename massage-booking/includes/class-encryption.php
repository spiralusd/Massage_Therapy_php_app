<?php

// includes/class-encryption.php

class Massage_Booking_Encryption {
    private $encryption_key;
    
    public function __construct() {
        // Use a constant defined in wp-config.php for the encryption key
        // This way it's not hardcoded in your plugin
        if (defined('MASSAGE_BOOKING_ENCRYPTION_KEY')) {
            $this->encryption_key = MASSAGE_BOOKING_ENCRYPTION_KEY;
        } else {
            // Fall back to a WordPress-specific key if not defined
            // Note: For production, always define the encryption key in wp-config.php
            $this->encryption_key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
        }
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        
        if ($encrypted === false) {
            return $data; // Return original data if encryption fails
        }
        
        // Combine IV and encrypted data with a delimiter
        return base64_encode($iv . '|' . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        try {
            $data = base64_decode($data);
            
            // Split IV and encrypted data
            list($iv, $encrypted) = explode('|', $data, 2);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
            
            if ($decrypted === false) {
                return ''; // Return empty string if decryption fails
            }
            
            return $decrypted;
        } catch (Exception $e) {
            return ''; // Return empty string on error
        }
    }
}