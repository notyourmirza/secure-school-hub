<?php
declare(strict_types=1);

/**
 * Migration runner.
 *
 *   php website/tools/migrate.php            apply pending migrations
 *   php website/tools/migrate.php --dry-run  list what would run
 *   php website/tools/migrate.php --only=007_security_hardening.sql
 *
 * Applied migrations are checksummed; editing an applied file aborts the run
 * instead of silently drifting from production.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

use SchoolSaaS\Database\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['dry-run', 'only:']);
$db = Database::connection();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    checksum CHAR(64) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
sort($files, SORT_STRING);

$appliedStmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration = :migration');
$recordStmt = $db->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)');
$exitCode = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (isset($options['only']) && $options['only'] !== $name) {
        continue;
    }

    $checksum = hash_file('sha256', $file);
    $appliedStmt->execute(['migration' => $name]);
    $previous = $appliedStmt->fetchColumn();

    if ($previous !== false) {
        if (!hash_equals((string)$previous, $checksum)) {
            fwrite(STDERR, "CHECKSUM MISMATCH {$name}: an applied migration was edited.\n");
            $exitCode = 1;
        } else {
            echo "SKIP  {$name}\n";
        }
        continue;
    }

    echo (isset($options['dry-run']) ? 'PLAN  ' : 'APPLY ') . $name . "\n";
    if (isset($options['dry-run'])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$name}");
    }

    try {
        $db->exec($sql);
        $recordStmt->execute(['migration' => $name, 'checksum' => $checksum]);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED {$name}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

exit($exitCode);
