<?php
/**
 * 天气服务接口（网站前台）
 *
 * 数据源统一为和风天气，由服务端读取后台配置的 API Host 与凭据；
 * 网页只拿到天气结果，不会接触 API Key / JWT Token / 私钥。
 *
 * 保持旧契约：{ code: 200, data: { temp, feelsLike, desc, icon, ... } }
 * 课表 App 使用 /api/timetable.php?action=weather，底层复用同一套 Weather.php。
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$mode = $_GET['mode'] ?? 'geo';
$lat  = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng  = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
$city = trim((string)($_GET['city'] ?? ''));
$slot = (int)($_GET['slot'] ?? 1);

/** 拿不到真实天气时如实返回「不可用」，不再使用模拟数据。 */
function outputFallback(): void
{
    echo json_encode([
        'code' => 200,
        'data' => [
            'temp' => '--', 'feelsLike' => '--', 'desc' => '未知',
            'icon' => '999', 'humidity' => '--', 'windDir' => '--',
            'windScale' => '--', 'vis' => '--', 'city' => '--',
            'obsTime' => date('Y-m-d\TH:i+08:00'), 'source' => 'none',
            'available' => false,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$backendRoot = dirname(__DIR__, 2) . '/backend/app';
if (!is_file($backendRoot . '/config/database.php') || !is_file($backendRoot . '/.installed')) {
    outputFallback();
}

try {
    require_once $backendRoot . '/config/config.php';
    require_once $backendRoot . '/core/Database.php';
    require_once $backendRoot . '/core/helpers.php';
    require_once $backendRoot . '/core/withu.php';
    require_once $backendRoot . '/core/Weather.php';
} catch (Throwable $e) {
    outputFallback();
}

// 公开接口限流：天气数据会消耗和风额度，避免被单 IP 高频刷取。
if (!withu_weather_rate_limit('web', 180, 60)) {
    http_response_code(429);
    echo json_encode([
        'code' => 429,
        'error' => 'weather_rate_limited',
        'data' => [
            'temp' => '--', 'feelsLike' => '--', 'desc' => '请求过于频繁',
            'icon' => '999', 'humidity' => '--', 'windDir' => '--',
            'windScale' => '--', 'vis' => '--', 'city' => '--',
            'obsTime' => date('Y-m-d\TH:i+08:00'), 'source' => 'none',
            'available' => false,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 情侣模式：优先使用显式坐标（与卡片头像一一对应），否则按 slot 从 map-all.json 解析
$coupleData = null;
if ($mode === 'couple') {
    if ($lat !== null && $lng !== null) {
        $mode = 'geo';
        $coupleData = [
            'lat' => $lat,
            'lng' => $lng,
            'name' => trim((string)($_GET['name'] ?? '')),
            'label' => trim((string)($_GET['label'] ?? '')),
        ];
    } else {
        try {
            $mapFile = __DIR__ . '/map-all.json';
            if (is_file($mapFile)) {
                $mapData = json_decode((string)file_get_contents($mapFile), true);
                $lovers = is_array($mapData) ? ($mapData['lovers'] ?? []) : [];
                $idx = $slot - 1;
                if (isset($lovers[$idx]) && !empty($lovers[$idx]['coords'])) {
                    $lat = (float)$lovers[$idx]['coords'][1];
                    $lng = (float)$lovers[$idx]['coords'][0];
                    $mode = 'geo';
                    $coupleData = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'name' => (string)($lovers[$idx]['name'] ?? ''),
                        'label' => (string)($lovers[$idx]['label'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            // 地图数据异常时走普通坐标/城市模式
        }
    }
}

// 城市名模式：先用 GeoAPI 换成坐标
if ($mode === 'city' && $city !== '' && ($lat === null || $lng === null)) {
    $lookup = withu_weather_lookup($city, ['number' => 1]);
    if (!empty($lookup['success']) && !empty($lookup['data']['locations'][0])) {
        $location = $lookup['data']['locations'][0];
        $lat = (float)$location['latitude'];
        $lng = (float)$location['longitude'];
        if ($coupleData === null) {
            $coupleData = [
                'lat' => $lat,
                'lng' => $lng,
                'name' => '',
                'label' => (string)($location['name'] ?? $city),
            ];
        }
    }
}

if ($lat === null || $lng === null) {
    outputFallback();
}

$label = '';
if (is_array($coupleData)) {
    $label = trim((string)($coupleData['label'] ?? ''));
    if ($label === '') {
        $label = trim((string)($coupleData['name'] ?? ''));
    }
}

$result = withu_weather_now($lat, $lng, ['label' => $label]);
if (empty($result['success']) || empty($result['data'])) {
    outputFallback();
}

$data = $result['data'];

// 情侣/固定位置模式下用昵称或地标作为城市展示名
if (is_array($coupleData)) {
    $cityText = trim((string)($coupleData['label'] ?? ''));
    if ($cityText === '') {
        $cityText = trim((string)($coupleData['name'] ?? ''));
    }
    if ($cityText !== '') {
        $data['city'] = $cityText;
    }
}

echo json_encode(['code' => 200, 'data' => $data], JSON_UNESCAPED_UNICODE);
