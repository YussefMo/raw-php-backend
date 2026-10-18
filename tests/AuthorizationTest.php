<?php

declare(strict_types=1);

namespace Tests;

/**
 * Authorization: who is allowed to reach which endpoint.
 *
 * The pattern is always the same three questions: no token (401),
 * wrong role (403), correct role (2xx).
 */
final class AuthorizationTest extends TestCase
{
    public function testAdminUserListIsAdminOnly(): void
    {
        $this->assertStatus(401, $this->call('GET', '/admin/users'));
        $this->assertStatus(200, $this->call('GET', '/admin/users', [], $this->adminToken()));
        $this->assertStatus(403, $this->call('GET', '/admin/users', [], $this->actingAs('author', 'az-author@example.com')));
        $this->assertStatus(403, $this->call('GET', '/admin/users', [], $this->actingAs('user', 'az-user@example.com')));
    }

    public function testCategoryListIsForAdminsAndAuthorsOnly(): void
    {
        $this->assertStatus(401, $this->call('GET', '/categories'));
        $this->assertStatus(200, $this->call('GET', '/categories', [], $this->adminToken()));
        $this->assertStatus(200, $this->call('GET', '/categories', [], $this->actingAs('author', 'az-author2@example.com')));
        $this->assertStatus(403, $this->call('GET', '/categories', [], $this->actingAs('user', 'az-user2@example.com')));
    }

    public function testOnlyAdminsCanCreateCategories(): void
    {
        $this->assertStatus(401, $this->call('POST', '/categories', ['name' => 'Science']));
        $this->assertStatus(403, $this->call('POST', '/categories', ['name' => 'Science'], $this->actingAs('author', 'az-author3@example.com')));
        $this->assertStatus(403, $this->call('POST', '/categories', ['name' => 'Science'], $this->actingAs('user', 'az-user3@example.com')));
        $this->assertStatus(201, $this->call('POST', '/categories', ['name' => 'Science'], $this->adminToken()));
    }

    public function testOnlyAdminsCanDeleteCategories(): void
    {
        $author = $this->actingAs('author', 'az-author4@example.com');

        $this->assertStatus(403, $this->call('DELETE', '/categories/' . $this->categoryId(4), [], $author));
    }

    public function testPublishingRequiresAuthorOrAdmin(): void
    {
        $title = ['title' => 'Hello', 'body' => 'Body', 'category_id' => $this->categoryId()];

        $this->assertStatus(401, $this->call('POST', '/posts', $title));
        $this->assertStatus(401, $this->call('POST', '/posts', $title, null));
        $this->assertStatus(201, $this->call('POST', '/posts', $title, $this->actingAs('author', 'az-author5@example.com')));
        $this->assertStatus(403, $this->call('POST', '/posts', $title, $this->actingAs('user', 'az-user5@example.com')));
    }

    public function testEditingAndDeletingAreForAdminsAndOwners(): void
    {
        $owner = $this->actingAs('author', 'az-owner@example.com');
        $stranger = $this->actingAs('author', 'az-stranger@example.com');
        $plainUser = $this->actingAs('user', 'az-plain@example.com');
        $postId = $this->createPost($owner, $this->categoryId());

        // Update: only the owner or an admin.
        $this->assertStatus(401, $this->call('PATCH', "/posts/{$postId}", ['title' => 'x']));
        $this->assertStatus(403, $this->call('PATCH', "/posts/{$postId}", ['title' => 'x'], $stranger));
        $this->assertStatus(403, $this->call('PATCH', "/posts/{$postId}", ['title' => 'x'], $plainUser));
        $this->assertStatus(200, $this->call('PATCH', "/posts/{$postId}", ['title' => 'Mine'], $owner));
        $this->assertStatus(200, $this->call('PATCH', "/posts/{$postId}", ['title' => 'Admin was here'], $this->adminToken()));

        // Delete: only the owner or an admin.
        $this->assertStatus(403, $this->call('DELETE', "/posts/{$postId}", [], $stranger));
        $this->assertStatus(403, $this->call('DELETE', "/posts/{$postId}", [], $plainUser));
        $this->assertStatus(204, $this->call('DELETE', "/posts/{$postId}", [], $owner));
    }

    public function testAdminCanDeleteAnyPostAuthorCannot(): void
    {
        $owner = $this->actingAs('author', 'az-owner2@example.com');
        $stranger = $this->actingAs('author', 'az-stranger2@example.com');
        $postId = $this->createPost($owner, $this->categoryId());

        $this->assertStatus(403, $this->call('DELETE', "/posts/{$postId}", [], $stranger));
        $this->assertStatus(204, $this->call('DELETE', "/posts/{$postId}", [], $this->adminToken()));
    }
}
