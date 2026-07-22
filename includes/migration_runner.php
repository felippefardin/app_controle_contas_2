<?php

declare(strict_types=1);

final class MigrationContext
{
    public function __construct(public readonly mysqli $db, public readonly string $database) {}

    public function tableExists(string $table): bool
    {
        $database = $this->database;
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1');
        $stmt->bind_param('ss', $database, $table);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    public function columnExists(string $table, string $column): bool
    {
        $database = $this->database;
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $stmt->bind_param('sss', $database, $table, $column);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    public function indexExists(string $table, string $index): bool
    {
        $database = $this->database;
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1');
        $stmt->bind_param('sss', $database, $table, $index);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }

    public function addColumn(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            $this->db->query('ALTER TABLE `' . self::identifier($table) . '` ADD COLUMN `' . self::identifier($column) . '` ' . $definition);
        }
    }

    public function addIndex(string $table, string $index, string $definition): void
    {
        if (!$this->indexExists($table, $index)) {
            $this->db->query('ALTER TABLE `' . self::identifier($table) . '` ADD ' . $definition);
        }
    }

    public static function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) throw new InvalidArgumentException('Identificador SQL inválido.');
        return $value;
    }
}

function migration_files(string $scope): array
{
    if (!in_array($scope, ['master', 'tenant'], true)) throw new InvalidArgumentException('Escopo de migração inválido.');
    $files = glob(__DIR__ . '/../migrations/' . $scope . '/*.php') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function load_migration(string $file): array
{
    $migration = require $file;
    if (!is_array($migration) || empty($migration['id']) || empty($migration['description']) || !isset($migration['up']) || !is_callable($migration['up'])) {
        throw new RuntimeException('Migração inválida: ' . basename($file));
    }
    if (!preg_match('/^[0-9]{12,20}_[a-z0-9_]+$/', (string) $migration['id'])) {
        throw new RuntimeException('ID de migração inválido: ' . (string) $migration['id']);
    }
    $migration['file'] = $file;
    $migration['checksum'] = hash_file('sha256', $file);
    return $migration;
}

function ensure_migration_table(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS schema_migrations (
        id VARCHAR(191) NOT NULL,
        checksum CHAR(64) NOT NULL,
        description VARCHAR(255) NOT NULL,
        batch INT NOT NULL,
        execution_ms INT NOT NULL DEFAULT 0,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function run_migrations_for_connection(mysqli $db, string $scope, bool $dryRun = false, bool $statusOnly = false): array
{
    $database = (string) ($db->query('SELECT DATABASE()')->fetch_row()[0] ?? '');
    if ($database === '') throw new RuntimeException('Conexão sem banco selecionado.');
    ensure_migration_table($db);
    $lockName = 'app_controle_migrations_' . hash('sha256', $database);
    $lockStmt = $db->prepare('SELECT GET_LOCK(?, 10)');
    $lockStmt->bind_param('s', $lockName);
    $lockStmt->execute();
    $locked = (int) ($lockStmt->get_result()->fetch_row()[0] ?? 0) === 1;
    $lockStmt->close();
    if (!$locked) throw new RuntimeException('Outra migração já está em execução para ' . $database . '.');

    $result = ['database' => $database, 'scope' => $scope, 'applied' => [], 'pending' => [], 'current' => []];
    try {
        $appliedRows = $db->query('SELECT id, checksum FROM schema_migrations')->fetch_all(MYSQLI_ASSOC);
        $applied = [];
        foreach ($appliedRows as $row) $applied[$row['id']] = $row['checksum'];
        $batch = (int) ($db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetch_row()[0] ?? 1);

        foreach (migration_files($scope) as $file) {
            $migration = load_migration($file);
            $id = (string) $migration['id'];
            if (isset($applied[$id])) {
                if (!hash_equals($applied[$id], $migration['checksum'])) {
                    throw new RuntimeException('Migração já aplicada foi alterada: ' . $id);
                }
                $result['current'][] = $id;
                continue;
            }
            $result['pending'][] = $id;
            if ($dryRun || $statusOnly) continue;

            $started = microtime(true);
            ($migration['up'])(new MigrationContext($db, $database));
            $elapsed = (int) round((microtime(true) - $started) * 1000);
            $stmt = $db->prepare('INSERT INTO schema_migrations (id, checksum, description, batch, execution_ms) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssii', $id, $migration['checksum'], $migration['description'], $batch, $elapsed);
            $stmt->execute();
            $stmt->close();
            $result['applied'][] = $id;
        }
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $lockName);
        $release->execute();
        $release->close();
    }
    return $result;
}
