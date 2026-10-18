<?php

declare(strict_types=1);

namespace Tests;

/**
 * Categories: list, create, delete (and the rules that protect posts).
 */
final class CategoryTest extends TestCase
{
    public function testIndexReturnsTheSeededCategories(): void
    {
        $response = $this->call('GET', '/categories', [], $this->adminToken());

        $this->assertStatus(200, $response);
        self::assertGreaterThanOrEqual(5, count($this->json($response)['data']));
    }

    public function testAdminCanCreateACategory(): void
    {
        $response = $this->call('POST', '/categories', ['name' => 'Science'], $this->adminToken());

        $this->assertStatus(201, $response);
        self::assertSame('Science', $this->json($response)['data']['name']);
    }

    public function testDuplicateCategoryNamesAreRejected(): void
    {
        $admin = $this->adminToken();

        $this->assertStatus(201, $this->call('POST', '/categories', ['name' => 'Sports'], $admin));
        // Case-insensitive: "sports" collides with "Sports"
        $this->assertStatus(409, $this->call('POST', '/categories', ['name' => 'sports'], $admin));
    }

    public function testCategoryNameIsValidated(): void
    {
        $admin = $this->adminToken();

        $this->assertStatus(422, $this->call('POST', '/categories', ['name' => '   '], $admin));
        $this->assertStatus(422, $this->call('POST', '/categories', ['name' => str_repeat('x', 256)], $admin));
    }

    public function testAdminCanDeleteAnUnusedCategory(): void
    {
        $admin = $this->adminToken();
        $created = $this->json($this->call('POST', '/categories', ['name' => 'Temporary'], $admin))['data'];

        $this->assertStatus(204, $this->call('DELETE', '/categories/' . $created['id'], [], $admin));
    }

    public function testCategoryInUseCannotBeDeleted(): void
    {
        $admin = $this->adminToken();
        $author = $this->actingAs('author', 'cat-inuse@example.com');
        $categoryId = $this->categoryId();
        $this->createPost($author, $categoryId);

        $response = $this->call('DELETE', "/categories/{$categoryId}", [], $admin);

        $this->assertStatus(409, $response);
    }

    public function testDeletingAnUnknownCategoryReturns404(): void
    {
        $this->assertStatus(404, $this->call('DELETE', '/categories/999999', [], $this->adminToken()));
    }
}
