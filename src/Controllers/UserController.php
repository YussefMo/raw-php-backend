<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\User;

/**
 * HTTP layer for user administration.
 *
 * Every route here is protected by AuthMiddleware + RoleMiddleware(admin) in
 * Application, so this controller can assume the caller is an admin.
 */
final class UserController
{
    public function __construct(
        private User $users,
    ) {
    }

    /**
     * GET /admin/users — list all users. Admin only.
     *
     * toArray() strips the password hash, so it can never be returned.
     */
    public function index(Request $request): Response
    {
        $users = array_map(
            static fn (User $user): array => $user->toArray(),
            $this->users->all()
        );

        return Response::json(['data' => $users]);
    }
}
