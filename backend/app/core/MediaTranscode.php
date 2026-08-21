<?php
/** 为浏览器不支持的 HEVC/MKV 等源生成短期 H264/AAC 播放缓存。 */

function withu_shell_arg(string $value): string
{
    if (PHP_OS_FAMILY === 'Windows') return '"' . str_replace('"', '\\"', $value) . '"';
    return escapeshellarg($value);
}

function withu_binary_path(string $name): string
{
    $setting = trim((string)get_setting($name . '_path', ''));
    $candidates = [];
    if ($setting !== '') $candidates[] = $setting;

    $binaryName = $name === 'ffmpeg' ? 'ffmpeg' : 'ffprobe';
    $embeddedRoot = ROOT_PATH . '/bin/ffmpeg';
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = $embeddedRoot . '/' . $binaryName . '.exe';
    } elseif (PHP_OS_FAMILY === 'Linux') {
        // 发布包内置 Linux x86_64 静态版本，生产服务器无需安装系统 ffmpeg。
        $machine = strtolower((string)php_uname('m'));
        if (in_array($machine, ['x86_64', 'amd64'], true)) {
            $candidates[] = $embeddedRoot . '/linux-x86_64/' . $binaryName;
        }
    }

    // 保留旧的本地开发路径，便于从历史目录运行开发环境。
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates[] = ROOT_PATH . '/../tools/ffmpeg/' . $binaryName . '.exe';
        $candidates[] = ROOT_PATH . '/../tools/ffmpeg/bin/' . $binaryName . '.exe';
        $candidates[] = 'C:/WithU/tools/ffmpeg/' . $binaryName . '.exe';
    }
    $candidates[] = $binaryName;

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            // 从 Windows 打包上传到 Linux 时可能丢失可执行位，首次使用时尽力修复。
            if (PHP_OS_FAMILY !== 'Windows' && !is_executable($candidate)) {
                @chmod($candidate, 0755);
            }
            if (PHP_OS_FAMILY === 'Windows' || is_executable($candidate)) return $candidate;
        }
        if ($candidate === $binaryName && function_exists('shell_exec')) {
            $lookup = PHP_OS_FAMILY === 'Windows'
                ? 'where ' . $candidate . ' 2>NUL'
                : 'command -v ' . $candidate . ' 2>/dev/null';
            $found = trim((string)@shell_exec($lookup));
            if ($found !== '') return $candidate;
        }
    }
    return '';
}

function withu_probe_media($db, array $media, string $sourceUrl): array
{
    if (!empty($media['video_codec'])) return $media;
    $probe = withu_binary_path('ffprobe');
    if ($probe === '' || $sourceUrl === '') return $media;
    $command = withu_shell_arg($probe) . ' -v error -show_entries stream=index,codec_name,codec_type,width,height -of json ' . withu_shell_arg($sourceUrl);
    $output = [];
    $exitCode = 1;
    @exec($command, $output, $exitCode);
    if ($exitCode !== 0) return $media;
    $decoded = json_decode(implode("\n", $output), true);
    if (!is_array($decoded)) return $media;
    $video = null; $audio = null;
    foreach ((array)($decoded['streams'] ?? []) as $stream) {
        if (($stream['codec_type'] ?? '') === 'video' && !$video) $video = $stream;
        if (($stream['codec_type'] ?? '') === 'audio' && !$audio) $audio = $stream;
    }
    if (!$video) return $media;
    $data = [
        'video_codec' => (string)($video['codec_name'] ?? ''),
        'audio_codec' => (string)($audio['codec_name'] ?? ''),
        'width' => isset($video['width']) ? (int)$video['width'] : null,
        'height' => isset($video['height']) ? (int)$video['height'] : null,
        'resolution' => withu_media_resolution_from_dimensions(
            isset($video['width']) ? (int)$video['width'] : null,
            isset($video['height']) ? (int)$video['height'] : null,
            (string)($media['resolution'] ?? '')
        ),
        'browser_playback' => 'unknown',
        'updated_at' => withu_now(),
    ];
    $media = array_merge($media, $data);
    $db->update('media_library', $data, 'id = :id', ['id' => (int)$media['id']]);
    return $media;
}

function withu_media_resolution_from_dimensions(?int $width, ?int $height, string $fallback = ''): string
{
    $width = max(0, (int)$width);
    $height = max(0, (int)$height);
    if ($width >= 3800 || $height >= 2000) return '4K';
    if ($width >= 2500 || $height >= 1300) return '2K';
    if ($height >= 1000) return '1080P';
    if ($height >= 700) return '720P';
    return trim($fallback);
}

function withu_probe_source_stream_info(string $sourceUrl, array $headers = [], int $timeout = 20): array
{
    $probe = withu_binary_path('ffprobe');
    if ($probe === '' || trim($sourceUrl) === '') return [];
    $timeout = max(6, min(60, $timeout));
    $headerText = '';
    foreach ($headers as $header) {
        $header = trim((string)$header);
        if ($header !== '') $headerText .= $header . "\r\n";
    }
    $command = withu_shell_arg($probe)
        . ' -v error -rw_timeout ' . (int)($timeout * 1000000)
        . ($headerText !== '' ? ' -headers ' . withu_shell_arg($headerText) : '')
        . ' -select_streams v:0 -show_entries stream=codec_name,width,height -of json '
        . withu_shell_arg($sourceUrl);
    $output = [];
    $exitCode = 1;
    @exec($command, $output, $exitCode);
    if ($exitCode !== 0) return [];
    $decoded = json_decode(implode("\n", $output), true);
    $stream = is_array($decoded) ? (($decoded['streams'][0] ?? null) ?: null) : null;
    if (!is_array($stream)) return [];
    $width = isset($stream['width']) ? (int)$stream['width'] : null;
    $height = isset($stream['height']) ? (int)$stream['height'] : null;
    return array_filter([
        'video_codec' => trim((string)($stream['codec_name'] ?? '')) ?: null,
        'width' => $width,
        'height' => $height,
        'resolution' => withu_media_resolution_from_dimensions($width, $height),
    ], static function ($value): bool { return $value !== null && $value !== ''; });
}

function withu_media_requires_transcode(array $media): bool
{
    $codec = strtolower((string)($media['video_codec'] ?? ''));
    $audio = strtolower((string)($media['audio_codec'] ?? ''));
    $source = strtolower((string)($media['source_url'] ?? $media['file_name'] ?? ''));
    $extension = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION));
    if ($extension !== 'mp4') return true;
    if ($codec !== '' && !in_array($codec, ['h264', 'avc', 'avc1'], true)) return true;
    if ($audio !== '' && !in_array($audio, ['aac', 'mp3', 'ac3', 'eac3'], true)) return true;
    return (bool)preg_match('/(?:h265|hevc|10bit|10-bit|mkv)/i', $source);
}

function withu_transcode_media($db, array $media, string $sourceUrl): string
{
    $ffmpeg = withu_binary_path('ffmpeg');
    if ($ffmpeg === '') throw new RuntimeException('未找到 ffmpeg，无法为当前视频生成浏览器兼容版本');
    $cacheDir = ROOT_PATH . '/uploads/media-cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    // The cache identity follows the source file, not mutable metadata timestamps.
    $key = substr(sha1((string)$media['id'] . '|' . (string)($media['file_etag'] ?? '') . '|' . (string)($media['file_size'] ?? '') . '|' . (string)($media['source_url'] ?? '')), 0, 16);
    $target = $cacheDir . '/media-' . (int)$media['id'] . '-' . $key . '.mp4';
    foreach (glob($cacheDir . '/media-' . (int)$media['id'] . '-*.mp4') ?: [] as $old) {
        if ($old !== $target && @filemtime($old) < time() - 86400) @unlink($old);
    }
    if (is_file($target) && @filemtime($target) >= time() - 86400 && filesize($target) > 0) return $target;
    $lockPath = $target . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock) @flock($lock, LOCK_EX);
    if (is_file($target) && @filemtime($target) >= time() - 86400 && filesize($target) > 0) {
        if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
        @unlink($lockPath);
        return $target;
    }
    $part = $target . '.part';
    @unlink($part);
    $command = withu_shell_arg($ffmpeg) . ' -y -hide_banner -loglevel error -i ' . withu_shell_arg($sourceUrl) . ' -map 0:v:0 -map 0:a:0? -vf ' . withu_shell_arg("scale=w='min(1920,iw)':h=-2") . ' -c:v libx264 -preset ultrafast -crf 26 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart -f mp4 ' . withu_shell_arg($part) . ' 2>&1';
    $output = [];
    $exitCode = 1;
    @exec($command, $output, $exitCode);
    if ($exitCode !== 0 || !is_file($part) || filesize($part) <= 0) {
        error_log('[MediaTranscode] failed for media ' . (int)$media['id'] . ': ' . mb_substr(implode("\n", $output), 0, 500));
        @unlink($part);
        if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
        @unlink($lockPath);
        throw new RuntimeException('视频转码失败：' . mb_substr(implode("\n", $output), 0, 500));
    }
    @rename($part, $target);
    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
    @unlink($lockPath);
    $db->update('media_library', ['browser_playback' => 'transcoded', 'updated_at' => withu_now()], 'id = :id', ['id' => (int)$media['id']]);
    return $target;
}
