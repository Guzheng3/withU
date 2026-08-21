// withU 1314 端口反代：1314 域名显示 frontend 完整前端（转发到 8901 withU server）
// 分流：/admin/ 与 /admin-assets/ 走 withU 原生 PHP 后台（8902），其余走 8901
const http = require('http');

const PORT = parseInt(process.env.PORT || '1314', 10);
const TARGET_HOST = process.env.TARGET_HOST || '127.0.0.1';
const WEB_PORT = parseInt(process.env.TARGET_PORT || '8901', 10);
const PHP_PORT = parseInt(process.env.PHP_PORT || '8902', 10);

function pickTarget(url) {
  if (url === '/admin' || url.startsWith('/admin/')) return PHP_PORT;
  if (url.startsWith('/admin-assets/')) return PHP_PORT;
  return WEB_PORT;
}

const server = http.createServer((req, res) => {
  let path = req.url;
  if (req.url.startsWith('/admin-assets/')) {
    path = '/assets/' + req.url.slice('/admin-assets/'.length);
  }
  const headers = Object.assign({}, req.headers);
  delete headers.host;
  const preq = http.request({
    hostname: TARGET_HOST,
    port: pickTarget(req.url),
    path: path,
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
    console.error('[reverse-proxy]', req.method, req.url, e.message);
    if (!res.headersSent) { res.writeHead(502); res.end('reverse proxy error'); }
  });
  req.pipe(preq);
});

server.listen(PORT, () => console.log('reverse proxy on ' + PORT + ' -> 8901 (frontend) / 8902 (withU admin)'));
