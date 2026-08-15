/**
 * withUstrm 内置组件 - 桥接服务器
 * 仅监听 127.0.0.1:3111，供 withU 后台 PHP 网关反代。
 * 职责：提供 Nuxt 静态产物 + 反代 Spring Boot API + SPA 回退。
 * 零第三方依赖。
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = parseInt(process.env.STRM_BRIDGE_PORT || '3111', 10);
const PUBLIC = path.join(__dirname, 'frontend', '.output', 'public');
const BACKEND = process.env.STRM_BACKEND || 'http://127.0.0.1:8080';

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
