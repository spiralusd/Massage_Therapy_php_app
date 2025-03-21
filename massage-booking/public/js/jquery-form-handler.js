jQuery(document).ready(function($) {
    // Find the appointment form
    const appointmentForm = $('#appointmentForm');
    
    if (!appointmentForm.length) {
        console.error('Appointment form not found');
        return;
    }
    
    // Handle form submission
    appointmentForm.on('submit', function(e) {
        // Prevent default form submission AND stop event propagation to prevent
        // other handlers from executing
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        console.log('Form submission intercepted by jQuery handler');
        
        // Improved validation logic
        let valid = true;
        let errorFields = [];
        
        // Required fields with more detailed checking
        const requiredFields = [
            {id: 'fullName', label: 'Full Name'},
            {id: 'email', label: 'Email Address'},
            {id: 'phone', label: 'Phone Number'},
            {id: 'appointmentDate', label: 'Appointment Date'}
        ];
        
        requiredFields.forEach(field => {
            const input = $('#' + field.id);
            console.log('Checking field:', field.id, 'Value:', input.val());
            
            if (!input.length) {
                console.error('Field not found in DOM:', field.id);
                return;
            }
            
            if (!input.val() || input.val().trim() === '') {
                input.css('border-color', 'red');
                errorFields.push(field.label);
                valid = false;
            } else {
                input.css('border-color', '');
            }
        });
        
        // Check duration is selected
        const selectedDuration = $('input[name="duration"]:checked');
        console.log('Duration selected:', selectedDuration.length, selectedDuration.val());
        if (!selectedDuration.length) {
            $('#serviceDuration').css('border-color', 'red');
            errorFields.push('Service Duration');
            valid = false;
        } else {
            $('#serviceDuration').css('border-color', '');
        }
        
        // Check time slot is selected
        const selectedTimeSlot = $('.time-slot.selected');
        console.log('Time slot selected:', selectedTimeSlot.length);
        if (!selectedTimeSlot.length) {
            $('#timeSlots').css('border-color', 'red');
            errorFields.push('Time Slot');
            valid = false;
        } else {
            $('#timeSlots').css('border-color', '');
        }
        
        if (!valid) {
            console.warn('Form validation failed. Missing fields:', errorFields);
            alert('Please fill in all required fields: ' + errorFields.join(', '));
            return;
        }
        
        console.log('Form validation passed');
        
        // Show loading state
        $('body').append('<div id="loadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999;"><div style="text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto; animation: spin 2s linear infinite;"></div><p style="margin-top: 10px;">Processing your appointment...</p></div></div>');
        $('<style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');
        
        // Get focus areas
        const focusAreas = [];
        $('input[name="focus"]:checked').each(function() {
            focusAreas.push($(this).val());
        });
        
        // Prepare form data - Use simple key/value pairs to avoid JSON stringification issues
        const formData = {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            appointmentDate: $('#appointmentDate').val(),
            startTime: selectedTimeSlot.attr('data-time'),  // Use attr instead of data for consistency
            endTime: selectedTimeSlot.attr('data-end-time'),
            duration: selectedDuration.val(),
            focusAreas: JSON.stringify(focusAreas),
            pressurePreference: $('#pressurePreference').val(),
            specialRequests: $('#specialRequests').val()
        };
        
        console.log('Submitting appointment data via jQuery AJAX:', formData);
        
        // Submit via jQuery AJAX - with explicit settings for maximum compatibility
        $.ajax({
            url: massageBookingAPI.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            cache: false,
            processData: true,  // Process data as form fields
            contentType: 'application/x-www-form-urlencoded', // Standard form content type
            success: function(response) {
                // Remove loading overlay
                $('#loadingOverlay').remove();
                
                console.log('Server response:', response);
                
                if (response.success) {
                    // Show success message
                    alert('Your appointment has been booked successfully! A confirmation email will be sent shortly.');
                    
                    // Reset form
                    appointmentForm[0].reset();
                    $('.radio-option, .checkbox-option, .time-slot').removeClass('selected');
                    $('#bookingSummary').removeClass('visible');
                    $('#timeSlots').html('<p>Please select a date to see available time slots.</p>');
                    
                    // Reselect default option (60 min)
                    $('#duration60').prop('checked', true);
                    $('#duration60').closest('.radio-option').addClass('selected');
                } else {
                    // Show error message
                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Failed to book appointment'));
                }
            },
            error: function(xhr, status, error) {
                // Remove loading overlay
                $('#loadingOverlay').remove();
                
                console.error('AJAX Error:', status, error);
                
                // Log the raw response for debugging
                console.log('Raw server response status:', xhr.status);
                console.log('Raw server response text:', xhr.responseText);
                
                // Try to extract more info from response
                let errorMessage = 'An error occurred while booking your appointment.';
                try {
                    // Try to parse as JSON first
                    const jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse.data && jsonResponse.data.message) {
                        errorMessage = jsonResponse.data.message;
                    } else if (jsonResponse.message) {
                        errorMessage = jsonResponse.message;
                    }
                } catch (e) {
                    // Not valid JSON, try to extract error from HTML
                    console.log('Response is not valid JSON:', e);
                    
                    // Look for common error patterns in HTML response
                    const responseText = xhr.responseText;
                    if (responseText.includes('Fatal error')) {
                        const errorStart = responseText.indexOf('Fatal error');
                        const errorEnd = responseText.indexOf('</b>', errorStart);
                        if (errorStart > -1 && errorEnd > -1) {
                            errorMessage = responseText.substring(errorStart, errorEnd + 4)
                                .replace(/<[^>]*>/g, '');
                        }
                    }
                }
                
                alert('Error: ' + errorMessage + ' Please try again or contact us directly.');
            }
        });
    });
});