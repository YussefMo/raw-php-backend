<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Represents the HTTP response we send back.
 *
 * Controllers return a Response instead of calling `echo`/`header()` directly.
 * That makes them testable and lets middleware modify the response on the way
 * out (see CorsMiddleware).
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private int $status,
        private array $headers,
        private string $body,
    ) {
    }

    /** Build a JSON response. This is what most API endpoints return. */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self(
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers),
            $body === false ? '{}' : $body,
        );
    }

    /** An empty response, used for 204/OPTIONS preflight. */
    public static function noContent(): self
    {
        return new self(204, [], '');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Return a COPY of this response with an extra header.
     * Middleware uses this to add headers (e.g. CORS) without mutating the
     * original response object.
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** Flush the response to the client. The last step of the lifecycle. */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}
