// 注册 / 卸载「定时同步解析脚本仓库」的 Windows 任务计划。
// 用法：
//   node scripts/register-sync-schedule.cjs            # 注册每日 04:00 同步
//   node scripts/register-sync-schedule.cjs --time 03:30
//   node scripts/register-sync-schedule.cjs --name withU-SyncWarehouse
//   node scripts/register-sync-schedule.cjs --php C:\path\to\php.exe
//   node scripts/register-sync-schedule.cjs --unregister
//
// 注册后可在「任务计划程序」中查看/调整，或直接运行：
//   schtasks /run /tn withU-SyncWarehouse
//
// Linux / WSL 部署请改用 crontab：
//   0 4 * * * cd /path/to/withU && php scripts/sync-warehouse.php >> runtime/logs/warehouse-sync.log 2>&1

const { spawnSync } = require('child_process');
const path = require('path');
const root = path.resolve(__dirname, '..');

const args = process.argv.slice(2);
const argValue = (name, fallback) => {
  const idx = args.indexOf(name);
  return idx >= 0 && args[idx + 1] ? args[idx + 1] : fallback;
};

const taskName = argValue('--name', 'withU-SyncWarehouse');
const time = argValue('--time', '04:00');
const unregister = args.includes('--unregister');

// PHP 可执行文件：--php > 环境变量 PHP > withU Windows 栈常用路径
const defaultPhp = 'C:\\Users\\Administrator\\scoop\\apps\\php\\current\\php.exe';
let php = argValue('--php', process.env.PHP || defaultPhp);
if (!require('fs').existsSync(php)) {
  const which = spawnSync('where', ['php'], { encoding: 'utf8', windowsHide: true });
  const found = String(which.stdout || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean)[0];
  if (found) {
    php = found;
  }
}
if (!require('fs').existsSync(php)) {
  console.error(`找不到 php.exe（尝试了 ${php}），请用 --php 指定完整路径。`);
  process.exit(1);
}

const script = path.join(root, 'scripts', 'sync-warehouse.php');
// 与 start-withu.cjs 一致：带上站点 php.ini（启用 pdo_mysql 等扩展）
const phpIni = path.join(root, 'deploy-local', 'php.ini');
const iniArgs = require('fs').existsSync(phpIni) ? ['-c', phpIni] : [];
const taskCommand = `"${php}" ${iniArgs.map((a) => `"${a}"`).join(' ')} "${script}"`;

function run(command, argsList) {
  const r = spawnSync(command, argsList, { encoding: 'utf8', windowsHide: true, timeout: 60000 });
  if (r.stdout) process.stdout.write(r.stdout);
  if (r.stderr) process.stderr.write(r.stderr);
  return r.status;
}

if (unregister) {
  const status = run('schtasks', ['/delete', '/tn', taskName, '/f']);
  if (status === 0) {
    console.log(`已删除任务计划：${taskName}`);
  }
  process.exit(status ?? 1);
}

const status = run('schtasks', [
  '/create',
  '/tn', taskName,
  '/tr', taskCommand,
  '/sc', 'daily',
  '/st', time,
  '/f',
]);
if (status === 0) {
  console.log(`已注册任务计划：${taskName}（每天 ${time} 执行）`);
  console.log(`任务命令：${taskCommand}`);
  console.log('');
  console.log('立即试跑一次：schtasks /run /tn ' + taskName);
  console.log('Linux / WSL 部署请改用 crontab：');
  console.log('  0 ' + time.split(':')[1] + ' * * * cd ' + root + ' && php scripts/sync-warehouse.php >> runtime/logs/warehouse-sync.log 2>&1');
}
process.exit(status ?? 1);
