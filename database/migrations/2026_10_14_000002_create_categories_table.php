<?php

declare(strict_types=1);

use App\Config\Config;
use App\Database\MigrationInterface;

/**
 * Creates the `categories` table and seeds a few starter categories so the
 * "new post" dropdown is not empty right after migrating.
 *
 * `name` is UNIQUE, so duplicate categories are impossible at the database
 * level (the API also returns 409 for a duplicate, as a friendly error).
 */
return new class () implements MigrationInterface {
    /** @var string[] */
    private const STARTER_CATEGORIES = ['Technology', 'Gaming', 'Auto', 'Entertainment', 'Books'];

    public function table(): ?string
    {
        return 'categories';
    }

    public function up(PDO $db, Config $config): void
    {
        $db->exec(
            'CREATE TABLE categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY categories_name_unique (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $insert = $db->prepare('INSERT INTO categories (name) VALUES (?)');
        foreach (self::STARTER_CATEGORIES as $name) {
            $insert->execute([$name]);
        }
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS categories');
    }
};
