/**
 * API Connector Fix for Massage Booking Plugin
 * 
 * This script fixes issues with the API connector that may cause 400 errors:
 * 1. Improves nonce handling
 * 2. Fixes time slot fetching
 * 3. Enhances error reporting
 * 4. Ensures cross-browser compatibility
 * 
 * Save as: public/js/api-connector-fix.js
 * Enqueue after api-connector.js in massage-booking.php
 */

(function($) {
    'use strict';
    
    console.log('API Connector Fix loading...');
    
    // Check if window.massageBookingAPI exists
    if (typeof window.massageBookingAPI === 'undefined') {
        console.error('API Connector Fix: massageBookingAPI is undefined');
        // Create fallback API configuration
        window.massageBookingAPI = {
            restUrl: '/wp-json/massage-booking/v1/',
            nonce: '',
            ajaxUrl: '/wp-admin/admin-ajax.php',
            siteUrl: window.location.origin,
            isFallback: true
        };
    }
    
    // Fix time slot fetching function
    const originalFetchTimeSlots = window.fetchAvailableTimeSlots;
    
    // Override fetchAvailableTimeSlots with a more robust implementation
    window.fetchAvailableTimeSlots = async function(date, duration) {
        console.log(`API Connector Fix: Fetching time slots for ${date} with duration ${duration}`);
        
        const slotsContainer = document.getElementById('timeSlots');
        if (!slotsContainer) {
            console.error('API Connector Fix: Time slots container not found');
            return { available: false, slots: [] };
        }
        
        // Show loading state
        slotsContainer.innerHTML = '<p>Loading available times...</p>';
        slotsContainer.classList.add('loading');
        
        try {
            // First try to use the original function if it's available and not a placeholder
            if (typeof originalFetchTimeSlots === 'function' && 
                !originalFetchTimeSlots.toString().includes('placeholder')) {
                try {
                    const result = await originalFetchTimeSlots(date, duration);
                    // If original function works, return its result
                    return result;
                } catch (e) {
                    console.warn('API Connector Fix: Original fetch function failed, using fixed version', e);
                    // Continue with our implementation
                }
            }
            
            // Implementation with better error handling
            const timeSlotsUrl = `${massageBookingAPI.restUrl}available-slots?date=${encodeURIComponent(date)}&duration=${encodeURIComponent(duration)}`;
            
            // Add better error handling for fetch
            const response = await fetch(timeSlotsUrl, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': massageBookingAPI.nonce,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                cache: 'no-cache' // Prevent caching issues
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }
            
            // Parse JSON with better error handling
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
                    throw new Error('Invalid JSON response from server');
                }
            }
            
            // Update the UI with the fetched slots
            updateTimeSlots(slotsContainer, data);
            
            return data;
        } catch (error) {
            console.error('API Connector Fix: Error fetching time slots:', error);
            
            // Show error in time slots container
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
    
    /**
     * Update the time slots UI with better rendering
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
                try {
                    const formData = JSON.parse(sessionStorage.getItem('massageBookingFormData') || '{}');
                    formData.selectedTimeSlot = slot.startTime;
                    formData.selectedEndTime = slot.endTime;
                    sessionStorage.setItem('massageBookingFormData', JSON.stringify(formData));
                } catch (e) {
                    console.warn('API Connector Fix: Could not save to session storage:', e);
                }
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
     * Fix the loadSettings function if it exists
     */
    if (typeof window.loadSettings === 'function') {
        const originalLoadSettings = window.loadSettings;
        window.loadSettings = async function() {
            console.log('API Connector Fix: Loading settings');
            
            try {
                // Try to use the original function
                return await originalLoadSettings();
            } catch (error) {
                console.warn('API Connector Fix: Original loadSettings failed, using fixed version', error);
                
                try {
                    // Our implementation with better error handling
                    const response = await fetch(massageBookingAPI.restUrl + 'settings', {
                        method: 'GET',
                        headers: {
                            'X-WP-Nonce': massageBookingAPI.nonce,
                            'Accept': 'application/json'
                        },
                        cache: 'no-cache'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Failed to load settings (HTTP ${response.status})`);
                    }
                    
                    const settings = await response.json();
                    console.log('API Connector Fix: Settings loaded from WordPress:', settings);
                    
                    // Apply settings to form elements
                    applySettingsToForm(settings);
                    
                    return settings;
                } catch (e) {
                    console.error('API Connector Fix: Failed to load settings', e);
                    return {};
                }
            }
        };
    }
    
    /**
     * Apply settings to form elements
     */
    function applySettingsToForm(settings) {
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
    }
    
    /**
     * Ensure updateSummary function is available
     */
    if (typeof window.updateSummary !== 'function') {
        window.updateSummary = function() {
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
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('API Connector Fix: Document ready');
        
        // Run after a short delay to ensure the form is present
        setTimeout(function() {
            // Check if the form exists
            const form = document.getElementById('appointmentForm');
            if (!form) {
                console.warn('API Connector Fix: Form not found, attempting to locate it');
                
                // Try to find and fix the form
                const possibleForms = document.querySelectorAll(
                    'form.booking-form, ' + 
                    '.massage-booking-container form, ' + 
                    'form'
                );
                
                if (possibleForms.length > 0) {
                    console.log('API Connector Fix: Found a form without ID. Fixing...');
                    possibleForms[0].id = 'appointmentForm';
                }
            }
            
            // Try to load settings to initialize the form
            if (typeof window.loadSettings === 'function') {
                console.log('API Connector Fix: Loading settings');
                window.loadSettings().catch(error => {
                    console.error('API Connector Fix: Failed to load settings:', error);
                });
            }
            
            // Restore form state from session storage
            try {
                const savedData = sessionStorage.getItem('massageBookingFormData');
                if (savedData) {
                    console.log('API Connector Fix: Restoring form state from session storage');
                    restoreFormState(JSON.parse(savedData));
                }
            } catch (e) {
                console.warn('API Connector Fix: Failed to restore form state:', e);
            }
        }, 500);
    });
    
    /**
     * Restore form state from session storage
     */
    function restoreFormState(formData) {
        if (!formData) return;
        
        // Restore date input
        if (formData.appointmentDate) {
            const dateInput = document.getElementById('appointmentDate');
            if (dateInput) {
                dateInput.value = formData.appointmentDate;
                
                // Trigger date input event to load time slots
                setTimeout(() => {
                    const event = new Event('change', { bubbles: true });
                    dateInput.dispatchEvent(event);
                    
                    // After slots are loaded, restore selected time slot
                    if (formData.selectedTimeSlot) {
                        setTimeout(() => {
                            const slot = document.querySelector(`.time-slot[data-time="${formData.selectedTimeSlot}"]`);
                            if (slot) {
                                slot.click();
                            }
                        }, 500);
                    }
                }, 100);
            }
        }
        
        // Restore duration selection
        if (formData.duration) {
            const durationInput = document.querySelector(`input[name="duration"][value="${formData.duration}"]`);
            if (durationInput && !durationInput.checked) {
                durationInput.checked = true;
                const radioOption = durationInput.closest('.radio-option');
                if (radioOption) {
                    document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                    radioOption.classList.add('selected');
                }
            }
        }
        
        // Restore text inputs
        ['fullName', 'email', 'phone', 'specialRequests'].forEach(field => {
            if (formData[field]) {
                const input = document.getElementById(field);
                if (input) {
                    input.value = formData[field];
                }
            }
        });
    }
    
    console.log('API Connector Fix loaded successfully');
    
})(jQuery);