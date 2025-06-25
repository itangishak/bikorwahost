-- Migration script to add indexes for better report performance
-- This script only adds indexes and doesn't modify existing table structure

USE bikorwa_shop;

-- Add indexes for better performance on reports (only if they don't exist)
CREATE INDEX IF NOT EXISTS idx_ventes_date_statut ON ventes(date_vente, statut_vente);
CREATE INDEX IF NOT EXISTS idx_details_ventes_vente ON details_ventes(vente_id);
CREATE INDEX IF NOT EXISTS idx_paiements_dettes_date ON paiements_dettes(date_paiement);
CREATE INDEX IF NOT EXISTS idx_depenses_date ON depenses(date_depense);
CREATE INDEX IF NOT EXISTS idx_salaires_date ON salaires(date_paiement);