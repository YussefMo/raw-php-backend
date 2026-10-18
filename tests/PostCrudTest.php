<?php

declare(strict_types=1);

namespace Tests;

/**
 * Full CRUD on posts: list, read, create, update, delete.
 */
final class PostCrudTest extends TestCase
{
    // ---- Read ------------------------------------------------------------

    public function testIndexListsPosts(): void
    {
        $token = $this->actingAs('author', 'crud-list@example.com');
        $this->createPost($token, $this->categoryId(), 'Listed post');

        $response = $this->call('GET', '/');

        $this->assertStatus(200, $response);
        self::assertContains('Listed post', array_column($this->json($response)['data'], 'title'));
    }

    public function testShowReturnsASinglePostWithItsRelations(): void
    {
        $token = $this->actingAs('author', 'crud-show@example.com');
        $postId = $this->createPost($token, $this->categoryId(), 'Single post');

        $response = $this->call('GET', "/posts/{$postId}");

        $this->assertStatus(200, $response);
        $post = $this->json($response)['data'];

        self::assertSame('Single post', $post['title']);
        self::assertSame($this->categoryId(), (int) $post['category_id']);
        // author and category_name come from the JOINs, not from the posts row
        self::assertSame('Author Tester', $post['author']);
        self::assertNotEmpty($post['category_name']);
    }

    public function testShowReturns404ForAnUnknownPost(): void
    {
        $this->assertStatus(404, $this->call('GET', '/posts/999999'));
    }

    public function testMyPostsReturnsOnlyTheCallersPosts(): void
    {
        $first = $this->actingAs('author', 'crud-mine-a@example.com');
        $second = $this->actingAs('author', 'crud-mine-b@example.com');
        $this->createPost($first, $this->categoryId(), 'First author post');
        $this->createPost($second, $this->categoryId(), 'Second author post');

        $response = $this->call('GET', '/me/posts', [], $first);

        $this->assertStatus(200, $response);
        $titles = array_column($this->json($response)['data'], 'title');
        self::assertContains('First author post', $titles);
        self::assertNotContains('Second author post', $titles);
    }

    // ---- Create ----------------------------------------------------------

    public function testCreateAttributesThePostToTheCaller(): void
    {
        $token = $this->actingAs('author', 'crud-owner@example.com');

        $response = $this->call('POST', '/posts', [
            'title' => 'Mine',
            'body' => 'Body',
            'category_id' => $this->categoryId(),
        ], $token);

        $this->assertStatus(201, $response);
        $post = $this->json($response)['data'];
        $me = $this->json($this->call('GET', '/auth/me', [], $token))['user'];

        self::assertSame('Author Tester', $post['author']);
        self::assertSame((int) $me['id'], (int) $post['user_id']);
    }

    public function testCreateValidatesTitleAndCategory(): void
    {
        $token = $this->actingAs('author', 'crud-validate@example.com');

        $noTitle = $this->call('POST', '/posts', ['body' => 'b', 'category_id' => $this->categoryId()], $token);
        $this->assertStatus(422, $noTitle);
        self::assertArrayHasKey('title', $this->json($noTitle)['errors']);

        $noCategory = $this->call('POST', '/posts', ['title' => 'x', 'body' => 'b'], $token);
        $this->assertStatus(422, $noCategory);
        self::assertArrayHasKey('category_id', $this->json($noCategory)['errors']);

        $badCategory = $this->call('POST', '/posts', ['title' => 'x', 'body' => 'b', 'category_id' => 999999], $token);
        $this->assertStatus(422, $badCategory);
        self::assertArrayHasKey('category_id', $this->json($badCategory)['errors']);
    }

    // ---- Update ----------------------------------------------------------

    public function testOwnerCanUpdateAllEditableFields(): void
    {
        $token = $this->actingAs('author', 'crud-update@example.com');
        $postId = $this->createPost($token, $this->categoryId(), 'Before');

        $response = $this->call('PATCH', "/posts/{$postId}", [
            'title' => 'After',
            'body' => 'Updated body',
            'category_id' => $this->categoryId(1),
        ], $token);

        $this->assertStatus(200, $response);
        $post = $this->json($response)['data'];

        self::assertSame('After', $post['title']);
        self::assertSame('Updated body', $post['body']);
        self::assertSame($this->categoryId(1), (int) $post['category_id']);
    }

    public function testUpdateIsPartialAndLeavesUntouchedFieldsAlone(): void
    {
        $token = $this->actingAs('author', 'crud-partial@example.com');
        $postId = $this->createPost($token, $this->categoryId(), 'Keep this title');

        $response = $this->call('PATCH', "/posts/{$postId}", ['body' => 'Only the body changed'], $token);

        $this->assertStatus(200, $response);
        $post = $this->json($response)['data'];

        self::assertSame('Keep this title', $post['title']);
        self::assertSame('Only the body changed', $post['body']);
    }

    public function testUpdatePersistsToTheDatabase(): void
    {
        $token = $this->actingAs('author', 'crud-persist@example.com');
        $postId = $this->createPost($token, $this->categoryId());

        $this->call('PATCH', "/posts/{$postId}", ['title' => 'Persisted'], $token);

        // Re-read through a fresh request, not from the PATCH response.
        $post = $this->json($this->call('GET', "/posts/{$postId}"))['data'];
        self::assertSame('Persisted', $post['title']);
    }

    public function testUpdateValidatesTheNewValues(): void
    {
        $token = $this->actingAs('author', 'crud-update-bad@example.com');
        $postId = $this->createPost($token, $this->categoryId());

        $this->assertStatus(422, $this->call('PATCH', "/posts/{$postId}", ['title' => '   '], $token));
        $this->assertStatus(422, $this->call('PATCH', "/posts/{$postId}", ['category_id' => 999999], $token));
        $this->assertStatus(422, $this->call('PATCH', "/posts/{$postId}", ['category_id' => 'abc'], $token));
    }

    public function testUpdateRejectsAnEmptyPayload(): void
    {
        $token = $this->actingAs('author', 'crud-update-empty@example.com');
        $postId = $this->createPost($token, $this->categoryId());

        $this->assertStatus(422, $this->call('PATCH', "/posts/{$postId}", [], $token));
    }

    public function testUpdateCannotReassignTheOwner(): void
    {
        $owner = $this->actingAs('author', 'crud-reassign-a@example.com');
        $other = $this->actingAs('author', 'crud-reassign-b@example.com');
        $otherId = (int) $this->json($this->call('GET', '/auth/me', [], $other))['user']['id'];
        $postId = $this->createPost($owner, $this->categoryId());

        $response = $this->call('PATCH', "/posts/{$postId}", [
            'title' => 'Still mine',
            'user_id' => $otherId, // user_id is not updatable
        ], $owner);

        $this->assertStatus(200, $response);
        self::assertNotSame($otherId, (int) $this->json($response)['data']['user_id']);
    }

    public function testUpdateReturns404ForAnUnknownPost(): void
    {
        $token = $this->actingAs('author', 'crud-update-404@example.com');

        $this->assertStatus(404, $this->call('PATCH', '/posts/999999', ['title' => 'x'], $token));
    }

    // ---- Delete ----------------------------------------------------------

    public function testOwnerCanDeleteTheirPost(): void
    {
        $token = $this->actingAs('author', 'crud-delete@example.com');
        $postId = $this->createPost($token, $this->categoryId());

        $this->assertStatus(204, $this->call('DELETE', "/posts/{$postId}", [], $token));
        $this->assertStatus(404, $this->call('GET', "/posts/{$postId}"));
    }

    public function testDeleteRequiresATokenAnd404sUnknownIds(): void
    {
        $this->assertStatus(401, $this->call('DELETE', '/posts/1'));
        $this->assertStatus(404, $this->call('DELETE', '/posts/999999', [], $this->adminToken()));
    }
}
