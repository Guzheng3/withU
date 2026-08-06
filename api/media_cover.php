<?php
header('Content-Type: image/jpeg');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/MediaDatabase.php';
require_once __DIR__ . '/../core/MediaSchema.php';
require_once __DIR__ . '/../core/MediaRepository.php';
require_once __DIR__ . '/../core/MediaImages.php';

$auth = new Auth();
withu_require_couple_user($auth);
$id = (int)($_GET['id'] ?? 0);
$media = $id > 0 ? withu_media_fetch($id) : null;
if (!$media || trim((string)$media['cover_url']) === '') {
    http_response_code(404);
    exit;
}

withu_media_image_response(trim((string)$media['cover_url']), 'cover', 'cover:' . (int)$media['id']);
