<?php
require_once __DIR__ . '/Weather.php';

function withu_http_json(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    $raw = curl_exec($ch); 
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

function withu_weather($db, float $lat, float $lng): array
{
    // 数据源已统一切到和风天气，但对外仍保持 Open-Meteo 字段形状，
    // 避免破坏旅行 / 地图 / Android 端现有解析。
    $now = withu_weather_now($lat, $lng);
    if (empty($now['success']) || empty($now['data'])) {
        return [
            'success' => false,
            'message' => (string)($now['message'] ?? '天气服务暂时不可用'),
            'error' => (string)($now['error'] ?? 'weather_upstream_error'),
        ];
    }

    $nowData = $now['data'];
    $obsAt = withu_weather_parse_time($nowData['obsTime'] ?? null);
    $current = [
        'time' => $obsAt !== null ? $obsAt->format('Y-m-d\TH:i') : date('Y-m-d\TH:i'),
        'temperature_2m' => is_numeric($nowData['temp'] ?? null) ? (float)$nowData['temp'] : null,
        'relative_humidity_2m' => is_numeric($nowData['humidity'] ?? null) ? (int)$nowData['humidity'] : null,
        'apparent_temperature' => is_numeric($nowData['feelsLike'] ?? null) ? (float)$nowData['feelsLike'] : null,
        'precipitation' => is_numeric($nowData['precip'] ?? null) ? (float)$nowData['precip'] : 0.0,
        'weather_code' => (int)($nowData['wmo_code'] ?? 0),
        'wind_speed_10m' => is_numeric($nowData['windSpeed'] ?? null) ? (float)$nowData['windSpeed'] : null,
    ];

    $dailyColumns = [
        'time' => [],
        'weather_code' => [],
        'temperature_2m_max' => [],
        'temperature_2m_min' => [],
        'precipitation_probability_max' => [],
    ];
    $daily = withu_weather_daily($lat, $lng, ['days' => 7]);
    $dailyCached = !empty($daily['cached']);
    if (!empty($daily['success']) && !empty($daily['data']['daily'])) {
        foreach ($daily['data']['daily'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dailyColumns['time'][] = (string)($item['fxDate'] ?? '');
            $dailyColumns['weather_code'][] = (int)($item['weather_code'] ?? 0);
            $dailyColumns['temperature_2m_max'][] = $item['tempMax'] ?? null;
            $dailyColumns['temperature_2m_min'][] = $item['tempMin'] ?? null;
            // 和风逐日预报没有降水概率字段，保持 null 不伪造数据。
            $dailyColumns['precipitation_probability_max'][] = null;
        }
    }

    return [
        'success' => true,
        'cached' => !empty($now['cached']) && $dailyCached,
        'data' => [
            'latitude' => $lat,
            'longitude' => $lng,
            'timezone' => (string)($now['location']['timezone'] ?? 'Asia/Shanghai'),
            'current' => $current,
            'daily' => $dailyColumns,
        ],
    ];
}
