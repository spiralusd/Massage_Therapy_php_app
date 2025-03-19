<?php
/**
 * Encryption Handler Class - Optimized Version
 * 
 * Provides HIPAA-compliant encryption functionality for sensitive client data.
 * 
 * @package Massage_Booking
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Massage_Booking_Encryption {
    /**
     * Encryption key
     *
     * @var string
     */
    private $encryption_key;
    
    /**
     * Cipher method
     *
     * @var string
     */
    private $cipher_method = 'AES-256-CBC';
    
    /**
     * Constructor
     * Sets up encryption key
     */
    public function __construct() {
        // Use constant defined in wp-config.php for the encryption key if available
        if (defined('MASSAGE_BOOKING_ENCRYPTION_KEY')) {
            $this->encryption_key = MASSAGE_BOOKING_ENCRYPTION_KEY;
        } else {
            // Fall back to WordPress salts if not defined
            // Note: For production, always define the encryption key in wp-config.php
            $this->encryption_key = $this->generate_key_from_salts();
        }
        
        // Validate encryption method is available
        if (!in_array($this->cipher_method, openssl_get_cipher_methods())) {
            // Fall back to a standard method if the preferred one isn't available
            $this->cipher_method = 'AES-128-CBC';
            
            // Log warning about less secure encryption
            error_log('Warning: AES-256-CBC not available, falling back to ' . $this->cipher_method);
        }
    }
    
    /**
     * Generate encryption key from WordPress salts
     *
     * @return string Generated key
     */
    private function generate_key_from_salts() {
        // Use WordPress authentication keys and salts as a source of entropy
        $salt_keys = [
            defined('AUTH_KEY') ? AUTH_KEY : '',
            defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '',
            defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '',
            defined('NONCE_KEY') ? NONCE_KEY : '',
            defined('AUTH_SALT') ? AUTH_SALT : '',
            defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '',
            defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : '',
            defined('NONCE_SALT') ? NONCE_SALT : '',
        ];
        
        // Combine salts and hash them
        $combined_salts = implode('', array_filter($salt_keys));
        
        if (empty($combined_salts)) {
            // If no salts are defined, use a default (not recommended for production)
            error_log('Warning: No WordPress salts defined, using default encryption key');
            $combined_salts = 'massage_booking_default_key_' . get_site_url();
        }
        
        // Create a 256-bit key (32 bytes) using SHA-256
        return hash('sha256', $combined_salts, true);
    }
    
    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string|bool Encrypted data or false on failure
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        try {
            // Generate initialization vector
            $iv_length = openssl_cipher_iv_length($this->cipher_method);
            $iv = openssl_random_pseudo_bytes($iv_length);
            
            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                $this->cipher_method,
                $this->encryption_key,
                0,
                $iv
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed: ' . openssl_error_string());
            }
            
            // Combine IV and encrypted data with a delimiter
            // The IV needs to be stored with the data for decryption
            $result = base64_encode($iv . '|' . $encrypted);
            
            return $result;
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt data
     *
     * @param string $data Encrypted data
     * @return string|bool Decrypted data or false on failure
     */
    public function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        try {
            $decoded = base64_decode($data);
            if ($decoded === false) {
                throw new Exception('Base64 decoding failed');
            }
            
            // Split IV and encrypted data
            $parts = explode('|', $decoded, 2);
            if (count($parts) !== 2) {
                throw new Exception('Invalid encrypted data format');
            }
            
            list($iv, $encrypted) = $parts;
            
            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher_method,
                $this->encryption_key,
                0,
                $iv
            );
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed: ' . openssl_error_string());
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test if encryption is properly configured
     *
     * @return bool True if encryption is working, false otherwise
     */
    public function test_encryption() {
        $test_string = 'Test encryption string: ' . time();
        $encrypted = $this->encrypt($test_string);
        
        if ($encrypted === false) {
            return false;
        }
        
        $decrypted = $this->decrypt($encrypted);
        
        return $decrypted === $test_string;
    }
}