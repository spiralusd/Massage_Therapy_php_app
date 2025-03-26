<?php
/**
 * Massage Booking Shortcodes - Fixed Version
 *
 * Provides shortcodes to embed the booking form and related elements.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all shortcodes
 */
function massage_booking_register_shortcodes() {
    add_shortcode('massage_booking_form', 'massage_booking_form_shortcode');
    add_shortcode('massage_business_hours', 'massage_booking_business_hours_shortcode');
    add_shortcode('massage_services', 'massage_booking_services_shortcode');
}
add_action('init', 'massage_booking_register_shortcodes');

/**
 * Main booking form shortcode
 * Usage: [massage_booking_form]
 */
function massage_booking_form_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'title' => '',
        'show_services' => 'true',
        'show_privacy' => 'true',
    ), $atts, 'massage_booking_form');
    
    // Check if the function exists
    if (!function_exists('massage_booking_display_form')) {
        // Include the booking form file
        require_once(MASSAGE_BOOKING_PLUGIN_DIR . 'public/booking-form.php');
    }
    
    // Check again if the function exists after including the file
    if (!function_exists('massage_booking_display_form')) {
        return '<div class="error">Error: Booking form function not available even after including file.</div>';
    }
    
    // Return the form
    return massage_booking_display_form();
}

/**
 * Business hours shortcode
 * Usage: [massage_business_hours]
 */
function massage_booking_business_hours_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'title' => 'Business Hours',
        'show_closed_days' => 'true',
    ), $atts, 'massage_business_hours');
    
    // Get settings
    $settings = new Massage_Booking_Settings();
    $schedule = $settings->get_setting('schedule', array());
    $working_days = $settings->get_setting('working_days', array('1', '2', '3', '4', '5'));
    
    // Start output buffering
    ob_start();
    
    // Container start
    echo '<div class="massage-business-hours">';
    
    // Title
    if (!empty($atts['title'])) {
        echo '<h3>' . esc_html($atts['title']) . '</h3>';
    }
    
    // Table start
    echo '<table class="business-hours-table">';
    
    // Days of the week
    $days = array(
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday'
    );
    
    // Day number mapping
    $day_numbers = array(
        'monday' => '1',
        'tuesday' => '2',
        'wednesday' => '3',
        'thursday' => '4',
        'friday' => '5',
        'saturday' => '6',
        'sunday' => '0'
    );
    
    // Loop through days
    foreach ($days as $day_key => $day_name) {
        $is_working_day = in_array($day_numbers[$day_key], $working_days);
        
        // Skip closed days if not showing them
        if ($atts['show_closed_days'] !== 'true' && !$is_working_day) {
            continue;
        }
        
        echo '<tr>';
        echo '<th>' . esc_html($day_name) . '</th>';
        
        if ($is_working_day && isset($schedule[$day_key]) && !empty($schedule[$day_key])) {
            echo '<td>';
            
            // Loop through time blocks for this day
            foreach ($schedule[$day_key] as $i => $block) {
                if ($i > 0) {
                    echo '<br>';
                }
                
                // Format time for display
                $from_time = date('g:i A', strtotime($block['from']));
                $to_time = date('g:i A', strtotime($block['to']));
                
                echo esc_html($from_time . ' - ' . $to_time);
            }
            
            echo '</td>';
        } else {
            echo '<td>Closed</td>';
        }
        
        echo '</tr>';
    }
    
    // Table end
    echo '</table>';
    
    // Container end
    echo '</div>';
    
    // Add some basic styling
    echo '<style>
        .massage-business-hours {
            margin: 20px 0;
        }
        .business-hours-table {
            width: 100%;
            border-collapse: collapse;
        }
        .business-hours-table th, .business-hours-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .business-hours-table th {
            text-align: left;
            width: 40%;
        }
    </style>';
    
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Services shortcode
 * Usage: [massage_services]
 */
function massage_booking_services_shortcode($atts) {
    // Parse attributes
    $atts = shortcode_atts(array(
        'title' => 'Our Services',
        'show_prices' => 'true',
        'button_text' => 'Book Now',
        'button_link' => '',
    ), $atts, 'massage_services');
    
    // Get settings
    $settings = new Massage_Booking_Settings();
    $durations = $settings->get_setting('durations', array('60', '90', '120'));
    $prices = $settings->get_setting('prices', array(
        '60' => 95,
        '90' => 125,
        '120' => 165
    ));
    
    // Service descriptions (can be enhanced with custom settings)
    $descriptions = array(
        '60' => 'Our standard session focuses on key areas of tension and is perfect for targeted relief.',
        '90' => 'Extended session allows for deeper work on problem areas while still addressing the full body.',
        '120' => 'Our most comprehensive session provides thorough treatment of the entire body with extra focus on areas of concern.'
    );
    
    // Start output buffering
    ob_start();
    
    // Container start
    echo '<div class="massage-services">';
    
    // Title
    if (!empty($atts['title'])) {
        echo '<h3>' . esc_html($atts['title']) . '</h3>';
    }
    
    // Services grid
    echo '<div class="services-grid">';
    
    // Loop through durations
    foreach ($durations as $duration) {
        $price = isset($prices[$duration]) ? $prices[$duration] : '';
        $description = isset($descriptions[$duration]) ? $descriptions[$duration] : '';
        
        echo '<div class="service-item">';
        echo '<h4>' . esc_html($duration) . ' Minute Massage';
        
        // Show price if enabled
        if ($atts['show_prices'] === 'true' && !empty($price)) {
            echo ' <span class="service-price">$' . esc_html($price) . '</span>';
        }
        
        echo '</h4>';
        
        // Description
        if (!empty($description)) {
            echo '<p>' . esc_html($description) . '</p>';
        }
        
        // Button if link is provided
        if (!empty($atts['button_link'])) {
            echo '<a href="' . esc_url($atts['button_link']) . '" class="service-button">' . 
                esc_html($atts['button_text']) . '</a>';
        }
        
        echo '</div>';
    }
    
    // Grid end
    echo '</div>';
    
    // Container end
    echo '</div>';
    
    // Add some basic styling
    echo '<style>
        .massage-services {
            margin: 20px 0;
        }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .service-item {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .service-price {
            font-weight: bold;
            color: #e74c3c;
        }
        .service-button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .service-button:hover {
            background-color: #2980b9;
        }
    </style>';
    
    // Return the buffered content
    return ob_get_clean();
}