<?php
/**
 * Massage Booking Form Template
 * 
 * This file serves as a template for displaying the booking form.
 * It can be included directly or used via the shortcode.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Don't output anything during REST API or AJAX requests
if (defined('REST_REQUEST') && REST_REQUEST) {
    return;
}

if (defined('DOING_AJAX') && DOING_AJAX) {
    return;
}

function manually_enqueue_massage_booking_styles() {
    wp_enqueue_style(
        'massage-booking-form-style', 
        '/wp-content/plugins/massage-booking/public/css/booking-form.css', 
        array(), 
        '1.0.2'
    );
}
add_action('wp_enqueue_scripts', 'manually_enqueue_massage_booking_styles');

/**
 * Function to display the booking form
 */
function massage_booking_display_form() {
    // Get settings
    $settings = new Massage_Booking_Settings();
    $business_name = $settings->get_setting('business_name', 'Massage Therapy Practice');
    $working_days = $settings->get_setting('working_days', array('1', '2', '3', '4', '5'));
    $durations = $settings->get_setting('durations', array('60', '90', '120'));
    $prices = $settings->get_setting('prices', array(
        '60' => 95,
        '90' => 125,
        '120' => 165
    ));
    $break_time = $settings->get_setting('break_time', 15);

    // For displaying available days
    $day_names = array(
        '0' => 'Sunday',
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday'
    );

    $available_days = array();
    foreach ($working_days as $day) {
        $available_days[] = $day_names[$day];
    }
    $available_days_text = implode(', ', $available_days);
    ?>

    <div class="massage-booking-container">
        <h2 class="booking-title"><?php echo esc_html($business_name); ?> - Appointment Booking</h2>
        
        <form id="appointmentForm" class="booking-form">
            <!-- Personal Information -->
            <div class="form-group">
                <label for="fullName">Full Name:</label>
                <input type="text" id="fullName" name="fullName" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number:</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            
            <!-- Service Selection -->
            <div class="form-group">
                <label>Select Service Duration:</label>
                <div class="radio-group" id="serviceDuration">
                    <?php foreach ($durations as $duration) : ?>
                        <div class="radio-option" data-value="<?php echo esc_attr($duration); ?>" data-price="<?php echo esc_attr($prices[$duration] ?? 0); ?>">
                            <input type="radio" name="duration" id="duration<?php echo esc_attr($duration); ?>" value="<?php echo esc_attr($duration); ?>" <?php checked($duration, '60'); ?>>
                            <label for="duration<?php echo esc_attr($duration); ?>"><?php echo esc_html($duration); ?> Minutes <span class="price">$<?php echo esc_html($prices[$duration] ?? 0); ?></span></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Additional Services -->
            <div class="form-group">
                <label>Focus Areas (Select all that apply):</label>
                <div class="checkbox-group" id="focusAreas">
                    <div class="checkbox-option" data-value="back">
                        <input type="checkbox" name="focus" id="focusBack" value="Back & Shoulders">
                        <label for="focusBack">Back & Shoulders</label>
                    </div>
                    <div class="checkbox-option" data-value="neck">
                        <input type="checkbox" name="focus" id="focusNeck" value="Neck & Upper Back">
                        <label for="focusNeck">Neck & Upper Back</label>
                    </div>
                    <div class="checkbox-option" data-value="legs">
                        <input type="checkbox" name="focus" id="focusLegs" value="Legs & Feet">
                        <label for="focusLegs">Legs & Feet</label>
                    </div>
                    <div class="checkbox-option" data-value="full">
                        <input type="checkbox" name="focus" id="focusFull" value="Full Body">
                        <label for="focusFull">Full Body</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="pressurePreference">Pressure Preference:</label>
                <select id="pressurePreference" name="pressurePreference">
                    <option value="Light">Light</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="Firm">Firm</option>
                    <option value="Deep Tissue">Deep Tissue</option>
                </select>
            </div>
            
            <!-- Date Selection -->
            <div class="form-group">
                <label for="appointmentDate">Select Date:</label>
                <input type="date" id="appointmentDate" name="appointmentDate" required data-available-days="<?php echo esc_attr(implode(',', $working_days)); ?>">
                <small>Available days: <?php echo esc_html($available_days_text); ?></small>
            </div>
            
            <!-- Time Slots -->
            <div class="form-group">
                <label>Available Time Slots:</label>
                <div class="time-slots" id="timeSlots">
                    <p>Please select a date to see available time slots.</p>
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="form-group">
                <label for="specialRequests">Special Requests or Health Concerns:</label>
                <textarea id="specialRequests" name="specialRequests" rows="4"></textarea>
            </div>
            
            <!-- Summary Section -->
            <div class="summary" id="bookingSummary">
                <h3>Booking Summary</h3>
                <p><strong>Service:</strong> <span id="summaryService"></span></p>
                <p><strong>Focus Areas:</strong> <span id="summaryFocusAreas"></span></p>
                <p><strong>Date & Time:</strong> <span id="summaryDateTime"></span></p>
                <p><strong>Total Price:</strong> <span id="summaryPrice"></span></p>
            </div>
            
            <!-- HIPAA Privacy Notice -->
            <div class="privacy-notice">
                <p><strong>Privacy Notice:</strong> This form is HIPAA compliant. Your personal and health information is protected and will only be used for appointment scheduling and to provide appropriate care. A <?php echo esc_html($break_time); ?>-minute break is automatically scheduled between appointments for your privacy and comfort. By submitting this form, you consent to the collection and processing of your information for these purposes.</p>
            </div>
            
            <button type="submit">Book Appointment</button>
        </form>
    </div>
    <?php
}

// If this file is included outside of a function, display the form
if (!function_exists('did_action') || did_action('wp_head')) {
    massage_booking_display_form();
}