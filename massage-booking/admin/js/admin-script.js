/**
 * Massage Booking System Admin Scripts
 */
jQuery(document).ready(function($) {
    
    /**
     * Update status class for appointment status cells
     */
    function initStatusClasses() {
        $('.massage-booking-admin td.column-status').each(function() {
            var status = $(this).text().trim().toLowerCase();
            $(this).html('<span class="status-' + status + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>');
        });
    }
    
    /**
     * Initialize date pickers
     */
    if ($.fn.datepicker) {
        $('.massage-booking-admin .date-picker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    }
    
    /**
     * Initialize tabs
     */
    $('.massage-booking-admin .nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        $('.massage-booking-admin .nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.massage-booking-admin .tab-content').hide();
        
        // Show the content for the active tab
        var target = $(this).attr('href');
        $(target).show();
    });
    
    /**
     * Initialize admin dashboard widget refresh
     */
    function refreshPendingCount() {
        if ($('#massage_booking_dashboard_widget').length) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'massage_booking_get_pending_count',
                    nonce: $('#massage_booking_nonce').val()
                },
                success: function(response) {
                    if (response.success && response.data.count > 0) {
                        $('#massage-booking-pending-count').text(response.data.count);
                    } else {
                        $('#massage-booking-pending-count').text('0');
                    }
                }
            });
        }
    }
    
    /**
     * Initialize appointment confirmation
     */
    $('.massage-booking-admin .confirm-appointment').on('click', function(e) {
        if (!confirm('Are you sure you want to confirm this appointment?')) {
            e.preventDefault();
        }
    });
    
    /**
     * Initialize appointment cancellation
     */
    $('.massage-booking-admin .cancel-appointment').on('click', function(e) {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
            e.preventDefault();
        }
    });
    
    /**
     * Initialize appointment deletion
     */
    $('.massage-booking-admin .delete-appointment').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this appointment? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    /**
     * Initialize form validation
     */
    $('.massage-booking-admin form').on('submit', function(e) {
        var valid = true;
        
        // Check required fields
        $(this).find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                valid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
    
    /**
     * Initialize the admin page
     */
    function initAdminPage() {
        initStatusClasses();
        
        // Show the first tab by default
        if ($('.massage-booking-admin .nav-tab').length) {
            $('.massage-booking-admin .nav-tab:first').click();
        }
        
        // Refresh pending count every 5 minutes
        if ($('#massage_booking_dashboard_widget').length) {
            refreshPendingCount();
            setInterval(refreshPendingCount, 5 * 60 * 1000);
        }
    }
    
    // Initialize when DOM is ready
    initAdminPage();
});
