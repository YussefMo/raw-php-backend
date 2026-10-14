<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Adds Cross-Origin Resource Sharing (CORS) headers so a browser on another
 * origin (e.g. a frontend dev server) may call this API.
 *
 * Registered globally, so it runs for every route.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedOrigins */
    public function __construct(
        private array $allowedOrigins = ['*'],
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->header('origin') ?? '*';
        $allowOrigin = in_array('*', $this->allowedOrigins, true) || in_array($origin, $this->allowedOrigins, true)
            ? $origin
            : $this->allowedOrigins[0];

        // A browser sends OPTIONS ("preflight") before a cross-origin request.
        // Answer it immediately; there is nothing for the app to do.
        if ($request->method() === 'OPTIONS') {
            return $this->withCors(Response::noContent(), $allowOrigin);
        }

        return $this->withCors($next($request), $allowOrigin);
    }

    private function withCors(Response $response, string $allowOrigin): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
