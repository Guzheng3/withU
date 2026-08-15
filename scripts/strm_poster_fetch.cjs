// strm_poster_fetch.cjs —— withUstrm 媒体库豆瓣海报兜底抓取桥（node）
// ------------------------------------------------------------
// withu 的 PHP 直连豆瓣会被反爬（418），改用 node 桥抓取：
//   对每个无海报的 strm 媒体，按标题搜豆瓣 subject_suggest 拿第一张海报，
//   下载到 <withu根>/runtime/strm-posters/<id>.jpg（不入 git，缓存 7 天）。
// 用法：node scripts/strm_poster_fetch.cjs '<json>'   或 从 stdin 读
//   json: [{"id":5,"title":"临江仙"}, ...]
// 输出：{"ok":true,"results":[{"id":5,"ok":true,"img":"https://..."|null}, ...]}
// ------------------------------------------------------------
const fs = require('fs');
const path = require('path');

const REPO = path.dirname(__dirname);               // repo/
const RUNTIME = path.join(path.dirname(REPO), 'runtime', 'strm-posters');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0';

async function searchPoster(title) {
  const url = 'https://movie.douban.com/j/subject_suggest?q=' + encodeURIComponent(title);
  const r = await fetch(url, {
    headers: { 'User-Agent': UA, 'Referer': 'https://movie.douban.com/', 'Accept': 'application/json' },
  });
  if (r.status !== 200) return null;
  const arr = await r.json().catch(() => []);
  if (!Array.isArray(arr) || !arr.length) return null;
  const hit = arr.find((x) => (x.title || '').includes(title)) || arr[0];
  return hit && hit.img ? hit.img : null;
}

async function download(url, file) {
  const r = await fetch(url, {
    headers: { 'User-Agent': UA, 'Referer': 'https://movie.douban.com/', 'Accept': 'image/*' },
  });
  if (r.status !== 200) return false;
  const buf = Buffer.from(await r.arrayBuffer());
  if (buf.length < 200) return false;
  fs.writeFileSync(file, buf);
  return true;
}

(async () => {
  let input = process.argv[2] || '';
  if (!input) {
    try { input = fs.readFileSync(0, 'utf8'); } catch (e) {}
  }
  let list = [];
  try { list = JSON.parse(input); }
  catch (e) {
    // Windows 下命令行传 JSON 有转义问题 → 支持 base64 输入
    try { list = JSON.parse(Buffer.from(input.trim(), 'base64').toString('utf8')); }
    catch (e2) {
      console.log(JSON.stringify({ ok: false, error: 'bad json' }));
      process.exit(1);
    }
  }
  fs.mkdirSync(RUNTIME, { recursive: true });
  const results = [];
  for (const it of list) {
    const id = Number(it.id || 0);
    const title = String(it.title || '').trim();
    const file = path.join(RUNTIME, id + '.jpg');
    let ok = false, cached = false, img = null;
    try {
      if (id && title && fs.existsSync(file) && Date.now() - fs.statSync(file).mtimeMs < 7 * 24 * 3600 * 1000) {
        ok = true; cached = true;
      } else if (id && title) {
        img = await searchPoster(title);
        if (img) ok = await download(img, file);
      }
    } catch (e) { ok = false; }
    results.push({ id, ok, cached, img: cached ? (fs.existsSync(file) ? '/api/strm.php?action=img&id=' + id : null) : img });
    await new Promise((r) => setTimeout(r, 250));
  }
  console.log(JSON.stringify({ ok: true, results }));
})();
