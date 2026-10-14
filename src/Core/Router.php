<?php

declare(strict_types=1);

namespace App\Core;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

/**
 * Maps an HTTP method + path to a handler, and runs the middleware pipeline.
 *
 * Routes are registered in Application (the composition root). A path may
 * contain placeholders written as {name}, e.g. "/posts/{id}"; captured values
 * are exposed through $request->param('id').
 */
final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     regex: string,
     *     params: string[],
     *     handler: callable,
     *     middleware: MiddlewareInterface[]
     * }>
     */
    private array $routes = [];

    /** @param MiddlewareInterface[] $globalMiddleware middleware that runs on every route */
    public function __construct(
        private array $globalMiddleware = [],
    ) {
    }

    /** @param MiddlewareInterface[] $middleware */
    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param MiddlewareInterface[] $middleware */
    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param MiddlewareInterface[] $middleware */
    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param MiddlewareInterface[] $middleware
     */
    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        // Turn "/posts/{id}" into the regex "#^/posts/([^/]+)$#" and remember
        // the placeholder names so we can map matches back to them.
        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        // Answer CORS preflight requests before route matching.
        if ($request->method() === 'OPTIONS') {
            return $this->runPipeline(
                $this->globalMiddleware,
                $request,
                static fn (Request $r): Response => Response::noContent()
            );
        }

        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            // The path exists but not for this method (e.g. GET on a POST route).
            if ($route['method'] !== $request->method()) {
                continue;
            }

            array_shift($matches); // drop the full-match element
            $request->setParams(array_combine($route['params'], $matches) ?: []);

            $middleware = array_merge($this->globalMiddleware, $route['middleware']);

            return $this->runPipeline($middleware, $request, $route['handler']);
        }

        if ($pathMatched) {
            return Response::json(['error' => 'Method Not Allowed', 'path' => $request->path()], 405);
        }

        return Response::json(['error' => 'Not Found', 'path' => $request->path()], 404);
    }

    /**
     * Wrap the handler in the middleware chain, onion-style:
     * the first middleware runs first and its response unwinds last.
     *
     * @param MiddlewareInterface[]                     $middleware
     * @param callable(Request): Response                $handler
     */
    private function runPipeline(array $middleware, Request $request, callable $handler): Response
    {
        // Start with the innermost core (the controller call)...
        $next = $handler;

        // ...then wrap each middleware around it, in reverse order.
        foreach (array_reverse($middleware) as $layer) {
            $previous = $next;
            $next = static fn (Request $r): Response => $layer->handle($r, $previous);
        }

        return $next($request);
    }
}
