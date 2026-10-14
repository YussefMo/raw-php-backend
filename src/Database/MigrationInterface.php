<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

/**
 * A single database migration.
 *
 * Every file in database/migrations/ returns an anonymous class that implements
 * this interface. The Migrator runs `up()` for migrations that have not been
 * applied yet.
 */
interface MigrationInterface
{
    /**
     * Apply the migration.
     *
     * $config is passed in so a migration can read .env values (for example the
     * seed admin's email/password).
     */
    public function up(PDO $db, Config $config): void;

    /** Reverse the migration. Used when rolling back. */
    public function down(PDO $db): void;

    /**
     * The main table this migration creates, or null.
     *
     * The Migrator checks whether the table already exists and, if it does,
     * records the migration as done instead of running it. That is what makes
     * `composer run migrate` safe to run against a database that already has
     * some tables.
     */
    public function table(): ?string;
}
