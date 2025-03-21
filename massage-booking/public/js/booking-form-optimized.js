/**
 * Massage Booking Form JavaScript - Optimized Version
 * 
 * This is the optimized JavaScript for the booking form.
 * The api-connector.js file will override some of these functions
 * to connect to the WordPress backend.
 * Enhanced Date & Time Selection for Massage Booking
 */

document.addEventListener('DOMContentLoaded', () => {
    // Set minimum date to today
    const today = new Date();
    const dateInput = document.getElementById('appointmentDate');
    if(!dateInput) return;
    
    // Set minimum date to today
    dateInput.min = today.toISOString().split('T')[0];
    
    /**
     * Disable unavailable days in the date picker
     * This uses the data-available-days attribute to determine which days are available
     */
    const disableUnavailableDays = () => {
        const availableDaysStr = dateInput.getAttribute('data-available-days');
        if (!availableDaysStr) return;
        
        // Convert to array of numbers
        const availableDays = availableDaysStr.split(',').map(Number);
        
        // Create a new Date Input with enhanced functionality
        dateInput.addEventListener('input', function() {
            validateSelectedDate(this, availableDays);
        });
        
        // Also validate on focus out to handle direct input
        dateInput.addEventListener('blur', function() {
            validateSelectedDate(this, availableDays);
        });
        
        // Add the date picker event listener
        enhanceDatePicker(dateInput, availableDays);
    };
    
    /**
     * Validate a selected date against available days
     */
    const validateSelectedDate = (dateElement, availableDays) => {
        if (!dateElement.value) return;
        
        const selectedDate = new Date(dateElement.value);
        const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
        
        if (!availableDays.includes(dayOfWeek)) {
            alert('Sorry, appointments are not available on this day. Please select a different date.');
            dateElement.value = '';
        } else {
            // Valid date selected, fetch available time slots
            const selectedDuration = document.querySelector('input[name="duration"]:checked')?.value || '60';
            if (typeof window.fetchAvailableTimeSlots === 'function') {
                window.fetchAvailableTimeSlots(dateElement.value, selectedDuration);
            }
        }
    };
    
    /**
     * Enhanced date picker implementation to handle unavailable days
     */
    const enhanceDatePicker = (dateElement, availableDays) => {
        // Add custom date picker behavior using native HTML date input
        // This adds extra functionality without replacing the native control
        
        // Mark unavailable days visually (requires browser support)
        if ('showPicker' in dateElement) { // Modern browsers
            // Create a style element for our custom date styles
            const styleEl = document.createElement('style');
            styleEl.textContent = `
                /* Attempt to gray out unavailable days - browser support varies */
                input[type="date"]::-webkit-calendar-picker-indicator {
                    background-color: transparent;
                    cursor: pointer;
                }
                
                /* Custom styling for the date input */
                input[type="date"] {
                    position: relative;
                    cursor: pointer;
                }
                
                input[type="date"]:invalid {
                    border-color: #dc3545;
                }
            `;
            document.head.appendChild(styleEl);
            
            // Add a note about available days
            const availableDayNames = availableDays.map(day => {
                const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return days[day];
            }).join(', ');
            
            const noteElement = document.createElement('small');
            noteElement.textContent = `Available days: ${availableDayNames}`;
            noteElement.style.display = 'block';
            noteElement.style.marginTop = '5px';
            noteElement.style.color = '#666';
            
            // Replace existing note if found, otherwise append
            const existingNote = dateElement.nextElementSibling;
            if (existingNote && existingNote.tagName.toLowerCase() === 'small') {
                existingNote.textContent = noteElement.textContent;
            } else {
                dateElement.parentNode.insertBefore(noteElement, dateElement.nextSibling);
            }
        }
    };
    
    // Call our enhancer function
    disableUnavailableDays();
    
    /**
     * Improved time slot fetching with better error handling
     */
    window.fetchAvailableTimeSlots = async function(date, duration) {
        // Show loading state
        const slotsContainer = document.getElementById('timeSlots');
        if (!slotsContainer) return;
        
        slotsContainer.innerHTML = '<p>Loading available times...</p>';
        slotsContainer.classList.add('loading');
        
        try {
            // Generate a new nonce for security
            let nonce = '';
            try {
                // Try to get a nonce from the server first
                const nonceResponse = await fetch(massageBookingAPI.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'generate_booking_nonce',
                        booking_action: 'check_time_slots',
                        _wpnonce: massageBookingAPI.nonce
                    })
                });
                
                const nonceData = await nonceResponse.json();
                nonce = nonceData.nonce || '';
            } catch(e) {
                console.error('Failed to generate nonce:', e);
                // Continue with the main nonce if specific nonce generation fails
            }
            
            // Fetch available slots from WordPress API
            const response = await fetch(
                `${massageBookingAPI.restUrl}available-slots?date=${date}&duration=${duration}`,
                {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': massageBookingAPI.nonce,
                        'X-Booking-Request-Nonce': nonce
                    }
                }
            );
            
            if (!response.ok) {
                throw new Error(`Failed to load time slots (HTTP ${response.status})`);
            }
            
            const data = await response.json();
            console.log('Available slots from WordPress:', data);
            
            // Update the UI
            displayTimeSlots(data, slotsContainer);
            
            // Return the data for any other components that need it
            return data;
        } catch (error) {
            console.error('Error fetching time slots:', error);
            
            // Show error message with retry button
            slotsContainer.innerHTML = `
                <p>Error loading available times. Please try again.</p>
                <button class="retry-button">Retry</button>
            `;
            
            // Add retry functionality
            const retryButton = slotsContainer.querySelector('.retry-button');
            if (retryButton) {
                retryButton.addEventListener('click', () => {
                    window.fetchAvailableTimeSlots(date, duration);
                });
            }
            
            // Return empty data structure
            return { available: false, slots: [] };
        } finally {
            // Remove loading state
            slotsContainer.classList.remove('loading');
        }
    };
    
    /**
     * Display time slots in the UI
     */
    const displayTimeSlots = (data, container) => {
        // Clear the container
        container.innerHTML = '';
        
        // Check if we have available slots
        if (!data.available || !data.slots || data.slots.length === 0) {
            container.innerHTML = '<p>No appointments available on this date.</p>';
            return;
        }
        
        // Sort slots by time before displaying
        const sortedSlots = [...data.slots].sort((a, b) => {
            return a.startTime.localeCompare(b.startTime);
        });
        
        // Create time slot elements
        sortedSlots.forEach(slot => {
            const slotElement = document.createElement('div');
            slotElement.className = 'time-slot';
            slotElement.setAttribute('data-time', slot.startTime);
            slotElement.setAttribute('data-end-time', slot.endTime);
            slotElement.setAttribute('role', 'option');
            slotElement.setAttribute('aria-selected', 'false');
            slotElement.textContent = slot.displayTime;
            
            // Add click event
            slotElement.addEventListener('click', function() {
                // Clear previous selection
                document.querySelectorAll('.time-slot').forEach(s => {
                    s.classList.remove('selected');
                    s.setAttribute('aria-selected', 'false');
                });
                
                // Select this slot
                this.classList.add('selected');
                this.setAttribute('aria-selected', 'true');
                
                // Update appointment summary
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
                
                // Save selection in form data
                const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
                formData.selectedTimeSlot = slot.startTime;
                formData.selectedEndTime = slot.endTime;
                sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
            });
            
            container.appendChild(slotElement);
        });
    };
    
    /**
     * Initialize the form with saved session data
     */
    const initFormWithSessionData = () => {
        try {
            const savedData = sessionStorage.getItem('massageBookingFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                
                // Restore text inputs
                ['fullName', 'email', 'phone', 'appointmentDate', 'specialRequests'].forEach(field => {
                    const input = document.getElementById(field);
                    if (input && formData[field]) {
                        input.value = formData[field];
                    }
                });
                
                // Restore radio buttons
                if (formData.duration) {
                    const radio = document.querySelector(`input[name="duration"][value="${formData.duration}"]`);
                    if (radio) {
                        radio.checked = true;
                        // Also update the selected class
                        const radioOption = radio.closest('.radio-option');
                        if (radioOption) {
                            document.querySelectorAll('.radio-option').forEach(opt => 
                                opt.classList.remove('selected'));
                            radioOption.classList.add('selected');
                        }
                    }
                }
                
                // Restore pressure preference
                if (formData.pressurePreference) {
                    const select = document.getElementById('pressurePreference');
                    if (select) {
                        select.value = formData.pressurePreference;
                    }
                }
                
                // Restore date and fetch time slots
                if (formData.appointmentDate) {
                    const dateField = document.getElementById('appointmentDate');
                    if (dateField) {
                        dateField.value = formData.appointmentDate;
                        
                        // Fetch time slots for this date
                        const duration = formData.duration || 
                            document.querySelector('input[name="duration"]:checked')?.value || '60';
                        
                        // Use setTimeout to ensure the date is set before fetching slots
                        setTimeout(() => {
                            if (typeof window.fetchAvailableTimeSlots === 'function') {
                                window.fetchAvailableTimeSlots(formData.appointmentDate, duration)
                                    .then(() => {
                                        // After slots are loaded, restore selected time slot
                                        if (formData.selectedTimeSlot) {
                                            setTimeout(() => {
                                                const slot = document.querySelector(`.time-slot[data-time="${formData.selectedTimeSlot}"]`);
                                                if (slot) {
                                                    slot.click();
                                                }
                                            }, 100);
                                        }
                                    });
                            }
                        }, 100);
                    }
                }
                
                // Restore focus areas
                if (formData.focusAreas && Array.isArray(formData.focusAreas)) {
                    formData.focusAreas.forEach(area => {
                        const checkbox = document.querySelector(`input[name="focus"][value="${area}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Update the selected class
                            const checkOption = checkbox.closest('.checkbox-option');
                            if (checkOption) {
                                checkOption.classList.add('selected');
                            }
                        }
                    });
                }
                
                // Update summary if needed
                if (typeof window.updateSummary === 'function') {
                    setTimeout(window.updateSummary, 200);
                }
            }
        } catch (e) {
            console.error('Error restoring form state:', e);
        }
    };
    
    // Call initialization functions
    initFormWithSessionData();
    
    // Save form state on changes
    const saveFormState = () => {
        const form = document.getElementById('appointmentForm');
        if (!form) return;
        
        // Get all form inputs
        const inputs = form.querySelectorAll('input, select, textarea');
        
        // For each input, add a change listener
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
                
                if (input.type === 'radio' && input.checked) {
                    // Radio button
                    formData[input.name] = input.value;
                } else if (input.type === 'checkbox') {
                    // Checkbox - gather all checked values for this name
                    const checkboxes = Array.from(
                        document.querySelectorAll(`input[type="checkbox"][name="${input.name}"]:checked`)
                    ).map(cb => cb.value);
                    
                    formData[input.name] = checkboxes;
                } else {
                    // Regular input
                    formData[input.id || input.name] = input.value;
                }
                
                // Save to session storage
                sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
            });
        });
    };
    
    // Initialize form state saving
    saveFormState();
});