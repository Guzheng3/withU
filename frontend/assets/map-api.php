<?php
/**
 * 地图 API 接口
 * 返回 map-all.json 中的地图数据
 */
header('Content-Type: application/json; charset=utf-8');

$mapFile = __DIR__ . '/../services/map-all.json';
if (file_exists($mapFile)) {
    readfile($mapFile);
} else {
    echo json_encode([
        'lovers' => [],
        'loveStartDate' => '',
        'milestones' => [],
        'events' => [],
        'albums' => [],
        'messages' => [],
        'moments' => [],
    ]);
}