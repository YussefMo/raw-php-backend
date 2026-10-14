<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Category;
use App\Models\Post;

/**
 * HTTP layer for posts.
 *
 * Reading is public; creating requires the "author" or "admin" role (enforced by
 * RoleMiddleware on the route, not here).
 */
final class PostController
{
    public function __construct(
        private Post $posts,
        private Category $categories,
    ) {
    }

    /** GET / (and GET /posts) — list all posts. Public. */
    public function index(Request $request): Response
    {
        return Response::json(['data' => $this->posts->all()]);
    }

    /**
     * GET /posts/{id} — one post.
     *
     * Also handles the legacy GET /id?id=1 form, which passes the id in the
     * query string instead of the path.
     */
    public function show(Request $request): Response
    {
        $id = (int) ($request->param('id') ?? $request->query('id', 0));

        if ($id <= 0) {
            return Response::json(['error' => 'A numeric post id is required.'], 422);
        }

        $post = $this->posts->find($id);

        if ($post === null) {
            return Response::json(['error' => "Post {$id} was not found."], 404);
        }

        return Response::json(['data' => $post]);
    }

    /**
     * POST /posts — create a post. Requires role: admin or author.
     *
     * Body: { "title": "...", "body": "...", "category_id": 1 }
     */
    public function store(Request $request): Response
    {
        $user = $request->user();

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        $categoryId = $request->input('category_id');

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }

        // category_id is required and MUST reference a real category.
        // This is the fix for "the API accepted category_id = 10 when only 5
        // categories exist": we verify it against the database before saving.
        if ($categoryId === null || $categoryId === '' || filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
            $errors['category_id'] = 'A valid category id is required.';
        } elseif (!$this->categories->exists((int) $categoryId)) {
            $errors['category_id'] = 'That category does not exist.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        // The post is attributed to the authenticated user, never to a value the
        // client sends — otherwise anyone could post as someone else.
        $author = $user?->name ?? 'unknown';

        $id = $this->posts->create($title, $body, $author, (int) $categoryId);

        return Response::json(['data' => $this->posts->find($id)], 201);
    }
}
