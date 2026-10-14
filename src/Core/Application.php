<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\JwtService;
use App\Auth\Role;
use App\Config\Config;
use App\Controllers\AuthController;
use App\Controllers\PostController;
use App\Controllers\UserController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Models\Post;
use App\Models\User;
use PDO;
use Throwable;

/**
 * Composition root + route table.
 *
 * This is where every object is built and wired together by hand (no DI
 * container), and where the URL -> controller + middleware map lives. Read this
 * file to see, in one place, the whole surface of the API.
 */
final class Application
{
    private Router $router;

    public function __construct(
        Config $config,
        PDO $db,
    ) {
        $jwt = new JwtService($config);

        // Models (data layer)
        $users = new User($db);
        $posts = new Post($db);

        // Controllers (HTTP layer), each given the models it needs
        $authController = new AuthController($users, $jwt);
        $postController = new PostController($posts);
        $userController = new UserController($users);

        // Shared middleware instances
        $auth = new AuthMiddleware($jwt, $users);

        // Only these roles may publish posts.
        $publisherRoles = [Role::Admin->value, Role::Author->value];

        // Global middleware runs on every route.
        $this->router = new Router([new CorsMiddleware()]);

        // --- Public routes -------------------------------------------------
        $this->router->get('/', [$postController, 'index']);
        $this->router->get('/posts', [$postController, 'index']);
        $this->router->get('/posts/{id}', [$postController, 'show']);
        $this->router->get('/id', [$postController, 'show']);
        $this->router->post('/auth/register', [$authController, 'register']);
        $this->router->post('/auth/login', [$authController, 'login']);

        // --- Authenticated routes (any role) -------------------------------
        $this->router->get('/auth/me', [$authController, 'me'], [$auth]);

        // --- Role-gated routes ---------------------------------------------
        // Publishing requires admin or author.
        $this->router->post('/posts', [$postController, 'store'], [
            $auth,
            new RoleMiddleware($publisherRoles),
        ]);

        // User administration requires admin.
        $this->router->get('/admin/users', [$userController, 'index'], [
            $auth,
            new RoleMiddleware([Role::Admin->value]),
        ]);
    }

    /** Dispatch the request and send the response. */
    public function run(Request $request): void
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            // Expected, handled failures thrown by controllers.
            $response = Response::json(['error' => $e->getMessage()], $e->status());
        } catch (Throwable $e) {
            // Unexpected failures. In production you would log $e and hide the
            // message; here we surface it so it is easy to debug and learn.
            $response = Response::json([
                'error' => 'Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }

        $response->send();
    }
}
