<?php
function withu_http_json(string $url, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
    $raw = curl_exec($ch); curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

function withu_weather($db, float $lat, float $lng): array
{
    $key = number_format($lat, 2, '.', '') . ',' . number_format($lng, 2, '.', '');
    $cached = $db->fetch('SELECT payload FROM weather_cache WHERE cache_key = :cache_key AND expires_at > :now LIMIT 1', ['cache_key' => $key, 'now' => withu_now()]);
    if ($cached) return ['success' => true, 'cached' => true, 'data' => json_decode($cached['payload'], true)];
    $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . rawurlencode((string)$lat) . '&longitude=' . rawurlencode((string)$lng) . '&current=temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=auto';
    $data = withu_http_json($url, ['User-Agent: withU/1.0']);
    if (!$data) return ['success' => false, 'message' => '天气服务暂时不可用'];
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    $exists = $db->fetch('SELECT id FROM weather_cache WHERE cache_key = :cache_key', ['cache_key' => $key]);
    $fields = ['cache_key' => $key, 'latitude' => $lat, 'longitude' => $lng, 'payload' => $payload, 'expires_at' => date('Y-m-d H:i:s', time() + 1800), 'created_at' => withu_now()];
    if ($exists) $db->update('weather_cache', $fields, 'id = :id', ['id' => $exists['id']]); else $db->insert('weather_cache', $fields);
    return ['success' => true, 'cached' => false, 'data' => $data];
}

function withu_ai_plan(string $destination, string $prompt, ?string &$source = null): array
{
    $endpoint = trim((string)get_setting('ai_api_endpoint', '')); $key = trim((string)get_setting('ai_api_key', ''));
    if ($endpoint !== '' && $key !== '') {
        $payload = json_encode(['model' => get_setting('ai_model', 'gpt-4o-mini'), 'messages' => [
            ['role' => 'system', 'content' => '你是情侣旅行规划助手，只返回 JSON，字段为 summary,itinerary,weather_alerts,tickets,packing。itinerary 是每天的数组。'],
            ['role' => 'user', 'content' => '目的地：' . $destination . '；需求：' . $prompt],
        ], 'temperature' => .3], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($endpoint); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 50, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key]]);
        $raw = curl_exec($ch); curl_close($ch); $response = is_string($raw) ? json_decode($raw, true) : null; $content = trim((string)($response['choices'][0]['message']['content'] ?? '')); $json = json_decode($content, true);
        if (is_array($json)) { $source = 'ai'; return $json; }
    }
    $source = 'local';
    return ['summary' => '以天气和交通实际情况为准的基础计划', 'itinerary' => [['day' => 1, 'items' => ['抵达 ' . $destination, '根据天气安排室内或户外活动', '记录一个共同地点']]], 'weather_alerts' => ['出发前重新检查天气'], 'tickets' => ['需要门票的景点请提前确认'], 'packing' => ['身份证件', '充电设备', '雨具']];
}
