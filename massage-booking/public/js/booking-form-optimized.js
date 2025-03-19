/**
 * Massage Booking Form JavaScript - Optimized Version
 * 
 * This is the optimized JavaScript for the booking form.
 * The api-connector.js file will override some of these functions
 * to connect to the WordPress backend.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Set minimum date to today
    const today = new Date();
    const dateInput = document.getElementById('appointmentDate');
    dateInput.min = today.toISOString().split('T')[0];
    
    /**
     * Validate available days when selecting a date
     */
    const setAvailableDays = () => {
        const availableDays = dateInput.getAttribute('data-available-days')?.split(',').map(Number) || [1, 2, 3, 4, 5];
        
        dateInput.addEventListener('input', function() {
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
    };
    
    // Handle service duration selection
    const initDurationSelection = () => {
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
                const selectedDate = dateInput.value;
                if (selectedDate) {
                    fetchAvailableTimeSlots(selectedDate, radio.value);
                }
            });
        });
    };
    
    // Handle focus areas selection
    const initFocusAreaSelection = () => {
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
    };
    
    /**
     * This function will be overridden by api-connector.js
     * to fetch time slots from the server
     * @param {string} date - Selected date
     * @param {string} duration - Selected duration in minutes
     * @returns {Promise} - Promise resolving to available time slots
     */
    const fetchAvailableTimeSlots = async (date, duration) => {
        console.log(`Fetching time slots for ${date} with duration ${duration}`);
        
        // This is a placeholder that will be replaced by api-connector.js
        // In a standalone version, this would generate slots based on hardcoded rules
        
        const slotsContainer = document.getElementById('timeSlots');
        slotsContainer.innerHTML = '<p>Loading available times...</p>';
        
        try {
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
        } catch (error) {
            console.error('Error fetching time slots:', error);
            slotsContainer.innerHTML = '<p>Error loading available times. Please try again.</p>';
        }
    };
    
    /**
     * Update appointment summary
     */
    const updateSummary = () => {
        const summary = document.getElementById('bookingSummary');
        const selectedDuration = document.querySelector('input[name="duration"]:checked');
        const selectedTime = document.querySelector('.time-slot.selected');
        const selectedDate = dateInput.value;
        
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
    };
    
    /**
     * Form validation function
     * @returns {boolean} - True if form is valid, false otherwise
     */
    const validateForm = () => {
        let valid = true;
        const requiredFields = ['fullName', 'email', 'phone', 'appointmentDate'];
        
        // Check required fields
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            if (!element.value) {
                element.style.borderColor = 'red';
                element.setAttribute('aria-invalid', 'true');
                valid = false;
            } else {
                element.style.borderColor = '';
                element.removeAttribute('aria-invalid');
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
    };
    
    // Form submission - this will be overridden by api-connector.js
    const initFormSubmission = () => {
        const form = document.getElementById('appointmentForm');
        if (form) {
            form.addEventListener('submit', function(e) {
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
                document.getElementById('timeSlots').innerHTML = '<p>Please select a date to see available time slots.</p>';
            });
        }
    };
    
    // Save form state to session storage
    const saveFormState = () => {
        const formElements = document.querySelectorAll('#appointmentForm input, #appointmentForm select, #appointmentForm textarea');
        formElements.forEach(element => {
            element.addEventListener('change', () => {
                const formData = {};
                formElements.forEach(el => {
                    if (el.type === 'radio' || el.type === 'checkbox') {
                        if (el.checked) {
                            formData[el.name] = el.value;
                        }
                    } else {
                        formData[el.name] = el.value;
                    }
                });
                sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
            });
        });
    };
    
    // Restore form state from session storage
    const restoreFormState = () => {
        const savedData = sessionStorage.getItem('massageBookingFormData');
        if (savedData) {
            const formData = JSON.parse(savedData);
            Object.keys(formData).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element) {
                    if (element.type === 'radio' || element.type === 'checkbox') {
                        document.querySelector(`[name="${key}"][value="${formData[key]}"]`).checked = true;
                        
                        // Also update the selected class
                        const container = element.closest('.radio-option, .checkbox-option');
                        if (container) {
                            container.classList.add('selected');
                        }
                    } else {
                        element.value = formData[key];
                    }
                }
            });
            
            // If date was selected, fetch time slots
            if (formData.appointmentDate) {
                const selectedDuration = document.querySelector('input[name="duration"]:checked')?.value || '60';
                fetchAvailableTimeSlots(formData.appointmentDate, selectedDuration);
            }
        }
    };
    
    // Add ARIA attributes for accessibility
    const enhanceAccessibility = () => {
        // Add role and aria-label to main sections
        document.getElementById('serviceDuration').setAttribute('role', 'radiogroup');
        document.getElementById('serviceDuration').setAttribute('aria-label', 'Service Duration Options');
        
        document.getElementById('focusAreas').setAttribute('role', 'group');
        document.getElementById('focusAreas').setAttribute('aria-label', 'Focus Areas Options');
        
        document.getElementById('timeSlots').setAttribute('role', 'listbox');
        document.getElementById('timeSlots').setAttribute('aria-label', 'Available Time Slots');
        
        // Make sure all inputs have proper labels
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.getAttribute('aria-label') && !input.getAttribute('id')) {
                input.setAttribute('aria-label', input.getAttribute('name'));
            }
        });
    };
    
    // Initialize the form
    const init = () => {
        setAvailableDays();
        initDurationSelection();
        initFocusAreaSelection();
        initFormSubmission();
        saveFormState();
        restoreFormState();
        enhanceAccessibility();
    };
    
    init();
    
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
