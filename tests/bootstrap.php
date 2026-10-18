<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap — prepares a dedicated test database.
 *
 * Tests must never touch development data, so they run against `blogs_test`
 * (see .env.testing). This script:
 *
 *   1. creates that database if it does not exist, and
 *   2. rebuilds its schema from the real migrations,
 *
 * so the tests always run against exactly the schema the app itself uses.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;
use App\Database\Migrator;

$config = new Config(__DIR__ . '/../.env.testing');

// 1. Create the database. A connection cannot select a database that does not
//    exist yet, so open a short-lived handle WITHOUT a dbname first.
$dsn = sprintf('mysql:host=%s;port=%s', $config->get('DB_HOST'), $config->get('DB_PORT'));
$server = new PDO($dsn, $config->get('DB_USERNAME'), $config->get('DB_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$server->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $config->get('DB_DATABASE')
));

// 2. Drop everything and migrate from zero, so every run starts from the same
//    known state (one admin + five starter categories).
$db = (new Database($config))->connect();
$silentLogger = static function (string $message): void {
};
(new Migrator($db, $config, __DIR__ . '/../database/migrations', $silentLogger))->fresh();
