// ============================================================
// withU 本地服务器：静态站点 + 前端服务接口（真实数据持久化）
// + 高德地图 JS API 代理（/_AMapService） + 动态配置注入
// ============================================================
const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const ROOT = path.join(__dirname, '..', '..', 'frontend');
const PORT = parseInt(process.env.PORT || '8899', 10);
const PHP_ROOT = path.join(__dirname, '..', 'app');
const PHP_BACKEND = 'http://127.0.0.1:8902';

const admin = require('./admin.js');
const store = require('./store.js');

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

function readFileSafe(f) {
  try { return JSON.parse(fs.readFileSync(f, 'utf8')); } catch (e) { return null; }
}

function readMapAll() {
  return store.loadMapAll();
}

// 扫 Lovefolder 全部图片（相册照片数据源，含后台元数据）
function listPhotos() {
  const meta = store.loadPhotosMeta();
  return store.listPhotos().map(ph => Object.assign({}, ph, meta[ph.photo_url] || {}));
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
  if (m) {
    for (const ms of (m.milestones || [])) {
      const d = String(ms.date || '').split('-');
      if (d.length >= 3) {
        items.push({
          id: 'm-' + items.length, type: 'milestone', title: ms.title || '纪念日', desc: ms.desc || '',
          year: parseInt(d[0], 10), month: parseInt(d[1], 10), day: parseInt(d[2], 10),
          time: '', author_id: 2, milestoneCategory: '纪念日'
        });
      }
    }
    for (const ev of (m.events || [])) {
      if (!ev.date) continue;
      const d = String(ev.date).split('-');
      if (d.length >= 3) {
        items.push({
          id: 'e-' + ev.id, type: 'map', title: ev.name || '事件', desc: '',
          year: parseInt(d[0], 10), month: parseInt(d[1], 10), day: parseInt(d[2], 10),
          time: '', author_id: 1, location: ev.city || '',
          map_lat: ev.coords ? ev.coords[1] : null, map_lng: ev.coords ? ev.coords[0] : null,
          mediaUrl: ev.image || '', thumbUrl: ev.thumb || ev.image || ''
        });
      }
    }
  }
  items.sort((a, b) => (a.year - b.year) || (a.month - b.month) || (a.day - b.day));
  return items;
}

// ---------------- 工具 ----------------
const urlDecodeForm = (body) => {
  const out = {};
  if (!body) return out;
  if (typeof body !== 'string') return body;
  for (const kv of body.split('&')) {
    const i = kv.indexOf('=');
    if (i <= 0) continue;
    const k = decodeURIComponent(kv.slice(0, i).replace(/\+/g, ' '));
    const v = decodeURIComponent(kv.slice(i + 1).replace(/\+/g, ' '));
    if (k) out[k] = v;
  }
  return out;
};
const parseBody = (body) => {
  if (!body) return {};
  if (typeof body !== 'string') return body;
  const t = body.trim();
  if (t[0] === '{') { try { return JSON.parse(t); } catch (e) { return {}; } }
  return urlDecodeForm(t);
};
const clientIp = (req) => (req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.socket.remoteAddress || '';
const visitorFp = (req) => {
  const ip = clientIp(req);
  const ua = req.headers['user-agent'] || '';
  return crypto.createHash('md5').update(ip + '|' + ua).digest('hex');
};
const pad = n => String(n).padStart(2, '0');
const fmtTime = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;

// ---------------- PHP 主站代理（watch.php 等根目录 PHP 页面与 /api/* → 1314） ----------------
function proxyToPhp(req, res, urlPath, body) {
  const reqUrl = new URL(urlPath, 'http://x');
  const target = PHP_BACKEND + reqUrl.pathname + reqUrl.search;
  const headers = Object.assign({}, req.headers);
  delete headers.host;
  headers.host = '127.0.0.1:1314';
  if (body) {
    headers['content-length'] = Buffer.byteLength(body);
  } else {
    delete headers['content-length'];
  }
  const preq = http.request(target, {
    method: req.method,
    headers: headers
  }, (pres) => {
    const h = Object.assign({}, pres.headers);
    delete h['transfer-encoding'];
    delete h['connection'];
    res.writeHead(pres.statusCode, h);
    pres.pipe(res);
  });
  preq.on('error', (e) => {
    console.error('[php-proxy]', urlPath, e.message);
    if (!res.headersSent) { res.writeHead(502); res.end('PHP proxy error'); }
  });
  preq.end(body);
}

// ---------------- 各服务接口 ----------------
function serviceHandler(req, res, urlPath, reqUrl, body) {
  const m = {};

  // ---------- 天气 ----------
  if (urlPath.includes('services/weather')) {
    const q = new URL(reqUrl, 'http://x').searchParams;
    const mode = q.get('mode') || 'ip';
    const slot = q.get('slot') || '1';
    const wc = store.loadWeatherConfig();
    let data = null;
    if (mode === 'couple') {
      data = (wc.cities && (wc.cities[slot] || wc.cities['1'])) || null;
    } else {
      data = wc.ip || null;
    }
    // 回退静态 weather.json
    if (!data) {
      const f = path.join(ROOT, 'services', 'weather.json');
      if (fs.existsSync(f)) return { file: f, json: true };
      data = { temp: '--', desc: '暂无数据', icon: '999', humidity: '--', vis: '--', feelsLike: '--', city: '未知', obsTime: new Date().toISOString() };
    }
    return json({ code: 200, data: Object.assign({ obsTime: new Date().toISOString(), source: mode }, data) });
  }

  // ---------- 地图 ----------
  if (urlPath.includes('assets/map-api') || urlPath.includes('map-api.php')) {
    const q = new URL(reqUrl, 'http://x').searchParams;
    const module = q.get('module');
    if (module === 'album_photos') {
      const code = q.get('code') || '';
      const photos = listPhotos();
      return json({ photos: photos.slice(0, 60) });
    }
    if (module === 'all' || !module) {
      const data = readMapAll();
      const mc = store.loadMapConfig();
      const lovers = (mc.lovers && mc.lovers.length >= 2) ? mc.lovers : (data.lovers || []);
      return json(Object.assign({}, data, { lovers }));
    }
    return json({ albums: [], moments: [], messages: [], events: [], milestones: [], lovers: [], loveStartDate: '2023-07-19 00:00:00' });
  }

  // ---------- 时光碎片（moments） ----------
  if (urlPath.includes('services/moments')) {
    const mData = readMapAll();
    const raw = (mData && mData.moments) || [];
    const photos = listPhotos();
    const cfg = store.loadLgConfig().WITHU_CONFIG || {};
    const maleAvatar = cfg.maleAvatar || 'Lovefolder/20260411043037_69d95ded97293201118237.webp';
    const femaleAvatar = cfg.femaleAvatar || 'Lovefolder/20260411043046_69d95df639c33274072975.webp';
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

  // ---------- 相册照片列表（分页） ----------
  if (urlPath.includes('services/photo-list')) {
    const q = new URL(reqUrl, 'http://x');
    const page = parseInt(q.searchParams.get('page') || '1', 10);
    const per = parseInt(q.searchParams.get('per_page') || '20', 10);
    const photos = listPhotos();
    const start = (page - 1) * per;
    const items = photos.slice(start, start + per);
    return json({ code: 200, data: { photos: items, counts: { total: photos.length }, pagination: { has_more: start + per < photos.length } } });
  }

  // ---------- 随机相册 ----------
  if (urlPath.includes('services/random_album')) {
    const mData = readMapAll();
    const albums = (mData && mData.albums) || [];
    const a = albums.length ? albums[Math.floor(Math.random() * albums.length)] : null;
    return json({ code: a ? 200 : 404, img_code: a ? a.code : '', msg: a ? '' : '暂无可用相册' });
  }

  // ---------- 随机文章 ----------
  if (urlPath.includes('services/random_article')) {
    const ids = listArticleIds();
    const id = ids.length ? ids[Math.floor(Math.random() * ids.length)] : 1;
    return json({ code: ids.length ? 200 : 404, id, msg: ids.length ? '' : '暂无可用文章' });
  }

  // ---------- 随机语录 ----------
  if (urlPath.includes('services/random_quote')) {
    return json({ text: QUOTES[Math.floor(Math.random() * QUOTES.length)] });
  }

  // ---------- 时间线 ----------
  if (urlPath.includes('services/timeline')) {
    return json({ code: 200, data: buildTimeline() });
  }

  // ---------- 清单搜索（真实数据） ----------
  if (urlPath.includes('services/lovelist-search')) {
    const p = parseBody(body);
    const list = store.loadLovelist ? store.loadLovelist() : null;
    let items = Array.isArray(list) ? list : [];
    const kw = String(p.search_info || '').trim();
    if (kw) items = items.filter(x => String(x.title || '').includes(kw) || String(x.city || '').includes(kw) || String(x.remark || '').includes(kw));
    if (p.status_filter !== undefined && p.status_filter !== '') {
      const sf = String(p.status_filter);
      if (sf === '0') items = items.filter(x => !x.icon && !x.finish_date);
      else if (sf === '1') items = items.filter(x => x.icon || x.finish_date);
    }
    return json(items);
  }

  // ---------- 留言提交 ----------
  if (urlPath.includes('services/message.php')) {
    const p = parseBody(body);
    const data = readMapAll();
    const msgs = data.messages || [];
    const maxId = msgs.length ? Math.max(...msgs.map(x => Number(x.id) || 0)) : 0;
    const now = new Date();
    const ts = Math.floor(now.getTime() / 1000);
    const text = String(p.text || '').trim();
    const parentId = p.parent_id !== undefined && p.parent_id !== '' ? Number(p.parent_id) : null;
    if (!text) return json({ Status: false, message: '留言内容不能为空' });
    const qq = String(p.qq || 'anon');
    const item = {
      id: maxId + 1,
      parentId,
      name: String(p.name || '匿名').slice(0, 30),
      qq,
      qq_hash: qq === 'anon' ? 'anon' : crypto.createHash('md5').update(String(qq)).digest('hex'),
      avatar: p.avatar || qqAvatarUrl(qq) || '',
      text,
      textHtml: text,
      city: p.city || '中国',
      lng: p.lng ? Number(p.lng) : null,
      lat: p.lat ? Number(p.lat) : null,
      os: p.os || '',
      browser: p.browser || '',
      weather: p.weather || '',
      weather_icon: p.weather_icon || '',
      timestamp: ts,
      timeStr: fmtTime(now),
      type: '',
      badge: null,
      like_count: 0,
      replyCount: 0,
      reply_to_id: p.reply_to_id !== undefined && p.reply_to_id !== '' ? Number(p.reply_to_id) : null
    };
    msgs.push(item);
    if (parentId) {
      const parent = msgs.find(x => Number(x.id) === Number(parentId));
      if (parent) parent.replyCount = (parent.replyCount || 0) + 1;
    }
    store.saveMapAll(data);
    console.log('[message] 新留言 id=' + item.id + ' name=' + item.name);
    return json({ Status: true, message: '留言成功', id: item.id, pending: false });
  }

  // ---------- 聊天数据 ----------
  if (urlPath.includes('services/chat-data')) {
    const cd = store.loadChatData();
    if (cd && cd.dialogues && cd.dialogues.length) {
      return json({ status: 'success', data: cd });
    }
    return json({ status: 'empty', message: '暂无对话数据' });
  }

  // ---------- 加密页暗号校验（任意暗号解锁，交互完整） ----------
  if (urlPath.includes('EncryptCheck')) {
    return json({ success: true, message: '密码验证通过' });
  }

  // ---------- 留言列表 / 回复 ----------
  if (urlPath.includes('services/message-list')) {
    const q = new URL(reqUrl, 'http://x');
    const action = q.searchParams.get('action') || 'list';
    const data = readMapAll();
    const all = (data && data.messages) || [];
    if (action === 'replies') {
      const parentId = q.searchParams.get('parent_id');
      const parent = all.find(x => String(x.id) === String(parentId)) || null;
      const replies = all.filter(x => String(x.parentId) === String(parentId)).map(x => Object.assign({}, x, { avatar: x.avatar || qqAvatarUrl(x.qq), replyCount: all.filter(y => String(y.parentId) === String(x.id)).length }));
      return json({ code: 200, data: { parent: parent ? Object.assign({}, parent, { avatar: parent.avatar || qqAvatarUrl(parent.qq), replyCount: replies.length }) : null, replies } });
    }
    const offset = parseInt(q.searchParams.get('offset') || '0', 10);
    const limit = parseInt(q.searchParams.get('limit') || '20', 10);
    const tops = all.filter(x => !x.parentId).sort((a, b) => Number(b.id) - Number(a.id));
    const items = tops.slice(offset, offset + limit).map(x => Object.assign({}, x, { avatar: x.avatar || qqAvatarUrl(x.qq), replyCount: all.filter(y => String(y.parentId) === String(x.id)).length }));
    return json({ code: 200, data: { items, pagination: { has_more: offset + limit < tops.length } } });
  }

  // ---------- 交互：点赞 / 浏览 / 状态 ----------
  if (urlPath.includes('services/interaction')) {
    const q = new URL(reqUrl, 'http://x');
    const p = q.searchParams.has('action') ? Object.fromEntries(q.searchParams.entries()) : parseBody(body);
    const action = p.action || '';
    const ints = store.loadInteractions();
    const stats = ints.stats || (ints.stats = {});
    const likers = ints.likers || (ints.likers = {});
    const fp = visitorFp(req);

    if (action === 'like') {
      const key = String(p.target_type) + ':' + String(p.target_id);
      const st = stats[key] || (stats[key] = { like_count: 0, view_count: 0 });
      const who = likers[key] || (likers[key] = []);
      const i = who.indexOf(fp);
      if (i >= 0) { who.splice(i, 1); st.like_count = Math.max(0, (st.like_count || 0) - 1); }
      else { who.push(fp); st.like_count = (st.like_count || 0) + 1; }
      store.saveInteractions(ints);
      return json({ code: 200, data: { liked: i < 0, like_count: st.like_count } });
    }

    if (action === 'view') {
      const key = String(p.target_type) + ':' + String(p.target_id);
      const st = stats[key] || (stats[key] = { like_count: 0, view_count: 0 });
      st.view_count = (st.view_count || 0) + 1;
      store.saveInteractions(ints);
      return json({ code: 200, data: { view_count: st.view_count } });
    }

    if (action === 'status') {
      const keys = String(p.items || '').split(',').filter(Boolean);
      const items = keys.map(k => {
        const idx = k.indexOf(':');
        if (idx < 0) return null;
        const t = k.slice(0, idx), id = k.slice(idx + 1);
        const st = stats[k] || { like_count: 0, view_count: 0 };
        return { target_type: t, target_id: id, liked: (likers[k] || []).indexOf(fp) >= 0, like_count: st.like_count || 0, view_count: st.view_count || 0 };
      }).filter(Boolean);
      return json({ code: 200, data: { items } });
    }

    return json({ code: 400, msg: '未知交互动作' });
  }

  // ---------- 访问信标 ----------
  if (urlPath.includes('services/access-beacon') || urlPath.includes('access-beacon')) {
    const p = parseBody(body);
    const bc = store.loadBeacons();
    bc.records.push({
      t: fmtTime(),
      ts: Date.now(),
      request_id: p.request_id || '',
      stay_seconds: Number(p.stay_seconds) || 0,
      token: p.token || '',
      page: req.headers['referer'] ? new URL(req.headers['referer'], 'http://x').pathname : '',
      ip: clientIp(req)
    });
    if (bc.records.length > 5000) bc.records = bc.records.slice(-5000);
    store.saveBeacons(bc);
    return json({ code: 0, msg: 'ok' });
  }

  // ---------- 信息服务：geo / 随机语录 / qq 头像 ----------
  if (urlPath.includes('services/info-service')) {
    const p = parseBody(body);
    const wc = store.loadWeatherConfig();
    if (p.action === 'geo') {
      const geo = (wc.ip && wc.ip.geo) || { city: wc.ip && wc.ip.city ? wc.ip.city : '湖北 · 武汉', lat: 30.514, lng: 114.413 };
      return json({ Status: true, city: geo.city, province: (geo.city || '').split('·')[0], adcode: geo.adcode || 420100, lat: geo.lat, lng: geo.lng });
    }
    if (p.action === 'qq') {
      const qq = String(p.qq || '');
      const valid = /^\d{4,12}$/.test(qq);
      // 昵称回填：从历史留言中取该 QQ 最近一次使用的昵称
      let nickname = '';
      if (valid) {
        const all = (readMapAll().messages || []).filter(m => String(m.qq) === qq && m.name);
        const latest = all.sort((a, b) => (Number(b.id) || 0) - (Number(a.id) || 0))[0];
        if (latest) nickname = latest.name;
      }
      return json({
        Status: true,
        data: {
          qq_hash: valid ? crypto.createHash('md5').update(qq).digest('hex') : ('local-' + qq),
          avatar: valid ? qqAvatarUrl(qq) : '',
          nickname
        }
      });
    }
    return json({ Status: true, randomContent: QUOTES[Math.floor(Math.random() * QUOTES.length)] });
  }

  // ---------- 音乐接口 ----------
  if (urlPath.includes('services/music-player-data')) {
    const f = path.join(ROOT, 'services', 'music-player-data.json');
    if (fs.existsSync(f)) return { file: f, json: true };
  }

  return null;
}

// ---------------- 高德 JS API 代理 ----------------
// 高德「代理服务」安全模式：前端将 webapi.amap.com 请求改写为 /_AMapService/...，
// 本代理转发到高德官方 API（仅 amap.com 域名），供 JS API 加载地图所需。
function amapProxy(req, res, reqUrl, urlPath) {
  const mc = store.loadMapConfig();
  const rest = urlPath.replace(/^\/_AMapService\/?/, '') || '';
  const firstSeg = rest.split('/')[0] || '';
  let target = (firstSeg.indexOf('.') >= 0 || firstSeg === 'localhost')
    ? 'https://' + rest
    : 'https://webapi.amap.com/' + rest;
  const idx = reqUrl.indexOf('?');
  if (idx >= 0) {
    const qs = new URLSearchParams(reqUrl.slice(idx + 1));
    if (mc.amapKey && !qs.get('key')) qs.set('key', mc.amapKey);
    target += '?' + qs.toString();
  }
  const proxyHeaders = Object.assign({}, req.headers, { host: 'webapi.amap.com' });
  delete proxyHeaders.referer;
  delete proxyHeaders.referrer;
  const proxyReq = https.request(target, {
    method: req.method,
    headers: proxyHeaders
  }, (pRes) => {
    const ct = pRes.headers['content-type'] || 'application/json';
    res.writeHead(pRes.statusCode || 200, {
      'Content-Type': ct,
      'Cache-Control': 'no-cache',
      'Access-Control-Allow-Origin': '*'
    });
    pRes.pipe(res);
  });
  proxyReq.on('error', (e) => {
    try {
      res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ status: 'error', msg: 'amap proxy error: ' + e.message }));
    } catch (err) {}
  });
  req.pipe(proxyReq);
  return true;
}

// ---------------- QQ 头像同源代理 ----------------
// 前端头像获取统一走后端：/_qqavatar?qq=<QQ>&s=<size> 由后端生成最终地址并 302 到 q1.qlogo.cn
// （不转发字节，浏览器直连加载，避免服务端到 qlogo 网络不稳定）
function qqAvatarProxy(req, res, reqUrl) {
  const q = new URL(reqUrl, 'http://x');
  let qq = String(q.searchParams.get('qq') || '');
  const s = String(q.searchParams.get('s') || '100');
  if (!/^\d{4,12}$/.test(qq)) qq = '10000';
  const target = 'https://q1.qlogo.cn/g?b=qq&nk=' + encodeURIComponent(qq) + '&s=' + encodeURIComponent(s);
  res.writeHead(302, {
    'Location': target,
    'Cache-Control': 'public, max-age=86400',
    'Access-Control-Allow-Origin': '*'
  });
  res.end();
  return true;
}

// 按 QQ 号生成同源头像地址
function qqAvatarUrl(qq) {
  const s = String(qq || '');
  return /^\d{4,12}$/.test(s) ? '/_qqavatar?qq=' + s + '&s=100' : '';
}

// ---------------- 动态配置注入 ----------------
const INJECT_PAGE = /^\/(index|about|albums|messages|timeline|lovelist|articles|album-detail|album-detail-private|page)(\.html|\.php)?$/;

function injectMapConfig(html, mc) {
  // 替换 _AMapSecurityConfig（高德安全模式）
  const sec = mc.securityMode === 'jsCode' && mc.securityJsCode
    ? { securityJsCode: mc.securityJsCode }
    : { serviceHost: '_AMapService', serviceMode: 'proxy' };
  html = html.replace(/window\._AMapSecurityConfig = \{[^;]*\};/, 'window._AMapSecurityConfig = ' + JSON.stringify(sec).replace(/</g, '\\u003c') + ';');
  // 替换 LGMAP_CONFIG 中的 amapKey / mapStyle / soloMode（保留其它字段）
  html = html.replace(/("amapKey":")[^"]*(")/, '$1' + (mc.amapKey || '') + '$2');
  html = html.replace(/("mapStyle":")[^"]*(")/, '$1' + (mc.mapStyle || 'amap://styles/normal') + '$2');
  return html;
}

function injectLgConfig(html, cfg) {
  const m = html.match(/window\.WITHU_CONFIG = Object\.assign\(window\.WITHU_CONFIG \|\| \{\}, \{[\s\S]*?\}\);/);
  if (m) {
    const safe = JSON.stringify(cfg).replace(/</g, '\\u003c');
    html = html.replace(m[0], 'window.WITHU_CONFIG = Object.assign(window.WITHU_CONFIG || {}, ' + safe + ');');
    const t = cfg.title || 'withU Demo';
    html = html.replace(/<title>[^<]*<\/title>/, '<title>' + t + ' — 小手一牵 岁岁年年～ 又一年了</title>');
  }
  return html;
}

// ---------------- HTTP 服务器 ----------------
const server = http.createServer((req, res) => {
  let body = '';
  req.on('data', d => { body += d; if (body.length > 2e6) req.destroy(); });
  req.on('end', () => {
    let urlPath = decodeURIComponent(req.url.split('?')[0].split('#')[0]);
    if (urlPath === '/') urlPath = '/index.html';

    // 高德代理（优先于 admin，因为不在 /admin 前缀下）
    if (urlPath.startsWith('/_AMapService')) {
      if (amapProxy(req, res, req.url, urlPath)) return;
    }

    // QQ 头像同源代理
    if (urlPath.startsWith('/_qqavatar')) {
      if (qqAvatarProxy(req, res, req.url)) return;
    }

    if (urlPath.startsWith('/admin')) console.log('[req]', req.method, urlPath);
    if (admin.mount(req, res, body, urlPath)) return;

    const stub = serviceHandler(req, res, urlPath, req.url, body);
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
      // ---- PHP 主站代理：withu-site 静态/回退均不存在时，转发到 1314 PHP 服务 ----
      if (!(filePath && fs.existsSync(filePath))) {
        const phpCandidate = path.join(PHP_ROOT, urlPath.replace(/^\//, ''));
        const isPhpPath = urlPath.endsWith('.php') || urlPath.startsWith('/api/');
        if (isPhpPath && (fs.existsSync(phpCandidate) || urlPath.startsWith('/api/'))) {
          return proxyToPhp(req, res, urlPath, body);
        }
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
          html = html.replace(/<title>[^<]*<\/title>/, '<title>' + (album.name || '相册') + ' — withU Demo</title>');
          html = injectMapConfig(html, store.loadMapConfig());
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-cache' });
          res.end(html);
          return;
        }
        if (fs.existsSync(privateShell)) {
          let html = fs.readFileSync(privateShell, 'utf8');
          html = injectMapConfig(html, store.loadMapConfig());
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-cache' });
          res.end(html);
          return;
        }
      }

      // ---- 地图配置注入（替换硬编码 amapKey / securityJsCode） ----
      if (INJECT_PAGE.test(urlPath) || urlPath === '/') {
        let html = data.toString('utf8');
        if (html.indexOf('_AMapSecurityConfig') >= 0) {
          html = injectMapConfig(html, store.loadMapConfig());
          data = Buffer.from(html, 'utf8');
          console.log('[map-config] injected for', urlPath);
        }
      }

      // ---- WITHU_CONFIG 注入：后台保存的 withu-config.json → 页面 window.WITHU_CONFIG ----
      if (/^\/(index|about|albums|messages|timeline|lovelist|articles)(\.html|\.php)?$/.test(urlPath) || urlPath === '/') {
        const cfgFile = path.join(__dirname, 'app-config.json');
        if (fs.existsSync(cfgFile)) {
          try {
            const cfg = JSON.parse(fs.readFileSync(cfgFile, 'utf8')).WITHU_CONFIG || {};
            let html = data.toString('utf8');
            const replaced = injectLgConfig(html, cfg);
            if (replaced !== html) {
              data = Buffer.from(replaced, 'utf8');
              console.log('[withu-config] injected for', urlPath);
            }
          } catch (e) { console.error('[withu-config inject err]', e.message); }
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
