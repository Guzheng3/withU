const fs = require('fs');
const path = require('path');
const {spawnSync} = require('child_process');
const root = path.resolve(__dirname, '..');
const runtime = path.join(__dirname, 'runtime');
fs.mkdirSync(runtime, {recursive:true});
const php = 'C:\\Users\\Administrator\\scoop\\apps\\php\\current\\php.exe';
const mariadb = 'C:\\Users\\Administrator\\scoop\\apps\\mariadb\\12.3.2\\mariadb-12.3.2-winx64';
const bin = path.join(mariadb, 'bin');
const mysql = path.join(bin, 'mysql.exe');
const mysqld = path.join(bin, 'mariadbd.exe');
const installDb = path.join(bin, 'mysql_install_db.exe');
const data = path.join(runtime, 'mariadb-data');
const port = 8088;
function wmiCreate(command){const r=spawnSync('wmic',['process','call','create',command],{encoding:'utf8',windowsHide:true}); console.log(r.stdout||r.stderr||'');}
function listening(p){const r=spawnSync('netstat',['-ano'],{encoding:'utf8'}); return /LISTENING/.test((r.stdout||'').split(/\r?\n/).find(x=>x.includes(':'+p+' '))||'');}
function mysqlExec(sql, db){const args=['--protocol=tcp','-h','127.0.0.1','-P','3307','-u','root']; if(db)args.push(db); args.push('-e',sql); return spawnSync(mysql,args,{encoding:'utf8',timeout:10000});}
if(!fs.existsSync(path.join(data,'mysql'))){fs.mkdirSync(data,{recursive:true}); console.log('Initializing MariaDB data directory...'); const r=spawnSync(installDb,['-d',data,'-p',''],{encoding:'utf8',timeout:120000}); console.log(r.stdout||r.stderr||'');}
if(!listening(3307)){console.log('Starting MariaDB...'); wmiCreate(`${mysqld} --basedir=${mariadb} --datadir=${data} --port=3307 --bind-address=127.0.0.1 --log-error=${path.join(runtime,'mariadb-3307.err')}`);}
let ready=false; for(let i=0;i<40;i++){const r=mysqlExec('SELECT 1'); if(r.status===0){ready=true;break;} Atomics.wait(new Int32Array(new SharedArrayBuffer(4)),0,0,500);}
if(!ready){console.error('MariaDB did not become ready'); process.exit(1);}
const schema=fs.readFileSync(path.join(root,'database','schema.sql'),'utf8');
mysqlExec('CREATE DATABASE IF NOT EXISTS couple_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
spawnSync(mysql,['--protocol=tcp','-h','127.0.0.1','-P','3307','-u','root','couple_website'],{input:schema,encoding:'utf8',timeout:30000});
mysqlExec('CREATE DATABASE IF NOT EXISTS withu_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS \'withu\'@\'127.0.0.1\' IDENTIFIED BY \'withu_dev\'; GRANT ALL ON withu_media.* TO \'withu\'@\'127.0.0.1\'; CREATE USER IF NOT EXISTS \'withu\'@\'localhost\' IDENTIFIED BY \'withu_dev\'; GRANT ALL ON withu_media.* TO \'withu\'@\'localhost\'; FLUSH PRIVILEGES;');
const cfgDir=path.join(root,'config'); fs.mkdirSync(cfgDir,{recursive:true});
if(!fs.existsSync(path.join(cfgDir,'config.php'))) fs.writeFileSync(path.join(cfgDir,'config.php'), `<?php\ndefine('DEBUG_MODE', true); error_reporting(E_ALL); ini_set('display_errors','1'); ini_set('log_errors','1'); date_default_timezone_set('Asia/Shanghai'); define('ROOT_PATH', dirname(__DIR__)); $scheme='http'; $host='127.0.0.1:8088'; define('BASE_URL', $scheme.'://'.$host); ini_set('session.cookie_httponly','1'); ini_set('session.use_only_cookies','1'); ini_set('session.cookie_samesite','Lax'); define('UPLOAD_DIR', ROOT_PATH.'/uploads/'); define('UPLOAD_URL', BASE_URL.'/uploads/'); define('MAX_FILE_SIZE', 5*1024*1024); define('SECRET_KEY','withu-local-dev-secret-change-me'); define('LOGIN_MAX_ATTEMPTS',5); define('LOGIN_ATTEMPT_WINDOW',900); define('LOGIN_LOCKOUT_SECONDS',900); define('SITE_NAME','我们的小情侣网站');`);
if(!fs.existsSync(path.join(cfgDir,'database.php'))) fs.writeFileSync(path.join(cfgDir,'database.php'), `<?php\nreturn ['host'=>'127.0.0.1','port'=>3307,'dbname'=>'couple_website','username'=>'root','password'=>'','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]];`);
fs.writeFileSync(path.join(root,'.installed'),'local deployment\n');
if(!listening(port)){console.log('Starting PHP server...'); wmiCreate(`"${php}" -c "${path.join(__dirname,'php.ini')}" -S 127.0.0.1:${port} -t "${root}"`);} else console.log('PHP already listening on '+port);

// ===== withUstrm 内置组件（后端 8080 + bridge 3111）=====
// 构建缺失产物并生成启动器（幂等）
const setupR = spawnSync('node',[path.join(__dirname,'setup-strm.cjs')],{encoding:'utf8',timeout:1800000});
process.stdout.write(setupR.stdout||''); if(setupR.stderr) process.stdout.write(setupR.stderr);
if(setupR.status!==0) console.error('withUstrm 组件构建失败，请检查 setup-strm.cjs');

const nodeExe = 'C:\\Program Files\\nodejs\\node.exe';
function cimStart(scriptPath, portName){
  const ps = `$c = '"C:\\Program Files\\nodejs\\node.exe" ${scriptPath}'; Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $c } | Select-Object ProcessId | Format-List`;
  const r = spawnSync('powershell',['-NoProfile','-Command',ps],{encoding:'utf8',windowsHide:true,timeout:60000});
  console.log(r.stdout || r.stderr || '');
}
if(!listening(8080)){ console.log('Starting withUstrm backend (8080)...'); cimStart('E:\\Agent\\withu\\runtime\\strm\\start-backend.js','backend'); }
else console.log('withUstrm backend already on 8080');
if(!listening(3111)){ console.log('Starting withUstrm bridge (3111)...'); cimStart('E:\\Agent\\withu\\runtime\\strm\\start-bridge.js','bridge'); }
else console.log('withUstrm bridge already on 3111');
console.log('withUstrm ready: http://127.0.0.1:8088/admin/strm.php/ (后台菜单「媒体库 STRM」)');
console.log('WithU ready: http://127.0.0.1:'+port+'/');
