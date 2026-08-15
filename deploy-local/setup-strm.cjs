#!/usr/bin/env node
/**
 * withUstrm 内置组件 - 一键构建与启动器生成（幂等）
 *
 * 职责：
 *  1. 后端 jar 缺失 → 用 gradlew 构建 bootJar
 *  2. 前端静态产物缺失 → npm ci + nuxt generate（baseURL=/admin/strm.php/）
 *  3. 探测 JDK 21 / Node 路径
 *  4. 生成 runtime/strm/start-backend.js 与 start-bridge.js（含持久化 JWT）
 *
 * 之后即可由 start-withu.cjs 一键启动。
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');               // repo/
const workRoot = path.resolve(root, '..');                // E:/Agent/withu/
const strmRoot = path.join(root, 'strm');
const backendDir = path.join(strmRoot, 'backend');
const frontendDir = path.join(strmRoot, 'frontend');
const jar = path.join(backendDir, 'build', 'libs', 'openlisttostrm.jar');
const pub = path.join(frontendDir, '.output', 'public');
const runtime = path.join(workRoot, 'runtime', 'strm');

function run(cmd, args, opts = {}) {
  const r = spawnSync(cmd, args, { encoding: 'utf8', timeout: 1800000, maxBuffer: 64 * 1024 * 1024, shell: true, ...opts });
  return r;
}
function which(cmd) {
  const w = process.platform === 'win32' ? 'where' : 'which';
  const r = spawnSync(w, [cmd], { encoding: 'utf8', windowsHide: true });
  if (r.status !== 0) return null;
  const line = (r.stdout || '').split(/\r?\n/).find(x => x.trim() && !/Common Files/i.test(x));
  return line ? line.trim() : null;
}
function ensureJava() {
  const jh = process.env.JAVA_HOME;
  if (jh) { const jx = path.join(jh, 'bin', process.platform === 'win32' ? 'java.exe' : 'java'); if (fs.existsSync(jx)) return jx; }
  const j = which('java');
  if (j) return j;
  throw new Error('未找到 JDK 21，请安装并设置 JAVA_HOME');
}
function ensureNode() {
  const n = which('node');
  if (n) return n;
  throw new Error('未找到 Node.js');
}

fs.mkdirSync(runtime, { recursive: true });
['logs', 'db', 'config', 'strm', 'logs/frontend'].forEach(d => fs.mkdirSync(path.join(runtime, d), { recursive: true }));

// ---- 1. 后端构建（缺 jar 时） ----
if (!fs.existsSync(jar)) {
  console.log('[strm] 后端 jar 缺失，开始构建 (gradlew bootJar)...');
  const g = fs.existsSync(path.join(backendDir, 'gradlew.bat'))
    ? path.join(backendDir, 'gradlew.bat')
    : 'gradle';
  const r = run(g, ['bootJar', '-x', 'test', '--no-daemon', '--console=plain'], { cwd: backendDir });
  console.log((r.stdout || '').split(/\r?\n/).slice(-12).join('\n'));
  if (!fs.existsSync(jar)) { console.error('[strm] 后端构建失败'); process.exit(1); }
  console.log('[strm] 后端构建完成: ' + jar);
} else {
  console.log('[strm] 后端 jar 已存在，跳过构建');
}

// ---- 2. 前端构建（缺 .output/public 时） ----
if (!fs.existsSync(path.join(pub, 'index.html'))) {
  console.log('[strm] 前端产物缺失，开始构建 (npm ci + nuxt generate)...');
  if (!fs.existsSync(path.join(frontendDir, 'node_modules'))) {
    let r = run('npm', ['ci', '--no-audit', '--no-fund'], { cwd: frontendDir });
    if (r.status !== 0) { console.error('[strm] npm ci 失败'); process.exit(1); }
  }
  const env = Object.assign({}, process.env, {
    NUXT_APP_BASE_URL: '/admin/strm.php/',
    NUXT_PUBLIC_APP_VERSION: 'local'
  });
  const r = run('npx', ['nuxt', 'generate'], { cwd: frontendDir, env });
  console.log((r.stdout || '').split(/\r?\n/).slice(-10).join('\n'));
  if (!fs.existsSync(path.join(pub, 'index.html'))) { console.error('[strm] 前端构建失败'); process.exit(1); }
  console.log('[strm] 前端构建完成: ' + pub);
} else {
  console.log('[strm] 前端产物已存在，跳过构建');
}

// ---- 3. 持久化 JWT ----
const jwtFile = path.join(runtime, 'jwt.txt');
let jwt = '';
try { jwt = fs.readFileSync(jwtFile, 'utf8').trim(); } catch (e) { /* ignore */ }
if (!jwt || jwt.length < 32) {
  jwt = crypto.randomBytes(32).toString('hex');
  fs.writeFileSync(jwtFile, jwt);
  console.log('[strm] 已生成新 JWT_SECRET 并持久化');
}

// ---- 4. 生成启动器 ----
const java = ensureJava();
// 幂等注入 TMDB 代理配置（本机 Clash 混合端口，可在 systemconf.json 手动改）
function ensureTmdbProxy() {
  const confPath = path.join(runtime, 'config', 'systemconf.json');
  // 内置 mihomo 代理端口（若启用）；未启用则默认 7897（复用手动 Clash）
  let proxyPort = '7897';
  try {
    const mst = JSON.parse(fs.readFileSync(path.join(workRoot, 'runtime', 'mihomo', 'status.json'), 'utf8'));
    if (mst.enabled && mst.port) proxyPort = String(mst.port);
  } catch (e) { /* 未启用内置代理 */ }
  if (!fs.existsSync(confPath)) return;
  try {
    const conf = JSON.parse(fs.readFileSync(confPath, 'utf8'));
    if (!conf.tmdb) conf.tmdb = {};
    let changed = false;
    if (!conf.tmdb.proxyHost) { conf.tmdb.proxyHost = '127.0.0.1'; changed = true; }
    if (!conf.tmdb.proxyPort) { conf.tmdb.proxyPort = proxyPort; changed = true; }
    if (!conf.tmdb.apiKey) { conf.tmdb.apiKey = '8d70511b0389c1015d30b6c1ebf08dce'; changed = true; }
    if (changed) {
      fs.writeFileSync(confPath, JSON.stringify(conf, null, 2).replace(/\n/g, '\r\n'), 'utf8');
      console.log('[strm] 已注入 TMDB 代理配置 (127.0.0.1:7897)');
    }
  } catch (e) { console.warn('[strm] systemconf 注入失败:', e.message); }
}
ensureTmdbProxy();

const node = ensureNode();
const bridge = path.join(strmRoot, 'bridge.js');

// 后端 JVM 走内置 mihomo/本机 Clash 的混合代理端口
const PROXY_PORT = (() => { try { const mst = JSON.parse(fs.readFileSync(path.join(workRoot, 'runtime', 'mihomo', 'status.json'), 'utf8')); if (mst.enabled && mst.port) return String(mst.port); } catch (e) {} return '7897'; })();

const backendLauncher = [
  "const { spawn } = require('child_process');",
  "const fs = require('fs');",
  "const base = '" + runtime.replace(/\\/g, '/') + "';",
  "const java = '" + java.replace(/\\/g, '/') + "';",
  "const jar = '" + jar.replace(/\\/g, '/') + "';",
  "const env = Object.assign({}, process.env, {",
  "  APP_LOG_PATH: base + '/logs',",
  "  APP_DATABASE_PATH: base + '/db/openlist2strm.db',",
  "  APP_CONFIG_PATH: base + '/config',",
  "  APP_STRM_PATH: base + '/strm',",
  "  APP_USER_INFO_PATH: base + '/config/userInfo.json',",
  "  APP_FRONTEND_LOGS_PATH: base + '/logs/frontend',",
  "  JWT_SECRET: '" + jwt + "',",
  "  DOUBAN_COOKIE_KEY: '" + jwt + "'",
  "});",
  "const fd = fs.openSync(base + '/backend.log', 'a');",
  "const proxyArgs = ['-Dhttp.proxyHost=127.0.0.1', '-Dhttp.proxyPort=' + PROXY_PORT, '-Dhttps.proxyHost=127.0.0.1', '-Dhttps.proxyPort=' + PROXY_PORT, '-Dhttp.nonProxyHosts=localhost|127.0.0.1|[::1]', '-Dhttps.nonProxyHosts=localhost|127.0.0.1|[::1]'];",
  "const p = spawn(java, [...proxyArgs, '-jar', jar, '--server.address=127.0.0.1'], { env, cwd: base, stdio: ['ignore', fd, fd] });",
  "console.log('spawned java pid', p.pid);",
  "p.on('exit', c => { try{fs.closeSync(fd);}catch(e){} console.log('java exited', c); process.exit(c || 0); });"
].join('\n');
fs.writeFileSync(path.join(runtime, 'start-backend.js'), backendLauncher);

const bridgeLauncher = [
  "const { spawn } = require('child_process');",
  "const fs = require('fs');",
  "const node = process.execPath;",
  "const bridge = '" + bridge.replace(/\\/g, '/') + "';",
  "const log = '" + (path.join(runtime, 'bridge.log')).replace(/\\/g, '/') + "';",
  "const fd = fs.openSync(log, 'a');",
  "const p = spawn(node, [bridge], { stdio: ['ignore', fd, fd] });",
  "console.log('spawned bridge pid', p.pid);",
  "p.on('exit', c => { try{fs.closeSync(fd);}catch(e){} console.log('bridge exited', c); process.exit(c || 0); });"
].join('\n');
fs.writeFileSync(path.join(runtime, 'start-bridge.js'), bridgeLauncher);

console.log('[strm] 启动器已生成: ' + runtime);
console.log('[strm] 组件就绪: 后端 127.0.0.1:8080 + bridge 127.0.0.1:3111');
console.log('[strm] 后台访问: /admin/strm.php/ （需以情侣账号登录 withu 后台）');
