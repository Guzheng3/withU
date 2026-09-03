<?php
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
