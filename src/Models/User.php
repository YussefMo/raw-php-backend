<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth\Role;
use PDO;

/**
 * Data access for the `users` table.
 *
 * Reminder: models only talk to the database. They never read $_POST, never
 * set headers and never echo JSON — controllers do that.
 */
final class User
{
    /** Public shape of a user. NOTE: password_hash is intentionally missing. */
    public ?int $id = null;
    public string $name = '';
    public string $email = '';
    public string $role = Role::User->value;
    public string $created_at = '';

    /**
     * The password hash is kept on the object because login needs it for
     * password_verify(), but toArray() below never exposes it to the client.
     */
    public string $password_hash = '';

    public function __construct(
        private PDO $db,
    ) {
    }

    /** Insert a new user and return its id. Expects an ALREADY hashed password. */
    public function create(string $name, string $email, string $passwordHash, string $role): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $passwordHash, $role]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?self
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, role, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function findByEmail(string $email): ?self
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, role, created_at FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return self[] */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, email, password_hash, role, created_at FROM users ORDER BY id ASC'
        );

        $users = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $user = $this->hydrate($row);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * The safe, public representation of a user. This is THE single place that
     * decides what may leave the API — so password_hash can never leak.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at,
        ];
    }

    /** Turn a database row into a User object (or null when there is no row). */
    private function hydrate(array|false $row): ?self
    {
        if ($row === false) {
            return null;
        }

        $user = new self($this->db);
        $user->id = (int) $row['id'];
        $user->name = (string) $row['name'];
        $user->email = (string) $row['email'];
        $user->password_hash = (string) $row['password_hash'];
        $user->role = (string) $row['role'];
        $user->created_at = (string) $row['created_at'];

        return $user;
    }
}
