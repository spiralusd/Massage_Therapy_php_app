/**
 * Massage Booking API Diagnostics Tool
 * 
 * This script provides diagnostic capabilities for the Massage Booking API
 * to help troubleshoot common issues with the booking form and API integration.
 * 
 * Version: 1.1.0
 * 
 * Usage: Add this script to your theme or plugin and enqueue it after the main
 * API connector scripts.
 */

(function($) {
    'use strict';
    
    // Run diagnostics when page loads
    $(document).ready(function() {
        console.log('API Diagnostics Tool: Initializing...');
        
        // Only run on booking pages
        if (!isBookingPage()) {
            console.log('API Diagnostics Tool: Not a booking page, diagnostics skipped.');
            return;
        }
        
        // Add diagnostics button for admins
        if (isAdmin()) {
            addDiagnosticsButton();
        }
        
        // Run background checks
        runBackgroundChecks();
    });
    
    /**
     * Check if current page has a booking form
     */
    function isBookingPage() {
        return (
            document.querySelector('.massage-booking-container') !== null ||
            document.querySelector('#appointmentForm') !== null ||
            document.querySelector('form.booking-form') !== null
        );
    }
    
    /**
     * Check if current user appears to be an admin
     */
    function isAdmin() {
        return (
            document.body.classList.contains('logged-in') && 
            (document.body.classList.contains('admin-bar') || 
             document.getElementById('wpadminbar') !== null)
        );
    }
    
    /**
     * Add diagnostics button for admin users
     */
    function addDiagnosticsButton() {
        const button = document.createElement('button');
        button.id = 'mb-run-diagnostics';
        button.textContent = 'Run API Diagnostics';
        button.style.position = 'fixed';
        button.style.bottom = '10px';
        button.style.right = '10px';
        button.style.zIndex = '9999';
        button.style.background = '#4a6fa5';
        button.style.color = 'white';
        button.style.border = 'none';
        button.style.borderRadius = '4px';
        button.style.padding = '8px 12px';
        button.style.cursor = 'pointer';
        button.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        
        // Add click event
        button.addEventListener('click', function() {
            runDiagnostics(true);
        });
        
        document.body.appendChild(button);
    }
    
    /**
     * Run background checks without UI
     */
    function runBackgroundChecks() {
        runDiagnostics(false);
    }
    
    /**
     * Run full diagnostics
     */
    function runDiagnostics(showResults) {
        console.log('API Diagnostics Tool: Running diagnostics...');
        
        const results = {
            timestamp: new Date().toISOString(),
            environment: getEnvironmentInfo(),
            form: checkFormStructure(),
            api: checkApiConfiguration(),
            functions: checkCoreFunctions(),
            events: checkEventHandlers(),
            storage: checkSessionStorage()
        };
        
        // Log results to console
        console.log('API Diagnostics Tool: Results', results);
        
        // Auto-fix common issues
        const fixes = applyAutoFixes(results);
        
        // Show results to admin if requested
        if (showResults) {
            displayResults(results, fixes);
        }
        
        return results;
    }
    
    /**
     * Get environment information
     */
    function getEnvironmentInfo() {
        return {
            userAgent: navigator.userAgent,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            jquery: (typeof $ !== 'undefined') ? $.fn.jquery : 'Not available',
            wpDebug: (typeof massageBookingAPI !== 'undefined' && massageBookingAPI.wpDebug) ? 
                massageBookingAPI.wpDebug : 'Unknown'
        };
    }
    
    /**
     * Check form structure
     */
    function checkFormStructure() {
        const formElement = document.getElementById('appointmentForm');
        
        const formResults = {
            formExists: formElement !== null,
            formId: formElement ? formElement.id : null,
            requiredElements: {}
        };
        
        // Check for required form elements
        const requiredElements = [
            'fullName',
            'email',
            'phone',
            'appointmentDate',
            'pressurePreference',
            'specialRequests',
            'timeSlots',
            'bookingSummary'
        ];
        
        requiredElements.forEach(elementId => {
            const element = document.getElementById(elementId);
            formResults.requiredElements[elementId] = element !== null;
        });
        
        // Check for radio and checkbox groups
        formResults.radioGroup = document.querySelector('.radio-group') !== null;
        formResults.checkboxGroup = document.querySelector('.checkbox-group') !== null;
        
        return formResults;
    }
    
    /**
     * Check API configuration
     */
    function checkApiConfiguration() {
        const apiResults = {
            apiObjectExists: typeof massageBookingAPI !== 'undefined',
            config: {}
        };
        
        if (apiResults.apiObjectExists) {
            // Check required API properties
            const requiredProps = [
                'restUrl',
                'nonce',
                'ajaxUrl',
                'siteUrl',
                'formAction'
            ];
            
            requiredProps.forEach(prop => {
                apiResults.config[prop] = {
                    exists: typeof massageBookingAPI[prop] !== 'undefined',
                    value: typeof massageBookingAPI[prop] !== 'undefined' ? 
                        (prop === 'nonce' ? '[REDACTED]' : massageBookingAPI[prop]) : null
                };
            });
            
            // Check if the API is a fallback
            apiResults.isFallback = massageBookingAPI.isFallback === true;
        }
        
        return apiResults;
    }
    
    /**
     * Check core functions
     */
    function checkCoreFunctions() {
        const functionsResults = {
            loadSettings: typeof window.loadSettings === 'function',
            fetchAvailableTimeSlots: typeof window.fetchAvailableTimeSlots === 'function',
            updateSummary: typeof window.updateSummary === 'function',
            validateForm: typeof window.validateForm === 'function',
            resetForm: typeof window.resetForm === 'function'
        };
        
        // Check if fetchAvailableTimeSlots is a placeholder
        if (functionsResults.fetchAvailableTimeSlots) {
            functionsResults.isPlaceholderFunction = 
                window.fetchAvailableTimeSlots.toString().includes('placeholder');
        }
        
        // Check if API connector is initialized
        functionsResults.apiConnectorInitialized = window._apiConnectorInitialized === true;
        
        return functionsResults;
    }
    
    /**
     * Check event handlers
     */
    function checkEventHandlers() {
        const eventsResults = {
            dateInput: false,
            formSubmit: false,
            radioOptions: false,
            checkboxOptions: false
        };
        
        // Try to detect attached event handlers
        const dateInput = document.getElementById('appointmentDate');
        if (dateInput) {
            // jQuery events
            if (typeof $ !== 'undefined' && typeof $.fn.jquery !== 'undefined') {
                const events = $._data(dateInput, 'events');
                eventsResults.dateInput = events && (events.change || events.input);
            }
            
            // DOM events - difficult to detect but we can check for properties
            eventsResults.dateInput = eventsResults.dateInput || 
                (dateInput.onchange !== null || dateInput.oninput !== null);
        }
        
        // Check form submit handler
        const form = document.getElementById('appointmentForm');
        if (form) {
            if (typeof $ !== 'undefined' && typeof $.fn.jquery !== 'undefined') {
                const events = $._data(form, 'events');
                eventsResults.formSubmit = events && events.submit;
            }
            
            eventsResults.formSubmit = eventsResults.formSubmit || form.onsubmit !== null;
        }
        
        return eventsResults;
    }
    
    /**
     * Check session storage
     */
    function checkSessionStorage() {
        const storageResults = {
            available: false,
            formDataExists: false,
            formData: null
        };
        
        try {
            // Test if sessionStorage is available
            sessionStorage.setItem('test', 'test');
            sessionStorage.removeItem('test');
            storageResults.available = true;
            
            // Check for form data
            const formData = sessionStorage.getItem('massageBookingFormData');
            storageResults.formDataExists = formData !== null;
            
            if (storageResults.formDataExists) {
                try {
                    storageResults.formData = JSON.parse(formData);
                } catch (e) {
                    storageResults.parseError = e.message;
                }
            }
        } catch (e) {
            storageResults.error = e.message;
        }
        
        return storageResults;
    }
    
    /**
     * Apply auto fixes for common issues
     */
    function applyAutoFixes(results) {
        const fixes = {
            applied: [],
            skipped: []
        };
        
        // Fix 1: Form ID
        if (!results.form.formExists) {
            const possibleForms = document.querySelectorAll(
                'form.booking-form, ' + 
                '.massage-booking-container form, ' + 
                'form'
            );
            
            if (possibleForms.length > 0) {
                possibleForms[0].id = 'appointmentForm';
                fixes.applied.push('Added missing ID "appointmentForm" to form element');
            } else {
                fixes.skipped.push('No form found to fix missing form ID');
            }
        }
        
        // Fix 2: API object
        if (!results.api.apiObjectExists) {
            window.massageBookingAPI = {
                restUrl: '/wp-json/massage-booking/v1/',
                nonce: '',
                ajaxUrl: '/wp-admin/admin-ajax.php',
                siteUrl: window.location.origin,
                isLoggedIn: 'no',
                version: '1.1.0',
                isFallback: true
            };
            fixes.applied.push('Created fallback massageBookingAPI object');
        }
        
        // Fix 3: Core functions
        if (!results.functions.fetchAvailableTimeSlots || results.functions.isPlaceholderFunction) {
            window.fetchAvailableTimeSlots = async function(date, duration) {
                console.log('API Diagnostics: Using patched fetchAvailableTimeSlots function');
                
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
                    
                    slotsContainer.innerHTML = '';
                    slotsContainer.classList.remove('loading');
                    
                    // Check if slots are available
                    if (!data || !data.available || !data.slots || data.slots.length === 0) {
                        slotsContainer.innerHTML = '<p>No appointments available on this date.</p>';
                        return { available: false, slots: [] };
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
                        });
                        
                        slotsContainer.appendChild(slotElement);
                    });
                    
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
            
            fixes.applied.push('Added functional replacement for fetchAvailableTimeSlots');
        }
        
        if (!results.functions.updateSummary) {
            window.updateSummary = function() {
                const summary = document.getElementById('bookingSummary');
                if (!summary) return;
                
                const selectedDuration = document.querySelector('input[name="duration"]:checked');
                const selectedTime = document.querySelector('.time-slot.selected');
                const selectedDate = document.getElementById('appointmentDate')?.value;
                
                if (selectedDuration && selectedTime && selectedDate) {
                    summary.classList.add('visible');
                    
                    const durationValue = selectedDuration.value;
                    const durationPrice = selectedDuration.closest('.radio-option')?.getAttribute('data-price') || '0';
                    
                    // Update service
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
                    summary.classList.remove('visible');
                }
            };
            
            fixes.applied.push('Added missing updateSummary function');
        }
        
        // Fix 4: Time slot selection event handlers
        const timeSlots = document.querySelectorAll('.time-slot');
        if (timeSlots.length > 0) {
            let needsEvents = false;
            
            // Test if click handlers are working by checking if any slot has a click handler
            const testSlot = timeSlots[0];
            const oldClickHandlers = $._data(testSlot, 'events');
            needsEvents = !oldClickHandlers || !oldClickHandlers.click;
            
            if (needsEvents) {
                timeSlots.forEach(slot => {
                    slot.addEventListener('click', function() {
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
                    });
                });
                
                fixes.applied.push('Added missing click event handlers to time slots');
            } else {
                fixes.skipped.push('Time slot event handlers already exist');
            }
        } else {
            fixes.skipped.push('No time slots found to fix event handlers');
        }
        
        // Fix 5: Date input event handler
        const dateInput = document.getElementById('appointmentDate');
        if (dateInput) {
            const oldDateHandlers = $._data(dateInput, 'events');
            const needsDateHandler = !oldDateHandlers || (!oldDateHandlers.change && !oldDateHandlers.input);
            
            if (needsDateHandler) {
                dateInput.addEventListener('change', function() {
                    if (this.value && typeof window.fetchAvailableTimeSlots === 'function') {
                        const duration = document.querySelector('input[name="duration"]:checked')?.value || '60';
                        window.fetchAvailableTimeSlots(this.value, duration);
                    }
                });
                
                fixes.applied.push('Added missing change event handler to date input');
            } else {
                fixes.skipped.push('Date input event handler already exists');
            }
        } else {
            fixes.skipped.push('Date input not found to fix event handler');
        }
        
        // Fix 6: Initialize API connector
        if (!results.functions.apiConnectorInitialized) {
            window._apiConnectorInitialized = true;
            
            // Try to load settings
            if (typeof window.loadSettings === 'function') {
                window.loadSettings().catch(error => {
                    console.warn('API Diagnostics: Failed to load settings:', error);
                });
            }
            
            fixes.applied.push('Initialized API connector');
        }
        
        return fixes;
    }
    
    /**
     * Display results in a modal for admin users
     */
    function displayResults(results, fixes) {
        // Create a modal to display results
        const modalContainer = document.createElement('div');
        modalContainer.className = 'mb-diagnostics-modal';
        modalContainer.style.position = 'fixed';
        modalContainer.style.top = '0';
        modalContainer.style.left = '0';
        modalContainer.style.width = '100%';
        modalContainer.style.height = '100%';
        modalContainer.style.background = 'rgba(0, 0, 0, 0.7)';
        modalContainer.style.zIndex = '10000';
        modalContainer.style.display = 'flex';
        modalContainer.style.justifyContent = 'center';
        modalContainer.style.alignItems = 'center';
        
        // Modal content
        const modalContent = document.createElement('div');
        modalContent.style.background = 'white';
        modalContent.style.borderRadius = '5px';
        modalContent.style.width = '80%';
        modalContent.style.maxWidth = '800px';
        modalContent.style.maxHeight = '80vh';
        modalContent.style.padding = '20px';
        modalContent.style.overflow = 'auto';
        modalContent.style.position = 'relative';
        
        // Close button
        const closeButton = document.createElement('button');
        closeButton.textContent = '×';
        closeButton.style.position = 'absolute';
        closeButton.style.top = '10px';
        closeButton.style.right = '10px';
        closeButton.style.border = 'none';
        closeButton.style.background = 'none';
        closeButton.style.fontSize = '24px';
        closeButton.style.cursor = 'pointer';
        closeButton.addEventListener('click', function() {
            document.body.removeChild(modalContainer);
        });
        
        // Title
        const title = document.createElement('h2');
        title.textContent = 'API Diagnostics Results';
        title.style.marginTop = '0';
        title.style.marginBottom = '20px';
        title.style.color = '#4a6fa5';
        
        // Results content
        modalContent.appendChild(closeButton);
        modalContent.appendChild(title);
        
        // Add automatic fixes section
        const fixesSection = document.createElement('div');
        fixesSection.style.marginBottom = '20px';
        fixesSection.style.padding = '10px';
        fixesSection.style.background = '#f0f8ff';
        fixesSection.style.border = '1px solid #c6e2ff';
        fixesSection.style.borderRadius = '4px';
        
        const fixesTitle = document.createElement('h3');
        fixesTitle.textContent = 'Automatic Fixes Applied';
        fixesTitle.style.marginTop = '0';
        fixesSection.appendChild(fixesTitle);
        
        if (fixes.applied.length > 0) {
            const fixesList = document.createElement('ul');
            fixesList.style.marginBottom = '10px';
            
            fixes.applied.forEach(fix => {
                const fixItem = document.createElement('li');
                fixItem.textContent = fix;
                fixItem.style.color = 'green';
                fixesList.appendChild(fixItem);
            });
            
            fixesSection.appendChild(fixesList);
        } else {
            const noFixesMessage = document.createElement('p');
            noFixesMessage.textContent = 'No automatic fixes were necessary.';
            fixesSection.appendChild(noFixesMessage);
        }
        
        modalContent.appendChild(fixesSection);
        
        // Add sections for each diagnostic category
        const categories = [
            { name: 'Form Structure', data: results.form },
            { name: 'API Configuration', data: results.api },
            { name: 'Core Functions', data: results.functions },
            { name: 'Event Handlers', data: results.events },
            { name: 'Session Storage', data: results.storage }
        ];
        
        categories.forEach(category => {
            const section = document.createElement('div');
            section.style.marginBottom = '20px';
            
            const sectionTitle = document.createElement('h3');
            sectionTitle.textContent = category.name;
            sectionTitle.style.borderBottom = '1px solid #ddd';
            sectionTitle.style.paddingBottom = '5px';
            section.appendChild(sectionTitle);
            
            const sectionContent = document.createElement('pre');
            sectionContent.style.background = '#f5f5f5';
            sectionContent.style.padding = '10px';
            sectionContent.style.borderRadius = '4px';
            sectionContent.style.overflow = 'auto';
            sectionContent.style.maxHeight = '200px';
            sectionContent.style.fontSize = '12px';
            sectionContent.textContent = JSON.stringify(category.data, null, 2);
            section.appendChild(sectionContent);
            
            modalContent.appendChild(section);
        });
        
        // Add troubleshooting tips
        const tipsSection = document.createElement('div');
        tipsSection.style.marginTop = '20px';
        tipsSection.style.padding = '10px';
        tipsSection.style.background = '#fffaf0';
        tipsSection.style.border = '1px solid #ffe4b5';
        tipsSection.style.borderRadius = '4px';
        
        const tipsTitle = document.createElement('h3');
        tipsTitle.textContent = 'Troubleshooting Tips';
        tipsTitle.style.marginTop = '0';
        tipsSection.appendChild(tipsTitle);
        
        const tipsList = document.createElement('ul');
        
        const tips = [
            'Ensure all required scripts are properly enqueued in the massage-booking.php file.',
            'Check if wp_localize_script is properly passing the massageBookingAPI object.',
            'Verify the WordPress REST API is working by visiting ' + results.api.config.restUrl?.value + 'settings in your browser.',
            'Clear your browser cache and reload the page to ensure you have the latest script versions.',
            'Check the PHP error log for any server-side errors.'
        ];
        
        tips.forEach(tip => {
            const tipItem = document.createElement('li');
            tipItem.textContent = tip;
            tipsList.appendChild(tipItem);
        });
        
        tipsSection.appendChild(tipsList);
        modalContent.appendChild(tipsSection);
        
        modalContainer.appendChild(modalContent);
        document.body.appendChild(modalContainer);
    }
    
    // Add AJAX test functionality
    function testApiEndpoint() {
        if (typeof massageBookingAPI === 'undefined') {
            return {success: false, error: 'API configuration missing'};
        }
        
        return $.ajax({
            url: massageBookingAPI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'massage_booking_test_api',
                nonce: massageBookingAPI.nonce
            },
            dataType: 'json'
        });
    }
    
    // Expose API for external use
    window.massageBookingDiagnostics = {
        run: runDiagnostics,
        testApi: testApiEndpoint
    };
    
})(jQuery);
