<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/helpers.php'; require_once __DIR__ . '/../core/withu.php'; require_once __DIR__ . '/../core/Travel.php';
$auth=new Auth();$user=withu_require_couple_user($auth);$db=Database::getInstance();$action=$_GET['action']??$_POST['action']??'weather';$body=withu_json_body();
if($action==='weather'){$lat=(float)($_GET['lat']??0);$lng=(float)($_GET['lng']??0);if($lat<-90||$lat>90||$lng<-180||$lng>180)withu_json_response(['success'=>false,'message'=>'经纬度不合法'],400);withu_json_response(withu_weather($db,$lat,$lng));}
if($action==='geocode'){$q=trim((string)($_GET['q']??''));if($q==='')withu_json_response(['success'=>false,'message'=>'请输入地点'],400);$data=withu_http_json('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&q='.rawurlencode($q),['User-Agent: withU/1.0 contact=withu']);withu_json_response(['success'=>true,'items'=>$data?:[]]);}
withu_json_response(['success'=>false,'message'=>'未知操作'],400);
