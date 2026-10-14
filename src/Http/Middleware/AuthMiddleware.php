<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\JwtService;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use Throwable;

/**
 * Authentication: "who are you?".
 *
 * Reads the bearer token, verifies it, loads the matching user and attaches it
 * to the request. If anything is wrong, it short-circuits with a 401 and the
 * controller is never reached.
 *
 * This middleware only proves identity. Deciding what a user MAY do is the job
 * of RoleMiddleware.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JwtService $jwt,
        private User $users,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Missing bearer token.',
            ], 401);
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (Throwable) {
            // Expired, tampered, or malformed token: all mean "not authenticated".
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $userId = (int) ($claims['sub'] ?? 0);
        $user = $userId > 0 ? $this->users->findById($userId) : null;

        // The token may be valid but the account may have been deleted.
        // Loading the user fresh also means role changes take effect immediately.
        if ($user === null) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'User no longer exists.',
            ], 401);
        }

        $request->setUser($user);

        return $next($request);
    }
}
