<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Category;

/**
 * HTTP layer for categories.
 *
 *   GET    /categories       admin + author  (used by the "new post" dropdown)
 *   POST   /categories       admin only
 *   DELETE /categories/{id}  admin only
 *
 * Authorization is enforced by RoleMiddleware on the routes in Application;
 * this controller assumes the caller already passed that check.
 */
final class CategoryController
{
    private const MAX_NAME_LENGTH = 255;

    public function __construct(
        private Category $categories,
    ) {
    }

    /** GET /categories — list all categories. */
    public function index(Request $request): Response
    {
        return Response::json(['data' => $this->categories->all()]);
    }

    /**
     * POST /categories — create a category. Admin only.
     *
     * Body: { "name": "Science" }
     */
    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Name must be at most ' . self::MAX_NAME_LENGTH . ' characters.';
        }

        if ($errors !== []) {
            return Response::json(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        if ($this->categories->nameExists($name)) {
            return Response::json(['error' => 'That category already exists.'], 409);
        }

        $id = $this->categories->create($name);

        return Response::json(['data' => $this->categories->find($id)], 201);
    }

    /**
     * DELETE /categories/{id} — remove a category. Admin only.
     *
     * Refuses to delete a category that still has posts (409) rather than
     * leaving those posts pointing at a category that no longer exists.
     */
    public function destroy(Request $request): Response
    {
        $id = (int) ($request->param('id') ?? 0);

        if ($id <= 0) {
            return Response::json(['error' => 'A numeric category id is required.'], 422);
        }

        if ($this->categories->find($id) === null) {
            return Response::json(['error' => "Category {$id} was not found."], 404);
        }

        if ($this->categories->isInUse($id)) {
            return Response::json([
                'error' => 'Category is in use.',
                'message' => 'Reassign or delete its posts before deleting this category.',
            ], 409);
        }

        $this->categories->delete($id);

        return Response::noContent();
    }
}
