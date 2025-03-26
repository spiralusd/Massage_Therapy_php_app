/**
 * Fixed jQuery Form Handler for Massage Booking System
 * 
 * This patch addresses the 400 error issues by:
 * 1. Improving form data validation
 * 2. Adding proper error handling for API responses
 * 3. Fixing AJAX submission parameters
 * 4. Ensuring compatibility across browsers
 * 
 * Save as: public/js/jquery-form-handler-fix.js
 * Enqueue after jquery-form-handler.js in massage-booking.php
 */

(function($) {
    'use strict';
    
    // Track whether handler has been initialized to prevent duplicate initialization
    let isHandlerInitialized = false;
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log("Fixed Form Handler: Document ready");
        initFormHandler();
    });
    
    // Function to initialize form handler
    function initFormHandler() {
        // Prevent multiple initializations
        if (isHandlerInitialized) {
            console.log("Fixed Form Handler: Already initialized");
            return;
        }
        
        const $form = $('#appointmentForm');
        
        // If form doesn't exist, exit
        if (!$form.length) {
            console.error("Fixed Form Handler: Form not found");
            return;
        }
        
        console.log("Fixed Form Handler: Initializing");
        
        // Check if API configuration is available
        if (typeof massageBookingAPI === 'undefined') {
            console.error("Fixed Form Handler: massageBookingAPI not available");
            createFallbackAPI();
        }
        
        // Remove any existing submit handlers to prevent duplicates
        $form.off('submit');
        
        // Add our improved submit handler
        $form.on('submit', function(e) {
            // Prevent default form submission
            e.preventDefault();
            console.log("Fixed Form Handler: Form submission intercepted");
            
            // Validate form
            if (!validateForm()) {
                console.warn("Fixed Form Handler: Form validation failed");
                return false;
            }
            
            // Show loading overlay
            const $loadingOverlay = createLoadingOverlay();
            $('body').append($loadingOverlay);
            
            // Collect form data with improved collection
            const formData = collectFormData();
            
            // Log what we're about to submit
            console.log("Fixed Form Handler: Submitting form data:", formData);
            
            // Submit the form with better error handling
            $.ajax({
                url: massageBookingAPI.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                timeout: 30000, // 30-second timeout
                success: function(response) {
                    console.log("Fixed Form Handler: Server response received", response);
                    
                    if (response.success) {
                        handleSuccess(response.data);
                    } else {
                        handleError({
                            message: response.data?.message || 'Unknown error occurred',
                            code: response.data?.code || 'unknown_error',
                            data: response.data
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Fixed Form Handler: AJAX error", {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    // Try to parse the error response
                    let errorMessage = 'Server connection error. Please try again.';
                    
                    try {
                        if (xhr.responseText) {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            } else if (response.message) {
                                errorMessage = response.message;
                            }
                        }
                    } catch (e) {
                        // Handle non-JSON responses
                        if (xhr.status === 0) {
                            errorMessage = 'Network error. Please check your connection.';
                        } else if (xhr.status === 400) {
                            errorMessage = 'Invalid request. Please check all fields and try again.';
                        } else if (xhr.status === 403) {
                            errorMessage = 'Permission denied. Please refresh the page and try again.';
                        } else if (xhr.status >= 500) {
                            errorMessage = 'Server error. Please try again later.';
                        }
                    }
                    
                    handleError({ message: errorMessage });
                },
                complete: function() {
                    // Remove loading overlay
                    $loadingOverlay.remove();
                }
            });
        });
        
        // Mark as initialized
        isHandlerInitialized = true;
        console.log("Fixed Form Handler: Initialization complete");
    }
    
    /**
     * Create a fallback API configuration if the original is missing
     */
    function createFallbackAPI() {
        console.warn("Fixed Form Handler: Creating fallback API configuration");
        
        window.massageBookingAPI = {
            restUrl: '/wp-json/massage-booking/v1/',
            nonce: '',
            ajaxUrl: '/wp-admin/admin-ajax.php',
            siteUrl: window.location.origin,
            isFallback: true
        };
    }
    
    /**
     * Create a loading overlay with improved styling
     */
    function createLoadingOverlay() {
        return $('<div>', {
            id: 'loadingOverlay',
            css: {
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                backgroundColor: 'rgba(255,255,255,0.8)',
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                zIndex: 9999
            }
        }).html(`
            <div style="text-align: center;">
                <div style="
                    border: 4px solid #f3f3f3; 
                    border-top: 4px solid #4a6fa5; 
                    border-radius: 50%; 
                    width: 40px; 
                    height: 40px; 
                    margin: 0 auto;
                    animation: spin 2s linear infinite;
                "></div>
                <p style="margin-top: 10px;">Processing your appointment...</p>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `);
    }
    
    /**
     * Form validation with improved error handling
     */
    function validateForm() {
        const errors = [];
        
        // Required fields with custom validation
        const requiredFields = [
            { id: 'fullName', label: 'Full Name', validator: val => val.trim() !== '' },
            { id: 'email', label: 'Email Address', validator: isValidEmail },
            { id: 'phone', label: 'Phone Number', validator: isValidPhone },
            { id: 'appointmentDate', label: 'Appointment Date', validator: val => val.trim() !== '' }
        ];
        
        // Check required fields
        requiredFields.forEach(field => {
            const $el = $(`#${field.id}`);
            const value = $el.val() || '';
            
            if (!field.validator(value)) {
                $el.addClass('error').attr('aria-invalid', 'true');
                errors.push(field.label);
            } else {
                $el.removeClass('error').removeAttr('aria-invalid');
            }
        });
        
        // Check if a service duration is selected
        if (!$('input[name="duration"]:checked').length) {
            $('#serviceDuration').addClass('error');
            errors.push('Service Duration');
        } else {
            $('#serviceDuration').removeClass('error');
        }
        
        // Check if a time slot is selected
        if (!$('.time-slot.selected').length) {
            $('#timeSlots').addClass('error');
            errors.push('Time Slot');
        } else {
            $('#timeSlots').removeClass('error');
        }
        
        // If there are errors, show them and prevent submission
        if (errors.length > 0) {
            showFormError('Please complete the following: ' + errors.join(', '));
            return false;
        }
        
        return true;
    }
    
    /**
     * Email validation using regex
     */
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    /**
     * Phone validation with improved regex
     */
    function isValidPhone(phone) {
        // Accept a wider range of phone formats
        return /^[\d\s()+\-\.]{7,20}$/.test(phone);
    }
    
    /**
     * Display form error message with improved styling
     */
    function showFormError(message) {
        // Remove any existing error messages
        $('.form-error-message').remove();
        
        // Create error element
        const $errorElement = $('<div>', {
            class: 'form-error-message',
            css: {
                background: '#f8d7da',
                color: '#721c24',
                padding: '15px',
                borderRadius: '4px',
                marginBottom: '20px',
                border: '1px solid #f5c6cb'
            }
        }).html(`<p><strong>Error:</strong> ${message}</p>`);
        
        // Add to the top of the form
        $('#appointmentForm').prepend($errorElement);
        
        // Scroll to the error message
        $('html, body').animate({
            scrollTop: $errorElement.offset().top - 100
        }, 500);
        
        // Automatically remove after 8 seconds
        setTimeout(() => {
            $errorElement.fadeOut(500, function() {
                $(this).remove();
            });
        }, 8000);
    }
    
    /**
     * Collect form data with improved handling of focus areas
     */
    function collectFormData() {
        const $timeSlot = $('.time-slot.selected');
        const $duration = $('input[name="duration"]:checked');
        
        // Collect focus areas as an array
        const focusAreas = [];
        $('input[name="focus"]:checked').each(function() {
            focusAreas.push($(this).val());
        });
        
        // Check for valid data
        if (!$timeSlot.length || !$duration.length) {
            showFormError('Please select both a time slot and service duration.');
            return {};
        }
        
        // Format data for submission
        return {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            appointmentDate: $('#appointmentDate').val(),
            startTime: $timeSlot.attr('data-time'),
            endTime: $timeSlot.attr('data-end-time'),
            duration: $duration.val(),
            focusAreas: JSON.stringify(focusAreas),
            pressurePreference: $('#pressurePreference').val(),
            specialRequests: $('#specialRequests').val()
        };
    }
    
    /**
     * Handle successful form submission
     */
    function handleSuccess(data) {
        console.log("Fixed Form Handler: Form submitted successfully", data);
        
        // Show success message
        const message = data.message || 'Your appointment has been booked successfully! A confirmation email will be sent shortly.';
        alert(message);
        
        // Reset the form
        resetForm();
    }
    
    /**
     * Handle form submission error
     */
    function handleError(error) {
        console.error("Fixed Form Handler: Form submission error", error);
        
        // Show error message
        const message = error.message || 'An error occurred while booking your appointment. Please try again.';
        showFormError(message);
    }
    
    /**
     * Reset the form after successful submission
     */
    function resetForm() {
        // Reset form fields
        document.getElementById('appointmentForm').reset();
        
        // Clear selected classes
        $('.radio-option, .checkbox-option, .time-slot').removeClass('selected');
        
        // Hide the summary
        $('#bookingSummary').removeClass('visible');
        
        // Reset time slots
        $('#timeSlots').html('<p>Please select a date to see available time slots.</p>');
        
        // Reset errors
        $('.error').removeClass('error').removeAttr('aria-invalid');
        $('.form-error-message').remove();
        
        // Reselect default option (60 min)
        $('#duration60').prop('checked', true)
            .closest('.radio-option')
            .addClass('selected');
        
        // Clear session storage
        try {
            sessionStorage.removeItem('massageBookingFormData');
        } catch (e) {
            console.warn('Fixed Form Handler: Failed to clear session storage', e);
        }
        
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('#appointmentForm').offset().top - 100
        }, 500);
    }
    
    // Expose the handler in case it needs to be manually triggered
    window.initMassageBookingFormHandler = initFormHandler;
    
})(jQuery);