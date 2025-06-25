// Function to update statistics after debt payment or cancellation
function updateStatistics(amount, action) {
    // Get statistics elements
    const activeDettesEl = document.getElementById('dettes-actives');
    const partialDettesEl = document.getElementById('dettes-partielles');
    const totalAmountEl = document.getElementById('montant-total-restant');
    
    if (activeDettesEl && partialDettesEl && totalAmountEl) {
        // Parse current values
        let activeCount = parseInt(activeDettesEl.textContent.replace(/\s/g, '')) || 0;
        let partialCount = parseInt(partialDettesEl.textContent.replace(/\s/g, '')) || 0;
        let totalAmount = parseFloat(totalAmountEl.textContent.replace(/\s/g, '').replace(/[^\d.-]/g, '')) || 0;
        
        if (action === 'pay-full') {
            // If fully paid, decrease active or partial count and reduce total amount
            if (activeCount > 0) activeCount--;
            else if (partialCount > 0) partialCount--;
            totalAmount -= amount;
        } else if (action === 'cancel') {
            // If cancelled, decrease active or partial count and reduce total amount
            if (activeCount > 0) activeCount--;
            else if (partialCount > 0) partialCount--;
            totalAmount -= amount;
        }
        
        // Update the display (ensuring non-negative values)
        activeDettesEl.textContent = Math.max(0, activeCount).toLocaleString('fr-FR');
        partialDettesEl.textContent = Math.max(0, partialCount).toLocaleString('fr-FR');
        totalAmountEl.textContent = Math.max(0, totalAmount).toLocaleString('fr-FR') + ' F';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap components
    const viewDetteModal = new bootstrap.Modal(document.getElementById('viewDetteModal'));
    const detteModal = new bootstrap.Modal(document.getElementById('detteModal'));
    const paiementModal = new bootstrap.Modal(document.getElementById('paiementModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    
    // Note: The Add new debt button has been removed
    
    // View debt details
    document.querySelectorAll('.viewBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const detteId = this.getAttribute('data-id');
            loadDetteDetails(detteId);
        });
    });
    
    // Edit debt
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const detteId = this.getAttribute('data-id');
            loadDetteForEdit(detteId);
        });
    });
    
    // Payment button
    document.querySelectorAll('.payBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const detteId = this.getAttribute('data-id');
            loadDetteForPayment(detteId);
        });
    });
    
    // Delete button - only visible to managers but add extra check
    document.querySelectorAll('.deleteBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Double check if user is manager (in case of DOM manipulation)
            if (!isManager) {
                showToast('Erreur', 'Seuls les gestionnaires peuvent annuler des dettes', 'error');
                return;
            }
            const detteId = this.getAttribute('data-id');
            document.getElementById('confirmDeleteBtn').setAttribute('data-id', detteId);
            deleteModal.show();
        });
    });
    
    // Confirm delete button
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const detteId = this.getAttribute('data-id');
        cancelDette(detteId);
    });
    
    // Save debt button
    document.getElementById('saveDetteBtn').addEventListener('click', function() {
        saveDette();
    });
    
    // Save payment button
    document.getElementById('savePaiementBtn').addEventListener('click', function() {
        savePaiement();
    });
    
    // Client selection change - load related invoices
    document.getElementById('client_id_form').addEventListener('change', function() {
        const clientId = this.value;
        if (clientId) {
            loadClientInvoices(clientId);
        } else {
            // Clear invoices dropdown
            const invoiceSelect = document.getElementById('vente_id');
            invoiceSelect.innerHTML = '<option value="">Aucune facture associée</option>';
        }
    });
    
    // Functions
    
    // Load debt details for view modal
    function loadDetteDetails(detteId) {
        // Show spinner
        document.getElementById('detailsSpinner').classList.remove('d-none');
        document.getElementById('detteDetails').classList.add('d-none');
        
        // Show modal
        viewDetteModal.show();
        
        // Fetch data
        fetch(`../../../src/api/dettes/get_dette.php?id=${detteId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const dette = data.dette;
                    
                    // Populate data
                    document.getElementById('view_client_nom').textContent = dette.client_nom;
                    document.getElementById('view_montant_initial').textContent = formatMontant(dette.montant_initial) + ' F';
                    document.getElementById('view_montant_restant').textContent = formatMontant(dette.montant_restant) + ' F';
                    document.getElementById('view_date_creation').textContent = formatDate(dette.date_creation);
                    document.getElementById('view_date_echeance').textContent = dette.date_echeance ? formatDate(dette.date_echeance) : 'Non définie';
                    document.getElementById('view_id').textContent = dette.id;
                    document.getElementById('view_facture').textContent = dette.numero_facture || 'Aucune facture associée';
                    document.getElementById('view_note').textContent = dette.note || '-';
                    
                    // Set status badge
                    let statusBadge = '';
                    if (dette.statut === 'active') {
                        statusBadge = '<span class="badge bg-warning">Active</span>';
                    } else if (dette.statut === 'partiellement_payee') {
                        statusBadge = '<span class="badge bg-info">Partiellement payée</span>';
                    } else if (dette.statut === 'payee') {
                        statusBadge = '<span class="badge bg-success">Payée</span>';
                    } else {
                        statusBadge = '<span class="badge bg-danger">Annulée</span>';
                    }
                    document.getElementById('view_statut').innerHTML = statusBadge;
                    
                    // Handle payments table
                    const paiementsContainer = document.getElementById('paiementsTableContainer');
                    const noPaiements = document.getElementById('noPaiements');
                    const paiementsTableBody = document.getElementById('paiementsTableBody');
                    
                    if (data.paiements && data.paiements.length > 0) {
                        paiementsContainer.classList.remove('d-none');
                        noPaiements.classList.add('d-none');
                        
                        // Clear table
                        paiementsTableBody.innerHTML = '';
                        
                        // Add rows
                        data.paiements.forEach(paiement => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${formatDate(paiement.date_paiement)}</td>
                                <td>${formatMontant(paiement.montant)} F</td>
                                <td>${paiement.methode_paiement}</td>
                                <td>${paiement.reference || '-'}</td>
                                <td>${paiement.utilisateur_nom || '-'}</td>
                            `;
                            paiementsTableBody.appendChild(row);
                        });
                    } else {
                        paiementsContainer.classList.add('d-none');
                        noPaiements.classList.remove('d-none');
                    }
                    
                    // Hide spinner
                    document.getElementById('detailsSpinner').classList.add('d-none');
                    document.getElementById('detteDetails').classList.remove('d-none');
                } else {
                    showToast('Erreur', data.message || 'Erreur lors du chargement des détails', 'error');
                    viewDetteModal.hide();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Une erreur est survenue lors du chargement des détails', 'error');
                viewDetteModal.hide();
            });
    }
    
    // Load debt for edit
    function loadDetteForEdit(detteId) {
        // Show loading state
        document.getElementById('saveDetteBtn').disabled = true;
        
        // Fetch data
        fetch(`../../../src/api/dettes/get_dette.php?id=${detteId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const dette = data.dette;
                    
                    // Set form title
                    document.getElementById('detteModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Modifier la dette';
                    
                    // Populate form
                    document.getElementById('dette_id').value = dette.id;
                    document.getElementById('client_id_form').value = dette.client_id;
                    document.getElementById('montant_initial').value = dette.montant_initial;
                    document.getElementById('note').value = dette.note || '';
                    
                    if (dette.date_echeance) {
                        document.getElementById('date_echeance').value = dette.date_echeance.split(' ')[0]; // Extract date part
                    } else {
                        document.getElementById('date_echeance').value = '';
                    }
                    
                    // Load invoices for this client
                    loadClientInvoices(dette.client_id, dette.vente_id);
                    
                    // Show modal
                    detteModal.show();
                    
                    // Enable save button
                    document.getElementById('saveDetteBtn').disabled = false;
                } else {
                    showToast('Erreur', data.message || 'Erreur lors du chargement des données', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Une erreur est survenue lors du chargement des données', 'error');
            });
    }
    
    // Load debt for payment
    function loadDetteForPayment(detteId) {
        // Fetch data
        fetch(`../../../src/api/dettes/get_dette.php?id=${detteId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const dette = data.dette;
                    
                    // Populate form
                    document.getElementById('paiement_dette_id').value = dette.id;
                    document.getElementById('paiement-client-nom').textContent = dette.client_nom;
                    document.getElementById('paiement-montant-restant').textContent = formatMontant(dette.montant_restant);
                    
                    // Set default payment amount to remaining amount
                    document.getElementById('montant').value = dette.montant_restant;
                    
                    // Reset other fields
                    document.getElementById('methode_paiement').value = '';
                    document.getElementById('reference').value = '';
                    document.getElementById('note_paiement').value = '';
                    
                    // Show modal
                    paiementModal.show();
                } else {
                    showToast('Erreur', data.message || 'Erreur lors du chargement des données', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Une erreur est survenue lors du chargement des données', 'error');
            });
    }
    
    // Save debt
    function saveDette() {
        const form = document.getElementById('detteForm');
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Show spinner
        const spinner = document.getElementById('saveDetteSpinner');
        spinner.classList.remove('d-none');
        document.getElementById('saveDetteBtn').disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        formData.append('utilisateur_id', '<?php echo $current_user_id; ?>');
        
        // Determine if this is a create or update operation
        const isUpdate = formData.get('id') !== '';
        const url = isUpdate ? '../../../src/api/dettes/update_dette.php' : '../../../src/api/dettes/add_dette.php';
        
        // Submit data
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            document.getElementById('saveDetteBtn').disabled = false;
            
            if (data.success) {
                // Show success message
                showToast('Succès', data.message || 'Dette enregistrée avec succès', 'success');
                
                // Close modal
                detteModal.hide();
                
                // Reload page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast('Erreur', data.message || 'Erreur lors de l\'enregistrement', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            spinner.classList.add('d-none');
            document.getElementById('saveDetteBtn').disabled = false;
            showToast('Erreur', 'Une erreur est survenue lors de l\'enregistrement', 'error');
        });
    }
    
    // Save payment
    function savePaiement() {
        const form = document.getElementById('paiementForm');
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Get form values
        const detteId = document.getElementById('paiement_dette_id').value;
        const montant = parseFloat(document.getElementById('montant').value);
        const montantRestant = parseFloat(document.getElementById('paiement-montant-restant').textContent.replace(/\s/g, ''));
        
        // Validate amount
        if (montant <= 0) {
            showToast('Erreur', 'Le montant doit être supérieur à 0', 'error');
            return;
        }
        
        if (montant > montantRestant) {
            showToast('Erreur', 'Le montant ne peut pas dépasser le montant restant', 'error');
            return;
        }
        
        // Show spinner
        const spinner = document.getElementById('savePaiementSpinner');
        spinner.classList.remove('d-none');
        document.getElementById('savePaiementBtn').disabled = true;
        
        // Prepare form data
        const formData = new FormData(form);
        formData.append('utilisateur_id', '<?php echo $current_user_id; ?>');
        
        // Submit data
        fetch('../../../src/api/dettes/add_paiement.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            document.getElementById('savePaiementBtn').disabled = false;
            
            if (data.success) {
                // Show success message
                showToast('Succès', data.message || 'Paiement enregistré avec succès', 'success');
                
                // Close modal
                paiementModal.hide();
                
                // Check if the payment was for the full amount
                const remaining = montantRestant - montant;
                if (remaining <= 0) {
                    // If fully paid, remove the row from the table
                    const rowToRemove = document.querySelector(`tr[data-id="${detteId}"]`);
                    if (rowToRemove) {
                        // Update statistics
                        updateStatistics(montantRestant, 'pay-full');
                        
                        // Fade out animation
                        rowToRemove.style.transition = 'opacity 0.5s';
                        rowToRemove.style.opacity = '0';
                        
                        // Remove after animation completes
                        setTimeout(() => {
                            rowToRemove.remove();
                            
                            // Check if table is now empty
                            const tbody = document.querySelector('table.table-bordered tbody');
                            if (tbody && tbody.children.length === 0) {
                                // Replace table with empty message
                                const tableContainer = document.querySelector('.table-responsive');
                                if (tableContainer) {
                                    tableContainer.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Aucune dette trouvée</div>';
                                }
                            }
                        }, 500);
                    }
                } else {
                    // If partially paid, just reload to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } else {
                showToast('Erreur', data.message || 'Erreur lors de l\'enregistrement', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            spinner.classList.add('d-none');
            document.getElementById('savePaiementBtn').disabled = false;
            showToast('Erreur', 'Une erreur est survenue lors de l\'enregistrement', 'error');
        });
    }
    
    // Cancel debt
    function cancelDette(detteId) {
        // Double check if user is manager (in case of programmatic access)
        if (!isManager) {
            showToast('Erreur', 'Seuls les gestionnaires peuvent annuler des dettes', 'error');
            deleteModal.hide();
            return;
        }
        
        // Show spinner
        const spinner = document.getElementById('deleteSpinner');
        spinner.classList.remove('d-none');
        document.getElementById('confirmDeleteBtn').disabled = true;
        
        // Prepare data
        const formData = new FormData();
        formData.append('id', detteId);
        formData.append('utilisateur_id', '<?php echo $current_user_id; ?>');
        
        // Submit data
        fetch('../../../src/api/dettes/cancel_dette.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Hide spinner
            spinner.classList.add('d-none');
            document.getElementById('confirmDeleteBtn').disabled = false;
            
            if (data.success) {
                // Show success message
                showToast('Succès', data.message || 'Dette annulée avec succès', 'success');
                
                // Close modal
                deleteModal.hide();
                
                // Remove the cancelled debt row from the table
                const rowToRemove = document.querySelector(`tr[data-id="${detteId}"]`);
                if (rowToRemove) {
                    // Get the remaining amount from the row
                    const montantCell = rowToRemove.querySelector('td:nth-child(3)');
                    let montantRestant = 0;
                    
                    if (montantCell) {
                        // Extract amount from text like "1 000 000 F"
                        const amountText = montantCell.textContent.trim();
                        montantRestant = parseFloat(amountText.replace(/\s/g, '').replace(/[^\d.-]/g, '')) || 0;
                    }
                    
                    // Update statistics
                    updateStatistics(montantRestant, 'cancel');
                    
                    // Fade out animation
                    rowToRemove.style.transition = 'opacity 0.5s';
                    rowToRemove.style.opacity = '0';
                    
                    // Remove after animation completes
                    setTimeout(() => {
                        rowToRemove.remove();
                        
                        // Check if table is now empty
                        const tbody = document.querySelector('table.table-bordered tbody');
                        if (tbody && tbody.children.length === 0) {
                            // Replace table with empty message
                            const tableContainer = document.querySelector('.table-responsive');
                            if (tableContainer) {
                                tableContainer.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Aucune dette trouvée</div>';
                            }
                        }
                    }, 500);
                }
            } else {
                showToast('Erreur', data.message || 'Erreur lors de l\'annulation', 'error');
                deleteModal.hide();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            spinner.classList.add('d-none');
            document.getElementById('confirmDeleteBtn').disabled = false;
            showToast('Erreur', 'Une erreur est survenue lors de l\'annulation', 'error');
            deleteModal.hide();
        });
    }
    
    // Load client invoices
    function loadClientInvoices(clientId, selectedInvoiceId = null) {
        if (!clientId) {
            const invoiceSelect = document.getElementById('vente_id');
            invoiceSelect.innerHTML = '<option value="">Aucune facture associée</option>';
            return;
        }
        
        // Show loading state
        const invoiceSelect = document.getElementById('vente_id');
        invoiceSelect.innerHTML = '<option value="">Chargement des factures...</option>';
        invoiceSelect.disabled = true;
        
        fetch(`../../../src/api/dettes/get_client_invoices.php?client_id=${clientId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Re-enable and reset select
                invoiceSelect.disabled = false;
                invoiceSelect.innerHTML = '<option value="">Aucune facture associée</option>';
                
                if (data.success && data.invoices && data.invoices.length > 0) {
                    data.invoices.forEach(invoice => {
                        const option = document.createElement('option');
                        option.value = invoice.id;
                        option.textContent = `${invoice.numero_facture} (${formatMontant(invoice.montant_total)} F)`;
                        if (selectedInvoiceId && invoice.id == selectedInvoiceId) {
                            option.selected = true;
                        }
                        invoiceSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                invoiceSelect.disabled = false;
                invoiceSelect.innerHTML = '<option value="">Aucune facture associée</option>';
                showToast('Erreur', 'Impossible de charger les factures du client', 'error');
            });
    }
    
    // Format amount with thousands separator
    function formatMontant(montant) {
        return new Intl.NumberFormat('fr-FR').format(montant);
    }
    
    // Format date to dd/mm/yyyy
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
    }
    
    // Show toast notification
    function showToast(title, message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        
        // Create toast element
        const toastElement = document.createElement('div');
        toastElement.classList.add('toast', 'show', 'mb-3');
        toastElement.setAttribute('role', 'alert');
        toastElement.setAttribute('aria-live', 'assertive');
        toastElement.setAttribute('aria-atomic', 'true');
        
        // Set background color based on type
        let bgClass = 'bg-primary';
        let iconClass = 'fa-info-circle';
        
        if (type === 'success') {
            bgClass = 'bg-success';
            iconClass = 'fa-check-circle';
        } else if (type === 'error') {
            bgClass = 'bg-danger';
            iconClass = 'fa-exclamation-circle';
        } else if (type === 'warning') {
            bgClass = 'bg-warning';
            iconClass = 'fa-exclamation-triangle';
        }
        
        // Set toast content
        toastElement.innerHTML = `
            <div class="toast-header ${bgClass} text-white">
                <i class="fas ${iconClass} me-2"></i>
                <strong class="me-auto">${title}</strong>
                <small>Maintenant</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        // Add toast to container
        toastContainer.appendChild(toastElement);
        
        // Remove toast after 5 seconds
        setTimeout(() => {
            toastElement.remove();
        }, 5000);
    }
});
