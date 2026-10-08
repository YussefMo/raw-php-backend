<?php

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;
use App\Models\Post;

$config = new Config(
    __DIR__ . '/../.env'
);

$database = new Database($config);

$db = $database->connect();

$post = new Post($db);

$result = $post->read();

$count = $result->rowCount();

if ($count > 0) {
    $post_arr = [
        'data' => []
    ];

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $post_arr['data'][] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'body' => html_entity_decode($row['body']),
            'author' => $row['author'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
        ];
    }

    echo json_encode($post_arr);
} else {
    echo json_encode([
        'err' => 'nothing here'
    ]);
}
