<?php

$main = require __DIR__ . '/database.php';

return [
    'host' => $main['host'] ?? '127.0.0.1',
    'port' => $main['port'] ?? 3306,
    'dbname' => getenv('WITHU_MEDIA_DB') ?: 'withu_media',
    'username' => getenv('WITHU_MEDIA_DB_USER') ?: ($main['username'] ?? 'withu'),
    'password' => getenv('WITHU_MEDIA_DB_PASSWORD') ?: ($main['password'] ?? ''),
    'charset' => $main['charset'] ?? 'utf8mb4',
    'options' => $main['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
