$(document).ready(function() {
    console.log('Initializing nouvelle_vente_script.js');
    
    if (typeof BASE_URL === 'undefined') {
        console.error('BASE_URL is not defined! This will cause AJAX requests to fail.');
        // Fallback value if somehow BASE_URL is not defined
        BASE_URL = window.location.origin + '/Bikorwa';
    }
    
    // Initialize Select2 for clients with AJAX
    $('#client').select2({
        ajax: {
            url: BASE_URL + '/src/api/clients/get_clients.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    page: params.page || 1
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                return {
                    results: data.clients.map(function(client) {
                        return {
                            id: client.id,
                            text: client.nom + (client.telephone ? ' (' + client.telephone + ')' : '')
                        };
                    }),
                    pagination: {
                        more: (params.page * 10) < data.total_count
                    }
                };
            },
            cache: true
        },
        placeholder: 'Rechercher un client',
        minimumInputLength: 1
    });

    // Initialize Select2 for products with AJAX
    $('.select2-products').select2({
        ajax: {
            url: BASE_URL + '/src/api/produits/get_produits.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term,
                    page: params.page || 1,
                    with_stock: true // Only get products with stock
                };
            },
            processResults: function(data, params) {
                params.page = params.page || 1;
                console.log('Product API response:', data);
                
                // Check if data has the right structure
                if (!data.success || !data.produits) {
                    console.error('Invalid API response format:', data);
                    return { results: [] };
                }
                
                return {
                    results: data.produits.map(function(produit) {
                        return {
                            id: produit.id,
                            text: produit.nom + ' (' + produit.code + ')',
                            prix: produit.prix_vente,
                            stock: produit.quantite_stock
                        };
                    }),
                    pagination: {
                        more: (params.page * 10) < data.total_count
                    }
                };
            },
            cache: true
        },
        placeholder: 'Rechercher un produit',
        minimumInputLength: 1
    });

    // When a product is selected, update price and stock info
    $('#produit').on('select2:select', function(e) {
        var data = e.params.data;
        $('#prix').val(data.prix);
        $('#stock-disponible').val(data.stock);
        
        // Reset quantity to 1
        $('#quantite').val(1);
        
        // Make sure quantity does not exceed stock
        var maxStock = parseFloat(data.stock);
        $('#quantite').attr('max', maxStock);

        // Add button to view FIFO batches if not already present
        if ($('#view-batches-btn').length === 0) {
            var batchButton = $('<button type="button" class="btn btn-info btn-sm ml-2" id="view-batches-btn" title="Voir les lots FIFO">' +
                              '<i class="fas fa-layer-group"></i></button>');
            $('#stock-disponible').after(batchButton);
            
            // Add click handler for the batch button
            $('#view-batches-btn').on('click', function(e) {
                e.preventDefault();
                var productId = $('#produit').val();
                if (productId) {
                    loadFifoBatches(productId);
                } else {
                    toastr.warning('Veuillez sélectionner un produit d\'abord');
                }
            });
        }
    });

    // Prevent adding more than available stock
    $('#quantite').on('input', function() {
        var stock = parseFloat($('#stock-disponible').val()) || 0;
        var quantity = parseFloat($(this).val()) || 0;
        
        if (quantity > stock) {
            toastr.warning('La quantité ne peut pas dépasser le stock disponible');
            $(this).val(stock);
        }
    });

    // Add product to the sale
    $('#ajouter-produit').on('click', function() {
        var produitSelect = $('#produit');
        var produitId = produitSelect.val();
        
        if (!produitId) {
            toastr.error('Veuillez sélectionner un produit');
            return;
        }
        
        var produitData = produitSelect.select2('data')[0];
        var prix = parseFloat($('#prix').val());
        var quantite = parseFloat($('#quantite').val());
        
        if (isNaN(prix) || prix <= 0) {
            toastr.error('Le prix doit être supérieur à 0');
            return;
        }
        
        if (isNaN(quantite) || quantite <= 0) {
            toastr.error('La quantité doit être supérieure à 0');
            return;
        }
        
        var stock = parseFloat($('#stock-disponible').val());
        if (quantite > stock) {
            toastr.error('La quantité dépasse le stock disponible');
            return;
        }
        
        // Check if product already exists in the table
        var existingRow = $('#produits-table tbody').find('tr[data-id="' + produitId + '"]');
        if (existingRow.length > 0) {
            // Update existing row
            var currentQty = parseFloat(existingRow.find('.product-quantity').text());
            var newQty = currentQty + quantite;
            
            if (newQty > stock) {
                toastr.error('La quantité totale dépasse le stock disponible');
                return;
            }
            
            existingRow.find('.product-quantity').text(newQty.toFixed(2));
            
            var rowTotal = prix * newQty;
            existingRow.find('.product-total').text(rowTotal.toFixed(2));
            existingRow.data('total', rowTotal);
        } else {
            // Add new row
            var total = prix * quantite;
            var newRow = $('<tr data-id="' + produitId + '" data-price="' + prix + '" data-total="' + total + '">' +
                '<td>' + produitData.text + '<input type="hidden" name="produits[' + produitId + '][id]" value="' + produitId + '"></td>' +
                '<td>' + prix.toFixed(2) + '<input type="hidden" name="produits[' + produitId + '][prix]" value="' + prix + '"></td>' +
                '<td class="product-quantity">' + quantite.toFixed(2) + '<input type="hidden" name="produits[' + produitId + '][quantite]" value="' + quantite + '"></td>' +
                '<td class="product-total">' + total.toFixed(2) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn btn-sm btn-danger remove-product">' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>');
            
            $('#produits-table tbody').append(newRow);
        }
        
        // Reset product selection
        produitSelect.val(null).trigger('change');
        $('#prix').val('');
        $('#quantite').val(1);
        $('#stock-disponible').val('');
        
        // Update total
        updateTotal();
    });
    
    // Remove product from the sale
    $(document).on('click', '.remove-product', function() {
        $(this).closest('tr').remove();
        updateTotal();
    });
    
    // Update total amount
    function updateTotal() {
        var total = 0;
        $('#produits-table tbody tr').each(function() {
            total += parseFloat($(this).data('total'));
        });
        
        $('#total-montant').text(total.toFixed(2) + ' BIF');
        $('#montant-total').val(total.toFixed(2));
        
        // Update payment status based on amount paid
        updatePaymentStatus();
    }
    
    // Update payment status based on amount paid
    $('#montant-paye').on('input', function() {
        updatePaymentStatus();
    });
    
    function updatePaymentStatus() {
        var total = parseFloat($('#montant-total').val()) || 0;
        var paid = parseFloat($('#montant-paye').val()) || 0;
        
        if (paid === 0) {
            $('#statut-paiement').val('credit');
        } else if (paid < total) {
            $('#statut-paiement').val('partiel');
        } else {
            $('#statut-paiement').val('paye');
        }
    }
    
    // Preview sale before confirming
    $('#preview-btn').on('click', function() {
        var produits = $('#produits-table tbody tr');
        if (produits.length === 0) {
            toastr.error('Veuillez ajouter au moins un produit à la vente');
            return;
        }
        
        // Get client name
        var clientId = $('#client').val();
        var clientName = clientId ? $('#client').select2('data')[0].text : 'Aucun';
        
        // Format date
        var dateVente = $('#date-vente').val();
        var formattedDate = new Date(dateVente).toLocaleString('fr-FR');
        
        // Get payment status
        var statutPaiement = $('#statut-paiement').val();
        var statutPaiementText = {
            'paye': 'Payé',
            'partiel': 'Paiement partiel',
            'credit': 'Crédit'
        }[statutPaiement];
        
        // Get note
        var note = $('#note').val() || '-';
        
        // Update preview modal
        $('#preview-client').text(clientName);
        $('#preview-date').text(formattedDate);
        $('#preview-montant-total').text($('#montant-total').val());
        $('#preview-statut-paiement').text(statutPaiementText);
        $('#preview-note').text(note);
        
        // Clear and repopulate products table
        var previewProducts = $('#preview-produits');
        previewProducts.empty();
        
        produits.each(function() {
            var produitName = $(this).find('td:first').text();
            var prix = parseFloat($(this).data('price')).toFixed(2);
            var quantite = $(this).find('.product-quantity').text();
            var total = parseFloat($(this).data('total')).toFixed(2);
            
            previewProducts.append(
                '<tr>' +
                    '<td>' + produitName + '</td>' +
                    '<td>' + prix + '</td>' +
                    '<td>' + quantite + '</td>' +
                    '<td>' + total + '</td>' +
                '</tr>'
            );
        });
        
        // Set preview total
        $('#preview-total').text($('#montant-total').val() + ' BIF');
        
        // Show preview modal
        $('#preview-modal').modal('show');
    });
    
    // Confirm sale
    $('#confirmer-vente').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        // Disable button and show loading
        button.html('<i class="fas fa-spinner fa-spin"></i> Traitement en cours...');
        button.attr('disabled', true);
        
        // Prepare form data
        var formData = new FormData();
        
        // Add client ID if selected
        var clientId = $('#client').val();
        if (clientId) {
            formData.append('client_id', clientId);
        }
        
        // Add date, payment info, and note
        formData.append('date_vente', $('#date-vente').val());
        formData.append('montant_total', $('#montant-total').val());
        formData.append('montant_paye', $('#montant-paye').val());
        formData.append('statut_paiement', $('#statut-paiement').val());
        formData.append('note', $('#note').val());
        
        // Add products
        var produits = [];
        $('#produits-table tbody tr').each(function() {
            var produitId = $(this).data('id');
            var prix = parseFloat($(this).data('price'));
            var quantite = parseFloat($(this).find('.product-quantity').text());
            
            produits.push({
                id: produitId,
                prix: prix,
                quantite: quantite
            });
        });
        
        formData.append('produits', JSON.stringify(produits));
        
        // Submit form via AJAX
        $.ajax({
            url: BASE_URL + '/src/api/ventes/add_vente.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                if (response.success) {
                    // Show success message
                    toastr.success('Vente enregistrée avec succès');
                    
                    // Update success modal and show it
                    $('#facture-number').text(response.numero_facture);
                    $('#print-facture').attr('href', BASE_URL + '/src/views/ventes/facture.php?id=' + response.vente_id);
                    $('#success-modal').modal('show');
                } else {
                    // Show error message
                    toastr.error(response.message || 'Une erreur est survenue');
                }
            },
            error: function(xhr) {
                // Hide preview modal
                $('#preview-modal').modal('hide');
                
                // Reset button
                button.html(originalText);
                button.attr('disabled', false);
                
                // Show error message
                var errorMessage = 'Une erreur est survenue';
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    console.error('Error parsing response', e);
                }
                
                toastr.error(errorMessage);
            }
        });
    });
    
    // Function to load and display FIFO batches for a product
    function loadFifoBatches(productId) {
        // Show loading in the modal
        $('#batch-list').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement des lots...</td></tr>');
        $('#batch-modal').modal('show');
        
        // Fetch batch data via AJAX
        $.ajax({
            url: BASE_URL + '/src/ajax/get_product_batches.php',
            type: 'GET',
            data: { product_id: productId },
            dataType: 'json',
            success: function(response) {
                // Update product info
                $('#batch-product-name').text(response.product.nom);
                $('#batch-product-code').text(response.product.code);
                $('#batch-product-unit').text(response.product.unite_mesure);
                $('#batch-product-stock').text(response.product.stock_total + ' ' + response.product.unite_mesure);
                
                // Clear and populate batch list
                $('#batch-list').empty();
                if (response.batches.length === 0) {
                    $('#batch-list').html('<tr><td colspan="5" class="text-center">Aucun lot disponible</td></tr>');
                } else {
                    $.each(response.batches, function(index, batch) {
                        var date = new Date(batch.date_mouvement).toLocaleDateString('fr-FR');
                        var valeur = (parseFloat(batch.prix_unitaire) * parseFloat(batch.quantite_restante)).toFixed(2);
                        
                        $('#batch-list').append(
                            '<tr' + (index === 0 ? ' class="table-primary"' : '') + '>' +
                                '<td>' + date + '</td>' +
                                '<td>' + parseFloat(batch.quantite_restante).toFixed(2) + ' ' + response.product.unite_mesure + '</td>' +
                                '<td>' + parseFloat(batch.prix_unitaire).toFixed(2) + ' BIF</td>' +
                                '<td>' + valeur + ' BIF</td>' +
                                '<td>' + (batch.reference || '-') + '</td>' +
                            '</tr>'
                        );
                    });
                }
                
                // Update total value
                $('#batch-total-value').text(parseFloat(response.total_batch_value).toFixed(2) + ' BIF');
            },
            error: function(xhr) {
                // Show error message
                $('#batch-list').html('<tr><td colspan="5" class="text-center text-danger">Erreur lors du chargement des lots</td></tr>');
                console.error('Error loading batches:', xhr);
            }
        });
    }
});
