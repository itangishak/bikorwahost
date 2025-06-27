<?php
class DbSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table;

    public function __construct(PDO $pdo, $table = 'sessions') {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string|false {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = :id AND expires > NOW() LIMIT 1");
        $stmt->execute(['id' => $id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['data'];
        }
        return '';
    }

    public function write($id, $data): bool {
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $this->pdo->prepare("REPLACE INTO {$this->table} (id, data, expires) VALUES (:id, :data, :expires)");
        return $stmt->execute(['id' => $id, 'data' => $data, 'expires' => $expires]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires < NOW()");
        return $stmt->execute();
    }
}
?>
