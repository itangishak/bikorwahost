<?php
class UserSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table;

    public function __construct(PDO $pdo, string $table = 'user_sessions') {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function open($savePath, $sessionName): bool {
        // Session opening logic (if any)
        return true;
    }

    public function close(): bool {
        // Session closing logic (if any)
        return true;
    }

    public function read($id): string|false {
        // Get user_id directly
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE user_id = :user_id AND expires > NOW() LIMIT 1");
        $stmt->execute(['user_id' => $id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['data'];
        }
        return '';
    }

    public function write($id, $data): bool {
        // Set a 1-hour expiry on session
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = $this->pdo->prepare("REPLACE INTO {$this->table} (user_id, data, expires) VALUES (:user_id, :data, :expires)");
        return $stmt->execute(['user_id' => $id, 'data' => $data, 'expires' => $expires]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $id]);
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires < NOW()");
        return $stmt->execute();
    }
}

// Initialize session handling
function initializeUserSession(PDO $pdo) {
    $handler = new UserSessionHandler($pdo);
    session_set_save_handler($handler, true);
    session_start();
}
