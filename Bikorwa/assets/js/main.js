/**
 * Main JavaScript file for BIKORWA SHOP
 * Contains common functionality used across the application
 */

// When DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize toastr notifications if available
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: "toast-top-right",
            timeOut: 5000
        };
    }
    
    // Handle sidebar toggle on mobile if present
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }
    
    // Format currency values
    formatCurrencyElements();
});

/**
 * Format all elements with the currency-format class
 */
function formatCurrencyElements() {
    document.querySelectorAll('.currency-format').forEach(function(element) {
        const value = parseFloat(element.textContent);
        if (!isNaN(value)) {
            element.textContent = formatCurrency(value);
        }
    });
}

/**
 * Format a number as BIF currency
 * @param {number} amount - The amount to format
 * @returns {string} - Formatted currency string
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-BI', { 
        style: 'currency', 
        currency: 'BIF',
        maximumFractionDigits: 0 
    }).format(amount);
}

/**
 * Show a confirmation dialog
 * @param {string} message - Message to display
 * @param {Function} callback - Function to call if confirmed
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}
