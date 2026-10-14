<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * The three roles in the system, as a PHP "backed enum".
 *
 * An enum is a type-safe list of allowed values. `Role::Admin->value` is the
 * string "admin" that we store in the database, so typos like "admn" become
 * impossible in code.
 */
enum Role: string
{
    case Admin = 'admin';
    case Author = 'author';
    case User = 'user';

    /** @return string[] every role value, e.g. for validation. */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Roles a visitor may pick when signing up.
     *
     * NOTE: "admin" is deliberately absent. A public sign-up must never be able
     * to grant itself elevated privileges. Admins are created by the migration
     * seed (or promoted by an existing admin).
     *
     * @return string[]
     */
    public static function selfAssignable(): array
    {
        return [self::User->value, self::Author->value];
    }
}
