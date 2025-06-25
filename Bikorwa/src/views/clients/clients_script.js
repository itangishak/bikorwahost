/**
 * BIKORWA SHOP - Clients Management JavaScript
 * Handles all client CRUD operations with AJAX
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables and elements
    const addClientModal = document.getElementById('addClientModal');
    const editClientModal = document.getElementById('editClientModal');
    const viewClientModal = document.getElementById('viewClientModal');
    const confirmationModal = document.getElementById('confirmationModal');
    const addClientForm = document.getElementById('addClientForm');
    const editClientForm = document.getElementById('editClientForm');
    const confirmationMessage = document.getElementById('confirmationMessage');
    const saveClientBtn = document.getElementById('saveClientBtn');
    const updateClientBtn = document.getElementById('updateClientBtn');
    const confirmBtn = document.getElementById('confirmBtn');
    const clientDetails = document.getElementById('clientDetails');
    const toastContainer = document.getElementById('toastContainer');
    
    let currentClientId = null;
    let currentPage = 1;
    let currentSearch = '';
    
    // Event listeners for the buttons
    if (document.getElementById('addNewClientBtn')) {
        document.getElementById('addNewClientBtn').addEventListener('click', function() {
            resetAddForm();
            new bootstrap.Modal(addClientModal).show();
        });
    }
    
    // Add client form submission
    if (saveClientBtn) {
        saveClientBtn.addEventListener('click', function() {
            if (addClientForm.checkValidity()) {
                addClient();
            } else {
                addClientForm.reportValidity();
            }
        });
    }
    
    // Edit client form submission
    if (updateClientBtn) {
        updateClientBtn.addEventListener('click', function() {
            if (editClientForm.checkValidity()) {
                updateClient();
            } else {
                editClientForm.reportValidity();
            }
        });
    }
    
    // Initialize event listeners for action buttons (edit, view, delete)
    initActionButtons();
    
    /**
     * Initialize event listeners for action buttons in the client listing
     */
    function initActionButtons() {
        // Edit client buttons
        document.querySelectorAll('.btn-edit-client').forEach(button => {
            button.addEventListener('click', function() {
                const clientId = this.dataset.id;
                getClientDetails(clientId, 'edit');
            });
        });
        
        // View client buttons
        document.querySelectorAll('.btn-view-client').forEach(button => {
            button.addEventListener('click', function() {
                const clientId = this.dataset.id;
                getClientDetails(clientId, 'view');
            });
        });
        
        // Delete client buttons
        document.querySelectorAll('.btn-delete-client').forEach(button => {
            button.addEventListener('click', function() {
                const clientId = this.dataset.id;
                const clientName = this.dataset.name;
                showDeleteConfirmation(clientId, clientName);
            });
        });
        
        // Pagination links
        document.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.parentElement.classList.contains('active') && !this.parentElement.classList.contains('disabled')) {
                    e.preventDefault();
                    const page = this.dataset.page;
                    if (page) {
                        window.location.href = `?page=${page}${currentSearch ? '&search=' + encodeURIComponent(currentSearch) : ''}`;
                    }
                }
            });
        });
    }
    
    /**
     * Reset the add client form
     */
    function resetAddForm() {
        addClientForm.reset();
        document.getElementById('limite_credit').value = 0;
    }
    
    /**
     * Add a new client via AJAX
     */
    function addClient() {
        // Show spinner
        const spinner = saveClientBtn.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        saveClientBtn.disabled = true;
        
        const formData = new FormData(addClientForm);
        
        fetch('../../api/clients/add_client.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            saveClientBtn.disabled = false;
            
            if (data.success) {
                // Close modal and show success message
                bootstrap.Modal.getInstance(addClientModal).hide();
                showToast('Succès', data.message, 'success');
                
                // Reload the page to show the new client
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show error message
                showToast('Erreur', data.message, 'danger');
            }
        })
        .catch(error => {
            // Hide spinner
            spinner.classList.add('d-none');
            saveClientBtn.disabled = false;
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de l\'ajout du client.', 'danger');
            console.error('Error:', error);
        });
    }
    
    /**
     * Get client details for viewing or editing
     */
    function getClientDetails(clientId, mode) {
        // Show spinner for view mode
        if (mode === 'view') {
            clientDetails.classList.add('d-none');
            document.getElementById('viewClientSpinner').classList.remove('d-none');
            new bootstrap.Modal(viewClientModal).show();
        } else {
            // Show spinner for edit mode
            updateClientBtn.querySelector('.spinner-border').classList.remove('d-none');
            updateClientBtn.disabled = true;
        }
        
        fetch(`../../api/clients/get_client_details.php?id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const client = data.client;
                
                if (mode === 'view') {
                    // Fill view details
                    document.getElementById('view_nom').textContent = client.nom;
                    document.getElementById('view_telephone').textContent = client.telephone || '-';
                    document.getElementById('view_email').textContent = client.email || '-';
                    document.getElementById('view_adresse').textContent = client.adresse || '-';
                    document.getElementById('view_limite_credit').textContent = formatMoney(client.limite_credit) + ' BIF';
                    document.getElementById('view_date_creation').textContent = formatDate(client.date_creation);
                    document.getElementById('view_note').textContent = client.note || '-';
                    
                    // Show details and hide spinner
                    clientDetails.classList.remove('d-none');
                    document.getElementById('viewClientSpinner').classList.add('d-none');
                } else if (mode === 'edit') {
                    // Fill edit form
                    document.getElementById('edit_id').value = client.id;
                    document.getElementById('edit_nom').value = client.nom;
                    document.getElementById('edit_telephone').value = client.telephone || '';
                    document.getElementById('edit_email').value = client.email || '';
                    document.getElementById('edit_adresse').value = client.adresse || '';
                    document.getElementById('edit_limite_credit').value = client.limite_credit;
                    document.getElementById('edit_note').value = client.note || '';
                    
                    // Hide spinner and show modal
                    updateClientBtn.querySelector('.spinner-border').classList.add('d-none');
                    updateClientBtn.disabled = false;
                    new bootstrap.Modal(editClientModal).show();
                }
            } else {
                // Show error message
                showToast('Erreur', data.message, 'danger');
                
                if (mode === 'view') {
                    bootstrap.Modal.getInstance(viewClientModal).hide();
                }
            }
        })
        .catch(error => {
            // Hide spinner
            if (mode === 'view') {
                bootstrap.Modal.getInstance(viewClientModal).hide();
            } else {
                updateClientBtn.querySelector('.spinner-border').classList.add('d-none');
                updateClientBtn.disabled = false;
            }
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de la récupération des détails du client.', 'danger');
            console.error('Error:', error);
        });
    }
    
    /**
     * Update client information
     */
    function updateClient() {
        // Show spinner
        const spinner = updateClientBtn.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        updateClientBtn.disabled = true;
        
        const formData = new FormData(editClientForm);
        
        fetch('../../api/clients/update_client.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            updateClientBtn.disabled = false;
            
            if (data.success) {
                // Close modal and show success message
                bootstrap.Modal.getInstance(editClientModal).hide();
                showToast('Succès', data.message, 'success');
                
                // Reload the page to show the updated client
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show error message
                showToast('Erreur', data.message, 'danger');
            }
        })
        .catch(error => {
            // Hide spinner
            spinner.classList.add('d-none');
            updateClientBtn.disabled = false;
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de la mise à jour du client.', 'danger');
            console.error('Error:', error);
        });
    }
    
    /**
     * Show delete confirmation modal
     */
    function showDeleteConfirmation(clientId, clientName) {
        currentClientId = clientId;
        confirmationMessage.innerHTML = `Êtes-vous sûr de vouloir supprimer le client <strong>${clientName}</strong>? Cette action est irréversible.`;
        
        // Reset spinner and button state
        confirmBtn.querySelector('.spinner-border').classList.add('d-none');
        confirmBtn.disabled = false;
        
        // Setup confirm button event listener (remove previous to avoid duplicates)
        confirmBtn.removeEventListener('click', handleDeleteConfirmation);
        confirmBtn.addEventListener('click', handleDeleteConfirmation);
        
        // Show the modal
        new bootstrap.Modal(confirmationModal).show();
    }
    
    /**
     * Handle the delete confirmation
     */
    function handleDeleteConfirmation() {
        // Show spinner
        const spinner = confirmBtn.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        confirmBtn.disabled = true;
        
        fetch('../../api/clients/delete_client.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${currentClientId}`
        })
        .then(response => response.json())
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            confirmBtn.disabled = false;
            
            if (data.success) {
                // Close modal and show success message
                bootstrap.Modal.getInstance(confirmationModal).hide();
                showToast('Succès', data.message, 'success');
                
                // Reload the page to update the client list
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show error message
                bootstrap.Modal.getInstance(confirmationModal).hide();
                showToast('Erreur', data.message, 'danger');
            }
        })
        .catch(error => {
            // Hide spinner
            spinner.classList.add('d-none');
            confirmBtn.disabled = false;
            
            // Close modal and show error message
            bootstrap.Modal.getInstance(confirmationModal).hide();
            showToast('Erreur', 'Une erreur est survenue lors de la suppression du client.', 'danger');
            console.error('Error:', error);
        });
    }
    
    /**
     * Show a toast notification
     */
    function showToast(title, message, type) {
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}:</strong> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();
        
        // Remove toast from DOM after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
    
    /**
     * Format a date string to a readable format
     */
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    /**
     * Format a number as currency
     */
    function formatMoney(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount);
    }
    
    // Initialize search form
    if (document.getElementById('searchForm')) {
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                const searchTerm = searchInput.value.trim();
                window.location.href = searchTerm 
                    ? `?search=${encodeURIComponent(searchTerm)}` 
                    : window.location.pathname;
            }
        });
    }
    
    // Get current search term from URL
    const urlParams = new URLSearchParams(window.location.search);
    currentSearch = urlParams.get('search') || '';
    currentPage = parseInt(urlParams.get('page')) || 1;
});
