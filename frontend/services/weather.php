<?php
/**
 * 天气服务接口
 * 优先使用高德天气 API（后台设置），否则使用 QWeather 或静态兜底
 */
header('Content-Type: application/json; charset=utf-8');

$mode = $_GET['mode'] ?? 'ip';
$lat  = $_GET['lat'] ?? null;
$lng  = $_GET['lng'] ?? null;
$slot = $_GET['slot'] ?? '1';

// 读取数据库中的天气 Key
$weatherKey = '';
$weatherType = 'qweather';
try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (is_file($rootPath . '/config/database.php') && is_file($rootPath . '/.installed')) {
        require_once $rootPath . '/config/config.php';
        require_once $rootPath . '/core/Database.php';
        $db = Database::getInstance();
        $row = $db->fetch("SELECT value FROM settings WHERE `key`='amap_weather_key'");
        if ($row && !empty($row['value'])) {
            $weatherKey = $row['value'];
            $weatherType = 'amap';
        }
    }
} catch (Throwable $e) {}

function outputFallback() {
    $fallbackFile = __DIR__ . '/weather.json';
    if (file_exists($fallbackFile)) {
        readfile($fallbackFile);
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
}

function fetchAmapWeather($key) {
    global $mode, $lat, $lng;
    
    // 高德天气 API 需要城市编码或经纬度
    // 优先使用传入的经纬度，否则使用 IP 定位
    $cityParam = '';
    if ($mode === 'geo' && $lat !== null && $lng !== null) {
        // 高德支持经纬度格式: 经度,纬度
        $cityParam = '&city=' . $lng . ',' . $lat;
    } else {
        // 使用 IP 定位获取城市编码
        $ipUrl = 'https://restapi.amap.com/v3/ip?key=' . $key;
        $ctx = @stream_context_create(['http' => ['timeout' => 5]]);
        $ipRaw = @file_get_contents($ipUrl, false, $ctx);
        if ($ipRaw) {
            $ipData = json_decode($ipRaw, true);
            if ($ipData && $ipData['status'] === '1' && !empty($ipData['adcode'])) {
                $cityParam = '&city=' . $ipData['adcode'];
            }
        }
    }
    
    if (empty($cityParam)) {
        return null;
    }
    
    // 获取实时天气
    $url = 'https://restapi.amap.com/v3/weather/weatherInfo?key=' . $key . $cityParam . '&extensions=base';
    $ctx = @stream_context_create(['http' => ['timeout' => 5]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    
    $data = json_decode($raw, true);
    if (!$data || $data['status'] !== '1' || empty($data['lives'])) return null;
    
    $live = $data['lives'][0];
    
    // 高德天气图标映射到 QWeather 图标编号
    $iconMap = [
        '晴' => '100', '少云' => '101', '晴间多云' => '101',
        '多云' => '101', '阴' => '104', '有风' => '300',
        '平静' => '999', '微风' => '300', '和风' => '300', '清风' => '300',
        '强风/劲风' => '300', '疾风' => '300', '大风' => '300', '烈风' => '300',
        '风暴' => '300', '狂爆风' => '300', '飓风' => '300', '热带风暴' => '300',
        '阵雨' => '300', '雷阵雨' => '302', '雷阵雨并伴有冰雹' => '304',
        '小雨' => '305', '中雨' => '306', '大雨' => '307', '暴雨' => '310',
        '大暴雨' => '310', '特大暴雨' => '310', '强阵雨' => '300',
        '毛毛雨/细雨' => '300', '雨' => '300', '小雪' => '400', '中雪' => '401',
        '大雪' => '402', '暴雪' => '403', '雨夹雪' => '404', '雨雪天气' => '404',
        '阵雪' => '400', '薄雾' => '501', '雾' => '501', '霾' => '502',
        '扬沙' => '503', '浮尘' => '503', '沙尘暴' => '503', '强沙尘暴' => '503',
        '热' => '100', '冷' => '999', '未知' => '999',
    ];
    
    $weather = $live['weather'] ?? '';
    $icon = '999';
    // 尝试精确匹配或模糊匹配
    if (isset($iconMap[$weather])) {
        $icon = $iconMap[$weather];
    } else {
        foreach ($iconMap as $pattern => $code) {
            if (mb_strpos($weather, $pattern) !== false) {
                $icon = $code;
                break;
            }
        }
    }
    
    return [
        'code' => 200,
        'data' => [
            'temp'      => $live['temperature'] ?? '--',
            'feelsLike' => $live['temperature'] ?? '--',
            'desc'      => $weather ?: '未知',
            'icon'      => $icon,
            'humidity'  => $live['humidity'] ?? '--',
            'windDir'   => $live['winddirection'] ?? '--',
            'windScale' => $live['windpower'] ?? '--',
            'vis'       => '--',
            'city'      => $live['city'] ?? ($live['province'] ?? '未知'),
            'obsTime'   => $live['reporttime'] ?? date('Y-m-d\TH:i+08:00'),
            'source'    => 'amap',
        ],
    ];
}

// 主逻辑
if ($mode === 'couple') {
    // 情侣模式：根据 slot 返回对应情侣所在地的天气
    $coupleData = null;
    try {
        $mapFile = __DIR__ . '/map-all.json';
        if (file_exists($mapFile)) {
            $mapData = json_decode(file_get_contents($mapFile), true);
            $lovers = $mapData['lovers'] ?? [];
            $idx = intval($slot) - 1;
            if (isset($lovers[$idx]) && !empty($lovers[$idx]['coords'])) {
                $lat = $lovers[$idx]['coords'][1];
                $lng = $lovers[$idx]['coords'][0];
                $mode = 'geo';
                $coupleData = ['lat' => $lat, 'lng' => $lng, 'name' => $lovers[$idx]['name'] ?? ''];
            }
        }
    } catch (Throwable $e) {}
}

if ($weatherType === 'amap' && $weatherKey) {
    $result = fetchAmapWeather($weatherKey);
    if ($result) {
        // 情侣模式下用对方昵称
        if ($coupleData && !empty($coupleData['name'])) {
            $result['data']['city'] = $coupleData['name'] . ' · ' . ($result['data']['city'] ?? '');
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

outputFallback();