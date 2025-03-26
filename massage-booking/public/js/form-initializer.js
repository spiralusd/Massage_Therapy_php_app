/**
 * Form Initializer - Runs before API connector
 * Ensures the form ID is properly set before API initialization
 */
(function() {
    function initializeForm() {
        console.log('Form initializer running');
        // Find the form element using multiple selectors
        var forms = document.querySelectorAll('form.booking-form, .massage-booking-container form, form');
        
        // Assign the ID to the first form found if it doesn't already have it
        if (forms.length > 0) {
            if (!forms[0].id || forms[0].id !== 'appointmentForm') {
                forms[0].id = 'appointmentForm';
                console.log('ID assigned to form: appointmentForm');
            }
            
            // Dispatch an event to notify API connector
            var event = new CustomEvent('form_initialized', { 
                detail: { form: forms[0] } 
            });
            document.dispatchEvent(event);
        } else {
            console.error('No form found on page');
        }
    }
    
    // Run as soon as DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeForm);
    } else {
        // DOM already loaded, run immediately
        initializeForm();
    }
})();