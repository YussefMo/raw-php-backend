<?php

declare(strict_types=1);

/**
 * Front controller — the single entry point for every request.
 *
 * The PHP dev server is started with this file as its "router script":
 *
 *     php -S localhost:8000 -t public public/index.php
 *
 * so every request lands here, no matter which URL was requested.
 */

use App\Config\Config;
use App\Core\Application;
use App\Database\Database;
use App\Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

// Never let PHP print warnings/notices into the body: it would corrupt the JSON.
// Application::run() turns thrown errors into clean JSON responses instead.
ini_set('display_errors', '0');

$config = new Config(__DIR__ . '/../.env');
$db = (new Database($config))->connect();

$app = new Application($config, $db);
$app->run(Request::capture());
