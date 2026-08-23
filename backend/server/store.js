// ============================================================
// withU 数据存取层：统一的 JSON 持久化（带内存缓存）
// 所有数据文件集中在 withu-server/data/ 与 withu-site/services/
// ============================================================
const fs = require('fs');
const path = require('path');

const DATA_DIR = path.join(__dirname, 'data');
const ROOT = path.join(__dirname, '..', '..', 'frontend');

const cache = {};

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function abs(rel) {
  if (ABS_MAP[rel]) return ABS_MAP[rel];
  if (path.isAbsolute(rel)) return rel;
  return path.join(DATA_DIR, rel);
}

// 特殊绝对路径映射（@ 前缀 key → 固定文件）
const ABS_MAP = {
  '@mapAll': path.join(ROOT, 'services', 'map-all.json'),
  '@lgConfig': path.join(__dirname, 'app-config.json')
};

function load(rel, def) {
  const f = abs(rel);
  if (cache[rel] !== undefined) return cache[rel];
  try {
    const raw = fs.readFileSync(f, 'utf8');
    cache[rel] = JSON.parse(raw);
  } catch (e) {
    cache[rel] = def === undefined ? null : (typeof def === 'function' ? def() : JSON.parse(JSON.stringify(def)));
  }
  return cache[rel];
}

function save(rel, data) {
  ensureDir(DATA_DIR);
  cache[rel] = data;
  fs.writeFileSync(abs(rel), JSON.stringify(data, null, 2), 'utf8');
  return data;
}

function touch(rel, def) {
  if (load(rel, def) === null) save(rel, def);
  return cache[rel];
}

// ---- 具体数据文件 ----
const FILE = {
  interactions: 'interactions.json',
  chatData: 'chat-data.json',
  beacons: 'beacons.json',
  weatherConfig: 'weather-config.json',
  mapConfig: 'map-config.json',
  lgConfig: path.join(__dirname, 'app-config.json'),
  mapAll: path.join(ROOT, 'services', 'map-all.json')
};

function loadMapAll() {
  const m = load('@mapAll', { lovers: [], loveStartDate: '2023-07-19 00:00:00', milestones: [], moments: [], messages: [], albums: [], events: [] });
  return m;
}
function saveMapAll(m) {
  ensureDir(path.dirname(FILE.mapAll));
  cache['@mapAll'] = m;
  fs.writeFileSync(FILE.mapAll, JSON.stringify(m, null, 2), 'utf8');
  return m;
}

function loadLgConfig() {
  const c = load('@lgConfig', {});
  return c;
}
function saveLgConfig(c) {
  cache['@lgConfig'] = c;
  fs.writeFileSync(FILE.lgConfig, JSON.stringify(c, null, 2), 'utf8');
  return c;
}

function loadInteractions() {
  return touch(FILE.interactions, { stats: {}, likers: {} });
}
function saveInteractions(d) { return save(FILE.interactions, d); }

function loadChatData() {
  const d = load(FILE.chatData, null);
  return d;
}
function saveChatData(d) { return save(FILE.chatData, d); }

function loadBeacons() {
  return touch(FILE.beacons, { records: [] });
}
function saveBeacons(d) { return save(FILE.beacons, d); }

function loadWeatherConfig() {
  return touch(FILE.weatherConfig, { cities: {}, ip: {} });
}
function saveWeatherConfig(d) { return save(FILE.weatherConfig, d); }

function loadMapConfig() {
  return touch(FILE.mapConfig, {
    amapKey: '',
    securityMode: 'proxy',   // 'proxy' | 'jsCode'
    securityJsCode: '',
    serviceHost: '',          // 留空自动用当前 origin
    mapStyle: 'amap://styles/normal'
  });
}
function saveMapConfig(d) { return save(FILE.mapConfig, d); }

module.exports = {
  DATA_DIR,
  load,
  save,
  touch,
  loadMapAll,
  saveMapAll,
  loadLgConfig,
  saveLgConfig,
  loadInteractions,
  saveInteractions,
  loadChatData,
  saveChatData,
  loadBeacons,
  saveBeacons,
  loadWeatherConfig,
  saveWeatherConfig,
  loadMapConfig,
  saveMapConfig,
  loadLovelist,
  saveLovelist,
  listPhotos,
  loadPhotosMeta,
  savePhotosMeta
};

// ---- 相册照片：Lovefolder 扫描 + 元数据 ----
const PHOTOS_META_FILE = path.join(__dirname, 'admin-data', 'photos-meta.json');
const PHOTOS_DIR = path.join(ROOT, 'Lovefolder');

let _photoCache = null;
let _photoCacheAt = 0;
function listPhotos() {
  if (_photoCache && (Date.now() - _photoCacheAt) < 3000) return _photoCache;
  const exts = ['.webp', '.jpg', '.jpeg', '.gif', '.png'];
  const out = [];
  if (fs.existsSync(PHOTOS_DIR)) {
    for (const f of fs.readdirSync(PHOTOS_DIR)) {
      const ext = path.extname(f).toLowerCase();
      if (!exts.includes(ext)) continue;
      const thumb = f.replace(/(\.\w+)$/, '_thumb$1');
      out.push({
        photo_url: 'Lovefolder/' + f,
        photo_thumb: fs.existsSync(path.join(PHOTOS_DIR, thumb)) ? 'Lovefolder/' + thumb : 'Lovefolder/' + f,
        photo_type: 0,
        photo_text: '',
        photo_byname: '',
        photo_date: f.replace(/^(\d{4})(\d{2})(\d{2}).*$/, '$1-$2-$3')
      });
    }
  }
  out.sort((a, b) => b.photo_date.localeCompare(a.photo_date));
  _photoCache = out;
  _photoCacheAt = Date.now();
  return out;
}
function loadPhotosMeta() {
  try { return JSON.parse(fs.readFileSync(PHOTOS_META_FILE, 'utf8')); }
  catch (e) { return {}; }
}
function savePhotosMeta(meta) {
  ensureDir(path.dirname(PHOTOS_META_FILE));
  fs.writeFileSync(PHOTOS_META_FILE, JSON.stringify(meta, null, 2), 'utf8');
  return meta;
}

// ---- 愿望清单（与 admin.js 共用同一文件） ----
const LOVELIST_FILE = path.join(__dirname, 'admin-data', 'lovelist.json');

function defaultLovelist() {
  return [
    { id: 6, icon: 1, title: '一起看海边的日落', finish_date: '2024-10-03', city: '广东 · 深圳', remark: '橘子汽水味的傍晚', lng: 114.541, lat: 22.542, imgurl: [] },
    { id: 5, icon: 1, title: '吃遍大学城小吃街', finish_date: '2024-08-21', city: '湖北 · 武汉', remark: '三鲜豆皮最好吃', lng: 114.413, lat: 30.514, imgurl: [] },
    { id: 4, icon: 1, title: '一起过第一个新年', finish_date: '2024-02-10', city: '广东 · 珠海', remark: '烟花和你都在', lng: 113.576, lat: 22.270, imgurl: [] },
    { id: 3, icon: 0, title: '去冰岛看极光', finish_date: '', city: '冰岛 · 雷克雅未克', remark: '攒钱计划进行中', lng: -21.942, lat: 64.146, imgurl: [] },
    { id: 2, icon: 0, title: '养一只叫奶糖的猫', finish_date: '', city: '我们的家', remark: '', lng: 0, lat: 0, imgurl: [] },
    { id: 1, icon: 0, title: '拍一套复古婚纱照', finish_date: '', city: '待定', remark: '胶片感', lng: 0, lat: 0, imgurl: [] }
  ];
}
function loadLovelist() {
  try { return JSON.parse(fs.readFileSync(LOVELIST_FILE, 'utf8')); }
  catch (e) { return defaultLovelist(); }
}
function saveLovelist(list) {
  ensureDir(path.dirname(LOVELIST_FILE));
  fs.writeFileSync(LOVELIST_FILE, JSON.stringify(list, null, 2), 'utf8');
  return list;
}
