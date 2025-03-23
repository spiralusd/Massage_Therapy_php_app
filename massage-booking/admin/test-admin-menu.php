<?php
// Test Admin Menu
add_action('admin_menu', 'test_massage_booking_menu');

function test_massage_booking_menu() {
    add_menu_page(
        'Test Massage Booking',
        'Test Massage Booking',
        'manage_options',
        'test-massage-booking',
        'test_massage_booking_callback',
        'dashicons-calendar-alt',
        30
    );
}

function test_massage_booking_callback() {
    echo '<div class="wrap"><h1>Test Massage Booking</h1><p>This is a test page.</p></div>';
}