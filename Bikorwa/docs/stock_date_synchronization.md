# Synchronisation des Dates de Mouvements de Stock

## Problème Résolu

Auparavant, tous les mouvements de stock utilisaient `CURRENT_TIMESTAMP` pour `date_mouvement`, ce qui ne permettait pas de maintenir une cohérence temporelle avec les événements liés (ventes, création de produits).

## Solution Implémentée

### 1. Modifications du Code

#### A. Modèle Stock (`src/models/Stock.php`)
- **Méthode `ajouterStock()`** : Ajout du paramètre optionnel `$date_mouvement`
- **Méthode `retirerStock()`** : Ajout du paramètre optionnel `$date_mouvement`
- **Nouvelle méthode `enregistrerStockInitial()`** : Utilise automatiquement la date de création du produit

#### B. API de Vente (`src/api/ventes/add_vente.php`)
- Les mouvements de sortie utilisent maintenant `date_vente` au lieu de `CURRENT_TIMESTAMP`
- Synchronisation automatique lors de la création des ventes

### 2. Règles de Synchronisation

| Type de Mouvement | Date Utilisée | Source |
|-------------------|---------------|---------|
| **Sortie** (vente) | `date_vente` | Table `ventes` |
| **Entrée** (stock initial) | `date_creation` | Table `produits` |
| **Entrée** (réapprovisionnement) | Date personnalisée ou `CURRENT_TIMESTAMP` | Saisie manuelle |

### 3. Utilisation

#### Pour les Ventes (Automatique)
```php
// Le code existant fonctionne automatiquement
// Les mouvements de sortie utilisent maintenant date_vente
```

#### Pour le Stock Initial
```php
$stock = new Stock($pdo);
$stock->enregistrerStockInitial($produit_id, $quantite, $prix_unitaire, $utilisateur_id, $note);
```

#### Pour les Ajustements Manuels
```php
$stock = new Stock($pdo);
// Avec date personnalisée
$stock->ajouterStock($produit_id, $quantite, $prix_unitaire, $reference, $utilisateur_id, $note, $date_custom);
// Sans date (utilise CURRENT_TIMESTAMP)
$stock->ajouterStock($produit_id, $quantite, $prix_unitaire, $reference, $utilisateur_id, $note);
```

### 4. Migration des Données Existantes

Un script de migration est disponible pour synchroniser les données existantes :

```bash
php src/utils/synchronize_stock_dates.php
```

Ce script :
- Met à jour les mouvements de sortie avec les dates de vente correspondantes
- Met à jour les premiers mouvements d'entrée avec les dates de création des produits
- Génère un rapport des modifications effectuées

### 5. Avantages

1. **Cohérence temporelle** : Les mouvements de stock reflètent les vraies dates des événements
2. **Traçabilité améliorée** : Meilleur suivi historique des mouvements
3. **Rapports précis** : Les analyses par période sont maintenant exactes
4. **Comptabilité cohérente** : Les calculs de coût des marchandises sont plus précis

### 6. Impact sur les Rapports

- **Rapports de ventes** : Les calculs de COGS peuvent maintenant être basés sur les vraies dates
- **Analyses de stock** : Les mouvements sont correctement datés
- **Historique** : Traçabilité complète des opérations

### 7. Compatibilité

- **Rétrocompatible** : Le code existant continue de fonctionner
- **Optionnel** : Les dates personnalisées sont optionnelles
- **Flexible** : Possibilité d'utiliser des dates custom ou automatiques

## Notes Techniques

- Les transactions sont utilisées pour garantir la cohérence
- La validation des dates est effectuée côté base de données
- Les erreurs sont gérées avec rollback automatique
- Le script de migration peut être exécuté plusieurs fois sans risque
