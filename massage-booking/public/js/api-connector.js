/**
 * Massage Booking - API Connector
 * 
 * This script connects the original booking form to WordPress.
 */
jQuery(document).ready(function($) {
    // Log initialization
    console.log('Massage Booking API Connector initialized');
    
    // Check if form exists
    const appointmentForm = document.getElementById('appointmentForm');
    if (!appointmentForm) {
        console.error('Appointment form not found');
        return;
    }
    
    // Check if WordPress API data exists
    if (typeof massageBookingAPI === 'undefined') {
        console.error('WordPress API data not available');
        return;
    }
    
    // Store references to original functions
    const originalLoadSettings = window.loadSettings;
    const originalFetchTimeSlots = window.fetchAvailableTimeSlots;
    const originalUpdateSummary = window.updateSummary;
    
    // We'll override these key functions next...
    
    // Initialize by loading settings
    if (typeof window.loadSettings === 'function') {
        window.loadSettings();
    }
    
    // Override loadSettings function
    window.loadSettings = async function() {
    try {
        // Fetch settings from WordPress API
        const response = await fetch(massageBookingAPI.restUrl + 'settings', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': massageBookingAPI.nonce
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to load settings');
        }
        
        const settings = await response.json();
        console.log('Settings loaded from WordPress:', settings);
        
        // Apply working days setting
        if (settings.working_days) {
            document.getElementById('appointmentDate').setAttribute(
                'data-available-days', 
                settings.working_days.join(',')
            );
            
            // Update the displayed days text
            const dayNames = {
                '0': 'Sunday', 
                '1': 'Monday', 
                '2': 'Tuesday',
                '3': 'Wednesday', 
                '4': 'Thursday', 
                '5': 'Friday', 
                '6': 'Saturday'
            };
            
            const availableDays = settings.working_days.map(day => dayNames[day]).join(', ');
            const smallElement = document.querySelector('#appointmentDate + small');
            if (smallElement) {
                smallElement.textContent = 'Available days: ' + availableDays;
            }
        }
        
        // Apply service durations
        if (settings.durations) {
            const radioOptions = document.querySelectorAll('.radio-option');
            radioOptions.forEach(option => {
                const value = option.getAttribute('data-value');
                if (!settings.durations.includes(value)) {
                    option.style.display = 'none';
                } else {
                    option.style.display = 'block';
                    
                    // Update price if available
                    if (settings.prices && settings.prices[value]) {
                        const priceElement = option.querySelector('.price');
                        if (priceElement) {
                            priceElement.textContent = '$' + settings.prices[value];
                        }
                    }
                }
            });
        }
        
        // Update break time in privacy notice
        if (settings.break_time) {
            const privacyNotice = document.querySelector('.privacy-notice p');
            if (privacyNotice) {
                privacyNotice.innerHTML = privacyNotice.innerHTML.replace(
                    /\d+-minute break/, 
                    settings.break_time + '-minute break'
                );
            }
        }
        
        // Call the original function that sets up date validation
        if (typeof window.setAvailableDays === 'function') {
            window.setAvailableDays();
        }
        
        return settings;
    } catch (error) {
        console.error('Error loading settings from WordPress:', error);
        
        // Fall back to original function if possible
        if (typeof originalLoadSettings === 'function') {
            return originalLoadSettings();
        }
        
        // Set default working days if all else fails
        document.getElementById('appointmentDate').setAttribute('data-available-days', '1,2,3,4,5');
    }
};
    // Override fetchAvailableTimeSlots function
window.fetchAvailableTimeSlots = async function(date, duration) {
    try {
        // Show loading indicator
        const slotsContainer = document.getElementById('timeSlots');
        slotsContainer.innerHTML = '<p>Loading available times...</p>';
        
        // Fetch available slots from WordPress API
        const response = await fetch(
            `${massageBookingAPI.restUrl}available-slots?date=${date}&duration=${duration}`,
            {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': massageBookingAPI.nonce
                }
            }
        );
        
        if (!response.ok) {
            throw new Error('Failed to load time slots');
        }
        
        const data = await response.json();
        console.log('Available slots from WordPress:', data);
        
        // Clear the container
        slotsContainer.innerHTML = '';
        
        // Check if we have available slots
        if (!data.available || !data.slots || data.slots.length === 0) {
            slotsContainer.innerHTML = '<p>No appointments available on this date.</p>';
            return;
        }
        
        // Create time slot elements
        data.slots.forEach(slot => {
            const slotElement = document.createElement('div');
            slotElement.className = 'time-slot';
            slotElement.setAttribute('data-time', slot.startTime);
            slotElement.setAttribute('data-end-time', slot.endTime);
            slotElement.textContent = slot.displayTime;
            
            // Add click event handler
            slotElement.addEventListener('click', function() {
                // Clear previous selection
                document.querySelectorAll('.time-slot').forEach(s => {
                    s.classList.remove('selected');
                });
                
                // Select this slot
                this.classList.add('selected');
                
                // Update appointment summary
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
            });
            
            slotsContainer.appendChild(slotElement);
        });
    } catch (error) {
        console.error('Error fetching time slots from WordPress:', error);
        
        // Show error message
        const slotsContainer = document.getElementById('timeSlots');
        slotsContainer.innerHTML = '<p>Error loading available times. Please try again.</p>';
        
        // Fall back to original function if possible
        if (typeof originalFetchTimeSlots === 'function') {
            return originalFetchTimeSlots(date, duration);
        }
    }
};
    // Override form submission
appointmentForm.addEventListener('submit', async function(e) {
    // Prevent the default form submission
    e.preventDefault();
    
    console.log('Form submission intercepted');
    
    // Validate the form using the original validation function
    if (typeof window.validateForm === 'function') {
        if (!window.validateForm()) {
            console.log('Form validation failed');
            return;
        }
    }
    
    // Get selected values
    const selectedTimeSlot = document.querySelector('.time-slot.selected');
    const selectedDuration = document.querySelector('input[name="duration"]:checked');
    
    // Verify required selections
    if (!selectedTimeSlot || !selectedDuration) {
        alert('Please select a time slot and service duration.');
        return;
    }
    
    // Prepare the form data for submission
    const formData = {
        fullName: document.getElementById('fullName').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        appointmentDate: document.getElementById('appointmentDate').value,
        startTime: selectedTimeSlot.getAttribute('data-time'),
        endTime: selectedTimeSlot.getAttribute('data-end-time'),
        duration: selectedDuration.value,
        focusAreas: Array.from(document.querySelectorAll('input[name="focus"]:checked'))
            .map(checkbox => checkbox.value),
        pressurePreference: document.getElementById('pressurePreference').value,
        specialRequests: document.getElementById('specialRequests').value
    };
    
    console.log('Submitting appointment to WordPress:', formData);
    
    // Disable submit button to prevent double submissions
    const submitButton = appointmentForm.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';
    
    try {
        // Send data to WordPress API
        const response = await fetch(massageBookingAPI.restUrl + 'appointments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': massageBookingAPI.nonce
            },
            body: JSON.stringify(formData)
        });
        
        const responseData = await response.json();
        
        if (!response.ok) {
            throw new Error(responseData.message || 'Failed to book appointment');
        }
        
        console.log('Appointment successfully created:', responseData);
        
        // Show success message
        alert('Your appointment has been booked! A confirmation email will be sent shortly.');
        
        // Reset form
        appointmentForm.reset();
        
        // Clear selections
        document.querySelectorAll('.radio-option, .checkbox-option, .time-slot').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Hide summary
        const summaryElement = document.getElementById('bookingSummary');
        if (summaryElement) {
            summaryElement.classList.remove('visible');
        }
        
        // Reset time slots
        document.getElementById('timeSlots').innerHTML = '<p>Please select a date to see available time slots.</p>';
        
        // Reselect default option (60 min)
        const defaultOption = document.getElementById('duration60');
        if (defaultOption) {
            defaultOption.checked = true;
            const radioOption = defaultOption.closest('.radio-option');
            if (radioOption) {
                radioOption.classList.add('selected');
            }
        }
    } catch (error) {
        console.error('Error booking appointment:', error);
        alert('Error: ' + error.message);
    } finally {
        // Re-enable submit button
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    }
}, true); // Using capture phase to ensure our handler runs firstd
    
    // Function to update form based on WordPress settings
async function updateFormBasedOnSettings() {
    const settings = await window.loadSettings();
    
    // Update form title if configured
    if (settings.business_name) {
        const titleElement = document.querySelector('.container h1');
        if (titleElement) {
            titleElement.textContent = settings.business_name + ' - Appointment Booking';
        }
    }
    
    // Update service descriptions if provided
    if (settings.service_descriptions) {
        const radioOptions = document.querySelectorAll('.radio-option');
        radioOptions.forEach(option => {
            const value = option.getAttribute('data-value');
            if (settings.service_descriptions[value]) {
                const labelElement = option.querySelector('label');
                const priceElement = option.querySelector('.price');
                const priceText = priceElement ? priceElement.outerHTML : '';
                
                labelElement.innerHTML = settings.service_descriptions[value] + ' ' + priceText;
            }
        });
    }
}

// Call after loading settings
window.loadSettings().then(updateFormBasedOnSettings);
});