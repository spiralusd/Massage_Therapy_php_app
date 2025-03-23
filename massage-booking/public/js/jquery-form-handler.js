/**
 * Unified Form Submission Handler for Massage Booking System
 * 
 * Combines functionality from form-submit-fix.js and jquery-form-handler.js
 * with enhanced debugging and error handling capabilities
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

    // Debug configuration
    const DEBUG = true;

    /**
     * Log debug messages with optional data
     * 
     * @param {string} message Debug message
     * @param {*} [data=null] Optional data to log
     */
    function logDebug(message, data = null) {
        if (!DEBUG) return;
        
        if (data !== null) {
            console.log(`FORM DEBUG: ${message}`, data);
        } else {
            console.log(`FORM DEBUG: ${message}`);
        }
        
        // Optional: Send debug log to server if massageBookingAPI is available
        try {
            if (massageBookingAPI && massageBookingAPI.ajaxUrl) {
                fetch(massageBookingAPI.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'massage_booking_debug_form_submission',
                        message: message,
                        data: JSON.stringify(data),
                        nonce: massageBookingAPI.nonce
                    })
                }).catch(() => {}); // Ignore errors from this call
            }
        } catch (e) {
            // Ignore any errors from debug logging
        }
    }

    /**
     * Create a loading overlay with Tailwind-like styling
     * 
     * @returns {jQuery} Loading overlay element
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
                <div class="loading-spinner" style="
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
        `);
    }

    /**
     * Validate email format
     * 
     * @param {string} email Email address to validate
     * @returns {boolean} Whether email is valid
     */
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    /**
     * Validate phone format
     * 
     * @param {string} phone Phone number to validate
     * @returns {boolean} Whether phone is valid
     */
    function isValidPhone(phone) {
        return /^[\d\s()+\-\.]{7,20}$/.test(phone);
    }

    /**
     * Display form error message
     * 
     * @param {string} message Error message to display
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
     * Enhanced form validation
     * 
     * @returns {boolean} Whether form is valid
     */
    function validateForm() {
        const errors = [];
        
        // Required fields
        const requiredFields = [
            {id: 'fullName', label: 'Full Name', validator: (val) => val.trim() !== ''},
            {id: 'email', label: 'Email Address', validator: isValidEmail},
            {id: 'phone', label: 'Phone Number', validator: isValidPhone},
            {id: 'appointmentDate', label: 'Appointment Date', validator: (val) => val.trim() !== ''}
        ];
        
        // Check all required fields
        requiredFields.forEach(field => {
            const $el = $(`#${field.id}`);
            const value = $el.val();
            
            if (!field.validator(value)) {
                $el.addClass('error').attr('aria-invalid', 'true');
                errors.push(field.label);
            } else {
                $el.removeClass('error').removeAttr('aria-invalid');
            }
        });
        
        // Check service duration
        if (!$('input[name="duration"]:checked').length) {
            $('#serviceDuration').addClass('error');
            errors.push('Service Duration');
        } else {
            $('#serviceDuration').removeClass('error');
        }
        
        // Check time slot
        if (!$('.time-slot.selected').length) {
            $('#timeSlots').addClass('error');
            errors.push('Time Slot');
        } else {
            $('#timeSlots').removeClass('error');
        }
        
        // Show error message if validation failed
        if (errors.length > 0) {
            const errorMessage = 'Please complete the following: ' + errors.join(', ');
            showFormError(errorMessage);
            logDebug('Form validation failed', errors);
            return false;
        }
        
        return true;
    }

    /**
     * Collect form data for submission
     * 
     * @returns {Object} Collected form data
     */
    function collectFormData() {
        const selectedTimeSlot = $('.time-slot.selected');
        const selectedDuration = $('input[name="duration"]:checked');
        
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
     * Submit form via AJAX
     * 
     * @param {Object} formData Form data to submit
     * @returns {Promise} Promise resolving with server response
     */
    function submitForm(formData) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: massageBookingAPI.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                timeout: 30000, // 30-second timeout
                success: function(response) {
                    logDebug('Form submission response', response);
                    
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
                    logDebug('Form submission error', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    let errorMessage = 'Server connection error. Please try again.';
                    let errorData = {};
                    
                    // Try to parse error response
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
     * Handle successful form submission
     * 
     * @param {Object} data Submission response data
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
     * 
     * @param {Object} error Error details
     */
    function handleError(error) {
        logDebug('Form submission error', error);
        
        // Show error message
        const message = error.message || 'An error occurred while booking your appointment. Please try again.';
        showFormError(message);
    }

    /**
     * Reset the form after successful submission
     */
    function resetForm() {
        // Reset form fields
        const form = document.getElementById('appointmentForm');
        form.reset();
        
        // Clear selected classes
        $('.radio-option, .checkbox-option, .time-slot').removeClass('selected');
        
        // Hide the summary
        $('#bookingSummary').removeClass('visible');
        
        // Reset time slots
        $('#timeSlots').html('<p>Please select a date to see available time slots.</p>');
        
        // Clear session storage
        sessionStorage.removeItem('massageBookingFormData');
        
        // Reselect default option (60 min)
        $('#duration60').prop('checked', true)
            .closest('.radio-option')
            .addClass('selected');
        
        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('#appointmentForm').offset().top - 100
        }, 500);
    }

    /**
     * Initialize form submission handler
     */
    function initFormHandler() {
        // Prevent multiple initializations
        if ($('#appointmentForm').data('handler-initialized')) {
            return;
        }
        
        logDebug('Initializing unified form submission handler');
        
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
                    // Remove loading overlay
                    $(loadingOverlay).remove();
                });
        });
        
        // Mark as initialized
        $('#appointmentForm').data('handler-initialized', true);
        
        logDebug('Unified form handler initialization complete');
    }

    // Initialize when document is ready
    $(document).ready(initFormHandler);

    // Add some debug features in development mode
    function addDebugFeatures() {
        if (!DEBUG) return;
        
        // Create a debug button for testing
        const debugButton = $('<button>', {
            type: 'button',
            id: 'debugFormButton',
            text: 'Debug Form',
            css: {
                marginTop: '20px',
                background: '#6c757d',
                color: 'white',
                border: 'none',
                padding: '8px 15px',
                borderRadius: '4px',
                cursor: 'pointer',
                display: 'none'
            },
            click: function() {
                // Log current form state
                const formState = {
                    formData: collectFormData(),
                    selectedTimeSlot: $('.time-slot.selected').length ? {
                        time: $('.time-slot.selected').attr('data-time'),
                        endTime: $('.time-slot.selected').attr('data-end-time'),
                        text: $('.time-slot.selected').text()
                    } : 'None',
                    sessionStorage: JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}')
                };
                
                logDebug('Debug button clicked', formState);
                alert('Debug information has been logged to the console and server.');
            }
        });
        
        // Insert debug button after form
        const container = $('#appointmentForm').closest('.massage-booking-container, .container');
        if (container.length) {
            container.append(debugButton);
        } else {
            $('#appointmentForm').after(debugButton);
        }
        
        // Show debug button with Ctrl+Shift+D
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                $('#debugFormButton').toggle();
                e.preventDefault();
            }
        });
    }

    // Add debug features in development mode
    addDebugFeatures();

})(jQuery);