<?php
/** 媒体识别：本地文件结构解析，AI 只负责返回豆瓣 ID，详情由豆瓣接口补全。 */

require_once __DIR__ . '/MediaImages.php';

function withu_media_title_key(string $title): string
{
    $title = mb_strtolower(trim($title));
    $title = preg_replace('/\.[a-z0-9]{2,5}$/iu', '', $title);
    $title = preg_replace('/\{[^}]*\}/u', '', $title);
    $title = preg_replace('/[\[（(](?:19|20)\d{2}[\]）)]/u', '', $title);
    $title = preg_replace('/\b(?:s\d{1,2}e\d{1,4}|\d{3,4}p|4320p|2160p|1080p|720p|web[- .]?(?:dl|rip)|bluray|blu[- .]?ray|remux|hevc|h\.?265|x265|h\.?264|x264|avc|hdr10\+?|hdr|sdr|dolby.?vision|dovi|dv|aac|dts(?:[- .]?hd)?(?:[- .]?ma)?|truehd|atmos|eac3|ddp(?:\d(?:\.\d)?)?|flac|ac3|10bit|60fps|mkv|mp4)\b/iu', '', $title);
    $title = preg_replace('/第\s*\d{1,4}\s*集/u', '', $title);
    return trim((string)preg_replace('/[\s\-_:：!！?？,，.。·《》\[\]\(\)（）]+/u', '', $title));
}

function withu_media_identify(array $media): array
{
    $fileName = trim((string)($media['file_name'] ?? ''));
    $sourcePath = trim(str_replace('\\', '/', (string)($media['source_path'] ?? $media['source_key'] ?? '')));
    $sourcePath = '/' . trim(preg_replace('#/+#', '/', $sourcePath), '/');
    $segments = array_values(array_filter(explode('/', trim($sourcePath, '/')), static function ($value): bool { return trim((string)$value) !== ''; }));
    $parentName = count($segments) > 1 ? (string)$segments[count($segments) - 2] : '';
    $withoutExtension = preg_replace('/\.[^.]+$/', '', $fileName);
    $withoutExtension = is_string($withoutExtension) ? $withoutExtension : $fileName;

    $qualityPattern = '/\b(?:4320p|2160p|1080p|720p|576p|480p|8k|4k|uhd|web[- .]?(?:dl|rip)|bluray|blu[- .]?ray|remux|hevc|h[ .]?265|x265|h[ .]?264|x264|avc|hdr10\+?|hdr|sdr|dolby.?vision|dovi|dv|aac|dts(?:[- .]?hd)?(?:[- .]?ma)?|truehd|atmos|eac3|ddp(?:\d(?:\.\d)?)?|flac|ac3|10bit|60fps|proper|repack|extended|uncut|dubbed|multi|dual|complete|nf|amzn|dsnp|hmax|atvp|tmdbid?[- ]?\d+)\b/iu';
    $sizePattern = '/\b\d+(?:\.\d+)?\s*(?:tb|t|gb|g|mb|m)\b/iu';
    $audioLayoutPattern = '/\b(?:mono|stereo|2[ .]0|5[ .]1|7[ .]1|7[ .]2|dts[- .]?hd|dts[- .]?x|ma)\b/iu';
    $genericFolder = '/^(?:dav|电影|电视剧|剧集|动漫|动画|综艺|纪录片|短剧|影视库文件|影视资源|资源库|不限速最全库|持续更新|国产剧|欧美剧|日韩剧|数字开头|字母开头|其他|未分类|movies?|series|shows?|tv|library|media|season\s*\d+|s\d{1,2})$/iu';

    $season = null;
    $episode = null;
    if (preg_match('/\bS(\d{1,2})(?:[ ._-]*E(\d{1,4}))?\b/iu', $withoutExtension, $match)) {
        $season = (int)$match[1];
        if (!empty($match[2])) $episode = (int)$match[2];
    } elseif (preg_match('/第\s*(\d{1,2})\s*季(?:\s*第\s*(\d{1,4})\s*[集话期]?)?/u', $withoutExtension, $match)) {
        $season = (int)$match[1];
        if (!empty($match[2])) $episode = (int)$match[2];
    }
    if ($episode === null && preg_match('/第\s*(\d{1,4})\s*[集话期]/u', $withoutExtension, $match)) $episode = (int)$match[1];
    if ($episode === null && preg_match('/\b(?:ep?|episode)[ ._-]*(\d{1,4})\b/iu', $withoutExtension, $match)) $episode = (int)$match[1];
    $seasonFolder = null;
    foreach (array_reverse(array_slice($segments, 0, -1)) as $segment) {
        if (preg_match('/^(?:season|s)\s*\d{1,2}$/iu', $segment)) { $seasonFolder = (int)preg_replace('/\D+/', '', $segment); break; }
    }
    if ($season === null && $seasonFolder !== null) $season = $seasonFolder;
    $hasSeriesPath = (bool)preg_match('/^(?:电视剧|剧集|series|shows?|tv)$/iu', $parentName);
    if ($episode === null && ($hasSeriesPath || preg_match('/season|第\s*\d+\s*季/iu', $parentName))) {
        if (preg_match('/^(\d{1,3})(?:[._ -]|$)/', $withoutExtension, $match)) $episode = (int)$match[1];
    }
    $year = null;
    if (preg_match('/(?:19|20)\d{2}/', $withoutExtension . ' ' . implode(' ', array_slice($segments, -3, 2)), $match)) $year = (int)$match[0];

    $cleanTitlePart = static function (string $value) use ($qualityPattern, $sizePattern, $audioLayoutPattern): string {
        $normalized = class_exists('Normalizer') ? normalizer_normalize($value, Normalizer::FORM_KC) : $value;
        $text = str_replace(['.', '_'], ' ', (string)($normalized ?: $value));
        $text = preg_replace_callback('/[\[\(\{〔【「]([^\]\)\}〕】」]*)[\]\)\}〕】」]/u', static function ($match) use ($qualityPattern, $sizePattern): string {
            return preg_match($qualityPattern, (string)$match[1]) || preg_match($sizePattern, (string)$match[1]) ? ' ' : ' ' . $match[1] . ' ';
        }, $text);
        $text = preg_replace($sizePattern, ' ', $text);
        $text = preg_replace($qualityPattern, ' ', (string)$text);
        $text = preg_replace($audioLayoutPattern, ' ', (string)$text);
        $text = preg_replace('/(?:国语|粤语|国英双语|中字|简中|繁中|字幕|双语|中英|原盘|杜比视界|杜比全景声|内封字幕|特效字幕)/iu', ' ', (string)$text);
        $text = preg_replace('/(?:19|20)\d{2}/', ' ', (string)$text);
        $text = preg_replace('/\bS\d{1,2}(?:[ ._-]*E\d{1,4})?\b/iu', ' ', (string)$text);
        $text = preg_replace('/第\s*[0-9一二三四五六七八九十百]+\s*季/iu', ' ', (string)$text);
        $text = preg_replace('/(?:第\s*\d{1,2}\s*季)?\s*(?:第\s*\d{1,4}\s*[集话期]|\d{1,4}\s*[集话期]|(?:ep?|episode)[ ._-]*\d{1,4})/iu', ' ', (string)$text);
        $text = preg_replace('/[\[\](){}〔〕【】「」]+/u', ' ', (string)$text);
        $text = preg_replace('/(?:HD|SD|无水印|完整版|高清版)/iu', ' ', (string)$text);
        return trim((string)preg_replace('/\s{2,}/', ' ', (string)$text));
    };

    $fileTitle = $cleanTitlePart($withoutExtension);
    $bracketAliases = [];
    if (preg_match_all('/\[([^\]]+)\]|\(([^)]+)\)|〔([^〕]+)〕|【([^】]+)】|「([^」]+)」/u', $withoutExtension, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $value = $cleanTitlePart((string)($match[1] ?: $match[2] ?: $match[3] ?: $match[4] ?: $match[5]));
            if (mb_strlen($value) >= 2 && !preg_match('/^\d{1,4}$/', $value) && !preg_match('/^(?:new|sample|proper|hd|sd)$/iu', $value) && !preg_match($genericFolder, $value)) $bracketAliases[] = $value;
        }
    }
    $folderCandidates = [];
    foreach (array_reverse(array_slice($segments, 0, -1)) as $value) {
        if (preg_match($genericFolder, $value)) continue;
        $candidate = $cleanTitlePart($value);
        if (mb_strlen($candidate) >= 2 && !preg_match('/^\d{1,4}$/', $candidate)) $folderCandidates[] = $candidate;
    }
    $folderTitle = $folderCandidates[0] ?? '';
    $bracketTitle = '';
    foreach ($bracketAliases as $value) if (preg_match('/[\x{3400}-\x{9fff}]/u', $value)) { $bracketTitle = $value; break; }
    $title = trim($folderTitle ?: $bracketTitle ?: $fileTitle ?: $cleanTitlePart($parentName) ?: ($fileName ?: '未命名媒体'));
    $aliases = array_values(array_unique(array_filter(array_merge([$title, $fileTitle], $bracketAliases, $folderCandidates), static function ($value): bool { return trim((string)$value) !== ''; })));

    $haystack = $sourcePath . ' ' . $fileName . ' ' . $title;
    $typeId = 1;
    if (preg_match('/动漫|动画|anime|cartoon|donghua/iu', $haystack)) $typeId = 3;
    elseif (preg_match('/综艺|真人秀|脱口秀|variety|show/iu', $haystack)) $typeId = 4;
    elseif ($season !== null || $episode !== null || preg_match('/电视剧|剧集|series|shows?|tv|season/iu', $haystack)) $typeId = 2;

    $resolution = preg_match('/(?:4320p|8k|2160p|4k|uhd)/iu', $withoutExtension) ? '4K' : (preg_match('/1440p|2k/iu', $withoutExtension) ? '2K' : (preg_match('/1080p/iu', $withoutExtension) ? '1080P' : (preg_match('/720p/iu', $withoutExtension) ? '720P' : null)));
    $videoCodec = preg_match('/(?:hevc|h\.?265|x265|10bit)/iu', $withoutExtension) ? 'HEVC' : (preg_match('/(?:avc|h\.?264|x264)/iu', $withoutExtension) ? 'AVC' : null);
    $audioCodec = null;
    if (preg_match('/truehd/iu', $withoutExtension)) $audioCodec = 'TrueHD';
    elseif (preg_match('/atmos/iu', $withoutExtension)) $audioCodec = 'Atmos';
    elseif (preg_match('/(?:ddp|eac3)/iu', $withoutExtension)) $audioCodec = 'DDP';
    elseif (preg_match('/dts/iu', $withoutExtension)) $audioCodec = 'DTS';
    elseif (preg_match('/aac/iu', $withoutExtension)) $audioCodec = 'AAC';
    $dynamicRange = preg_match('/dolby.?vision|\bdv\b/iu', $withoutExtension) ? 'DV' : (preg_match('/hdr/iu', $withoutExtension) ? 'HDR' : (preg_match('/sdr/iu', $withoutExtension) ? 'SDR' : null));
    $releaseSource = preg_match('/remux/iu', $withoutExtension) ? 'Remux' : (preg_match('/blu[- .]?ray/iu', $withoutExtension) ? 'Blu-ray' : (preg_match('/web[- .]?(?:dl|rip)/iu', $withoutExtension) ? 'WEB' : null));
    $languages = [];
    if (preg_match('/国语|普通话/iu', $withoutExtension)) $languages[] = '国语';
    if (preg_match('/粤语/iu', $withoutExtension)) $languages[] = '粤语';
    if (preg_match('/中字|简中|繁中|字幕/iu', $withoutExtension)) $languages[] = '中字';
    $metadata = ['recognized_title' => $title, 'recognized_aliases' => $aliases, 'recognized_year' => $year, 'dynamic_range' => $dynamicRange, 'release_source' => $releaseSource, 'languages' => $languages];
    return [
        'series_key' => mb_substr('name:' . sha1($typeId . '|' . withu_media_title_key($title)), 0, 255),
        'series_name' => mb_substr($title, 0, 255), 'season_number' => $season, 'episode_number' => $episode,
        'episode_title' => null, 'media_type_id' => $typeId, 'resolution' => $resolution,
        'video_codec' => $videoCodec, 'audio_codec' => $audioCodec,
        'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'recognition_status' => $title !== '' ? 'recognized' : 'pending', 'recognition_source' => 'local',
    ];
}

function withu_media_structure(array $media): array
{
    return withu_media_identify($media);
}

function withu_media_display_row(array $row): array
{
    $structure = withu_media_structure($row);
    foreach ($structure as $key => $value) {
        if (!isset($row[$key]) || $row[$key] === '' || $row[$key] === null) $row[$key] = $value;
    }
    $metadata = json_decode((string)($row['metadata_json'] ?? '{}'), true);
    if (is_array($metadata) && !empty($metadata['release_source'])) {
        $row['release_source'] = (string)$metadata['release_source'];
    }
    $row['id'] = (int)($row['id'] ?? 0);
    return $row;
}

function withu_ai_json(string $content): ?array
{
    $content = trim($content);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $decoded = json_decode(trim($content), true);
    return is_array($decoded) ? $decoded : null;
}

function withu_douban_get(string $url, int $timeout = 12): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (withU media scraper)',
        CURLOPT_ENCODING => '',
        CURLOPT_REFERER => 'https://movie.douban.com/',
        CURLOPT_HTTPHEADER => ['Accept: application/json,text/html;q=0.9,*/*;q=0.8', 'Accept-Language: zh-CN,zh;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    return $status >= 200 && $status < 300 && is_string($body) ? $body : '';
}

/**
 * The default catalogue source is Douban's Chinese-market listing. It needs
 * no third-party API token and provides domestic-TV / Chinese-language-movie
 * rankings plus Chinese synopsis, cast, cover and rating data.
 */
function withu_douban_mobile_json(string $path, array $query = [], int $timeout = 20): array
{
    $url = 'https://m.douban.com/rexxar/api/v2/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        // The bundled Windows/PHP runtime may not have a complete CA bundle.
        // Metadata URLs are public read-only endpoints; disabling verification
        // here keeps hot-list scraping usable in the packaged server scene.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WithU/1.0)',
        CURLOPT_REFERER => 'https://m.douban.com/movie/',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: zh-CN,zh;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $json = is_string($body) ? json_decode($body, true) : null;
    return $status >= 200 && $status < 300 && is_array($json) ? $json : [];
}

function withu_douban_mobile_metadata(string $subjectId): array
{
    if (!preg_match('/^\d{4,12}$/', trim($subjectId))) return [];
    $data = withu_douban_mobile_json('/subject/' . trim($subjectId), [], 30);
    if (!$data) return [];
    $cast = [];
    foreach ((array)($data['actors'] ?? []) as $actor) if (!empty($actor['name'])) $cast[] = trim((string)$actor['name']);
    $genres = [];
    foreach ((array)($data['genres'] ?? []) as $genre) if (trim((string)$genre) !== '') $genres[] = trim((string)$genre);
    $cover = trim((string)($data['cover_url'] ?? $data['pic']['large'] ?? $data['pic']['normal'] ?? ''));
    $rating = $data['rating']['value'] ?? null;
    $episodeCount = withu_array_first_scalar($data, ['episodes_count', 'episode_count', 'total_episodes', 'number_of_episodes']);
    $lastEpisodeNumber = withu_array_first_scalar($data, ['last_episode_number', 'latest_episode_number']);
    $metadata = [
        'douban_id' => trim($subjectId),
        'series_name' => trim((string)($data['title'] ?? '')),
        'rating' => is_numeric($rating) && (float)$rating > 0 ? number_format((float)$rating, 1, '.', '') : null,
        'douban_episode_count' => is_numeric($episodeCount) && (int)$episodeCount > 0 ? (int)$episodeCount : null,
        'douban_is_released' => array_key_exists('is_released', $data) ? (bool)$data['is_released'] : null,
        'douban_last_episode_number' => is_numeric($lastEpisodeNumber) && (int)$lastEpisodeNumber > 0 ? (int)$lastEpisodeNumber : null,
        'summary' => trim(preg_replace('/\s+/u', ' ', (string)($data['intro'] ?? ''))),
        'cover_url' => $cover,
        'tags' => $genres ? json_encode(array_values(array_unique($genres)), JSON_UNESCAPED_UNICODE) : null,
        'cast_names' => $cast ? json_encode(array_values(array_unique($cast)), JSON_UNESCAPED_UNICODE) : null,
    ];
    return array_filter($metadata, static function ($value): bool { return $value !== null && $value !== ''; });
}

function withu_douban_fetch_domestic_hot(): array
{
    $sources = [
        ['subject/recent_hot/tv', ['start' => 0, 'limit' => 50, 'type' => 'tv_domestic'], 'tv'],
        ['subject/recent_hot/movie', ['start' => 0, 'limit' => 50, 'category' => '热门', 'type' => '华语'], 'movie'],
    ];
    $items = [];
    foreach ($sources as [$path, $query, $mediaType]) {
        $data = withu_douban_mobile_json($path, $query, 25);
        foreach ((array)($data['items'] ?? []) as $item) {
            $id = trim((string)($item['id'] ?? ''));
            $title = trim((string)($item['title'] ?? ''));
            if ($id === '' || $title === '') continue;
            $rating = $item['rating']['value'] ?? null;
            $items[] = [
                'title' => $title,
                'douban_id' => $id,
                'media_type' => $mediaType,
                'cover_url' => (string)($item['pic']['large'] ?? $item['pic']['normal'] ?? ''),
                'rating' => is_numeric($rating) && (float)$rating > 0 ? number_format((float)$rating, 1, '.', '') : '',
                'summary' => '',
            ];
        }
    }
    return $items;
}

/** TMDb is the primary public metadata provider. The credential stays server-side. */
function withu_tmdb_token(): string
{
    return trim((string)get_setting('tmdb_read_access_token', ''));
}

function withu_tmdb_api_base(): string
{
    return rtrim(trim((string)get_setting('tmdb_api_base', 'https://api.themoviedb.org/3')), '/');
}

function withu_tmdb_request(string $path, array $query = [], int $timeout = 30): array
{
    $token = withu_tmdb_token();
    if ($token === '') return [];
    $url = withu_tmdb_api_base() . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'withU/1.0 (private media metadata)',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($status < 200 || $status >= 300 || !is_string($body)) return [];
    $json = json_decode($body, true);
    return is_array($json) ? $json : [];
}

function withu_tmdb_image_url(string $path, string $size = 'w780'): string
{
    $path = trim($path);
    return $path !== '' && strpos($path, '/') === 0 ? 'https://image.tmdb.org/t/p/' . $size . $path : '';
}

function withu_tmdb_metadata(string $tmdbId, string $mediaType = 'tv'): array
{
    if (!preg_match('/^\d{1,12}$/', trim($tmdbId))) return [];
    $mediaType = $mediaType === 'movie' ? 'movie' : 'tv';
    $data = withu_tmdb_request('/' . $mediaType . '/' . trim($tmdbId), [
        'language' => 'zh-CN',
        'append_to_response' => 'credits',
    ]);
    if (!$data) return [];
    $title = trim((string)($data[$mediaType === 'movie' ? 'title' : 'name'] ?? $data['original_title'] ?? $data['original_name'] ?? ''));
    $genres = [];
    foreach ((array)($data['genres'] ?? []) as $genre) if (!empty($genre['name'])) $genres[] = trim((string)$genre['name']);
    $cast = [];
    foreach (array_slice((array)($data['credits']['cast'] ?? []), 0, 20) as $person) if (!empty($person['name'])) $cast[] = trim((string)$person['name']);
    $metadata = [
        'tmdb_id' => trim($tmdbId),
        'series_name' => $title !== '' ? mb_substr($title, 0, 255) : null,
        'rating' => isset($data['vote_average']) && is_numeric($data['vote_average']) ? number_format((float)$data['vote_average'], 1, '.', '') : null,
        'summary' => trim((string)($data['overview'] ?? '')),
        'cover_url' => withu_tmdb_image_url((string)($data['poster_path'] ?? ''), 'w780'),
        'backdrop_url' => withu_tmdb_image_url((string)($data['backdrop_path'] ?? ''), 'w1280'),
        'tags' => $genres ? json_encode(array_values(array_unique($genres)), JSON_UNESCAPED_UNICODE) : null,
        'cast_names' => $cast ? json_encode(array_values(array_unique($cast)), JSON_UNESCAPED_UNICODE) : null,
    ];
    return array_filter($metadata, static function ($value): bool { return $value !== null && $value !== ''; });
}

function withu_media_match_key(string $value): string
{
    $value = mb_strtolower(trim($value));
    return trim((string)preg_replace('/[\s\p{P}\p{S}]+/u', '', $value));
}

function withu_media_title_similarity(array $aliases, string $candidate): float
{
    $actual = withu_media_match_key($candidate);
    if ($actual === '') return 0.0;
    $best = 0.0;
    foreach ($aliases as $alias) {
        $expected = withu_media_match_key((string)$alias);
        if ($expected === '') continue;
        if ($expected === $actual) $best = max($best, 1.0);
        elseif (strpos($expected, $actual) !== false || strpos($actual, $expected) !== false) {
            $ratio = min(mb_strlen($expected), mb_strlen($actual)) / max(1, max(mb_strlen($expected), mb_strlen($actual)));
            $best = max($best, 0.58 + $ratio * 0.2);
        } else {
            $expectedTokens = preg_split('/(?=[a-z0-9])|(?<=[a-z0-9])|(?=[\x{3400}-\x{9fff}])|(?<=[\x{3400}-\x{9fff}])/u', $expected, -1, PREG_SPLIT_NO_EMPTY);
            $actualTokens = preg_split('/(?=[a-z0-9])|(?<=[a-z0-9])|(?=[\x{3400}-\x{9fff}])|(?<=[\x{3400}-\x{9fff}])/u', $actual, -1, PREG_SPLIT_NO_EMPTY);
            $intersection = count(array_intersect(array_unique($expectedTokens ?: []), array_unique($actualTokens ?: [])));
            $total = count(array_unique(array_merge($expectedTokens ?: [], $actualTokens ?: [])));
            if ($total > 0) $best = max($best, ($intersection / $total) * 0.72);
        }
    }
    return min(1.0, $best);
}

function withu_tmdb_match_series(array $media, array $identified = []): array
{
    if (withu_tmdb_token() === '') return [];
    $typeId = (int)($media['media_type_id'] ?? $identified['media_type_id'] ?? 1);
    $mediaType = $typeId === 1 ? 'movie' : 'tv';
    $metadataJson = json_decode((string)($media['metadata_json'] ?? $identified['metadata_json'] ?? '{}'), true);
    $aliases = array_values(array_unique(array_filter(array_merge(
        [(string)($media['series_name'] ?? $identified['series_name'] ?? '')],
        is_array($metadataJson['recognized_aliases'] ?? null) ? $metadataJson['recognized_aliases'] : []
    ), static function ($value): bool { return mb_strlen(trim((string)$value)) >= 2; })));
    if (!$aliases) return [];
    $year = (int)($metadataJson['recognized_year'] ?? 0);
    $candidates = [];
    foreach (array_slice($aliases, 0, 6) as $query) {
        $data = withu_tmdb_request('/search/' . $mediaType, ['query' => $query, 'language' => 'zh-CN', 'include_adult' => 'false', 'page' => 1], 30);
        foreach ((array)($data['results'] ?? []) as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) continue;
            $title = trim((string)($item[$mediaType === 'movie' ? 'title' : 'name'] ?? $item['original_title'] ?? $item['original_name'] ?? ''));
            if ($title === '') continue;
            $release = (string)($item[$mediaType === 'movie' ? 'release_date' : 'first_air_date'] ?? '');
            $candidateYear = (int)substr($release, 0, 4);
            $similarity = withu_media_title_similarity($aliases, $title);
            $primarySimilarity = withu_media_title_similarity([(string)($media['series_name'] ?? $identified['series_name'] ?? '')], $title);
            $score = 0.03 + $similarity * 0.42 + $primarySimilarity * 0.26;
            if ($primarySimilarity >= 0.98) $score += 0.2;
            if ($year > 0 && $candidateYear > 0) {
                $difference = abs($year - $candidateYear);
                if ($difference === 0) $score += 0.25;
                elseif ($difference === 1) $score += 0.14;
                elseif ($difference === 2) $score += 0.05;
                else $score -= 0.2;
            } else $score += 0.04;
            $key = (string)$id;
            if (!isset($candidates[$key]) || $score > $candidates[$key]['score']) $candidates[$key] = ['id' => $id, 'title' => $title, 'year' => $candidateYear ?: null, 'score' => max(0.0, min(1.0, $score))];
        }
    }
    if (!$candidates) return [];
    usort($candidates, static function (array $a, array $b): int { return $b['score'] <=> $a['score']; });
    $best = $candidates[0];
    $second = $candidates[1] ?? null;
    if ($best['score'] < 0.62 || ($second && $best['score'] < 0.84 && $best['score'] - $second['score'] < 0.08)) return ['match_score' => $best['score'], 'match_confidence' => '低', 'match_candidates' => array_slice($candidates, 0, 5), 'match_needs_review' => true];
    $metadata = withu_tmdb_metadata((string)$best['id'], $mediaType);
    if (!$metadata) return [];
    $metadata['tmdb_id'] = (string)$best['id'];
    $metadata['match_score'] = round($best['score'], 2);
    $metadata['match_confidence'] = $best['score'] >= 0.82 && ($year > 0 || !empty($best['year'])) ? '高' : '中';
    $metadata['match_candidates'] = array_slice($candidates, 0, 5);
    $metadata['match_needs_review'] = $metadata['match_confidence'] !== '高';
    return $metadata;
}

function withu_tmdb_fetch_trending(array $types = ['tv', 'movie']): array
{
    $items = [];
    foreach ($types as $type) {
        $type = $type === 'movie' ? 'movie' : 'tv';
        $data = withu_tmdb_request('/trending/' . $type . '/week', ['language' => 'zh-CN'], 30);
        foreach ((array)($data['results'] ?? []) as $item) {
            $id = trim((string)($item['id'] ?? ''));
            $title = trim((string)($item[$type === 'movie' ? 'title' : 'name'] ?? $item['original_title'] ?? $item['original_name'] ?? ''));
            if ($id === '' || $title === '') continue;
            $items[] = [
                'title' => $title,
                'tmdb_id' => $id,
                'media_type' => $type,
                'cover_url' => withu_tmdb_image_url((string)($item['poster_path'] ?? ''), 'w780'),
                'backdrop_url' => withu_tmdb_image_url((string)($item['backdrop_path'] ?? ''), 'w1280'),
                'rating' => isset($item['vote_average']) && is_numeric($item['vote_average']) ? number_format((float)$item['vote_average'], 1, '.', '') : '',
                'summary' => trim((string)($item['overview'] ?? '')),
            ];
        }
    }
    return $items;
}

function withu_douban_api_base(): string
{
    $base = trim((string)get_setting('douban_api_base', 'https://api.justoneapi.com'));
    return $base !== '' ? rtrim($base, '/') : 'https://api.justoneapi.com';
}

function withu_douban_api_token(): string
{
    return trim((string)get_setting('douban_api_token', ''));
}

function withu_douban_api_request(string $path, array $query = [], int $timeout = 90): array
{
    $token = withu_douban_api_token();
    if ($token === '') return [];
    $query = array_merge(['token' => $token], $query);
    $url = withu_douban_api_base() . '/' . ltrim($path, '/') . '?' . http_build_query($query);
    $raw = withu_douban_get($url, $timeout);
    if ($raw === '') return [];
    $json = json_decode($raw, true);
    if (!is_array($json)) return [];
    if (array_key_exists('code', $json) && (string)$json['code'] !== '0') return [];
    return is_array($json['data'] ?? null) ? $json['data'] : $json;
}

function withu_array_first_scalar($node, array $keys): string
{
    if (!is_array($node)) return '';
    foreach ($keys as $key) {
        if (array_key_exists($key, $node) && !is_array($node[$key]) && trim((string)$node[$key]) !== '') return trim((string)$node[$key]);
    }
    foreach ($node as $child) {
        if (is_array($child)) {
            $value = withu_array_first_scalar($child, $keys);
            if ($value !== '') return $value;
        }
    }
    return '';
}

function withu_array_collect_names($node, array $containerKeys = []): array
{
    $names = [];
    if (!is_array($node)) return $names;
    $scan = $node;
    foreach ($containerKeys as $key) {
        if (isset($node[$key]) && is_array($node[$key])) {
            $scan = $node[$key];
            break;
        }
    }
    foreach ($scan as $item) {
        if (is_string($item) && trim($item) !== '') {
            $names[] = trim($item);
        } elseif (is_array($item)) {
            $name = withu_array_first_scalar($item, ['name', 'title', 'cn_name', 'name_cn']);
            if ($name !== '') $names[] = $name;
        }
    }
    return array_values(array_unique(array_filter($names)));
}

function withu_douban_api_metadata(string $subjectId): array
{
    if (!preg_match('/^[0-9]{4,12}$/', trim($subjectId))) return [];
    $data = withu_douban_api_request('/api/douban/get-subject-detail/v1', ['subjectId' => trim($subjectId)], 90);
    if (!$data) return [];
    $metadata = ['douban_id' => trim($subjectId)];
    $title = withu_array_first_scalar($data, ['title', 'name', 'original_title', 'subject_title']);
    if ($title !== '') $metadata['series_name'] = mb_substr($title, 0, 255);
    $episodeCount = withu_array_first_scalar($data, ['episodes_count', 'episode_count', 'total_episodes', 'number_of_episodes']);
    if (is_numeric($episodeCount) && (int)$episodeCount > 0) $metadata['douban_episode_count'] = (int)$episodeCount;
    $lastEpisodeNumber = withu_array_first_scalar($data, ['last_episode_number', 'latest_episode_number']);
    if (array_key_exists('is_released', $data)) $metadata['douban_is_released'] = (bool)$data['is_released'];
    if (is_numeric($lastEpisodeNumber) && (int)$lastEpisodeNumber > 0) $metadata['douban_last_episode_number'] = (int)$lastEpisodeNumber;
    $cover = withu_array_first_scalar($data, ['cover_url', 'cover', 'poster_url', 'poster', 'pic', 'image', 'img', 'url']);
    if ($cover !== '' && preg_match('#^https?://#i', $cover)) $metadata['cover_url'] = $cover;
    $rating = withu_array_first_scalar($data, ['rating', 'score', 'rate', 'value']);
    if ($rating !== '' && is_numeric($rating)) $metadata['rating'] = number_format((float)$rating, 1, '.', '');
    $summary = withu_array_first_scalar($data, ['summary', 'intro', 'description', 'desc', 'plot']);
    if ($summary !== '') $metadata['summary'] = trim(preg_replace('/\s+/u', ' ', $summary));
    $genres = withu_array_collect_names($data, ['genres', 'genre', 'types', 'tags']);
    if ($genres) $metadata['tags'] = json_encode($genres, JSON_UNESCAPED_UNICODE);
    $actors = withu_array_collect_names($data, ['actors', 'casts', 'cast', 'celebrities']);
    if ($actors) $metadata['cast_names'] = json_encode($actors, JSON_UNESCAPED_UNICODE);
    return $metadata;
}

function withu_douban_subject_id(string $query, string $knownId = ''): string
{
    if (preg_match('/^[0-9]{4,12}$/', trim($knownId))) return trim($knownId);
    $query = trim($query);
    if ($query === '') return '';
    $json = withu_douban_get('https://movie.douban.com/j/subject_suggest?q=' . rawurlencode($query), 10);
    $items = json_decode($json, true);
    if (!is_array($items)) return '';
    $normalized = preg_replace('/[\s\-_:：!！?？]+/u', '', mb_strtolower($query));
    foreach ($items as $item) {
        $id = trim((string)($item['id'] ?? ''));
        $title = (string)($item['title'] ?? $item['sub_title'] ?? '');
        $candidate = preg_replace('/[\s\-_:：!！?？]+/u', '', mb_strtolower($title));
        if ($id !== '' && ($candidate === $normalized || strpos($candidate, $normalized) !== false || strpos($normalized, $candidate) !== false)) return $id;
    }
    $mobile = json_decode(withu_douban_get('https://m.douban.com/rexxar/api/v2/search/subjects?q=' . rawurlencode($query), 12), true);
    foreach ((array)($mobile['subjects']['items'] ?? []) as $item) {
        $target = is_array($item['target'] ?? null) ? $item['target'] : [];
        $id = trim((string)($target['id'] ?? ''));
        $title = (string)($target['title'] ?? '');
        $candidate = preg_replace('/[\s\-_:：!！?？]+/u', '', mb_strtolower($title));
        if ($id !== '' && ($candidate === $normalized || strpos($candidate, $normalized) !== false || strpos($normalized, $candidate) !== false)) return $id;
    }
    $first = $items[0] ?? [];
    return preg_match('/^[0-9]{4,12}$/', (string)($first['id'] ?? '')) ? (string)$first['id'] : '';
}

function withu_douban_metadata(string $query, string $knownId = ''): array
{
    static $cache = [];
    $cacheKey = mb_strtolower(trim($query) . '|' . trim($knownId));
    if (array_key_exists($cacheKey, $cache)) return $cache[$cacheKey];
    $id = withu_douban_subject_id($query, $knownId);
    if ($id === '') return $cache[$cacheKey] = [];

    $data = withu_douban_api_metadata($id);
    if (!$data) $data = ['douban_id' => $id];
    if (trim($query) !== '') {
        $suggest = json_decode(withu_douban_get('https://movie.douban.com/j/subject_suggest?q=' . rawurlencode($query), 10), true);
        if (is_array($suggest)) {
            foreach ($suggest as $item) {
                if ((string)($item['id'] ?? '') === $id && !empty($item['img'])) {
                    $data['cover_url'] = (string)$item['img'];
                    break;
                }
            }
        }
    }

    $mobile = json_decode(withu_douban_get('https://m.douban.com/rexxar/api/v2/subject/' . rawurlencode($id), 12), true);
    if (is_array($mobile)) {
        // media_library stores the normalized movie/series name in
        // series_name; it does not have a standalone title column.
        if (!empty($mobile['title'])) $data['series_name'] = trim((string)$mobile['title']);
        if (!empty($mobile['cover_url'])) $data['cover_url'] = (string)$mobile['cover_url'];
        if (!empty($mobile['rating']['value']) && is_numeric($mobile['rating']['value'])) $data['rating'] = number_format((float)$mobile['rating']['value'], 1, '.', '');
        if (!empty($mobile['intro']) && is_string($mobile['intro'])) $data['summary'] = trim(preg_replace('/\s+/u', ' ', $mobile['intro']));
        if (!empty($mobile['episodes_count']) && is_numeric($mobile['episodes_count']) && (int)$mobile['episodes_count'] > 0) $data['douban_episode_count'] = (int)$mobile['episodes_count'];
        if (array_key_exists('is_released', $mobile)) $data['douban_is_released'] = (bool)$mobile['is_released'];
        if (!empty($mobile['last_episode_number']) && is_numeric($mobile['last_episode_number']) && (int)$mobile['last_episode_number'] > 0) $data['douban_last_episode_number'] = (int)$mobile['last_episode_number'];
        if (!empty($mobile['genres']) && is_array($mobile['genres'])) $data['tags'] = json_encode(array_values(array_unique(array_map('strval', $mobile['genres']))), JSON_UNESCAPED_UNICODE);
        if (!empty($mobile['actors']) && is_array($mobile['actors'])) $data['cast_names'] = json_encode(array_values(array_filter(array_map(static function ($actor): string { return trim((string)($actor['name'] ?? '')); }, $mobile['actors']))), JSON_UNESCAPED_UNICODE);
    }

    $html = withu_douban_get('https://movie.douban.com/subject/' . rawurlencode($id) . '/', 15);
    if ($html !== '' && class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $meta = static function (DOMXPath $xpath, string $property): string {
            $nodes = $xpath->query('//meta[@property="' . $property . '"]/@content');
            return $nodes && $nodes->length ? trim((string)$nodes->item(0)->nodeValue) : '';
        };
        $image = $meta($xpath, 'og:image');
        if ($image !== '') $data['cover_url'] = $image;
        $rating = $xpath->query('//strong[contains(@class,"rating_num")]');
        if ($rating && $rating->length && is_numeric(trim($rating->item(0)->textContent))) $data['rating'] = number_format((float)trim($rating->item(0)->textContent), 1, '.', '');
        $summary = $xpath->query('//span[@property="v:summary"]');
        if ($summary && $summary->length) $data['summary'] = trim(preg_replace('/\s+/u', ' ', $summary->item(0)->textContent));
        $info = $xpath->query('//div[@id="info"]');
        if ($info && $info->length) {
            $infoText = preg_replace('/\s+/u', ' ', (string)$info->item(0)->textContent);
            if (preg_match('/集数\s*[:：]\s*(\d{1,4})/u', $infoText, $episodeMatch)) $data['douban_episode_count'] = (int)$episodeMatch[1];
        }
        $genres = [];
        foreach ($xpath->query('//span[@property="v:genre"]') ?: [] as $node) $genres[] = trim($node->textContent);
        if ($genres) $data['tags'] = json_encode(array_values(array_unique($genres)), JSON_UNESCAPED_UNICODE);
        $actors = [];
        foreach ($xpath->query('//span[@class="actor"]//span[@property="v:starring"]') ?: [] as $node) $actors[] = trim($node->textContent);
        if ($actors) $data['cast_names'] = json_encode(array_values(array_unique($actors)), JSON_UNESCAPED_UNICODE);
    }
    return $cache[$cacheKey] = $data;
}

function withu_media_normalize_title(string $title): string
{
    $title = mb_strtolower(trim($title));
    $title = preg_replace('/\.[a-z0-9]{2,5}$/iu', '', $title);
    // Media folders often carry provider markers such as "{tmdb-12345}".
    // They are useful in the source path but must not take part in title matching.
    $title = preg_replace('/\{[^}]*\}/u', '', $title);
    $title = preg_replace('/[\[（(](?:19|20)\d{2}[\]）)]/u', '', $title);
    $title = preg_replace('/\b(?:s\d{1,2}e\d{1,4}|\d{3,4}p|2160p|1080p|720p|web[- ]?dl|hdr|h265|h264|hevc|aac|ddp|sdr|mkv|mp4)\b/iu', '', $title);
    $title = preg_replace('/第\s*\d{1,4}\s*集/u', '', $title);
    return preg_replace('/[\s\-_:：!！?？,，.。·《》\[\]\(\)（）]+/u', '', $title);
}

function withu_media_hot_api_url(string $urlOrPath): string
{
    $urlOrPath = trim($urlOrPath);
    if ($urlOrPath === '' || preg_match('#^https?://#i', $urlOrPath)) return $urlOrPath;
    $base = trim((string)get_setting('douban_hot_api_base', ''));
    if ($base === '') $base = trim((string)get_setting('douban_api_base', ''));
    if ($base === '') $base = withu_douban_api_base();
    if ($base === '') return '';
    return rtrim($base, '/') . '/' . ltrim($urlOrPath, '/');
}

function withu_media_hot_item_value(array $item, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($item[$key]) && !is_array($item[$key]) && trim((string)$item[$key]) !== '') {
            return trim((string)$item[$key]);
        }
    }
    return '';
}

function withu_media_collect_hot_items($node, array &$items): void
{
    if (!is_array($node)) return;
    $title = withu_media_hot_item_value($node, ['title', 'name', 'movie_name', 'movieName', 'tv_name', 'tvName', 'vod_name', 'subject_title']);
    $id = withu_media_hot_item_value($node, ['douban_id', 'doubanId', 'subject_id', 'subjectId', 'sid', 'id']);
    if ($title !== '') {
        $items[] = [
            'title' => $title,
            'douban_id' => preg_match('/^[0-9]{4,12}$/', $id) ? $id : '',
            'cover_url' => withu_media_hot_item_value($node, ['cover_url', 'cover', 'poster_url', 'poster', 'pic', 'img', 'image']),
            'rating' => withu_media_hot_item_value($node, ['rating', 'score', 'rate']),
            'summary' => withu_media_hot_item_value($node, ['summary', 'intro', 'description', 'desc']),
        ];
    }
    foreach ($node as $child) {
        if (is_array($child)) withu_media_collect_hot_items($child, $items);
    }
}

function withu_media_fetch_hot_items(array $urlsOrPaths): array
{
    $items = [];
    foreach ($urlsOrPaths as $urlOrPath) {
        $url = withu_media_hot_api_url((string)$urlOrPath);
        if ($url === '') continue;
        $token = withu_douban_api_token();
        if ($token !== '' && strpos($url, 'token=') === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'token=' . rawurlencode($token);
        }
        $raw = withu_douban_get($url, 20);
        if ($raw === '') continue;
        $json = json_decode($raw, true);
        if (!is_array($json)) continue;
        withu_media_collect_hot_items($json, $items);
    }
    $dedup = [];
    foreach ($items as $item) {
        $key = $item['douban_id'] !== '' ? 'id:' . $item['douban_id'] : 'title:' . withu_media_normalize_title((string)$item['title']);
        if (!isset($dedup[$key])) $dedup[$key] = $item;
    }
    return array_values($dedup);
}

function withu_media_hot_match(array $media, array $hotItems): array
{
    if (!$hotItems) return [];
    $haystack = withu_media_normalize_title((string)($media['series_name'] ?? '') . ' ' . (string)($media['file_name'] ?? '') . ' ' . basename((string)($media['source_key'] ?? '')));
    if ($haystack === '') return [];
    foreach ($hotItems as $item) {
        $needle = withu_media_normalize_title((string)($item['title'] ?? ''));
        if ($needle === '') continue;
        if ($haystack === $needle || strpos($haystack, $needle) !== false || strpos($needle, $haystack) !== false) return $item;
    }
    return [];
}

function withu_recognize_series($db, string $seriesKey, array $hints = [], bool $force = false): array
{
    $seriesKey = trim($seriesKey);
    if ($seriesKey === '') return ['success' => false, 'message' => '缺少剧集分组'];
    $episodes = $db->fetchAll(
        'SELECT * FROM media_library WHERE series_key = :series_key ORDER BY season_number ASC, episode_number ASC, id ASC LIMIT 5000',
        ['series_key' => $seriesKey]
    );
    if (!$episodes) return ['success' => false, 'message' => '未找到剧集'];
    $first = withu_media_display_row($episodes[0]);
    if (!$force && (string)($first['recognition_status'] ?? '') === 'recognized' && !empty($first['douban_id'])) {
        return ['success' => true, 'skipped' => true, 'message' => '剧集已刮削'];
    }

    $data = withu_media_structure($first);
    if (!empty($hints['title'])) $data['series_name'] = mb_substr((string)$hints['title'], 0, 255);
    if (!empty($hints['douban_id']) && preg_match('/^[0-9]{4,12}$/', (string)$hints['douban_id'])) $data['douban_id'] = (string)$hints['douban_id'];
    if (!empty($hints['tmdb_id']) && preg_match('/^\d{1,12}$/', (string)$hints['tmdb_id'])) $data['tmdb_id'] = (string)$hints['tmdb_id'];
    if (!empty($hints['cover_url'])) $data['cover_url'] = (string)$hints['cover_url'];
    if (!empty($hints['rating']) && is_numeric($hints['rating'])) $data['rating'] = number_format((float)$hints['rating'], 1, '.', '');
    if (!empty($hints['summary'])) $data['summary'] = (string)$hints['summary'];
    if (!empty($hints['backdrop_url'])) $data['backdrop_url'] = (string)$hints['backdrop_url'];

    $tmdbMatch = [];
    if (empty($data['tmdb_id'])) $tmdbMatch = withu_tmdb_match_series($first, $data);
    if ($tmdbMatch && empty($tmdbMatch['match_needs_review'])) {
        $data = array_merge($data, $tmdbMatch);
    }
    $tmdb = !empty($data['tmdb_id']) ? withu_tmdb_metadata((string)$data['tmdb_id'], (string)($hints['media_type'] ?? ((int)($data['media_type_id'] ?? 1) === 1 ? 'movie' : 'tv'))) : [];
    if ($tmdb) $data = array_merge($data, $tmdb);

    if (empty($data['douban_id']) && empty($data['tmdb_id'])) {
        $endpoint = trim((string)get_setting('ai_api_endpoint', ''));
        $apiKey = trim((string)get_setting('ai_api_key', ''));
        if ($endpoint === '' && $apiKey !== '') $endpoint = 'https://api.deepseek.com/chat/completions';
        if ($endpoint !== '' && $apiKey !== '') {
            $payload = json_encode([
                'model' => get_setting('ai_model', 'deepseek-chat'),
                'messages' => [
                    ['role' => 'system', 'content' => '你是影视豆瓣 ID 识别器。只返回合法 JSON，不要 Markdown，格式必须是 {"douban_id":"数字"}。无法确认时返回 {"douban_id":""}。'],
                    ['role' => 'user', 'content' => "根据以下剧集路径和示例文件名识别豆瓣 subject ID：\n剧集名：" . (string)($data['series_name'] ?? '') . "\n路径：" . (string)($first['source_key'] ?? '') . "\n文件名：" . (string)($first['file_name'] ?? '')],
                ],
                'temperature' => 0.1,
            ], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            ]);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $ai = withu_ai_json((string)($decoded['choices'][0]['message']['content'] ?? ''));
            $doubanId = is_array($ai) ? trim((string)($ai['douban_id'] ?? '')) : '';
            if ($status >= 200 && $status < 300 && preg_match('/^[0-9]{4,12}$/', $doubanId)) $data['douban_id'] = $doubanId;
        }
    }

    // For domestic hot items, the mobile endpoint is the preferred detail
    // source; web scraping stays only as a fallback for non-hot lookups.
    $douban = !$tmdb && !empty($data['douban_id'])
        ? withu_douban_mobile_metadata((string)$data['douban_id'])
        : [];
    if (!$douban && !$tmdb) $douban = withu_douban_metadata((string)($data['series_name'] ?? $first['series_name'] ?? ''), (string)($data['douban_id'] ?? ''));
    if ($douban) $data = array_merge($data, $douban);
    $now = withu_now();
    $localMetadata = json_decode((string)($data['metadata_json'] ?? '{}'), true);
    if (!is_array($localMetadata)) $localMetadata = [];
    $localMetadata['metadata_match'] = [
        'status' => (!empty($data['douban_id']) || !empty($data['tmdb_id'])) ? 'matched' : (!empty($tmdbMatch['match_candidates']) ? 'needs_review' : 'unmatched'),
        'source' => !empty($data['tmdb_id']) ? 'tmdb' : (!empty($data['douban_id']) ? 'douban' : 'local'),
        'douban_id' => (string)($data['douban_id'] ?? ''), 'tmdb_id' => (string)($data['tmdb_id'] ?? ''),
        'score' => $tmdbMatch['match_score'] ?? null, 'confidence' => $tmdbMatch['match_confidence'] ?? null,
        'candidates' => $tmdbMatch['match_candidates'] ?? [],
        'matched_at' => $now,
    ];
    $data = withu_media_localize_metadata_images($data, $seriesKey);
    $shared = array_filter([
        'series_name' => $data['series_name'] ?? null,
        'douban_id' => $data['douban_id'] ?? null,
        'tmdb_id' => $data['tmdb_id'] ?? null,
        'rating' => $data['rating'] ?? null,
        'cast_names' => $data['cast_names'] ?? null,
        'summary' => $data['summary'] ?? null,
        'cover_url' => $data['cover_url'] ?? null,
        'backdrop_url' => $data['backdrop_url'] ?? null,
        'tags' => $data['tags'] ?? null,
        'metadata_json' => $data['metadata_json'] ?? null,
        'recognition_status' => 'recognized',
        'recognition_source' => !empty($data['tmdb_id']) ? 'tmdb' : (!empty($hints) ? 'douban_cn_hot' : 'on_demand'),
        'recognized_at' => $now,
        'updated_at' => $now,
    ], static function ($value): bool { return $value !== null && $value !== ''; });
    $db->update('media_library', $shared, 'series_key = :series_key', ['series_key' => $seriesKey]);
    $resourceUpdate = [
        'media_type_id' => (int)($data['media_type_id'] ?? 1), 'title' => (string)($data['series_name'] ?? ''),
        'metadata_json' => json_encode($localMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'recognition_status' => 'recognized', 'updated_at' => $now,
    ];
    $mediaIds = array_values(array_unique(array_map(static function ($item): int { return (int)($item['id'] ?? 0); }, $episodes)));
    foreach ($episodes as $episode) {
        $episodeId = (int)($episode['id'] ?? 0);
        if ($episodeId <= 0) continue;
        $episodeMetadata = json_decode((string)($episode['metadata_json'] ?? '{}'), true);
        if (!is_array($episodeMetadata)) $episodeMetadata = [];
        $episodeMetadata['metadata_match'] = $localMetadata['metadata_match'];
        $db->update('media_library', ['metadata_json' => json_encode($episodeMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => $now], 'id = :id', ['id' => $episodeId]);
    }
    foreach ($mediaIds as $mediaId) if ($mediaId > 0) $db->update('media_resources', $resourceUpdate, 'media_id = :media_id', ['media_id' => $mediaId]);
    return ['success' => true, 'skipped' => false, 'message' => '剧集信息已刮削', 'episodes' => count($episodes), 'data' => $shared];
}

function withu_copy_series_metadata($db, array $media): array
{
    $seriesKey = trim((string)($media['series_key'] ?? ''));
    if ($seriesKey === '') return [];
    $peer = $db->fetch('SELECT douban_id,tmdb_id,rating,cast_names,summary,cover_url,backdrop_url,tags FROM media_library WHERE series_key = :series_key AND id <> :id AND recognition_status = :status AND (douban_id IS NOT NULL OR tmdb_id IS NOT NULL OR cover_url IS NOT NULL) ORDER BY id ASC LIMIT 1', ['series_key' => $seriesKey, 'id' => (int)$media['id'], 'status' => 'recognized']);
    if (!$peer) return [];
    return array_filter($peer, static function ($value): bool { return $value !== null && $value !== ''; });
}

function withu_recognize_media($db, array $media, bool $force = false): array
{
    if (!$force && ($media['recognition_status'] ?? '') === 'recognized') {
        return ['success' => true, 'skipped' => true, 'message' => '文件没有变化，跳过重复识别'];
    }

    $now = withu_now();
    $data = withu_media_structure($media);
    $name = (string)($media['file_name'] ?? '');
    $seriesPeer = withu_copy_series_metadata($db, $media);
    if ($seriesPeer) $data = array_merge($data, $seriesPeer, ['recognition_source' => 'douban_cache']);
    $endpoint = trim((string)get_setting('ai_api_endpoint', ''));
    $apiKey = trim((string)get_setting('ai_api_key', ''));
    if (!$seriesPeer && $endpoint === '' && $apiKey !== '') $endpoint = 'https://api.deepseek.com/chat/completions';

    if (!$seriesPeer && $endpoint !== '' && $apiKey !== '') {
        $payload = json_encode([
            'model' => get_setting('ai_model', 'deepseek-chat'),
            'messages' => [
                ['role' => 'system', 'content' => '你是影视豆瓣 ID 识别器。只返回合法 JSON，不要 Markdown，格式必须是 {"douban_id":"数字"}。只能返回你确认过的豆瓣 subject ID，无法确认时返回 {"douban_id":""}，不要返回评分、简介、封面或其他字段。'],
                ['role' => 'user', 'content' => "根据以下媒体路径和文件名识别影视信息：\n路径：" . (string)($media['source_key'] ?? '') . "\n文件名：" . $name],
            ],
            'temperature' => 0.1,
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $ai = withu_ai_json((string)($decoded['choices'][0]['message']['content'] ?? ''));
        if ($status >= 200 && $status < 300 && is_array($ai)) {
            $doubanId = trim((string)($ai['douban_id'] ?? ''));
            if (preg_match('/^[0-9]{4,12}$/', $doubanId)) $data['douban_id'] = $doubanId;
            $data['recognition_source'] = 'deepseek';
        } else {
            $data['recognition_source'] = 'local';
        }
    } elseif (!$seriesPeer) {
        $data['recognition_source'] = 'local';
    }

    $douban = withu_douban_metadata((string)($data['series_name'] ?? ''), (string)($data['douban_id'] ?? ''));
    if ($douban) {
        $data = array_merge($data, $douban);
        $data['recognition_source'] = $seriesPeer ? 'douban_cache' : 'deepseek_douban';
    }

    $data['recognition_status'] = 'recognized';
    $data['recognized_at'] = $now;
    $data['updated_at'] = $now;
    $data = withu_media_localize_metadata_images($data, (string)($data['series_key'] ?? $media['series_key'] ?? $media['id'] ?? ''));
    $db->update('media_library', $data, 'id = :id', ['id' => (int)$media['id']]);
    return ['success' => true, 'skipped' => false, 'message' => '媒体信息已保存', 'data' => $data];
}
