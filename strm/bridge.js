/**
 * withUstrm 内置组件 - 桥接服务器
 * 仅监听 127.0.0.1:3111，供 withU 后台 PHP 网关反代。
 * 职责：提供 Nuxt 静态产物 + 反代 Spring Boot API + SPA 回退。
 * 零第三方依赖。
 *
 * 安全：从启动之初即校验内部共享密钥头 X-Withu-Bridge-Secret
 * （与 runtime/strm/bridge-secret.txt 比对）。只有带正确密钥的请求
 * （即 from withU 后台网关 admin/strm.php 转发的请求）才会被放行，
 * 否则一律 403 —— 杜绝「绕过 withU 后台直接访问 withUstrm」。
 */
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = parseInt(process.env.STRM_BRIDGE_PORT || '3111', 10);
const PUBLIC = path.join(__dirname, 'frontend', '.output', 'public');
const BACKEND = process.env.STRM_BACKEND || 'http://127.0.0.1:8080';

// 读取内部共享密钥（支持 env / 文件两个来源）
function loadSecret() {
  if (process.env.STRM_BRIDGE_SECRET) return process.env.STRM_BRIDGE_SECRET.trim();
  const cand = [
    process.env.STRM_BRIDGE_SECRET_FILE,
    path.join(__dirname, '..', '..', 'runtime', 'strm', 'bridge-secret.txt'),
    path.join(__dirname, 'runtime', 'bridge-secret.txt'),
  ].filter(Boolean);
  for (const f of cand) {
    try {
      const s = fs.readFileSync(f, 'utf8').trim();
      if (s) return s;
    } catch (e) { /* 继续找下一个 */ }
  }
  return '';
}
const SECRET = loadSecret();
const SECRET_BUF = Buffer.from(SECRET, 'utf8');

function authOk(req) {
  const h = req.headers['x-withu-bridge-secret'];
  if (!h || !SECRET) return false;
  const a = Buffer.from(String(h), 'utf8');
  const b = SECRET_BUF;
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.ico': 'image/x-icon',
  '.webmanifest': 'application/manifest+json',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.eot': 'application/vnd.ms-fontobject',
  '.map': 'application/json'
};

function safeJoin(base, p) {
  const full = path.normalize(path.join(base, p));
  return full.startsWith(base) ? full : null;
}

const server = http.createServer((req, res) => {
  // ---- 统一鉴权：无内部密钥 → 403（仅 withU 后台网关可到达） ----
  if (!authOk(req)) {
    res.writeHead(403, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ code: 403, message: 'forbidden: only accessible via withU admin gateway' }));
    return;
  }

  let u;
  try { u = new URL(req.url, 'http://localhost'); } catch (e) { res.writeHead(400); res.end('bad url'); return; }
  let pathname;
  try { pathname = decodeURIComponent(u.pathname); } catch (e) { pathname = u.pathname; }

  // 1) API 反代到 Spring Boot
  if (pathname.startsWith('/api/')) {
    const upstream = BACKEND + u.pathname + u.search;
    const headers = Object.assign({}, req.headers);
    delete headers['host'];
    delete headers['connection'];
    const r = http.request(upstream, { method: req.method, headers }, r2 => {
      res.writeHead(r2.statusCode || 502, r2.headers);
      r2.pipe(res);
    });
    r.on('error', e => { res.writeHead(502, { 'Content-Type': 'text/plain' }); res.end('bridge api error: ' + e.message); });
    req.pipe(r);
    return;
  }

  // 2) 静态文件 / SPA 回退
  const rel = pathname === '/' ? '/index.html' : pathname;
  let file = safeJoin(PUBLIC, rel);
  if (!file || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
    const idx = safeJoin(PUBLIC, rel.replace(/\/+$/, '') + '/index.html');
    if (idx && fs.existsSync(idx) && !fs.statSync(idx).isDirectory()) file = idx;
    else file = safeJoin(PUBLIC, '/index.html');
  }
  fs.readFile(file, (err, data) => {
    if (err) { res.writeHead(404, { 'Content-Type': 'text/plain' }); res.end('not found'); return; }
    const ext = path.extname(file).toLowerCase();
    res.writeHead(200, {
      'Content-Type': MIME[ext] || 'application/octet-stream',
      'Cache-Control': ext === '.html' ? 'no-cache' : 'public, max-age=86400'
    });
    res.end(data);
  });
});

server.listen(PORT, '127.0.0.1', () => console.log('bridge listening on 127.0.0.1:' + PORT));
