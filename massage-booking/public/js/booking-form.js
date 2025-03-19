/**
 * Massage Booking Form JavaScript
 * 
 * This is the original JavaScript for the booking form.
 * The api-connector.js file will override some of these functions
 * to connect to the WordPress backend.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const today = new Date();
    document.getElementById('appointmentDate').min = today.toISOString().split('T')[0];
    
    // Validate available days when selecting a date
    function setAvailableDays() {
        const dateInput = document.getElementById('appointmentDate');
        const availableDays = dateInput.getAttribute('data-available-days').split(',').map(Number);
        
        dateInput.addEventListener('input', function(e) {
            const selectedDate = new Date(this.value);
            const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
            
            if (!availableDays.includes(dayOfWeek)) {
                alert('Sorry, appointments are not available on this day. Please select a different date.');
                this.value = '';
            } else {
                // When a valid date is selected, fetch available time slots
                const selectedDuration = document.querySelector('input[name="duration"]:checked')?.value || '60';
                fetchAvailableTimeSlots(this.value, selectedDuration);
            }
        });
    }
    
    // Handle service duration selection
    const radioOptions = document.querySelectorAll('.radio-option');
    radioOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Clear previous selection
            radioOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Select this option
            this.classList.add('selected');
            
            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Update summary
            updateSummary();
            
            // Re-fetch available slots if a date is already selected
            const selectedDate = document.getElementById('appointmentDate').value;
            if (selectedDate) {
                fetchAvailableTimeSlots(selectedDate, radio.value);
            }
        });
    });
    
    // Handle focus areas selection
    const checkboxOptions = document.querySelectorAll('.checkbox-option');
    checkboxOptions.forEach(option => {
        option.addEventListener('click', function() {
            this.classList.toggle('selected');
            
            // Toggle the checkbox
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            // Update summary
            updateSummary();
        });
    });
    
    // This function will be overridden by api-connector.js
    // to fetch time slots from the server
    async function fetchAvailableTimeSlots(date, duration) {
        console.log(`Fetching time slots for ${date} with duration ${duration}`);
        
        // This is a placeholder that will be replaced by api-connector.js
        // In a standalone version, this would generate slots based on hardcoded rules
        
        const slotsContainer = document.getElementById('timeSlots');
        slotsContainer.innerHTML = '<p>Loading available times...</p>';
        
        // Simulate network delay
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Simulate some available slots
        slotsContainer.innerHTML = '';
        
        const startHour = 9; // 9 AM
        const endHour = 17;  // 5 PM
        
        for (let hour = startHour; hour <= endHour; hour++) {
            // Create time slots for each hour
            const time = `${hour}:00`;
            const displayTime = `${hour > 12 ? hour - 12 : hour}:00 ${hour >= 12 ? 'PM' : 'AM'}`;
            
            const slotElement = document.createElement('div');
            slotElement.className = 'time-slot';
            slotElement.setAttribute('data-time', time);
            
            // Calculate end time based on duration
            const endHour = Math.floor(hour + parseInt(duration) / 60);
            const endMinutes = (parseInt(duration) % 60);
            const endTime = `${endHour}:${endMinutes.toString().padStart(2, '0')}`;
            
            slotElement.setAttribute('data-end-time', endTime);
            slotElement.textContent = displayTime;
            
            // Add click event
            slotElement.addEventListener('click', function() {
                // Clear previous selection
                document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                
                // Select this slot
                this.classList.add('selected');
                
                // Update summary
                updateSummary();
            });
            
            slotsContainer.appendChild(slotElement);
        }
    }
    
    // Update appointment summary
    function updateSummary() {
        const summary = document.getElementById('bookingSummary');
        const selectedDuration = document.querySelector('input[name="duration"]:checked');
        const selectedTime = document.querySelector('.time-slot.selected');
        const selectedDate = document.getElementById('appointmentDate').value;
        
        if (selectedDuration && selectedTime && selectedDate) {
            // Show summary
            summary.classList.add('visible');
            
            // Update service
            const durationValue = selectedDuration.value;
            const durationPrice = selectedDuration.closest('.radio-option').getAttribute('data-price');
            document.getElementById('summaryService').textContent = `${durationValue} Minutes Massage ($${durationPrice})`;
            
            // Update focus areas
            const selectedFocusAreas = Array.from(document.querySelectorAll('input[name="focus"]:checked'))
                .map(checkbox => checkbox.value);
            document.getElementById('summaryFocusAreas').textContent = selectedFocusAreas.length > 0 
                ? selectedFocusAreas.join(', ') 
                : 'No specific areas selected';
            
            // Format date
            const formattedDate = new Date(selectedDate).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Update date & time
            document.getElementById('summaryDateTime').textContent = `${formattedDate} at ${selectedTime.textContent}`;
            
            // Update price
            document.getElementById('summaryPrice').textContent = `$${durationPrice}`;
        } else {
            // Hide summary if not all required elements are selected
            summary.classList.remove('visible');
        }
    }
    
    // Form validation function
    function validateForm() {
        let valid = true;
        const requiredFields = ['fullName', 'email', 'phone', 'appointmentDate'];
        
        // Check required fields
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            if (!element.value) {
                element.style.borderColor = 'red';
                valid = false;
            } else {
                element.style.borderColor = '';
            }
        });
        
        // Check service duration
        if (!document.querySelector('input[name="duration"]:checked')) {
            document.getElementById('serviceDuration').style.borderColor = 'red';
            valid = false;
        } else {
            document.getElementById('serviceDuration').style.borderColor = '';
        }
        
        // Check time slot
        if (!document.querySelector('.time-slot.selected')) {
            document.getElementById('timeSlots').style.borderColor = 'red';
            valid = false;
        } else {
            document.getElementById('timeSlots').style.borderColor = '';
        }
        
        return valid;
    }
    
    // Form submission - this will be overridden by api-connector.js
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Show mock success message - this will be replaced by actual API call
        alert('Your appointment has been booked! A confirmation email will be sent shortly.');
        
        // Reset form
        this.reset();
        document.querySelectorAll('.radio-option, .checkbox-option, .time-slot').forEach(el => {
            el.classList.remove('selected');
        });
        document.getElementById('bookingSummary').classList.remove('visible');
    });
    
    // Initialize the form
    setAvailableDays();
    
    // Make functions available globally for api-connector.js to override
    window.fetchAvailableTimeSlots = fetchAvailableTimeSlots;
    window.updateSummary = updateSummary;
    window.validateForm = validateForm;
    window.setAvailableDays = setAvailableDays;
    window.loadSettings = function() {
        // Placeholder function to be overridden by api-connector.js
        console.log('Loading settings (default implementation)');
        return Promise.resolve({});
    };
});