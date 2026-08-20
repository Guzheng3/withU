// ============================================================
// LG-NewUi 本地复刻站 · 管理后台模块（基于 LikeGirl 后台协议还原）
// 挂载到 server.js：require('./admin.js').mount(req, res, body, urlPath)
// 登录协议与 LikeGirl 一致：POST adminName + pw(md5) → session cookie
// 默认账号 admin / lovezz（与原版一致），安全码 Love
// 数据持久化：work/site/services/map-all.json + admin-data/*.json
// ============================================================
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', 'lg-site');
const DATA_DIR = path.join(__dirname, 'admin-data');
const MAP_FILE = path.join(ROOT, 'services', 'map-all.json');
const CONFIG_FILE = path.join(__dirname, 'lg-config.json');
const USERS_FILE = path.join(DATA_DIR, 'users.json');
const LOVELIST_FILE = path.join(DATA_DIR, 'lovelist.json');
const OPLOG_FILE = path.join(DATA_DIR, 'oplog.jsonl');

const SECRET = 'lg-local-admin-2026-secret';
const md5 = s => crypto.createHash('md5').update(String(s)).digest('hex');
const now = () => new Date().toLocaleString('zh-CN', { hour12: false });

// ---------- 数据层 ----------
function loadMap() {
  try { return JSON.parse(fs.readFileSync(MAP_FILE, 'utf8')); }
  catch (e) { return { lovers: [], loveStartDate: '2023-07-19 00:00:00', milestones: [], moments: [], messages: [], albums: [], events: [] }; }
}
function saveMap(m) { fs.mkdirSync(path.dirname(MAP_FILE), { recursive: true }); fs.writeFileSync(MAP_FILE, JSON.stringify(m, null, 2), 'utf8'); }

function loadConfig() {
  try { return JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8')); }
  catch (e) { return {}; }
}
function saveConfig(c) { fs.writeFileSync(CONFIG_FILE, JSON.stringify(c, null, 2), 'utf8'); }

function loadUsers() {
  try { return JSON.parse(fs.readFileSync(USERS_FILE, 'utf8')); }
  catch (e) { return { users: [{ user: 'admin', pw_md5: md5('lovezz'), code: 'Love', name: '管理员' }] }; }
}
function saveUsers(u) { fs.mkdirSync(DATA_DIR, { recursive: true }); fs.writeFileSync(USERS_FILE, JSON.stringify(u, null, 2), 'utf8'); }

function loadLovelist() {
  try { return JSON.parse(fs.readFileSync(LOVELIST_FILE, 'utf8')); }
  catch (e) {
    return [
      { id: 6, icon: 1, title: '一起看海边的日落', finish_date: '2024-10-03', city: '广东 · 深圳', remark: '橘子汽水味的傍晚', lng: 114.541, lat: 22.542, imgurl: [] },
      { id: 5, icon: 1, title: '吃遍大学城小吃街', finish_date: '2024-08-21', city: '湖北 · 武汉', remark: '三鲜豆皮最好吃', lng: 114.413, lat: 30.514, imgurl: [] },
      { id: 4, icon: 1, title: '一起过第一个新年', finish_date: '2024-02-10', city: '广东 · 珠海', remark: '烟花和你都在', lng: 113.576, lat: 22.270, imgurl: [] },
      { id: 3, icon: 0, title: '去冰岛看极光', finish_date: '', city: '冰岛 · 雷克雅未克', remark: '攒钱计划进行中', lng: -21.942, lat: 64.146, imgurl: [] },
      { id: 2, icon: 0, title: '养一只叫奶糖的猫', finish_date: '', city: '我们的家', remark: '', lng: 0, lat: 0, imgurl: [] },
      { id: 1, icon: 0, title: '拍一套复古婚纱照', finish_date: '', city: '待定', remark: '胶片感', lng: 0, lat: 0, imgurl: [] }
    ];
  }
}
function saveLovelist(list) { fs.mkdirSync(DATA_DIR, { recursive: true }); fs.writeFileSync(LOVELIST_FILE, JSON.stringify(list, null, 2), 'utf8'); }

function logOp(user, action, detail) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
  fs.appendFileSync(OPLOG_FILE, JSON.stringify({ t: now(), user, action, detail }) + '\n', 'utf8');
}
function readOps(limit) {
  try {
    const lines = fs.readFileSync(OPLOG_FILE, 'utf8').trim().split('\n').filter(Boolean);
    return lines.slice(-(limit || 50)).map(l => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean);
  } catch (e) { return []; }
}

// 相册照片数（Lovefolder 实际文件数）
function photoCount() {
  const dir = path.join(ROOT, 'Lovefolder');
  try { return fs.readdirSync(dir).filter(f => /\.(webp|jpg|jpeg|gif|png)$/i.test(f)).length; } catch (e) { return 0; }
}

// ---------- 认证 ----------
function sessionToken(user, pw_md5) { return md5(user + ':' + pw_md5 + ':' + SECRET); }

function parseCookies(req) {
  const out = {};
  const raw = req.headers.cookie || '';
  for (const kv of raw.split(';')) {
    const i = kv.indexOf('=');
    if (i > 0) out[kv.slice(0, i).trim()] = kv.slice(i + 1).trim();
  }
  return out;
}
function authUser(req) {
  const tok = parseCookies(req).lg_admin;
  if (!tok) return null;
  for (const u of loadUsers().users) {
    if (sessionToken(u.user, u.pw_md5) === tok) return u;
  }
  return null;
}

const json = (res, code, obj) => {
  if (res.headersSent) { console.error('[admin] headers already sent, skip write'); return; }
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-cache' });
  res.end(JSON.stringify(obj));
};

// ---------- API 分发 ----------
function apiRouter(req, res, body, urlPath, q) {
  const user = authUser(req);
  const needAuth = () => {
    if (!user) return json(res, 401, { ok: false, msg: '未登录或会话过期' });
    return null;
  };

  // ---- 登录（无需会话）----
  if (urlPath === '/admin/api/login' && req.method === 'POST') {
    let p = {};
    try { p = JSON.parse(body || '{}'); } catch (e) { return json(res, 400, { ok: false, msg: '参数错误' }); }
    const u = loadUsers().users.find(x => x.user === String(p.adminName || ''));
    if (!u || u.pw_md5 !== md5(String(p.pw || ''))) {
      logOp(p.adminName || '?', '登录失败', '用户名或密码错误');
      return json(res, 200, { ok: false, msg: '用户名或密码错误' });
    }
    logOp(u.user, '登录成功', '后台登录');
    res.writeHead(200, {
      'Content-Type': 'application/json; charset=utf-8',
      'Set-Cookie': 'lg_admin=' + sessionToken(u.user, u.pw_md5) + '; Path=/; HttpOnly; SameSite=Lax'
    });
    return res.end(JSON.stringify({ ok: true, msg: '登录成功', name: u.name || u.user }));
  }
  if (urlPath === '/admin/api/logout' && req.method === 'POST') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8', 'Set-Cookie': 'lg_admin=; Path=/; Max-Age=0' });
    return res.end(JSON.stringify({ ok: true }));
  }

  // 以下全部需要登录
  const authErr = needAuth();
  if (authErr) return authErr;

  // ---- 仪表盘 ----
  if (urlPath === '/admin/api/dashboard' && req.method === 'GET') {
    const m = loadMap();
    return json(res, 200, {
      ok: true,
      stats: {
        messages: (m.messages || []).length,
        albums: (m.albums || []).length,
        photos: photoCount(),
        moments: (m.moments || []).length,
        articles: (m.events || []).length,
        events: (m.events || []).length,
        milestones: (m.milestones || []).length,
        lovelist: loadLovelist().length
      },
      config: { title: (loadConfig().LG_CONFIG || {}).title || '', version: (loadConfig().LG_CONFIG || {}).version || '' },
      ops: readOps(12)
    });
  }

  // ---- 留言管理 ----
  if (urlPath === '/admin/api/messages' && req.method === 'GET') {
    const m = loadMap();
    const kw = (q.get('kw') || '').toLowerCase();
    let list = m.messages || [];
    if (kw) list = list.filter(x => (x.name || '').toLowerCase().includes(kw) || (x.text || '').toLowerCase().includes(kw));
    list = list.slice().sort((a, b) => String(b.id).localeCompare(String(a.id), 'zh', { numeric: true }));
    return json(res, 200, { ok: true, data: list.slice(0, 200), total: list.length });
  }
  if (urlPath === '/admin/api/message/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    const before = m.messages.length;
    m.messages = m.messages.filter(x => String(x.id) !== String(p.id));
    saveMap(m);
    logOp(user.user, '删除留言', 'id=' + p.id);
    return json(res, 200, { ok: true, removed: before - m.messages.length });
  }
  if (urlPath === '/admin/api/message/toggle-admin' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    const it = m.messages.find(x => String(x.id) === String(p.id));
    if (!it) return json(res, 404, { ok: false, msg: '留言不存在' });
    it.type = it.type === 'admin' ? (it.oldType || '') : 'admin';
    if (it.type === 'admin') { it.oldType = it.oldType || ''; it.badge = { type: 'admin', label: '管理员' }; }
    else { it.badge = null; }
    saveMap(m);
    logOp(user.user, '切换管理员徽章', 'id=' + p.id);
    return json(res, 200, { ok: true, type: it.type });
  }

  // ---- 相册管理 ----
  if (urlPath === '/admin/api/albums' && req.method === 'GET') {
    const m = loadMap();
    return json(res, 200, { ok: true, data: m.albums || [] });
  }
  if (urlPath === '/admin/api/album/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    if (!p.name) return json(res, 400, { ok: false, msg: '相册名不能为空' });
    const m = loadMap();
    const albums = m.albums || [];
    if (p.code) {
      const it = albums.find(a => String(a.code) === String(p.code));
      if (it) Object.assign(it, { name: p.name, city: p.city || '', desc: p.desc || '', date: p.date || '', private: !!p.private });
      logOp(user.user, '编辑相册', p.code);
    } else {
      const code = new Date().toISOString().replace(/[-T:]/g, '').slice(0, 14) + String(Math.floor(Math.random() * 100)).padStart(2, '0');
      albums.push({ code, name: p.name, city: p.city || '', desc: p.desc || '', date: p.date || '', image: p.image || '', thumb: p.thumb || '', count: 0, photoCount: 0, private: !!p.private });
      logOp(user.user, '新增相册', p.name);
    }
    saveMap(m);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/album/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    m.albums = (m.albums || []).filter(a => String(a.code) !== String(p.code));
    saveMap(m);
    logOp(user.user, '删除相册', 'code=' + p.code);
    return json(res, 200, { ok: true });
  }

  // ---- 时光碎片（moments）----
  if (urlPath === '/admin/api/moments' && req.method === 'GET') {
    const m = loadMap();
    return json(res, 200, { ok: true, data: m.moments || [] });
  }
  if (urlPath === '/admin/api/moment/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    if (!p.articletitle) return json(res, 400, { ok: false, msg: '标题不能为空' });
    const m = loadMap();
    const moments = m.moments || [];
    if (p.id) {
      const it = moments.find(x => String(x.id) === String(p.id));
      if (it) Object.assign(it, { articletitle: p.articletitle, articlename: p.articlename || '', articletime: p.articletime || '', ipcity: p.ipcity || '', coords: p.coords || '' });
    } else {
      const id = moments.length ? Math.max(...moments.map(x => Number(x.id) || 0)) + 1 : 1;
      moments.push({ id, articletitle: p.articletitle, articlename: p.articlename || '', articletime: p.articletime || new Date().toISOString().slice(0, 10), ipcity: p.ipcity || '', coords: p.coords || '', encryptionSwitch: 0 });
    }
    saveMap(m);
    logOp(user.user, '保存时光碎片', p.articletitle);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/moment/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    m.moments = (m.moments || []).filter(x => String(x.id) !== String(p.id));
    saveMap(m);
    logOp(user.user, '删除时光碎片', 'id=' + p.id);
    return json(res, 200, { ok: true });
  }

  // ---- 时间线（milestones + events）----
  if (urlPath === '/admin/api/timeline' && req.method === 'GET') {
    const m = loadMap();
    return json(res, 200, { ok: true, milestones: m.milestones || [], events: m.events || [] });
  }
  if (urlPath === '/admin/api/milestone/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    if (!p.date || !p.title) return json(res, 400, { ok: false, msg: '日期与标题必填' });
    const m = loadMap();
    const ms = m.milestones || [];
    if (p.oldDate && p.oldTitle) {
      const it = ms.find(x => x.date === p.oldDate && x.title === p.oldTitle);
      if (it) Object.assign(it, { date: p.date, title: p.title, desc: p.desc || '' });
    } else ms.push({ date: p.date, title: p.title, desc: p.desc || '' });
    saveMap(m);
    logOp(user.user, '保存里程碑', p.title);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/milestone/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    m.milestones = (m.milestones || []).filter(x => !(x.date === p.date && x.title === p.title));
    saveMap(m);
    logOp(user.user, '删除里程碑', p.title);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/event/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    if (!p.date || !p.name) return json(res, 400, { ok: false, msg: '日期与名称必填' });
    const m = loadMap();
    const ev = m.events || [];
    if (p.id) {
      const it = ev.find(x => String(x.id) === String(p.id));
      if (it) Object.assign(it, { date: p.date, name: p.name, city: p.city || '', done: !!p.done, image: p.image || '' });
    } else {
      const id = ev.length ? Math.max(...ev.map(x => Number(x.id) || 0)) + 1 : 1;
      ev.push({ id, date: p.date, name: p.name, city: p.city || '', done: !!p.done, image: p.image || '', thumb: p.image || '' });
    }
    saveMap(m);
    logOp(user.user, '保存时间线事件', p.name);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/event/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const m = loadMap();
    m.events = (m.events || []).filter(x => String(x.id) !== String(p.id));
    saveMap(m);
    logOp(user.user, '删除时间线事件', 'id=' + p.id);
    return json(res, 200, { ok: true });
  }

  // ---- 清单（lovelist）----
  if (urlPath === '/admin/api/lovelist' && req.method === 'GET') {
    return json(res, 200, { ok: true, data: loadLovelist() });
  }
  if (urlPath === '/admin/api/lovelist/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    if (!p.title) return json(res, 400, { ok: false, msg: '清单标题不能为空' });
    const list = loadLovelist();
    if (p.id) {
      const it = list.find(x => String(x.id) === String(p.id));
      if (it) Object.assign(it, { title: p.title, icon: p.icon ? 1 : 0, finish_date: p.finish_date || '', city: p.city || '', remark: p.remark || '' });
    } else {
      const id = list.length ? Math.max(...list.map(x => Number(x.id) || 0)) + 1 : 1;
      list.push({ id, icon: p.icon ? 1 : 0, title: p.title, finish_date: p.finish_date || '', city: p.city || '', remark: p.remark || '', lng: 0, lat: 0, imgurl: [] });
    }
    saveLovelist(list);
    logOp(user.user, '保存愿望清单', p.title);
    return json(res, 200, { ok: true });
  }
  if (urlPath === '/admin/api/lovelist/delete' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    saveLovelist(loadLovelist().filter(x => String(x.id) !== String(p.id)));
    logOp(user.user, '删除愿望清单', 'id=' + p.id);
    return json(res, 200, { ok: true });
  }

  // ---- 站点设置（lg-config.json）----
  if (urlPath === '/admin/api/config' && req.method === 'GET') {
    const c = loadConfig().LG_CONFIG || {};
    return json(res, 200, { ok: true, config: {
      title: c.title, version: c.version, boy: c.boy, girl: c.girl, startTime: c.startTime,
      userCity: c.userCity, weatherEnabled: c.weatherEnabled, soloMode: c.soloMode, maleName: c.maleName, femaleName: c.femaleName
    } });
  }
  if (urlPath === '/admin/api/config/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const full = loadConfig();
    const c = full.LG_CONFIG = full.LG_CONFIG || {};
    ['title', 'version', 'boy', 'girl', 'maleName', 'femaleName', 'startTime', 'userCity'].forEach(k => { if (p[k] !== undefined) c[k] = p[k]; });
    if (p.weatherEnabled !== undefined) c.weatherEnabled = !!p.weatherEnabled;
    if (p.soloMode !== undefined) c.soloMode = !!p.soloMode;
    saveConfig(full);
    logOp(user.user, '修改站点设置', 'title=' + (p.title || ''));
    return json(res, 200, { ok: true });
  }

  // ---- 账号管理 ----
  if (urlPath === '/admin/api/account' && req.method === 'GET') {
    return json(res, 200, { ok: true, users: loadUsers().users.map(u => ({ user: u.user, name: u.name })) });
  }
  if (urlPath === '/admin/api/account/password' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const us = loadUsers();
    const me = us.users.find(x => x.user === user.user);
    if (!me) return json(res, 401, { ok: false, msg: '会话异常' });
    if (String(p.code || '') !== String(me.code || '')) return json(res, 403, { ok: false, msg: '安全码错误（修改敏感信息需安全码二次验证）' });
    if (!p.newPw || String(p.newPw).length < 4) return json(res, 400, { ok: false, msg: '新密码至少 4 位' });
    me.pw_md5 = md5(String(p.newPw));
    saveUsers(us);
    logOp(user.user, '修改密码', 'user=' + user.user);
    return json(res, 200, { ok: true, msg: '密码已修改，请重新登录' });
  }
  if (urlPath === '/admin/api/account/save' && req.method === 'POST') {
    let p = {}; try { p = JSON.parse(body || '{}'); } catch (e) {}
    const us = loadUsers();
    if (p.newUser) {
      if (us.users.find(x => x.user === p.newUser)) return json(res, 400, { ok: false, msg: '账号已存在' });
      us.users.push({ user: p.newUser, pw_md5: md5(String(p.newPw || '123456')), code: String(p.code || 'Love'), name: p.name || p.newUser });
      logOp(user.user, '新增账号', p.newUser);
    } else if (p.delUser && p.delUser !== 'admin') {
      us.users = us.users.filter(x => x.user !== p.delUser);
      logOp(user.user, '删除账号', p.delUser);
    }
    saveUsers(us);
    return json(res, 200, { ok: true });
  }

  return json(res, 404, { ok: false, msg: '未知接口: ' + urlPath });
}

// ---------- 页面 ----------
function pageHandler(req, res, urlPath) {
  if (urlPath === '/admin/' || urlPath === '/admin' || urlPath === '/admin/index.html') {
    const user = authUser(req);
    const f = path.join(ROOT, '_external', 'lgadmin', 'index.html');
    if (!fs.existsSync(f)) { res.writeHead(404); res.end('admin page missing'); return; }
    let html = fs.readFileSync(f, 'utf8');
    html = html.replace('__BOOT_USER__', JSON.stringify(user ? { user: user.user, name: user.name } : null));
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-cache' });
    res.end(html);
    return true;
  }
  return false;
}

function mount(req, res, body, urlPath) {
  try {
  if (urlPath.startsWith('/admin/')) {
    const q = new URL(req.url, 'http://x').searchParams;
    if (urlPath.startsWith('/admin/api/')) { apiRouter(req, res, body, urlPath, q); return true; }
    if (pageHandler(req, res, urlPath)) return true;
    res.writeHead(404); res.end('admin 404');
    return true;
  }
  return false;
  } catch (e) {
    console.error('[admin] mount error:', e.message);
    try { if (!res.headersSent) { res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' }); res.end(JSON.stringify({ ok: false, msg: 'admin error: ' + e.message })); } } catch (e2) {}
    return true;
  }
}

module.exports = { mount };
