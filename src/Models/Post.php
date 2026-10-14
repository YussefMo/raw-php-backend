<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Data access for the `posts` table.
 *
 * Models return plain arrays here (not PDOStatement objects). That keeps the
 * "how to fetch" details inside the model and gives controllers simple data.
 */
final class Post
{
    /** Columns every post query returns, including the joined category name. */
    private const SELECT = 'SELECT
            c.name AS category_name,
            p.id,
            p.category_id,
            p.title,
            p.body,
            p.author,
            p.created_at
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id';

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

    /** Create a post and return the new id. category_id is required (NOT NULL). */
    public function create(string $title, string $body, string $author, int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO posts (title, body, author, category_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$title, $body, $author, $categoryId]);

        return (int) $this->db->lastInsertId();
    }
}
