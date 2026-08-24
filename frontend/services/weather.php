<?php
/**
 * 天气服务接口
 * 纯兜底服务，返回静态数据
 * 实际天气数据由前端通过 QWeather JS SDK 直接获取
 */
header('Content-Type: application/json; charset=utf-8');

$weatherFile = __DIR__ . '/weather.json';
if (file_exists($weatherFile)) {
    readfile($weatherFile);
} else {
    echo json_encode([
        'code' => 200,
        'data' => [
            'temp' => '--', 'feelsLike' => '--', 'desc' => '未知',
            'icon' => '999', 'humidity' => '--', 'windDir' => '--',
            'windScale' => '--', 'vis' => '--', 'city' => '未知',
            'obsTime' => date('Y-m-d\TH:i+08:00'), 'source' => 'fallback',
        ],
    ]);
}