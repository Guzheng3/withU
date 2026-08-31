<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/helpers.php'; require_once __DIR__ . '/../core/withu.php';
$auth=new Auth();$user=withu_require_couple_user($auth);$message='';$messageError=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $singleId=(int)($_POST['single_id']??0);
    if($singleId>0){
        if($auth->revokeTrustedDevice($singleId))$message='设备已解绑';
    }elseif(isset($_POST['bulk_submit'])){
        $ids=array_values(array_filter(array_map('intval',(array)($_POST['ids']??[]))));
        if(!$ids){$message='请先勾选要解绑的设备';$messageError=true;}
        else{
            $count=0;foreach($ids as $id){if($auth->revokeTrustedDevice($id))$count++;}
            if($count>0)$message="已批量解绑 {$count} 台设备";
            else{$message='所选设备均无需解绑';$messageError=true;}
        }
    }
}
$devices=$auth->listTrustedDevices();
$hasActive=false;foreach($devices as $device){if(empty($device['revoked_at'])){$hasActive=true;break;}}
$adminPage='devices';include __DIR__.'/header.php';
?><section class="admin-page-title"><h1>信任设备</h1><p>登录一次后设备会自动保持登录；解绑后该设备需要重新登录。</p></section><?php if($message):?><div class="admin-card" style="margin-bottom:1rem;color:<?php echo $messageError?'#b45309':'#15803d';?>"><?php echo e($message);?></div><?php endif;?><section class="admin-card"><form method="post" id="deviceForm"><?php echo csrf_field();?><?php if($hasActive):?><div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-bottom:0.75rem;flex-wrap:wrap"><span id="deviceSelectedCount" style="font-size:0.85rem;color:var(--text-light)">已选 0 台</span><button class="btn btn-danger" type="submit" name="bulk_submit" value="1" id="deviceBulkBtn" disabled><i class="ti ti-unlink"></i><span>批量解绑</span></button></div><?php endif;?><table class="admin-table"><thead><tr><?php if($hasActive):?><th style="width:36px"><input type="checkbox" id="deviceCheckAll" aria-label="全选设备"></th><?php endif;?><th>设备</th><th>IP</th><th>首次信任</th><th>最近使用</th><th>状态</th><th>操作</th></tr></thead><tbody><?php foreach($devices as $device):$active=empty($device['revoked_at']);?><tr><?php if($hasActive):?><td><?php if($active):?><input type="checkbox" class="device-check" name="ids[]" value="<?php echo (int)$device['id'];?>" aria-label="选择 <?php echo e($device['device_name']??'浏览器');?>"><?php endif;?></td><?php endif;?><td style="max-width:360px;word-break:break-all"><?php echo e($device['device_name']??'浏览器');?></td><td><?php echo e($device['last_ip']??'');?></td><td><?php echo e($device['created_at']);?></td><td><?php echo e($device['last_seen_at']??'');?></td><td><?php echo $active?'使用中':'已解绑';?></td><td><?php if($active):?><button class="btn btn-secondary" type="submit" name="single_id" value="<?php echo (int)$device['id'];?>">解绑</button><?php endif;?></td></tr><?php endforeach;?></tbody></table></form></section><?php if($hasActive):?><script>(function(){var form=document.getElementById('deviceForm');if(!form)return;var boxes=Array.prototype.slice.call(form.querySelectorAll('.device-check'));var all=document.getElementById('deviceCheckAll');var bulkBtn=document.getElementById('deviceBulkBtn');var countEl=document.getElementById('deviceSelectedCount');function checkedCount(){return boxes.filter(function(b){return b.checked;}).length;}function sync(){var checked=checkedCount();if(countEl)countEl.textContent='已选 '+checked+' 台';if(bulkBtn)bulkBtn.disabled=checked===0;if(all){all.checked=boxes.length>0&&checked===boxes.length;all.indeterminate=checked>0&&checked<boxes.length;}}boxes.forEach(function(b){b.addEventListener('change',sync);});if(all)all.addEventListener('change',function(){boxes.forEach(function(b){b.checked=all.checked;});sync();});if(bulkBtn)bulkBtn.addEventListener('click',function(ev){ev.preventDefault();var n=checkedCount();if(n===0)return;var submit=function(){form.submit();};if(typeof window.adminConfirm==='function'){window.adminConfirm('确认批量解绑选中的 '+n+' 台设备？解绑后这些设备需要重新登录。',submit);}else{submit();}});})();</script><?php endif;?><?php include __DIR__.'/footer.php';?>
