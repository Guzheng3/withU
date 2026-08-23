<?php
/**
 * 信息/地理服务接口
 * action=geo: 返回访问者城市和坐标
 * action=random: 返回随机语录
 */
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'geo') {
    // 从 IP 获取地理位置
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // 处理代理 IP
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    if ($ip === '::1' || $ip === '127.0.0.1') {
        $ip = '8.8.8.8'; // 本地测试用公网 IP
    }

    $city = '未知';
    $lat = null;
    $lng = null;

    // 尝试 ip-api.com
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $geo = @file_get_contents("http://ip-api.com/json/{$ip}?lang=zh-CN&fields=city,regionName,country,lat,lon", false, $ctx);
    if ($geo) {
        $geoData = json_decode($geo, true);
        if ($geoData && ($geoData['city'] ?? '') !== '') {
            $city = $geoData['city'];
            $region = $geoData['regionName'] ?? '';
            $country = $geoData['country'] ?? '';
            if ($region && $region !== $city) {
                $city = $region . ' · ' . $city;
            }
            if ($country && $country !== '中国') {
                $city = $country . ' · ' . $city;
            }
            $lat = $geoData['lat'] ?? null;
            $lng = $geoData['lon'] ?? null;
        }
    }

    echo json_encode(['city' => $city, 'lat' => $lat, 'lng' => $lng]);
    exit;
}

// 随机语录
$quotes = [
    '所爱隔山海，山海皆可平。',
    '你是我所有的少女情怀和心之所向。',
    '世界万物，你是归途。',
    '初见是惊鸿一瞥，重逢是始料未及。',
    '浮世万千，吾爱有三，日、月与卿。',
    '山水一程，三生有幸。',
    '你的名字，是我见过最短的情诗。',
    '人间烟火气，最抚凡人心。',
    '愿有岁月可回首，且以深情共白头。',
    '满目山河空念远，不如怜取眼前人。',
    '你是落日弥漫的橘，天边透亮的星。',
    '晚风踩着云朵，月亮贩售快乐。',
    '入目无别人，四下皆是你。',
    '这世间青山灼灼，星光杳杳，而你眉眼如初。',
    '好的爱情是你通过一个人看到整个世界。',
    '山河远阔，人间烟火，无一是你，无一不是你。',
];

$idx = array_rand($quotes);
echo json_encode(['Status' => true, 'randomContent' => $quotes[$idx]]);