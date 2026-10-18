<?php

declare(strict_types=1);

namespace Tests;

use App\Config\Config;
use App\Core\Application;
use App\Database\Database;
use App\Http\Request;
use App\Http\Response;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for the API test suite.
 *
 * Every test calls the Application directly (`Application::handle()`), so we
 * exercise the real router, middleware, controllers and SQL — without a web
 * server or HTTP sockets.
 *
 * Each test runs inside a database transaction that is rolled back in tearDown,
 * so tests start from the same seeded state (one admin + five categories) and
 * never leak data into each other or into the dev database.
 */
abstract class TestCase extends BaseTestCase
{
    protected static Config $config;
    protected static PDO $db;

    protected function setUp(): void
    {
        parent::setUp();

        // Connect once for the whole suite.
        if (!isset(self::$db)) {
            self::$config = new Config(__DIR__ . '/../.env.testing');
            self::$db = (new Database(self::$config))->connect();
        }

        self::$db->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::$db->rollBack();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Request helpers
    // ------------------------------------------------------------------

    protected function app(): Application
    {
        return new Application(self::$config, self::$db);
    }

    /**
     * Send a request straight through the Application.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    protected function call(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null,
        array $query = [],
    ): Response {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer ' . $token];

        return $this->app()->handle(Request::create($method, $path, $body, $headers, $query));
    }

    /** @return array<string, mixed> */
    protected function json(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function assertStatus(int $expected, Response $response): void
    {
        self::assertSame($expected, $response->status(), 'Unexpected status. Body: ' . $response->body());
    }

    // ------------------------------------------------------------------
    // Auth helpers
    // ------------------------------------------------------------------

    protected function register(string $name, string $email, string $role = 'user', string $password = 'secret123'): Response
    {
        return $this->call('POST', '/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);
    }

    protected function login(string $email, string $password = 'secret123'): string
    {
        $response = $this->call('POST', '/auth/login', ['email' => $email, 'password' => $password]);
        self::assertSame(200, $response->status(), 'Login failed. Body: ' . $response->body());

        return (string) ($this->json($response)['access_token'] ?? '');
    }

    /** Register a fresh user (role: user or author) and return their token. */
    protected function actingAs(string $role, string $email): string
    {
        $this->assertStatus(201, $this->register(ucfirst($role) . ' Tester', $email, $role));

        return $this->login($email);
    }

    /** Token for the admin seeded by the users migration. */
    protected function adminToken(): string
    {
        return $this->login(self::$config->get('ADMIN_EMAIL'), self::$config->get('ADMIN_PASSWORD'));
    }

    // ------------------------------------------------------------------
    // Data helpers
    // ------------------------------------------------------------------

    /** A seeded category id (the migration creates five). */
    protected function categoryId(int $offset = 0): int
    {
        $ids = self::$db->query('SELECT id FROM categories ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        return (int) $ids[$offset];
    }

    protected function createPost(string $token, int $categoryId, string $title = 'A test post'): int
    {
        $response = $this->call('POST', '/posts', [
            'title' => $title,
            'body' => 'Body of the post',
            'category_id' => $categoryId,
        ], $token);
        self::assertSame(201, $response->status(), 'Create post failed. Body: ' . $response->body());

        return (int) $this->json($response)['data']['id'];
    }
}
