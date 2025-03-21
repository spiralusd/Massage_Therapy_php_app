/**
 * Massage Booking - API Connector - Optimized Version
 * 
 * This script connects the booking form to WordPress REST API.
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
    const originalValidateForm = window.validateForm;
    
    // Loading indicator for API calls
    const createLoader = () => {
        const loader = document.createElement('div');
        loader.className = 'api-loading';
        loader.innerHTML = `
            <div class="loading-spinner"></div>
            <p>Processing...</p>
        `;
        
        // Add styles
        const style = document.createElement('style');
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
        
        return loader;
    };
    
    const showLoader = () => {
        const loader = createLoader();
        document.body.appendChild(loader);
        return loader;
    };
    
    const hideLoader = (loader) => {
        if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
        }
    };
    
    // API error handling
    const handleApiError = (error, message = 'An error occurred') => {
        console.error(error);
        
        // Create error display element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'api-error';
        errorDiv.innerHTML = `
            <p><strong>Error:</strong> ${message}</p>
            <p>${error.message || 'Please try again or contact support.'}</p>
            <button class="dismiss-error">Dismiss</button>
        `;
        
        // Add styles
        const style = document.createElement('style');
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
        
        // Add dismiss button functionality
        errorDiv.querySelector('.dismiss-error').addEventListener('click', () => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        });
        
        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 10000);
        
        document.body.appendChild(errorDiv);
    };
    
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
    
    // Override loadSettings function
    window.loadSettings = async function() {
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
                    'X-WP-Nonce': massageBookingAPI.nonce
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
            
            hideLoader(loader);
            return settings;
        } catch (error) {
            hideLoader(loader);
            handleApiError(error, 'Failed to load settings');
            
            // Fall back to original function if possible
            if (typeof originalLoadSettings === 'function') {
                return originalLoadSettings();
            }
            
            // Set default working days if all else fails
            document.getElementById('appointmentDate').setAttribute('data-available-days', '1,2,3,4,5');
            return {};
        }
    };
    
    // Override fetchAvailableTimeSlots function
    window.fetchAvailableTimeSlots = async function(date, duration) {
        try {
            // Check cache first
            const cachedSlots = apiCache.getSlots(date, duration);
            if (cachedSlots) {
                updateTimeSlotDisplay(cachedSlots);
                return cachedSlots;
            }
            
            // Show loading indicator
            const slotsContainer = document.getElementById('timeSlots');
            slotsContainer.innerHTML = '<p>Loading available times...</p>';
            
            // Fetch available slots from WordPress API with proper error handling
            let timeSlotUrl = `${massageBookingAPI.restUrl}available-slots?date=${encodeURIComponent(date)}&duration=${encodeURIComponent(duration)}`;
            
            const response = await fetch(timeSlotUrl, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': massageBookingAPI.nonce,
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
            console.error('Error fetching time slots from WordPress:', error);
            
            // Show error message
            const slotsContainer = document.getElementById('timeSlots');
            slotsContainer.innerHTML = `
                <p>Error loading available times. Please try again.</p>
                <button class="retry-button">Retry</button>
            `;
            
            // Add retry button functionality
            const retryButton = slotsContainer.querySelector('.retry-button');
            if (retryButton) {
                retryButton.addEventListener('click', () => {
                    window.fetchAvailableTimeSlots(date, duration);
                });
            }
            
            // Fall back to original function if possible
            if (typeof originalFetchTimeSlots === 'function') {
                return originalFetchTimeSlots(date, duration);
            }
            
            return { available: false, slots: [] };
        }
    };
    
    // Helper function to update time slot display
    function updateTimeSlotDisplay(data) {
        const slotsContainer = document.getElementById('timeSlots');
        
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
                sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
            });
            
            slotsContainer.appendChild(slotElement);
        });
    }
    
    // Validate email format
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Validate phone format
    function validatePhone(phone) {
        const re = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
        return re.test(String(phone));
    }
    
    // Enhanced client-side validation
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
    
    // Override the form submission
    /*
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
        const loader = showLoader ? showLoader() : null;

        // Get selected values
        const selectedTimeSlot = document.querySelector('.time-slot.selected');
        const selectedDuration = document.querySelector('input[name="duration"]:checked');

        // Verify required selections
        if (!selectedTimeSlot || !selectedDuration) {
            if (hideLoader) hideLoader(loader);
            alert('Please select a time slot and service duration.');
            return;
        }

        // Get focus areas as an array
        const focusAreas = Array.from(document.querySelectorAll('input[name="focus"]:checked'))
            .map(checkbox => checkbox.value);

        try {
            // APPROACH 1: Use AJAX instead of fetch
            // This is more compatible with older WordPress setups

            // Create a traditional AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', massageBookingAPI.ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            // Set up form data in URL encoded format
            const ajaxData = new URLSearchParams({
                'action': 'massage_booking_create_appointment',
                'nonce': massageBookingAPI.nonce,
                'fullName': document.getElementById('fullName').value,
                'email': document.getElementById('email').value,
                'phone': document.getElementById('phone').value,
                'appointmentDate': document.getElementById('appointmentDate').value,
                'startTime': selectedTimeSlot.getAttribute('data-time'),
                'endTime': selectedTimeSlot.getAttribute('data-end-time'),
                'duration': selectedDuration.value,
                'focusAreas': JSON.stringify(focusAreas),
                'pressurePreference': document.getElementById('pressurePreference').value,
                'specialRequests': document.getElementById('specialRequests').value
            });

            console.log('Submitting appointment via AJAX:', ajaxData.toString());

            // Handle response
            xhr.onload = function() {
                if (hideLoader) hideLoader(loader);

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        console.log('Appointment successfully created:', response);

                        // Show success message
                        alert('Your appointment has been booked! A confirmation email will be sent shortly.');

                        // Reset form
                        resetForm();
                    } else {
                        throw new Error(response.message || 'Failed to book appointment');
                    }
                } catch (parseError) {
                    console.error('Error parsing response:', parseError, 'Raw response:', xhr.responseText);
                    alert('There was a problem processing the server response. Please try again or contact support.');
                }
            };

            xhr.onerror = function() {
                if (hideLoader) hideLoader(loader);
                console.error('Network error occurred');
                alert('A network error occurred. Please check your internet connection and try again.');
            };

            // Send the request
            xhr.send(ajaxData.toString());

            // ------ ALTERNATIVE APPROACH ------
            // If you prefer using fetch, but want a more robust implementation,
            // replace the XMLHttpRequest code above with this:
            /*
            // Create FormData object
            const formData = new FormData();
            formData.append('action', 'massage_booking_create_appointment');
            formData.append('nonce', massageBookingAPI.nonce);
            formData.append('fullName', document.getElementById('fullName').value);
            formData.append('email', document.getElementById('email').value);
            formData.append('phone', document.getElementById('phone').value);
            formData.append('appointmentDate', document.getElementById('appointmentDate').value);
            formData.append('startTime', selectedTimeSlot.getAttribute('data-time'));
            formData.append('endTime', selectedTimeSlot.getAttribute('data-end-time'));
            formData.append('duration', selectedDuration.value);
            formData.append('focusAreas', JSON.stringify(focusAreas));
            formData.append('pressurePreference', document.getElementById('pressurePreference').value);
            formData.append('specialRequests', document.getElementById('specialRequests').value);

            console.log('Submitting appointment via Fetch FormData');

            const response = await fetch(massageBookingAPI.ajaxUrl, {
                method: 'POST',
                body: formData,
                // No need to set Content-Type header - browser sets it with boundary
            });

            // Just log the raw response for debugging
            const rawText = await response.text();
            console.log('Raw server response:', rawText);

            // Try to parse JSON from the response
            let responseData;
            try {
                responseData = JSON.parse(rawText);
            } catch (parseError) {
                console.error('Error parsing response:', parseError);
                if (hideLoader) hideLoader(loader);
                alert('The server returned an invalid response. Please try again later.');
                return;
            }

            if (hideLoader) hideLoader(loader);

            if (responseData.success) {
                console.log('Appointment successfully created:', responseData);

                // Show success message
                alert('Your appointment has been booked! A confirmation email will be sent shortly.');

                // Reset form
                resetForm();
            } else {
                throw new Error(responseData.message || 'Failed to book appointment');
            }
            */
   /*     } catch (error) {
            if (hideLoader) hideLoader(loader);
            console.error('Error booking appointment:', error);
            alert('Error: ' + error.message);
        }
    }, true); // Using capture phase to ensure our handler runs first */
    console.log('Form submission will be handled by jQuery handler in jquery-form-handler.js');

    // Helper function to reset the form after successful submission
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
    }
    
    // Initialize the form by loading settings
    window.loadSettings().then(() => {
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