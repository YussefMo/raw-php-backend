<?php

declare(strict_types=1);

use App\Config\Config;
use App\Database\MigrationInterface;

/**
 * Creates the `posts` table.
 *
 * The schema is intentionally strict:
 *   - user_id     NOT NULL, FK -> users(id)      ON DELETE CASCADE
 *   - category_id NOT NULL, FK -> categories(id) ON DELETE RESTRICT
 *
 * So a post can never be orphaned: it always has an owner and a category.
 * There is deliberately NO `author` text column — the author's name is joined
 * from `users`, which keeps one source of truth for names.
 */
return new class () implements MigrationInterface {
    public function table(): ?string
    {
        return 'posts';
    }

    public function up(PDO $db, Config $config): void
    {
        $db->exec(
            'CREATE TABLE posts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                category_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY posts_user_id_index (user_id),
                KEY posts_category_id_index (category_id),
                CONSTRAINT posts_user_id_foreign
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT posts_category_id_foreign
                    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS posts');
    }
};
