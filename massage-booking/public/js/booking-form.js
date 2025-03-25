/**
 * Massage Booking Form JavaScript - Consolidated Version
 * 
 * Enhanced form functionality combining the best features from all versions
 * Version: 1.0.7
 */

(function() {
    'use strict';
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Store initial state for form reset purposes
        const initialState = {};
        
        // Set minimum date to today/tomorrow
        const setMinimumDate = function() {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            const dateInput = document.getElementById('appointmentDate');
            if (dateInput) {
                dateInput.min = today.toISOString().split('T')[0];
                
                // Store initial date input properties
                initialState.dateMin = dateInput.min;
                initialState.dateAvailableDays = dateInput.getAttribute('data-available-days');
            }
        };
        
        /**
         * Disable unavailable days in the date picker
         * This uses the data-available-days attribute to determine which days are available
         */
        const disableUnavailableDays = function() {
            const dateInput = document.getElementById('appointmentDate');
            if (!dateInput) return;
            
            const availableDaysStr = dateInput.getAttribute('data-available-days');
            if (!availableDaysStr) return;
            
            // Convert to array of numbers
            const availableDays = availableDaysStr.split(',').map(Number);
            
            // Add validation on input change
            dateInput.addEventListener('input', function() {
                validateSelectedDate(this, availableDays);
            });
            
            // Also validate on focus out to handle direct input
            dateInput.addEventListener('blur', function() {
                validateSelectedDate(this, availableDays);
            });
            
            // Store available days in initial state
            initialState.availableDays = availableDays;
        };
        
        /**
         * Validate a selected date against available days
         */
        const validateSelectedDate = function(dateElement, availableDays) {
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
         * Initialize radio buttons
         */
        const initRadioOptions = function() {
            const radioOptions = document.querySelectorAll('.radio-option');
            radioOptions.forEach(option => {
                // If the radio button is checked initially, add selected class
                if (option.querySelector('input[type="radio"]').checked) {
                    option.classList.add('selected');
                }
                
                // Add click event handler
                option.addEventListener('click', function() {
                    // Clear previous selection
                    document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                    
                    // Select this option
                    this.classList.add('selected');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Update booking summary
                    if (typeof window.updateSummary === 'function') {
                        window.updateSummary();
                    }
                    
                    // Re-fetch available slots if a date is already selected
                    const selectedDate = document.getElementById('appointmentDate').value;
                    if (selectedDate) {
                        if (typeof window.fetchAvailableTimeSlots === 'function') {
                            window.fetchAvailableTimeSlots(selectedDate, radio.value);
                        }
                    }
                    
                    // Save selected value to session
                    saveFormState();
                });
                
                // Store initial state
                const radioInput = option.querySelector('input[type="radio"]');
                if (radioInput) {
                    initialState[radioInput.name] = radioInput.checked ? radioInput.value : null;
                }
            });
        };
        
        /**
         * Initialize checkbox options
         */
        const initCheckboxOptions = function() {
            const checkboxOptions = document.querySelectorAll('.checkbox-option');
            checkboxOptions.forEach(option => {
                // If the checkbox is checked initially, add selected class
                if (option.querySelector('input[type="checkbox"]').checked) {
                    option.classList.add('selected');
                }
                
                // Add click event handler
                option.addEventListener('click', function() {
                    // Toggle selection
                    this.classList.toggle('selected');
                    
                    // Toggle the checkbox
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                    
                    // Update booking summary
                    if (typeof window.updateSummary === 'function') {
                        window.updateSummary();
                    }
                    
                    // Save form state
                    saveFormState();
                });
                
                // Store initial state
                const checkboxInput = option.querySelector('input[type="checkbox"]');
                if (checkboxInput) {
                    initialState[checkboxInput.name] = checkboxInput.checked;
                }
            });
        };
        
        /**
         * Update booking summary
         */
        const updateSummary = function() {
            const summary = document.getElementById('bookingSummary');
            if (!summary) return;
            
            const selectedDuration = document.querySelector('input[name="duration"]:checked');
            const selectedTime = document.querySelector('.time-slot.selected');
            const selectedDate = document.getElementById('appointmentDate')?.value;
            
            if (selectedDuration && selectedTime && selectedDate) {
                // Show summary
                summary.classList.add('visible');
                
                // Update service
                const durationValue = selectedDuration.value;
                const durationPrice = selectedDuration.closest('.radio-option')?.getAttribute('data-price') || '0';
                const summaryService = document.getElementById('summaryService');
                if (summaryService) {
                    summaryService.textContent = `${durationValue} Minutes Massage ($${durationPrice})`;
                }
                
                // Update focus areas
                const selectedFocusAreas = Array.from(document.querySelectorAll('input[name="focus"]:checked'))
                    .map(checkbox => checkbox.value);
                const summaryFocusAreas = document.getElementById('summaryFocusAreas');
                if (summaryFocusAreas) {
                    summaryFocusAreas.textContent = selectedFocusAreas.length > 0 
                        ? selectedFocusAreas.join(', ') 
                        : 'No specific areas selected';
                }
                
                // Format date
                const formattedDate = new Date(selectedDate).toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                // Update date & time
                const summaryDateTime = document.getElementById('summaryDateTime');
                if (summaryDateTime) {
                    summaryDateTime.textContent = `${formattedDate} at ${selectedTime.textContent}`;
                }
                
                // Update price
                const summaryPrice = document.getElementById('summaryPrice');
                if (summaryPrice) {
                    summaryPrice.textContent = `$${durationPrice}`;
                }
            } else {
                // Hide summary if not all required elements are selected
                summary.classList.remove('visible');
            }
        };
        
        /**
         * Form validation
         */
        const validateForm = function() {
            let valid = true;
            const errors = [];
            
            // Check required fields
            const requiredFields = ['fullName', 'email', 'phone', 'appointmentDate'];
            requiredFields.forEach(field => {
                const element = document.getElementById(field);
                if (!element || !element.value.trim()) {
                    if (element) {
                        element.style.borderColor = 'red';
                        element.setAttribute('aria-invalid', 'true');
                    }
                    
                    errors.push(field.replace(/([A-Z])/g, ' $1').trim());
                    valid = false;
                } else {
                    if (element) {
                        element.style.borderColor = '';
                        element.removeAttribute('aria-invalid');
                    }
                }
            });
            
            // Special validation for email format
            const emailField = document.getElementById('email');
            if (emailField && emailField.value.trim() && !isValidEmail(emailField.value)) {
                emailField.style.borderColor = 'red';
                emailField.setAttribute('aria-invalid', 'true');
                errors.push('Valid Email Address');
                valid = false;
            }
            
            // Special validation for phone format
            const phoneField = document.getElementById('phone');
            if (phoneField && phoneField.value.trim() && !isValidPhone(phoneField.value)) {
                phoneField.style.borderColor = 'red';
                phoneField.setAttribute('aria-invalid', 'true');
                errors.push('Valid Phone Number');
                valid = false;
            }
            
            // Check service duration
            if (!document.querySelector('input[name="duration"]:checked')) {
                document.getElementById('serviceDuration').style.borderColor = 'red';
                errors.push('Service Duration');
                valid = false;
            } else {
                document.getElementById('serviceDuration').style.borderColor = '';
            }
            
            // Check time slot
            if (!document.querySelector('.time-slot.selected')) {
                document.getElementById('timeSlots').style.borderColor = 'red';
                errors.push('Time Slot');
                valid = false;
            } else {
                document.getElementById('timeSlots').style.borderColor = '';
            }
            
            // Display validation errors if any
            if (!valid) {
                displayFormErrors(errors);
            }
            
            return valid;
        };
        
        /**
         * Display form validation errors
         */
        const displayFormErrors = function(errors) {
            // Remove any existing error messages
            const existingErrors = document.querySelectorAll('.validation-errors');
            existingErrors.forEach(el => el.parentNode.removeChild(el));
            
            // Create error message element
            const errorContainer = document.createElement('div');
            errorContainer.className = 'validation-errors';
            errorContainer.setAttribute('role', 'alert');
            errorContainer.innerHTML = `
                <h3>Please fix the following errors:</h3>
                <ul>${errors.map(error => `<li>${error}</li>`).join('')}</ul>
            `;
            
            // Add to the form
            const form = document.getElementById('appointmentForm');
            if (form) {
                if (form.firstChild) {
                    form.insertBefore(errorContainer, form.firstChild);
                } else {
                    form.appendChild(errorContainer);
                }
                
                // Scroll to errors
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
                // Remove after 8 seconds
                setTimeout(() => {
                    if (errorContainer.parentNode) {
                        errorContainer.parentNode.removeChild(errorContainer);
                    }
                }, 8000);
            }
        };
        
        /**
         * Validate email format
         */
        const isValidEmail = function(email) {
            // Basic email validation regex
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        };
        
        /**
         * Validate phone number format
         */
        const isValidPhone = function(phone) {
            // Flexible phone number validation - accepts various formats
            return /^[\d\s()+\-\.]{7,20}$/.test(phone);
        };
        
        /**
         * Save form state to session storage
         */
        const saveFormState = function() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            
            const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
            
            // Text inputs and selects
            form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea').forEach(input => {
                if (input.id) {
                    formData[input.id] = input.value;
                }
            });
            
            // Radio buttons
            form.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                formData[radio.name] = radio.value;
            });
            
            // Checkboxes - collect as array
            const checkboxGroups = {};
            form.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                if (!checkboxGroups[checkbox.name]) {
                    checkboxGroups[checkbox.name] = [];
                }
                checkboxGroups[checkbox.name].push(checkbox.value);
            });
            
            // Add checkbox groups to form data
            Object.keys(checkboxGroups).forEach(name => {
                formData[name] = checkboxGroups[name];
            });
            
            // Selected time slot
            const selectedTimeSlot = document.querySelector('.time-slot.selected');
            if (selectedTimeSlot) {
                formData.selectedTimeSlot = selectedTimeSlot.getAttribute('data-time');
                formData.selectedEndTime = selectedTimeSlot.getAttribute('data-end-time');
            }
            
            // Save to session storage
            sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
        };
        
        /**
         * Load form state from session storage
         */
        const loadFormState = function() {
            const savedData = sessionStorage.getItem('massageBookingFormData');
            if (!savedData) return;
            
            try {
                const formData = JSON.parse(savedData);
                
                // Restore text inputs
                for (const key in formData) {
                    const input = document.getElementById(key);
                    if (input && input.type !== 'radio' && input.type !== 'checkbox') {
                        input.value = formData[key];
                    }
                }
                
                // Restore radio buttons
                if (formData.duration) {
                    const radio = document.querySelector(`input[name="duration"][value="${formData.duration}"]`);
                    if (radio) {
                        radio.checked = true;
                        // Update selected class
                        const radioOption = radio.closest('.radio-option');
                        if (radioOption) {
                            document.querySelectorAll('.radio-option').forEach(opt => 
                                opt.classList.remove('selected'));
                            radioOption.classList.add('selected');
                        }
                    }
                }
                
                // Restore checkboxes for focus areas
                if (formData.focus && Array.isArray(formData.focus)) {
                    formData.focus.forEach(value => {
                        const checkbox = document.querySelector(`input[name="focus"][value="${value}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Update selected class
                            const checkboxOption = checkbox.closest('.checkbox-option');
                            if (checkboxOption) {
                                checkboxOption.classList.add('selected');
                            }
                        }
                    });
                }
                
                // Restore date and load time slots
                if (formData.appointmentDate) {
                    const dateInput = document.getElementById('appointmentDate');
                    if (dateInput) {
                        dateInput.value = formData.appointmentDate;
                        
                        // Fetch time slots for this date
                        setTimeout(() => {
                            if (typeof window.fetchAvailableTimeSlots === 'function') {
                                window.fetchAvailableTimeSlots(
                                    formData.appointmentDate, 
                                    formData.duration || '60'
                                ).then(() => {
                                    // After slots are loaded, restore selected time slot
                                    if (formData.selectedTimeSlot) {
                                        setTimeout(() => {
                                            const slot = document.querySelector(`.time-slot[data-time="${formData.selectedTimeSlot}"]`);
                                            if (slot) {
                                                slot.click();
                                            }
                                        }, 300);
                                    }
                                });
                            }
                        }, 100);
                    }
                }
                
                // Update summary
                setTimeout(() => {
                    if (typeof window.updateSummary === 'function') {
                        window.updateSummary();
                    }
                }, 500);
                
            } catch (error) {
                console.error('Error loading form state:', error);
            }
        };
        
        /**
         * Reset form to initial state
         */
        const resetForm = function() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            
            // Reset the form
            form.reset();
            
            // Clear all "selected" classes
            document.querySelectorAll('.radio-option, .checkbox-option, .time-slot').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Reset radio buttons to initial state
            Object.keys(initialState).forEach(key => {
                if (key === 'dateMin' || key === 'dateAvailableDays' || key === 'availableDays') {
                    return; // Skip these properties
                }
                
                const element = document.querySelector(`input[name="${key}"][value="${initialState[key]}"]`);
                if (element) {
                    element.checked = true;
                    
                    // Update selected class
                    const option = element.closest('.radio-option, .checkbox-option');
                    if (option) {
                        option.classList.add('selected');
                    }
                }
            });
            
            // Clear time slots
            const timeSlots = document.getElementById('timeSlots');
            if (timeSlots) {
                timeSlots.innerHTML = '<p>Please select a date to see available time slots.</p>';
            }
            
            // Hide summary
            const summary = document.getElementById('bookingSummary');
            if (summary) {
                summary.classList.remove('visible');
            }
            
            // Clear session storage
            sessionStorage.removeItem('massageBookingFormData');
        };
        
        /**
         * Add form submit handler
         * This will be overridden by api-connector.js
         */
        const initSubmitHandler = function() {
            const form = document.getElementById('appointmentForm');
            if (!form) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!validateForm()) {
                    return;
                }
                
                // Show placeholder success message
                // This will be replaced by API connector
                alert('Your appointment has been booked! A confirmation email will be sent shortly.');
                
                // Reset form
                resetForm();
            });
        };
        
        /**
         * Add accessibility enhancements
         */
        const addAccessibilityFeatures = function() {
            // Add ARIA roles to form components
            const serviceDuration = document.getElementById('serviceDuration');
            if (serviceDuration) {
                serviceDuration.setAttribute('role', 'radiogroup');
                serviceDuration.setAttribute('aria-label', 'Service Duration Options');
            }
            
            const focusAreas = document.getElementById('focusAreas');
            if (focusAreas) {
                focusAreas.setAttribute('role', 'group');
                focusAreas.setAttribute('aria-label', 'Focus Areas Options');
            }
            
            const timeSlots = document.getElementById('timeSlots');
            if (timeSlots) {
                timeSlots.setAttribute('role', 'listbox');
                timeSlots.setAttribute('aria-label', 'Available Time Slots');
            }
            
            // Ensure all inputs have accessible labels
            document.querySelectorAll('input, select, textarea').forEach(input => {
                if (!input.getAttribute('aria-label') && input.getAttribute('id')) {
                    const labelEl = document.querySelector(`label[for="${input.getAttribute('id')}"]`);
                    if (!labelEl) {
                        // Add aria-label if no label exists
                        input.setAttribute('aria-label', input.getAttribute('name'));
                    }
                }
            });
        };
        
        /**
         * Initialize the form by calling all setup functions
         */
        const initForm = function() {
            setMinimumDate();
            disableUnavailableDays();
            initRadioOptions();
            initCheckboxOptions();
            initSubmitHandler();
            addAccessibilityFeatures();
            
            // Add change event listeners to inputs for session state saving
            document.querySelectorAll('#appointmentForm input, #appointmentForm select, #appointmentForm textarea').forEach(input => {
                input.addEventListener('change', saveFormState);
            });
            
            // Try to restore form state from session
            loadFormState();
            
            // Add a custom event listener for form reset
            document.addEventListener('resetBookingForm', resetForm);
        };
        
        // Initialize the form
        initForm();
        
        // Expose necessary functions to window for API connector
        window.fetchAvailableTimeSlots = async function(date, duration) {
            // This is a placeholder that will be replaced by api-connector.js
            console.log(`Fetching time slots for ${date} with duration ${duration}`);
            
            const slotsContainer = document.getElementById('timeSlots');
            if (!slotsContainer) return;
            
            slotsContainer.innerHTML = '<p>Loading available times...</p>';
            
            // Simulate loading (this will be replaced by API connector)
            setTimeout(() => {
                slotsContainer.innerHTML = '<p>Please use the API connector to fetch available times.</p>';
            }, 1000);
            
            return { available: false, slots: [] };
        };
        
        window.updateSummary = updateSummary;
        window.validateForm = validateForm;
        window.resetForm = resetForm;
        window.disableUnavailableDays = disableUnavailableDays;
    });
    
    // Improved handling of unavailable days
    function disableUnavailableDays() {
        const dateInput = document.getElementById('appointmentDate');
        if (!dateInput) return;

        const availableDaysAttr = dateInput.getAttribute('data-available-days');
        if (!availableDaysAttr) return;

        const availableDays = availableDaysAttr.split(',').map(Number);

        // Create a datepicker if jQuery UI is available
        if ($.fn.datepicker) {
            $(dateInput).datepicker({
                beforeShowDay: function(date) {
                    const day = date.getDay();
                    return [availableDays.includes(day), ''];
                },
                dateFormat: 'yy-mm-dd',
                minDate: 0 // Today
            });
        } else {
            // Fallback to manual validation
            dateInput.addEventListener('input', function() {
                validateSelectedDate(this, availableDays);
            });
        }
    }
})();