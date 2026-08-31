<?php
// 首页结尾「一句话」随机文案接口，返回 { text: string }（page-index.js EpilogueQuote 消费）。
// 文案池为同目录 quotes.json（字符串数组），可自行增删；文件缺失或为空时使用内置默认文案。
header('Content-Type: application/json; charset=UTF-8');

$default = '未完待续，敬请期待下一章的精彩。';
$text = $default;
$poolFile = __DIR__ . '/quotes.json';
if (is_file($poolFile)) {
    $pool = json_decode((string) file_get_contents($poolFile), true);
    if (is_array($pool)) {
        $pool = array_values(array_filter($pool, 'is_string'));
        if ($pool) {
            $text = $pool[array_rand($pool)];
        }
    }
}
echo json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
