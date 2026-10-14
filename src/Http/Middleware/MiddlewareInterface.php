<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Contract every middleware must follow.
 *
 * Middleware wraps the request handler: it can inspect (or reject) the request
 * BEFORE calling $next, and inspect (or modify) the response AFTER. Calling
 * $next($request) passes control along the pipeline.
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
