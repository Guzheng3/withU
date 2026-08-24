<?php
/**
 * withU 全局配置生成
 * 从数据库读取情侣信息、设置等，生成 $withuConfigJson
 * 数据库不可用时使用默认静态配置兜底
 */

// 默认静态配置（数据库不可用时兜底）
$withuConfigJson = '{"title":"withU","boy":"Ki.","girl":"Really","startTime":"2023-07-19 00:00:00","version":"2.2.5","pcCarouselHeight":"80vh","mobileCarouselHeight":"50vh","pcPhotoCoverHeight":"80vh","mobilePhotoCoverHeight":"60vh","pcImgMaxHeight":"450px","mobileImgMaxHeight":"260px","maleName":"Ki.","maleAvatar":"/Lovefolder/20260411043037_69d95ded97293201118237.webp","femaleName":"Really","femaleAvatar":"/Lovefolder/20260411043046_69d95df639c33274072975.webp","siteBase":"","assetBase":"","imageErrorFallback":"/Style/img/file-placeholder.svg","owoBase":"/OwO","soloMode":false,"weatherEnabled":true,"weatherToken":"d4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721","weatherType":"qweather","amapKey":"","weatherLocMode":"auto","weatherLocCity":"","soloOwnerGeo":{"lat":21.915454,"lng":110.856708},"boyCoords":[116.39,39.90],"girlCoords":[116.39,39.90],"bannedChars":"操屌","endpoints":{"mapApi":"/assets/map-api.php","weatherNow":"/services/weather.php","interaction":"/services/interaction.php","accessBeacon":"/services/access-beacon.php","messageList":"/services/message-list.php","messageSubmit":"/services/message.php","infoService":"/services/info-service.php","weatherApi":"/services/weather.php"}}';

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

    $boyName = $user1['nickname'] ?? '他';
    $girlName = $user2['nickname'] ?? '她';
    $boyAvatar = $user1['avatar'] ?? '/assets/images/default-avatar.svg';
    $girlAvatar = $user2['avatar'] ?? '/assets/images/default-avatar.svg';

    // 读取情侣坐标（从 map-all.json）
    $boyCoords = [116.39, 39.90];
    $girlCoords = [116.39, 39.90];
    $mapFile = __DIR__ . '/../services/map-all.json';
    if (file_exists($mapFile)) {
        $mapData = json_decode(file_get_contents($mapFile), true);
        $lovers = $mapData['lovers'] ?? [];
        if (isset($lovers[0]['coords'])) {
            $boyCoords = $lovers[0]['coords'];
        }
        if (isset($lovers[1]['coords'])) {
            $girlCoords = $lovers[1]['coords'];
        }
    }

    $loveDateRow = $db->fetch("SELECT value FROM settings WHERE `key`='love_date'");
    $startTime = ($loveDateRow && !empty($loveDateRow['value'])) ? $loveDateRow['value'] . ' 00:00:00' : '2023-07-19 00:00:00';

    $siteTitleRow = $db->fetch("SELECT value FROM settings WHERE `key`='site_title'");
    $siteTitle = ($siteTitleRow && !empty($siteTitleRow['value'])) ? $siteTitleRow['value'] : 'withU';

    $weatherKeyRow = $db->fetch("SELECT value FROM settings WHERE `key`='amap_weather_key'");
    $weatherKey = ($weatherKeyRow && !empty($weatherKeyRow['value'])) ? $weatherKeyRow['value'] : '';

    $locModeRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_mode'");
    $locMode = ($locModeRow && !empty($locModeRow['value'])) ? $locModeRow['value'] : 'auto';

    $locCityRow = $db->fetch("SELECT value FROM settings WHERE `key`='weather_loc_city'");
    $locCity = ($locCityRow && !empty($locCityRow['value'])) ? $locCityRow['value'] : '';

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
        'weatherEnabled' => true,
        'weatherToken' => $weatherKey ?: 'd4210665334edba618aecc1829a5e734701e2b824c5aebd4ff8859d7a2536721',
        'weatherType' => $weatherKey ? 'amap' : 'qweather',
        'amapKey' => $weatherKey ?: '',
        'weatherLocMode' => $locMode,
        'weatherLocCity' => $locCity,
        'soloOwnerGeo' => ['lat' => 21.915454, 'lng' => 110.856708],
        'boyCoords' => $boyCoords,
        'girlCoords' => $girlCoords,
        'bannedChars' => '操屌',
        'endpoints' => [
            'mapApi' => '/assets/map-api.php', 'weatherNow' => '/services/weather.php',
            'interaction' => '/services/interaction.php', 'accessBeacon' => '/services/access-beacon.php',
            'messageList' => '/services/message-list.php', 'messageSubmit' => '/services/message.php',
            'infoService' => '/services/info-service.php', 'weatherApi' => '/services/weather.php',
        ],
    ];
    $withuConfigJson = json_encode($withuConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // 使用默认静态配置
}