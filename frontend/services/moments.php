<?php
// 首页「时光碎片」数据接口（page-index.js MomentCard 消费）
// 返回 moments 数组；空数组时前端使用内置占位卡片（设计内空态）。
// TODO(数据源)：时光碎片的真实数据来源（如相册最新照片、纪念事件流）确定后，在此按
// [{id, type:'image', url, original, img_code, publisher:{name,avatar}, publishTime, location, date, title, description}] 结构填充。
header('Content-Type: application/json; charset=UTF-8');
echo '[]';
