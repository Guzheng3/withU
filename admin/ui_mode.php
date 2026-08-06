<?php
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
require_csrf();

$mode = (string)($_POST['mode'] ?? 'current');
if (!in_array($mode, ['current', 'apple'], true)) {
    $mode = 'current';
}

$next = (string)($_POST['next'] ?? '/admin/index.php');
if ($next === '' || strpos($next, '/admin/') !== 0 || strpos($next, "\n") !== false || strpos($next, "\r") !== false) {
    $next = '/admin/index.php';
}

$db = Database::getInstance();
$existing = $db->fetch("SELECT id FROM settings WHERE `key` = 'admin_ui_mode' LIMIT 1");
$data = [
    'value' => $mode,
    'description' => '后台界面模式：current/apple',
    'updated_at' => date('Y-m-d H:i:s'),
];
if ($existing) {
    $db->update('settings', $data, '`key` = :key', ['key' => 'admin_ui_mode']);
} else {
    $db->insert('settings', ['key' => 'admin_ui_mode'] + $data);
}

header('Location: ' . $next);
exit;
