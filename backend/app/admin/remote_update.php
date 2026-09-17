<?php
error_reporting(E_ALL & ~E_DEPRECATED);
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole(['user1', 'user2']);
$db = Database::getInstance();
migrate_schema_if_needed();

$error = '';
$success = '';

function withu_remote_update_format_bytes($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return '未知';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}

function withu_remote_update_max_upload_bytes(): int {
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $iniKey) {
        $value = ini_get($iniKey);
        if ($value !== false) {
            $limits[] = parse_php_size_to_bytes($value);
        }
    }

    $maxBytes = empty($limits) ? 2 * 1024 * 1024 * 1024 : min($limits);
    return max(1, min($maxBytes, 2 * 1024 * 1024 * 1024));
}

/** 版本号归一化：`v5.2.0-6+136` 与 `5.2.0.6` 视为同一版本（与 app 侧 RELEASE.md 约定一致）。 */
function withu_remote_update_normalize_version(string $version): string {
    $value = strtolower(trim($version));
    if ($value !== '' && $value[0] === 'v') {
        $value = substr($value, 1);
    }
    $value = explode('+', $value)[0];
    if (preg_match('/^(\d+(?:\.\d+)*)-(\d+)$/', $value, $matches)) {
        $value = $matches[1] . '.' . $matches[2];
    }
    return $value;
}

/** 外链安装包下载上限：超过即拒绝，避免填错链接时把服务器磁盘写满。 */
function withu_remote_update_max_link_bytes(): int {
    return 512 * 1024 * 1024;
}

/** 下载外链安装包到临时文件；返回空字符串表示成功，否则返回错误文案。 */
function withu_remote_update_fetch_apk(string $url, string $destPath): string {
    if (!function_exists('curl_init')) {
        return '服务器缺少 cURL 扩展，无法校验外链安装包。';
    }

    $handle = @fopen($destPath, 'wb');
    if ($handle === false) {
        return '无法创建临时文件以校验外链安装包。';
    }

    $maxBytes = withu_remote_update_max_link_bytes();
    $received = 0;
    $tooLarge = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_USERAGENT => 'withu-release-verifier',
        CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use ($handle, &$received, $maxBytes, &$tooLarge) {
            $received += strlen($chunk);
            if ($received > $maxBytes) {
                $tooLarge = true;
                return 0;
            }
            return fwrite($handle, $chunk);
        },
    ]);
    $executed = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $transportError = curl_error($curl);
    curl_close($curl);
    fclose($handle);

    if ($tooLarge) {
        @unlink($destPath);
        return '外链安装包超过 ' . withu_remote_update_format_bytes($maxBytes) . '，拒绝校验。';
    }
    if ($executed === false || $status !== 200) {
        @unlink($destPath);
        $reason = $transportError !== '' ? $transportError : ('HTTP ' . $status);
        return '无法下载外链安装包（' . $reason . '），请确认链接可直连且未过期。';
    }
    return '';
}

/** 读取二进制字符串池中的长度前缀（UTF-8 池为 1~2 字节，UTF-16 池为 1~2 个 16 位字）。 */
function withu_remote_update_read_pool_length(string $data, int &$position, int $limit, bool $utf8): ?int {
    if ($utf8) {
        if ($position >= $limit) {
            return null;
        }
        $first = ord($data[$position]);
        $position++;
        if (($first & 0x80) !== 0) {
            if ($position >= $limit) {
                return null;
            }
            $second = ord($data[$position]);
            $position++;
            return (($first & 0x7F) << 8) | $second;
        }
        return $first;
    }

    if ($position + 2 > $limit) {
        return null;
    }
    $first = unpack('v', substr($data, $position, 2))[1];
    $position += 2;
    if (($first & 0x8000) !== 0) {
        if ($position + 2 > $limit) {
            return null;
        }
        $second = unpack('v', substr($data, $position, 2))[1];
        $position += 2;
        return (($first & 0x7FFF) << 16) | $second;
    }
    return $first;
}

/** 解析 AXML 字符串池，返回「下标 => 字符串」。 */
function withu_remote_update_parse_string_pool(string $data, int $chunkStart, int $headerSize, int $chunkSize): ?array {
    $limit = $chunkStart + $chunkSize;
    if ($chunkStart + $headerSize + 20 > strlen($data)) {
        return null;
    }
    $stringCount = unpack('V', substr($data, $chunkStart + 8, 4))[1];
    $flags = unpack('V', substr($data, $chunkStart + 16, 4))[1];
    $stringsStart = unpack('V', substr($data, $chunkStart + 20, 4))[1];
    $utf8 = ($flags & 0x0100) !== 0;
    $offsetsBase = $chunkStart + $headerSize;
    $dataBase = $chunkStart + $stringsStart;

    $strings = [];
    for ($index = 0; $index < $stringCount; $index++) {
        $offsetPosition = $offsetsBase + $index * 4;
        if ($offsetPosition + 4 > $limit || $offsetPosition + 4 > strlen($data)) {
            return null;
        }
        $position = $dataBase + unpack('V', substr($data, $offsetPosition, 4))[1];
        if ($position < 0 || $position > $limit) {
            return null;
        }
        if ($utf8) {
            // UTF-8 池：先 utf16 长度，再 utf8 字节长度
            if (withu_remote_update_read_pool_length($data, $position, $limit, true) === null) {
                return null;
            }
            $byteLength = withu_remote_update_read_pool_length($data, $position, $limit, true);
            if ($byteLength === null || $position + $byteLength > $limit) {
                return null;
            }
            $strings[$index] = substr($data, $position, $byteLength);
        } else {
            $charLength = withu_remote_update_read_pool_length($data, $position, $limit, false);
            if ($charLength === null || $position + $charLength * 2 > $limit) {
                return null;
            }
            $strings[$index] = mb_convert_encoding(substr($data, $position, $charLength * 2), 'UTF-8', 'UTF-16LE');
        }
    }
    return $strings;
}

/**
 * 读取 APK 内 AndroidManifest.xml 的 versionName / versionCode。
 *
 * 发布页必须核对「填写的版本号 == 包内真实 versionName」，而 PHP 主机上一般没有 aapt，
 * 所以这里直接解析二进制的 AXML 分块结构（本函数只依赖 zlib/标准库，见调用方对 ZipArchive 的检查）。
 */
function withu_remote_update_parse_axml_manifest(string $data): ?array {
    $length = strlen($data);
    if ($length < 8 || unpack('v', substr($data, 0, 2))[1] !== 0x0003) {
        return null;
    }

    $strings = null;
    $offset = 8;
    while ($offset + 8 <= $length) {
        $chunkType = unpack('v', substr($data, $offset, 2))[1];
        $headerSize = unpack('v', substr($data, $offset + 2, 2))[1];
        $chunkSize = unpack('V', substr($data, $offset + 4, 4))[1];
        if ($chunkSize < 8 || $offset + $chunkSize > $length) {
            return null;
        }

        if ($chunkType === 0x0001) {
            $strings = withu_remote_update_parse_string_pool($data, $offset, $headerSize, $chunkSize);
            if ($strings === null) {
                return null;
            }
        } elseif ($chunkType === 0x0102 && is_array($strings)) {
            $info = withu_remote_update_parse_manifest_start_element($data, $offset, $headerSize, $strings);
            if ($info !== null) {
                return $info;
            }
        }
        $offset += $chunkSize;
    }
    return null;
}

/** 从 START_ELEMENT 分块中取出 <manifest> 的 versionName / versionCode。 */
function withu_remote_update_parse_manifest_start_element(string $data, int $chunkStart, int $headerSize, array $strings): ?array {
    $body = $chunkStart + $headerSize;
    if ($body + 20 > strlen($data)) {
        return null;
    }
    $nameIndex = unpack('V', substr($data, $body + 4, 4))[1];
    if (($strings[$nameIndex] ?? null) !== 'manifest') {
        return null;
    }
    $attributeStart = unpack('v', substr($data, $body + 8, 2))[1];
    $attributeSize = unpack('v', substr($data, $body + 10, 2))[1];
    $attributeCount = unpack('v', substr($data, $body + 12, 2))[1];

    $result = ['versionName' => null, 'versionCode' => null];
    for ($index = 0; $index < $attributeCount; $index++) {
        $position = $body + $attributeStart + $index * $attributeSize;
        if ($position + 20 > strlen($data)) {
            return null;
        }
        $attributeNameIndex = unpack('V', substr($data, $position + 4, 4))[1];
        $rawValueIndex = unpack('V', substr($data, $position + 8, 4))[1];
        $valueType = ord($data[$position + 15]);
        $valueData = unpack('V', substr($data, $position + 16, 4))[1];
        $attributeName = $strings[$attributeNameIndex] ?? '';

        if ($attributeName === 'versionName') {
            if ($rawValueIndex !== 0xFFFFFFFF) {
                $result['versionName'] = $strings[$rawValueIndex] ?? null;
            } elseif ($valueType === 0x03) {
                $result['versionName'] = $strings[$valueData] ?? null;
            }
        } elseif ($attributeName === 'versionCode') {
            $result['versionCode'] = $valueType === 0x10
                ? $valueData
                : (int)($strings[$valueData] ?? 0);
        }
    }
    return $result;
}

/** 从文件流里精确读取 N 字节；不足返回 null。 */
function withu_remote_update_read_exact($handle, int $bytes): ?string {
    $buffer = '';
    while (strlen($buffer) < $bytes) {
        $chunk = fread($handle, $bytes - strlen($buffer));
        if ($chunk === false || $chunk === '') {
            return null;
        }
        $buffer .= $chunk;
    }
    return $buffer;
}

/**
 * 不依赖 php-zip 的 ZIP 条目读取：走中央目录定位，再用 gzinflate 解压。
 * 发布机常常只装了 zlib 而没装 php-zip，缺了它整套校验就会把发布堵死。
 */
function withu_remote_update_read_zip_entry(string $apkPath, string $entryName): ?string {
    if (!function_exists('gzinflate')) {
        return null;
    }
    $handle = @fopen($apkPath, 'rb');
    if ($handle === false) {
        return null;
    }
    $size = (int)filesize($apkPath);
    $result = null;
    $tailLength = (int)min($size, 65557);
    $tail = $tailLength > 0 ? (fseek($handle, $size - $tailLength) === 0 ? fread($handle, $tailLength) : false) : false;
    $eocd = is_string($tail) ? strrpos($tail, "PK\x05\x06") : false;
    if ($eocd !== false) {
        $entryCount = unpack('v', substr($tail, $eocd + 10, 2))[1];
        $centralOffset = unpack('V', substr($tail, $eocd + 16, 4))[1];
        if (fseek($handle, $centralOffset) === 0) {
            for ($index = 0; $index < $entryCount; $index++) {
                $header = withu_remote_update_read_exact($handle, 46);
                if ($header === null || substr($header, 0, 4) !== "PK\x01\x02") {
                    break;
                }
                $method = unpack('v', substr($header, 10, 2))[1];
                $compressedSize = unpack('V', substr($header, 20, 4))[1];
                $nameLength = unpack('v', substr($header, 28, 2))[1];
                $extraLength = unpack('v', substr($header, 30, 2))[1];
                $commentLength = unpack('v', substr($header, 32, 2))[1];
                $localOffset = unpack('V', substr($header, 42, 4))[1];
                $name = $nameLength > 0 ? (string)withu_remote_update_read_exact($handle, $nameLength) : '';
                if ($extraLength + $commentLength > 0) {
                    fseek($handle, $extraLength + $commentLength, SEEK_CUR);
                }
                if ($name !== $entryName) {
                    continue;
                }

                $localHeader = fseek($handle, $localOffset) === 0
                    ? withu_remote_update_read_exact($handle, 30)
                    : null;
                if ($localHeader === null || substr($localHeader, 0, 4) !== "PK\x03\x04") {
                    break;
                }
                $localNameLength = unpack('v', substr($localHeader, 26, 2))[1];
                $localExtraLength = unpack('v', substr($localHeader, 28, 2))[1];
                $dataOffset = $localOffset + 30 + $localNameLength + $localExtraLength;
                if ($compressedSize <= 0 || $dataOffset + $compressedSize > $size) {
                    break;
                }
                $payload = fseek($handle, $dataOffset) === 0
                    ? withu_remote_update_read_exact($handle, $compressedSize)
                    : null;
                if ($payload === null) {
                    break;
                }
                if ($method === 0) {
                    $result = $payload;
                } elseif ($method === 8) {
                    $inflated = @gzinflate($payload);
                    $result = is_string($inflated) ? $inflated : null;
                }
                break;
            }
        }
    }
    fclose($handle);
    return $result;
}

/** 取出 APK 内的二进制 AndroidManifest.xml。 */
function withu_remote_update_read_apk_manifest(string $apkPath): ?string {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($apkPath) === true) {
            $manifest = $zip->getFromName('AndroidManifest.xml');
            $zip->close();
            if (is_string($manifest) && $manifest !== '') {
                return $manifest;
            }
        }
    }
    return withu_remote_update_read_zip_entry($apkPath, 'AndroidManifest.xml');
}

/** 从 APK 中读取 versionName / versionCode；读不到返回 null。 */
function withu_remote_update_apk_manifest_info(string $apkPath): ?array {
    $manifest = withu_remote_update_read_apk_manifest($apkPath);
    if (!is_string($manifest) || $manifest === '') {
        return null;
    }
    return withu_remote_update_parse_axml_manifest($manifest);
}

/**
 * 核对安装包内的 versionName 与填写的版本号是否一致。
 * 返回空字符串表示一致，否则返回错误文案。
 */
function withu_remote_update_verify_apk_version(string $apkPath, string $version): string {
    $info = withu_remote_update_apk_manifest_info($apkPath);
    if ($info === null || $info['versionName'] === null) {
        return '无法从安装包内读取 versionName，拒绝发布以免版本号填错（请确认服务器已启用 zlib/gzinflate）。';
    }

    $declared = withu_remote_update_normalize_version((string)$info['versionName']);
    $entered = withu_remote_update_normalize_version($version);
    if ($declared !== $entered) {
        return '填写的版本号 ' . $version . ' 与安装包内的 versionName '
            . (string)$info['versionName'] . ' 不一致，请核对后再发布。';
    }
    return '';
}

function withu_remote_update_source_label(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    return preg_match('#^https?://#i', $apkUrl) ? '外部链接' : '本地上传';
}

function withu_remote_update_download_url(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    if ($apkUrl === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $apkUrl)) {
        return BASE_URL . '/api/app_update_download.php?id=' . (int)$row['id'];
    }
    return upload_url($apkUrl);
}

function withu_remote_update_local_path(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    if ($apkUrl === '' || preg_match('#^https?://#i', $apkUrl) || strpos($apkUrl, '..') !== false) {
        return '';
    }
    return rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($apkUrl, '/\\');
}

function withu_remote_update_metadata_ok(?array $row): string {
    $apkUrl = trim((string)($row['apk_url'] ?? ''));
    $sha256 = strtolower(trim((string)($row['sha256'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        return '安装包摘要无效，请重新上传或填写完整的 SHA-256。';
    }
    if (preg_match('#^https?://#i', $apkUrl)) {
        if (!preg_match('#^https://#i', $apkUrl) || strlen($apkUrl) > 512) {
            return '安装包链接必须是 HTTPS 地址，且长度不超过 512 个字符。';
        }
        return '';
    }
    if (!is_file(withu_remote_update_local_path($row))) {
        return '本地上传的安装包文件已丢失，请重新上传。';
    }
    return '';
}

$now = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'create');

    if (in_array($action, ['enable', 'disable', 'delete'], true)) {
        $releaseId = (int)($_POST['id'] ?? 0);
        $release = $releaseId > 0
            ? $db->fetch('SELECT * FROM `remote_updates` WHERE `id` = :id LIMIT 1', ['id' => $releaseId])
            : null;

        if (!$release) {
            $error = '没有找到要操作的版本。';
        } else {
            if ($action === 'delete') {
                $localPath = withu_remote_update_local_path($release);
                $referenceCount = (int)$db->fetch(
                    'SELECT COUNT(*) AS `total` FROM `remote_updates` WHERE `apk_url` = :apk_url AND `id` <> :id',
                    ['apk_url' => (string)$release['apk_url'], 'id' => $releaseId]
                )['total'];
                $db->delete('remote_updates', 'id = :id', ['id' => $releaseId]);
                if ($localPath !== '' && $referenceCount === 0 && is_file($localPath)) {
                    @unlink($localPath);
                }
                header('Location: /admin/remote_update.php?success=deleted');
                exit;
            }

            $metadataError = withu_remote_update_metadata_ok($release);
            if ($metadataError !== '') {
                $error = $metadataError;
            } elseif ($action === 'enable') {
                $publishedAt = !empty($release['published_at']) ? (string)$release['published_at'] : $now;
                $db->update('remote_updates', ['enabled' => 0], 'enabled = 1');
                $db->update(
                    'remote_updates',
                    ['enabled' => 1, 'published_at' => $publishedAt, 'updated_at' => $now],
                    'id = :id',
                    ['id' => $releaseId]
                );
                header('Location: /admin/remote_update.php?success=enabled');
                exit;
            } else {
                $db->update(
                    'remote_updates',
                    ['enabled' => 0, 'updated_at' => $now],
                    'id = :id',
                    ['id' => $releaseId]
                );
                header('Location: /admin/remote_update.php?success=disabled');
                exit;
            }
        }
    } elseif ($action === 'create') {
        $sourceMode = (string)($_POST['source_mode'] ?? '');
        $version = trim((string)($_POST['version'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $apkLink = trim((string)($_POST['apk_link'] ?? ''));
        $apkSha256 = strtolower(trim((string)($_POST['apk_sha256'] ?? '')));
        $forceUpdate = isset($_POST['force_update']) ? 1 : 0;
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $apkUrl = '';
        $sha256 = '';
        $sizeBytes = 0;
        $savedLocalPath = '';

        if ($version === '') {
            $error = '请填写版本号。';
        } elseif (mb_strlen($version) > 32) {
            $error = '版本号不能超过 32 个字符。';
        } elseif (mb_strlen($title) > 120) {
            $error = '更新标题不能超过 120 个字符。';
        } elseif (mb_strlen($body) > 10000) {
            $error = '更新说明不能超过 10000 个字符。';
        } elseif ($sourceMode !== 'local' && $sourceMode !== 'link') {
            $error = '请选择安装包来源。';
        } elseif ($sourceMode === 'local') {
            $file = $_FILES['apk_file'] ?? null;
            if (!is_array($file) || !isset($file['error'])) {
                $error = '请选择要上传的 APK 文件。';
            } elseif ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
                $error = '请选择要上传的 APK 文件。';
            } elseif ((int)$file['error'] === UPLOAD_ERR_INI_SIZE || (int)$file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = 'APK 文件超过上传大小限制（最大 ' . withu_remote_update_format_bytes(withu_remote_update_max_upload_bytes()) . '）。';
            } elseif ((int)$file['error'] !== UPLOAD_ERR_OK) {
                $error = 'APK 上传失败，错误码 ' . (int)$file['error'] . '。';
            } elseif (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'apk') {
                $error = '安装包必须是 .apk 文件。';
            } elseif ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > withu_remote_update_max_upload_bytes()) {
                $error = 'APK 文件大小无效或超过上传限制。';
            } elseif (!is_uploaded_file((string)$file['tmp_name'])) {
                $error = '无法确认上传文件来源，请重试。';
            } else {
                $uploadDir = UPLOAD_DIR . '/updates';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $error = '无法创建安装包上传目录。';
                } else {
                    $filename = uniqid('apk-', true) . '.apk';
                    $savedLocalPath = rtrim($uploadDir, '/\\') . '/' . $filename;
                    if (!move_uploaded_file((string)$file['tmp_name'], $savedLocalPath)) {
                        $error = 'APK 文件保存失败，请检查服务器写入权限。';
                    } else {
                        $computedSha256 = @hash_file('sha256', $savedLocalPath);
                        if (!is_string($computedSha256) || !preg_match('/^[a-f0-9]{64}$/', $computedSha256)) {
                            @unlink($savedLocalPath);
                            $savedLocalPath = '';
                            $error = '无法计算 APK 的 SHA-256，请重新上传。';
                        } else {
                            // 版本号必须等于包内真实 versionName，否则客户端会把错的版本号当更新提示。
                            $versionMismatch = withu_remote_update_verify_apk_version($savedLocalPath, $version);
                            if ($versionMismatch !== '') {
                                @unlink($savedLocalPath);
                                $savedLocalPath = '';
                                $error = $versionMismatch;
                            } else {
                                $apkUrl = 'updates/' . $filename;
                                $sha256 = strtolower($computedSha256);
                                $sizeBytes = (int)filesize($savedLocalPath);
                            }
                        }
                    }
                }
            }
        } else {
            if ($apkLink === '') {
                $error = '请粘贴 APK 下载链接。';
            } elseif (!preg_match('#^https://#i', $apkLink)) {
                $error = 'APK 下载链接必须使用 HTTPS。';
            } elseif (strlen($apkLink) > 512) {
                $error = 'APK 下载链接不能超过 512 个字符。';
            } elseif (($parsedHost = parse_url($apkLink, PHP_URL_HOST)) === false || $parsedHost === null) {
                $error = 'APK 下载链接格式不正确。';
            } elseif (!preg_match('/^[a-f0-9]{64}$/', $apkSha256)) {
                $error = '外部链接必须填写 64 位小写 SHA-256 摘要。';
            } else {
                // 外链模式过去只校验摘要格式，填的摘要与链接里的文件可以完全无关，
                // 于是「版本号 / 摘要 / 文件」不一致也能发布出去。这里实际下载并逐项核对。
                $tempApkPath = tempnam(sys_get_temp_dir(), 'withu-apk-');
                if ($tempApkPath === false) {
                    $error = '无法创建临时文件以校验外链安装包。';
                } else {
                    $fetchError = withu_remote_update_fetch_apk($apkLink, $tempApkPath);
                    if ($fetchError !== '') {
                        $error = $fetchError;
                    } else {
                        $actualSha256 = @hash_file('sha256', $tempApkPath);
                        if (!is_string($actualSha256) || !hash_equals($apkSha256, strtolower($actualSha256))) {
                            $error = '外链安装包的 SHA-256 与填写值不一致，拒绝发布（实际为 '
                                . (is_string($actualSha256) ? strtolower($actualSha256) : '无法计算') . '）。';
                        } else {
                            $versionMismatch = withu_remote_update_verify_apk_version($tempApkPath, $version);
                            if ($versionMismatch !== '') {
                                $error = $versionMismatch;
                            } else {
                                $apkUrl = $apkLink;
                                $sha256 = $apkSha256;
                                $sizeBytes = (int)filesize($tempApkPath);
                            }
                        }
                    }
                    @unlink($tempApkPath);
                }
            }
        }

        if ($error === '') {
            $data = [
                'version' => $version,
                'title' => $title !== '' ? $title : '版本更新',
                'body' => $body,
                'apk_url' => $apkUrl,
                'sha256' => $sha256,
                'size_bytes' => $sizeBytes,
                'force_update' => $forceUpdate,
                'enabled' => $enabled,
                'published_at' => $enabled ? $now : null,
                'updated_at' => $now,
            ];
            try {
                if ($enabled) {
                    $db->update('remote_updates', ['enabled' => 0], 'enabled = 1');
                }
                $db->insert('remote_updates', $data);
                header('Location: /admin/remote_update.php?success=' . ($enabled ? 'published' : 'saved'));
                exit;
            } catch (Throwable $e) {
                error_log('Remote update create error: ' . $e->getMessage());
                if ($savedLocalPath !== '' && is_file($savedLocalPath)) {
                    @unlink($savedLocalPath);
                }
                $error = '版本保存失败，请稍后重试。';
            }
        }

        $form = [
            'source_mode' => $sourceMode === 'link' ? 'link' : 'local',
            'version' => $version,
            'title' => $title,
            'body' => $body,
            'apk_link' => $apkLink,
            'apk_sha256' => $apkSha256,
            'force_update' => $forceUpdate,
            'enabled' => $enabled,
        ];
    } else {
        $error = '不支持的操作。';
    }
}

$successCode = (string)($_GET['success'] ?? '');
$successMessages = [
    'published' => '新版本已发布，客户端将收到更新提示。',
    'saved' => '版本已保存为草稿，暂未下发给客户端。',
    'enabled' => '该版本已启用，并替换当前生效版本。',
    'disabled' => '该版本已停用。',
    'deleted' => '该版本已删除。',
    '1' => '操作已完成。',
];
if ($successCode !== '' && isset($successMessages[$successCode])) {
    $success = $successMessages[$successCode];
}

$currentRelease = $db->fetch(
    'SELECT * FROM `remote_updates` WHERE `enabled` = 1 ORDER BY `id` DESC LIMIT 1'
);
$releases = $db->fetchAll('SELECT * FROM `remote_updates` ORDER BY `id` DESC LIMIT 30');

$maxUploadBytes = withu_remote_update_max_upload_bytes();
$form = $form ?? [
    'source_mode' => 'local',
    'version' => '',
    'title' => '',
    'body' => '',
    'apk_link' => '',
    'apk_sha256' => '',
    'force_update' => 0,
    'enabled' => 1,
];

$adminPage = 'remote_update';
$adminNarrow = true;
include __DIR__ . '/header.php';
?>

<style>
    .remote-update-source-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        margin: 0.35rem 0 1rem;
    }

    .remote-update-source-option {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        min-height: 84px;
        padding: 0.8rem;
        border: 1px solid rgba(148, 163, 184, 0.45);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
        cursor: pointer;
    }

    .remote-update-source-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .remote-update-source-option:has(input:checked) {
        border-color: var(--v3-pink, #f26d9c);
        box-shadow: inset 0 0 0 1px var(--v3-pink, #f26d9c);
    }

    .remote-update-source-option:focus-within {
        outline: 2px solid rgba(242, 109, 156, 0.28);
        outline-offset: 2px;
    }

    .remote-update-source-option i {
        flex: 0 0 auto;
        margin-top: 0.1rem;
        color: var(--v3-pink-deep, #d94d81);
        font-size: 1.25rem;
    }

    .remote-update-source-copy {
        min-width: 0;
    }

    .remote-update-source-copy strong,
    .remote-update-source-copy small {
        display: block;
    }

    .remote-update-source-copy strong {
        margin-bottom: 0.2rem;
        color: var(--text, #334155);
        font-size: 0.88rem;
    }

    .remote-update-source-copy small {
        color: var(--text-light, #64748b);
        font-size: 0.75rem;
        line-height: 1.5;
    }

    .remote-update-field-group[hidden] {
        display: none;
    }

    .remote-update-current-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.7rem;
        margin-top: 0.8rem;
    }

    .remote-update-meta-item {
        min-width: 0;
        padding: 0.7rem 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
    }

    .remote-update-meta-item span,
    .remote-update-meta-item strong {
        display: block;
    }

    .remote-update-meta-item span {
        margin-bottom: 0.25rem;
        color: var(--text-light, #64748b);
        font-size: 0.74rem;
    }

    .remote-update-meta-item strong {
        overflow: hidden;
        color: var(--text, #334155);
        font-size: 0.84rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .remote-update-body {
        margin-top: 0.7rem;
        padding-top: 0.7rem;
        border-top: 1px solid rgba(148, 163, 184, 0.3);
        color: var(--text, #334155);
        font-size: 0.84rem;
        line-height: 1.7;
    }

    .remote-update-history {
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .remote-update-history-item {
        padding: 0.85rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.42);
    }

    .remote-update-history-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
    }

    .remote-update-history-head strong {
        margin-right: auto;
        color: var(--text, #334155);
        font-size: 0.9rem;
    }

    .remote-update-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: rgba(100, 116, 139, 0.11);
        color: #526070;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .remote-update-pill-active {
        background: rgba(34, 197, 94, 0.12);
        color: #15803d;
    }

    .remote-update-pill-force {
        background: rgba(234, 88, 12, 0.12);
        color: #c2410c;
    }

    .remote-update-history-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem 0.9rem;
        margin-top: 0.4rem;
        color: var(--text-light, #64748b);
        font-size: 0.74rem;
    }

    .remote-update-history-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.65rem;
    }

    .remote-update-empty {
        padding: 1.4rem 0.8rem;
        border: 1px dashed rgba(148, 163, 184, 0.45);
        border-radius: 10px;
        color: var(--text-light, #64748b);
        font-size: 0.84rem;
        text-align: center;
    }

    @media (max-width: 560px) {
        .remote-update-source-options,
        .remote-update-current-meta {
            grid-template-columns: 1fr;
        }

        .remote-update-history-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .remote-update-history-actions form,
        .remote-update-history-actions button {
            width: 100%;
        }
    }
</style>

<section class="admin-page-title">
    <h1>远程更新</h1>
    <p>上传 APK 或填写 HTTPS 下载链接；启用后，课表客户端会读取最新版本并提示更新。</p>
</section>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?php echo e($success); ?></div><?php endif; ?>

<section class="admin-grid admin-grid-single">
    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-cloud-check" aria-hidden="true"></i>当前生效版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">客户端调用公开接口时返回的信息，只允许一个版本处于启用状态。</div>
        </div>

        <?php if ($currentRelease): ?>
            <div class="remote-update-current-meta">
                <div class="remote-update-meta-item">
                    <span>版本号</span>
                    <strong><?php echo e($currentRelease['version']); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>更新标题</span>
                    <strong><?php echo e($currentRelease['title']); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>安装包来源</span>
                    <strong><?php echo e(withu_remote_update_source_label($currentRelease)); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>安装包大小</span>
                    <strong><?php echo e(withu_remote_update_format_bytes($currentRelease['size_bytes'])); ?></strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>SHA-256</span>
                    <strong title="<?php echo e($currentRelease['sha256']); ?>"><?php echo e(substr((string)$currentRelease['sha256'], 0, 18)); ?>…</strong>
                </div>
                <div class="remote-update-meta-item">
                    <span>发布时间</span>
                    <strong><?php echo e($currentRelease['published_at'] ?: $currentRelease['updated_at']); ?></strong>
                </div>
            </div>
            <div class="remote-update-body">
                <?php echo nl2br(e((string)$currentRelease['body'])); ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem;">
                <span class="remote-update-pill remote-update-pill-active"><i class="ti ti-circle-check"></i>正在下发</span>
                <?php if ((int)$currentRelease['force_update'] === 1): ?>
                    <span class="remote-update-pill remote-update-pill-force"><i class="ti ti-alert-triangle"></i>强制更新</span>
                <?php endif; ?>
                <a class="btn" href="<?php echo e(withu_remote_update_download_url($currentRelease)); ?>" target="_blank" rel="noopener">
                    <i class="ti ti-download"></i><span>检查下载地址</span>
                </a>
            </div>
        <?php else: ?>
            <div class="remote-update-empty">
                <i class="ti ti-cloud-off" style="display:block;margin-bottom:.4rem;font-size:1.5rem;"></i>
                暂无生效版本。发布新版本后，客户端才会收到更新提示。
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-cloud-upload" aria-hidden="true"></i>发布新版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">上传 APK 可自动计算摘要；使用外部链接时需要粘贴文件对应的 SHA-256。</div>
        </div>

        <form method="post" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int)$maxUploadBytes; ?>">

            <div class="remote-update-source-options" role="radiogroup" aria-label="安装包来源">
                <label class="remote-update-source-option">
                    <input type="radio" name="source_mode" value="local" <?php echo $form['source_mode'] === 'local' ? 'checked' : ''; ?>>
                    <i class="ti ti-upload" aria-hidden="true"></i>
                    <span class="remote-update-source-copy">
                        <strong>上传 APK</strong>
                        <small>保存在本站，摘要自动计算；单文件最大 <?php echo e(withu_remote_update_format_bytes($maxUploadBytes)); ?></small>
                    </span>
                </label>
                <label class="remote-update-source-option">
                    <input type="radio" name="source_mode" value="link" <?php echo $form['source_mode'] === 'link' ? 'checked' : ''; ?>>
                    <i class="ti ti-link" aria-hidden="true"></i>
                    <span class="remote-update-source-copy">
                        <strong>粘贴链接</strong>
                        <small>使用 HTTPS 直链，客户端先跳转本站再下载并校验摘要</small>
                    </span>
                </label>
            </div>

            <label class="admin-field">
                版本号
                <input class="admin-input" type="text" name="version" value="<?php echo e($form['version']); ?>" placeholder="例如 1.2.0" maxlength="32">
                <span class="admin-help">客户端只会在远端版本比当前版本更新时弹窗。</span>
            </label>
            <label class="admin-field">
                更新标题
                <input class="admin-input" type="text" name="title" value="<?php echo e($form['title']); ?>" placeholder="例如：课表同步更稳定了" maxlength="120">
            </label>
            <label class="admin-field">
                更新说明
                <textarea class="admin-input" name="body" rows="5" maxlength="10000" placeholder="每行写一条变更说明"><?php echo e($form['body']); ?></textarea>
            </label>

            <div class="remote-update-field-group" id="remote-update-local-fields" <?php echo $form['source_mode'] !== 'local' ? 'hidden' : ''; ?>>
                <label class="admin-field">
                    APK 文件
                    <input class="admin-input" type="file" name="apk_file" accept=".apk,application/vnd.android.package-archive">
                    <span class="admin-help">选择 .apk 文件，保存时自动计算 SHA-256。</span>
                </label>
            </div>

            <div class="remote-update-field-group" id="remote-update-link-fields" <?php echo $form['source_mode'] !== 'link' ? 'hidden' : ''; ?>>
                <label class="admin-field">
                    APK 下载链接
                    <input class="admin-input" type="url" name="apk_link" value="<?php echo e($form['apk_link']); ?>" placeholder="https://example.com/app.apk" maxlength="512">
                    <span class="admin-help">必须是可直接下载 APK 的 HTTPS 链接。</span>
                </label>
                <label class="admin-field">
                    APK SHA-256
                    <input class="admin-input" type="text" name="apk_sha256" value="<?php echo e($form['apk_sha256']); ?>" placeholder="64 位小写十六进制摘要" pattern="[a-f0-9]{64}" autocomplete="off" autocapitalize="off" spellcheck="false">
                    <span class="admin-help">可用 <code>certutil -hashfile app.apk SHA256</code> 或 <code>sha256sum app.apk</code> 获取。</span>
                </label>
            </div>

            <div class="admin-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem;margin-top:.2rem;">
                <label class="switch">
                    <input type="checkbox" name="force_update" value="1" <?php echo $form['force_update'] ? 'checked' : ''; ?>>
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-label">强制更新</span>
                </label>
                <label class="switch">
                    <input type="checkbox" name="enabled" value="1" <?php echo $form['enabled'] ? 'checked' : ''; ?>>
                    <span class="switch-track"><span class="switch-thumb"></span></span>
                    <span class="switch-label">保存后立即启用</span>
                </label>
            </div>

            <div class="admin-page-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-desktop-check"></i><span><?php echo $form['enabled'] ? '发布版本' : '保存草稿'; ?></span>
                </button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <div>
                <div class="admin-card-title">
                    <i class="ti ti-history" aria-hidden="true"></i>历史版本
                    <button type="button" class="admin-help-toggle" title="查看说明" aria-label="查看说明" aria-expanded="false"><i class="ti ti-info-circle"></i></button>
                </div>
            </div>
        </div>
        <div class="admin-card-help">
            <div class="admin-card-subtitle">保留最近 30 条发布记录；可停用当前版本、启用草稿或删除不再使用的版本。</div>
        </div>

        <?php if (!$releases): ?>
            <div class="remote-update-empty">还没有发布记录。</div>
        <?php else: ?>
            <ul class="remote-update-history">
                <?php foreach ($releases as $release): ?>
                    <?php
                        $isActive = (int)($release['enabled'] ?? 0) === 1;
                        $isForce = (int)($release['force_update'] ?? 0) === 1;
                        $historyTitle = trim((string)$release['title']) !== ''
                            ? trim((string)$release['title'])
                            : '版本 ' . $release['version'];
                    ?>
                    <li class="remote-update-history-item">
                        <div class="remote-update-history-head">
                            <strong>v<?php echo e($release['version']); ?> · <?php echo e($historyTitle); ?></strong>
                            <?php if ($isActive): ?>
                                <span class="remote-update-pill remote-update-pill-active"><i class="ti ti-circle-check"></i>生效中</span>
                            <?php else: ?>
                                <span class="remote-update-pill">已停用</span>
                            <?php endif; ?>
                            <?php if ($isForce): ?>
                                <span class="remote-update-pill remote-update-pill-force">强制</span>
                            <?php endif; ?>
                        </div>
                        <div class="remote-update-history-meta">
                            <span><?php echo e(withu_remote_update_source_label($release)); ?></span>
                            <span><?php echo e(withu_remote_update_format_bytes($release['size_bytes'])); ?></span>
                            <span>SHA-256 <?php echo e(substr((string)$release['sha256'], 0, 12)); ?>…</span>
                            <span><?php echo e($release['published_at'] ?: $release['updated_at']); ?></span>
                        </div>
                        <?php if (trim((string)$release['body']) !== ''): ?>
                            <div class="remote-update-body"><?php echo nl2br(e((string)$release['body'])); ?></div>
                        <?php endif; ?>
                        <div class="remote-update-history-actions">
                            <?php if (!$isActive): ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="enable">
                                    <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                    <button type="submit" class="btn" <?php echo $currentRelease ? 'onclick="return confirm(\'启用后会替换当前生效版本，继续吗？\');"' : ''; ?>>
                                        <i class="ti ti-player-play"></i><span>启用</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                    <button type="submit" class="btn">
                                        <i class="ti ti-player-pause"></i><span>停用</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('删除后无法恢复，确定删除该版本吗？');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$release['id']; ?>">
                                <button type="submit" class="btn" style="color:#b42318;">
                                    <i class="ti ti-trash"></i><span>删除</span>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var sourceRadios = document.querySelectorAll('input[name="source_mode"]');
    var localFields = document.getElementById('remote-update-local-fields');
    var linkFields = document.getElementById('remote-update-link-fields');
    if (!sourceRadios.length || !localFields || !linkFields) return;

    function syncSourceFields() {
        var selected = document.querySelector('input[name="source_mode"]:checked');
        var mode = selected && selected.value === 'link' ? 'link' : 'local';
        localFields.hidden = mode !== 'local';
        linkFields.hidden = mode !== 'link';
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', syncSourceFields);
    });
    syncSourceFields();
}());
</script>

<?php include __DIR__ . '/footer.php'; ?>
