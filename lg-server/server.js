// 本地静态服务器：work/site 根 + 服务桩路由（静态化动态接口）
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'lg-site');
const PORT = parseInt(process.env.PORT || '8899', 10);

const admin = require('./admin.js');

const MIME = {
  '.html': 'text/html; charset=utf-8', '.php': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8', '.svg': 'image/svg+xml',
  '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.gif': 'image/gif', '.webp': 'image/webp', '.ico': 'image/x-icon',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf',
  '.otf': 'font/otf', '.eot': 'application/vnd.ms-fontobject',
  '.mp3': 'audio/mpeg', '.mp4': 'video/mp4', '.cur': 'image/x-icon',
  '.map': 'application/json', '.wasm': 'application/wasm', '.lrc': 'text/plain; charset=utf-8'
};

// 语录库（留言编辑器「随机一句」+ 首页结语）
const QUOTES = [
  '所爱隔山海，山海皆可平。',
  '你是我所有的少女情怀和心之所向。',
  '世界万物，你是归途。',
  '初见是惊鸿一瞥，重逢是始料未及。',
  '浮世万千，吾爱有三，日、月与卿。',
  '山水一程，三生有幸。',
  '你的名字，是我见过最短的情诗。',
  '人间烟火气，最抚凡人心。',
  '愿有岁月可回首，且以深情共白头。',
  '满目山河空念远，不如怜取眼前人。',
  '你是落日弥漫的橘，天边透亮的星。',
  '晚风踩着云朵，月亮贩售快乐。',
  '入目无别人，四下皆是你。',
  '这世间青山灼灼，星光杳杳，而你眉眼如初。',
  '好的爱情是你通过一个人看到整个世界。',
  '山河远阔，人间烟火，无一是你，无一不是你。'
];

const json = obj => ({ inline: JSON.stringify(obj) });

function readMapAll() {
  const f = path.join(ROOT, 'services', 'map-all.json');
  if (!fs.existsSync(f)) return null;
  try { return JSON.parse(fs.readFileSync(f, 'utf8')); } catch (e) { return null; }
}

// 扫 Lovefolder 全部图片（相册照片数据源）
let _photoCache = null;
function listPhotos() {
  if (_photoCache) return _photoCache;
  const dir = path.join(ROOT, 'Lovefolder');
  const exts = ['.webp', '.jpg', '.jpeg', '.gif', '.png'];
  const out = [];
  if (fs.existsSync(dir)) {
    for (const f of fs.readdirSync(dir)) {
      const ext = path.extname(f).toLowerCase();
      if (!exts.includes(ext)) continue;
      const thumb = f.replace(/(\.\w+)$/, '_thumb$1');
      out.push({
        photo_url: 'Lovefolder/' + f,
        photo_thumb: fs.existsSync(path.join(dir, thumb)) ? 'Lovefolder/' + thumb : 'Lovefolder/' + f,
        photo_type: 0,
        photo_text: '',
        photo_byname: '',
        photo_date: f.replace(/^(\d{4})(\d{2})(\d{2}).*$/, '$1-$2-$3')
      });
    }
  }
  out.sort((a, b) => b.photo_date.localeCompare(a.photo_date));
  _photoCache = out;
  return out;
}

// 文章 id 列表（从 articles.html 链接提取）
function listArticleIds() {
  const f = path.join(ROOT, 'articles.html');
  if (!fs.existsSync(f)) return [];
  const html = fs.readFileSync(f, 'utf8');
  const ids = new Set();
  for (const m of html.matchAll(/page\.html\?id=(\d+)/g)) ids.add(parseInt(m[1], 10));
  return [...ids];
}

// timeline 数据合成（milestones + events）
function buildTimeline() {
  const m = readMapAll();
  const items = [];
  const MK = { '01': '一月', '02': '二月', '03': '三月', '04': '四月', '05': '五月', '06': '六月', '07': '七月', '08': '八月', '09': '九月', '10': '十月', '11': '十一月', '12': '十二月' };
  if (m) {
    for (const ms of (m.milestones || [])) {
      const d = String(ms.date || '').split('-');
      if (d.length >= 3) {
        items.push({
          id: 'm-' + items.length, type: 'text', title: ms.title || '纪念日', content: ms.desc || '',
          year: parseInt(d[0], 10), month: parseInt(d[1], 10), day: parseInt(d[2], 10),
          month_cn: MK[d[1]] || d[1], time: '', author_id: 2
        });
      }
    }
    for (const ev of (m.events || [])) {
      if (!ev.date) continue;
      const d = String(ev.date).split('-');
      if (d.length >= 3) {
        items.push({
          id: 'e-' + ev.id, type: 'image', title: ev.name || '事件', content: '',
          year: parseInt(d[0], 10), month: parseInt(d[1], 10), day: parseInt(d[2], 10),
          month_cn: MK[d[1]] || d[1], time: '', author_id: 1,
          images: ev.image ? [{ url: ev.image, thumb: ev.thumb ? ('Lovefolder/' + ev.thumb.split('/').pop()) : null }] : []
        });
      }
    }
  }
  items.sort((a, b) => (a.year - b.year) || (a.month - b.month) || (a.day - b.day));
  return items;
}

// 服务桩：静态化动态接口
function serviceStub(urlPath, reqUrl, body) {
  // 天气
  if (urlPath.includes('services/weather')) {
    const f = path.join(ROOT, 'services', 'weather.json');
    if (fs.existsSync(f)) return { file: f, json: true };
  }
  // 地图
  if (urlPath.includes('assets/map-api') || urlPath.includes('map-api.php')) {
    const q = new URL(reqUrl, 'http://x').searchParams.get('module');
    if (q === 'all' || !q) {
      const f = path.join(ROOT, 'services', 'map-all.json');
      if (fs.existsSync(f)) return { file: f, json: true };
    }
    return json({ albums: [], moments: [], messages: [], events: [], milestones: [], lovers: [], loveStartDate: '2023-07-19 00:00:00' });
  }
  // 时光碎片（map-all.moments 文章字段 → MomentCard 卡片字段）
  if (urlPath.includes('services/moments')) {
    const m = readMapAll();
    const raw = (m && m.moments) || [];
    const photos = listPhotos();
    const maleAvatar = 'Lovefolder/20260411043037_69d95ded97293201118237.webp';
    const femaleAvatar = 'Lovefolder/20260411043046_69d95df639c33274072975.webp';
    const items = raw.map((r, i) => {
      const img = photos.length ? photos[Math.floor(Math.random() * photos.length)].photo_url : maleAvatar;
      const isGirl = /really|女主/i.test(r.articlename || '');
      return {
        id: r.id || i + 1,
        type: 'image',
        url: img,
        original: '',
        img_code: '',
        publisher: { name: r.articlename || '我们', avatar: isGirl ? femaleAvatar : maleAvatar },
        publishTime: r.articletime || '',
        location: r.ipcity || '',
        date: (r.articletime || '').slice(0, 10),
        title: r.articletitle || '时光碎片',
        description: r.articletitle || '记录美好瞬间'
      };
    });
    return json(items);
  }
  // 相册照片列表（分页）
  if (urlPath.includes('services/photo-list')) {
    const q = new URL(reqUrl, 'http://x');
    const page = parseInt(q.searchParams.get('page') || '1', 10);
    const per = parseInt(q.searchParams.get('per_page') || '20', 10);
    const photos = listPhotos();
    const start = (page - 1) * per;
    const items = photos.slice(start, start + per);
    return json({ code: 200, data: { photos: items, counts: { total: photos.length }, pagination: { has_more: start + per < photos.length } } });
  }
  // 随机相册
  if (urlPath.includes('services/random_album')) {
    const m = readMapAll();
    const albums = (m && m.albums) || [];
    const a = albums.length ? albums[Math.floor(Math.random() * albums.length)] : null;
    return json({ code: 200, img_code: a ? a.code : '' });
  }
  // 随机文章
  if (urlPath.includes('services/random_article')) {
    const ids = listArticleIds();
    const id = ids.length ? ids[Math.floor(Math.random() * ids.length)] : 1;
    return json({ code: 200, id });
  }
  // 随机语录
  if (urlPath.includes('services/random_quote')) {
    return json({ text: QUOTES[Math.floor(Math.random() * QUOTES.length)] });
  }
  // 时间线
  if (urlPath.includes('services/timeline')) {
    return json({ code: 200, data: buildTimeline() });
  }
  // 清单搜索
  // 清单搜索（返回演示清单，任意查询码均可体验完整交互）
  if (urlPath.includes('services/lovelist-search')) {
    const demoItems = [
      { id: 6, icon: 1, title: '一起看海边的日落', finish_date: '2024-10-03', city: '广东 · 深圳', remark: '橘子汽水味的傍晚', lng: 114.541, lat: 22.542, imgurl: [] },
      { id: 5, icon: 1, title: '吃遍大学城小吃街', finish_date: '2024-08-21', city: '湖北 · 武汉', remark: '三鲜豆皮最好吃', lng: 114.413, lat: 30.514, imgurl: [] },
      { id: 4, icon: 1, title: '一起过第一个新年', finish_date: '2024-02-10', city: '广东 · 珠海', remark: '烟花和你都在', lng: 113.576, lat: 22.270, imgurl: [] },
      { id: 3, icon: 0, title: '去冰岛看极光', finish_date: '', city: '冰岛 · 雷克雅未克', remark: '攒钱计划进行中', lng: -21.942, lat: 64.146, imgurl: [] },
      { id: 2, icon: 0, title: '养一只叫奶糖的猫', finish_date: '', city: '我们的家', remark: '', lng: 0, lat: 0, imgurl: [] },
      { id: 1, icon: 0, title: '拍一套复古婚纱照', finish_date: '', city: '待定', remark: '胶片感', lng: 0, lat: 0, imgurl: [] }
    ];
    return json(demoItems);
  }
  // 留言提交
  if (urlPath.includes('services/message.php')) {
    return json({ Status: true, message: '留言成功' });
  }
  // 聊天数据（empty → 前端加载内置 mock 对话）
  if (urlPath.includes('services/chat-data')) {
    return json({ status: 'empty', message: '暂无对话数据' });
  }
  // 加密页暗号校验（任意暗号解锁，交互完整）
  if (urlPath.includes('EncryptCheck')) {
    return json({ success: true, message: '密码验证通过' });
  }
  // 留言列表 / 回复
  if (urlPath.includes('services/message-list')) {
    const q = new URL(reqUrl, 'http://x');
    const action = q.searchParams.get('action') || 'list';
    const data = readMapAll();
    const all = (data && data.messages) || [];
    if (action === 'replies') {
      const parentId = q.searchParams.get('parent_id');
      const parent = all.find(m => String(m.id) === String(parentId)) || null;
      const replies = all.filter(m => String(m.parentId) === String(parentId)).map(m => Object.assign({}, m, { replyCount: all.filter(x => String(x.parentId) === String(m.id)).length }));
      return json({ code: 200, data: { parent: parent ? Object.assign({}, parent, { replyCount: replies.length }) : null, replies } });
    }
    const offset = parseInt(q.searchParams.get('offset') || '0', 10);
    const limit = parseInt(q.searchParams.get('limit') || '20', 10);
    const tops = all.filter(m => !m.parentId);
    const items = tops.slice(offset, offset + limit).map(m => Object.assign({}, m, { replyCount: all.filter(x => String(x.parentId) === String(m.id)).length }));
    return json({ code: 200, data: { items, pagination: { has_more: offset + limit < tops.length } } });
  }
  // 交互：点赞/留言等 → 空成功
  if (urlPath.includes('services/interaction')) {
    return json({ code: 0, msg: 'ok', data: {} });
  }
  // 访问信标
  if (urlPath.includes('services/access-beacon') || urlPath.includes('access-beacon')) {
    return json({ code: 0, msg: 'ok' });
  }
  // 信息服务：geo / 随机语录 / qq 头像
  if (urlPath.includes('services/info-service')) {
    let action = '';
    if (body) {
      try { action = (typeof body === 'string' ? JSON.parse(body) : body).action || ''; }
      catch (e) { const m = /(?:^|&)action=([^&]*)/.exec(body); if (m) action = decodeURIComponent(m[1]); }
    }
    if (action === 'geo') return json({ Status: true, city: '湖北 · 武汉', province: '湖北', adcode: 420100 });
    if (action === 'qq') {
      let qq = '';
      try { qq = (typeof body === 'string' ? JSON.parse(body) : body).qq || ''; }
      catch (e) { const m = /(?:^|&)qq=([^&]*)/.exec(body); if (m) qq = decodeURIComponent(m[1]); }
      return json({ Status: true, data: { qq_hash: 'local-' + qq, avatar: qq ? 'https://q1.qlogo.cn/g?b=qq&nk=' + qq + '&s=100' : '' } });
    }
    return json({ Status: true, randomContent: QUOTES[Math.floor(Math.random() * QUOTES.length)] });
  }
  // 音乐接口
  if (urlPath.includes('services/music-player-data')) {
    const f = path.join(ROOT, 'services', 'music-player-data.json');
    if (fs.existsSync(f)) return { file: f, json: true };
  }
  return null;
}

const server = http.createServer((req, res) => {
  let body = '';
  req.on('data', d => { body += d; });
  req.on('end', () => {
    let urlPath = decodeURIComponent(req.url.split('?')[0].split('#')[0]);
    if (urlPath === '/') urlPath = '/index.html';

    if (urlPath.startsWith('/admin')) console.log('[req]', req.method, urlPath);
    if (admin.mount(req, res, body, urlPath)) return;

    const stub = serviceStub(urlPath, req.url, body);
    let filePath = null, inline = null, jsonFlag = false;
    if (stub) {
      if (stub.file) { filePath = stub.file; jsonFlag = stub.json; }
      else if (stub.inline) { inline = stub.inline; }
    }

    if (!filePath && !inline) {
      if (urlPath.startsWith('/ext/')) {
        filePath = path.join(ROOT, '_external', urlPath.slice('/ext/'.length));
      } else {
        filePath = path.join(ROOT, urlPath);
      }
      if (!fs.existsSync(filePath) && filePath.endsWith('.php')) {
        const alt = filePath.slice(0, -4) + '.html';
        if (fs.existsSync(alt)) filePath = alt;
      }
    }

    if (inline !== null) {
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-cache' });
      res.end(inline);
      return;
    }

    const rel = path.relative(ROOT, filePath);
    if (rel.startsWith('..') || path.isAbsolute(rel)) {
      res.writeHead(403); res.end('403'); return;
    }

    fs.readFile(filePath, (err, data) => {
      if (err) { res.writeHead(404); res.end('404: ' + urlPath); return; }
      // 相册详情：按 code 动态渲染（公开相册→正常页；私密/未知→加密壳）
      if (urlPath === '/album-detail.html' || urlPath === '/album-detail.php') {
        const q = new URL(req.url, 'http://x');
        const code = q.searchParams.get('code') || '';
        const map = readMapAll();
        const album = map && map.albums ? map.albums.find(a => String(a.code) === String(code)) : null;
        const privateShell = path.join(ROOT, 'album-detail-private.html');
        if (album) {
          let html = data.toString('utf8');
          html = html.replace(/data-code="[^"]*"/, 'data-code="' + code + '"');
          html = html.replace(/data-album-name="[^"]*"/, 'data-album-name="' + (album.name || '') + '"');
          html = html.replace(/<title>[^<]*<\/title>/, '<title>' + (album.name || '相册') + ' — LG-NewUi Demo</title>');
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-cache' });
          res.end(html);
          return;
        }
        if (fs.existsSync(privateShell)) {
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-cache' });
          res.end(fs.readFileSync(privateShell));
          return;
        }
      }
      // ---- LG_CONFIG 注入：后台保存的 lg-config.json → 页面 window.LG_CONFIG（首页/各页） ----
      if (/^\/(index|about|albums|messages|timeline|lovelist|articles)(\.html|\.php)?$/.test(urlPath) || urlPath === '/') {
        const cfgFile = path.join(__dirname, 'lg-config.json');
        if (fs.existsSync(cfgFile)) {
          try {
            const cfg = JSON.parse(fs.readFileSync(cfgFile, 'utf8')).LG_CONFIG || {};
            let html = data.toString('utf8');
            const m = html.match(/window\.LG_CONFIG = Object\.assign\(window\.LG_CONFIG \|\| \{\}, \{[\s\S]*?\}\);/);
            if (m) {
              const safe = JSON.stringify(cfg).replace(/</g, '\\u003c');
              html = html.replace(m[0], 'window.LG_CONFIG = Object.assign(window.LG_CONFIG || {}, ' + safe + ');');
              // 同时驱动 <title> 与顶部 logo 品牌名（演示后台改配置 → 前台反映）
              const t = cfg.title || 'LG-NewUi Demo';
              html = html.replace(/<title>[^<]*<\/title>/, '<title>' + t + ' — 小手一牵 岁岁年年～ 又一年了</title>');
              data = Buffer.from(html, 'utf8');
              console.log('[lg-config] injected for', urlPath);
            }
          } catch (e) { console.error('[lg-config inject err]', e.message); }
        }
      }
      const ext = path.extname(filePath).toLowerCase();
      res.writeHead(200, {
        'Content-Type': jsonFlag ? 'application/json; charset=utf-8' : (MIME[ext] || 'application/octet-stream'),
        'Cache-Control': 'no-cache'
      });
      res.end(data);
    });
  });
});

server.listen(PORT, () => console.log('LG local server on http://127.0.0.1:' + PORT));
process.on('SIGTERM', () => process.exit(0));
process.on('SIGINT', () => process.exit(0));
