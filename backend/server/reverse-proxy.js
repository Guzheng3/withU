// withU 1314 端口反代：1314 域名显示 withu-site 完整前端（转发到 8901 withu-server）
const http = require('http');

const PORT = parseInt(process.env.PORT || '1314', 10);
const TARGET_HOST = process.env.TARGET_HOST || '127.0.0.1';
const TARGET_PORT = parseInt(process.env.TARGET_PORT || '8901', 10);

const server = http.createServer((req, res) => {
  const headers = Object.assign({}, req.headers);
  delete headers.host;
  const preq = http.request({
    hostname: TARGET_HOST,
    port: TARGET_PORT,
    path: req.url,
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

server.listen(PORT, () => console.log('reverse proxy on ' + PORT + ' -> ' + TARGET_HOST + ':' + TARGET_PORT));
