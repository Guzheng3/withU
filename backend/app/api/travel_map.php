<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/withu.php';
require_once __DIR__ . '/../core/TravelMap.php';
$auth = new Auth();
$user = withu_require_couple_user($auth);
$db = Database::getInstance();
withu_travel_map_ensure_schema($db);
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'snapshot');
$body = withu_json_body();
if ($action === 'snapshot') withu_json_response(['success' => true] + withu_travel_map_payload($db, $user));
if ($action === 'save_position') {
    withu_require_json_csrf($body);
    $lat = $body['latitude'] ?? null; $lng = $body['longitude'] ?? null;
    if (!withu_travel_map_valid_point($lat, $lng)) withu_json_response(['success'=>false,'message'=>'经纬度不合法'],400);
    $data = ['user_id'=>(int)$user['id'], 'location_name'=>trim((string)($body['location_name']??'')), 'latitude'=>withu_travel_map_float($lat), 'longitude'=>withu_travel_map_float($lng), 'visibility'=>in_array(($body['visibility']??'couple'),['private','couple','public'],true)?$body['visibility']:'couple', 'updated_at'=>withu_now()];
    $old = $db->fetch('SELECT id FROM couple_positions WHERE user_id=:uid LIMIT 1',['uid'=>(int)$user['id']]);
    if ($old) $db->update('couple_positions',$data,'id=:id',['id'=>(int)$old['id']]); else $db->insert('couple_positions',$data);
    withu_json_response(['success'=>true,'message'=>'当前位置已保存'] + withu_travel_map_payload($db,$user));
}
if ($action === 'save_location') {
    withu_require_json_csrf($body);
    $lat=$body['latitude']??null; $lng=$body['longitude']??null; $title=trim((string)($body['title']??''));
    if ($title==='') withu_json_response(['success'=>false,'message'=>'请填写地点标题'],400);
    if (!withu_travel_map_valid_point($lat,$lng)) withu_json_response(['success'=>false,'message'=>'经纬度不合法'],400);
    $now=withu_now(); $id=$db->insert('travel_locations',['creator_id'=>(int)$user['id'],'title'=>$title,'location_name'=>trim((string)($body['location_name']??'')),'description'=>trim((string)($body['description']??'')),'latitude'=>withu_travel_map_float($lat),'longitude'=>withu_travel_map_float($lng),'visit_date'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($body['visit_date']??''))?$body['visit_date']:null,'is_favorite'=>!empty($body['is_favorite'])?1:0,'created_at'=>$now,'updated_at'=>$now]);
    withu_json_response(['success'=>true,'id'=>(int)$id,'message'=>'足迹已保存'] + withu_travel_map_payload($db,$user));
}
if ($action === 'delete_location') { withu_require_json_csrf($body); $id=(int)($body['id']??0); if($id>0)$db->delete('travel_locations','id=:id',['id'=>$id]); withu_json_response(['success'=>true] + withu_travel_map_payload($db,$user)); }
if ($action === 'save_route') {
    withu_require_json_csrf($body); $points=withu_travel_map_points($body['points']??[]); if(count($points)<2)withu_json_response(['success'=>false,'message'=>'路线至少需要两个点'],400);
    $now=withu_now(); $id=$db->insert('travel_routes',['creator_id'=>(int)$user['id'],'title'=>trim((string)($body['title']??'我们的路线'))?:'我们的路线','description'=>trim((string)($body['description']??'')),'start_name'=>trim((string)($body['start_name']??'')),'end_name'=>trim((string)($body['end_name']??'')),'distance_km'=>is_numeric($body['distance_km']??null)?round((float)$body['distance_km'],2):null,'points_json'=>json_encode($points,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>$now,'updated_at'=>$now]);
    withu_json_response(['success'=>true,'id'=>(int)$id,'message'=>'路线已保存'] + withu_travel_map_payload($db,$user));
}
withu_json_response(['success'=>false,'message'=>'未知操作'],400);
