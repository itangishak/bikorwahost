/**
 * BIKORWA SHOP – Expense Management JavaScript
 * Handles all AJAX operations for the expense management module.
 */

document.addEventListener('DOMContentLoaded', function () {
    // ---------------------------------------------------------------------
    // Robust toast helper – never throws if Bootstrap is missing or DOM not ready
    // ---------------------------------------------------------------------
    function showToast(message, type = 'info') {
        // Ultra-light XSS-safe “escape”
        const safeText = String(message).replace(/[&<>"]/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
        }[c]));

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success',
                title: type.charAt(0).toUpperCase() + type.slice(1),
                html: safeText,
                timer: 5000,
                timerProgressBar: true,
                allowOutsideClick: true,
                allowEscapeKey: true,
                allowEnterKey: true,
                didOpen: () => {
                    // Ensure no aria-hidden is set on parent elements
                    const container = Swal.getContainer();
                    if (container) {
                        container.removeAttribute('aria-hidden');
                        container.setAttribute('aria-modal', 'true');
                        container.setAttribute('role', 'dialog');
                        // Remove aria-hidden from any parent elements
                        let parent = container.parentElement;
                        while (parent && parent !== document.body) {
                            parent.removeAttribute('aria-hidden');
                            parent = parent.parentElement;
                        }
                    }
                }
            });
        } else {
            console[type === 'error' ? 'error' : 'log'](`Alert: ${type}: ${safeText}`);
            alert(`${type.toUpperCase()}: ${safeText}`);
        }
    }

    // ---------------------------------------------------------------------
    // Loading spinner helper
    // ---------------------------------------------------------------------
    function setButtonLoading(button, isLoading) {
        const spinner = button.querySelector('.spinner-border');
        if (isLoading) {
            button.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
        } else {
            button.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    }

    // ---------------------------------------------------------------------
    // Generic AJAX error handler
    // ---------------------------------------------------------------------
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        try {
            const response = JSON.parse(xhr.responseText);
            showToast(response.message || 'Une erreur est survenue.', 'error');
        } catch (e) {
            showToast('Une erreur est survenue lors de la communication avec le serveur.', 'error');
        }
    }

    // ---------------------------------------------------------------------
    // Client-side form validation helper
    // ---------------------------------------------------------------------
    function validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
            field.addEventListener('input', function () {
                if (this.value.trim()) this.classList.remove('is-invalid');
            });
        });

        if (!isValid) showToast('Veuillez remplir tous les champs obligatoires.', 'warning');
        return isValid;
    }

    // ---------------------------------------------------------------------
    // Helpers to add / update / remove rows in the expenses table
    // ---------------------------------------------------------------------
    function updateExpenseRow(expense) {
        const row = document.querySelector(`tr[data-expense-id="${expense.id}"]`);
        if (row) {
            row.querySelector('.expense-description').textContent = expense.description;
            row.querySelector('.expense-amount').textContent =
                new Intl.NumberFormat('fr-FR').format(expense.montant) + ' FBU';
            row.querySelector('.expense-category').textContent = expense.categorie_nom;
            row.querySelector('.expense-payment-mode').textContent = expense.mode_paiement;
        }
    }

    function addExpenseToTable(expense) {
        const tbody = document.querySelector('.table tbody');
        if (!tbody) return;

        const newRow = document.createElement('tr');
        newRow.setAttribute('data-expense-id', expense.id);
        newRow.innerHTML = `
            <td class="expense-description">${expense.description}</td>
            <td class="expense-amount">${new Intl.NumberFormat('fr-FR').format(expense.montant)} FBU</td>
            <td class="expense-category">${expense.categorie_nom}</td>
            <td class="expense-payment-mode">${expense.mode_paiement}</td>
            <td>
                <button class="btn btn-sm btn-info view-depense-btn" data-id="${expense.id}">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-warning edit-depense-btn" data-id="${expense.id}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger delete-depense-btn" data-id="${expense.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.insertBefore(newRow, tbody.firstChild);

        // Update total badge
        const totalBadge = document.querySelector('.badge.bg-primary');
        if (totalBadge) {
            const current = parseInt(totalBadge.textContent.match(/\d+/)[0] || '0', 10);
            totalBadge.textContent = `Total: ${current + 1}`;
        }

        attachEventListenersToRow(newRow);
    }

    function removeExpenseRow(expenseId) {
        const row = document.querySelector(`tr[data-expense-id="${expenseId}"]`);
        if (!row) return;
        row.remove();

        const totalBadge = document.querySelector('.badge.bg-primary');
        if (totalBadge) {
            const current = parseInt(totalBadge.textContent.match(/\d+/)[0] || '1', 10);
            totalBadge.textContent = `Total: ${Math.max(0, current - 1)}`;
        }

        const tbody = document.querySelector('.table tbody');
        if (tbody && tbody.children.length === 0) {
            const cardBody = document.querySelector('.card-body');
            if (cardBody) {
                cardBody.innerHTML =
                    '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Aucune dépense aujourd\'hui.</div>';
            }
        }
    }

    function attachEventListenersToRow(row) {
        row.querySelector('.view-depense-btn')?.addEventListener('click', handleViewExpense);
        row.querySelector('.edit-depense-btn')?.addEventListener('click', handleEditExpense);
        row.querySelector('.delete-depense-btn')?.addEventListener('click', handleDeleteExpense);
    }

    // ---------------------------------------------------------------------
    // ===============  EXPENSE OPERATIONS  =================================
    // ---------------------------------------------------------------------

    // ---------- Add Expense ----------
    const addExpenseModal = document.getElementById('addExpenseModal');
    const addExpenseForm = document.getElementById('addExpenseForm');
    addExpenseForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add('was-validated'); return; }

        // Ensure date_depense has a value
        const dateDepenseInput = this.querySelector('#date_depense');
        if (dateDepenseInput && !dateDepenseInput.value) {
            const today = new Date().toISOString().split('T')[0];
            dateDepenseInput.value = today;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true);

        fetch('add_expense.php', { method: 'POST', body: new FormData(this) })
            .then(res => { if (!res.ok) throw new Error('Network error'); return res.json(); })
            .then(data => {
                setButtonLoading(submitBtn, false);
                if (data.success) {
                    showToast(data.message || 'Dépense ajoutée avec succès', 'success');
                    this.reset(); this.classList.remove('was-validated');
                    // Refresh the page after successful insertion
                    setTimeout(() => location.reload(), 1500); // Delay to allow toast to be visible
                } else showToast(data.message || 'Erreur lors de l\'ajout', 'error');
            })
            .catch(err => {
                setButtonLoading(submitBtn, false);
                console.error(err);
                showToast('Erreur réseau: ' + err.message, 'error');
            });
    });

    // ---------- Edit Expense ----------
    const editExpenseModal = document.getElementById('editExpenseModal');
    const editExpenseForm = document.getElementById('editExpenseForm');
    const updateExpenseBtn = document.getElementById('updateExpenseBtn');

    document.querySelectorAll('.edit-depense-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            console.log('Opening edit modal for ID:', id);
            
            // Set the ID in hidden field
            const idInput = document.getElementById('editExpenseId');
            if (idInput) {
                idInput.value = id;
                console.log('Set hidden input value to:', idInput.value);
            } else {
                console.error('Could not find editExpenseId input');
            }
            
            // Populate form with expense data
            fetch(`get_expense_details.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    console.log('Received expense data:', data);
                    if (data.success && data.expense) {
                        const d = data.expense;
                        // Handle date format if needed
                        let dateDepense = d.date_depense;
                        if (dateDepense.includes('/')) {
                            // Convert DD/MM/YYYY to YYYY-MM-DD for input field
                            const parts = dateDepense.split('/');
                            dateDepense = `${parts[2]}-${parts[1]}-${parts[0]}`;
                        }
                        
                        // Safely set values only if elements exist
                        const descriptionEl = document.getElementById('edit_description');
                        if (descriptionEl) descriptionEl.value = d.description || '';
                        else console.error('Element edit_description not found');
                        
                        const montantEl = document.getElementById('edit_montant');
                        if (montantEl) montantEl.value = d.montant || '';
                        else console.error('Element edit_montant not found');
                        
                        const dateEl = document.getElementById('edit_date_depense');
                        if (dateEl) dateEl.value = dateDepense || '';
                        else console.error('Element edit_date_depense not found');
                        
                        const categorieEl = document.getElementById('edit_categorie_id');
                        if (categorieEl) categorieEl.value = d.categorie_id || '';
                        else console.error('Element edit_categorie_id not found');
                        
                        const modePaiementEl = document.getElementById('edit_mode_paiement');
                        if (modePaiementEl) modePaiementEl.value = d.mode_paiement || '';
                        else console.error('Element edit_mode_paiement not found');
                        
                        const referenceEl = document.getElementById('edit_reference_paiement');
                        if (referenceEl) referenceEl.value = d.reference_paiement || '';
                        else console.error('Element edit_reference_paiement not found');
                        
                        // Try different possible IDs for note field
                        let noteEl = document.getElementById('edit_note');
                        if (!noteEl) noteEl = document.getElementById('editNote');
                        if (!noteEl) noteEl = document.getElementById('note');
                        if (noteEl) noteEl.value = d.note || '';
                        else console.error('Element for note field not found (tried edit_note, editNote, note)');
                        
                        console.log('Populated form fields with date:', dateDepense);
                    } else {
                        console.error('Failed to load expense data', data);
                        showToast('Erreur lors du chargement des données', 'error');
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    showToast('Erreur lors du chargement des données', 'error');
                });
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editExpenseModal'));
            modal.show();
        });
    });

    window.editExpenseSubmit = function(event) {
        event = event || window.event;
        if (event?.preventDefault) event.preventDefault();
        
        const form = event?.target || this;
        if (!form || form.tagName !== 'FORM') return;
        
        // Explicitly get ID from hidden input
        const idInput = form.querySelector('[name="id"], #editExpenseId');
        if (!idInput || !idInput.value) {
            console.error('ID input missing or empty', idInput);
            showToast('ID de dépense manquant', 'error');
            return;
        }
        
        console.log('Submitting edit form with ID:', idInput.value);
        
        const formData = new FormData(form);
        const data = {
            id: parseInt(idInput.value), // Get from verified input
            date_depense: formData.get('date_depense'),
            montant: formData.get('montant'),
            categorie_id: parseInt(formData.get('categorie_id')),
            mode_paiement: formData.get('mode_paiement'),
            description: formData.get('description') || '',
            reference_paiement: formData.get('reference_paiement') || '',
            note: formData.get('note') || ''
        };
        
        console.log('Submitting data:', data);
        
        const submitBtn = form.querySelector('[type="submit"]');
        setButtonLoading(submitBtn, true);
        
        fetch('../../api/depenses/update_expense.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(res => {
            console.log('Response status:', res.status);
            console.log('Response headers:', res.headers.get('content-type'));
            return res.text();
        })
        .then(text => {
            console.log('Raw response text:', text);
            try {
                const response = JSON.parse(text);
                if (response.success) {
                    showToast('Dépense mise à jour avec succès', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    console.error('Server error message:', response.message);
                    showToast(response.message || 'Erreur lors de la mise à jour', 'error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                showToast('Erreur de format dans la réponse du serveur', 'error');
            }
            setButtonLoading(submitBtn, false);
        })
        .catch(err => {
            setButtonLoading(submitBtn, false);
            console.error('Update error:', err);
            showToast('Erreur réseau lors de la mise à jour', 'error');
        });
    }

    // Attach the submit handler to the form if it exists
    const editExpenseFormInJour = document.getElementById('editExpenseForm');
    if (editExpenseFormInJour) {
        editExpenseFormInJour.addEventListener('submit', window.editExpenseSubmit);
    }

    // ---------- View Expense ----------
    const viewExpenseModal = document.getElementById('viewExpenseModal');
    const expenseDetails = document.getElementById('expenseDetails');

    function handleViewExpense() {
        const id = this.getAttribute('data-id');
        const modal = new bootstrap.Modal(viewExpenseModal);
        modal.show();

        expenseDetails.classList.add('d-none');
        viewExpenseModal.querySelector('.spinner-border')?.parentElement.classList.remove('d-none');

        fetch(`get_expense.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                viewExpenseModal.querySelector('.spinner-border')?.parentElement.classList.add('d-none');
                if (!data.success) { showToast(data.message, 'error'); modal.hide(); return; }

                const e = data.expense;
                const modeLabels = { 'Espèces': 'Espèces', 'Cheque': 'Chèque', 'Virement': 'Virement', 'Carte': 'Carte', 'Mobile Money': 'Mobile Money' };
                const viewMap = {
                    view_id:                e.id,
                    view_date_depense:      new Date(e.date_depense).toLocaleDateString('fr-FR'),
                    view_montant:           new Intl.NumberFormat('fr-FR').format(e.montant) + ' BIF',
                    view_categorie:         e.categorie_nom,
                    view_mode_paiement:     modeLabels[e.mode_paiement] || e.mode_paiement,
                    view_description:       e.description || '-',
                    view_reference:         e.reference_paiement || '-',
                    view_note:              e.note || '-',
                    view_date_ajout:        new Date(e.date_enregistrement).toLocaleString('fr-FR'),
                    view_ajoute_par:        e.utilisateur_nom || e.utilisateur_id
                };

                for (const [id, val] of Object.entries(viewMap)) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = val;
                }

                expenseDetails.classList.remove('d-none');
            })
            .catch(err => {
                console.error(err);
                showToast('Erreur lors de la récupération des détails.', 'error');
                modal.hide();
            });
    }

    document.querySelectorAll('.view-depense-btn').forEach(btn => btn.addEventListener('click', handleViewExpense));

    // ---------- Delete Expense ----------
    function handleDeleteExpense() {
        const id = this.getAttribute('data-id');
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Vous ne pourrez pas revenir en arrière !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer !',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                setButtonLoading(this, true);

                fetch('delete_expense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`,
                })
                    .then(res => res.json())
                    .then(data => {
                        setButtonLoading(this, false);
                        if (data.success) {
                            showToast(data.message, 'success');
                            removeExpenseRow(id);
                            // Refresh the page after successful deletion
                            setTimeout(() => location.reload(), 1500); // Delay to allow toast to be visible
                        } else showToast(data.message, 'error');
                    })
                    .catch(err => {
                        setButtonLoading(this, false);
                        console.error(err);
                        showToast('Erreur lors de la suppression.', 'error');
                    });
            }
        });
    }

    document.querySelectorAll('.delete-depense-btn').forEach(btn => btn.addEventListener('click', handleDeleteExpense));

    // ---------------------------------------------------------------------
    // ===============  CATEGORY OPERATIONS  ===============================
    // ---------------------------------------------------------------------

    const addCategoryModal = document.getElementById('addCategoryModal');
    const addCategoryForm = document.getElementById('addCategoryForm');
    addCategoryForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add('was-validated'); return; }

        const submitBtn = this.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true);
        fetch('add_category.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                setButtonLoading(submitBtn, false);
                if (data.success) {
                    showToast(data.message || 'Catégorie ajoutée', 'success');
                    this.reset(); this.classList.remove('was-validated');
                 
                    // Refresh the page after successful category addition
                    setTimeout(() => location.reload(), 1500); // Delay to allow toast to be visible
                } else showToast(data.message || 'Erreur lors de l\'ajout', 'error');
            })
            .catch(err => {
                setButtonLoading(submitBtn, false);
                console.error(err);
                showToast('Erreur réseau.', 'error');
            });
    });

    const saveCategoryBtn = document.getElementById('saveCategoryBtn');
    saveCategoryBtn?.addEventListener('click', function () {
        if (!validateForm(addCategoryForm)) return;
        setButtonLoading(this, true);

        fetch('add_category.php', { method: 'POST', body: new FormData(addCategoryForm) })
            .then(res => res.json())
            .then(data => {
                setButtonLoading(this, false);
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(addCategoryModal).hide();
                    addCategoryForm.reset();

                    if (data.category) {
                        ['categorie_id', 'edit_categorie_id', 'categorie'].forEach(id => {
                            const sel = document.getElementById(id);
                            if (sel) sel.add(new Option(data.category.nom, data.category.id));
                        });
                    }
                } else showToast(data.message, 'error');
            })
            .catch(err => {
                setButtonLoading(this, false);
                console.error(err);
                showToast('Erreur lors de l\'ajout de la catégorie.', 'error');
            });
    });
});
