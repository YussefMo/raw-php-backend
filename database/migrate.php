<?php

declare(strict_types=1);

/**
 * CLI entry point for migrations: `composer run migrate`.
 *
 * It uses the same Config + Database classes as the web app, so migrations
 * always run against the database defined in .env.
 */

use App\Config\Config;
use App\Database\Database;
use App\Database\Migrator;

require __DIR__ . '/../vendor/autoload.php';

$config = new Config(__DIR__ . '/../.env');
$db = (new Database($config))->connect();

echo 'Running migrations from database/migrations ...' . PHP_EOL;

(new Migrator($db, $config, __DIR__ . '/migrations'))->run();
