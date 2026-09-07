<?php
error_reporting(E_ALL & ~E_DEPRECATED);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

migrate_schema_if_needed();

$db = Database::getInstance();

function withu_remote_update_payload(?array $row): array {
    if (!$row) {
        return ['success' => true, 'has_update' => false];
    }

    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    $sha256 = strtolower(trim((string)($row['sha256'] ?? '')));
    if ($apkUrl === '' || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        error_log('Remote update release #' . (int)($row['id'] ?? 0) . ' has invalid APK metadata');
        return ['success' => true, 'has_update' => false];
    }

    $isExternalUrl = (bool)preg_match('#^https?://#i', $apkUrl);
    if ($isExternalUrl) {
        $downloadUrl = BASE_URL . '/api/app_update_download.php?id=' . (int)$row['id'];
    } else {
        $localPath = UPLOAD_DIR . '/' . ltrim($apkUrl, '/');
        if (!is_file($localPath)) {
            return ['success' => true, 'has_update' => false];
        }
        $downloadUrl = upload_url($apkUrl);
    }

    return [
        'success' => true,
        'has_update' => true,
        'release' => [
            'version' => trim((string)($row['version'] ?? '')),
            'title' => trim((string)($row['title'] ?? '')) ?: '版本更新',
            'body' => (string)($row['body'] ?? ''),
            'download_url' => $downloadUrl,
            'sha256' => $sha256,
            'force_update' => (int)($row['force_update'] ?? 0) === 1,
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ],
    ];
}

try {
    $release = $db->fetch(
        "SELECT *
         FROM `remote_updates`
         WHERE `enabled` = 1
         ORDER BY `id` DESC
         LIMIT 1"
    );
    withu_json_response(withu_remote_update_payload($release));
} catch (Throwable $e) {
    error_log('Remote update API error: ' . $e->getMessage());
    withu_json_response(['success' => false, 'has_update' => false, 'message' => 'Update check temporarily unavailable'], 500);
}
