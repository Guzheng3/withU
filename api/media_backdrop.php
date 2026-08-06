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
if (!$media) {
    http_response_code(404);
    exit;
}

$url = trim((string)($media['backdrop_url'] ?? ''));
if ($url === '') $url = trim((string)($media['cover_url'] ?? ''));
if ($url === '') $url = '/assets/images/default_hero.jpg';
withu_media_image_response($url, 'backdrop', 'backdrop:' . (int)$media['id']);
