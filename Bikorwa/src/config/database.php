<?php
/**
 * Configuration de la base de données
 * BIKORWA SHOP
 */

class Database {
    /** @var array<string, mixed> */
    private $config = [];

    /** @var PDO|null */
    private $conn = null;

    /** @var string|null */
    private $lastError = null;

    public function __construct(array $overrideConfig = [])
    {
        $defaults = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'bumadste_bikorwa_shop',
            'username' => 'bumadste_bikorwa',
            'password' => 'Bikorwa2024!',
            'charset' => 'utf8mb4',
        ];

        $this->config = array_merge(
            $defaults,
            $this->loadConfigOverrides(),
            array_filter($overrideConfig, static function ($value) {
                return $value !== null && $value !== '';
            })
        );
    }

    /**
     * Get a PDO connection instance.
     */
    public function getConnection(): ?PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $hostsToTry = $this->buildHostFallbacks($this->config['host']);

        foreach ($hostsToTry as $host) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                (int) $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
                }

                $this->conn = new PDO(
                    $dsn,
                    $this->config['username'],
                    $this->config['password'],
                    $options
                );

                $this->conn->query('SELECT 1');
                $this->lastError = null;

                return $this->conn;
            } catch (PDOException $exception) {
                $this->lastError = $exception->getMessage();
                $this->conn = null;

                error_log('Database connection error: ' . $exception->getMessage());
                error_log('DSN: ' . $dsn);
                error_log('Username: ' . $this->config['username']);
            }
        }

        return null;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Build a list of hostnames to try when establishing a connection.
     *
     * @param string $primaryHost
     * @return array<int, string>
     */
    private function buildHostFallbacks(string $primaryHost): array
    {
        $hosts = [$primaryHost];

        if ($primaryHost === 'localhost') {
            $hosts[] = '127.0.0.1';
        }

        if (!empty($this->config['fallback_host']) && !in_array($this->config['fallback_host'], $hosts, true)) {
            $hosts[] = $this->config['fallback_host'];
        }

        return array_unique($hosts);
    }

    /**
     * Load configuration overrides from environment variables or local config file.
     *
     * @return array<string, mixed>
     */
    private function loadConfigOverrides(): array
    {
        $overrides = [];

        $localConfigPath = __DIR__ . '/database.local.php';
        if (file_exists($localConfigPath)) {
            $localConfig = include $localConfigPath;
            if (is_array($localConfig)) {
                $overrides = array_merge($overrides, $localConfig);
            }
        }

        $envMap = [
            'host' => ['BIKORWA_DB_HOST', 'DB_HOST'],
            'port' => ['BIKORWA_DB_PORT', 'DB_PORT'],
            'database' => ['BIKORWA_DB_NAME', 'DB_NAME'],
            'username' => ['BIKORWA_DB_USER', 'DB_USER', 'DB_USERNAME'],
            'password' => ['BIKORWA_DB_PASSWORD', 'DB_PASSWORD', 'DB_PASS'],
            'charset' => ['BIKORWA_DB_CHARSET', 'DB_CHARSET'],
            'fallback_host' => ['BIKORWA_DB_FALLBACK_HOST'],
        ];

        foreach ($envMap as $key => $envNames) {
            foreach ($envNames as $env) {
                $value = getenv($env);
                if ($value !== false && $value !== '') {
                    $overrides[$key] = $key === 'port' ? (int) $value : $value;
                    break;
                }
            }
        }

        return $overrides;
    }
}
