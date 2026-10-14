<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
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

        if ($title === '') {
            return Response::json([
                'error' => 'Validation failed',
                'errors' => ['title' => 'Title is required.'],
            ], 422);
        }

        // The post is attributed to the authenticated user, never to a value the
        // client sends — otherwise anyone could post as someone else.
        $author = $user?->name ?? 'unknown';

        $id = $this->posts->create(
            $title,
            $body,
            $author,
            $categoryId === null ? null : (int) $categoryId,
        );

        return Response::json(['data' => $this->posts->find($id)], 201);
    }
}
