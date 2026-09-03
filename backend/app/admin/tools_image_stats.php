<?php
// 后台小工具：简单统计图片体积与相册带宽预估
// 根据不同操作返回 HTML 或 JSON
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/MediaTranscode.php';

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();

// 状态提示
$error   = '';
$success = '';

// 确保最新表结构（包含 is_optimized / skip_optimize）
if (function_exists('migrate_schema_if_needed')) {
    migrate_schema_if_needed();
}

$adminPage = 'tools_stats';
$adminNarrow = true;

// 每次批处理的最大条数，避免超时（用于相册缩略图补齐相关操作）
$batchLimit = 100;

// AJAX: 统计压缩占比
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stats') {
    header('Content-Type: application/json; charset=UTF-8');

    $excludeAlbums  = !empty($_POST['exclude_albums']) ? 1 : 0;
    $excludeImages  = !empty($_POST['exclude_images']) ? 1 : 0;

    try {
        // 可压缩图片：排除不压缩相册 / 图片
        $where = [];
        $params = [];

        if ($excludeAlbums) {
            $where[] = '(a.keep_original_quality = 0 OR a.keep_original_quality IS NULL)';
        }
        if ($excludeImages) {
            $where[] = 'ai.skip_optimize = 0';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $totalRow = $db->fetch("
            SELECT COUNT(*) AS c
            FROM album_images ai
            LEFT JOIN albums a ON ai.album_id = a.id
            $whereSql
        ", $params);
        $total = (int) ($totalRow['c'] ?? 0);

        $optimized = 0;
        if ($total > 0) {
            $whereOpt = $where;
            $whereOpt[] = 'ai.is_optimized = 1';
            $whereOptSql = 'WHERE ' . implode(' AND ', $whereOpt);

            $optRow = $db->fetch("
                SELECT COUNT(*) AS c
                FROM album_images ai
                LEFT JOIN albums a ON ai.album_id = a.id
                $whereOptSql
            ", $params);
            $optimized = (int) ($optRow['c'] ?? 0);
        }

        $notOptimized = max(0, $total - $optimized);

        echo json_encode([
            'success'       => true,
            'total'         => $total,
            'optimized'     => $optimized,
            'not_optimized' => $notOptimized,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => '统计失败：' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// AJAX: 统计相册视频转码情况
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'video_stats') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $totalRow = $db->fetch("SELECT COUNT(*) AS c FROM album_videos");
        $total = (int) ($totalRow['c'] ?? 0);

        $transcoded = 0;
        if ($total > 0) {
            $doneRow = $db->fetch("SELECT COUNT(*) AS c FROM album_videos WHERE is_transcoded = 1");
            $transcoded = (int) ($doneRow['c'] ?? 0);
        }

        $pending = max(0, $total - $transcoded);

        echo json_encode([
            'success'    => true,
            'total'      => $total,
            'transcoded' => $transcoded,
            'pending'    => $pending,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success'    => true,
            'total'      => 0,
            'transcoded' => 0,
            'pending'    => 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// AJAX: 统计文章视频转码情况
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'article_video_stats') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $totalRow = $db->fetch("SELECT COUNT(*) AS c FROM article_videos");
        $total = (int) ($totalRow['c'] ?? 0);

        $transcoded = 0;
        if ($total > 0) {
            $doneRow = $db->fetch("SELECT COUNT(*) AS c FROM article_videos WHERE is_transcoded = 1");
            $transcoded = (int) ($doneRow['c'] ?? 0);
        }

        $pending = max(0, $total - $transcoded);

        echo json_encode([
            'success'    => true,
            'total'      => $total,
            'transcoded' => $transcoded,
            'pending'    => $pending,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success'    => true,
            'total'      => 0,
            'transcoded' => 0,
            'pending'    => 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// AJAX: 相册视频一键转码（分批）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'video_transcode_batch') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!function_exists('shell_exec')) {
        echo json_encode([
            'success'   => true,
            'processed' => 0,
            'pending'   => 0,
            'message'   => '当前 PHP 环境禁用了 shell_exec，已跳过视频转码。',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : $batchLimit;

    try {
        $rows = $db->fetchAll(
            "SELECT id, album_id, video_path, original_video_path, is_transcoded
             FROM album_videos
             WHERE is_transcoded = 0 OR is_transcoded IS NULL
             ORDER BY id ASC
             LIMIT :limit",
            ['limit' => $limit]
        );
    } catch (Throwable $e) {
        echo json_encode([
            'success'   => true,
            'processed' => 0,
            'pending'   => 0,
            'message'   => '读取相册视频失败：' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $processed = 0;
    if ($rows) {
        foreach ($rows as $row) {
            $videoId   = (int) ($row['id'] ?? 0);
            $albumId   = (int) ($row['album_id'] ?? 0);
            if ($videoId <= 0 || $albumId <= 0) {
                continue;
            }

            $originalPath = $row['original_video_path'] ?: $row['video_path'];
            $originalPath = trim((string) $originalPath);
            if ($originalPath === '') {
                continue;
            }

            $videoAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($originalPath, '/');
            if (!is_file($videoAbs)) {
                continue;
            }

            $transcodedSubDir = 'albums/' . $albumId . '/videos/transcoded';
            $transcodedDirAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . trim($transcodedSubDir, '/');
            if (!is_dir($transcodedDirAbs)) {
                @mkdir($transcodedDirAbs, 0755, true);
            }
            if (!is_dir($transcodedDirAbs)) {
                continue;
            }

            $transcodedFile = 'video_' . $videoId . '_h264.mp4';
            $transcodedAbs  = $transcodedDirAbs . '/' . $transcodedFile;

            $ffmpeg = withu_binary_path('ffmpeg');
            if ($ffmpeg === '') continue;
            $cmd = withu_shell_arg($ffmpeg)
                . ' -y'
                . ' -i ' . escapeshellarg($videoAbs)
                . ' -c:v libx264 -preset medium -crf 23'
                . ' -c:a aac -b:a 128k'
                . ' -movflags +faststart '
                . escapeshellarg($transcodedAbs)
                . ' 2>&1';
            @shell_exec($cmd);

            if (!is_file($transcodedAbs)) {
                continue;
            }

            $newRelative = trim($transcodedSubDir, '/') . '/' . $transcodedFile;

            $updateData = [
                'video_path'    => $newRelative,
                'is_transcoded' => 1,
            ];
            if (empty($row['original_video_path'])) {
                $updateData['original_video_path'] = $originalPath;
            }

            try {
                $db->update('album_videos', $updateData, 'id = :id', ['id' => $videoId]);
                $processed++;
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    // 计算剩余未转码数量
    try {
        $pendingRow = $db->fetch(
            "SELECT COUNT(*) AS c
             FROM album_videos
             WHERE is_transcoded = 0 OR is_transcoded IS NULL"
        );
        $pending = (int) ($pendingRow['c'] ?? 0);
    } catch (Throwable $e) {
        $pending = 0;
    }

    echo json_encode([
        'success'   => true,
        'processed' => $processed,
        'pending'   => $pending,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: 文章视频一键转码（分批）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'article_video_transcode_batch') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!function_exists('shell_exec')) {
        echo json_encode([
            'success'   => true,
            'processed' => 0,
            'pending'   => 0,
            'message'   => '当前 PHP 环境禁用了 shell_exec，已跳过文章视频转码。',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : $batchLimit;

    try {
        $rows = $db->fetchAll(
            "SELECT id, article_id, video_path, original_video_path, is_transcoded
             FROM article_videos
             WHERE is_transcoded = 0 OR is_transcoded IS NULL
             ORDER BY id ASC
             LIMIT :limit",
            ['limit' => $limit]
        );
    } catch (Throwable $e) {
        echo json_encode([
            'success'   => true,
            'processed' => 0,
            'pending'   => 0,
            'message'   => '读取文章视频失败：' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $processed = 0;
    if ($rows) {
        foreach ($rows as $row) {
            $videoId   = (int) ($row['id'] ?? 0);
            $articleId = (int) ($row['article_id'] ?? 0);
            if ($videoId <= 0) {
                continue;
            }

            $originalPath = $row['original_video_path'] ?: $row['video_path'];
            $originalPath = trim((string) $originalPath);
            if ($originalPath === '') {
                continue;
            }

            $videoAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($originalPath, '/');
            if (!is_file($videoAbs)) {
                continue;
            }

            // 优先根据 article_id 分目录；若为 0，则回退到 uploads/articles/videos/transcoded
            if ($articleId > 0) {
                $transcodedSubDir = 'articles/' . $articleId . '/videos/transcoded';
            } else {
                $transcodedSubDir = 'articles/videos/transcoded';
            }
            $transcodedDirAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . trim($transcodedSubDir, '/');
            if (!is_dir($transcodedDirAbs)) {
                @mkdir($transcodedDirAbs, 0755, true);
            }
            if (!is_dir($transcodedDirAbs)) {
                continue;
            }

            // 以视频 ID 生成固定文件名，避免重复转码造成路径不一致
            $transcodedFile = 'article_video_' . $videoId . '_h264.mp4';
            $transcodedAbs  = $transcodedDirAbs . '/' . $transcodedFile;

            $ffmpeg = withu_binary_path('ffmpeg');
            if ($ffmpeg === '') continue;
            $cmd = withu_shell_arg($ffmpeg)
                . ' -y'
                . ' -i ' . escapeshellarg($videoAbs)
                . ' -c:v libx264 -preset medium -crf 23'
                . ' -c:a aac -b:a 128k'
                . ' -movflags +faststart '
                . escapeshellarg($transcodedAbs)
                . ' 2>&1';
            @shell_exec($cmd);

            if (!is_file($transcodedAbs)) {
                continue;
            }

            $newRelative = trim($transcodedSubDir, '/') . '/' . $transcodedFile;

            $updateData = [
                'video_path'    => $newRelative,
                'is_transcoded' => 1,
            ];
            if (empty($row['original_video_path'])) {
                $updateData['original_video_path'] = $originalPath;
            }

            try {
                $db->update('article_videos', $updateData, 'id = :id', ['id' => $videoId]);
                $processed++;
            } catch (Throwable $e) {
                // 单条更新失败忽略
                continue;
            }
        }
    }

    // 计算剩余未转码数量
    try {
        $pendingRow = $db->fetch(
            "SELECT COUNT(*) AS c
             FROM article_videos
             WHERE is_transcoded = 0 OR is_transcoded IS NULL"
        );
        $pending = (int) ($pendingRow['c'] ?? 0);
    } catch (Throwable $e) {
        $pending = 0;
    }

    echo json_encode([
        'success'   => true,
        'processed' => $processed,
        'pending'   => $pending,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX: 一键压缩未压缩图片（分批）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'optimize_batch') {
    header('Content-Type: application/json; charset=UTF-8');

    $excludeAlbums  = !empty($_POST['exclude_albums']) ? 1 : 0;
    $excludeImages  = !empty($_POST['exclude_images']) ? 1 : 0;
    $limit          = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 50;

    try {
        $where = ['ai.is_optimized = 0'];
        $params = [];
        if ($excludeAlbums) {
            $where[] = '(a.keep_original_quality = 0 OR a.keep_original_quality IS NULL)';
        }
        if ($excludeImages) {
            $where[] = 'ai.skip_optimize = 0';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $rows = $db->fetchAll("
            SELECT ai.id, ai.image_path
            FROM album_images ai
            LEFT JOIN albums a ON ai.album_id = a.id
            $whereSql
            ORDER BY ai.id ASC
            LIMIT :limit
        ", ['limit' => $limit]);

        $processed = 0;
        foreach ($rows as $row) {
            $path = $row['image_path'] ?? '';
            if (!$path) {
                continue;
            }
            $abs = rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($path, '/');
            if (!is_file($abs)) {
                continue;
            }
            try {
                optimize_uploaded_image($abs);
                $db->update('album_images', [
                    'is_optimized' => 1,
                ], 'id = :id', ['id' => $row['id']]);
                $processed++;
            } catch (Throwable $e) {
                // 单张失败忽略
                continue;
            }
        }

        // 计算剩余未压缩数量
        $remainingRow = $db->fetch("
            SELECT COUNT(*) AS c
            FROM album_images ai
            LEFT JOIN albums a ON ai.album_id = a.id
            $whereSql
        ");
        $remaining = (int) ($remainingRow['c'] ?? 0);

        echo json_encode([
            'success'   => true,
            'processed' => $processed,
            'remaining' => $remaining,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => '压缩失败：' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
require_once __DIR__ . '/_advanced/tools_image_stats.php';

include __DIR__ . '/header.php';
?>

<section class="admin-page-title">
    <h1>图片体积、压缩与补齐工具</h1>
    <p>查看图片体积、压缩占比，并按需一键压缩或为旧数据补齐缩略图 / WebP / 视频转码。</p>
    <p style="margin-top:0.25rem;font-size:0.85rem;color:var(--text-light);">
        推荐使用顺序：<strong>① 相册图片：补齐缩略图 / WebP 或仅补表</strong>（先让相册卡片和瀑布流都有专属缩略图） →
        <strong>② 压缩状态与一键压缩</strong>（统一压缩主图体积并生成 WebP） →
        <strong>③ 文章图片：触发 WebP 补齐</strong>（可选，对正文图片做相同处理） →
        <strong>④ 相册视频：统一视频转码</strong>（可选，为未转码视频生成浏览器更友好的版本）。
    </p>
</section>

<?php echo withu_advanced_tools_panel(); ?>

<?php include __DIR__ . '/footer.php'; ?>
