<?php
/**
 * ProductCodeGenerator – DATE-BASED FORMAT ONLY
 * Creates unique codes like 20250624-001, 20250624-002 …
 */
class ProductCodeGenerator
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /** Always returns YYYYMMDD-nnn */
    public function generateCode(): string
    {
        $today   = date('Ymd');            // 20250624
        $pattern = $today . '-%';

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM produits WHERE code LIKE :pattern
        ");
        $stmt->execute(['pattern' => $pattern]);
        $next = (int)$stmt->fetchColumn() + 1;        // 1, 2, …

        return $today . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}

/** Helper wrapper */
function generateProductCode(PDO $pdo): string
{
    return (new ProductCodeGenerator($pdo))->generateCode();
}
