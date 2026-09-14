<?php
/**
 * 信息/地理服务接口
 * action=geo: 返回访问者城市和坐标
 * action=random: 返回随机语录
 */
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'qq') {
    // QQ 号 → 昵称/头像：多源回退 + 文件缓存。
    // 说明：腾讯官方匿名昵称 CGI 已要求登录，主流第三方源可用性随时间波动，
    // 因此按优先级逐源尝试并缓存结果；全部失败返回 Status:false，前端走「朋友(x)」兜底。
    withu_info_service_qq();
    exit;
}

/**
 * QQ 信息查询：返回 {Status:true, data:{nickname, avatar, qq_hash}} 或 {Status:false}。
 * 缓存：命中 7 天、未命中 1 小时（避免反复请求已失效的上游）。
 */
function withu_info_service_qq(): void
{
    $qq = preg_replace('/\D/', '', (string)($_POST['qq'] ?? $_GET['qq'] ?? ''));
    if ($qq === '' || strlen($qq) < 5 || strlen($qq) > 12) {
        echo json_encode(['Status' => false]);
        return;
    }

    $cacheDir = sys_get_temp_dir() . '/withu-qq-nick';
    $cacheFile = $cacheDir . '/' . md5('nick' . $qq) . '.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $ttl = !empty($cached['n']) ? 604800 : 3600;
            if (time() - (int)$cached['t'] < $ttl) {
                if (!empty($cached['n'])) {
                    echo json_encode(['Status' => true, 'data' => [
                        'nickname' => $cached['n'],
                        'avatar'   => '/_qqavatar.php?qq=' . $qq . '&s=100',
                        'qq_hash'  => md5($qq),
                    ]], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['Status' => false]);
                }
                return;
            }
        }
    }

    $nickname = withu_info_service_fetch_nickname($qq);

    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    @file_put_contents($cacheFile, json_encode(['t' => time(), 'n' => $nickname]));

    if ($nickname !== '') {
        echo json_encode(['Status' => true, 'data' => [
            'nickname' => $nickname,
            'avatar'   => '/_qqavatar.php?qq=' . $qq . '&s=100',
            'qq_hash'  => md5($qq),
        ]], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['Status' => false]);
    }
}

/** 带超时的 GET */
function withu_info_service_http_get(string $url): string
{
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
    return (string)@file_get_contents($url, false, $ctx);
}

/** 按优先级尝试多个上游，返回昵称（拿不到返回空串） */
function withu_info_service_fetch_nickname(string $qq): string
{
    // 源 0：本站留言历史——同 QQ 曾留言时用过的昵称，零外部依赖、永远可用
    try {
        require_once __DIR__ . '/message-common.php';
        $db = withu_message_db();
        if ($db) {
            $row = $db->fetch(
                "SELECT guest_nickname FROM messages
                 WHERE guest_qq = :qq AND guest_nickname <> '' AND status = 'published'
                 ORDER BY id DESC LIMIT 1",
                ['qq' => $qq]
            );
            if ($row && trim((string)$row['guest_nickname']) !== '') {
                return trim((string)$row['guest_nickname']);
            }
        }
    } catch (Throwable $e) {
        // 本站历史查不到则继续走外部源
    }

    // 源 1：腾讯官方 CGI（GBK 编码；当前需登录，若腾讯恢复匿名访问则自动生效）
    $body = withu_info_service_http_get('https://r.qzone.qq.com/fcg-bin/cgi_get_portrait.fcg?uins=' . $qq);
    if ($body !== '') {
        $nick = withu_info_service_parse_tencent($body);
        if ($nick !== '') return $nick;
    }

    // 源 2：UOMG
    $body = withu_info_service_http_get('https://api.uomg.com/api/qq.info?qq=' . $qq);
    if ($body !== '') {
        $nick = withu_info_service_parse_uomg($body);
        if ($nick !== '') return $nick;
    }

    // 源 3：QJQQ
    $body = withu_info_service_http_get('https://api.qjqq.cn/api/qqinfo?qq=' . $qq);
    if ($body !== '') {
        $nick = withu_info_service_parse_qjqq($body);
        if ($nick !== '') return $nick;
    }

    // 源 4：VVHAN
    $body = withu_info_service_http_get('https://api.vvhan.com/api/qqinfo?qq=' . $qq);
    if ($body !== '') {
        $nick = withu_info_service_parse_generic($body, [200, 1]);
        if ($nick !== '') return $nick;
    }

    // 源 5：OIOWEB
    $body = withu_info_service_http_get('https://api.oioweb.cn/api/qq/Info?qq=' . $qq);
    if ($body !== '') {
        $nick = withu_info_service_parse_generic($body, [200, 1]);
        if ($nick !== '') return $nick;
    }

    return '';
}

/** 宽松解析：{code:…, name/nick/nickname:…} 或 {code:…, data:{name/…}}，code 命中给定值集合即认为成功 */
function withu_info_service_parse_generic(string $body, array $okCodes): string
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) return '';
    if (!in_array((int)($decoded['code'] ?? -1), $okCodes, true)) return '';
    $candidates = [$decoded['data'] ?? null, $decoded];
    foreach ($candidates as $node) {
        if (!is_array($node)) continue;
        $nick = trim((string)($node['name'] ?? $node['nick'] ?? $node['nickname'] ?? ''));
        if ($nick !== '') return $nick;
    }
    return '';
}

/** 解析腾讯 CGI 响应（_Callback 包裹，GBK 编码，昵称在数组下标 5；error 结构视为失败） */
function withu_info_service_parse_tencent(string $body): string
{
    $utf8 = mb_check_encoding($body, 'UTF-8') ? $body : @mb_convert_encoding($body, 'UTF-8', 'GBK');
    $json = trim($utf8);
    $l = strpos($json, '(');
    $r = strrpos($json, ')');
    if ($l === false || $r === false || $r <= $l) return '';
    $decoded = json_decode(substr($json, $l + 1, $r - $l - 1), true);
    if (!is_array($decoded) || !empty($decoded['error'])) return '';
    $first = reset($decoded);
    if (is_array($first) && isset($first[5]) && is_string($first[5]) && trim($first[5]) !== '') {
        return trim($first[5]);
    }
    return '';
}

/** 解析 UOMG 响应（{code:1, name:昵称}） */
function withu_info_service_parse_uomg(string $body): string
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || (int)($decoded['code'] ?? 0) !== 1) return '';
    $nick = trim((string)($decoded['name'] ?? $decoded['nick'] ?? $decoded['nickname'] ?? ''));
    return $nick;
}

/** 解析 QJQQ 响应（{code:1, nick:昵称}） */
function withu_info_service_parse_qjqq(string $body): string
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || (int)($decoded['code'] ?? 0) !== 1) return '';
    $nick = trim((string)($decoded['nick'] ?? $decoded['name'] ?? $decoded['nickname'] ?? ''));
    return $nick;
}

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