/**
 * Massage Booking API Troubleshooter
 * 
 * This script diagnoses issues with the API connector and attempts to fix
 * common problems. Add this to your page right before the closing </body> tag.
 */

(function() {
    'use strict';
    
    // Wait for page to fully load
    window.addEventListener('load', function() {
        console.log('API Troubleshooter running...');
        
        // Check if we're on a booking page
        if (!document.querySelector('.massage-booking-container') && 
            !document.querySelector('#appointmentForm') &&
            !document.querySelector('form.booking-form')) {
            console.log('Not on a booking page. Troubleshooter not needed.');
            return;
        }
        
        // Run diagnostics after a short delay
        setTimeout(runDiagnostics, 1000);
    });
    
    /**
     * Run diagnostics on the API and form
     */
    function runDiagnostics() {
        console.log('Running API diagnostics...');
        
        const issues = [];
        
        // Check for form
        const form = document.getElementById('appointmentForm');
        if (!form) {
            issues.push('Form with ID "appointmentForm" not found.');
            
            // Try to find and fix the form
            const possibleForms = document.querySelectorAll(
                'form.booking-form, ' + 
                '.massage-booking-container form, ' + 
                'form'
            );
            
            if (possibleForms.length > 0) {
                issues.push('Found a form without ID. Fixing...');
                possibleForms[0].id = 'appointmentForm';
            } else {
                issues.push('No forms found on the page!');
            }
        }
        
        // Check for massageBookingAPI object
        if (typeof massageBookingAPI === 'undefined') {
            issues.push('massageBookingAPI object is missing.');
            
            // Check if wp_localize_script was called
            if (document.body.textContent.includes('massage_booking_api_connector')) {
                issues.push('wp_localize_script may have failed.');
            }
            
            // Create fallback API configuration
            window.massageBookingAPI = {
                restUrl: '/wp-json/massage-booking/v1/',
                nonce: '',
                ajaxUrl: '/wp-admin/admin-ajax.php',
                isFallback: true
            };
            
            issues.push('Created fallback API configuration.');
        } else {
            // Check API object properties
            if (!massageBookingAPI.restUrl) {
                issues.push('massageBookingAPI.restUrl is missing.');
                massageBookingAPI.restUrl = '/wp-json/massage-booking/v1/';
            }
            
            if (!massageBookingAPI.ajaxUrl) {
                issues.push('massageBookingAPI.ajaxUrl is missing.');
                massageBookingAPI.ajaxUrl = '/wp-admin/admin-ajax.php';
            }
        }
        
        // Check for jQuery
        if (typeof jQuery === 'undefined') {
            issues.push('jQuery is not loaded.');
        }
        
        // Check for essential functions
        const requiredFunctions = [
            'fetchAvailableTimeSlots',
            'updateSummary',
            'loadSettings'
        ];
        
        requiredFunctions.forEach(function(funcName) {
            if (typeof window[funcName] !== 'function') {
                issues.push(`Function ${funcName} is missing.`);
                
                // Create placeholder for missing functions
                window[funcName] = window[funcName] || function() {
                    console.warn(`Placeholder for ${funcName} called.`);
                    return Promise.resolve({});
                };
            }
        });
        
        // Log diagnostic results
        if (issues.length > 0) {
            console.warn('API Troubleshooter found issues:');
            issues.forEach(issue => console.warn('- ' + issue));
            
            // Add visible message for admins
            if (isAdmin()) {
                showAdminMessage(issues);
            }
            
            // Try to fix the issues
            fixIssues();
        } else {
            console.log('API Troubleshooter: No issues found.');
        }
    }
    
    /**
     * Attempt to fix identified issues
     */
    function fixIssues() {
        console.log('Attempting to fix issues...');
        
        // Ensure the form has the correct ID
        const form = document.getElementById('appointmentForm') || 
                     document.querySelector('form.booking-form') || 
                     document.querySelector('.massage-booking-container form') ||
                     document.querySelector('form');
                     
        if (form && form.id !== 'appointmentForm') {
            form.id = 'appointmentForm';
            console.log('Form ID corrected to "appointmentForm"');
        }
        
        // If API connector is missing but jQuery is available, try to initialize
        if (typeof jQuery !== 'undefined') {
            // Simulate the API connector initialization
            if (!window._apiConnectorInitialized && form) {
                window._apiConnectorInitialized = true;
                
                // Try to load settings
                if (typeof window.loadSettings === 'function') {
                    window.loadSettings().catch(error => {
                        console.warn('Failed to load settings:', error);
                    });
                }
                
                console.log('Initialized API connector manually');
            }
        }
        
        // If date picker exists, make sure it has change event handler
        const datePicker = document.getElementById('appointmentDate');
        if (datePicker) {
            datePicker.removeEventListener('change', dateChangeHandler);
            datePicker.addEventListener('change', dateChangeHandler);
            console.log('Date picker event handler fixed');
        }
    }
    
    /**
     * Date change event handler
     */
    function dateChangeHandler() {
        if (this.value && typeof window.fetchAvailableTimeSlots === 'function') {
            const duration = document.querySelector('input[name="duration"]:checked')?.value || '60';
            window.fetchAvailableTimeSlots(this.value, duration).catch(error => {
                console.warn('Error fetching time slots:', error);
            });
        }
    }
    
    /**
     * Check if current user appears to be an admin
     */
    function isAdmin() {
        return document.body.classList.contains('logged-in') && 
               (document.body.classList.contains('admin-bar') || 
                document.getElementById('wpadminbar'));
    }
    
    /**
     * Show admin message about issues
     */
    function showAdminMessage(issues) {
        // Create message container
        const messageContainer = document.createElement('div');
        messageContainer.style.position = 'fixed';
        messageContainer.style.top = '32px';
        messageContainer.style.right = '10px';
        messageContainer.style.padding = '15px';
        messageContainer.style.background = '#f8d7da';
        messageContainer.style.border = '1px solid #f5c6cb';
        messageContainer.style.borderRadius = '4px';
        messageContainer.style.maxWidth = '300px';
        messageContainer.style.zIndex = '9999';
        messageContainer.style.fontSize = '12px';
        
        // Add message content
        messageContainer.innerHTML = `
            <h4 style="margin-top:0;">Massage Booking API Issues</h4>
            <p>The following issues were detected:</p>
            <ul style="padding-left:20px;margin-bottom:10px;">
                ${issues.map(issue => `<li>${issue}</li>`).join('')}
            </ul>
            <p>Auto-fixing has been attempted. Check console for details.</p>
            <button id="dismiss-api-message" style="padding:5px 10px;margin-top:10px;">Dismiss</button>
        `;
        
        // Add to page
        document.body.appendChild(messageContainer);
        
        // Add dismiss handler
        document.getElementById('dismiss-api-message').addEventListener('click', function() {
            messageContainer.remove();
        });
        
        // Auto-dismiss after 30 seconds
        setTimeout(function() {
            if (document.body.contains(messageContainer)) {
                messageContainer.remove();
            }
        }, 30000);
    }
})();
