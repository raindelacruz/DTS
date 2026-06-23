<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/app/config/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$migrationFiles = glob(__DIR__ . '/migrations/*.php') ?: [];
sort($migrationFiles, SORT_STRING);

foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile, '.php');
    if (in_array($migrationName, $applied, true)) {
        echo "Already applied: {$migrationName}" . PHP_EOL;
        continue;
    }

    $migration = require $migrationFile;
    if (!is_callable($migration)) {
        throw new RuntimeException("Migration {$migrationName} must return a callable.");
    }

    $migration($pdo);
    $statement = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
    $statement->execute(['migration' => $migrationName]);
    echo "Applied: {$migrationName}" . PHP_EOL;
}

echo 'Migrations complete.' . PHP_EOL;
