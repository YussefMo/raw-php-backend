<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;

/**
 * Represents one incoming HTTP request.
 *
 * This class is the only place that reads PHP superglobals ($_SERVER, $_GET,
 * $_POST, php://input). Controllers and middleware receive this object instead,
 * which keeps them decoupled from PHP's globals and easy to reason about.
 */
final class Request
{
    /** The authenticated user, set by AuthMiddleware once a token is verified. */
    private ?User $user = null;

    /** Route placeholders captured from the URL, e.g. ['id' => '5'] for /posts/5. */
    private array $params = [];

    /**
     * @param array<string, mixed>  $query   parsed query string ($_GET)
     * @param array<string, mixed>  $body    decoded request body
     * @param array<string, string> $headers lower-cased header names => values
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $headers,
    ) {
    }

    /**
     * Build a Request from the current PHP process. This is the "outermost"
     * step of the request lifecycle.
     */
    public static function capture(): self
    {
        $headers = self::captureHeaders();

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::normalizePath((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            $_GET,
            self::captureBody($headers),
            $headers
        );
    }

    /**
     * Build a Request from explicit values, without any superglobals.
     *
     * Used by the test suite, which calls the Application directly instead of
     * going through a real HTTP server.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers header name => value, any case
     * @param array<string, mixed>  $query
     */
    public static function create(
        string $method,
        string $path,
        array $body = [],
        array $headers = [],
        array $query = [],
    ): self {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        return new self(strtoupper($method), self::normalizePath($path), $query, $body, $normalized);
    }

    /** Strip the query string and trailing slash: "/posts/1/?x=2" -> "/posts/1". */
    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /** @return array<string, string> */
    private static function captureHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }

            return $headers;
        }

        // Fallback for environments without getallheaders(): PHP exposes request
        // headers as $_SERVER['HTTP_FOO'] -> "foo".
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private static function captureBody(array $headers): array
    {
        $contentType = $headers['content-type'] ?? '';

        // JSON bodies are NOT parsed into $_POST, so read the raw stream.
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        // Classic form submissions land in $_POST automatically.
        return $_POST;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Read a value from the query string. Example: $request->query('id'). */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** Read a value from the (already decoded) request body. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Was this key actually present in the body?
     *
     * Partial updates (PATCH) need this to tell "not sent" apart from
     * "sent as null/empty", so untouched fields are left alone.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body);
    }

    /** Read a request header, case-insensitively. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Extract the token from an "Authorization: Bearer <token>" header. */
    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /** Read a route placeholder captured by the router. */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** @param array<string, string> $params */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
