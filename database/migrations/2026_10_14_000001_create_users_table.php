<?php

declare(strict_types=1);

use App\Auth\Role;
use App\Config\Config;
use App\Database\MigrationInterface;

/**
 * Creates the `users` table and seeds one admin account from .env.
 *
 * All tables in this project use utf8mb4 / utf8mb4_unicode_ci so that joining
 * text columns across tables never hits a collation mismatch.
 */
return new class () implements MigrationInterface {
    public function table(): ?string
    {
        return 'users';
    }

    public function up(PDO $db, Config $config): void
    {
        $db->exec(
            "CREATE TABLE users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'author', 'user') NOT NULL DEFAULT 'user',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->seedAdmin($db, $config);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS users');
    }

    /**
     * Create the first admin from .env so authorization can be tested right
     * after migrating. Uses PHP's own password_hash(), like the rest of the app.
     */
    private function seedAdmin(PDO $db, Config $config): void
    {
        $email = strtolower(trim($config->get('ADMIN_EMAIL')));
        $password = $config->get('ADMIN_PASSWORD');
        $name = $config->get('ADMIN_NAME');

        // Idempotent: do nothing if that email is already in the table.
        $exists = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $insert = $db->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            Role::Admin->value,
        ]);
    }
};
