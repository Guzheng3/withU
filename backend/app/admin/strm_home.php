<?php
/**
 * 媒体库 STRM - 后台内嵌页
 *
 * 不新开窗口：把 withUstrm 完整界面直接嵌入后台主内容区（iframe），
 * 保留后台侧边栏/顶栏，仍走 /admin/strm.php/ 鉴权网关（未登录 302 拦截）。
 */
$adminPage = 'strm';
require_once __DIR__ . '/header.php';
?>
<div class="strm-frame-wrap">
    <iframe id="strmFrame"
            class="strm-frame"
            src="/admin/strm.php/"
            title="媒体库 STRM"
            allow="fullscreen"
            loading="eager"></iframe>
</div>
<script>
(function () {
    'use strict';
    // 让 iframe 高度自适应剩余视口，保持后台布局不滚动、strm 内部自行滚动
    function fit() {
        var f = document.getElementById('strmFrame');
        if (!f) { return; }
        var top = f.getBoundingClientRect().top;
        var h = window.innerHeight - top - 10;
        if (h < 320) { h = 320; }
        f.style.height = Math.round(h) + 'px';
    }
    fit();
    window.addEventListener('resize', fit);
    // 布局稳定后（字体/图片就绪）再校正一次
    setTimeout(fit, 60);
    setTimeout(fit, 400);
    setTimeout(fit, 1200);
})();
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
