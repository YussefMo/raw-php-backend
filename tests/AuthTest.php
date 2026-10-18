<?php

declare(strict_types=1);

namespace Tests;

/**
 * Authentication: register, login, and "who am I".
 */
final class AuthTest extends TestCase
{
    public function testRegisterCreatesAUserWithoutLeakingThePasswordHash(): void
    {
        $response = $this->register('Alice', 'alice@example.com', 'author');

        $this->assertStatus(201, $response);
        $user = $this->json($response)['user'];

        self::assertSame('Alice', $user['name']);
        self::assertSame('alice@example.com', $user['email']);
        self::assertSame('author', $user['role']);
        self::assertArrayNotHasKey('password_hash', $user);
        self::assertArrayNotHasKey('password', $user);
    }

    public function testRegisterDefaultsToTheUserRole(): void
    {
        $response = $this->call('POST', '/auth/register', [
            'name' => 'NoRole',
            'email' => 'norole@example.com',
            'password' => 'secret123',
        ]);

        $this->assertStatus(201, $response);
        self::assertSame('user', $this->json($response)['user']['role']);
    }

    public function testRegisterRejectsInvalidInput(): void
    {
        $response = $this->call('POST', '/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $this->assertStatus(422, $response);
        $errors = $this->json($response)['errors'];
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('password', $errors);
    }

    public function testRegisterCannotSelfAssignTheAdminRole(): void
    {
        $response = $this->register('Hacker', 'hacker@example.com', 'admin');

        $this->assertStatus(422, $response);
        self::assertArrayHasKey('role', $this->json($response)['errors']);
    }

    public function testRegisterRejectsADuplicateEmail(): void
    {
        $this->assertStatus(201, $this->register('First', 'dup@example.com'));

        $this->assertStatus(409, $this->register('Second', 'dup@example.com'));
    }

    public function testLoginReturnsATokenAndTheUser(): void
    {
        $this->register('Bob', 'bob@example.com');

        $response = $this->call('POST', '/auth/login', [
            'email' => 'bob@example.com',
            'password' => 'secret123',
        ]);

        $this->assertStatus(200, $response);
        $body = $this->json($response);

        self::assertNotEmpty($body['access_token']);
        self::assertSame('Bearer', $body['token_type']);
        self::assertSame('bob@example.com', $body['user']['email']);
        self::assertArrayNotHasKey('password_hash', $body['user']);
    }

    public function testLoginRejectsAWrongPassword(): void
    {
        $this->register('Carol', 'carol@example.com');

        $response = $this->call('POST', '/auth/login', [
            'email' => 'carol@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertStatus(401, $response);
    }

    public function testLoginRejectsAnUnknownEmail(): void
    {
        $response = $this->call('POST', '/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $this->assertStatus(401, $response);
    }

    public function testEmailIsCaseInsensitive(): void
    {
        $this->register('Dave', 'Dave@Example.com');

        $response = $this->call('POST', '/auth/login', [
            'email' => 'dave@example.com',
            'password' => 'secret123',
        ]);

        $this->assertStatus(200, $response);
    }

    public function testMeRequiresAToken(): void
    {
        $this->assertStatus(401, $this->call('GET', '/auth/me'));
    }

    public function testMeRejectsAGarbageToken(): void
    {
        $this->assertStatus(401, $this->call('GET', '/auth/me', [], 'not.a.real.token'));
    }

    public function testMeReturnsTheAuthenticatedUser(): void
    {
        $token = $this->actingAs('author', 'me@example.com');

        $response = $this->call('GET', '/auth/me', [], $token);

        $this->assertStatus(200, $response);
        self::assertSame('me@example.com', $this->json($response)['user']['email']);
        self::assertSame('author', $this->json($response)['user']['role']);
    }
}
