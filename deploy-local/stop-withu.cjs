const {spawnSync} = require('child_process');
// WithU 全家一键停止：php(8088) + withUstrm 后端(8080) + bridge(3111) + MariaDB(3307)
function killByPort(port, name){
  const ns = spawnSync('netstat',['-ano'],{encoding:'utf8'});
  const pids = new Set();
  (ns.stdout||'').split(/\r?\n/).forEach(l=>{
    if(l.includes(':'+port+' ') && /LISTENING/i.test(l)){
      const m = l.trim().split(/\s+/);
      const pid = m[m.length-1];
      if(pid && /^\d+$/.test(pid)) pids.add(pid);
    }
  });
  if(pids.size===0){ console.log(name+' ('+port+') 未运行'); return 0; }
  pids.forEach(pid=>{
    const r = spawnSync('taskkill',['/PID',pid,'/F','/T'],{encoding:'utf8',windowsHide:true});
    console.log('已停止', name, '(pid '+pid+')');
  });
  return pids.size;
}
killByPort(8088,'withu php');
killByPort(8080,'withUstrm 后端');
killByPort(3111,'withUstrm bridge');
// MariaDB 优雅关闭
const mysqladmin = 'C:\\Users\\Administrator\\scoop\\apps\\mariadb\\12.3.2\\mariadb-12.3.2-winx64\\bin\\mysqladmin.exe';
const r = spawnSync(mysqladmin,['--protocol=tcp','-h','127.0.0.1','-P','3307','-u','root','shutdown'],{encoding:'utf8',timeout:10000});
console.log('MariaDB 停止:', r.status===0?'ok':((r.stdout||r.stderr||'').trim()||'未运行'));
console.log('WithU 全家已停止。');
