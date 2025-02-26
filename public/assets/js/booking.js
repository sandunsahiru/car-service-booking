// booking.js - Handles booking form population and cancellation

document.addEventListener('DOMContentLoaded', function() {
    // Handle Book Now buttons from AI recommendations
    const bookNowButtons = document.querySelectorAll('.recommendation-book-now');
    bookNowButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get data attributes from the button
            const carId = this.getAttribute('data-car-id');
            const serviceId = this.getAttribute('data-service-id');
            const recommendationId = this.getAttribute('data-recommendation-id');
            
            // Scroll to booking form
            document.querySelector('#bookingForm').scrollIntoView({ behavior: 'smooth' });
            
            // Populate form fields
            if(carId) {
                document.querySelector('#car_id').value = carId;
            }
            
            if(serviceId) {
                document.querySelector('#service_id').value = serviceId;
            }
            
            // Add hidden field for recommendation ID if it exists
            if(recommendationId) {
                let hiddenField = document.querySelector('#recommendation_id');
                if(!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = 'recommendation_id';
                    hiddenField.id = 'recommendation_id';
                    document.querySelector('#bookingForm').appendChild(hiddenField);
                }
                hiddenField.value = recommendationId;
            }
            
            // Update any dynamic fields that might depend on selected car/service
            updateAvailableTimeSlots();
        });
    });
    
    // Handle cancellation buttons
    const cancelButtons = document.querySelectorAll('.cancel-booking-btn');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if(!confirm('Are you sure you want to cancel this booking?')) {
                e.preventDefault();
                return false;
            }
            
            // Form submission will be handled via the standard form submit
            return true;
        });
    });
    
    // Update available time slots when service or date changes
    function updateAvailableTimeSlots() {
        const serviceId = document.querySelector('#service_id').value;
        const bookingDate = document.querySelector('#booking_date').value;
        
        if(!serviceId || !bookingDate) {
            return;
        }
        
        // Create form data for AJAX request
        const formData = new FormData();
        formData.append('action', 'get_available_slots');
        formData.append('service_id', serviceId);
        formData.append('date', bookingDate);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        // Send AJAX request to get available time slots
        fetch('ajax/booking.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success && data.slots) {
                const timeSelect = document.querySelector('#booking_time');
                
                // Clear existing options except the first one
                while(timeSelect.options.length > 1) {
                    timeSelect.remove(1);
                }
                
                // Add new time slots
                data.slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    option.textContent = slot.formatted_time;
                    timeSelect.appendChild(option);
                });
                
                // Enable select if options are available
                timeSelect.disabled = data.slots.length === 0;
                
                // Show message if no slots available
                if(data.slots.length === 0) {
                    alert('No available time slots for the selected date. Please choose another date.');
                }
            }
        })
        .catch(error => {
            console.error('Error fetching available time slots:', error);
        });
    }
    
    // Set up event listeners for dynamic slot updates
    const serviceSelect = document.querySelector('#service_id');
    const dateInput = document.querySelector('#booking_date');
    
    if(serviceSelect && dateInput) {
        serviceSelect.addEventListener('change', updateAvailableTimeSlots);
        dateInput.addEventListener('change', updateAvailableTimeSlots);
    }
});