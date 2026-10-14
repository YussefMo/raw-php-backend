<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Authorization: "are you allowed to do this?".
 *
 * Must run AFTER AuthMiddleware, because it relies on $request->user().
 * Each route declares which roles may access it, e.g.:
 *
 *     new RoleMiddleware([Role::Admin->value])
 *
 * A failed check returns 403 Forbidden (we know who you are, but you may not).
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedRoles role values that may access the route */
    public function __construct(
        private array $allowedRoles,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required.',
            ], 401);
        }

        if (!in_array($user->role, $this->allowedRoles, true)) {
            return Response::json([
                'error' => 'Forbidden',
                'message' => 'Your role does not allow this action.',
                'required_roles' => $this->allowedRoles,
            ], 403);
        }

        return $next($request);
    }
}
