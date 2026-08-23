<?php
/**
 * WithU · cz（厂长资源 4kcz.com）本地采集库。
 * ------------------------------------------------------------
 * 目标：把 cz 的搜索结果 / 详情线路 / 解析结果落到本地数据库，
 *      搜索与播放优先走本地，miss 才打源站并回填，降低重复请求。
 *
 * 表：
 *   cz_resources  —— 资源（title + detail_url + 线路列表 vplays_json）
 *   cz_lines      —— 线路解析结果缓存（vplay_url → m3u8/mp4/error）
 *
 * 所有表幂等创建，兼容已部署库。编码统一 cz，为后续其他源（xl01/zy…）预留。
 */

if (!function_exists('withu_cz_now')) {
    function withu_cz_now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('withu_cz_catalog_ensure_schema')) {
    function withu_cz_catalog_ensure_schema($db): void
    {
        static $done = false;
        if ($done) return;
        $db->query("CREATE TABLE IF NOT EXISTS `cz_resources` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `detail_url` varchar(500) NOT NULL,
            `vplays_json` mediumtext DEFAULT NULL,
            `line_count` int(11) NOT NULL DEFAULT 0,
            `poster` varchar(500) NOT NULL DEFAULT '',
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `last_checked_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_cz_detail_url` (`detail_url`(191)),
            KEY `idx_cz_title` (`title`),
            KEY `idx_cz_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query("CREATE TABLE IF NOT EXISTS `cz_lines` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `resource_id` int(11) NOT NULL DEFAULT 0,
            `vplay_url` varchar(500) NOT NULL,
            `label` varchar(64) NOT NULL DEFAULT '',
            `kind` varchar(16) NOT NULL DEFAULT '',
            `m3u8` varchar(1000) NOT NULL DEFAULT '',
            `error` varchar(500) NOT NULL DEFAULT '',
            `seg_total` int(11) NOT NULL DEFAULT 0,
            `checked_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_cz_vplay_url` (`vplay_url`(191)),
            KEY `idx_cz_line_res` (`resource_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (function_exists('withu_cz_catalog_ensure_meta_columns')) withu_cz_catalog_ensure_meta_columns($db);
        $done = true;
    }
}

/** 幂等补充元数据列（兼容已建老表）。 */
if (!function_exists('withu_cz_catalog_ensure_meta_columns')) {
    function withu_cz_catalog_ensure_meta_columns($db): void
    {
        static $metaDone = false;
        if ($metaDone) return;
        $cols = [
            'director' => "varchar(255) NOT NULL DEFAULT ''",
            'writer' => "varchar(255) NOT NULL DEFAULT ''",
            'actors' => "text DEFAULT NULL",
            'year' => "varchar(16) NOT NULL DEFAULT ''",
            'area' => "varchar(64) NOT NULL DEFAULT ''",
            'types_json' => "varchar(255) NOT NULL DEFAULT ''",
            'aka' => "varchar(255) NOT NULL DEFAULT ''",
            'release' => "varchar(64) NOT NULL DEFAULT ''",
            'lang' => "varchar(64) NOT NULL DEFAULT ''",
            'intro' => "text DEFAULT NULL",
            'rating' => "varchar(16) NOT NULL DEFAULT ''",
            'episode_status' => "varchar(64) NOT NULL DEFAULT ''",
        ];
        foreach ($cols as $col => $def) {
            $db->query("ALTER TABLE `cz_resources` ADD COLUMN IF NOT EXISTS `{$col}` {$def}");
        }
        $metaDone = true;
    }
}


/** 归一化 4kcz 详情页 URL（补协议，用于查重）。 */
if (!function_exists('withu_cz_norm_url')) {
    function withu_cz_norm_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (strpos($url, 'http') !== 0) $url = 'https://' . $url;
        return $url;
    }
}

/** 清洗源站详情标题（去掉“在线播放|超清|1080p|厂长资源”等杂质）。 */
if (!function_exists('withu_cz_clean_title')) {
    function withu_cz_clean_title(string $title): string
    {
        $t = trim($title);
        if ($t === '') return '';
        $pos = strpos($t, '|');
        if ($pos !== false) $t = trim(substr($t, 0, $pos));
        $t = preg_replace('/[（(].*?[)）]/u', '', $t);
        $t = preg_replace('/在线播放|超清播放|超清下载|超清|高清|全集|蓝光|1080p|1080P|720p|720P|4k|4K|厂长资源|厂长/u', '', $t);
        $t = preg_replace('/^[\s·|\-_《》]+|[\s·|\-_《》]+$/u', '', $t);
        return $t !== '' ? $t : $title;
    }
}

/** 本地模糊搜索（标题 LIKE，按最近更新排序）。 */
if (!function_exists('withu_cz_catalog_search_local')) {
    function withu_cz_catalog_search_local($db, string $q, int $limit = 30): array
    {
        withu_cz_catalog_ensure_schema($db);
        $q = trim($q);
        if ($q === '') return [];
        $like = '%' . $q . '%';
        return $db->fetchAll(
            "SELECT * FROM cz_resources WHERE status = 1 AND title LIKE :q
             ORDER BY updated_at DESC LIMIT " . max(1, min(200, $limit)),
            ['q' => $like]
        );
    }
}

/** 按 detail_url 取本地资源。 */
if (!function_exists('withu_cz_catalog_get_by_url')) {
    function withu_cz_catalog_get_by_url($db, string $url): ?array
    {
        withu_cz_catalog_ensure_schema($db);
        $url = withu_cz_norm_url($url);
        if ($url === '') return null;
        $row = $db->fetch('SELECT * FROM cz_resources WHERE detail_url = :url LIMIT 1', ['url' => $url]);
        return $row ?: null;
    }
}


/** 海报下载到本地 runtime/cache/cz/posters/，返回 web 相对路径（失败返回空串）。 */
if (!function_exists('withu_cz_download_poster')) {
    function withu_cz_download_poster(string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') return '';
        if (!preg_match('#^https?://#', $posterUrl)) return $posterUrl;
        $dir = ROOT_PATH . '/runtime/cache/cz/posters/';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $ext = 'jpg';
        if (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $posterUrl, $em)) $ext = strtolower($em[1]) === 'jpeg' ? 'jpg' : strtolower($em[1]);
        $name = md5($posterUrl) . '.' . $ext;
        $file = $dir . $name;
        if (is_file($file) && filesize($file) > 100) return '/runtime/cache/cz/posters/' . $name;
        try {
            $r = cz_get($posterUrl, ['Referer' => 'https://www.4kcz.com/'], 15000);
            $body = (string)($r['body'] ?? '');
            if ($body !== '' && strlen($body) > 100) {
                // 按文件头修正扩展名
                if (str_starts_with($body, "\x89PNG")) $ext = 'png';
                elseif (str_starts_with($body, "GIF8")) $ext = 'gif';
                elseif (str_starts_with($body, "RIFF") && strpos(substr($body, 0, 16), 'WEBP') !== false) $ext = 'webp';
                if (substr($name, -4) !== '.' . $ext) { $name = md5($posterUrl) . '.' . $ext; $file = $dir . $name; }
                file_put_contents($file, $body);
                return is_file($file) && filesize($file) > 100 ? '/runtime/cache/cz/posters/' . $name : '';
            }
        } catch (Throwable $e) {
            // 海报失败不影响主流程
        }
        return '';
    }
}

/** meta 数组 → 可入库字段。 */
if (!function_exists('withu_cz_meta_to_row')) {
    function withu_cz_meta_to_row(array $meta): array
    {
        $types = array_values(array_filter((array)($meta['types'] ?? []), 'strlen'));
        return [
            'director' => (string)($meta['director'] ?? ''),
            'writer' => (string)($meta['writer'] ?? ''),
            'actors' => (string)($meta['actors'] ?? ''),
            'year' => (string)($meta['year'] ?? ''),
            'area' => (string)($meta['area'] ?? ''),
            'types_json' => json_encode($types, JSON_UNESCAPED_UNICODE),
            'aka' => (string)($meta['aka'] ?? ''),
            'release' => (string)($meta['release'] ?? ''),
            'lang' => (string)($meta['lang'] ?? ''),
            'intro' => (string)($meta['intro'] ?? ''),
            'rating' => (string)($meta['rating'] ?? ''),
            'poster' => (string)($meta['poster_local'] ?? ''),
        ];
    }
}

/** upsert 资源（title + detail_url + 线路列表 + 元数据）。vplays: [{url,label}]，meta: 可选字段 */
if (!function_exists('withu_cz_catalog_upsert')) {
    function withu_cz_catalog_upsert($db, string $title, string $url, array $vplays = [], array $meta = []): array
    {
        withu_cz_catalog_ensure_schema($db);
        $url = withu_cz_norm_url($url);
        $now = withu_cz_now();
        $vplaysJson = $vplays ? json_encode($vplays, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $lineCount = count($vplays);
        $metaRow = withu_cz_meta_to_row($meta);
        $row = $url !== '' ? withu_cz_catalog_get_by_url($db, $url) : null;
        $set = [
            'title' => $title !== '' ? $title : (string)($row['title'] ?? ''),
            'updated_at' => $now,
        ];
        if ($vplaysJson !== null) {
            $set['vplays_json'] = $vplaysJson;
            $set['line_count'] = $lineCount;
            $set['last_checked_at'] = $now;
            if ($lineCount > 0) $set['episode_status'] = '共 ' . $lineCount . ' 集';
        }
        foreach ($metaRow as $k => $v) {
            if ($v === '') continue; // 不覆盖已有值
            if ($k === 'poster' && !empty($row['poster']) && $row['poster'] !== $v) continue; // 已有海报不换
            $set[$k] = $v;
        }
        if ($row) {
            $db->update('cz_resources', $set, 'id = :id', ['id' => (int)$row['id']]);
            $id = (int)$row['id'];
        } else {
            $insert = [
                'title' => $title,
                'detail_url' => $url,
                'vplays_json' => $vplaysJson,
                'line_count' => $lineCount,
                'status' => 1,
                'last_checked_at' => $vplaysJson !== null ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach ($metaRow as $k => $v) {
                if ($v !== '') $insert[$k] = $v;
            }
            $id = (int)$db->insert('cz_resources', $insert);
        }
        return ['id' => $id, 'title' => $title, 'detail_url' => $url, 'vplays' => $vplays];
    }
}

/** 打源站拉详情（detail_url → title + vplays），并入库。 */
if (!function_exists('withu_cz_catalog_refresh_detail')) {
    function withu_cz_catalog_refresh_detail($db, string $url, bool $force = false): array
    {
        withu_cz_catalog_ensure_schema($db);
        $url = withu_cz_norm_url($url);
        if (strpos($url, '4kcz.com') === false) {
            throw new RuntimeException('请输入 4kcz.com 详情页或播放页网址');
        }
        // 本地有线路且 12 小时内刷新过 → 直接用
        if (!$force) {
            $row = withu_cz_catalog_get_by_url($db, $url);
            if ($row && !empty($row['vplays_json'])) {
                $checked = strtotime((string)($row['last_checked_at'] ?? ''));
                if ($checked && (time() - $checked) < 12 * 3600) {
                    $vplays = json_decode((string)$row['vplays_json'], true) ?: [];
                    $types = !empty($row['types_json']) ? (json_decode((string)$row['types_json'], true) ?: []) : [];
                    return [
                        'title' => (string)$row['title'], 'vplays' => $vplays, 'cached' => true, 'resource_id' => (int)$row['id'],
                        'meta' => [
                            'poster' => (string)($row['poster'] ?? ''),
                            'director' => (string)($row['director'] ?? ''),
                            'writer' => (string)($row['writer'] ?? ''),
                            'actors' => (string)($row['actors'] ?? ''),
                            'year' => (string)($row['year'] ?? ''),
                            'area' => (string)($row['area'] ?? ''),
                            'types' => $types,
                            'aka' => (string)($row['aka'] ?? ''),
                            'release' => (string)($row['release'] ?? ''),
                            'lang' => (string)($row['lang'] ?? ''),
                            'intro' => (string)($row['intro'] ?? ''),
                            'rating' => (string)($row['rating'] ?? ''),
                            'episode_status' => (string)($row['episode_status'] ?? ''),
                        ],
                    ];
                }
            }
        }
        $d = cz_detailToVplays($url);
        $vplays = array_map(static function ($vp): array {
            if (is_string($vp)) $vp = ['url' => $vp, 'label' => ''];
            return ['url' => (string)($vp['url'] ?? ''), 'label' => (string)($vp['label'] ?? '')];
        }, is_array($d['vplays'] ?? null) ? $d['vplays'] : []);
        // 元数据 + 海报（详情页 HTML 需再拉一次）
        $meta = [];
        try {
            $page = cz_get($url, ['Referer' => 'https://www.4kcz.com/']);
            if (($page['status'] ?? 0) === 200) {
                $meta = cz_parseDetailMeta((string)($page['body'] ?? ''));
                $posterLocal = $meta['poster'] !== '' ? withu_cz_download_poster($meta['poster']) : '';
                $meta['poster_local'] = $posterLocal;
            }
        } catch (Throwable $e) {
            // 元数据失败不影响线路入库
        }
        $saved = withu_cz_catalog_upsert($db, withu_cz_clean_title((string)($d['title'] ?? '')), $url, $vplays, $meta);
        return ['title' => $saved['title'], 'vplays' => $vplays, 'meta' => $meta, 'cached' => false, 'resource_id' => (int)$saved['id']];
    }
}

/** 按 vplay_url 查线路解析缓存（默认 2 小时新鲜）。 */
if (!function_exists('withu_cz_catalog_get_line')) {
    function withu_cz_catalog_get_line($db, string $vplayUrl, int $ttlSeconds = 7200): ?array
    {
        withu_cz_catalog_ensure_schema($db);
        $vplayUrl = trim($vplayUrl);
        if ($vplayUrl === '') return null;
        $row = $db->fetch('SELECT * FROM cz_lines WHERE vplay_url = :url LIMIT 1', ['url' => $vplayUrl]);
        if (!$row) return null;
        $checked = strtotime((string)($row['checked_at'] ?? ''));
        if (!$checked || (time() - $checked) > $ttlSeconds) return null;
        if (!empty($row['error'])) return null; // 之前失败的不复用
        $entry = [
            'vplay' => (string)$row['vplay_url'],
            'm3u8' => (string)$row['m3u8'],
            'kind' => (string)$row['kind'],
            'segTotal' => (int)$row['seg_total'],
            'cached' => true,
        ];
        return $entry;
    }
}

/** 线路解析结果入库。 */
if (!function_exists('withu_cz_catalog_save_line')) {
    function withu_cz_catalog_save_line($db, int $resourceId, string $vplayUrl, array $entry): void
    {
        withu_cz_catalog_ensure_schema($db);
        $now = withu_cz_now();
        $kind = (string)($entry['kind'] ?? '');
        if ($kind === '') $kind = preg_match('/\.m3u8(\?|$)/i', (string)($entry['m3u8'] ?? '')) ? 'hls' : 'mp4';
        $error = (string)($entry['error'] ?? '');
        $m3u8 = (string)($entry['m3u8'] ?? '');
        $row = $db->fetch('SELECT id FROM cz_lines WHERE vplay_url = :url LIMIT 1', ['url' => $vplayUrl]);
        if ($row) {
            $db->update('cz_lines', [
                'resource_id' => $resourceId,
                'kind' => $kind,
                'm3u8' => $m3u8,
                'error' => $error,
                'seg_total' => (int)($entry['segTotal'] ?? 0),
                'checked_at' => $now,
                'updated_at' => $now,
            ], 'id = :id', ['id' => (int)$row['id']]);
        } else {
            $db->insert('cz_lines', [
                'resource_id' => $resourceId,
                'vplay_url' => $vplayUrl,
                'label' => '',
                'kind' => $kind,
                'm3u8' => $m3u8,
                'error' => $error,
                'seg_total' => (int)($entry['segTotal'] ?? 0),
                'checked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

/** 主动采集：关键词搜索 → 逐部拉详情线路入库（最多 maxItems 部）。 */
if (!function_exists('withu_cz_catalog_collect')) {
    function withu_cz_catalog_collect($db, string $q, int $maxItems = 20): array
    {
        withu_cz_catalog_ensure_schema($db);
        $q = trim($q);
        if ($q === '') return ['success' => false, 'message' => '缺少关键词'];
        $list = cz_search($q); // 可能抛 RuntimeException
        $list = array_slice($list, 0, max(1, min(50, $maxItems)));
        $collected = 0;
        $failed = [];
        foreach ($list as $item) {
            $url = withu_cz_norm_url((string)($item['url'] ?? ''));
            $title = (string)($item['title'] ?? '');
            if ($url === '') continue;
            try {
                withu_cz_catalog_refresh_detail($db, $url, true);
                $collected++;
            } catch (Throwable $e) {
                $failed[] = $title !== '' ? $title : $url;
            }
        }
        return [
            'success' => true,
            'keyword' => $q,
            'found' => count($list),
            'collected' => $collected,
            'failed' => $failed,
        ];
    }
}

/** 本地资源库分页列表。 */
if (!function_exists('withu_cz_catalog_list')) {
    function withu_cz_catalog_list($db, int $page = 1, int $perPage = 20, string $q = ''): array
    {
        withu_cz_catalog_ensure_schema($db);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = 'status = 1';
        $params = [];
        if (trim($q) !== '') {
            $where .= ' AND title LIKE :q';
            $params['q'] = '%' . trim($q) . '%';
        }
        $total = (int)$db->fetch('SELECT COUNT(*) AS c FROM cz_resources WHERE ' . $where, $params)['c'];
        $offset = ($page - 1) * $perPage;
        $rows = $db->fetchAll(
            "SELECT * FROM cz_resources WHERE {$where} ORDER BY updated_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($rows as &$row) {
            $row['vplays'] = !empty($row['vplays_json']) ? (json_decode((string)$row['vplays_json'], true) ?: []) : [];
            unset($row['vplays_json']);
        }
        unset($row);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}
