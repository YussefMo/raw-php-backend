<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\JwtService;
use App\Auth\Role;
use App\Config\Config;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\PostController;
use App\Controllers\UserController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Models\Category;
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
        $categories = new Category($db);

        // Controllers (HTTP layer), each given the models it needs
        $authController = new AuthController($users, $jwt);
        $postController = new PostController($posts, $categories);
        $categoryController = new CategoryController($categories);
        $userController = new UserController($users);

        // Shared middleware instances
        $auth = new AuthMiddleware($jwt, $users);

        // Role groups used by the routes below.
        $authorAndAdmin = [Role::Admin->value, Role::Author->value];
        $adminOnly = [Role::Admin->value];

        // Global middleware runs on every route.
        $this->router = new Router([new CorsMiddleware()]);

        // --- Public routes -------------------------------------------------
        $this->router->get('/', [$postController, 'index']);
        $this->router->get('/posts', [$postController, 'index']);
        $this->router->get('/posts/{id}', [$postController, 'show']);
        $this->router->get('/id', [$postController, 'show']); // legacy ?id= form
        $this->router->post('/auth/register', [$authController, 'register']);
        $this->router->post('/auth/login', [$authController, 'login']);

        // --- Authenticated routes (any role) -------------------------------
        $this->router->get('/auth/me', [$authController, 'me'], [$auth]);
        // "My Posts" and deletion: any authenticated user. Whether a caller may
        // actually delete a given post (admin vs owner) is checked in the controller.
        $this->router->get('/me/posts', [$postController, 'mine'], [$auth]);
        $this->router->delete('/posts/{id}', [$postController, 'destroy'], [$auth]);

        // --- Author or admin ---------------------------------------------
        // Authors need the category list to fill the "new post" dropdown.
        $this->router->get('/categories', [$categoryController, 'index'], [
            $auth,
            new RoleMiddleware($authorAndAdmin),
        ]);
        $this->router->post('/posts', [$postController, 'store'], [
            $auth,
            new RoleMiddleware($authorAndAdmin),
        ]);

        // --- Admin only ----------------------------------------------------
        $this->router->post('/categories', [$categoryController, 'store'], [
            $auth,
            new RoleMiddleware($adminOnly),
        ]);
        $this->router->delete('/categories/{id}', [$categoryController, 'destroy'], [
            $auth,
            new RoleMiddleware($adminOnly),
        ]);
        $this->router->get('/admin/users', [$userController, 'index'], [
            $auth,
            new RoleMiddleware($adminOnly),
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
