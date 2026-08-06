<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/withu.php';

$auth = new Auth();
$user = withu_require_couple_user($auth);
$themeConfig = withu_theme_config();
$themeInlineStyle = '';
foreach (($themeConfig['colors'] ?? []) as $themeName => $themeValue) {
    $themeInlineStyle .= '--withu-custom-' . $themeName . ':' . $themeValue . ';';
}
?>
<!doctype html>
<html lang="zh-CN" data-withu-theme="<?php echo e($themeConfig['preset']); ?>" data-withu-mode="<?php echo e($themeConfig['mode']); ?>"<?php if (!empty($themeConfig['custom'])): ?> data-withu-theme-custom="1" style="<?php echo e($themeInlineStyle); ?>"<?php endif; ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>天气与旅行 - withU</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/theme.css?v=withu-theme-20260719-3">
    <style>
        .travel{max-width:1000px;margin:2rem auto;padding:1rem}
        .travel-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .travel-card{padding:1rem;background:var(--withu-surface);border:1px solid var(--withu-border);border-radius:10px;box-shadow:var(--withu-shadow);color:var(--withu-text)}
        .travel input,.travel textarea{width:100%;padding:.65rem;margin:.3rem 0 .7rem;border:1px solid var(--withu-border);border-radius:.5rem;background:var(--withu-surface);color:var(--withu-text)}
        .travel pre{white-space:pre-wrap;line-height:1.7;color:var(--withu-text-muted)}
        @media(max-width:700px){.travel-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="travel">
    <h1>天气与旅行</h1>
    <p><a href="/">返回首页</a> · 规划结果会保存到情侣空间。</p>
    <div class="travel-grid">
        <section class="travel-card">
            <h2>天气</h2>
            <input id="lat" type="number" step="any" placeholder="纬度">
            <input id="lng" type="number" step="any" placeholder="经度">
            <button id="weather" class="btn btn-primary">查询天气</button>
            <pre id="weatherOut"></pre>
        </section>
        <section class="travel-card">
            <h2>AI 旅行规划</h2>
            <input id="destination" placeholder="目的地">
            <input id="start" type="date">
            <input id="end" type="date">
            <textarea id="prompt" rows="5" placeholder="例如：两天、轻松、适合拍照、预算有限"></textarea>
            <button id="plan" class="btn btn-primary">生成计划</button>
            <pre id="planOut"></pre>
        </section>
    </div>
</main>
<script>
var csrf=<?php echo json_encode(csrf_token()); ?>;
function text(id,value){document.getElementById(id).textContent=value;}
document.getElementById('weather').onclick=function(){
    var lat=document.getElementById('lat').value,lng=document.getElementById('lng').value;
    fetch('/api/travel.php?action=weather&lat='+encodeURIComponent(lat)+'&lng='+encodeURIComponent(lng))
        .then(function(r){return r.json();})
        .then(function(r){text('weatherOut',r.success?JSON.stringify(r.data,null,2):r.message);});
};
document.getElementById('plan').onclick=function(){
    var data={destination:document.getElementById('destination').value,start_date:document.getElementById('start').value,end_date:document.getElementById('end').value,prompt:document.getElementById('prompt').value,_token:csrf};
    fetch('/api/travel.php?action=plan',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(data)})
        .then(function(r){return r.json();})
        .then(function(r){text('planOut',r.success?JSON.stringify(r.plan,null,2):r.message);});
};
</script>
</body>
</html>
