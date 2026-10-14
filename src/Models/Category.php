<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Data access for the `categories` table.
 *
 * Categories are the "folders" posts belong to. Posts store a category_id, so
 * anything that creates a post must check the id really exists first
 * (see PostController) — otherwise you get orphaned posts pointing at nothing.
 */
final class Category
{
    public function __construct(
        private PDO $db,
    ) {
    }

    /**
     * List every category, alphabetically.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db
            ->query('SELECT id, name, created_at FROM categories ORDER BY name ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, created_at FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Does this category id exist? Used to validate a post's category_id. */
    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Case-insensitive duplicate check, so "Books" and "books" collide. */
    public function nameExists(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)');
        $stmt->execute([$name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Is any post still assigned to this category?
     *
     * posts.category_id is NOT NULL and has no DB foreign key, so deleting a
     * category in use would silently orphan its posts. We block it instead.
     */
    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM posts WHERE category_id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
