<?php
// 首页结尾「一句话」随机文案接口，返回 { text: string }（page-index.js EpilogueQuote 消费）。
// 文案池为同目录 quotes.json（字符串数组），可自行增删；文件缺失或为空时使用内置默认文案。
//
// 防重复机制：会话内维护一条洗牌队列（随机不重复抽取）。
// 每次请求从队首弹出一条；队列耗尽后重洗一轮，并保证新一轮首句与上一句不同，
// 因此同一访问会话内连续 1314 次切换不会出现重复，跨轮次也不会紧邻重复。
// 池内容变化（md5 校验）时自动重建队列，避免索引越界或漏抽新词条。
header('Content-Type: application/json; charset=UTF-8');
session_start();

$default = '未完待续，敬请期待下一章的精彩。';
$text = $default;
$poolFile = __DIR__ . '/quotes.json';
if (is_file($poolFile)) {
    $pool = json_decode((string) file_get_contents($poolFile), true);
    if (is_array($pool)) {
        $pool = array_values(array_filter($pool, 'is_string'));
        $n = count($pool);
        if ($n > 0) {
            if ($n === 1) {
                $text = $pool[0];
            } else {
                $poolMd5 = md5_file($poolFile);
                if (!isset($_SESSION['quote_pool_md5']) || $_SESSION['quote_pool_md5'] !== $poolMd5) {
                    $_SESSION['quote_queue'] = [];
                    $_SESSION['quote_pool_md5'] = $poolMd5;
                }
                if (!isset($_SESSION['quote_queue']) || !is_array($_SESSION['quote_queue'])) {
                    $_SESSION['quote_queue'] = [];
                }
                // 只保留合法索引
                $_SESSION['quote_queue'] = array_values(array_filter(
                    $_SESSION['quote_queue'],
                    function ($i) use ($n) { return is_int($i) && $i >= 0 && $i < $n; }
                ));
                $queue = &$_SESSION['quote_queue'];
                $lastIdx = isset($_SESSION['quote_last']) ? (int) $_SESSION['quote_last'] : -1;
                if (!isset($pool[$lastIdx])) {
                    $lastIdx = -1;
                }
                if (count($queue) === 0) {
                    // 新一轮：重洗，并保证首句不与上一句重复
                    $queue = range(0, $n - 1);
                    shuffle($queue);
                    if ($lastIdx >= 0 && $queue[0] === $lastIdx) {
                        $j = array_rand(array_diff($queue, [$lastIdx]));
                        $queue[0] = $queue[$j];
                        $queue[$j] = $lastIdx;
                    }
                }
                $idx = array_shift($queue);
                $_SESSION['quote_last'] = $idx;
                $text = $pool[$idx];
            }
        }
    }
}
echo json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
