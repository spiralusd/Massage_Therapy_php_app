/**
 * Enhanced Massage Booking API Connector with Comprehensive Diagnostics
 * Version: 2.0.0
 */
(function($) {
    'use strict';

    // Logging function for debugging
    function debugLog(message, data = null) {
        if (window.massageBookingDebug || window.WP_DEBUG) {
            console.group('Massage Booking API Connector');
            console.log(message);
            if (data) {
                console.log('Additional Data:', data);
            }
            console.groupEnd();
        }
    }

    // Comprehensive form validation
    function validateForm($form) {
        debugLog('Starting form validation');
        let isValid = true;
        const errors = [];

        // Required fields
        const requiredFields = [
            'fullName', 'email', 'phone', 
            'appointmentDate', 'duration'
        ];

        requiredFields.forEach(fieldName => {
            const $field = $form.find(`#${fieldName}`);
            if (!$field.val().trim()) {
                errors.push(`${fieldName} is required`);
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });

        // Time slot validation
        const $selectedTimeSlot = $form.find('.time-slot.selected');
        if ($selectedTimeSlot.length === 0) {
            errors.push('Please select a time slot');
            $form.find('#timeSlots').addClass('error');
            isValid = false;
        }

        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const $emailField = $form.find('#email');
        if ($emailField.val() && !emailRegex.test($emailField.val())) {
            errors.push('Invalid email format');
            $emailField.addClass('error');
            isValid = false;
        }

        // Display errors if any
        if (!isValid) {
            displayFormErrors($form, errors);
        }

        debugLog('Form Validation Result', { 
            isValid: isValid, 
            errors: errors 
        });

        return isValid;
    }

    // Error display function
    function displayFormErrors($form, errors) {
        // Remove existing error messages
        $form.find('.form-error').remove();

        const errorHtml = `
            <div class="form-error alert alert-danger">
                <strong>Please correct the following errors:</strong>
                <ul>
                    ${errors.map(error => `<li>${error}</li>`).join('')}
                </ul>
            </div>
        `;

        $form.prepend(errorHtml);
        
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $form.offset().top - 100
        }, 500);
    }

    // Form submission handler
    function handleFormSubmission(event) {
        event.preventDefault();
        const $form = $(this);

        debugLog('Form Submission Triggered');

        // Validate form first
        if (!validateForm($form)) {
            return false;
        }

        // Show loading state
        const $submitButton = $form.find('button[type="submit"]');
        $submitButton.prop('disabled', true).addClass('loading');

        // Collect form data
        const formData = {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $form.find('#fullName').val(),
            email: $form.find('#email').val(),
            phone: $form.find('#phone').val(),
            appointmentDate: $form.find('#appointmentDate').val(),
            startTime: $form.find('.time-slot.selected').data('time'),
            endTime: $form.find('.time-slot.selected').data('end-time'),
            duration: $form.find('input[name="duration"]:checked').val(),
            focusAreas: $form.find('input[name="focus"]:checked').map(function() {
                return $(this).val();
            }).get(),
            pressurePreference: $form.find('#pressurePreference').val(),
            specialRequests: $form.find('#specialRequests').val()
        };

        debugLog('Collected Form Data', formData);

        // Submit via AJAX
        $.ajax({
            url: massageBookingAPI.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000, // 30-second timeout
            success: function(response) {
                debugLog('Submission Success', response);

                if (response.success) {
                    // Redirect to thank you page
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert('Appointment booked successfully!');
                    }
                } else {
                    // Display server-side validation errors
                    alert(response.data.message || 'An error occurred. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                debugLog('Submission Error', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });

                alert('Network error. Please try again later.');
            },
            complete: function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false).removeClass('loading');
            }
        });

        return false;
    }

    // Initialize form submission
    function initFormSubmission() {
        const $form = $('#appointmentForm');
        
        if ($form.length) {
            debugLog('Initializing Form Submission Handler');
            $form.on('submit', handleFormSubmission);
        } else {
            debugLog('Appointment Form Not Found');
        }
    }

    // Document ready initialization
    $(document).ready(function() {
        initFormSubmission();
    });

})(jQuery);