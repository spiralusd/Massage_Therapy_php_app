/**
 * Debug Toggle Module for Massage Booking System
 * 
 * This module adds a debug toggle to the booking form when WP_DEBUG is enabled.
 * It helps developers troubleshoot issues with the booking form and API integration.
 */

(function() {
    'use strict';
    
    // Only run in development/debug mode
    if (typeof WP_DEBUG === 'undefined' || !WP_DEBUG) {
        return;
    }
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initDebugTools();
    });
    
    /**
     * Initialize debug tools
     */
    function initDebugTools() {
        // Create debug toggle container if it doesn't exist
        if (!document.getElementById('debug-controls')) {
            const debugControls = document.createElement('div');
            debugControls.id = 'debug-controls';
            debugControls.style.position = 'fixed';
            debugControls.style.bottom = '10px';
            debugControls.style.right = '10px';
            debugControls.style.background = '#f1f1f1';
            debugControls.style.padding = '10px';
            debugControls.style.borderRadius = '5px';
            debugControls.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
            debugControls.style.zIndex = '9999';
            
            // Add toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.id = 'toggleDebug';
            toggleBtn.className = 'button';
            toggleBtn.textContent = 'Toggle Debug Mode';
            debugControls.appendChild(toggleBtn);
            
            // Add debug info container
            const debugInfo = document.createElement('div');
            debugInfo.id = 'debugInfo';
            debugInfo.style.display = 'none';
            debugInfo.style.marginTop = '10px';
            debugInfo.style.maxHeight = '300px';
            debugInfo.style.overflowY = 'auto';
            debugInfo.style.fontFamily = 'monospace';
            debugInfo.style.fontSize = '12px';
            debugInfo.style.background = '#fff';
            debugInfo.style.padding = '10px';
            debugInfo.style.border = '1px solid #ddd';
            
            const debugTitle = document.createElement('h4');
            debugTitle.textContent = 'Debug Information';
            debugInfo.appendChild(debugTitle);
            
            const debugContent = document.createElement('div');
            debugContent.id = 'debugContent';
            debugInfo.appendChild(debugContent);
            
            debugControls.appendChild(debugInfo);
            
            // Add to page
            document.body.appendChild(debugControls);
            
            // Initialize toggle functionality
            initToggle();
        }
    }
    
    /**
     * Initialize toggle button functionality
     */
    function initToggle() {
        const toggleBtn = document.getElementById('toggleDebug');
        const debugInfo = document.getElementById('debugInfo');
        const debugContent = document.getElementById('debugContent');
        
        if (!toggleBtn || !debugInfo || !debugContent) {
            console.error('Debug elements not found');
            return;
        }
        
        let debugMode = false;
        
        // Toggle debug panel
        toggleBtn.addEventListener('click', function() {
            debugMode = !debugMode;
            debugInfo.style.display = debugMode ? 'block' : 'none';
            toggleBtn.textContent = debugMode ? 'Hide Debug Info' : 'Toggle Debug Mode';
            
            if (debugMode) {
                // Collect debug information
                collectDebugInfo();
                
                // Hook into API functions
                hookIntoAPIs();
            }
        });
    }
    
    /**
     * Collect debug information about form state
     */
    function collectDebugInfo() {
        const debugContent = document.getElementById('debugContent');
        if (!debugContent) return;
        
        const formState = {
            elements: {
                'appointmentForm': !!document.getElementById('appointmentForm'),
                'timeSlots': !!document.getElementById('timeSlots'),
                'bookingSummary': !!document.getElementById('bookingSummary'),
                'formElement': document.querySelector('form') ? document.querySelector('form').id : 'No form found'
            },
            scripts: {
                'jQuery': typeof jQuery !== 'undefined',
                'massageBookingAPI': typeof massageBookingAPI !== 'undefined',
                'fetchAvailableTimeSlots': typeof window.fetchAvailableTimeSlots === 'function',
                'updateSummary': typeof window.updateSummary === 'function'
            },
            fields: {},
            options: {},
            timeSlots: {}
        };
        
        // Get form field values
        const form = document.getElementById('appointmentForm');
        if (form) {
            form.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.id) {
                    formState.fields[el.id] = el.value;
                }
            });
            
            // Get selected radio/checkbox options
            form.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked').forEach(el => {
                formState.options[el.name] = el.value;
            });
        }
        
        // Get time slot information
        const selectedSlot = document.querySelector('.time-slot.selected');
        if (selectedSlot) {
            formState.timeSlots.selected = {
                time: selectedSlot.textContent,
                dataTime: selectedSlot.getAttribute('data-time'),
                dataEndTime: selectedSlot.getAttribute('data-end-time')
            };
        }
        
        // Show debug info
        debugContent.innerHTML = '<pre>' + JSON.stringify(formState, null, 2) + '</pre>';
        
        // Add session storage info
        try {
            const sessionData = sessionStorage.getItem('massageBookingFormData');
            if (sessionData) {
                const sessionInfo = JSON.parse(sessionData);
                debugContent.innerHTML += '<h4>Session Storage</h4><pre>' + JSON.stringify(sessionInfo, null, 2) + '</pre>';
            } else {
                debugContent.innerHTML += '<h4>Session Storage</h4><pre>No data in session storage</pre>';
            }
        } catch (e) {
            debugContent.innerHTML += '<h4>Session Storage</h4><pre>Error reading session storage: ' + e.message + '</pre>';
        }
    }
    
    /**
     * Hook into API functions to monitor activity
     */
    function hookIntoAPIs() {
        const debugContent = document.getElementById('debugContent');
        if (!debugContent) return;
        
        // Hook into time slot fetching
        if (typeof window.fetchAvailableTimeSlots === 'function') {
            const originalFetch = window.fetchAvailableTimeSlots;
            window.fetchAvailableTimeSlots = function(date, duration) {
                const eventEntry = document.createElement('div');
                eventEntry.innerHTML = `<strong>${new Date().toLocaleTimeString()}</strong>: Fetching slots for ${date}, duration ${duration}`;
                debugContent.appendChild(eventEntry);
                return originalFetch(date, duration);
            };
        }
        
        // Hook into form submission
        const form = document.getElementById('appointmentForm');
        if (form) {
            const originalSubmit = form.onsubmit;
            form.addEventListener('submit', function(e) {
                const eventEntry = document.createElement('div');
                eventEntry.innerHTML = `<strong>${new Date().toLocaleTimeString()}</strong>: Form submission intercepted`;
                debugContent.appendChild(eventEntry);
                
                // Don't interfere with the normal submission handling
                if (originalSubmit) {
                    return originalSubmit.call(this, e);
                }
            }, true);
        }
    }
    
    /**
     * Auto-recovery for common issues
     */
    function autoRecoverIssues() {
        // If form not found, try to find any form and assign the ID
        if (!document.getElementById('appointmentForm')) {
            console.warn('[Debug] Form not found - attempting auto-recovery');
            
            // Try to find any form
            const forms = document.querySelectorAll('form');
            if (forms.length > 0) {
                forms[0].id = 'appointmentForm';
                console.log('[Debug] Auto-recovery: ID assigned to found form', forms[0]);
            }
            
            // If no forms at all, try to create one
            if (forms.length === 0) {
                const containers = document.querySelectorAll('.massage-booking-container, .booking-container');
                if (containers.length > 0) {
                    console.log('[Debug] No form found at all. Creating a placeholder form');
                    
                    const placeholderForm = document.createElement('form');
                    placeholderForm.id = 'appointmentForm';
                    placeholderForm.innerHTML = '<p>Debug placeholder form - original form not found</p>';
                    containers[0].appendChild(placeholderForm);
                }
            }
        }
    }
    
    // Run auto-recovery after a short delay
    setTimeout(autoRecoverIssues, 1000);
})();