<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Data access for the `posts` table.
 *
 * A post ALWAYS has an owner (user_id NOT NULL, FK -> users) and a category
 * (category_id NOT NULL, FK -> categories), so a post can never be orphaned.
 * There is no `author` text column: the author name is joined from `users`,
 * which keeps a single source of truth for names.
 */
final class Post
{
    /**
     * Columns every post query returns.
     *
     * `u.name AS author` keeps the API response shape the frontend already
     * expects, while the value now comes from the owning user.
     */
    private const SELECT = 'SELECT
            c.name AS category_name,
            u.name AS author,
            p.id,
            p.category_id,
            p.title,
            p.body,
            p.user_id,
            p.created_at
        FROM posts p
        JOIN users u ON p.user_id = u.id
        JOIN categories c ON p.category_id = c.id';

    /**
     * Columns a client may change through PATCH.
     * `user_id` is deliberately absent: a post's owner can never be reassigned.
     */
    private const UPDATABLE = ['title', 'body', 'category_id'];

    public function __construct(
        private PDO $db,
    ) {
    }

    /**
     * List every post, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query(self::SELECT . ' ORDER BY p.created_at DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Posts owned by one user (the "My Posts" view).
     *
     * @return array<int, array<string, mixed>>
     */
    public function byUser(int $userId): array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE p.user_id = ? ORDER BY p.created_at DESC');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a single post by id, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE p.id = ? LIMIT 1');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Create a post and return the new id. Owner and category are required. */
    public function create(string $title, string $body, int $categoryId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, body, category_id, user_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$title, $body, $categoryId, $userId]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update the given columns of a post.
     *
     * The column names are taken from the UPDATABLE whitelist (never from the
     * request), and the values are bound, so this cannot be used for injection.
     *
     * @param array<string, mixed> $fields column => value
     */
    public function update(int $id, array $fields): void
    {
        $sets = [];
        $params = [];

        foreach ($fields as $column => $value) {
            if (!in_array($column, self::UPDATABLE, true)) {
                continue;
            }
            $sets[] = "`{$column}` = ?";
            $params[] = $value;
        }

        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE posts SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    /** Delete a post. Returns false when the id did not exist. */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
