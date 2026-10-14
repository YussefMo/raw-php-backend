<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;
use RuntimeException;
use Throwable;

/**
 * A tiny, hand-written migration runner.
 *
 * How it decides what to do, in order:
 *   1. Make sure the `migrations` ledger table exists.
 *   2. Load the list of migrations already applied from that table.
 *   3. For every file in database/migrations (sorted by filename):
 *        - already in the ledger?          -> skip
 *        - its table already exists in DB? -> record as applied, skip
 *        - otherwise                       -> run up() and record it
 *
 * Step 3's "table already exists" check is what lets you run the migrator on a
 * database that already has tables (posts, categories) without touching them.
 */
final class Migrator
{
    /**
     * @param callable(string): void|null $logger
     */
    public function __construct(
        private PDO $db,
        private Config $config,
        private string $migrationsPath,
        private $logger = null,
    ) {
    }

    /** Run every pending migration. */
    public function run(): void
    {
        $this->ensureLedgerTable();

        $applied = $this->appliedMigrations();
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);

        $ran = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $applied, true)) {
                $this->log("  skip      {$name} (already recorded)");
                $skipped++;

                continue;
            }

            $migration = require $file;

            if (!$migration instanceof MigrationInterface) {
                throw new RuntimeException(
                    "Migration {$name} must return an object implementing " . MigrationInterface::class . '.'
                );
            }

            // The requested behaviour: look at the database first. If the table
            // this migration would create is already present, just record it.
            $table = $migration->table();
            if ($table !== null && $this->tableExists($table)) {
                $this->record($name);
                $this->log("  skip      {$name} (table \"{$table}\" already exists)");
                $skipped++;

                continue;
            }

            try {
                $migration->up($this->db, $this->config);
                $this->record($name);
                $this->log("  migrated  {$name}");
                $ran++;
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Migration {$name} failed: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        $this->log("Done. {$ran} migrated, {$skipped} skipped.");
    }

    /**
     * Drop every table in the current database, then migrate from zero.
     *
     * Destructive: use it to reset a development database (the `migrate:fresh`
     * composer script). It only ever touches the database in .env.
     */
    public function fresh(): void
    {
        $this->dropAllTables();
        $this->run();
    }

    /** Drop all tables, ignoring foreign-key order. */
    private function dropAllTables(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

        $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Create the ledger table if it is not there yet. */
    private function ensureLedgerTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(255) NOT NULL,
                migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY migrations_name_unique (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return string[] names of migrations already applied */
    private function appliedMigrations(): array
    {
        return $this->db
            ->query('SELECT migration FROM migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Does a table with this name exist in the CURRENT database? */
    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function record(string $name): void
    {
        $stmt = $this->db->prepare('INSERT INTO migrations (migration) VALUES (?)');
        $stmt->execute([$name]);
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);

            return;
        }

        echo $message . PHP_EOL;
    }
}
