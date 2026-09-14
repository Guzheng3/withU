<?php
/**
 * 天气服务接口
 * 优先使用高德天气 API（后台设置），否则使用 Open-Meteo（免 Key）实时天气，最后静态兜底
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');


$mode = $_GET['mode'] ?? 'ip';
$lat  = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng  = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$city = $_GET['city'] ?? null;
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

// WMO 天气代码 -> [中文描述, QWeather 图标代码]
function wmoToIcon($code) {
    $map = [
        0  => ['晴', '100'],
        1  => ['晴间多云', '103'],
        2  => ['多云', '101'],
        3  => ['阴', '104'],
        45 => ['雾', '501'],
        48 => ['雾', '501'],
        51 => ['小雨', '305'],
        53 => ['小雨', '305'],
        55 => ['小雨', '305'],
        56 => ['冻雨', '306'],
        57 => ['冻雨', '306'],
        61 => ['小雨', '305'],
        63 => ['中雨', '306'],
        65 => ['大雨', '307'],
        66 => ['冻雨', '306'],
        67 => ['冻雨', '306'],
        71 => ['小雪', '400'],
        73 => ['中雪', '401'],
        75 => ['大雪', '402'],
        77 => ['阵雪', '400'],
        80 => ['阵雨', '300'],
        81 => ['阵雨', '300'],
        82 => ['强阵雨', '300'],
        85 => ['阵雪', '400'],
        86 => ['阵雪', '400'],
        95 => ['雷阵雨', '302'],
        96 => ['雷阵雨并伴有冰雹', '304'],
        99 => ['雷阵雨并伴有冰雹', '304'],
    ];
    return $map[$code] ?? ['未知', '999'];
}

// Open-Meteo 实时天气（免 Key，直接按经纬度查询）
function fetchOpenMeteoWeather($lat, $lng) {
    $url = 'https://api.open-meteo.com/v1/forecast'
        . '?latitude=' . rawurlencode(sprintf('%.6f', $lat))
        . '&longitude=' . rawurlencode(sprintf('%.6f', $lng))
        . '&current=temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,visibility'
        . '&timezone=Asia%2FShanghai';
    $ctx = @stream_context_create(['http' => ['timeout' => 6]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;

    $data = json_decode($raw, true);
    $cur = $data['current'] ?? null;
    if (!$cur || !isset($cur['temperature_2m'])) return null;

    list($desc, $icon) = wmoToIcon(intval($cur['weather_code'] ?? -1));
    $obsTime = date('Y-m-d\TH:i:s+08:00');
    if (!empty($cur['time'])) {
        $ts = strtotime($cur['time']);
        if ($ts) $obsTime = date('Y-m-d\TH:i:s+08:00', $ts);
    }

    return [
        'code' => 200,
        'data' => [
            'temp'      => round(floatval($cur['temperature_2m'])),
            'feelsLike' => isset($cur['apparent_temperature']) ? round(floatval($cur['apparent_temperature'])) : '--',
            'desc'      => $desc,
            'icon'      => $icon,
            'humidity'  => isset($cur['relative_humidity_2m']) ? round(floatval($cur['relative_humidity_2m'])) : '--',
            'windDir'   => '--',
            'windScale' => '--',
            'vis'       => isset($cur['visibility']) ? round(floatval($cur['visibility']) / 1000) : '--',
            'city'      => '未知',
            'obsTime'   => $obsTime,
            'source'    => 'open-meteo',
        ],
    ];
}

// 结果缓存（10 分钟），上游异常时可回退旧缓存
function cacheWeather($lat, $lng, $payload) {
    $dir = __DIR__ . '/runtime';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_dir($dir)) return;
    $key = 'wx-' . sprintf('%.2f_%.2f', $lat, $lng) . '.json';
    @file_put_contents($dir . '/' . $key, json_encode([
        'ts' => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_UNICODE));
}

function loadCachedWeather($lat, $lng, $allowStale) {
    $key = 'wx-' . sprintf('%.2f_%.2f', $lat, $lng) . '.json';
    $file = __DIR__ . '/runtime/' . $key;
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!$data || empty($data['payload'])) return null;
    if (!$allowStale && (time() - intval($data['ts'] ?? 0)) > 600) return null;
    return $data['payload'];
}

// 经纬度 -> adcode（高德天气接口只认城市编码 adcode，不认坐标）
function amapRegeoAdcode($key, $lng, $lat) {
    $url = 'https://restapi.amap.com/v3/geocode/regeo?key=' . $key
        . '&location=' . rawurlencode(sprintf('%.6f,%.6f', $lng, $lat));
    $ctx = @stream_context_create(['http' => ['timeout' => 5]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!$data || ($data['status'] ?? '') !== '1') return null;
    $adcode = $data['regeocode']['addressComponent']['adcode'] ?? '';
    return $adcode !== '' ? $adcode : null;
}

function fetchAmapWeather($key, $mode, $lat, $lng, $city) {
    // 注意：本文件在 php -S + router.php 下于函数作用域内被执行，
    // 顶层变量不是全局变量，因此一律通过参数传递，不使用 global

    // 高德天气 API 需要城市编码 adcode
    $cityParam = '';
    if ($mode === 'geo' && $lat !== null && $lng !== null) {
        // 经纬度先逆地理编码转 adcode
        $adcode = amapRegeoAdcode($key, $lng, $lat);
        if ($adcode) {
            $cityParam = '&city=' . $adcode;
        }
    } elseif ($mode === 'city' && $city !== null) {
        // 手动模式：使用城市名称
        $cityParam = '&city=' . urlencode($city);
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

    // 高德天气不提供能见度与体感温度，坐标已知时用 Open-Meteo 补齐（失败不影响主数据）
    $vis = '--';
    $feelsLike = $live['temperature'] ?? '--';
    if ($mode === 'geo' && $lat !== null && $lng !== null) {
        $supp = fetchOpenMeteoWeather($lat, $lng);
        if ($supp && isset($supp['data'])) {
            if (isset($supp['data']['vis']) && $supp['data']['vis'] !== '--') {
                $vis = $supp['data']['vis'];
            }
            if (isset($supp['data']['feelsLike']) && $supp['data']['feelsLike'] !== '--') {
                $feelsLike = $supp['data']['feelsLike'];
            }
        }
    }

    return [
        'code' => 200,
        'data' => [
            'temp'      => $live['temperature'] ?? '--',
            'feelsLike' => $feelsLike,
            'desc'      => $weather ?: '未知',
            'icon'      => $icon,
            'humidity'  => $live['humidity'] ?? '--',
            'windDir'   => $live['winddirection'] ?? '--',
            'windScale' => $live['windpower'] ?? '--',
            'vis'       => $vis,
            'city'      => $live['city'] ?? ($live['province'] ?? '未知'),
            'obsTime'   => $live['reporttime'] ?? date('Y-m-d\TH:i+08:00'),
            'source'    => 'amap',
        ],
    ];
}

// 主逻辑
$coupleData = null;
if ($mode === 'couple') {
    // 情侣模式：优先使用前端传来的坐标（与卡片头像严格对应），否则按 slot 从 map-all.json 解析
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
            if (file_exists($mapFile)) {
                $mapData = json_decode(file_get_contents($mapFile), true);
                $lovers = $mapData['lovers'] ?? [];
                $idx = intval($slot) - 1;
                if (isset($lovers[$idx]) && !empty($lovers[$idx]['coords'])) {
                    $lat = $lovers[$idx]['coords'][1];
                    $lng = $lovers[$idx]['coords'][0];
                    $mode = 'geo';
                    $coupleData = [
                        'lat' => $lat, 'lng' => $lng,
                        'name' => $lovers[$idx]['name'] ?? '',
                        'label' => $lovers[$idx]['label'] ?? '',
                    ];
                }
            }
        } catch (Throwable $e) {}
    }
}

if ($weatherType === 'amap' && $weatherKey) {
    $result = fetchAmapWeather($weatherKey, $mode, $lat, $lng, $city);
    if ($result) {
        // 情侣模式下用对方昵称
        if ($coupleData && !empty($coupleData['name'])) {
            $result['data']['city'] = $coupleData['name'] . ' · ' . ($result['data']['city'] ?? '');
        }
        if ($lat !== null && $lng !== null) cacheWeather($lat, $lng, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 免 Key 实时天气（Open-Meteo）
if ($lat !== null && $lng !== null) {
    $cached = loadCachedWeather($lat, $lng, false);
    if ($cached) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = fetchOpenMeteoWeather($lat, $lng);
    if ($result) {
        // 情侣模式下城市显示「昵称 · 地标」，没有地标时回退昵称
        if ($coupleData) {
            $cityText = !empty($coupleData['label']) ? $coupleData['label']
                : (!empty($coupleData['name']) ? $coupleData['name'] : '');
            if ($cityText !== '') {
                $result['data']['city'] = $cityText;
            }
        }
        cacheWeather($lat, $lng, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 上游异常时回退旧缓存（宁旧勿假）
    $stale = loadCachedWeather($lat, $lng, true);
    if ($stale) {
        echo json_encode($stale, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

outputFallback();
