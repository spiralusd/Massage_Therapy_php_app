/**
 * Cross-Browser Compatibility Fix for Massage Booking Plugin
 * 
 * This script normalizes behavior between Firefox and Chrome (incognito) windows
 * by addressing common issues with form initialization, DOM manipulation,
 * and styling inconsistencies.
 * 
 * Usage: Add this script to your theme or plugin, and enqueue it after the main
 * booking form scripts but before the page renders.
 */

(function() {
    'use strict';
    
    // Run when DOM is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on a booking page
        if (!document.querySelector('.massage-booking-container') && 
            !document.querySelector('.booking-container') &&
            !document.querySelector('#appointmentForm')) {
            return;
        }
        
        console.log('Cross-browser compatibility fix loaded');
        
        // Fix 1: Ensure the form has the correct ID
        ensureFormId();
        
        // Fix 2: Normalize date input behavior
        normalizeDateInput();
        
        // Fix 3: Fix radio and checkbox selection states
        fixSelectionStates();
        
        // Fix 4: Ensure consistent CSS application
        enforceCssConsistency();
        
        // Fix 5: Handle session storage differences
        normalizeSessionStorage();
        
        // Fix 6: Add focus state visual indicators 
        improveFocusStates();
        
        // Fix 7: Ensure proper initialization
        ensureApiInitialization();
    });
    
    /**
     * Find the booking form and ensure it has the proper ID
     * This is critical because many scripts look for #appointmentForm
     */
    function ensureFormId() {
        const possibleForms = document.querySelectorAll(
            'form.booking-form, ' + 
            '.massage-booking-container form, ' + 
            '.booking-form-container form, ' + 
            '.wp-block-shortcode form, ' + 
            'div.entry-content form, ' + 
            'article form'
        );
        
        if (possibleForms.length > 0 && !document.getElementById('appointmentForm')) {
            console.log('Assigning ID to form');
            possibleForms[0].id = 'appointmentForm';
            
            // Dispatch an event to notify other scripts
            dispatchFormFoundEvent(possibleForms[0]);
        }
    }
    
    /**
     * Notify other scripts that the form was found and assigned an ID
     */
    function dispatchFormFoundEvent(form) {
        const event = new CustomEvent('mb_form_initialized', {
            detail: { form: form },
            bubbles: true
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Normalize the behavior of date input across browsers
     */
    function normalizeDateInput() {
        const dateInput = document.getElementById('appointmentDate');
        if (!dateInput) return;
        
        // Ensure date format is consistent
        dateInput.setAttribute('autocomplete', 'off');
        
        // Explicitly add a change handler for Firefox
        dateInput.addEventListener('change', function(e) {
            // This explicit blur will help trigger validation in Firefox
            this.blur();
            
            // Re-check available days
            const availableDaysStr = this.getAttribute('data-available-days');
            if (availableDaysStr) {
                const availableDays = availableDaysStr.split(',').map(Number);
                const selectedDate = new Date(this.value);
                const dayOfWeek = selectedDate.getDay();
                
                if (!availableDays.includes(dayOfWeek)) {
                    alert('Sorry, appointments are not available on this day. Please select a different date.');
                    this.value = '';
                    return;
                }
            }
            
            // Trigger a custom change event to ensure all handlers run
            const changeEvent = new Event('dateChanged', { bubbles: true });
            this.dispatchEvent(changeEvent);
        });
    }
    
    /**
     * Fix radio and checkbox selection states across browsers
     */
    function fixSelectionStates() {
        // Fix radio buttons
        document.querySelectorAll('.radio-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                option.classList.add('selected');
            }
            
            // Improve event handling
            option.addEventListener('click', function() {
                document.querySelectorAll('.radio-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                this.classList.add('selected');
                
                const radioInput = this.querySelector('input[type="radio"]');
                if (radioInput) {
                    radioInput.checked = true;
                    
                    // Create and dispatch change event
                    const changeEvent = new Event('change', { bubbles: true });
                    radioInput.dispatchEvent(changeEvent);
                }
            });
        });
        
        // Fix checkboxes
        document.querySelectorAll('.checkbox-option').forEach(option => {
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.checked) {
                option.classList.add('selected');
            }
            
            // Improve event handling
            option.addEventListener('click', function() {
                const checkboxInput = this.querySelector('input[type="checkbox"]');
                if (checkboxInput) {
                    checkboxInput.checked = !checkboxInput.checked;
                    this.classList.toggle('selected');
                    
                    // Create and dispatch change event
                    const changeEvent = new Event('change', { bubbles: true });
                    checkboxInput.dispatchEvent(changeEvent);
                }
            });
        });
    }
    
    /**
     * Enforce CSS consistency across browsers
     */
    function enforceCssConsistency() {
        // Add critical CSS rules as inline styles to ensure they're applied
        const criticalStyles = `
            .radio-option.selected, .checkbox-option.selected {
                border-color: #4a6fa5 !important;
                background-color: rgba(74, 111, 165, 0.1) !important;
            }
            
            .time-slot.selected {
                background-color: #4a6fa5 !important;
                color: white !important;
                border-color: #4a6fa5 !important;
            }
            
            .summary.visible {
                display: block !important;
            }
            
            input:invalid,
            textarea:invalid,
            select:invalid,
            input[aria-invalid="true"],
            textarea[aria-invalid="true"],
            select[aria-invalid="true"] {
                border-color: #dc3545 !important;
            }
        `;
        
        // Add the style element if it doesn't exist
        if (!document.getElementById('mb-critical-css')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'mb-critical-css';
            styleEl.type = 'text/css';
            styleEl.appendChild(document.createTextNode(criticalStyles));
            document.head.appendChild(styleEl);
        }
    }
    
    /**
     * Handle session storage differences between browsers
     */
    function normalizeSessionStorage() {
        // Create a wrapper for sessionStorage to handle exceptions
        window.mbSessionStorage = {
            getItem: function(key) {
                try {
                    return sessionStorage.getItem(key);
                } catch (e) {
                    console.warn('Session storage access failed:', e);
                    return null;
                }
            },
            
            setItem: function(key, value) {
                try {
                    sessionStorage.setItem(key, value);
                    return true;
                } catch (e) {
                    console.warn('Session storage write failed:', e);
                    return false;
                }
            },
            
            removeItem: function(key) {
                try {
                    sessionStorage.removeItem(key);
                    return true;
                } catch (e) {
                    console.warn('Session storage removal failed:', e);
                    return false;
                }
            }
        };
        
        // Load saved form state
        function loadFormState() {
            const savedData = window.mbSessionStorage.getItem('massageBookingFormData');
            if (!savedData) return;
            
            try {
                const formData = JSON.parse(savedData);
                
                // Apply saved data to form fields
                for (const key in formData) {
                    const element = document.getElementById(key);
                    if (element && element.type !== 'radio' && element.type !== 'checkbox') {
                        element.value = formData[key];
                    }
                }
                
                // Handle radio buttons
                if (formData.duration) {
                    const radio = document.querySelector(`input[name="duration"][value="${formData.duration}"]`);
                    if (radio) {
                        radio.checked = true;
                        const option = radio.closest('.radio-option');
                        if (option) {
                            document.querySelectorAll('.radio-option').forEach(o => o.classList.remove('selected'));
                            option.classList.add('selected');
                        }
                    }
                }
                
                // Handle checkboxes
                if (formData.focus && Array.isArray(formData.focus)) {
                    formData.focus.forEach(value => {
                        const checkbox = document.querySelector(`input[name="focus"][value="${value}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            const option = checkbox.closest('.checkbox-option');
                            if (option) option.classList.add('selected');
                        }
                    });
                }
            } catch (e) {
                console.warn('Failed to restore form state:', e);
            }
        }
        
        // Try to load form state now
        loadFormState();
    }
    
    /**
     * Improve focus states for better usability
     */
    function improveFocusStates() {
        const focusStyle = `
            input:focus, select:focus, textarea:focus, button:focus,
            .radio-option:focus, .checkbox-option:focus, .time-slot:focus {
                outline: 2px solid #4a6fa5 !important;
                outline-offset: 2px !important;
            }
        `;
        
        if (!document.getElementById('mb-focus-styles')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'mb-focus-styles';
            styleEl.appendChild(document.createTextNode(focusStyle));
            document.head.appendChild(styleEl);
        }
        
        // Make radio options and checkboxes focusable
        document.querySelectorAll('.radio-option, .checkbox-option').forEach(option => {
            option.setAttribute('tabindex', '0');
            option.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    }
    
    /**
     * Ensure proper API initialization
     */
    function ensureApiInitialization() {
        // Only proceed if we're on a booking page with a form
        if (!document.getElementById('appointmentForm')) return;
        
        // Check if the API connector is initialized
        setTimeout(() => {
            if (typeof window.fetchAvailableTimeSlots === 'function' && 
                window.fetchAvailableTimeSlots.toString().includes('placeholder')) {
                console.warn('API connector not properly initialized. Trying to load fallback.');
                
                // Try to force initialization of API connector
                if (typeof jQuery !== 'undefined' && typeof massageBookingAPI !== 'undefined') {
                    console.log('Attempting to reinitialize API connector with jQuery');
                    
                    // Only try to reinitialize if it looks like the connector hasn't already been initialized
                    if (!window._apiConnectorInitialized) {
                        jQuery(document).ready(() => {
                            const form = document.getElementById('appointmentForm');
                            if (form) {
                                window._apiConnectorInitialized = true;
                                // Dispatch a custom event to trigger API connector initialization
                                const event = new CustomEvent('mb_reinitialize_api', { detail: { form: form } });
                                document.dispatchEvent(event);
                            }
                        });
                    }
                }
            }
        }, 1000); // Give time for normal initialization first
    }
    
    // Add a window load event handler to perform final fixes
    window.addEventListener('load', function() {
        // Force a check of selected items to ensure UI state matches
        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const option = radio.closest('.radio-option');
            if (option) option.classList.add('selected');
        });
        
        document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
            const option = checkbox.closest('.checkbox-option');
            if (option) option.classList.add('selected');
        });
        
        // Check if time slots need loading
        const dateInput = document.getElementById('appointmentDate');
        const timeSlots = document.getElementById('timeSlots');
        
        if (dateInput && dateInput.value && timeSlots && 
            timeSlots.textContent.includes('select a date')) {
            // Try to load time slots
            setTimeout(() => {
                const duration = document.querySelector('input[name="duration"]:checked')?.value || '60';
                if (typeof window.fetchAvailableTimeSlots === 'function') {
                    console.log('Forcing time slot refresh for date:', dateInput.value);
                    window.fetchAvailableTimeSlots(dateInput.value, duration);
                }
            }, 500);
        }
    });
})();
