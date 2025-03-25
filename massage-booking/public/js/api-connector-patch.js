/**
 * API Connector Compatibility Patch
 * 
 * This patch addresses issues with API connector initialization and
 * event handling across different browsers.
 * 
 * How to use: Include this script after api-connector.js but before the
 * closing </body> tag on your booking page.
 */

(function() {
    'use strict';
    
    // Check for proper initialization on page load
    window.addEventListener('load', function() {
        // Only run on booking pages
        if (!document.querySelector('.massage-booking-container') && 
            !document.querySelector('.booking-container') && 
            !document.getElementById('appointmentForm')) {
            return;
        }
        
        console.log('API Connector patch loaded');
        
        // Listen for custom events from the cross-browser compatibility script
        document.addEventListener('mb_form_initialized', function(event) {
            console.log('Form initialized event received');
            initializeApi(event.detail.form);
        });
        
        document.addEventListener('mb_reinitialize_api', function(event) {
            console.log('API reinitialization requested');
            initializeApi(event.detail.form);
        });
        
        // Check if API is properly initialized
        checkApiInitialization();
    });
    
    /**
     * Initialize the API connector with the form
     */
    function initializeApi(form) {
        if (!form) return;
        
        // Only initialize if not already done
        if (window._apiConnectorInitialized) return;
        
        // Check if WordPress API data exists
        if (typeof massageBookingAPI === 'undefined') {
            console.error('WordPress API data not available');
            
            // Try to create a fallback
            window.massageBookingAPI = window.massageBookingAPI || {
                restUrl: '/wp-json/massage-booking/v1/',
                nonce: '',
                ajaxUrl: '/wp-admin/admin-ajax.php'
            };
            
            console.log('Created fallback API configuration');
            return;
        }
        
        // Mark as initialized
        window._apiConnectorInitialized = true;
        
        console.log('API connector manually initialized');
        
        // Attempt to load settings if the function exists
        if (typeof window.loadSettings === 'function') {
            window.loadSettings()
                .then(function() {
                    console.log('Settings loaded successfully');
                    
                    // Attempt to restore any form state
                    restoreFormState();
                })
                .catch(function(error) {
                    console.error('Failed to load settings:', error);
                });
        }
    }
    
    /**
     * Check if the API is properly initialized
     */
    function checkApiInitialization() {
        // Verify essential API functions
        setTimeout(function() {
            if (!window._apiConnectorInitialized) {
                // Check for form
                const form = document.getElementById('appointmentForm');
                if (!form) {
                    console.warn('No form found with ID appointmentForm');
                    
                    // Try to find and set the form ID
                    const possibleForms = document.querySelectorAll(
                        'form.booking-form, ' + 
                        '.massage-booking-container form, ' + 
                        '.booking-form-container form, ' + 
                        'form'
                    );
                    
                    if (possibleForms.length > 0) {
                        possibleForms[0].id = 'appointmentForm';
                        console.log('Form ID assigned to:', possibleForms[0]);
                        initializeApi(possibleForms[0]);
                    }
                    
                    return;
                }
                
                // Try to re-initialize
                if (typeof jQuery !== 'undefined') {
                    console.log('Attempting auto-initialization of API connector');
                    initializeApi(form);
                }
            }
            
            // Patch the time slot fetching function if it's still the placeholder
            if (typeof window.fetchAvailableTimeSlots === 'function' && 
                window.fetchAvailableTimeSlots.toString().includes('placeholder')) {
                console.log('Patching fetchAvailableTimeSlots function');
                patchTimeSlotFunction();
            }
        }, 1500);
    }
    
    /**
     * Patch the time slot function with a more resilient implementation
     */
    function patchTimeSlotFunction() {
        // Only patch if necessary
        if (window._timeSlotFunctionPatched) return;
        
        // Override with our implementation
        window.fetchAvailableTimeSlots = async function(date, duration) {
            console.log(`Patched function fetching time slots for ${date} with duration ${duration}`);
            
            const slotsContainer = document.getElementById('timeSlots');
            if (!slotsContainer) return { available: false, slots: [] };
            
            slotsContainer.innerHTML = '<p>Loading available times...</p>';
            slotsContainer.classList.add('loading');
            
            try {
                // Try to make the API request
                if (typeof massageBookingAPI === 'undefined') {
                    throw new Error('API configuration not available');
                }
                
                const timeSlotsUrl = `${massageBookingAPI.restUrl}available-slots?date=${encodeURIComponent(date)}&duration=${encodeURIComponent(duration)}`;
                
                const response = await fetch(timeSlotsUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': massageBookingAPI.nonce,
                        'Accept': 'application/json'
                    }
                });
                
                // Check for response issues
                if (!response.ok) {
                    throw new Error(`HTTP error: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    // Try to extract JSON from potential HTML response
                    const text = await response.text();
                    const jsonMatch = text.match(/\{.*\}/s);
                    if (jsonMatch) {
                        data = JSON.parse(jsonMatch[0]);
                    } else {
                        throw new Error('Invalid JSON response');
                    }
                }
                
                // Update the UI
                updateTimeSlots(slotsContainer, data);
                return data;
            } catch (error) {
                console.error('Error fetching time slots:', error);
                slotsContainer.classList.remove('loading');
                slotsContainer.innerHTML = `
                    <p>Error loading available times. Please try again.</p>
                    <button type="button" class="retry-button">Retry</button>
                `;
                
                // Add retry functionality
                const retryButton = slotsContainer.querySelector('.retry-button');
                if (retryButton) {
                    retryButton.addEventListener('click', function() {
                        window.fetchAvailableTimeSlots(date, duration);
                    });
                }
                
                return { available: false, slots: [] };
            }
        };
        
        window._timeSlotFunctionPatched = true;
    }
    
    /**
     * Update time slots display
     */
    function updateTimeSlots(container, data) {
        container.innerHTML = '';
        container.classList.remove('loading');
        
        // Check if slots are available
        if (!data || !data.available || !data.slots || data.slots.length === 0) {
            container.innerHTML = '<p>No appointments available on this date.</p>';
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
            
            // Add click event to select this time slot
            slotElement.addEventListener('click', function() {
                // Clear previous selections
                document.querySelectorAll('.time-slot').forEach(s => {
                    s.classList.remove('selected');
                    s.setAttribute('aria-selected', 'false');
                });
                
                // Select this slot
                this.classList.add('selected');
                this.setAttribute('aria-selected', 'true');
                
                // Update booking summary
                if (typeof window.updateSummary === 'function') {
                    window.updateSummary();
                }
                
                // Save to session storage
                saveTimeSlotSelection(slot.startTime, slot.endTime);
            });
            
            // Make the slot keyboard accessible
            slotElement.setAttribute('tabindex', '0');
            slotElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
            
            container.appendChild(slotElement);
        });
    }
    
    /**
     * Save time slot selection to session storage
     */
    function saveTimeSlotSelection(startTime, endTime) {
        try {
            const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
            formData.selectedTimeSlot = startTime;
            formData.selectedEndTime = endTime;
            sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
        } catch (e) {
            console.warn('Could not save to session storage:', e);
        }
    }
    
    /**
     * Restore form state from session storage
     */
    function restoreFormState() {
        try {
            const savedData = sessionStorage.getItem('massageBookingFormData');
            if (!savedData) return;
            
            const formData = JSON.parse(savedData);
            
            // Restore date if it was selected
            if (formData.appointmentDate) {
                const dateInput = document.getElementById('appointmentDate');
                if (dateInput) {
                    dateInput.value = formData.appointmentDate;
                    
                    // Trigger date input event to load time slots
                    const event = new Event('change', { bubbles: true });
                    dateInput.dispatchEvent(event);
                }
            }
            
            // Restore other fields will be handled by the main script
        } catch (e) {
            console.warn('Failed to restore form state:', e);
        }
    }
})();