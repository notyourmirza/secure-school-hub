<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Config/config.php';
require_once __DIR__ . '/src/Database/Database.php';

use SchoolSaaS\Database\Database;

$options = getopt('', ['dry-run', 'only:']);
$db = Database::connection();
$db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(190) NOT NULL UNIQUE, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$appliedStmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE migration=:migration');
$recordStmt = $db->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (:migration,:checksum)');
foreach ($files as $file) {
    $name = basename($file); if (isset($options['only']) && $options['only'] !== $name) continue;
    $checksum = hash_file('sha256', $file); $appliedStmt->execute(['migration'=>$name]); $previous=$appliedStmt->fetchColumn();
    if ($previous !== false) { if (!hash_equals((string)$previous,$checksum)) fwrite(STDERR,"Checksum mismatch for already-applied {$name}; aborting.\n"); else echo "SKIP {$name}\n"; continue; }
    echo (isset($options['dry-run'])?'WOULD APPLY ':'APPLY ').$name."\n";
    if (isset($options['dry-run'])) continue;
    $sql=file_get_contents($file); if ($sql===false) throw new RuntimeException("Cannot read {$name}");
    try { $db->exec($sql); $recordStmt->execute(['migration'=>$name,'checksum'=>$checksum]); } catch (Throwable $e) { throw new RuntimeException("Migration {$name} failed: ".$e->getMessage(),0,$e); }
}
