// withU 独立 lg 后台服务：单独端口提供原 lg 后台（withuadmin，admin/lovezz）
// 用法：PORT=8903 node lg-admin-server.js
const http = require('http');
const fs = require('fs');
const path = require('path');
const admin = require('./admin.js');

const PORT = parseInt(process.env.PORT || '8903', 10);
const ROOT = path.join(__dirname, '..', '..', 'frontend');

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

const server = http.createServer((req, res) => {
  let body = '';
  req.on('data', d => { body += d; if (body.length > 2e6) req.destroy(); });
  req.on('end', () => {
    const urlPath = decodeURIComponent(req.url.split('?')[0].split('#')[0]);
    if (admin.mount(req, res, body, urlPath)) return;
    let filePath;
    if (urlPath.startsWith('/ext/')) {
      filePath = path.join(ROOT, '_external', urlPath.slice('/ext/'.length));
    } else {
      filePath = path.join(ROOT, urlPath);
    }
    if (filePath === ROOT || urlPath === '/') {
      filePath = path.join(ROOT, 'index.html');
    }
    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
      res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream' });
      fs.createReadStream(filePath).pipe(res);
      return;
    }
    res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('not found');
  });
});

server.listen(PORT, () => console.log('lg admin server on 127.0.0.1:' + PORT));
