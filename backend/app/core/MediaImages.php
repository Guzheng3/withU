<?php

/**
 * Media poster/backdrop helpers.
 *
 * Metadata providers return remote images. WithU stores a private local copy
 * and then serves it through authenticated endpoints, so the front-end never
 * depends on hot-linked poster URLs.
 */

if (!function_exists('withu_media_image_is_local')) {
    function withu_media_image_is_local(string $url): bool
    {
        return strpos($url, '/uploads/') === 0 || strpos($url, '/assets/') === 0;
    }
}

if (!function_exists('withu_media_image_public_path')) {
    function withu_media_image_public_path(string $absolutePath): string
    {
        $root = str_replace('\\', '/', rtrim(ROOT_PATH, '/\\'));
        $path = str_replace('\\', '/', $absolutePath);
        if (strpos($path, $root . '/') !== 0) return '';
        return '/' . ltrim(substr($path, strlen($root)), '/');
    }
}

if (!function_exists('withu_media_image_absolute_path')) {
    function withu_media_image_absolute_path(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('#^https?://#i', $url)) return '';
        if (strpos($url, '/') !== 0) return '';
        $path = realpath(ROOT_PATH . $url);
        if ($path === false) return '';
        $root = realpath(ROOT_PATH);
        return $root !== false && strpos($path, $root) === 0 ? $path : '';
    }
}

if (!function_exists('withu_media_image_download')) {
    function withu_media_image_download(string $url, string $kind, string $identity = ''): string
    {
        $url = trim($url);
        if ($url === '' || withu_media_image_is_local($url)) return $url;
        if (!preg_match('#^https?://#i', $url)) return '';

        $kind = $kind === 'backdrop' ? 'backdrops' : 'covers';
        $cacheDir = ROOT_PATH . '/uploads/media-images/' . $kind;
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);

        $hash = substr(sha1($kind . '|' . $identity . '|' . $url), 0, 32);
        foreach (['jpg', 'png', 'webp', 'gif'] as $existingExt) {
            $existing = $cacheDir . '/' . $hash . '.' . $existingExt;
            if (is_file($existing) && @filesize($existing) > 100) return withu_media_image_public_path($existing);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (withU media image cache)',
            CURLOPT_REFERER => 'https://movie.douban.com/',
            CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        

        if ($status < 200 || $status >= 300 || !is_string($body) || strlen($body) < 100) return '';
        $info = @getimagesizefromstring($body);
        if ($info === false && strpos($contentType, 'image/') !== 0) return '';

        $mime = strtolower((string)($info['mime'] ?? strtok($contentType, ';')));
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $target = $cacheDir . '/' . $hash . '.' . $ext;
        if (@file_put_contents($target, $body, LOCK_EX) === false) return '';
        return withu_media_image_public_path($target);
    }
}

if (!function_exists('withu_media_localize_metadata_images')) {
    function withu_media_localize_metadata_images(array $data, string $identity = ''): array
    {
        $metadata = json_decode((string)($data['metadata_json'] ?? '{}'), true);
        if (!is_array($metadata)) $metadata = [];
        foreach (['cover_url' => 'cover', 'backdrop_url' => 'backdrop'] as $field => $kind) {
            $url = trim((string)($data[$field] ?? ''));
            if ($url === '' || withu_media_image_is_local($url)) continue;
            $local = withu_media_image_download($url, $kind, $identity);
            if ($local !== '') {
                $metadata['remote_images'][$field] = $url;
                $data[$field] = $local;
            }
        }
        if (!empty($metadata['remote_images'])) {
            $data['metadata_json'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $data;
    }
}

if (!function_exists('withu_media_image_response')) {
    function withu_media_image_response(string $url, string $kind, string $etagSeed): void
    {
        $url = trim($url);
        if ($url === '') { http_response_code(404); exit; }

        $etag = '"' . sha1($etagSeed . '|' . $url) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=604800');
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }

        $local = withu_media_image_absolute_path($url);
        if ($local !== '' && is_file($local)) {
            $mime = @mime_content_type($local) ?: 'image/jpeg';
            if (strpos($mime, 'image/') !== 0) $mime = 'image/jpeg';
            header('Content-Type: ' . $mime);
            readfile($local);
            exit;
        }

        $localUrl = withu_media_image_download($url, $kind, $etagSeed);
        $local = $localUrl !== '' ? withu_media_image_absolute_path($localUrl) : '';
        if ($local !== '' && is_file($local)) {
            $mime = @mime_content_type($local) ?: 'image/jpeg';
            if (strpos($mime, 'image/') !== 0) $mime = 'image/jpeg';
            header('Content-Type: ' . $mime);
            readfile($local);
            exit;
        }

        http_response_code(404);
        exit;
    }
}
