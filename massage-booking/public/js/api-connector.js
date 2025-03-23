/**
 * Massage Booking - API Connector - Consolidated Version
 * 
 * This script connects the booking form to WordPress REST API.
 * Version: 1.0.7
 */

(function($) {
    'use strict';
    
    // Wait for jQuery and document to be ready
    $(document).ready(function() {
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
        
        // Cache for API responses
        const apiCache = {
            settings: null,
            slots: {},
            
            getSettings: function() {
                return this.settings;
            },
            
            setSettings: function(settings) {
                this.settings = settings;
                return settings;
            },
            
            getSlots: function(date, duration) {
                const key = `${date}-${duration}`;
                return this.slots[key];
            },
            
            setSlots: function(date, duration, slots) {
                const key = `${date}-${duration}`;
                this.slots[key] = slots;
                return slots;
            },
            
            clearCache: function() {
                this.settings = null;
                this.slots = {};
            }
        };
        
        // Loading indicator for API calls
        const createLoader = function() {
            const loader = document.createElement('div');
            loader.className = 'api-loading';
            loader.innerHTML = `
                <div class="loading-spinner"></div>
                <p>Processing...</p>
            `;
            
            // Add styles if they don't already exist
            if (!document.getElementById('api-loading-styles')) {
                const style = document.createElement('style');
                style.id = 'api-loading-styles';
                style.textContent = `
                    .api-loading {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(255, 255, 255, 0.8);
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        z-index: 9999;
                    }
                    .loading-spinner {
                        width: 40px;
                        height: 40px;
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #4a6fa5;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin-bottom: 10px;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
            
            return loader;
        };
        
        const showLoader = function() {
            const loader = createLoader();
            document.body.appendChild(loader);
            return loader;
        };
        
        const hideLoader = function(loader) {
            if (loader && loader.parentNode) {
                loader.parentNode.removeChild(loader);
            }
        };
        
        // API error handling
        const handleApiError = function(error, message = 'An error occurred') {
            console.error(error);
            
            // Create error display element
            const errorDiv = document.createElement('div');
            errorDiv.className = 'api-error';
            errorDiv.innerHTML = `
                <p><strong>Error:</strong> ${message}</p>
                <p>${error.message || 'Please try again or contact support.'}</p>
                <button class="dismiss-error">Dismiss</button>
            `;
            
            // Add styles if they don't already exist
            if (!document.getElementById('api-error-styles')) {
                const style = document.createElement('style');
                style.id = 'api-error-styles';
                style.textContent = `
                    .api-error {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        max-width: 300px;
                        background: #f8d7da;
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                        border-radius: 4px;
                        padding: 15px;
                        z-index: 9999;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    }
                    .dismiss-error {
                        background: #721c24;
                        color: white;
                        border: none;
                        padding: 5px 10px;
                        border-radius: 3px;
                        cursor: pointer;
                        margin-top: 10px;
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Add dismiss button functionality
            errorDiv.querySelector('.dismiss-error').addEventListener('click', function() {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            });
            
            // Auto-dismiss after 10 seconds
            setTimeout(function() {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 10000);
            
            document.body.appendChild(errorDiv);
        };
        
        /**
         * Generate a secure nonce for specific actions
         * 
         * @param {string} action The action name
         * @return {Promise<string>} A promise resolving to the nonce
         */
        const generateNonce = async function(action) {
            try {
                const response = await fetch(massageBookingAPI.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'generate_booking_nonce',
                        booking_action: action,
                        _wpnonce: massageBookingAPI.nonce
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Failed to generate nonce');
                }
                
                const data = await response.json();
                return data.success ? data.data.nonce || '' : '';
            } catch (error) {
                console.error('Error generating nonce:', error);
                return '';
            }
        };
        
        /**
         * Load settings from WordPress
         * 
         * @return {Promise<Object>} A promise resolving to the settings object
         */
        const loadSettings = async function() {
            try {
                // Check cache first
                const cachedSettings = apiCache.getSettings();
                if (cachedSettings) {
                    return cachedSettings;
                }
                
                const loader = showLoader();
                
                // Fetch settings from WordPress API
                const response = await fetch(massageBookingAPI.restUrl + 'settings', {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': massageBookingAPI.nonce,
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to load settings (HTTP ${response.status})`);
                }
                
                const settings = await response.json();
                console.log('Settings loaded from WordPress:', settings);
                
                // Cache the settings
                apiCache.setSettings(settings);
                
                // Apply working days setting
                if (settings.working_days) {
                    const dateInput = document.getElementById('appointmentDate');
                    if (dateInput) {
                        dateInput.setAttribute(
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
                
                // Call any other relevant initialization functions
                if (typeof window.disableUnavailableDays === 'function') {
                    window.disableUnavailableDays();
                }
                
                if (typeof window.setAvailableDays === 'function') {
                    window.setAvailableDays();
                }
                
                hideLoader(loader);
                return settings;
            } catch (error) {
                hideLoader(loader);
                console.error('Error loading settings:', error);
                handleApiError(error, 'Failed to load settings');
                
                // Set default working days if all else fails
                const dateInput = document.getElementById('appointmentDate');
                if (dateInput) {
                    dateInput.setAttribute('data-available-days', '1,2,3,4,5');
                }
                
                return {};
            }
        };
        
        /**
         * Fetch available time slots from WordPress
         * 
         * @param {string} date Date in YYYY-MM-DD format
         * @param {string} duration Duration in minutes
         * @return {Promise<Object>} A promise resolving to the available slots
         */
        const fetchAvailableTimeSlots = async function(date, duration) {
            try {
                // Check cache first
                const cachedSlots = apiCache.getSlots(date, duration);
                if (cachedSlots) {
                    updateTimeSlotDisplay(cachedSlots);
                    return cachedSlots;
                }
                
                // Show loading indicator
                const slotsContainer = document.getElementById('timeSlots');
                if (!slotsContainer) return { available: false, slots: [] };
                
                slotsContainer.innerHTML = '<p>Loading available times...</p>';
                
                // Generate a nonce for this request
                const requestNonce = await generateNonce('check_slot_availability');
                
                // Fetch available slots from WordPress API
                const timeSlotUrl = `${massageBookingAPI.restUrl}available-slots?date=${encodeURIComponent(date)}&duration=${encodeURIComponent(duration)}`;
                
                const response = await fetch(timeSlotUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': massageBookingAPI.nonce,
                        'X-Booking-Request-Nonce': requestNonce,
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to load time slots (HTTP ${response.status})`);
                }
                
                // Try to parse response as JSON with error handling
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Failed to parse time slots JSON:', jsonError);
                    // Try alternate approach - get text and parse manually
                    const text = await response.text();
                    try {
                        // Remove any non-JSON content that might precede the JSON data
                        const jsonStart = text.indexOf('{');
                        if (jsonStart >= 0) {
                            const cleanJson = text.substring(jsonStart);
                            data = JSON.parse(cleanJson);
                        } else {
                            throw new Error('No JSON object found in response');
                        }
                    } catch (e) {
                        throw new Error('Invalid response format');
                    }
                }
                
                console.log('Available slots from WordPress:', data);
                
                // Cache the response
                apiCache.setSlots(date, duration, data);
                
                // Update the UI
                updateTimeSlotDisplay(data);
                
                return data;
            } catch (error) {
                console.error('Error fetching time slots:', error);
                
                // Show error message
                const slotsContainer = document.getElementById('timeSlots');
                if (slotsContainer) {
                    slotsContainer.innerHTML = `
                        <p>Error loading available times. Please try again.</p>
                        <button class="retry-button">Retry</button>
                    `;
                    
                    // Add retry button functionality
                    const retryButton = slotsContainer.querySelector('.retry-button');
                    if (retryButton) {
                        retryButton.addEventListener('click', function() {
                            fetchAvailableTimeSlots(date, duration);
                        });
                    }
                }
                
                return { available: false, slots: [] };
            }
        };
        
        /**
         * Update the time slot display with available slots
         * 
         * @param {Object} data The slots data from API
         */
        const updateTimeSlotDisplay = function(data) {
            const slotsContainer = document.getElementById('timeSlots');
            if (!slotsContainer) return;
            
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
                slotElement.setAttribute('role', 'option');
                slotElement.setAttribute('aria-selected', 'false');
                slotElement.textContent = slot.displayTime;
                
                // Add click event handler
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
                    
                    // Save selection to session storage
                    const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
                    formData.selectedTimeSlot = slot.startTime;
                    formData.selectedEndTime = slot.endTime;
                    sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
                });
                
                slotsContainer.appendChild(slotElement);
            });
        };
        
        /**
         * Validate email format
         */
        function validateEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
        
        /**
         * Validate phone format
         */
        function validatePhone(phone) {
            const re = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
            return re.test(String(phone));
        }
        
        /**
         * Enhanced client-side validation
         */
        function enhancedValidation() {
            const errors = [];
            
            // Name validation
            const nameField = document.getElementById('fullName');
            if (!nameField.value.trim()) {
                errors.push('Please enter your full name');
                nameField.style.borderColor = 'red';
                nameField.setAttribute('aria-invalid', 'true');
            } else {
                nameField.style.borderColor = '';
                nameField.removeAttribute('aria-invalid');
            }
            
            // Email validation
            const emailField = document.getElementById('email');
            if (!emailField.value.trim()) {
                errors.push('Please enter your email address');
                emailField.style.borderColor = 'red';
                emailField.setAttribute('aria-invalid', 'true');
            } else if (!validateEmail(emailField.value)) {
                errors.push('Please enter a valid email address');
                emailField.style.borderColor = 'red';
                emailField.setAttribute('aria-invalid', 'true');
            } else {
                emailField.style.borderColor = '';
                emailField.removeAttribute('aria-invalid');
            }
            
            // Phone validation
            const phoneField = document.getElementById('phone');
            if (!phoneField.value.trim()) {
                errors.push('Please enter your phone number');
                phoneField.style.borderColor = 'red';
                phoneField.setAttribute('aria-invalid', 'true');
            } else if (!validatePhone(phoneField.value)) {
                errors.push('Please enter a valid phone number');
                phoneField.style.borderColor = 'red';
                phoneField.setAttribute('aria-invalid', 'true');
            } else {
                phoneField.style.borderColor = '';
                phoneField.removeAttribute('aria-invalid');
            }
            
            // Date validation
            const dateField = document.getElementById('appointmentDate');
            if (!dateField.value) {
                errors.push('Please select an appointment date');
                dateField.style.borderColor = 'red';
                dateField.setAttribute('aria-invalid', 'true');
            } else {
                dateField.style.borderColor = '';
                dateField.removeAttribute('aria-invalid');
            }
            
            // Duration validation
            const selectedDuration = document.querySelector('input[name="duration"]:checked');
            if (!selectedDuration) {
                errors.push('Please select a service duration');
                document.getElementById('serviceDuration').style.borderColor = 'red';
            } else {
                document.getElementById('serviceDuration').style.borderColor = '';
            }
            
            // Time slot validation
            const selectedTimeSlot = document.querySelector('.time-slot.selected');
            if (!selectedTimeSlot) {
                errors.push('Please select an appointment time');
                if (document.getElementById('timeSlots')) {
                    document.getElementById('timeSlots').style.borderColor = 'red';
                }
            } else {
                if (document.getElementById('timeSlots')) {
                    document.getElementById('timeSlots').style.borderColor = '';
                }
            }
            
            // Display validation errors if any
            if (errors.length > 0) {
                // Remove any existing error containers
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
                
                // Add styles
                const style = document.createElement('style');
                style.textContent = `
                    .validation-errors {
                        background-color: #f8d7da;
                        color: #721c24;
                        padding: 15px;
                        margin-bottom: 20px;
                        border: 1px solid #f5c6cb;
                        border-radius: 4px;
                    }
                    .validation-errors ul {
                        margin-top: 10px;
                        padding-left: 20px;
                    }
                `;
                document.head.appendChild(style);
                
                // Insert at top of form
                const form = document.getElementById('appointmentForm');
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
                
                return false;
            }
            
            return true;
        }
        
        /**
         * Submit the form via AJAX with better error handling
         */
        function submitForm(formData) {
            return new Promise((resolve, reject) => {
                // Store raw formData for debugging
                const formDataDebug = {...formData};
                delete formDataDebug.nonce; // Don't log sensitive data
                console.log('Submitting form data', formDataDebug);
                
                // Use jQuery AJAX for maximum compatibility
                $.ajax({
                    url: massageBookingAPI.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    timeout: 30000, // 30-second timeout
                    success: function(response) {
                        console.log('AJAX success response', response);
                        
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
                        console.error('AJAX error', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });

                        let errorMessage = 'Server connection error. Please try again.';
                        let errorData = {};

                        // Try to extract more detailed error information
                        try {
                            const jsonResponse = JSON.parse(xhr.responseText);
                            if (jsonResponse.data && jsonResponse.data.message) {
                                errorMessage = jsonResponse.data.message;
                            } else if (jsonResponse.message) {
                                errorMessage = jsonResponse.message;
                            }
                            errorData = jsonResponse;
                        } catch (e) {
                            // Not JSON, use text response or status
                            if (xhr.status === 0) {
                                errorMessage = 'Network error. Please check your connection.';
                            } else if (xhr.status === 403) {
                                errorMessage = 'Permission denied. Please refresh the page and try again.';
                            } else if (xhr.status >= 500) {
                                errorMessage = 'Server error. Please try again later.';
                            } else {
                                errorMessage = xhr.responseText || error || 'Unknown error';
                            }
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
         * Helper function to reset the form after successful submission
         */
        function resetForm() {
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
            
            // Clear session storage
            sessionStorage.removeItem('massageBookingFormData');
        }
        
        /**
         * Collect form data for submission
         */
        function collectFormData() {
            // Get selected values
            const selectedTimeSlot = document.querySelector('.time-slot.selected');
            const selectedDuration = document.querySelector('input[name="duration"]:checked');

            // Verify required selections
            if (!selectedTimeSlot || !selectedDuration) {
                throw new Error('Please select a time slot and service duration.');
            }

            // Get focus areas as an array
            const focusAreas = Array.from(document.querySelectorAll('input[name="focus"]:checked'))
                .map(checkbox => checkbox.value);

            // Prepare form data
            return {
                action: 'massage_booking_create_appointment',
                nonce: massageBookingAPI.nonce,
                fullName: document.getElementById('fullName').value,
                email: document.getElementById('email').value,
                phone: document.getElementById('phone').value,
                appointmentDate: document.getElementById('appointmentDate').value,
                startTime: selectedTimeSlot.getAttribute('data-time'),
                endTime: selectedTimeSlot.getAttribute('data-end-time'),
                duration: selectedDuration.value,
                focusAreas: JSON.stringify(focusAreas),
                pressurePreference: document.getElementById('pressurePreference').value,
                specialRequests: document.getElementById('specialRequests').value
            };
        }
        
        /**
         * Override the form submission
         */
        appointmentForm.addEventListener('submit', async function(e) {
            // Prevent the default form submission
            e.preventDefault();

            console.log('Form submission intercepted');

            // Validate using enhanced validation
            if (typeof enhancedValidation === 'function') {
                if (!enhancedValidation()) {
                    console.log('Form validation failed');
                    return;
                }
            } else if (typeof window.validateForm === 'function') {
                if (!window.validateForm()) {
                    console.log('Form validation failed');
                    return;
                }
            }

            // Show loader
            const loader = showLoader();

            try {
                // Collect form data
                const formData = collectFormData();
                
                // Submit the form
                const result = await submitForm(formData);
                
                // Handle success
                console.log('Appointment successfully created:', result);
                
                // Show success message
                alert('Your appointment has been booked! A confirmation email will be sent shortly.');
                
                // Reset form
                resetForm();
                
            } catch (error) {
                // Handle error
                console.error('Error booking appointment:', error);
                alert('Error: ' + (error.message || 'Failed to book appointment'));
            } finally {
                // Hide loader
                hideLoader(loader);
            }
        }, true); // Using capture phase to ensure our handler runs first
        
        // Initialize the form by loading settings
        window.loadSettings = loadSettings;
        window.fetchAvailableTimeSlots = fetchAvailableTimeSlots;
        
        // Initialize with settings
        loadSettings().then(() => {
            console.log('Settings loaded and applied to form');
            
            // Try to restore any saved form state
            try {
                const savedData = sessionStorage.getItem('massageBookingFormData');
                if (savedData) {
                    const formData = JSON.parse(savedData);
                    
                    // Restore date if it was selected
                    if (formData.appointmentDate) {
                        const dateField = document.getElementById('appointmentDate');
                        dateField.value = formData.appointmentDate;
                        
                        // Trigger date input event to load time slots
                        const event = new Event('input', { bubbles: true });
                        dateField.dispatchEvent(event);
                    }
                }
            } catch (e) {
                console.warn('Failed to restore saved form state:', e);
            }
        });
    });
})(jQuery);