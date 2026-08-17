#!/usr/bin/env node
/**
 * withU 内置 mihomo/clash 代理托管组件（跨平台：Windows / Linux / macOS）
 *
 * 解决「服务器上跑 TMDB 刮削 / 海报下载需要代理，但服务器没有 GUI 客户端」的问题。
 * 只填一个订阅地址，mihomo 自动拉订阅、url-test 自动选可用节点、本地起混合代理端口。
 *
 * 用法：
 *   配置订阅地址（任选其一，优先级从高到低）：
 *     1. 环境变量 WITHU_PROXY_SUB_URL（或 WITHU_MIHOMO_SUB_URL）
 *     2. 文件 <repo>/config/mihomo.json   → { "subUrl": "...", "port": 7897, "mirror": "" }
 *     3. 文件 <repo>/deploy-local/mihomo.json（同上，本地开发用，不入 git）
 *   运行：node deploy-local/setup-mihomo.cjs
 *
 * 效果：
 *   - 幂等下载对应平台的 mihomo 二进制到 runtime/mihomo/bin/
 *   - 生成 runtime/mihomo/config.yaml（proxy-providers + 按 TMDB 两域名延迟轮询的 url-test 组 + 排除 hysteria 节点 + 混合端口）
 *   - 生成 runtime/mihomo/start.cjs / stop.cjs（启停启动器，供 start-withu 调用）
 *   - 未配置订阅时：若本机已有 7897 监听（手动 Clash）则复用；否则打印提示、不阻塞
 *
 * 节点切换策略（融入 daitcl/mihomo 的配置模板思路）：
 *   - 轮询：url-test 组默认每 30 分钟对 TMDB 两域名（api.themoviedb.org / image.tmdb.org）测速（pollInterval 秒，后台可改）
 *   - 选优：tolerance=20，延迟差在 20ms 内不切换，优先选择延迟低的节点
 *   - 排除：exclude-filter 过滤 hysteria / hysteria2 / hy2 协议节点（测速能通但实际不可用）
 *   - 兜底：仅 themoviedb.org / tmdb.org 两域名走代理，其余 MATCH,DIRECT 直连
 *
 * 常用配置项：
 *   port   混合代理端口（默认 7897，与 withUstrm TMDB 代理配置一致）
 *   mirror GitHub 下载镜像前缀（默认空=直连；可用 https://ghproxy.net/https://github.com/ 等）
 *   apiPort  mihomo 外部控制端口（默认 9090，RESTful API）
 */
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const https = require('https');
const http = require('http');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');        // repo/
const workRoot = path.resolve(root, '..');         // 工作目录
const runtime = path.join(workRoot, 'runtime', 'mihomo');
const binDir = path.join(runtime, 'bin');
const confDir = path.join(runtime);
const GITHUB_API = 'https://api.github.com/repos/MetaCubeX/mihomo/releases/latest';
const DEFAULT_PORT = 7897;
const DEFAULT_API_PORT = 9090;

// ---------- 配置读取 ----------
function loadConfig() {
  const cands = [
    process.env.WITHU_PROXY_SUB_URL || process.env.WITHU_MIHOMO_SUB_URL,
    path.join(root, 'config', 'mihomo.json'),
    path.join(__dirname, 'mihomo.json'),
  ];
  let cfg = { port: DEFAULT_PORT, apiPort: DEFAULT_API_PORT, mirror: '', subUrl: '', pollInterval: 1800 };
  for (const c of cands) {
    if (!c) continue;
    if (typeof c === 'string' && /^https?:\/\//.test(c)) { cfg.subUrl = c; break; }
    try {
      if (fs.existsSync(c)) {
        const j = JSON.parse(fs.readFileSync(c, 'utf8'));
        cfg = Object.assign(cfg, j);
        break;
      }
    } catch (e) { console.warn('[mihomo] 配置读取失败', c, e.message); }
  }
  return cfg;
}

// ---------- 下载（自动跟随 5 跳重定向） ----------
function httpGetFollow(url, dest, opts = {}) {
  return new Promise((res, rej) => {
    const maxRedirect = opts.maxRedirect || 5;
    const doGet = (u, depth) => {
      const mod = u.startsWith('https:') ? https : http;
      const parsed = new URL(u);
      const req = mod.request({
        hostname: parsed.hostname, path: parsed.pathname + parsed.search,
        timeout: opts.timeout || 120000,
        headers: { 'User-Agent': 'Mozilla/5.0 (withU mihomo-setup)' }
      }, r => {
        if (r.statusCode >= 300 && r.statusCode < 400 && r.headers.location) {
          r.resume();
          if (depth >= maxRedirect) { rej(new Error('too many redirects')); return; }
          const next = new URL(r.headers.location, u).toString();
          console.log('[mihomo]   -> ' + next.replace(/(:\/\/[^:]+:)([^@]+)@/, '$1***@'));
          doGet(next, depth + 1);
          return;
        }
        if (r.statusCode >= 400) { rej(new Error('HTTP ' + r.statusCode + ' ' + u)); r.resume(); return; }
        const ws = fs.createWriteStream(dest);
        let got = 0;
        r.on('data', c => { got += c.length; if (opts.progress && got % (5 * 1024 * 1024) < 1024) opts.progress(got); });
        r.pipe(ws);
        ws.on('finish', () => { ws.close(); if (opts.progress) opts.progress(got, true); res(dest); });
        ws.on('error', rej);
      });
      req.on('error', rej);
      req.on('timeout', () => { req.destroy(); rej(new Error('timeout ' + u)); });
      req.end();
    };
    doGet(url, 0);
  });
}

async function getLatestTag() {
  const body = await new Promise((res, rej) => {
    const u = new URL(GITHUB_API);
    const req = https.request({ hostname: u.hostname, path: u.pathname, timeout: 20000, headers: { 'User-Agent': 'withU-mihomo' } }, r => {
      let b = ''; r.on('data', d => b += d); r.on('end', () => r.statusCode === 200 ? res(b) : rej(new Error('API ' + r.statusCode)));
    });
    req.on('error', rej); req.on('timeout', () => { req.destroy(); rej(new Error('api timeout')); });
    req.end();
  });
  return JSON.parse(body).tag_name; // v1.19.29
}

function platformAsset(tag, platform, arch) {
  const v = tag.replace(/^v/, '');
  if (platform === 'win32') {
    return { name: `mihomo-windows-${arch}-v${v}.zip`, kind: 'zip' };
  }
  const plat = platform === 'darwin' ? 'darwin' : 'linux';
  const a = arch === 'x64' ? 'amd64' : arch === 'arm64' ? 'arm64' : arch === 'ia32' ? '386' : arch;
  return { name: `mihomo-${plat}-${a}-v1-v${v}.gz`, kind: 'gz' };
}

// ---------- 本机已有 mihomo 核心探测（复用，零下载） ----------
function findLocalMihomo() {
  const isWin = process.platform === 'win32';
  const names = isWin ? ['mihomo.exe', 'clash-meta.exe', 'clash.exe', 'verge-mihomo.exe'] : ['mihomo', 'clash-meta', 'clash'];
  // 1. PATH
  for (const n of names) {
    try {
      const r = spawnSync(isWin ? 'where' : 'which', [n], { encoding: 'utf8', windowsHide: true });
      if (r.status === 0) {
        const line = (r.stdout || '').split(/\r?\n/).find(x => x.trim() && !/Common Files/i.test(x));
        if (line && fs.existsSync(line.trim())) return line.trim();
      }
    } catch (e) {}
  }
  // 2. Clash Verge 安装目录（Windows）
  if (isWin) {
    const cands = [
      'C:\\Program Files\\Clash Verge\\verge-mihomo.exe',
      'C:\\Program Files\\Clash Verge\\verge-mihomo-alpha.exe',
      'C:\\Program Files\\Clash Verge\\resources\\clash-verge\\verge-mihomo.exe',
      process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Programs', 'clash-verge', 'verge-mihomo.exe') : '',
      process.env.APPDATA ? path.join(process.env.APPDATA, 'io.github.clash-verge-rev.clash-verge-rev', 'clash-verge', 'verge-mihomo.exe') : '',
    ];
    for (const c of cands) if (c && fs.existsSync(c)) return c;
  }
  return null;
}

async function ensureBinary(cfg) {
  const plat = process.platform;
  const exe = path.join(binDir, plat === 'win32' ? 'mihomo.exe' : 'mihomo');
  if (fs.existsSync(exe)) { console.log('[mihomo] 二进制已存在: ' + exe); return exe; }
  fs.mkdirSync(binDir, { recursive: true });

  // 优先复用本机已有的 mihomo 核心（Clash Verge 内置等），零下载
  const local = findLocalMihomo();
  if (local) {
    fs.copyFileSync(local, exe);
    if (plat !== 'win32') { try { fs.chmodSync(exe, 0o755); } catch (e) {} }
    try {
      const v = spawnSync(exe, ['-v'], { encoding: 'utf8', timeout: 8000 });
      console.log('[mihomo] 复用本机 mihomo 核心 (' + local + ') => ' + (v.stdout || v.stderr || '').split('\n')[0]);
    } catch (e) {}
    return exe;
  }
  console.log('[mihomo] 本机无 mihomo 核心，开始下载 (' + plat + '/' + process.arch + ')...');
  const tag = await getLatestTag();
  const { name, kind } = platformAsset(tag, plat, process.arch);
  const baseUrl = `https://github.com/MetaCubeX/mihomo/releases/download/${tag}/${name}`;
  const tmp = path.join(binDir, name);
  const progress = got => console.log('[mihomo]   已下载 ' + (got / 1048576).toFixed(1) + ' MB');
  // 优先走本机已有代理（若有监听）下载，再直连，再镜像
  const proxied = listening(7897) || listening(7890);
  const proxyPort = listening(7897) ? 7897 : 7890;
  let ok = false;
  if (proxied) {
    try {
      console.log('[mihomo] 经本机代理 127.0.0.1:' + proxyPort + ' 下载...');
      const curl = spawnSync('curl', ['-x', 'http://127.0.0.1:' + proxyPort, '-L', '--connect-timeout', '20', '--max-time', '900', '-sS', '-o', tmp, baseUrl], { encoding: 'utf8', timeout: 920000 });
      ok = curl.status === 0 && fs.existsSync(tmp) && fs.statSync(tmp).size > 100000;
      if (!ok) console.warn('[mihomo] 代理下载失败: ' + (curl.stderr || '').slice(0, 200));
    } catch (e) { console.warn('[mihomo] 代理下载异常: ' + e.message); }
  }
  if (!ok) {
    try {
      await httpGetFollow(baseUrl, tmp, { timeout: 600000, progress });
      ok = fs.statSync(tmp).size > 100000;
    } catch (e) {
      console.warn('[mihomo] 直连下载失败: ' + e.message);
    }
  }
  if (!ok && !cfg.mirror) {
    for (const m of ['https://ghproxy.net/', 'https://gh-proxy.com/', 'https://ghfast.top/']) {
      try { console.log('[mihomo] 尝试镜像 ' + m); await httpGetFollow(m + baseUrl.replace('https://', ''), tmp, { timeout: 600000, progress }); ok = fs.statSync(tmp).size > 100000; if (ok) break; }
      catch (e2) { console.warn('[mihomo] 镜像失败: ' + e2.message); }
    }
  }
  if (ok) {
    if (kind === 'gz') {
      const raw = zlib.gunzipSync(fs.readFileSync(tmp));
      fs.writeFileSync(exe, raw);
      if (plat !== 'win32') { try { fs.chmodSync(exe, 0o755); } catch (e) {} }
      fs.unlinkSync(tmp);
    } else {
      const tar = spawnSync('tar', ['-xf', tmp, '-C', binDir], { encoding: 'utf8' });
      if (tar.status !== 0) {
        const ps = spawnSync('powershell', ['-NoProfile', '-Command', `Expand-Archive -Force -Path '${tmp}' -DestinationPath '${binDir}'`], { encoding: 'utf8', windowsHide: true });
        if (ps.status !== 0) throw new Error('zip 解压失败: ' + (ps.stderr || tar.stderr));
      }
      fs.unlinkSync(tmp);
      const found = (function walk(d) {
        for (const f of fs.readdirSync(d, { withFileTypes: true })) {
          const fp = path.join(d, f.name);
          if (f.isDirectory()) { const r2 = walk(fp); if (r2) return r2; }
          else if (/mihomo[^\\/]*\.exe$/i.test(f.name) || f.name === 'mihomo') return fp;
        }
        return null;
      })(binDir);
      if (found && !fs.existsSync(exe)) fs.renameSync(found, exe);
    }
    console.log('[mihomo] 二进制就绪: ' + exe + ' (' + (fs.statSync(exe).size / 1048576).toFixed(1) + ' MB)');
    return exe;
  }
  throw new Error('mihomo 二进制下载失败（可先在本机安装 Clash Verge，脚本会自动复用其核心）');
}



// ---------- 配置生成 ----------
function genConfig(cfg, subUrl) {
  const providerPath = './providers/sub.yaml';
  // 读取 TMDB 配置，用于对 TMDB 两个域名测速（api 需要带 key 返回 200）
  let tmdbApiKey = '';
  let tmdbApiBase = 'https://api.themoviedb.org';
  let tmdbImageBase = 'https://image.tmdb.org';
  try {
    const confPath = path.join(workRoot, 'runtime', 'strm', 'config', 'systemconf.json');
    if (fs.existsSync(confPath)) {
      const sc = JSON.parse(fs.readFileSync(confPath, 'utf8'));
      if (sc.tmdb) {
        if (sc.tmdb.apiKey) tmdbApiKey = String(sc.tmdb.apiKey).trim();
        if (sc.tmdb.baseUrl) tmdbApiBase = String(sc.tmdb.baseUrl).replace(/\/+$/, '');
        if (sc.tmdb.imageBaseUrl) tmdbImageBase = String(sc.tmdb.imageBaseUrl).replace(/\/+$/, '');
      }
    }
  } catch (e) { /* 忽略 */ }
  const apiSpeedUrl = tmdbApiBase + '/3/configuration?api_key=' + encodeURIComponent(tmdbApiKey);
  const imageSpeedUrl = tmdbImageBase + '/t/p/w92/8uO0gvtHj6YST5soLVhNkeMfrXk.jpg';
  // 排除 hysteria/hysteria2/hy2 等不可用协议节点
  const EXCLUDE_HY = "(?i)hysteria|hysteria2|hy2|hys|hy-2|hy-";
  // 轮询测速间隔（秒），默认 30 分钟，可在后台设置
  const pollInterval = Number(cfg.pollInterval) > 0 ? Number(cfg.pollInterval) : 1800;

  const lines = [
    'mixed-port: ' + cfg.port,
    'allow-lan: false',
    'mode: rule',
    'log-level: info',
    'ipv6: false',
    'unified-delay: true',
    'tcp-concurrent: true',
    'find-process-mode: off',
    'external-controller: 127.0.0.1:' + cfg.apiPort,
    'secret: ""',
    '',
    'dns:',
    '  enable: true',
    '  ipv6: false',
    '  enhanced-mode: redir-host',
    '  nameserver:',
    '    - https://223.5.5.5/dns-query',
    '    - https://119.29.29.29/dns-query',
    '  fallback:',
    '    - https://1.1.1.1/dns-query',
    '    - https://8.8.8.8/dns-query',
    '  fallback-filter:',
    '    geoip: true',
    '    geoip-code: CN',
    '',
    'proxy-providers:',
    '  sub:',
    '    type: http',
    '    url: "' + subUrl + '"',
    '    interval: 86400',
    '    path: ' + providerPath,
    '    health-check:',
    '      enable: true',
    '      url: ' + imageSpeedUrl,
    '      interval: 120',
    '      timeout: 5000',
    '      lazy: true',
    '',
    'proxy-groups:',
    '  - name: TMDB_API',
    '    type: url-test',
    '    url: ' + apiSpeedUrl,
    '    interval: ' + pollInterval,
    '    timeout: 5000',
    '    tolerance: 20',
    '    lazy: true',
    '    exclude-filter: "' + EXCLUDE_HY + '"',
    '    use:',
    '      - sub',
    '  - name: TMDB_IMAGE',
    '    type: url-test',
    '    url: ' + imageSpeedUrl,
    '    interval: ' + pollInterval,
    '    timeout: 5000',
    '    tolerance: 20',
    '    lazy: true',
    '    exclude-filter: "' + EXCLUDE_HY + '"',
    '    use:',
    '      - sub',
    '',
    'rules:',
    '  - DOMAIN-SUFFIX,themoviedb.org,TMDB_API',
    '  - DOMAIN-SUFFIX,tmdb.org,TMDB_IMAGE',
    '  - MATCH,DIRECT',
    ''
  ];
  fs.mkdirSync(path.join(confDir, 'providers'), { recursive: true });
  fs.writeFileSync(path.join(confDir, 'config.yaml'), lines.join('\n'), 'utf8');
}

// ---------- 启动器 ----------
function writeLaunchers(exe, cfg) {
  const conf = path.join(confDir, 'config.yaml');
  const start = [
    "const { spawn } = require('child_process');",
    "const fs = require('fs');",
    "const path = require('path');",
    "const exe = '" + exe.replace(/\\/g, '/') + "';",
    "const conf = '" + conf.replace(/\\/g, '/') + "';",
    "const log = path.join(path.dirname(conf), 'mihomo.log');",
    "const fd = fs.openSync(log, 'a');",
    "const p = spawn(exe, ['-d', path.dirname(conf), '-f', conf], { stdio: ['ignore', fd, fd] });",
    "console.log('mihomo pid', p.pid, 'port', " + cfg.port + ");",
    "p.on('exit', c => { try{fs.closeSync(fd);}catch(e){} console.log('mihomo exited', c); process.exit(c||0); });"
  ].join('\n');
  fs.writeFileSync(path.join(confDir, 'start.cjs'), start);
  const stop = [
    "const { spawnSync } = require('child_process');",
    "const r = spawnSync('netstat', ['-ano'], { encoding: 'utf8' });",
    "const lines = (r.stdout||'').split(/\\r?\\n/).filter(x => x.includes(':" + cfg.port + " ') && x.includes('LISTENING'));",
    "const pids = new Set(lines.map(l => l.trim().split(/\\s+/).pop()).filter(p => p && p !== '0'));",
    "for (const pid of pids) { try { spawnSync('taskkill', ['/F', '/PID', pid]); } catch(e){} }",
    "console.log('mihomo stopped', [...pids]);"
  ].join('\n');
  fs.writeFileSync(path.join(confDir, 'stop.cjs'), stop);
}

// ---------- 端口占用检测 ----------
function listening(p) {
  try {
    const r = spawnSync('netstat', ['-ano'], { encoding: 'utf8' });
    return /LISTENING/.test((r.stdout || '').split(/\r?\n/).find(x => x.includes(':' + p + ' ')) || '');
  } catch (e) { return false; }
}

// ---------- 主流程 ----------
(async () => {
  const cfg = loadConfig();
  fs.mkdirSync(runtime, { recursive: true });

  if (!cfg.subUrl) {
    if (listening(cfg.port)) {
      console.log('[mihomo] 未配置订阅地址，但本机已有 127.0.0.1:' + cfg.port + ' 在监听（手动 Clash 客户端），直接复用。');
      console.log('[mihomo] 如需内置代理，请设置 WITHU_PROXY_SUB_URL 或 config/mihomo.json');
    } else {
      console.log('[mihomo] 未配置订阅地址，跳过内置代理。');
      console.log('[mihomo]   在 Linux 服务器部署时务必配置：export WITHU_PROXY_SUB_URL="https://你的订阅地址"');
      console.log('[mihomo]   或写入 <repo>/config/mihomo.json: { "subUrl": "..." }');
    }
    fs.writeFileSync(path.join(runtime, 'status.json'), JSON.stringify({ enabled: false, port: cfg.port, reason: 'no-subscription' }, null, 2));
    process.exit(0);
  }

  console.log('[mihomo] 订阅地址: ' + cfg.subUrl.replace(/(:\/\/[^:]+:)([^@]+)@/, '$1***@'));
  try {
    const exe = await ensureBinary(cfg);
    genConfig(cfg, cfg.subUrl);
    writeLaunchers(exe, cfg);
    fs.writeFileSync(path.join(runtime, 'status.json'), JSON.stringify({ enabled: true, port: cfg.port, apiPort: cfg.apiPort, subUrl: cfg.subUrl, binary: exe, config: path.join(confDir, 'config.yaml') }, null, 2));
    console.log('[mihomo] 组件就绪：混合代理 127.0.0.1:' + cfg.port + '（AUTO url-test 自动选节点）');
    console.log('[mihomo] 配置文件: ' + path.join(confDir, 'config.yaml'));
    console.log('[mihomo] 启动器: ' + path.join(confDir, 'start.cjs'));
  } catch (e) {
    console.error('[mihomo] 设置失败: ' + e.message);
    process.exit(1);
  }
})();
