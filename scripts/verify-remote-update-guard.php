<?php
/**
 * 发布前守卫自测：核对「填写的版本号」与 APK 内真实 versionName 是否一致。
 *
 * 用法:
 *   php scripts/verify-remote-update-guard.php <apk路径> <填写的版本号>
 *   php scripts/verify-remote-update-guard.php <apk路径> <填写的版本号> <期望的sha256>
 *
 * 退出码 0 表示一致（可以发布），1 表示不一致或读取失败（应拒绝发布）。
 *
 * 背景：曾出现记录写 5.2.0.7、摘要来自本地 5.2.0.6 包、实际分发 2.1.1-8 老包的事故，
 * 三者互不匹配照样发布成功。admin/remote_update.php 现在会在发布时拦截这类数据，
 * 本脚本用于在不发布的前提下先自测那道拦截。
 *
 * remote_update.php 加载时会执行鉴权与数据库初始化，无法直接 include，
 * 因此这里从源文件中抽出待测函数再执行，保证测的就是发布时真正跑的那份代码。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$apkPath = $argv[1] ?? '';
$declaredVersion = $argv[2] ?? '';
$expectedSha256 = strtolower(trim($argv[3] ?? ''));

if ($apkPath === '' || $declaredVersion === '') {
    fwrite(STDERR, "用法: php scripts/verify-remote-update-guard.php <apk路径> <填写的版本号> [期望的sha256]\n");
    exit(2);
}
if (!is_file($apkPath)) {
    fwrite(STDERR, "找不到文件: {$apkPath}\n");
    exit(2);
}

$sourcePath = __DIR__ . '/../backend/app/admin/remote_update.php';
$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "无法读取 {$sourcePath}\n");
    exit(2);
}

$startMarker = 'function withu_remote_update_format_bytes';
$endMarker = 'function withu_remote_update_source_label';
$start = strpos($source, $startMarker);
$end = strpos($source, $endMarker);
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "无法从 remote_update.php 抽出待测函数（标记可能已被改动）\n");
    exit(2);
}
eval(substr($source, $start, $end - $start));

$failed = false;

$info = withu_remote_update_apk_manifest_info($apkPath);
if ($info === null || $info['versionName'] === null) {
    echo "失败：无法从 APK 内读取 versionName（需要 zlib/gzinflate 或 php-zip）\n";
    exit(1);
}
printf("包内 versionName = %s, versionCode = %s\n", $info['versionName'], $info['versionCode']);
printf("归一化后：包内 %s / 填写 %s\n",
    withu_remote_update_normalize_version((string)$info['versionName']),
    withu_remote_update_normalize_version($declaredVersion)
);

$versionError = withu_remote_update_verify_apk_version($apkPath, $declaredVersion);
if ($versionError === '') {
    echo "版本号一致性：通过\n";
} else {
    $failed = true;
    echo "版本号一致性：拒绝\n  {$versionError}\n";
}

if ($expectedSha256 !== '') {
    $actualSha256 = strtolower((string)hash_file('sha256', $apkPath));
    printf("包 sha256 = %s\n", $actualSha256);
    if (hash_equals($expectedSha256, $actualSha256)) {
        echo "摘要一致性：通过\n";
    } else {
        $failed = true;
        echo "摘要一致性：拒绝\n  填写的 {$expectedSha256} 与包内实际 {$actualSha256} 不一致\n";
    }
}

exit($failed ? 1 : 0);
