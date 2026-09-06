<?php
/**
 * withU 全局配置生成
 * 从数据库读取情侣信息、设置等，生成 $withuConfigJson
 * 数据库不可用时使用默认静态配置兜底
 */

// 默认静态配置（数据库不可用时兜底）
$withuConfigJson = '{"title":"withU","boy":"Ki.","girl":"Really","startTime":"2023-07-19 00:00:00","version":"2.2.5","pcCarouselHeight":"80vh","mobileCarouselHeight":"50vh","pcPhotoCoverHeight":"80vh","mobilePhotoCoverHeight":"60vh","pcImgMaxHeight":"450px","mobileImgMaxHeight":"260px","maleName":"Ki.","maleAvatar":"/Lovefolder/20260411043037_69d95ded97293201118237.webp","femaleName":"Really","femaleAvatar":"/Lovefolder/20260411043046_69d95df639c33274072975.webp","siteBase":"","assetBase":"","imageErrorFallback":"/Style/img/file-placeholder.svg","owoBase":"/OwO","soloMode":false,"weatherEnabled":true,"weatherToken":"d4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721","weatherType":"qweather","amapKey":"","weatherLocMode":"auto","weatherLocCity":"","weatherLocLat":null,"weatherLocLng":null,"weatherLocName":"","soloOwnerGeo":{"lat":21.915454,"lng":110.856708},"boyCoords":[116.39,39.90],"girlCoords":[116.39,39.90],"bannedChars":"操屌","endpoints":{"mapApi":"/assets/map-api.php","weatherNow":"/services/weather.php","interaction":"/services/interaction.php","accessBeacon":"/services/access-beacon.php","messageList":"/services/message-list.php","messageSubmit":"/services/message.php","infoService":"/services/info-service.php","weatherApi":"/services/weather.php"}}';

// 站点标题（供页面 <title> 等使用；数据库可用时取 settings.site_title，否则用默认值）
$withuSiteTitle = 'withU';

// 首页「Together Since」起始日展示文案（数据库不可用时兜底）
$withuStartDateDisplay = '2023-07-19 00:00';

try {
    $rootPath = dirname(__DIR__, 2) . '/backend/app';
    if (!is_file($rootPath . '/config/database.php') || !is_file($rootPath . '/.installed')) {
        throw new RuntimeException('not installed');
    }
    require_once $rootPath . '/config/config.php';
    require_once $rootPath . '/core/Database.php';
    $db = Database::getInstance();

    $users = $db->fetchAll("SELECT * FROM users WHERE status='active' ORDER BY FIELD(role,'user1','user2')");
    $user1 = null; $user2 = null;
    foreach ($users as $u) {
        $role = $u['role'] ?? '';
        if ($role === 'user1' && !$user1) $user1 = $u;
        if ($role === 'user2' && !$user2) $user2 = $u;
    }

    $boyName = $user1['nickname'] ?? 'Ki.';
    $girlName = $user2['nickname'] ?? 'Really';
    $boyAvatar = $user1['avatar'] ?? '/assets/images/default-avatar.svg';
    $girlAvatar = $user2['avatar'] ?? '/assets/images/default-avatar.svg';

    // 性别与最近上线时间（头像区徽标用），老库缺列时保持 null
    $boyGender = in_array($user1['gender'] ?? '', ['male', 'female'], true) ? $user1['gender'] : null;
    $girlGender = in_array($user2['gender'] ?? '', ['male', 'female'], true) ? $user2['gender'] : null;
    $boyLastLogin = $user1['last_login_at'] ?? null;
    $girlLastLogin = $user2['last_login_at'] ?? null;

    // 读取情侣坐标（从 map-all.json）
    // 优先按头像文件名 / 昵称匹配 lovers，避免 lovers 数组顺序与 boy/girl 顺序不一致时位置挂反
    $boyCoords = [116.39, 39.90];
    $girlCoords = [116.39, 39.90];
    $boyCoordsMatched = false;
    $girlCoordsMatched = false;
    $boyAvatarFile = strtolower(pathinfo(parse_url((string)$boyAvatar, PHP_URL_PATH) ?? '', PATHINFO_BASENAME));
    $girlAvatarFile = strtolower(pathinfo(parse_url((string)$girlAvatar, PHP_URL_PATH) ?? '', PATHINFO_BASENAME));
    $mapFile = __DIR__ . '/../services/map-all.json';
    if (file_exists($mapFile)) {
        $mapData = json_decode(file_get_contents($mapFile), true);
        $lovers = $mapData['lovers'] ?? [];
        foreach ($lovers as $lover) {
            $loverCoords = $lover['coords'] ?? null;
            if (!is_array($loverCoords) || count($loverCoords) < 2) {
                continue;
            }
            $loverName = trim((string)($lover['name'] ?? ''));
            $loverAvatarFile = strtolower(pathinfo(parse_url((string)($lover['avatar'] ?? ''), PHP_URL_PATH) ?? '', PATHINFO_BASENAME));
            $byAvatar = $loverAvatarFile !== '' && $loverAvatarFile === $boyAvatarFile;
            $byName = $loverName !== '' && $loverName === trim((string)$boyName);
            if (!$boyCoordsMatched && ($byAvatar || $byName)) {
                $boyCoords = array_slice($loverCoords, 0, 2);
                $boyCoordsMatched = true;
                continue;
            }
            $byAvatar = $loverAvatarFile !== '' && $loverAvatarFile === $girlAvatarFile;
            $byName = $loverName !== '' && $loverName === trim((string)$girlName);
            if (!$girlCoordsMatched && ($byAvatar || $byName)) {
                $girlCoords = array_slice($loverCoords, 0, 2);
                $girlCoordsMatched = true;
            }
        }
        // 头像与昵称都未匹配上时按旧的顺序兜底（lovers[0] -> boy, lovers[1] -> girl）
        if (!$boyCoordsMatched && isset($lovers[0]['coords'])) {
            $boyCoords = $lovers[0]['coords'];
        }
        if (!$girlCoordsMatched && isset($lovers[1]['coords'])) {
            $girlCoords = $lovers[1]['coords'];
        }
    }

    $loveDateRow = $db->fetch("SELECT value FROM settings WHERE `key`='love_date'");
    // 后台 love_date 现按 "Y-m-d H:i:s" 存储，直接透传；
    // 兼容历史仅日期 "Y-m-d" 的旧数据，补齐到 00:00:00
    $loveDateValue = ($loveDateRow && !empty($loveDateRow['value'])) ? trim($loveDateRow['value']) : '';
    if ($loveDateValue === '') {
        $startTime = '2023-07-19 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $loveDateValue)) {
        $startTime = $loveDateValue . ' 00:00:00';
    } else {
        $startTime = $loveDateValue;
    }
    // 起始日文案随 startTime 同步（精确到分钟，与卡片展示格式一致）
    $withuStartDateDisplay = substr($startTime, 0, 16);

    $siteTitleRow = $db->fetch("SELECT value FROM settings WHERE `key`='site_title'");
    $siteTitle = ($siteTitleRow && !empty($siteTitleRow['value'])) ? $siteTitleRow['value'] : 'withU';
    $withuSiteTitle = $siteTitle;

    $weatherKeyRow = $db->fetch("SELECT value FROM settings WHERE `key`='amap_weather_key'");
    $weatherKey = ($weatherKeyRow && !empty($weatherKeyRow['value'])) ? $weatherKeyRow['value'] : '';

    $locModeRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_mode'");
    $locMode = ($locModeRow && !empty($locModeRow['value'])) ? $locModeRow['value'] : 'auto';

    $locCityRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_city'");
    $locCity = ($locCityRow && !empty($locCityRow['value'])) ? $locCityRow['value'] : '';

    $locLatRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_lat'");
    $locLat = ($locLatRow && $locLatRow['value'] !== '') ? floatval($locLatRow['value']) : null;

    $locLngRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_lng'");
    $locLng = ($locLngRow && $locLngRow['value'] !== '') ? floatval($locLngRow['value']) : null;

    $locNameRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_name'");
    $locName = ($locNameRow && !empty($locNameRow['value'])) ? $locNameRow['value'] : '';

    $withuConfig = [
        'title' => $siteTitle,
        'boy' => $boyName, 'girl' => $girlName,
        'startTime' => $startTime,
        'version' => '2.2.5',
        'pcCarouselHeight' => '80vh', 'mobileCarouselHeight' => '50vh',
        'pcPhotoCoverHeight' => '80vh', 'mobilePhotoCoverHeight' => '60vh',
        'pcImgMaxHeight' => '450px', 'mobileImgMaxHeight' => '260px',
        'maleName' => $boyName, 'maleAvatar' => $boyAvatar,
        'femaleName' => $girlName, 'femaleAvatar' => $girlAvatar,
        'siteBase' => '', 'assetBase' => '',
        'imageErrorFallback' => '/Style/img/file-placeholder.svg',
        'owoBase' => '/OwO', 'soloMode' => false,
        'loggedIn' => (bool)($loggedIn ?? false),
        'weatherEnabled' => true,
        'weatherToken' => $weatherKey ?: 'd4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721',
        'weatherType' => $weatherKey ? 'amap' : 'qweather',
        'amapKey' => $weatherKey ?: '',
        'weatherLocMode' => $locMode,
        'weatherLocCity' => $locCity,
        'weatherLocLat' => $locLat,
        'weatherLocLng' => $locLng,
        'weatherLocName' => $locName,
        'soloOwnerGeo' => ['lat' => 21.915454, 'lng' => 110.856708],
        'boyCoords' => $boyCoords,
        'girlCoords' => $girlCoords,
        'bannedChars' => '操屌',
        'endpoints' => [
            'mapApi' => '/assets/map-api.php', 'weatherNow' => '/services/weather.php',
            'locationBeacon' => '/services/location-beacon.php',
            'interaction' => '/services/interaction.php', 'accessBeacon' => '/services/access-beacon.php',
            'messageList' => '/services/message-list.php', 'messageSubmit' => '/services/message.php',
            'infoService' => '/services/info-service.php', 'weatherApi' => '/services/weather.php',
        ],
    ];
    $withuConfigJson = json_encode($withuConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // 使用默认静态配置
}