<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Thin wrapper around the firebase/php-jwt library.
 *
 * The rest of the application never builds or parses a token by hand: it asks
 * this service to `issue()` one at login and `decode()` one on each protected
 * request. Swapping the JWT library later only means changing this file.
 */
final class JwtService
{
    /** HS256 = HMAC-SHA256, a symmetric algorithm using one shared secret. */
    private const ALGORITHM = 'HS256';

    private string $secret;
    private int $ttl;
    private string $issuer;

    public function __construct(Config $config)
    {
        $this->secret = $config->get('JWT_SECRET');
        $this->ttl = (int) $config->get('JWT_TTL');
        $this->issuer = $config->get('JWT_ISSUER');
    }

    /**
     * Create a signed access token for a user.
     *
     * "sub" (subject) is the user id, which is the claim AuthMiddleware reads
     * to know who is calling.
     */
    public function issue(int $userId, string $role, string $email): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,      // who issued the token
            'iat' => $now,               // issued at
            'exp' => $now + $this->ttl,  // expiry; the library rejects it after this
            'sub' => $userId,            // the user id
            'role' => $role,             // handy for the client, not for authz decisions
            'email' => $email,
        ];

        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    /**
     * Verify the signature and expiry, then return the claims as an array.
     *
     * Throws whenever the token is invalid or expired; callers must catch it.
     *
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        // JWT::decode returns a stdClass, so we cast it to an array.
        return (array) JWT::decode($token, new Key($this->secret, self::ALGORITHM));
    }

    /** Token lifetime in seconds, reported to clients at login. */
    public function ttl(): int
    {
        return $this->ttl;
    }
}
