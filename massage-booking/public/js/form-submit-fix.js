/**
 * Integrated Form Submission Handler
 * 
 * This script fixes the double form submission prompt issue by properly integrating 
 * the jQuery form handler with the API connector. Place this in the public/js directory
 * and include it in your page-booking.php template after the other scripts.
 */
(function($) {
    // Make sure jQuery is loaded
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded - form handler cannot initialize');
        return;
    }

    // Make sure necessary API data is loaded
    if (typeof massageBookingAPI === 'undefined') {
        console.error('massageBookingAPI data is not available - form handler cannot initialize');
        return;
    }

    // Debug flag - set to true for console logging
    const DEBUG = false;

    /**
     * Log debug messages if debug is enabled
     */
    function logDebug(message, data = null) {
        if (!DEBUG) return;
        
        if (data !== null) {
            console.log(`FORM DEBUG: ${message}`, data);
        } else {
            console.log(`FORM DEBUG: ${message}`);
        }
    }

    /**
     * Create a loading overlay with proper styling
     */
    function createLoadingOverlay() {
        const overlay = $('<div>', {
            id: 'loadingOverlay',
            css: {
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                backgroundColor: 'rgba(255,255,255,0.8)',
                zIndex: 9999,
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center'
            }
        });
        
        overlay.html(`
            <div style="text-align: center;">
                <div class="loading-spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #4a6fa5; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto; animation: spin 2s linear infinite;"></div>
                <p style="margin-top: 10px;">Processing your appointment...</p>
            </div>
        `);
        
        if (!document.getElementById('spin-animation')) {
            $('<style>', {
                id: 'spin-animation',
                text: '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
            }).appendTo('head');
        }
        
        return overlay;
    }

    /**
     * Display form error message
     */
    function showFormError(message) {
        // Remove any existing error messages
        $('.form-error-message').remove();
        
        // Create error element
        const errorElement = $('<div>', {
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
        $('#appointmentForm').prepend(errorElement);
        
        // Scroll to the error message
        $('html, body').animate({
            scrollTop: errorElement.offset().top - 100
        }, 500);
        
        // Automatically remove after 8 seconds
        setTimeout(() => {
            errorElement.fadeOut(500, function() {
                $(this).remove();
            });
        }, 8000);
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    /**
     * Validate phone format - accept various formats
     */
    function isValidPhone(phone) {
        return /^[\d\s()+\-\.]{7,20}$/.test(phone);
    }

    /**
     * Enhanced form validation
     */
    function validateForm() {
        const errors = [];
        
        // Required fields
        const requiredFields = [
            {id: 'fullName', label: 'Full Name'},
            {id: 'email', label: 'Email Address'},
            {id: 'phone', label: 'Phone Number'},
            {id: 'appointmentDate', label: 'Appointment Date'}
        ];
        
        // Check all required fields
        requiredFields.forEach(field => {
            const value = $(`#${field.id}`).val();
            if (!value || value.trim() === '') {
                $(`#${field.id}`).css('border-color', 'red').attr('aria-invalid', 'true');
                errors.push(field.label);
            } else {
                $(`#${field.id}`).css('border-color', '').removeAttr('aria-invalid');
            }
        });
        
        // Check email format
        const email = $('#email').val();
        if (email && !isValidEmail(email)) {
            $('#email').css('border-color', 'red').attr('aria-invalid', 'true');
            errors.push('Valid Email Address');
        }
        
        // Check phone format
        const phone = $('#phone').val();
        if (phone && !isValidPhone(phone)) {
            $('#phone').css('border-color', 'red').attr('aria-invalid', 'true');
            errors.push('Valid Phone Number');
        }
        
        // Check service duration
        if (!$('input[name="duration"]:checked').length) {
            $('#serviceDuration').css('border-color', 'red');
            errors.push('Service Duration');
        } else {
            $('#serviceDuration').css('border-color', '');
        }
        
        // Check time slot
        if (!$('.time-slot.selected').length) {
            $('#timeSlots').css('border-color', 'red');
            errors.push('Time Slot');
        } else {
            $('#timeSlots').css('border-color', '');
        }
        
        // Show error message if validation failed
        if (errors.length > 0) {
            const errorMessage = 'Please complete the following: ' + errors.join(', ');
            showFormError(errorMessage);
            logDebug('Validation failed', errors);
            return false;
        }
        
        return true;
    }

    /**
     * Collect all form data
     */
    function collectFormData() {
        // Get selected values
        const selectedTimeSlot = $('.time-slot.selected');
        const selectedDuration = $('input[name="duration"]:checked');
        
        // Create an object to hold all form data
        const formData = {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            appointmentDate: $('#appointmentDate').val(),
            duration: selectedDuration.val(),
            startTime: selectedTimeSlot.attr('data-time'),
            endTime: selectedTimeSlot.attr('data-end-time'),
            pressurePreference: $('#pressurePreference').val(),
            specialRequests: $('#specialRequests').val()
        };
        
        // Get focus areas
        const focusAreas = [];
        $('input[name="focus"]:checked').each(function() {
            focusAreas.push($(this).val());
        });
        formData.focusAreas = JSON.stringify(focusAreas);
        
        return formData;
    }

    /**
     * Submit the form via AJAX with better error handling
     */
    function submitForm(formData) {
        return new Promise((resolve, reject) => {
            // Store raw formData for debugging
            const formDataDebug = {...formData};
            delete formDataDebug.nonce; // Don't log sensitive data
            logDebug('Submitting form data', formDataDebug);
            
            // Use jQuery AJAX for maximum compatibility
            $.ajax({
                url: massageBookingAPI.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                timeout: 30000, // 30-second timeout
                success: function(response) {
                    logDebug('AJAX success response', response);
                    
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject({
                            message: response.data && response.data.message 
                                ? response.data.message 
                                : 'Unknown error occurred',
                            code: response.data && response.data.code 
                                ? response.data.code 
                                : 'unknown_error',
                            data: response.data
                        });
                    }
                },
                error: function(xhr, status, error) {
                    logDebug('AJAX error', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    let errorMessage = 'Server connection error. Please try again.';
                    let errorData = {};
                    
                    // Try to extract more info from response
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse.data && jsonResponse.data.message) {
                            errorMessage = jsonResponse.data.message;
                        }
                        errorData = jsonResponse;
                    } catch (e) {
                        // Not JSON, use text response
                        errorMessage = xhr.responseText || error || 'Unknown error';
                    }
                    
                    reject({
                        message: errorMessage,
                        code: status,
                        data: errorData,
                        xhr: xhr
                    });
                }
            });
        });
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
        
        // Clear session storage
        sessionStorage.removeItem('massageBookingFormData');
        
        // Reselect default option (60 min)
        $('#duration60').prop('checked', true);
        $('#duration60').closest('.radio-option').addClass('selected');
        
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('#appointmentForm').offset().top - 100
        }, 500);
    }

    /**
     * Handle successful form submission
     */
    function handleSuccess(data) {
        logDebug('Form submitted successfully', data);
        
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
        logDebug('Form submission error', error);
        
        // Show error message
        const message = error.message || 'An error occurred while booking your appointment. Please try again.';
        showFormError(message);
    }

    /**
     * Initialize the form submission handler
     */
    function initFormHandler() {
        // Only initialize once
        if ($('#appointmentForm').data('handler-initialized')) {
            return;
        }
        
        logDebug('Initializing form submission handler');
        
        // Remove any existing form submit handlers
        $('#appointmentForm').off('submit');
        
        // Add our form submit handler
        $('#appointmentForm').on('submit', function(e) {
            // Prevent default submission
            e.preventDefault();
            e.stopPropagation();
            
            logDebug('Form submission intercepted');
            
            // Validate form
            if (!validateForm()) {
                logDebug('Form validation failed');
                return false;
            }
            
            // Show loading overlay
            const loadingOverlay = createLoadingOverlay();
            $('body').append(loadingOverlay);
            
            // Collect form data
            const formData = collectFormData();
            
            // Submit form
            submitForm(formData)
                .then(handleSuccess)
                .catch(handleError)
                .finally(() => {
                    $(loadingOverlay).remove();
                });
        });
        
        // Mark as initialized
        $('#appointmentForm').data('handler-initialized', true);
        
        logDebug('Form handler initialization complete');
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initFormHandler();
    });

})(jQuery);