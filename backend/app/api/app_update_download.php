<?php
error_reporting(E_ALL & ~E_DEPRECATED);
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

migrate_schema_if_needed();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid update id');
}

try {
    $row = $db->fetch(
        "SELECT `apk_url`
         FROM `remote_updates`
         WHERE `id` = :id AND `enabled` = 1
         LIMIT 1",
        ['id' => $id]
    );
} catch (Throwable $e) {
    error_log('Remote update download lookup error: ' . $e->getMessage());
    http_response_code(500);
    exit('Update download temporarily unavailable');
}

$targetUrl = trim((string)($row['apk_url'] ?? ''));
if (!$row || !preg_match('#^https?://#i', $targetUrl) || strlen($targetUrl) > 512) {
    http_response_code(404);
    exit('Update download not found');
}

header('Location: ' . $targetUrl, true, 302);
exit;
