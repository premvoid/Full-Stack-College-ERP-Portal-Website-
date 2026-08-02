<?php
// Database configuration (PDO)
// Credentials are read from environment variables with safe local defaults.
// For production, set DB_HOST, DB_NAME, DB_USER, DB_PASS (e.g. in your web server env).
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'aiml_academichub';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 30,
];

class SafePDO extends PDO
{
    private array $dsn;
    private string $user;
    private string $pass;
    private array $options;

    public function __construct(string $dsn, ?string $user, ?string $pass, ?array $options)
    {
        $this->dsn     = ['dsn' => $dsn, 'user' => $user, 'pass' => $pass, 'options' => $options];
        $this->user    = $user;
        $this->pass    = $pass;
        $this->options = $options;
        parent::__construct($dsn, $user, $pass, $options);
    }

    private function reconnect(): void
    {
        $p = $this->dsn;
        parent::__construct($p['dsn'], $p['user'], $p['pass'], $p['options']);
    }

    private function isGoneAway(PDOException $e): bool
    {
        return str_contains($e->getMessage(), '2006') || str_contains($e->getMessage(), 'has gone away');
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        try {
            return $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } catch (PDOException $e) {
            if (!$this->isGoneAway($e)) {
                throw $e;
            }
            $this->reconnect();
            return $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        try {
            return parent::prepare($query, $options);
        } catch (PDOException $e) {
            if (!$this->isGoneAway($e)) {
                throw $e;
            }
            $this->reconnect();
            return parent::prepare($query, $options);
        }
    }

    public function exec(string $statement): int|false
    {
        try {
            return parent::exec($statement);
        } catch (PDOException $e) {
            if (!$this->isGoneAway($e)) {
                throw $e;
            }
            $this->reconnect();
            return parent::exec($statement);
        }
    }
}

try {
    $pdo = new SafePDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please try again later.');
}

// Site base URL helper (deployment-aware)
function base_url() {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = preg_replace('#/modules$#', '', $script);
        return rtrim($base, '/');
    }
    return '/aiml_academichub';
}
