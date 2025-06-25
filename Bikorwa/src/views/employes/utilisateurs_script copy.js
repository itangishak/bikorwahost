// User management JavaScript functionality
// Add this to your utilisateurs.php file after the edit form

// Initialize tooltips and setup AJAX forms
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
    
    // Add User Form Submit
    document.getElementById('saveNewUserBtn').addEventListener('click', function() {
        const form = document.getElementById('addUserForm');
        
        // Validate the form
        if (!validateUserForm(form)) {
            return;
        }
        
        // Show loading spinner
        const spinner = this.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        this.disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        formData.append('action', 'add');
        formData.append('actif', form.querySelector('#add_actif').checked ? '1' : '0');
        
        // Send AJAX request
        fetch('./process_users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Handle response
            if (data.success) {
                // Show success message
                showToast('Succès', data.message, 'success');
                
                // Close modal and reset form
                const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                modal.hide();
                form.reset();
                
                // Reload page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show error message
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de la communication avec le serveur.', 'error');
            console.error('Error:', error);
        });
    });
    
    // Edit User Form Submit
    document.getElementById('updateUserBtn').addEventListener('click', function() {
        const form = document.getElementById('editUserForm');
        
        // Validate the form
        if (!validateUserForm(form, true)) {
            return;
        }
        
        // Show loading spinner
        const spinner = this.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        this.disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        formData.append('action', 'update');
        formData.append('actif', form.querySelector('#edit_actif').checked ? '1' : '0');
        
        // Send AJAX request
        fetch('./process_users.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Handle response
            if (data.success) {
                // Show success message
                showToast('Succès', data.message, 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                modal.hide();
                
                // Update row data if needed
                updateUserRow(data.user);
            } else {
                // Show error message
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de la communication avec le serveur.', 'error');
            console.error('Error:', error);
        });
    });
});

// Function to validate user form
function validateUserForm(form, isEdit = false) {
    let isValid = true;
    
    // Reset previous validation state
    form.querySelectorAll('.is-invalid').forEach(element => {
        element.classList.remove('is-invalid');
    });
    
    // Validate required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    // Validate email if provided
    const emailField = form.querySelector('[type="email"]');
    if (emailField && emailField.value.trim() !== '') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value.trim())) {
            emailField.classList.add('is-invalid');
            isValid = false;
        }
    }
    
    // Validate password fields (only for add or if password is provided in edit)
    const passwordField = form.querySelector('[name="password"]');
    const confirmPasswordField = form.querySelector('[name="confirm_password"]');
    
    if (!isEdit || (passwordField.value.trim() !== '')) {
        // For add form or if password is being changed in edit form
        if (!isEdit && passwordField.value.trim().length < 8) {
            passwordField.classList.add('is-invalid');
            isValid = false;
        }
        
        if (passwordField.value !== confirmPasswordField.value) {
            confirmPasswordField.classList.add('is-invalid');
            isValid = false;
        }
    }
    
    return isValid;
}

// Function to view user details
function viewUser(id) {
    // Show loading state
    document.body.style.cursor = 'wait';
    
    // Fetch user details
    fetch(`./get_user.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Reset cursor
            document.body.style.cursor = 'default';
            
            if (data.success) {
                const user = data.user;
                
                // Populate modal with user data
                document.getElementById('view_nom').textContent = user.nom;
                document.getElementById('view_username').textContent = user.username;
                document.getElementById('view_role').textContent = user.role === 'receptionniste' ? 'Réceptionniste' : 'Gestionnaire';
                document.getElementById('view_actif').innerHTML = user.actif === '1' ? 
                    '<span class="badge bg-success">Actif</span>' : 
                    '<span class="badge bg-danger">Inactif</span>';
                document.getElementById('view_email').textContent = user.email || '-';
                document.getElementById('view_telephone').textContent = user.telephone || '-';
                document.getElementById('view_date_creation').textContent = formatDate(user.date_creation);
                document.getElementById('view_derniere_connexion').textContent = user.derniere_connexion ? formatDate(user.derniere_connexion) : '-';
                
                // Store the user ID for edit function
                document.querySelector('#viewUserModal').dataset.userId = user.id;
                
                // Show modal
                const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                viewModal.show();
            } else {
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Reset cursor
            document.body.style.cursor = 'default';
            showToast('Erreur', 'Impossible de récupérer les détails de l\'utilisateur.', 'error');
            console.error('Error:', error);
        });
}

// Function to trigger edit from view modal
function editUserFromView() {
    const userId = document.querySelector('#viewUserModal').dataset.userId;
    
    // Close view modal
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewUserModal'));
    viewModal.hide();
    
    // Open edit modal with user data
    editUser(userId);
}

// Function to edit user
function editUser(id) {
    // Show loading state
    document.body.style.cursor = 'wait';
    
    // Fetch user details
    fetch(`./get_user.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            // Reset cursor
            document.body.style.cursor = 'default';
            
            if (data.success) {
                const user = data.user;
                const form = document.getElementById('editUserForm');
                
                // Reset form
                form.reset();
                
                // Populate form with user data
                form.querySelector('#edit_id').value = user.id;
                form.querySelector('#edit_nom').value = user.nom;
                form.querySelector('#edit_username').value = user.username;
                form.querySelector('#edit_role').value = user.role;
                form.querySelector('#edit_email').value = user.email || '';
                form.querySelector('#edit_telephone').value = user.telephone || '';
                form.querySelector('#edit_actif').checked = user.actif === '1';
                
                // Clear password fields
                form.querySelector('#edit_password').value = '';
                form.querySelector('#edit_confirm_password').value = '';
                
                // Show modal
                const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                editModal.show();
            } else {
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Reset cursor
            document.body.style.cursor = 'default';
            showToast('Erreur', 'Impossible de récupérer les détails de l\'utilisateur.', 'error');
            console.error('Error:', error);
        });
}

// Function to delete user
function deleteUser(id, nom) {
    document.getElementById('confirmationMessage').innerHTML = `
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Êtes-vous sûr de vouloir supprimer définitivement l'utilisateur <strong>${nom}</strong> ?
            <div class="mt-2">Cette action est irréversible.</div>
        </div>
    `;
    
    // Setup confirmation button action
    const confirmBtn = document.getElementById('confirmBtn');
    
    // Remove any existing event listeners (important to prevent multiple handlers)
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    newConfirmBtn.addEventListener('click', function() {
        // Show loading spinner
        const spinner = this.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        this.disabled = true;
        
        // Send delete request
        fetch('./process_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            
            if (data.success) {
                // Show success message
                showToast('Succès', data.message, 'success');
                
                // Remove row from table
                const row = document.querySelector(`tr[data-user-id="${id}"]`);
                if (row) row.remove();
                
                // Reload page if table is empty
                if (document.querySelectorAll('table tbody tr').length === 0) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                // Show error message
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors de la suppression.', 'error');
            console.error('Error:', error);
        });
    });
    
    // Show confirmation modal
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    confirmModal.show();
}

// Function to toggle user status
function toggleStatus(id, isActive) {
    const statusText = isActive ? "désactiver" : "activer";
    document.getElementById('confirmationMessage').innerHTML = `
        <div class="alert alert-${isActive ? 'warning' : 'info'}">
            <i class="fas fa-${isActive ? 'user-slash' : 'user-check'} me-2"></i>
            Êtes-vous sûr de vouloir ${statusText} cet utilisateur ?
        </div>
    `;
    
    // Setup confirmation button action
    const confirmBtn = document.getElementById('confirmBtn');
    confirmBtn.classList.remove('btn-danger');
    confirmBtn.classList.add(isActive ? 'btn-warning' : 'btn-success');
    
    // Remove any existing event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    newConfirmBtn.addEventListener('click', function() {
        // Show loading spinner
        const spinner = this.querySelector('.spinner-border');
        spinner.classList.remove('d-none');
        this.disabled = true;
        
        // Send status toggle request
        fetch('./process_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=toggle_status&id=${id}&actif=${isActive ? '0' : '1'}`
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            
            if (data.success) {
                // Show success message
                showToast('Succès', data.message, 'success');
                
                // Update row status
                updateUserStatus(id, !isActive);
            } else {
                // Show error message
                showToast('Erreur', data.message, 'error');
            }
        })
        .catch(error => {
            // Hide loading spinner
            spinner.classList.add('d-none');
            this.disabled = false;
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            
            // Show error message
            showToast('Erreur', 'Une erreur est survenue lors du changement de statut.', 'error');
            console.error('Error:', error);
        });
    });
    
    // Show confirmation modal
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    confirmModal.show();
}

// Function to update user row after edit
function updateUserRow(user) {
    const row = document.querySelector(`tr[data-user-id="${user.id}"]`);
    if (!row) return;
    
    // Update row data
    row.cells[1].textContent = user.nom;
    row.cells[2].textContent = user.username;
    
    // Update role
    row.cells[3].innerHTML = user.role === 'receptionniste' ? 
        '<span class="badge bg-info badge-role">Réceptionniste</span>' : 
        '<span class="badge bg-warning text-dark badge-role">Gestionnaire</span>';
        
    row.cells[4].textContent = user.email || '-';
    row.cells[5].textContent = user.telephone || '-';
    
    // Update status badge
    updateUserStatus(user.id, user.actif === '1');
}

// Function to update user status in the row
function updateUserStatus(id, isActive) {
    const row = document.querySelector(`tr[data-user-id="${id}"]`);
    if (!row) return;
    
    // Update status badge
    const statusBadge = row.querySelector('.status-badge');
    if (statusBadge) {
        statusBadge.className = `badge ${isActive ? 'bg-success' : 'bg-danger'} status-badge`;
        statusBadge.textContent = isActive ? 'Actif' : 'Inactif';
    }
    
    // Update toggle status button
    const statusBtn = row.querySelector('.btn-action[title="Activer"], .btn-action[title="Désactiver"]');
    if (statusBtn) {
        statusBtn.className = `btn ${isActive ? 'btn-secondary' : 'btn-success'} btn-sm btn-action`;
        statusBtn.title = isActive ? 'Désactiver' : 'Activer';
        statusBtn.setAttribute('onclick', `toggleStatus(${id}, ${isActive})`);  
        
        // Update icon
        const icon = statusBtn.querySelector('i');
        if (icon) {
            icon.className = `fas ${isActive ? 'fa-user-slash' : 'fa-user-check'}`;
        }
    }
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(',', ' ');
}

// Toast notification function
function showToast(title, message, type = 'info', duration = 5000) {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        document.body.appendChild(toastContainer);
    }
    
    // Generate unique ID for toast
    const toastId = 'toast-' + Date.now();
    
    // Set icon based on type
    let icon = 'info-circle';
    let bgColor = 'bg-info';
    
    if (type === 'success') {
        icon = 'check-circle';
        bgColor = 'bg-success';
    } else if (type === 'error') {
        icon = 'exclamation-circle';
        bgColor = 'bg-danger';
    } else if (type === 'warning') {
        icon = 'exclamation-triangle';
        bgColor = 'bg-warning';
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = 'toast show';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
        <div class="toast-header ${bgColor} text-white">
            <i class="fas fa-${icon} me-2"></i>
            <strong class="me-auto">${title}</strong>
            <small>à l'instant</small>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    `;
    
    // Add toast to container
    toastContainer.appendChild(toast);
    
    // Initialize Bootstrap toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: duration
    });
    
    // Add event listener for when toast is hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
    
    // Show toast
    bsToast.show();
}
