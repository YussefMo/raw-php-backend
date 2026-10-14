<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\JwtService;
use App\Auth\Role;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;

/**
 * HTTP layer for authentication: register, login, and "who am I".
 *
 * Controllers do three things only: read input, call a model, build a Response.
 * They never write SQL and never echo.
 */
final class AuthController
{
    /** Minimum password length. Enforced here, before hashing. */
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private User $users,
        private JwtService $jwt,
    ) {
    }

    /**
     * POST /auth/register
     *
     * Body: { "name": "...", "email": "...", "password": "...", "role": "user|author" }
     */
    public function register(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', Role::User->value);

        // Collect all validation problems so the client can show them at once.
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }
        if (!in_array($role, Role::selfAssignable(), true)) {
            $errors['role'] = 'Role must be one of: ' . implode(', ', Role::selfAssignable()) . '.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        if ($this->users->emailExists($email)) {
            return Response::json(['error' => 'That email is already registered.'], 409);
        }

        // password_hash() is PHP's built-in, slow-by-design hashing function.
        // PASSWORD_DEFAULT currently means bcrypt and is designed to be upgraded
        // over time without breaking existing hashes.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $id = $this->users->create($name, $email, $passwordHash, $role);
        $user = $this->users->findById($id);

        return Response::json(['user' => $user?->toArray()], 201);
    }

    /**
     * POST /auth/login
     *
     * Body: { "email": "...", "password": "..." }
     * Returns a JWT access token on success.
     */
    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return Response::json([
                'error' => 'Validation failed',
                'errors' => ['email' => 'Email and password are required.'],
            ], 422);
        }

        $user = $this->users->findByEmail($email);

        // Use ONE generic message whether the email is unknown or the password
        // is wrong. Telling attackers which part failed helps them.
        if ($user === null || !password_verify($password, $user->password_hash)) {
            return Response::json(['error' => 'Invalid email or password.'], 401);
        }

        $token = $this->jwt->issue($user->id ?? 0, $user->role, $user->email);

        return Response::json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->ttl(),
            'user' => $user->toArray(),
        ]);
    }

    /**
     * GET /auth/me  (requires a valid token)
     *
     * Returns the user attached by AuthMiddleware.
     */
    public function me(Request $request): Response
    {
        $user = $request->user();

        // AuthMiddleware guarantees this, but we keep the guard for safety.
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return Response::json(['user' => $user->toArray()]);
    }
}
