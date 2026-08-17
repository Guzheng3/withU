<?php
/**
 * 通用辅助函数（UTF-8 中文版）
 */

/**
 * 转义 HTML
 */
function e($string) {
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

/**
 * 根据相对路径生成完整 URL
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * 发送重定向并结束脚本
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 输出 JSON 响应并结束脚本
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 简单的 GET 请求并解析 JSON
 * - 正常返回：关联数组
 * - 出错或解析失败：返回空数组
 */
function http_get_json(string $url, int $timeout = 5): array {
    $response = null;

    // 优先使用 cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'YC Album/1.0',
        ]);
        $response = curl_exec($ch);
        
    } else {
        // 回退到 file_get_contents
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => "User-Agent: YC Album/1.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === null) {
        return [];
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

/**
 * 格式化日期字符串
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * 计算两个日期之间的天数
 */
function daysBetween($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval  = $datetime1->diff($datetime2);
    return $interval->days;
}

/**
 * 上传文件（仅允许常见图片类型）
 * 返回数组：['success' => bool, 'message' => string, ...]
 */
function uploadFile($file, $subDir = '') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '文件上传失败'];
    }

    $maxSize = function_exists('get_max_upload_size_bytes') ? get_max_upload_size_bytes() : MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) {
        $maxMb = round($maxSize / 1024 / 1024, 1);
        return ['success' => false, 'message' => '文件大小超出限制，最大约 ' . $maxMb . 'MB'];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // 尽量通过 finfo 检测真实 MIME
    if (function_exists('finfo_open')) {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mimeType = $file['type'] ?? '';
    }

    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }

    $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExt, true)) {
        return ['success' => false, 'message' => '文件扩展名不被允许'];
    }
    $filename   = uniqid('', true) . '.' . $extension;
    $uploadPath = rtrim(UPLOAD_DIR . '/' . trim($subDir, '/'), '/');

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $filepath = $uploadPath . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relativePath = trim($subDir, '/') . '/' . $filename;
        $relativePath = ltrim($relativePath, '/');
        $shouldOptimize = true;

        // 当前请求级别的“跳过图片压缩”标记（如相册管理页一次性上传时临时关闭）
        if (!empty($GLOBALS['YC_SKIP_IMAGE_OPTIMIZE'])) {
            $shouldOptimize = false;
        }

        // 针对相册支持“保留原始画质”的开关（仅影响主图压缩，不影响缩略图与 WebP 生成）
        if (strpos($relativePath, 'albums/') === 0 && class_exists('Database')) {
            try {
                $parts = explode('/', $relativePath);
                // 结构类似 albums/{albumId}/filename
                if (isset($parts[1]) && ctype_digit($parts[1])) {
                    $albumId = (int) $parts[1];
                    $db = Database::getInstance();
                    $row = $db->fetch("SELECT keep_original_quality FROM albums WHERE id = :id LIMIT 1", ['id' => $albumId]);
                    if ($row && !empty($row['keep_original_quality'])) {
                        $shouldOptimize = false;
                    }
                }
            } catch (Throwable $e) {
                // 查询异常时保持默认压缩行为
            }
        }

        // 图片压缩与缩略图 / WebP 生成（仅在设置开启且允许压缩时进行）
        if ($shouldOptimize) {
            try {
                if (function_exists('optimize_uploaded_image')) {
                    optimize_uploaded_image($filepath);
                }
            } catch (Throwable $e) {
                // 压缩失败不影响上传主流程
            }
        }

        return [
            'success'  => true,
            'filename' => $filename,
            'path'     => $relativePath,
            'url'      => UPLOAD_URL . $relativePath,
        ];
    }

    return ['success' => false, 'message' => '保存上传文件失败'];
}

/**
 * 从 settings 读取简单配置键
 */
function get_setting(string $key, $default = null) {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            if (!class_exists('Database')) {
                return $default;
            }
            $rows = Database::getInstance()->fetchAll("SELECT `key`, `value` FROM settings");
            foreach ($rows as $row) {
                if (isset($row['key'])) {
                    $settingsCache[(string)$row['key']] = $row['value'] ?? null;
                }
            }
        } catch (Throwable $e) {
            // Keep an empty per-request cache so a failed settings table does
            // not trigger another database query for every helper call.
        }
    }
    if (array_key_exists($key, $settingsCache)) {
        return $settingsCache[$key];
    }
    return $default;
}

if (!function_exists('withu_theme_config')) {
    function withu_theme_config(): array {
        static $config = null;
        if ($config !== null) return $config;

        $presets = ['sakura', 'mint', 'sky', 'peach', 'lemon', 'sea', 'forest', 'minimal'];
        $presetColors = [
            'pastel-couple' => ['primary' => '#F5B6C8', 'secondary' => '#B9E3D0', 'accent' => '#B8DDF2'],
            'sakura' => ['primary' => '#F5B6C8', 'secondary' => '#B9E3D0', 'accent' => '#B8DDF2'],
            'mint' => ['primary' => '#B9E3D0', 'secondary' => '#C5E9ED', 'accent' => '#B8DDF2'],
            'sky' => ['primary' => '#B8DDF2', 'secondary' => '#B9E3D0', 'accent' => '#F5B6C8'],
            'peach' => ['primary' => '#F6C2A9', 'secondary' => '#C6E3D0', 'accent' => '#B8DDF2'],
            'lemon' => ['primary' => '#F2D58A', 'secondary' => '#C5E5D2', 'accent' => '#B8DDF2'],
            'sea' => ['primary' => '#A7DEDA', 'secondary' => '#B8DDF2', 'accent' => '#F5B6C8'],
            'forest' => ['primary' => '#AFCB9B', 'secondary' => '#B9E3D0', 'accent' => '#B8DDF2'],
            'minimal' => ['primary' => '#CDD5DC', 'secondary' => '#D5E1DB', 'accent' => '#D2E3ED'],
        ];
        $preset = (string)get_setting('theme_preset', 'sakura');
        if ($preset === 'pastel-couple') $preset = 'sakura';
        if (!in_array($preset, $presets, true)) $preset = 'sakura';
        $mode = 'light';

        $colors = $presetColors[$preset];
        $hasCustomColor = false;
        foreach (['primary', 'secondary', 'accent'] as $name) {
            $value = trim((string)get_setting('theme_custom_' . $name, ''));
            if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $colors[$name] = strtolower($value);
                $hasCustomColor = true;
            }
        }
        $config = [
            'preset' => $preset,
            'mode' => $mode,
            'custom' => $hasCustomColor,
            'colors' => $colors,
        ];
        return $config;
    }
}

if (!function_exists('withu_player_logo_bg_presets')) {
    function withu_player_logo_bg_presets(): array
    {
        return [
            'sakura' => ['name' => '樱花粉', 'style' => 'linear-gradient(135deg,#F5B6C8 0%,#E486A4 52%,#B8DDF2 100%)'],
            'mint' => ['name' => '薄荷绿', 'style' => 'linear-gradient(135deg,#DDF8EC 0%,#B9E3D0 52%,#73B997 100%)'],
            'sky' => ['name' => '天空蓝', 'style' => 'linear-gradient(135deg,#E6F5FF 0%,#B8DDF2 52%,#6AAED2 100%)'],
            'peach' => ['name' => '蜜桃橙', 'style' => 'linear-gradient(135deg,#FFE4D6 0%,#FFC0A6 48%,#F5B6C8 100%)'],
            'lavender' => ['name' => '薰衣草', 'style' => 'linear-gradient(135deg,#F1E8FF 0%,#CDB7F6 52%,#B8DDF2 100%)'],
            'rose_sunset' => ['name' => '日落玫瑰', 'style' => 'linear-gradient(135deg,#FFD1DC 0%,#F5B6C8 45%,#FFB37A 100%)'],
        ];
    }
}

if (!function_exists('withu_player_logo_bg_style')) {
    function withu_player_logo_bg_style(?string $preset = null, ?string $color = null): string
    {
        $presets = withu_player_logo_bg_presets();
        $preset = trim((string)($preset ?? get_setting('player_logo_bg_preset', 'sakura')));
        if ($preset !== 'custom' && !isset($presets[$preset])) {
            $preset = 'sakura';
        }

        $color = strtolower(trim((string)($color ?? get_setting('player_logo_bg_color', '#f5b6c8'))));
        if (!preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            $color = '#f5b6c8';
        }

        return $preset === 'custom' ? $color : $presets[$preset]['style'];
    }
}

/**
 * Cloudflare Turnstile 验证
 */
function verify_turnstile(string $token): bool {
    $enabled = (string) get_setting('turnstile_enabled', '0') === '1';
    if (!$enabled) {
        return true;
    }

    $siteKey   = (string) get_setting('turnstile_site_key', '');
    $secretKey = (string) get_setting('turnstile_secret_key', '');
    if ($secretKey === '' || $token === '') {
        return false;
    }

    $postData = [
        'secret'   => $secretKey,
        'response' => $token,
    ];
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $postData['remoteip'] = $_SERVER['REMOTE_ADDR'];
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        
        return false;
    }
    

    $data = json_decode($result, true);
    if (!is_array($data)) {
        return false;
    }

    return !empty($data['success']);
}

/**
 * 对上传后的图片进行压缩与尺寸优化
 * - 控制最大长边（如 2560px）
 * - 同时为相册图片生成专用缩略图（thumbs 子目录，长边约 480px）
 */
function optimize_uploaded_image(string $absolutePath): void {
    // 开关：settings.image_optimize_enabled = '1' 时启用（默认开启）
    $enabled = get_setting('image_optimize_enabled', '1');
    if ((string)$enabled !== '1') {
        return;
    }

    if (!is_file($absolutePath)) {
        return;
    }

    // 仅处理常见图片
    $info = @getimagesize($absolutePath);
    if ($info === false) {
        return;
    }
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return;
    }

    // 通过相对路径判断所属目录，用于为首页大图等特殊目录定制压缩策略
    $relativePath = '';
    if (defined('UPLOAD_DIR')) {
        $uploadRoot = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($absolutePath, $uploadRoot) === 0) {
            $relativePath = ltrim(substr($absolutePath, strlen($uploadRoot)), '/\\');
        }
    }

    // 主图默认最大长边
    $maxLongEdge   = 2560;
    $jpegQuality   = 82;
    $webpQuality   = 82;

    // 首页大图（hero_covers）采用更温和的压缩策略：
    // 不主动缩小分辨率，仅做较高质量重压缩，避免首页大图出现明显损失。
    if ($relativePath !== '' && strpos($relativePath, 'hero_covers/') === 0) {
        $maxLongEdge = 0;   // 0 表示不限制长边，仅依据原图尺寸
        $jpegQuality = 88;
        $webpQuality = 88;
    }

    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    if ($srcW <= 0 || $srcH <= 0) {
        return;
    }

    $scale = 1.0;
    $longEdge = max($srcW, $srcH);
    if ($maxLongEdge > 0 && $longEdge > $maxLongEdge) {
        $scale = $maxLongEdge / $longEdge;
    }

    $dstW = (int)round($srcW * $scale);
    $dstH = (int)round($srcH * $scale);

    // 使用 GD 做基础压缩，尽量保持简单稳定
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = @imagecreatefromjpeg($absolutePath);
            break;
        case 'image/png':
            $srcImg = @imagecreatefrompng($absolutePath);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $srcImg = @imagecreatefromwebp($absolutePath);
            } else {
                $srcImg = null;
            }
            break;
        default:
            $srcImg = null;
    }
    if (!$srcImg) {
        return;
    }

    // 若无需缩放，仅重写压缩质量
    if ($scale === 1.0) {
        $dstImg = $srcImg;
    } else {
        $dstImg = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    }

    // 统一质量策略：JPEG/WebP 质量稍微调高，兼顾画质与体积
    if ($mime === 'image/jpeg') {
        @imagejpeg($dstImg, $absolutePath, $jpegQuality);
    } elseif ($mime === 'image/png') {
        // PNG 使用压缩级别（0-9），这里取中间值，并尝试保持透明
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        @imagepng($dstImg, $absolutePath, 6);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        @imagewebp($dstImg, $absolutePath, $webpQuality);
    }

    // WebP 副本：与图片压缩开关保持一致（统一由 image_optimize_enabled 控制）
    $webpEnabled = $enabled;
    if ((string)$webpEnabled === '1' && function_exists('imagewebp')) {
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $pathInfo = pathinfo($absolutePath);
            if (!empty($pathInfo['dirname']) && !empty($pathInfo['filename'])) {
                $webpPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.webp';
                @imagewebp($dstImg, $webpPath, $webpQuality);
            }
        }
    }

    // 为相册图片额外生成缩略图：/uploads/albums/{id}/thumbs/{filename}
    // - 仅在图片位于 uploads/albums/ 目录下时启用
    // - 长边约 480px，用于瀑布流 / 列表等场景，减轻前端加载压力
    if ($relativePath !== '' && strpos($relativePath, 'albums/') === 0) {
        // 缩略图略微放大长边，提高在桌面端瀑布流中的清晰度
        $thumbMaxLongEdge = 640;
        $longEdge         = max($srcW, $srcH);
        $thumbScale       = 1.0;
        if ($longEdge > $thumbMaxLongEdge) {
            $thumbScale = $thumbMaxLongEdge / $longEdge;
        }

        $thumbW = (int) round($srcW * $thumbScale);
        $thumbH = (int) round($srcH * $thumbScale);

        $dirName   = dirname($absolutePath);
        $fileName  = basename($absolutePath);
        $thumbDir  = $dirName . DIRECTORY_SEPARATOR . 'thumbs';
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $fileName;

        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        // 当无需缩放时，直接复制压缩后的主图作为缩略图，避免额外开销
        if ($thumbScale === 1.0) {
            if (is_file($absolutePath)) {
                @copy($absolutePath, $thumbPath);
            }

            // 为缩略图生成 WebP 副本（仅在启用且支持时）
            if ((string)$webpEnabled === '1' && function_exists('imagewebp')) {
                $pi = pathinfo($thumbPath);
                if (!empty($pi['dirname']) && !empty($pi['filename'])) {
                    $ext = strtolower($pi['extension'] ?? '');
                    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                        $thumbWebpPath = $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename'] . '.webp';
                        // 从缩略图文件重新解码一遍，避免依赖上层资源
                        $thumbSrc = null;
                        if ($ext === 'jpg' || $ext === 'jpeg') {
                            $thumbSrc = @imagecreatefromjpeg($thumbPath);
                        } elseif ($ext === 'png') {
                            $thumbSrc = @imagecreatefrompng($thumbPath);
                        }
                        if ($thumbSrc) {
                            @imagewebp($thumbSrc, $thumbWebpPath, 82);
                            imagedestroy($thumbSrc);
                        }
                    }
                }
            }
        } else {
            $thumbImg = imagecreatetruecolor($thumbW, $thumbH);

            if ($mime === 'image/png') {
                imagealphablending($thumbImg, false);
                imagesavealpha($thumbImg, true);
            }

            imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);

            if ($mime === 'image/jpeg') {
                @imagejpeg($thumbImg, $thumbPath, 80);
            } elseif ($mime === 'image/png') {
                @imagepng($thumbImg, $thumbPath, 6);
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                @imagewebp($thumbImg, $thumbPath, 75);
            }

            // 为缩略图生成 WebP 副本（仅在启用且支持时）
            if ((string)$webpEnabled === '1' && function_exists('imagewebp')) {
                $pi = pathinfo($thumbPath);
                if (!empty($pi['dirname']) && !empty($pi['filename'])) {
                    $ext = strtolower($pi['extension'] ?? '');
                    if ($ext !== 'webp') {
                        $thumbWebpPath = $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename'] . '.webp';
                        @imagewebp($thumbImg, $thumbWebpPath, 82);
                    }
                }
            }

            imagedestroy($thumbImg);
        }
    }

    if ($dstImg !== $srcImg) {
        imagedestroy($dstImg);
    }
    imagedestroy($srcImg);
}

/**
 * 删除上传目录下的文件
 */
function deleteFile($path) {
    $filepath = UPLOAD_DIR . ltrim($path, '/');
    $deleted  = false;

    if (file_exists($filepath)) {
        $deleted = @unlink($filepath);
    }

    // 同时尝试删除同名的 WebP 文件（如果存在），避免残留
    $info = pathinfo($filepath);
    if (!empty($info['dirname']) && !empty($info['filename'])) {
        $ext = strtolower($info['extension'] ?? '');
        if ($ext !== 'webp') {
            $webpPath = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.webp';
            if (is_file($webpPath)) {
                @unlink($webpPath);
            }
        }
    }

    return $deleted;
}

/**
 * 从 HTML 中提取所有 uploads 下的图片/视频相对路径
 * 返回类似 ["articles/xxx.jpg", "articles/videos/yyy.mp4"] 的数组
 */
function extract_upload_paths_from_html(string $html): array {
    $html = (string) $html;
    if ($html === '') {
        return [];
    }

    $paths = [];

    // 粗略匹配所有带 uploads 路径的图片/视频 src
    if (preg_match_all(
        '#src=("|\')([^"\']*uploads/[^"\']+\.(?:jpg|jpeg|png|gif|webp|mp4|webm|ogg))\1#i',
        $html,
        $m
    )) {
        foreach ($m[2] as $url) {
            // 去掉域名，仅保留 /uploads/... 或 uploads/...
            $p = preg_replace('#^https?://[^/]+/#i', '/', $url);
            $p = preg_replace('#^//[^/]+/#', '/', $p);
            $p = ltrim($p, '/');
            if (strpos($p, 'uploads/') !== 0) {
                continue;
            }
            $relative = substr($p, strlen('uploads/'));
            $relative = ltrim($relative, '/');
            if ($relative !== '') {
                $paths[] = $relative;
            }
        }
    }

    return array_values(array_unique($paths));
}

/**
 * 如果某个上传文件在其他文章中不再被引用，则物理删除
 *
 * @param string $relativePath 相对于 uploads/ 的路径，例如 articles/xxx.jpg
 * @param int    $currentArticleId 当前文章 ID，用于排除自身（为 0 时不排除）
 */
function delete_upload_file_if_unused(string $relativePath, int $currentArticleId = 0): void {
    $relativePath = ltrim($relativePath, '/');
    if ($relativePath === '') {
        return;
    }

    if (!class_exists('Database')) {
        // 没有数据库环境时，直接按单篇引用删除
        deleteFile($relativePath);
        return;
    }

    try {
        $db = Database::getInstance();
    } catch (Exception $e) {
        // 无法获取数据库实例时不做复杂检查，避免影响主流程
        deleteFile($relativePath);
        return;
    }

    $needle = '%uploads/' . $relativePath . '%';

    // 检查其它文章正文是否仍引用该文件
    try {
        if ($currentArticleId > 0) {
            $row = $db->fetch(
                "SELECT COUNT(*) AS c
                 FROM articles
                 WHERE id <> :article_id AND status != 'deleted' AND content LIKE :needle",
                [
                    'article_id' => $currentArticleId,
                    'needle'     => $needle,
                ]
            );
        } else {
            $row = $db->fetch(
                "SELECT COUNT(*) AS c
                 FROM articles
                 WHERE status != 'deleted' AND content LIKE :needle",
                [
                    'needle' => $needle,
                ]
            );
        }

        $countArticles = $row ? (int) $row['c'] : 0;
        if ($countArticles > 0) {
            return;
        }
    } catch (Exception $e) {
        // 查询失败时，为避免误删，先不删除文件
        return;
    }

    // 检查其它文章块是否仍引用该文件
    try {
        if ($currentArticleId > 0) {
            $row = $db->fetch(
                "SELECT COUNT(*) AS c
                 FROM article_blocks
                 WHERE article_id <> :article_id AND html LIKE :needle",
                [
                    'article_id' => $currentArticleId,
                    'needle'     => $needle,
                ]
            );
        } else {
            $row = $db->fetch(
                "SELECT COUNT(*) AS c
                 FROM article_blocks
                 WHERE html LIKE :needle",
                [
                    'needle' => $needle,
                ]
            );
        }

        $countBlocks = $row ? (int) $row['c'] : 0;
        if ($countBlocks > 0) {
            return;
        }
    } catch (Exception $e) {
        // 查询失败时，为避免误删，先不删除文件
        return;
    }

    // 确认没有其它引用后，再物理删除
    deleteFile($relativePath);
}

/**
 * 兼容旧数据与新数据的上传文件 URL
 * - 旧数据可能已存完整 URL 或包含 /uploads/ 前缀
 * - 新数据存相对路径（如 albums/1/xxx.jpg）
 * - 兼容 album_images 等表中可能存在的 NULL 值
 */
function upload_url(?string $path): string {
    if ($path === null) {
        return '';
    }

    $path = trim($path);
    if ($path === '') {
        return '';
    }

    // 已经是 http/https 完整地址，直接返回
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // 以 /uploads/ 开头：补上域名
    if (strpos($path, '/uploads/') === 0) {
        return BASE_URL . $path;
    }

    // 以 uploads/ 开头：补上 / 和域名
    if (strpos($path, 'uploads/') === 0) {
        return BASE_URL . '/' . $path;
    }

    // 其它情况：按相对路径处理
    return UPLOAD_URL . ltrim($path, '/');
}

/**
 * 获取当前允许的最大上传大小（字节）
 * - 默认 15MB
 * - 系统设置可配置 max_upload_size_mb，范围 1~50MB
 * - 不超过服务器 upload_max_filesize / post_max_size
 */
function get_max_upload_size_bytes(): int {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $defaultMb = 15;
    $maxMb     = 50;

    $defaultBytes = $defaultMb * 1024 * 1024;
    $hardCap      = defined('MAX_FILE_SIZE') ? (int) MAX_FILE_SIZE : $defaultBytes;

    // 服务器 php.ini 限制
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $iniKey) {
        $val = ini_get($iniKey);
        if ($val !== false) {
            $limits[] = parse_php_size_to_bytes($val);
        }
    }
    $serverLimit = !empty($limits) ? min($limits) : ($maxMb * 1024 * 1024);

    $effectiveHardCap = min(max($hardCap, $defaultBytes), $serverLimit);

    // 从 settings 读取配置
    try {
        if (class_exists('Database')) {
            $db  = Database::getInstance();
            $row = $db->fetch("SELECT value FROM settings WHERE `key` = 'max_upload_size_mb' LIMIT 1");
            if ($row && isset($row['value']) && is_numeric($row['value'])) {
                $mb = (int) $row['value'];
                if ($mb >= 1 && $mb <= $maxMb) {
                    $bytes = $mb * 1024 * 1024;
                    $cached = min($bytes, $effectiveHardCap);
                    return $cached;
                }
            }
        }
    } catch (Throwable $e) {
        // 读取失败时退回默认
    }

    $cached = $effectiveHardCap;
    return $cached;
}

/**
 * 将 php.ini 风格的尺寸（如 8M, 2G）转换为字节数
 */
function parse_php_size_to_bytes(string $size): int {
    $size = trim($size);
    if ($size === '') {
        return 0;
    }

    $unit  = strtolower(substr($size, -1));
    $value = (float) $size;

    switch ($unit) {
        case 'g':
            $value *= 1024;
            // no break
        case 'm':
            $value *= 1024;
            // no break
        case 'k':
            $value *= 1024;
    }

    return (int) $value;
}

/**
 * 将时间转成“多久之前”的中文描述
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff      = time() - $timestamp;

    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' 分钟前';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' 小时前';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . ' 天前';
    }
    return formatDate($datetime, 'Y-m-d');
}

/**
 * 获取客户端 IP（简单实现）
 */
function getClientIp(): string {
    // 默认只信任 REMOTE_ADDR，避免攻击者伪造 X-Forwarded-For 等头绕过防爆破/限流
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remoteIp && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
        return $remoteIp;
    }

    // 如确实部署在可信反向代理之后，可通过环境变量显式开启对代理头的信任
    // 例如在 nginx / php-fpm 所在环境设置：TRUST_PROXY_IP_HEADERS=1
    if (getenv('TRUST_PROXY_IP_HEADERS') === '1') {
        $keys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
        ];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', $_SERVER[$key]);
                $ip     = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }

    return '0.0.0.0';
}

/**
 * 本地 IP 归属地解析（可选依赖）
 *
 * 优先使用本地 IP 库（如 ip2region），若未配置则返回空字符串。
 * - 你可以将 ip2region 的数据库文件放在 /database/ip2region.xdb
 * - 并在 /core 目录下放置对应的 PHP 解析类（例如 Ip2Region / XdbSearcher 等）
 * - 本函数会在检测到可用类与数据文件时自动调用并返回「国家 · 省份 · 城市」形式的文本
 */
function get_ip_location_local(string $ip): string {
    $ip = trim($ip);
    if ($ip === '' || $ip === '0.0.0.0') {
        return '';
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '';
    }

    // 内网 / 本地地址统一标记为「内网 IP」
    if (strpos($ip, '10.') === 0 ||
        strpos($ip, '192.168.') === 0 ||
        strpos($ip, '127.') === 0 ||
        preg_match('#^172\.(1[6-9]|2[0-9]|3[0-1])\.#', $ip)) {
        return '内网 IP';
    }

    $dbPath = dirname(__DIR__) . '/database/ip2region.xdb';
    if (!is_file($dbPath)) {
        return '';
    }

    // 支持多种常见的 ip2region PHP 封装类，如：
    // - Ip2Region（旧版）
    // - XdbSearcher（新版 XDB）
    // 若不存在对应类，则返回空字符串，由站长自行按需引入库文件。

    // 新版 ip2region XDB 官方 PHP Binding：ip2region\xdb\Searcher（IPv4）
    if (!class_exists('\ip2region\xdb\Searcher') && is_file(__DIR__ . '/Searcher.class.php')) {
        require_once __DIR__ . '/Searcher.class.php';
    }
    if (class_exists('\ip2region\xdb\Searcher') && class_exists('\ip2region\xdb\IPv4')) {
        try {
            static $xdbSearcher = null;
            if ($xdbSearcher === null) {
                $version      = \ip2region\xdb\IPv4::default();
                $xdbSearcher  = \ip2region\xdb\Searcher::newWithFileOnly($version, $dbPath);
            }
            $region = $xdbSearcher->search($ip);
            return normalize_ip_region_string($region);
        } catch (Throwable $e) {
            // 忽略解析失败，继续尝试旧版
        }
    }

    // 旧版 ip2region：Ip2Region->btreeSearch($ip) / ->binarySearch($ip)
    if (!class_exists('Ip2Region') && is_file(__DIR__ . '/Ip2Region.php')) {
        require_once __DIR__ . '/Ip2Region.php';
    }
    if (class_exists('Ip2Region')) {
        try {
            static $ip2region = null;
            if ($ip2region === null) {
                $ip2region = new Ip2Region($dbPath);
            }

            $res    = null;
            $region = '';
            if (method_exists($ip2region, 'btreeSearch')) {
                $res = $ip2region->btreeSearch($ip);
            } elseif (method_exists($ip2region, 'binarySearch')) {
                $res = $ip2region->binarySearch($ip);
            } elseif (method_exists($ip2region, 'search')) {
                $res = $ip2region->search($ip);
            }

            if (is_array($res) && isset($res['region'])) {
                $region = $res['region'];
            } elseif (is_string($res)) {
                $region = $res;
            }

            return normalize_ip_region_string($region);
        } catch (Throwable $e) {
            return '';
        }
    }

    return '';
}

/**
 * 将 ip2region 等库返回的 region 字符串，规整为「国家 · 省份 · 城市」形式
 */
function normalize_ip_region_string(?string $region): string {
    $region = trim((string) $region);
    if ($region === '' || $region === '0|0|0|0|0') {
        return '';
    }

    // ip2region 默认格式：country|region|province|city|isp
    $parts = explode('|', $region);
    $parts = array_map('trim', $parts);

    // 去掉无意义的 "0" 与常见运营商/网络类型（电信、移动、联通等）
    $names = [];
    $ispPatterns = [
        '电信', '联通', '移动', '铁通', '网通', '广电', '有线宽带', '宽带',
        '长城宽带', '鹏博士', '教育网', '联通宽带', '电信宽带', '移动宽带',
        '内网IP', '内网 IP',
    ];

    foreach ($parts as $idx => $part) {
        if ($part === '' || $part === '0') {
            continue;
        }

        // 跳过明显是运营商/网络类型的字段
        $isIsp = false;
        foreach ($ispPatterns as $kw) {
            if (mb_strpos($part, $kw) !== false) {
                $isIsp = true;
                break;
            }
        }
        if ($isIsp) {
            continue;
        }

        // 前几段一般为 国家 / 地区 / 省份 / 城市
        if ($idx <= 4) {
            $names[] = $part;
        }
    }

    if (empty($names)) {
        return '';
    }

    // 常见冗余清理：比如 "中国|中国|广东省|深圳市" -> "中国 · 广东省 · 深圳市"
    $names = array_values(array_unique($names));

    return implode(' · ', $names);
}

/**
 * CSRF：获取 / 生成全局 token
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF：输出隐藏字段 HTML
 */
function csrf_field(): string {
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * CSRF：生成用于 GET 请求的查询字符串片段
 * 示例：...?id=1&<?php echo csrf_query(); ?>
 */
function csrf_query(): string {
    return '_token=' . urlencode(csrf_token());
}

/**
 * CSRF：校验 token 是否有效
 */
function csrf_verify(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF：在处理 POST 前强制校验，失败则直接退出
 */
function require_csrf(): void {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify($token)) {
        http_response_code(400);
        exit('表单已过期或来源异常，请刷新页面后重试。');
    }
}

/**
 * 一次性表单 token：为指定表单 key 生成 / 获取 token
 * 用来防止重复提交（幂等控制）
 */
function form_once_token(string $formKey): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['form_once']) || !is_array($_SESSION['form_once'])) {
        $_SESSION['form_once'] = [];
    }
    if (empty($_SESSION['form_once'][$formKey])) {
        $_SESSION['form_once'][$formKey] = bin2hex(random_bytes(16));
    }
    return $_SESSION['form_once'][$formKey];
}

/**
 * 校验并消费一次性表单 token
 * 通过时返回 true，并删除对应 token
 */
function verify_and_consume_form_once_token(string $formKey, ?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['form_once'][$formKey]) || !is_string($token)) {
        return false;
    }
    $sessionToken = $_SESSION['form_once'][$formKey];
    $ok           = hash_equals($sessionToken, $token);
    unset($_SESSION['form_once'][$formKey]);
    return $ok;
}

/**
 * 将事件图标关键字映射到 Font Awesome 6 的图标名
 */
function event_icon_class(string $icon): string {
    $icon = trim($icon) !== '' ? trim($icon) : 'heart';

    $map = [
        'heart'     => 'heart',          // fa-heart
        'star'      => 'star',           // fa-star
        'gift'      => 'gift',           // fa-gift
        'cake'      => 'cake-candles',   // fa-cake-candles
        'ring'      => 'ring',           // fa-ring
        'heartbeat' => 'heart-pulse',    // fa-heart-pulse
    ];

    return $map[$icon] ?? 'heart';
}

/**
 * 获取网站的情侣双方用户信息（无需登录）
 * 返回数组：['user1' => [...], 'user2' => [...]]
 * 用于前端计算共创标记，不依赖当前登录状态
 */
function get_couple_users(): array {
    static $cached = null;
    
    if ($cached !== null) {
        return $cached;
    }
    
    try {
        if (!class_exists('Database')) {
            return ['user1' => null, 'user2' => null];
        }
        
        $db = Database::getInstance();
        
        $user1 = $db->fetch(
            "SELECT * FROM users WHERE role = 'user1' AND status = 'active' LIMIT 1"
        );
        
        $user2 = $db->fetch(
            "SELECT * FROM users WHERE role = 'user2' AND status = 'active' LIMIT 1"
        );
        
        $cached = [
            'user1' => $user1 ?: null,
            'user2' => $user2 ?: null
        ];
        
        return $cached;
    } catch (Throwable $e) {
        return ['user1' => null, 'user2' => null];
    }
}

/**
 * 最佳努力的数据库结构迁移：为老版本补充新字段
 * - articles.edit_mode
 * - article_blocks.speaker
 */
function migrate_schema_if_needed(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!class_exists('Database')) {
        return;
    }

    // Avoid rerunning dozens of SHOW/ALTER/CREATE statements on every PHP
    // request, including each high-frequency watch poll.
    $schemaVersion = '20260721-02';
    $runtimeDir = dirname(ROOT_PATH) . DIRECTORY_SEPARATOR . 'runtime';
    $markerPath = $runtimeDir . DIRECTORY_SEPARATOR . 'schema-version';
    $lockPath = $runtimeDir . DIRECTORY_SEPARATOR . 'schema-migration.lock';
    if (is_file($markerPath) && trim((string)@file_get_contents($markerPath)) === $schemaVersion) {
        return;
    }
    if (!is_dir($runtimeDir)) {
        @mkdir($runtimeDir, 0775, true);
    }
    $migrationLock = @fopen($lockPath, 'c');
    if ($migrationLock) {
        @flock($migrationLock, LOCK_EX);
        if (is_file($markerPath) && trim((string)@file_get_contents($markerPath)) === $schemaVersion) {
            @flock($migrationLock, LOCK_UN);
            @fclose($migrationLock);
            return;
        }
    }
    $migrationSucceeded = false;
    try {
        $db = Database::getInstance();

        // 为 articles 表新增 edit_mode 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `articles`
                ADD COLUMN `edit_mode` enum('full','blocks') NOT NULL DEFAULT 'full' COMMENT '编辑模式：full=整篇富文本，blocks=块级对话'
            ");
        } catch (Throwable $e) {
            // 字段已存在或数据库不支持该写法时忽略
        }

        // 扩展 articles.edit_mode 支持 chat 模式（若已存在则尝试修改枚举）
        try {
            $db->query("
                ALTER TABLE `articles`
                MODIFY COLUMN `edit_mode` enum('full','blocks','chat') NOT NULL DEFAULT 'full' COMMENT '编辑模式：full=整篇富文本，blocks=块级对话，chat=聊天创作'
            ");
        } catch (Throwable $e) {
            // 数据库不支持该写法或已经是目标枚举时忽略
        }

        // 为 article_blocks 表新增 speaker 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `article_blocks`
                ADD COLUMN `speaker` enum('male','female','system') DEFAULT NULL COMMENT '说话人：男主/女主/系统'
            ");
        } catch (Throwable $e) {
            // 字段已存在或数据库不支持该写法时忽略
        }

        // 为 albums 表新增 keep_original_quality 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `albums`
                ADD COLUMN `keep_original_quality` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否保留原始画质（0=默认压缩，1=尽量不压缩主图）'
            ");
        } catch (Throwable $e) {
            // 字段已存在或数据库不支持该写法时忽略
        }

        // 为 album_images 表新增 is_optimized / skip_optimize 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `album_images`
                ADD COLUMN `is_optimized` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已按当前规则压缩'
            ");
        } catch (Throwable $e) {
            // 已存在时忽略
        }
        try {
            $db->query("
                ALTER TABLE `album_images`
                ADD COLUMN `skip_optimize` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否永久跳过主图压缩'
            ");
        } catch (Throwable $e) {
            // 已存在时忽略
        }

        // 确保相册视频表存在（用于为相册单独管理视频资源）
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS `album_videos` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `album_id` int(11) NOT NULL COMMENT '相册ID',
                    `video_path` varchar(255) NOT NULL COMMENT '视频路径',
                    `poster_path` varchar(255) DEFAULT NULL COMMENT '封面图片路径',
                    `description` varchar(255) DEFAULT NULL COMMENT '视频描述',
                    `uploader_id` int(11) DEFAULT NULL COMMENT '上传用户ID',
                    `original_video_path` varchar(255) DEFAULT NULL COMMENT '原始上传视频路径（转码前）',
                    `is_transcoded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已按统一规则转码',
                    `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序值',
                    `created_at` datetime NOT NULL COMMENT '创建时间',
                    PRIMARY KEY (`id`),
                    KEY `album_id` (`album_id`),
                    KEY `sort_order` (`sort_order`),
                    KEY `uploader_id` (`uploader_id`),
                    KEY `is_transcoded` (`is_transcoded`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册视频表';
            ");
        } catch (Throwable $e) {
            // 表已存在或执行失败时忽略
        }

        // 为 album_videos 补充 uploader_id 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `album_videos`
                ADD COLUMN `uploader_id` int(11) DEFAULT NULL COMMENT '上传用户ID'
            ");
        } catch (Throwable $e) {
            // 字段已存在或数据库不支持该写法时忽略
        }

        // 为 album_videos 补充 original_video_path 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `album_videos`
                ADD COLUMN `original_video_path` varchar(255) DEFAULT NULL COMMENT '原始上传视频路径（转码前）'
            ");
        } catch (Throwable $e) {
            // 已存在时忽略
        }

        // 为 album_videos 补充 is_transcoded 字段（若不存在）
        try {
            $db->query("
                ALTER TABLE `album_videos`
                ADD COLUMN `is_transcoded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已按统一规则转码'
            ");
        } catch (Throwable $e) {
            // 已存在时忽略
        }

        // 确保文章视频表存在（用于为文章单独管理视频资源）
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS `article_videos` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `article_id` int(11) NOT NULL COMMENT '文章ID',
                    `video_path` varchar(255) NOT NULL COMMENT '视频路径',
                    `original_video_path` varchar(255) DEFAULT NULL COMMENT '原始上传视频路径（转码前）',
                    `poster_path` varchar(255) DEFAULT NULL COMMENT '封面图片路径',
                    `description` varchar(255) DEFAULT NULL COMMENT '视频描述',
                    `uploader_id` int(11) DEFAULT NULL COMMENT '上传用户ID',
                    `is_transcoded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已按统一规则转码',
                    `created_at` datetime NOT NULL COMMENT '创建时间',
                    PRIMARY KEY (`id`),
                    KEY `article_id` (`article_id`),
                    KEY `uploader_id` (`uploader_id`),
                    KEY `is_transcoded` (`is_transcoded`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章视频表';
            ");
        } catch (Throwable $e) {
            // 表已存在或执行失败时忽略
        }

        // 确保评论 IP 黑名单表存在
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS `comment_ip_blacklist` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `ip` varchar(45) NOT NULL COMMENT 'IP地址',
                    `reason` varchar(255) DEFAULT NULL COMMENT '拉黑原因',
                    `expires_at` datetime DEFAULT NULL COMMENT '过期时间，NULL 表示永久',
                    `created_at` datetime NOT NULL COMMENT '创建时间',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `ip` (`ip`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论IP黑名单';
            ");
        } catch (Throwable $e) {
            // 表已存在或执行失败时忽略
        }

        // 确保评论尝试记录表存在
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS `comment_attempts` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `ip` varchar(45) DEFAULT NULL,
                    `created_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_ip_time` (`ip`,`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论尝试记录';
            ");
        } catch (Throwable $e) {
            // 表已存在或执行失败时忽略
        }

        // 为 comments 表补充游客信息字段（若不存在）
        try {
            $row = $db->fetch("SHOW COLUMNS FROM `comments` LIKE 'guest_nickname'");
            if (!$row) {
                $db->query("ALTER TABLE `comments` ADD COLUMN `guest_nickname` varchar(100) DEFAULT NULL COMMENT '访客昵称' AFTER `user_id`");
            }
        } catch (Throwable $e) {
            // 字段已存在或执行失败时忽略
        }

        try {
            $row = $db->fetch("SHOW COLUMNS FROM `comments` LIKE 'guest_avatar'");
            if (!$row) {
                $db->query("ALTER TABLE `comments` ADD COLUMN `guest_avatar` varchar(255) DEFAULT NULL COMMENT '访客头像' AFTER `guest_nickname`");
            }
        } catch (Throwable $e) {
            // 字段已存在或执行失败时忽略
        }

        try {
            $row = $db->fetch("SHOW COLUMNS FROM `comments` LIKE 'guest_qq'");
            if (!$row) {
                $db->query("ALTER TABLE `comments` ADD COLUMN `guest_qq` varchar(20) DEFAULT NULL COMMENT '访客QQ' AFTER `guest_avatar`");
            }
        } catch (Throwable $e) {
            // 字段已存在或执行失败时忽略
        }

        // 为 comments 表补充 IP 与归属地字段（若不存在）
        try {
            $row = $db->fetch("SHOW COLUMNS FROM `comments` LIKE 'ip'");
            if (!$row) {
                $db->query("ALTER TABLE `comments` ADD COLUMN `ip` varchar(45) DEFAULT NULL COMMENT '评论IP' AFTER `guest_qq`");
            }
        } catch (Throwable $e) {
            // 字段已存在或执行失败时忽略
        }

        try {
            $row = $db->fetch("SHOW COLUMNS FROM `comments` LIKE 'location'");
            if (!$row) {
                $db->query("ALTER TABLE `comments` ADD COLUMN `location` varchar(255) DEFAULT NULL COMMENT 'IP归属地' AFTER `ip`");
            }
        } catch (Throwable $e) {
            // 字段已存在或执行失败时忽略
        }

        // withU 第一版扩展：账户设备、媒体库、同步观影和审核记录
        migrate_withu_v1($db);
        $migrationSucceeded = true;
    } catch (Throwable $e) {
        // 忽略迁移失败，保持主流程可用
    }
    if ($migrationSucceeded) {
        @file_put_contents($markerPath, $schemaVersion, LOCK_EX);
    }
    if ($migrationLock) {
        @flock($migrationLock, LOCK_UN);
        @fclose($migrationLock);
    }
}

/**
 * withU 业务扩展迁移。
 * 所有语句保持 MySQL 5.7 / 8.x 兼容，并且可重复执行。
 */
function migrate_withu_v1($db): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tables = [
        "CREATE TABLE IF NOT EXISTS `couple_invites` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `inviter_id` int(11) NOT NULL,
            `invite_code` varchar(32) NOT NULL,
            `invite_token_hash` varchar(64) NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `invitee_id` int(11) DEFAULT NULL,
            `expires_at` datetime NOT NULL,
            `accepted_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_invite_code` (`invite_code`),
            UNIQUE KEY `uk_invite_token` (`invite_token_hash`),
            KEY `idx_inviter_status` (`inviter_id`,`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='情侣邀请';",
        "CREATE TABLE IF NOT EXISTS `trusted_devices` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `device_token_hash` varchar(64) NOT NULL,
            `device_name` varchar(120) DEFAULT NULL,
            `last_ip` varchar(45) DEFAULT NULL,
            `last_seen_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `revoked_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_device_token` (`device_token_hash`),
            KEY `idx_device_user` (`user_id`,`revoked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='信任设备';",
        "CREATE TABLE IF NOT EXISTS `media_library` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `source_key` varchar(500) NOT NULL,
            `source_url` varchar(1000) DEFAULT NULL,
            `direct_url` text DEFAULT NULL,
            `file_name` varchar(255) NOT NULL,
            `series_key` varchar(255) DEFAULT NULL,
            `series_name` varchar(255) DEFAULT NULL,
            `season_number` int(11) DEFAULT NULL,
            `episode_number` int(11) DEFAULT NULL,
            `episode_title` varchar(255) DEFAULT NULL,
            `video_codec` varchar(30) DEFAULT NULL,
            `audio_codec` varchar(30) DEFAULT NULL,
            `browser_playback` varchar(20) NOT NULL DEFAULT 'unknown',
            `file_size` bigint(20) unsigned DEFAULT NULL,
            `file_md5` varchar(64) DEFAULT NULL,
            `file_etag` varchar(255) DEFAULT NULL,
            `mime_type` varchar(100) DEFAULT NULL,
            `duration_ms` bigint(20) unsigned DEFAULT NULL,
            `width` int(11) DEFAULT NULL,
            `height` int(11) DEFAULT NULL,
            `resolution` varchar(40) DEFAULT NULL,
            `tags` varchar(500) DEFAULT NULL,
            `douban_id` varchar(40) DEFAULT NULL,
            `rating` decimal(3,1) DEFAULT NULL,
            `cast_names` text DEFAULT NULL,
            `summary` text DEFAULT NULL,
            `cover_url` text DEFAULT NULL,
            `intro_start_ms` bigint(20) unsigned DEFAULT NULL,
            `intro_end_ms` bigint(20) unsigned DEFAULT NULL,
            `recognition_status` varchar(20) NOT NULL DEFAULT 'pending',
            `recognition_source` varchar(30) DEFAULT NULL,
            `recognized_at` datetime DEFAULT NULL,
            `last_scanned_at` datetime NOT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_media_source` (`source_key`),
            KEY `idx_media_md5` (`file_md5`),
            KEY `idx_media_status` (`recognition_status`),
            KEY `idx_media_name` (`file_name`),
            KEY `idx_media_status_updated` (`recognition_status`,`updated_at`,`id`),
            KEY `idx_media_series_episode` (`series_key`(191),`recognition_status`,`season_number`,`episode_number`,`id`),
            KEY `idx_media_series_name` (`series_name`(191)),
            KEY `idx_media_resolution` (`resolution`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OpenList媒体库';",
        "CREATE TABLE IF NOT EXISTS `watch_rooms` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `room_code` varchar(32) NOT NULL,
            `media_id` int(11) NOT NULL,
            `host_user_id` int(11) NOT NULL,
            `playback_state` varchar(20) NOT NULL DEFAULT 'paused',
            `current_position_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `speed` decimal(3,2) NOT NULL DEFAULT 1.00,
            `last_sync_at` datetime NOT NULL,
            `last_sync_unix_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `ended_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_room_code` (`room_code`),
            KEY `idx_room_media` (`media_id`),
            KEY `idx_room_host` (`host_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='同步观影房间';",
        "CREATE TABLE IF NOT EXISTS `watch_room_members` (
            `room_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `joined_at` datetime NOT NULL,
            `last_seen_at` datetime NOT NULL,
            `left_at` datetime DEFAULT NULL,
            PRIMARY KEY (`room_id`,`user_id`),
            KEY `idx_member_last_seen` (`user_id`,`last_seen_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='观影房间成员';",
        "CREATE TABLE IF NOT EXISTS `watch_events` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `room_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `event_type` varchar(30) NOT NULL,
            `position_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `speed` decimal(3,2) NOT NULL DEFAULT 1.00,
            `payload` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_watch_event_room` (`room_id`,`id`),
            KEY `idx_watch_event_user` (`user_id`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='观影同步事件';",
        "CREATE TABLE IF NOT EXISTS `watch_history` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `media_id` int(11) NOT NULL,
            `room_id` int(11) DEFAULT NULL,
            `started_at` datetime NOT NULL,
            `ended_at` datetime DEFAULT NULL,
            `watch_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `solo_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `together_duration_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `initiated_by` int(11) DEFAULT NULL,
            `last_position_ms` bigint(20) unsigned NOT NULL DEFAULT 0,
            `participants` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_history_media` (`media_id`,`started_at`),
            KEY `idx_history_room` (`room_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='观影历史';",
        "CREATE TABLE IF NOT EXISTS `moderation_events` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `target_type` varchar(30) NOT NULL,
            `target_id` bigint(20) DEFAULT NULL,
            `content` text DEFAULT NULL,
            `rule_result` varchar(20) NOT NULL DEFAULT 'allow',
            `ai_result` varchar(20) DEFAULT NULL,
            `risk_score` decimal(5,2) DEFAULT NULL,
            `reasons` text DEFAULT NULL,
            `review_status` varchar(20) NOT NULL DEFAULT 'pending',
            `reviewed_by` int(11) DEFAULT NULL,
            `review_note` varchar(500) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `reviewed_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_moderation_status` (`review_status`,`created_at`),
            KEY `idx_moderation_target` (`target_type`,`target_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容与安全审核记录';",
        "CREATE TABLE IF NOT EXISTS `weather_cache` (
            `id` int(11) NOT NULL AUTO_INCREMENT, `cache_key` varchar(120) NOT NULL,
            `latitude` decimal(10,7) NOT NULL, `longitude` decimal(10,7) NOT NULL,
            `payload` text NOT NULL, `expires_at` datetime NOT NULL, `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_weather_cache_key` (`cache_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='天气缓存';",
        "CREATE TABLE IF NOT EXISTS `travel_plans` (
            `id` int(11) NOT NULL AUTO_INCREMENT, `creator_id` int(11) NOT NULL,
            `destination` varchar(255) NOT NULL, `start_date` date DEFAULT NULL, `end_date` date DEFAULT NULL,
            `prompt` text DEFAULT NULL, `plan_payload` text NOT NULL, `ai_source` varchar(30) DEFAULT NULL,
            `created_at` datetime NOT NULL, `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`), KEY `idx_travel_creator` (`creator_id`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI旅行计划';",
        "CREATE TABLE IF NOT EXISTS `content_likes` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT, `actor_key` varchar(64) NOT NULL,
            `target_type` varchar(20) NOT NULL, `target_id` int(11) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_like_actor_target` (`actor_key`,`target_type`,`target_id`),
            KEY `idx_like_target` (`target_type`,`target_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游客与情侣点赞';"
    ];

    foreach ($tables as $sql) {
        try { $db->query($sql); } catch (Throwable $e) { /* 兼容旧库，继续迁移 */ }
    }

    $columns = [
        ['albums', 'layout_mode', "ALTER TABLE `albums` ADD COLUMN `layout_mode` varchar(20) NOT NULL DEFAULT 'grid' COMMENT '相册排版'"],
        ['albums', 'mask_type', "ALTER TABLE `albums` ADD COLUMN `mask_type` varchar(30) NOT NULL DEFAULT 'none' COMMENT '缩略图蒙版'"],
        ['albums', 'location_name', "ALTER TABLE `albums` ADD COLUMN `location_name` varchar(255) DEFAULT NULL COMMENT '相册地点'"],
        ['albums', 'latitude', "ALTER TABLE `albums` ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL COMMENT '纬度'"],
        ['albums', 'longitude', "ALTER TABLE `albums` ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL COMMENT '经度'"],
        ['album_images', 'location_name', "ALTER TABLE `album_images` ADD COLUMN `location_name` varchar(255) DEFAULT NULL COMMENT '图片地点'"],
        ['album_images', 'latitude', "ALTER TABLE `album_images` ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL COMMENT '图片纬度'"],
        ['album_images', 'longitude', "ALTER TABLE `album_images` ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL COMMENT '图片经度'"],
        ['album_images', 'location_visibility', "ALTER TABLE `album_images` ADD COLUMN `location_visibility` varchar(20) NOT NULL DEFAULT 'private' COMMENT '地点可见范围'"],
        ['users', 'last_login_at', "ALTER TABLE `users` ADD COLUMN `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间'"],
        ['media_library', 'series_key', "ALTER TABLE `media_library` ADD COLUMN `series_key` varchar(255) DEFAULT NULL COMMENT '剧集分组键'"],
        ['media_library', 'series_name', "ALTER TABLE `media_library` ADD COLUMN `series_name` varchar(255) DEFAULT NULL COMMENT '剧集名称'"],
        ['media_library', 'season_number', "ALTER TABLE `media_library` ADD COLUMN `season_number` int(11) DEFAULT NULL COMMENT '季数'"],
        ['media_library', 'episode_number', "ALTER TABLE `media_library` ADD COLUMN `episode_number` int(11) DEFAULT NULL COMMENT '集数'"],
        ['media_library', 'episode_title', "ALTER TABLE `media_library` ADD COLUMN `episode_title` varchar(255) DEFAULT NULL COMMENT '分集标题'"],
        ['media_library', 'video_codec', "ALTER TABLE `media_library` ADD COLUMN `video_codec` varchar(30) DEFAULT NULL COMMENT '视频编码'"],
        ['media_library', 'audio_codec', "ALTER TABLE `media_library` ADD COLUMN `audio_codec` varchar(30) DEFAULT NULL COMMENT '音频编码'"],
        ['media_library', 'browser_playback', "ALTER TABLE `media_library` ADD COLUMN `browser_playback` varchar(20) NOT NULL DEFAULT 'unknown' COMMENT '浏览器播放方式'"],
        ['watch_rooms', 'last_sync_unix_ms', "ALTER TABLE `watch_rooms` ADD COLUMN `last_sync_unix_ms` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `last_sync_at`"],
        ['watch_rooms', 'source', "ALTER TABLE `watch_rooms` ADD COLUMN `source` varchar(10) NOT NULL DEFAULT 'library' COMMENT '媒体来源: library/strm' AFTER `media_id`"],
        ['watch_rooms', 'source_episode', "ALTER TABLE `watch_rooms` ADD COLUMN `source_episode` int(11) NOT NULL DEFAULT 0 COMMENT 'strm 分集 id' AFTER `source`"],
    ];
    foreach ($columns as $column) {
        try {
            if (!$db->fetch("SHOW COLUMNS FROM `{$column[0]}` LIKE '{$column[1]}'")) {
                $db->query($column[2]);
            }
        } catch (Throwable $e) { /* 字段已经存在或无权限时忽略 */ }
    }

    // Match the high-frequency watch queries: room presence is filtered by
    // room, left_at and last_seen_at rather than by user first.
    $indexes = [
        ['watch_room_members', 'idx_member_room_presence', "ALTER TABLE `watch_room_members` ADD KEY `idx_member_room_presence` (`room_id`,`left_at`,`last_seen_at`)"],
        ['watch_history', 'idx_history_updated', "ALTER TABLE `watch_history` ADD KEY `idx_history_updated` (`updated_at`,`id`)"],
        ['media_library', 'idx_media_status_updated', "ALTER TABLE `media_library` ADD KEY `idx_media_status_updated` (`recognition_status`,`updated_at`,`id`)"],
        ['media_library', 'idx_media_series_episode', "ALTER TABLE `media_library` ADD KEY `idx_media_series_episode` (`series_key`(191),`recognition_status`,`season_number`,`episode_number`,`id`)"],
        ['media_library', 'idx_media_series_name', "ALTER TABLE `media_library` ADD KEY `idx_media_series_name` (`series_name`(191))"],
        ['media_library', 'idx_media_resolution', "ALTER TABLE `media_library` ADD KEY `idx_media_resolution` (`resolution`)"],
    ];
    foreach ($indexes as $index) {
        try {
            if (!$db->fetch("SHOW INDEX FROM `{$index[0]}` WHERE Key_name = '{$index[1]}'")) {
                $db->query($index[2]);
            }
        } catch (Throwable $e) { /* 索引已存在或旧库不允许修改时忽略 */ }
    }
    // Existing rooms predate the millisecond clock. Seed it from the legacy
    // datetime column so the first poll does not jump to Unix epoch.
    try {
        $db->query("UPDATE `watch_rooms` SET `last_sync_unix_ms` = UNIX_TIMESTAMP(`last_sync_at`) * 1000 WHERE `last_sync_unix_ms` = 0");
    } catch (Throwable $e) {
        error_log('[Schema] watch room clock backfill failed: ' . $e->getMessage());
    }

    $settings = [
        ['front_animation_enabled', '1', '前台花瓣与转场动效开关'],
        ['backend_animation_enabled', '0', '后台动效开关'],
        ['ai_moderation_enabled', '0', 'AI内容审核开关'],
        ['ai_api_endpoint', 'https://api.deepseek.com/chat/completions', 'DeepSeek兼容接口地址'],
        ['ai_api_key', '', 'AI接口密钥'],
        ['ai_model', 'deepseek-chat', 'AI模型名称'],
        ['media_ai_classify_enabled', '1', '影视采集启用 AI 分类和重复识别辅助'],
        ['openlist_webdav_url', '', 'OpenList网页目录地址'],
        ['openlist_webdav_username', '', 'OpenList账号用户名'],
        ['openlist_webdav_password', '', 'OpenList账号密码'],
        ['openlist_root_path', '/', 'OpenList媒体根目录'],
        ['tmdb_api_base', 'https://api.themoviedb.org/3', 'TMDb API 基础地址'],
        ['tmdb_read_access_token', '', 'TMDb API Read Access Token（仅服务端使用）'],
        ['douban_api_base', 'https://api.justoneapi.com', 'Just One API 基础地址'],
        ['douban_api_token', '', 'Just One API token'],
        ['douban_hot_tv_path', '/api/douban/get-recent-hot-tv/v1', '豆瓣近期热门电视剧接口路径'],
        ['douban_hot_movie_path', '/api/douban/get-recent-hot-movie/v1', '豆瓣近期热门电影接口路径'],
        ['theme_preset', 'sakura', '主站与后台主题预设'],
        ['theme_mode', 'light', '主题显示模式：固定白天模式'],
        ['theme_custom_primary', '', '自定义主题主色'],
        ['theme_custom_secondary', '', '自定义主题辅助色'],
        ['theme_custom_accent', '', '自定义主题强调色'],
        ['admin_ui_mode', 'current', '后台界面模式：current/apple'],
        ['watch_poll_interval_ms', '500', '一起看状态轮询间隔（毫秒）'],
        ['watch_sync_threshold_ms', '1000', '一起看同步校正阈值（毫秒）'],
        ['watch_presence_timeout_sec', '8', '一起看在线判定时间（秒）'],
        ['watch_heartbeat_interval_ms', '2500', '一起看心跳间隔（毫秒）'],
        ['watch_autoplay_enabled', '1', '一起看与个人观看默认自动播放'],
        ['player_default_speed', '1', '播放器默认倍速'],
        ['player_auto_next_enabled', '1', '播放器结束后自动播放下一集'],
    ];
    foreach ($settings as $setting) {
        try {
            $db->query("INSERT IGNORE INTO `settings` (`key`,`value`,`description`) VALUES (:k,:v,:d)", [
                'k' => $setting[0], 'v' => $setting[1], 'd' => $setting[2]
            ]);
        } catch (Throwable $e) { /* 设置表尚未可用时忽略 */ }
    }
}
