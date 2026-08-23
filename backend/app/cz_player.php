<?php
// cz_player.php —— 已并入 WithU 原播放界面（watch_play.php?source=cz）
// 保留本文件仅为兼容旧链接，直接 302 跳转。
$url = trim((string)($_GET['url'] ?? ''));
if ($url === '') {
    header('Location: /watch.php#cz'); exit;
}
header('Location: /watch_play.php?source=cz&url=' . rawurlencode($url), true, 302);
exit;
