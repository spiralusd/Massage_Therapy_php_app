<?php
/**
 * Template Name: Massage Booking Form
 *
 * A custom page template that displays the massage booking form without header and footer.
 * Merged version with embedded form content.
 */

// Exit if accessed directly or from admin
if (!defined('WPINC') || is_admin()) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #98c1d9;
            --accent-color: #ee6c4d;
            --light-color: #f8f9fa;
            --dark-color: #293241;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .booking-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .booking-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .booking-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        .booking-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* Optimized Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .radio-group, .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .radio-option, .checkbox-option {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1 1 200px;
        }
        
        .radio-option:hover, .checkbox-option:hover {
            border-color: var(--primary-color);
        }
        
        .radio-option.selected, .checkbox-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(74, 111, 165, 0.1);
        }
        
        .radio-option input, .checkbox-option input {
            margin-right: 10px;
        }
        
        .price {
            font-weight: bold;
            color: var(--accent-color);
            margin-left: 5px;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: block;
            margin: 30px auto 0;
        }
        
        button:hover {
            background-color: #3a5a84;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .time-slot {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            border-color: var(--primary-color);
        }
        
        .time-slot.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .time-slot.unavailable {
            background-color: #f1f1f1;
            color: #999;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .privacy-notice {
            margin-top: 30px;
            font-size: 14px;
            color: #666;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
        }
        
        .summary {
            margin-top: 20px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            display: none;
        }
        
        .summary.visible {
            display: block;
        }
        
        @media (max-width: 768px) {
            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    
    <div class="booking-container">
        <div class="booking-header">
            <h1><?php echo get_option('massage_booking_business_name', 'Massage Therapy Appointment Booking'); ?></h1>
        </div>
        
        <form id="appointmentForm">
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
                    <div class="radio-option" data-value="60" data-price="95">
                        <input type="radio" name="duration" id="duration60" value="60" checked>
                        <label for="duration60">60 Minutes <span class="price">$95</span></label>
                    </div>
                    <div class="radio-option" data-value="90" data-price="125">
                        <input type="radio" name="duration" id="duration90" value="90">
                        <label for="duration90">90 Minutes <span class="price">$125</span></label>
                    </div>
                    <div class="radio-option" data-value="120" data-price="165">
                        <input type="radio" name="duration" id="duration120" value="120">
                        <label for="duration120">120 Minutes <span class="price">$165</span></label>
                    </div>
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
                <input type="date" id="appointmentDate" name="appointmentDate" required data-available-days="1,2,3,4,5">
                <small>Available days: Monday-Friday</small>
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
                <p><strong>Privacy Notice:</strong> This form is HIPAA compliant. Your personal and health information is protected and will only be used for appointment scheduling and to provide appropriate care. A 15-minute break is automatically scheduled between appointments for your privacy and comfort. By submitting this form, you consent to the collection and processing of your information for these purposes.</p>
            </div>
            
            <button type="submit">Book Appointment</button>
        </form>
        
        <div class="booking-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo get_option('massage_booking_business_name', 'Massage Therapy Practice'); ?>. All rights reserved.</p>
            <p>This booking system is HIPAA compliant. Your information is secure and encrypted.</p>
        </div>
    </div>
    
    <?php
    // Enqueue necessary scripts and styles
    wp_enqueue_style(
        'massage-booking-form-style',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/css/booking-form.css',
        array(),
        MASSAGE_BOOKING_VERSION
    );
    
    // Enqueue optimized form script
    wp_enqueue_script(
        'massage-booking-form-script',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/booking-form.min.js',
        array('jquery'),
        MASSAGE_BOOKING_VERSION,
        true
    );
    
    // Enqueue optimized API connector
    wp_enqueue_script(
        'massage-booking-api-connector',
        MASSAGE_BOOKING_PLUGIN_URL . 'public/js/api-connector.min.js',
        array('jquery', 'massage-booking-form-script'),
        MASSAGE_BOOKING_VERSION,
        true
    );
    
    // Pass WordPress data to JavaScript
    wp_localize_script('massage-booking-api-connector', 'massageBookingAPI', array(
        'restUrl' => esc_url_raw(rest_url('massage-booking/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'ajaxUrl' => admin_url('admin-ajax.php')
    ));
    ?>
    
    <?php wp_footer(); ?>
</body>
</html>