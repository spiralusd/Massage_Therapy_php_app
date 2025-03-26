<?php
/**
 * Massage Booking Form Debug
 * 
 * This file helps diagnose issues with the booking form.
 */

// Include WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Security check - only admins can access this
if (!current_user_can('manage_options')) {
    die('Admin access required');
}

echo '<h1>Massage Booking Form Debug</h1>';

// Check for plugin directories
echo '<h2>Directory Structure</h2>';
echo '<p>Plugin root: ' . plugin_dir_path(__FILE__) . '</p>';

$public_dir = plugin_dir_path(__FILE__) . 'public/';
$booking_form_path = $public_dir . 'booking-form.php';

echo '<p>Public directory exists: ' . (is_dir($public_dir) ? 'Yes' : 'No') . '</p>';
echo '<p>booking-form.php path: ' . $booking_form_path . '</p>';
echo '<p>booking-form.php exists: ' . (file_exists($booking_form_path) ? 'Yes' : 'No') . '</p>';

// Try to include the booking form file
echo '<h2>File Inclusion Test</h2>';
if (file_exists($booking_form_path)) {
    echo '<p>Attempting to include booking-form.php...</p>';
    
    ob_start();
    $error = false;
    try {
        include_once($booking_form_path);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    $output = ob_get_clean();
    
    if ($error) {
        echo '<p style="color:red">Error including file: ' . $error . '</p>';
    } else {
        echo '<p style="color:green">File included successfully</p>';
    }
    
    // Check if the function exists
    echo '<p>massage_booking_display_form function exists: ' . (function_exists('massage_booking_display_form') ? 'Yes' : 'No') . '</p>';
    
    // Show file contents for inspection
    echo '<h3>File Contents:</h3>';
    echo '<pre style="background:#eee;padding:10px;overflow:auto;max-height:400px;font-size:12px;">';
    echo htmlspecialchars(file_get_contents($booking_form_path));
    echo '</pre>';
    
    // Show a list of defined functions
    echo '<h3>Defined Functions:</h3>';
    $functions = get_defined_functions();
    $user_functions = $functions['user'];
    echo '<p>Total user functions: ' . count($user_functions) . '</p>';
    echo '<ul>';
    foreach ($user_functions as $function) {
        if (strpos($function, 'massage_booking') !== false) {
            echo '<li>' . $function . '</li>';
        }
    }
    echo '</ul>';
} else {
    echo '<p style="color:red">Cannot include booking-form.php because it does not exist!</p>';
}

// Check shortcodes
echo '<h2>Shortcode Registration Test</h2>';
$shortcodes = $GLOBALS['shortcode_tags'];
echo '<p>massage_booking_form shortcode registered: ' . (isset($shortcodes['massage_booking_form']) ? 'Yes' : 'No') . '</p>';
if (isset($shortcodes['massage_booking_form'])) {
    echo '<p>Shortcode handler: ' . (is_array($shortcodes['massage_booking_form']) ? 'Object Method' : 'Function') . '</p>';
    if (!is_array($shortcodes['massage_booking_form'])) {
        echo '<p>Handler name: ' . $shortcodes['massage_booking_form'] . '</p>';
        echo '<p>Handler exists: ' . (function_exists($shortcodes['massage_booking_form']) ? 'Yes' : 'No') . '</p>';
    }
}

// File structure dump
echo '<h2>Plugin File Structure</h2>';
function list_dir_contents($dir, $indent = 0) {
    if (!is_dir($dir)) return;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . '/' . $file;
        echo str_repeat('&nbsp;', $indent * 4) . ($indent > 0 ? '└─ ' : '') . $file;
        
        if (is_dir($path)) {
            echo ' (dir)';
            echo '<br>';
            list_dir_contents($path, $indent + 1);
        } else {
            echo ' (' . filesize($path) . ' bytes)';
            echo '<br>';
        }
    }
}

list_dir_contents(plugin_dir_path(__FILE__));

echo '<h2>WordPress Environment</h2>';
echo '<p>WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . '</p>';
echo '<p>PHP Version: ' . phpversion() . '</p>';
echo '<p>WordPress Version: ' . get_bloginfo('version') . '</p>';
echo '<p>Theme: ' . wp_get_theme()->get('Name') . '</p>';
echo '<p>Active Plugins: ' . count(get_option('active_plugins')) . '</p>';