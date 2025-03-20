jQuery(document).ready(function($) {
    // Find the appointment form
    const appointmentForm = $('#appointmentForm');
    
    if (!appointmentForm.length) {
        console.error('Appointment form not found');
        return;
    }
    
    // Handle form submission
    appointmentForm.on('submit', function(e) {
        // Prevent default form submission
        e.preventDefault();
        
        console.log('Form submission intercepted by jQuery handler');
        
        // Basic validation
        let valid = true;
        
        // Required fields
        const requiredFields = ['fullName', 'email', 'phone', 'appointmentDate'];
        requiredFields.forEach(field => {
            const input = $('#' + field);
            if (!input.val().trim()) {
                input.css('border-color', 'red');
                valid = false;
            } else {
                input.css('border-color', '');
            }
        });
        
        // Check duration is selected
        if (!$('input[name="duration"]:checked').length) {
            $('#serviceDuration').css('border-color', 'red');
            valid = false;
        } else {
            $('#serviceDuration').css('border-color', '');
        }
        
        // Check time slot is selected
        if (!$('.time-slot.selected').length) {
            $('#timeSlots').css('border-color', 'red');
            valid = false;
        } else {
            $('#timeSlots').css('border-color', '');
        }
        
        if (!valid) {
            alert('Please fill in all required fields.');
            return;
        }
        
        // Show loading state
        $('body').append('<div id="loadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999;"><div style="text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; margin: 0 auto; animation: spin 2s linear infinite;"></div><p style="margin-top: 10px;">Processing your appointment...</p></div></div>');
        $('<style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');
        
        // Get selected values
        const selectedTimeSlot = $('.time-slot.selected');
        const selectedDuration = $('input[name="duration"]:checked');
        
        // Get focus areas
        const focusAreas = [];
        $('input[name="focus"]:checked').each(function() {
            focusAreas.push($(this).val());
        });
        
        // Prepare form data
        const formData = {
            action: 'massage_booking_create_appointment',
            nonce: massageBookingAPI.nonce,
            fullName: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            appointmentDate: $('#appointmentDate').val(),
            startTime: selectedTimeSlot.data('time'),
            endTime: selectedTimeSlot.data('end-time'),
            duration: selectedDuration.val(),
            focusAreas: JSON.stringify(focusAreas),
            pressurePreference: $('#pressurePreference').val(),
            specialRequests: $('#specialRequests').val()
        };
        
        console.log('Submitting appointment data via jQuery AJAX:', formData);
        
        // Submit via jQuery AJAX
        $.ajax({
            url: massageBookingAPI.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
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
                    alert('Error: ' + (response.data?.message || 'Failed to book appointment'));
                }
            },
            error: function(xhr, status, error) {
                // Remove loading overlay
                $('#loadingOverlay').remove();
                
                console.error('AJAX Error:', status, error);
                console.log('Server response:', xhr.responseText);
                
                // Try to parse response if possible
                let errorMessage = 'An error occurred while booking your appointment.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                } catch (e) {
                    // Could not parse JSON, use default message
                }
                
                alert('Error: ' + errorMessage + ' Please try again or contact us directly.');
            }
        });
    });
});