<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Role;
use App\Http\Request;
use App\Http\Response;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;

/**
 * HTTP layer for posts.
 *
 * Reading is public; creating requires the "author" or "admin" role; updating
 * and deleting are allowed for an admin (any post) or the post's owner.
 *
 * "admin OR owner" is not a static role check, so it lives here in a small
 * helper rather than in RoleMiddleware.
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

    /** GET /me/posts — the current user's own posts. Any authenticated user. */
    public function mine(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return Response::json(['data' => $this->posts->byUser((int) $user->id)]);
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
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        $categoryId = $request->input('category_id');

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }

        // category_id is required and MUST reference a real category.
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
        $id = $this->posts->create($title, $body, (int) $categoryId, (int) $user->id);

        return Response::json(['data' => $this->posts->find($id)], 201);
    }

    /**
     * PATCH /posts/{id} — update a post.
     *
     * Only the fields present in the body are changed (partial update). Allowed
     * for admins (any post) and the post's owner. `user_id` is never updatable.
     *
     * Body: any of { "title": "...", "body": "...", "category_id": 1 }
     */
    public function update(Request $request): Response
    {
        $user = $request->user();
        $post = $this->findAuthorized($request, $user);

        if ($post instanceof Response) {
            return $post; // 401 / 404 / 403 already decided
        }

        $errors = [];
        $fields = [];

        if ($request->has('title')) {
            $title = trim((string) $request->input('title', ''));
            if ($title === '') {
                $errors['title'] = 'Title cannot be empty.';
            } else {
                $fields['title'] = $title;
            }
        }

        if ($request->has('body')) {
            $fields['body'] = trim((string) $request->input('body', ''));
        }

        if ($request->has('category_id')) {
            $categoryId = $request->input('category_id');
            if (filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
                $errors['category_id'] = 'A valid category id is required.';
            } elseif (!$this->categories->exists((int) $categoryId)) {
                $errors['category_id'] = 'That category does not exist.';
            } else {
                $fields['category_id'] = (int) $categoryId;
            }
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        if ($fields === []) {
            return Response::json(['error' => 'No updatable fields were provided.'], 422);
        }

        $id = (int) $post['id'];
        $this->posts->update($id, $fields);

        return Response::json(['data' => $this->posts->find($id)]);
    }

    /**
     * DELETE /posts/{id} — delete a post.
     *
     * Allowed for admins (any post) and the post's owner.
     */
    public function destroy(Request $request): Response
    {
        $user = $request->user();
        $post = $this->findAuthorized($request, $user);

        if ($post instanceof Response) {
            return $post;
        }

        $this->posts->delete((int) $post['id']);

        return Response::noContent();
    }

    /**
     * Resolve the target post and check the caller may modify it.
     *
     * @return array<string, mixed>|Response the post, or the error Response to send
     */
    private function findAuthorized(Request $request, ?User $user): array|Response
    {
        if ($user === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $id = (int) ($request->param('id') ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'A numeric post id is required.'], 422);
        }

        $post = $this->posts->find($id);
        if ($post === null) {
            return Response::json(['error' => "Post {$id} was not found."], 404);
        }

        if (!$this->canModify($user, $post)) {
            return Response::json([
                'error' => 'Forbidden',
                'message' => 'You can only change your own posts.',
            ], 403);
        }

        return $post;
    }

    /** Admins may change any post; everyone else only the ones they own. */
    private function canModify(User $user, array $post): bool
    {
        if ($user->role === Role::Admin->value) {
            return true;
        }

        return $post['user_id'] !== null && (int) $post['user_id'] === $user->id;
    }
}
