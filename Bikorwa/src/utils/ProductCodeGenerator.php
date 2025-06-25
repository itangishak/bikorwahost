<?php
/* utils/ProductCodeGenerator.php  –  simplified, no beginTransaction() */

class ProductCodeGenerator
{
       private $pdo;                // ← no type-hint, works on PHP ≤ 7.3

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** YYYYMMDD-nnn – relies on the caller’s transaction (if any) */
    public function generateCode(): string
    {
        $today   = date('Ymd');
        $pattern = $today . '-%';

        // Just read the last code; caller decides about locking.
        $stmt = $this->pdo->prepare("
            SELECT code
            FROM produits
            WHERE code LIKE :pattern
            ORDER BY code DESC
            LIMIT 1
            FOR UPDATE                   -- still protects against races
        ");
        $stmt->execute(['pattern' => $pattern]);
        $last = $stmt->fetchColumn();

        $next = $last
            ? (intval(explode('-', $last)[1]) + 1)
            : 1;

        return $today . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}

/* Helper */
function generateProductCode(PDO $pdo): string
{
    return (new ProductCodeGenerator($pdo))->generateCode();
}
