<!-- FIFO Batch Details Modal -->
<div class="modal fade" id="batch-modal" tabindex="-1" role="dialog" aria-labelledby="batch-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="batch-modal-label">Détails des lots FIFO</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Produit:</strong> <span id="batch-product-name"></span></p>
                        <p><strong>Code:</strong> <span id="batch-product-code"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Unité de mesure:</strong> <span id="batch-product-unit"></span></p>
                        <p><strong>Stock total:</strong> <span id="batch-product-stock"></span></p>
                    </div>
                </div>

                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Lots disponibles (FIFO)</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date d'entrée</th>
                                        <th>Quantité restante</th>
                                        <th>Prix d'achat</th>
                                        <th>Valeur</th>
                                        <th>Référence</th>
                                    </tr>
                                </thead>
                                <tbody id="batch-list">
                                    <!-- Batch data will be loaded here -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">Valeur totale du stock:</th>
                                        <th id="batch-total-value" colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Méthode FIFO:</strong> Lors d'une vente, le système utilise automatiquement les lots les plus anciens en premier (Premier Entré, Premier Sorti) pour calculer le coût des marchandises vendues et le bénéfice.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
