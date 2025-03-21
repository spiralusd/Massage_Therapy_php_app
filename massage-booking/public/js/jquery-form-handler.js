/**
 * Enhanced jQuery Form Handler with Debugging Capabilities
 * This handles the form submission and properly integrates with the calendar
 */
jQuery(document).ready(function($) {
    // Find the appointment form
    const appointmentForm = $('#appointmentForm');
    
    if (!appointmentForm.length) {
        console.error('Appointment form not found');
        return;
    }
    
    // Debug mode - set to true to enable console logging
    const debug = true;
    
    // Debug logging function
    function logDebug(message, data = null) {
        if (!debug) return;
        
        if (data !== null) {
            console.log(`FORM DEBUG: ${message}`, data);
        } else {
            console.log(`FORM DEBUG: ${message}`);
        }
        
        // Send to server debug log if available
        if (typeof massageBookingAPI !== 'undefined') {
            // Don't block execution with this call
            try {
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
            } catch (e) {
                // Ignore errors from debug logging
            }
        }
    }
    
    // Initialize the form submission handler
    function initFormHandler() {
        logDebug('Initializing enhanced form handler');
        
        // Handle form submission
        appointmentForm.on('submit', function(e) {
            // Prevent the default form submission
            e.preventDefault();
            e.stopPropagation();
            
            logDebug('Form submission intercepted');
            
            // Basic form validation
            if (!validateForm()) {
                logDebug('Form validation failed');
                return false;
            }
            
            // Show loading overlay
            const loadingOverlay = createLoadingOverlay();
            $('body').append(loadingOverlay);
            
            // Prepare the form data
            const formData = collectFormData();
            logDebug('Form data collected', formData);
            
            // Submit the form via AJAX
            submitForm(formData)
                .then(handleSuccess)
                .catch(handleError)
                .finally(() => {
                    $(loadingOverlay).remove();
                });
        });
    }
    
    // Create a loading overlay
    function createLoadingOverlay() {
        // Create a styled overlay
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
        
        // Add content to the overlay
        overlay.html(`
            <div style="text-align: center;">
                <div class="loading-spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #4a6fa5; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto; animation: spin 2s linear infinite;"></div>
                <p style="margin-top: 10px;">Processing your appointment...</p>
            </div>
        `);
        
        // Add the spin animation if it's not already defined
        if (!document.getElementById('spin-animation')) {
            $('<style>', {
                id: 'spin-animation',
                text: '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
            }).appendTo('head');
        }
        
        return overlay;
    }
    
    // Validate the form before submission
    function validateForm() {
        // Use a more robust validation approach
        let valid = true;
        let errorFields = [];
        
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
                errorFields.push(field.label);
                valid = false;
            } else {
                $(`#${field.id}`).css('border-color', '').removeAttr('aria-invalid');
            }
        });
        
        // Check email format
        const email = $('#email').val();
        if (email && !isValidEmail(email)) {
            $('#email').css('border-color', 'red').attr('aria-invalid', 'true');
            errorFields.push('Valid Email Address');
            valid = false;
        }
        
        // Check phone format
        const phone = $('#phone').val();
        if (phone && !isValidPhone(phone)) {
            $('#phone').css('border-color', 'red').attr('aria-invalid', 'true');
            errorFields.push('Valid Phone Number');
            valid = false;
        }
        
        // Check service duration
        if (!$('input[name="duration"]:checked').length) {
            $('#serviceDuration').css('border-color', 'red');
            errorFields.push('Service Duration');
            valid = false;
        } else {
            $('#serviceDuration').css('border-color', '');
        }
        
        // Check time slot
        if (!$('.time-slot.selected').length) {
            $('#timeSlots').css('border-color', 'red');
            errorFields.push('Time Slot');
            valid = false;
        } else {
            $('#timeSlots').css('border-color', '');
        }
        
        // Show error message if validation failed
        if (!valid) {
            const errorMessage = 'Please complete the following: ' + errorFields.join(', ');
            showFormError(errorMessage);
            logDebug('Validation failed', errorFields);
        }
        
        return valid;
    }
    
    // Email validation helper
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    // Phone validation helper
    function isValidPhone(phone) {
        return /^[\d\s()+\-\.]{7,20}$/.test(phone);
    }
    
    // Collect all form data
    function collectFormData() {
        // Create an object to hold all form data
        const formData = {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            appointmentDate: $('#appointmentDate').val(),
            duration: $('input[name="duration"]:checked').val()
        };
        
        // Get selected time slot
        const selectedTimeSlot = $('.time-slot.selected');
        if (selectedTimeSlot.length) {
            formData.startTime = selectedTimeSlot.attr('data-time');
            formData.endTime = selectedTimeSlot.attr('data-end-time');
        }
        
        // Get focus areas
        const focusAreas = [];
        $('input[name="focus"]:checked').each(function() {
            focusAreas.push($(this).val());
        });
        formData.focusAreas = JSON.stringify(focusAreas);
        
        // Get pressure preference
        formData.pressurePreference = $('#pressurePreference').val();
        
        // Get special requests
        formData.specialRequests = $('#specialRequests').val();
        
        return formData;
    }
    
    // Submit the form via AJAX with better error handling
    function submitForm(formData) {
        return new Promise((resolve, reject) => {
            // Store raw formData for debugging
            const formDataDebug = {...formData};
            delete formDataDebug.nonce; // Don't log sensitive data
            logDebug('Submitting form data', formDataDebug);
            
            // First, try jQuery AJAX (most compatible)
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
    
    // Handle successful form submission
    function handleSuccess(data) {
        logDebug('Form submitted successfully', data);
        
        // Show success message
        const message = data.message || 'Your appointment has been booked successfully! A confirmation email will be sent shortly.';
        alert(message);
        
        // Reset the form
        resetForm();
    }
    
    // Handle form submission error
    function handleError(error) {
        logDebug('Form submission error', error);
        
        // Show error message
        const message = error.message || 'An error occurred while booking your appointment. Please try again.';
        showFormError(message);
    }
    
    // Display an error message on the form
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
        appointmentForm.prepend(errorElement);
        
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
    
    // Reset the form after successful submission
    function resetForm() {
        // Reset form fields
        appointmentForm[0].reset();
        
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
            scrollTop: appointmentForm.offset().top - 100
        }, 500);
    }
    
    // Add debugging button when in development mode
    function addDebugFeatures() {
        if (!debug) return;
        
        // Add debug button after the form
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
                display: 'none' // Hidden by default
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
                    sessionStorage: JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}'),
                    apiInfo: typeof massageBookingAPI !== 'undefined' ? {
                        ajaxUrl: massageBookingAPI.ajaxUrl,
                        restUrl: massageBookingAPI.restUrl,
                        nonceSet: !!massageBookingAPI.nonce
                    } : 'API not defined'
                };
                
                logDebug('Debug button clicked', formState);
                
                // Test the connection with a simple call
                testConnection();
                
                alert('Debug information has been logged to the console and server.');
            }
        });
        
        // Insert after form or at the end of the container
        const container = appointmentForm.closest('.massage-booking-container, .container');
        if (container.length) {
            container.append(debugButton);
        } else {
            appointmentForm.after(debugButton);
        }
        
        // Show debug button with Ctrl+Shift+D
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                $('#debugFormButton').toggle();
                e.preventDefault();
            }
        });
    }
    
    // Test connection to the server
    function testConnection() {
        logDebug('Testing connection to server');
        
        // Simple ping to verify API connectivity
        $.ajax({
            url: massageBookingAPI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'massage_booking_debug_form_submission',
                nonce: massageBookingAPI.nonce,
                message: 'Connection test',
                data: JSON.stringify({
                    timestamp: new Date().toISOString(),
                    userAgent: navigator.userAgent
                })
            },
            success: function(response) {
                logDebug('Connection test successful', response);
            },
            error: function(xhr, status, error) {
                logDebug('Connection test failed', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            }
        });
    }
    
    // Initialize the form handler
    initFormHandler();
    
    // Add debug features if in development mode
    addDebugFeatures();
});