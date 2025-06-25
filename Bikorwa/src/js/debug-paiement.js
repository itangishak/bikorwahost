/**
 * Debug script for paiement.php button click issues
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug script loaded for paiement.php');
    
    // 1. Check if Bootstrap is loaded
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    if (typeof bootstrap !== 'undefined') {
        console.log('Bootstrap Modal available:', typeof bootstrap.Modal !== 'undefined');
    }
    
    // 2. Check if jQuery is loaded (if used in the application)
    console.log('jQuery available:', typeof jQuery !== 'undefined');
    
    // 3. Debug the "Nouveau Paiement" button
    const addButton = document.querySelector('button[data-bs-target="#addPaymentModal"]');
    console.log('Add button found:', addButton !== null);
    
    if (addButton) {
        // Log button attributes
        console.log('Button attributes:', {
            'data-bs-toggle': addButton.getAttribute('data-bs-toggle'),
            'data-bs-target': addButton.getAttribute('data-bs-target'),
            'class': addButton.className
        });
        
        // Add explicit click handler to test if the button receives clicks
        addButton.addEventListener('click', function(e) {
            console.log('Button clicked!', e);
            console.log('Default prevented?', e.defaultPrevented);
            
            // Check if the modal exists
            const modal = document.getElementById('addPaymentModal');
            console.log('Modal found:', modal !== null);
            
            if (modal) {
                console.log('Modal display style:', getComputedStyle(modal).display);
                console.log('Modal classes:', modal.className);
            }
            
            // Try to manually open the modal
            try {
                console.log('Attempting to manually open modal...');
                const modalInstance = new bootstrap.Modal(document.getElementById('addPaymentModal'));
                modalInstance.show();
                console.log('Modal manually triggered');
            } catch (err) {
                console.error('Error manually opening modal:', err);
            }
        });
    }
    
    // 4. Check the addPaymentModal
    const addModal = document.getElementById('addPaymentModal');
    console.log('Add modal found:', addModal !== null);
    
    if (addModal) {
        // Log modal attributes
        console.log('Modal attributes:', {
            'id': addModal.id,
            'tabindex': addModal.getAttribute('tabindex'),
            'aria-labelledby': addModal.getAttribute('aria-labelledby'),
            'class': addModal.className
        });
        
        // Add event listeners to modal events
        addModal.addEventListener('show.bs.modal', function() {
            console.log('Modal show.bs.modal event fired');
        });
        
        addModal.addEventListener('shown.bs.modal', function() {
            console.log('Modal shown.bs.modal event fired');
        });
        
        addModal.addEventListener('hide.bs.modal', function() {
            console.log('Modal hide.bs.modal event fired');
        });
        
        addModal.addEventListener('hidden.bs.modal', function() {
            console.log('Modal hidden.bs.modal event fired');
        });
    }
    
    // 5. Check the add payment form
    const addForm = document.getElementById('addPaymentForm');
    console.log('Add form found:', addForm !== null);
    
    if (addForm) {
        // Add a submit handler if it doesn't have one
        if (!addForm.hasAttribute('data-has-listener')) {
            addForm.setAttribute('data-has-listener', 'true');
            addForm.addEventListener('submit', function(e) {
                console.log('Form submit event fired');
                e.preventDefault();
                
                // Get reference to the handlePaymentSubmit function if it exists
                if (typeof handlePaymentSubmit === 'function') {
                    console.log('handlePaymentSubmit function found, trying to call it');
                    try {
                        handlePaymentSubmit(this, 'add');
                    } catch (err) {
                        console.error('Error calling handlePaymentSubmit:', err);
                    }
                } else {
                    console.error('handlePaymentSubmit function not found in global scope');
                }
            });
            console.log('Added submit handler to addPaymentForm');
        }
    }
    
    // 6. Check the modal initialization
    console.log('Checking modal initialization...');
    try {
        const modals = document.querySelectorAll('.modal');
        console.log('Found', modals.length, 'modals on the page');
        
        modals.forEach((modal, index) => {
            console.log(`Modal ${index + 1}:`, {
                id: modal.id,
                class: modal.className
            });
        });
    } catch (err) {
        console.error('Error checking modals:', err);
    }
});

// Helper function to check if element events are working
function testElementEvents(elementSelector, eventType = 'click') {
    const element = document.querySelector(elementSelector);
    if (element) {
        console.log(`Adding test ${eventType} handler to ${elementSelector}`);
        element.addEventListener(eventType, function(e) {
            console.log(`${eventType} event on ${elementSelector} fired!`, e);
        });
        return true;
    } else {
        console.error(`Element ${elementSelector} not found for event testing`);
        return false;
    }
}

// Execute additional tests after a short delay to ensure page is fully loaded
setTimeout(function() {
    console.log('Running delayed tests...');
    
    // Test specific elements
    testElementEvents('button[data-bs-target="#addPaymentModal"]');
    testElementEvents('#addPaymentModal', 'show.bs.modal');
    
    // List all the scripts loaded on the page
    const scripts = document.querySelectorAll('script');
    console.log('Scripts loaded on page:', Array.from(scripts).map(s => s.src || 'inline script'));
    
    // Check if any JavaScript errors are in the console
    console.log('If you see this message, there are no fatal JavaScript errors preventing script execution');
}, 1000);
