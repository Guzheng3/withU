<?php
// 新版后台 - 新建相册（移动端优先）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

// 确保数据库结构为最新（包含 keep_original_quality 等字段）
if (function_exists('migrate_schema_if_needed')) {
    migrate_schema_if_needed();
}

// 读取全局图片压缩开关，用于控制相册级别压缩开关是否可用
$imageOptimizeEnabled = get_setting('image_optimize_enabled', '1');

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();
// 确保相册权限表存在（用于控制另一半是否可编辑）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `album_permissions` (
            `album_id` int(11) NOT NULL COMMENT '相册ID',
            `allow_partner_edit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许另一半编辑与上传',
            `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
            PRIMARY KEY (`album_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='相册权限表';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，后续仅在表存在时写入权限
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = withu_visibility_normalize($_POST['visibility'] ?? 'public');
    $isEncrypted = $visibility === 'hidden' ? 1 : 0;
    // 相册级图片压缩开关：勾选“本相册不应用压缩”时，保持原始画质（跳过主图压缩）
    $keepOriginal = isset($_POST['keep_original_quality']) ? 1 : 0;
    $allowPartnerEdit = isset($_POST['allow_partner_edit']) ? 1 : 0;
    $layoutMode = in_array($_POST['layout_mode'] ?? 'grid', ['grid', 'waterfall', 'heart'], true) ? $_POST['layout_mode'] : 'grid';
    $maskType = in_array($_POST['mask_type'] ?? 'none', ['none', 'circle', 'heart'], true) ? $_POST['mask_type'] : 'none';
    $locationName = trim($_POST['location_name'] ?? '');
    $latitude = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
    $longitude = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;

    if ($name === '') {
        $error = '请输入相册名称';
    } else {
        $coverImage = null;
        // 兼容旧字段 cover（单张封面）；新组件统一通过 album_images[] 多图上传
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['cover'], 'albums');
            if (!empty($upload['success'])) {
                $coverImage = $upload['path'];
            } else {
                $error = $upload['message'] ?? '封面上传失败';
            }
        }

        // 收集本次多选的相册照片（逐张容错，与「相册管理」批量上传口径一致）
        $photoFiles = [];
        if (isset($_FILES['album_images']) && is_array($_FILES['album_images']['name'])) {
            foreach ($_FILES['album_images']['name'] as $key => $fname) {
                if ($_FILES['album_images']['error'][$key] === UPLOAD_ERR_OK && $_FILES['album_images']['tmp_name'][$key] !== '') {
                    $photoFiles[] = [
                        'name'     => $fname,
                        'type'     => $_FILES['album_images']['type'][$key],
                        'tmp_name' => $_FILES['album_images']['tmp_name'][$key],
                        'error'    => $_FILES['album_images']['error'][$key],
                        'size'     => $_FILES['album_images']['size'][$key],
                    ];
                }
            }
        }

        // 未单独指定封面时，第一张照片自动作为封面（预览网格中的「封面」角标与之对应）
        $pendingPhotos = $photoFiles;
        if ($coverImage === null && $pendingPhotos) {
            $firstPhoto = array_shift($pendingPhotos);
            $upload = uploadFile($firstPhoto, 'albums');
            if (!empty($upload['success'])) {
                $coverImage = $upload['path'];
            }
            // 封面上传失败不阻断创建流程，照片仍会随后续逻辑尝试入库
        }

        if (!$error) {
            $data = [
                'user_id'     => $currentUser['id'],
                'name'        => $name,
                'description' => $description,
                'cover_image' => $coverImage,
                'keep_original_quality' => $keepOriginal,
                'is_encrypted'=> $isEncrypted,
                'visibility'  => $visibility,
                'layout_mode' => $layoutMode,
                'mask_type' => $maskType,
                'location_name' => $locationName !== '' ? $locationName : null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            $albumId = $db->insert('albums', $data);

            if ($albumId) {
                // 写入/更新相册权限（若权限表创建失败，此处忽略错误）
                try {
                    $db->query("
                        INSERT INTO album_permissions (album_id, allow_partner_edit, updated_at)
                        VALUES (:album_id, :allow_partner_edit, :updated_at)
                        ON DUPLICATE KEY UPDATE
                            allow_partner_edit = VALUES(allow_partner_edit),
                            updated_at = VALUES(updated_at)
                    ", [
                        'album_id'          => $albumId,
                        'allow_partner_edit'=> $allowPartnerEdit,
                        'updated_at'        => date('Y-m-d H:i:s'),
                    ]);
                } catch (Exception $e) {
                    // 忽略权限表写入失败，保持创建相册主流程可用
                }

                // 其余照片入库：与「相册管理」批量上传同一套处理（缩略图推断 + 压缩标记 + 上传者记录）
                if ($pendingPhotos) {
                    $imageOptimizeEnabled = get_setting('image_optimize_enabled', '1');
                    foreach ($pendingPhotos as $photoFile) {
                        $photoUpload = uploadFile($photoFile, 'albums/' . $albumId);
                        if (empty($photoUpload['success'])) {
                            continue; // 单张失败跳过，不影响其余照片与创建主流程
                        }
                        // 尝试推断缩略图路径：uploads/albums/{id}/thumbs/{filename}
                        $thumbnailPath = null;
                        $relative = ltrim($photoUpload['path'], '/');
                        $thumbnailRelative = preg_replace('#^(.*/)([^/]+)$#', '$1thumbs/$2', $relative);
                        if ($thumbnailRelative && $thumbnailRelative !== $relative) {
                            $thumbAbs = rtrim(UPLOAD_DIR, '/\\') . '/' . $thumbnailRelative;
                            if (is_file($thumbAbs)) {
                                $thumbnailPath = $thumbnailRelative;
                            }
                        }
                        $markOptimized = ((string)$imageOptimizeEnabled === '1' && !$keepOriginal);
                        $imageId = $db->insert('album_images', [
                            'album_id'       => $albumId,
                            'image_path'     => $photoUpload['path'],
                            'thumbnail_path' => $thumbnailPath,
                            'is_optimized'   => $markOptimized ? 1 : 0,
                            'skip_optimize'  => 0,
                            'description'    => null,
                            'sort_order'     => 0,
                            'created_at'     => date('Y-m-d H:i:s'),
                        ]);
                        if (!empty($imageId) && !empty($currentUser['id'])) {
                            try {
                                $db->query("
                                    INSERT INTO album_image_uploads (image_id, user_id, created_at)
                                    VALUES (:image_id, :user_id, :created_at)
                                    ON DUPLICATE KEY UPDATE
                                        user_id = VALUES(user_id),
                                        created_at = VALUES(created_at)
                                ", [
                                    'image_id'  => $imageId,
                                    'user_id'   => $currentUser['id'],
                                    'created_at'=> date('Y-m-d H:i:s'),
                                ]);
                            } catch (Exception $e) {
                                // 忽略记录失败
                            }
                        }
                    }
                }

                header('Location: /admin/albums.php?success=相册创建成功');
                exit;
            } else {
                $error = '创建失败，请重试';
            }
        }
    }
}

$adminPage = 'albums';
// 与其他表单/设置页（together_settings 等）一致，套用主题窄列宽度规范（--v3-content-max）
$adminNarrow = true;

include __DIR__ . '/header.php';
?>

    <style>
        /* —— 朋友圈式发布页（样式仅作用于本页） —— */
        /* 页面宽度不再自定：交给 admin-narrow 主题规范（.admin-main > * 限宽 --v3-content-max 并居中），与其他页面等宽 */
        .withu-moments-wrap{margin:0 auto 2rem;}
        .withu-moments-topbar{position:sticky;top:0;z-index:30;display:flex;align-items:center;gap:.75rem;background:rgba(255,255,255,.94);backdrop-filter:blur(8px);padding:.65rem .85rem;border-bottom:1px solid rgba(0,0,0,.06);}
        .withu-moments-back{display:inline-flex;align-items:center;gap:.35rem;color:#8a8a8a;font-size:.88rem;text-decoration:none;flex:none;}
        .withu-moments-back:hover{color:#e75480;}
        .withu-moments-title{flex:1;text-align:center;font-size:.95rem;font-weight:600;color:#334155;}
        .withu-moments-publish{border:0;border-radius:.5rem;background:linear-gradient(135deg,#f78fb3,#e75480);color:#fff;font-size:.88rem;font-weight:600;padding:.42rem 1.15rem;cursor:pointer;flex:none;}
        .withu-moments-publish:hover{filter:brightness(1.06);}
        .withu-moments-form{background:#fff;border-radius:0 0 .75rem .75rem;padding:.75rem 1.25rem 1.75rem;display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,352px);column-gap:2.25rem;align-items:start;}
        /* 左栏：照片 + 标题 + 想法；右栏：设置面板（窄屏回退单栏） */
        .withu-moments-main{min-width:0;}
        .withu-moments-side{position:sticky;top:4rem;min-width:0;}
        .withu-moments-side-title{font-size:.76rem;font-weight:600;letter-spacing:.09em;color:#c1c7d2;margin:0 0 .3rem;padding-left:.1rem;}
        .withu-moment-cell{transition:background .15s;}
        .withu-moment-cell:hover{background:rgba(148,163,184,.05);}
        .withu-moments-form :is(input,textarea,select):focus-visible{outline:2px solid rgba(231,84,128,.5);outline-offset:2px;border-radius:.35rem;}
        .withu-moments-publish[disabled]{opacity:.65;cursor:default;transform:none;}
        .withu-moments-publish:not([disabled]):active{transform:scale(.97);}
        @media (max-width:920px){
            .withu-moments-form{grid-template-columns:minmax(0,1fr);}
            .withu-moments-side{position:static;}
            .withu-moments-main{margin-bottom:1rem;}
        }
        .withu-moments-error{display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;padding:.65rem .85rem;border-radius:.6rem;background:rgba(248,113,113,.07);border:1px solid rgba(248,113,113,.3);color:#b91c1c;font-size:.88rem;}
        .withu-moment-title{width:100%;border:0;outline:none;background:transparent;font-size:1rem;font-weight:600;color:#333;padding:.65rem 0 .3rem;border-bottom:1px solid rgba(0,0,0,.05);font-family:inherit;}
        .withu-moment-title::placeholder{color:#c4c9d4;font-weight:500;}
        .withu-moment-think{width:100%;border:0;outline:none;resize:none;background:transparent;font-size:.95rem;line-height:1.65;color:#333;padding:.6rem 0 .8rem;min-height:92px;font-family:inherit;box-sizing:border-box;}
        .withu-moment-think::placeholder{color:#b9bec9;}
        .withu-moment-cells{border-top:1px solid rgba(0,0,0,.06);}
        .withu-moment-cells-cont{border-top:none;}
        .withu-moment-cell{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.82rem .1rem;border-bottom:1px solid rgba(0,0,0,.05);font-size:.9rem;color:#334155;}
        .withu-moment-cell-details{display:block;}
        .withu-moment-cell summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:.75rem;}
        .withu-moment-cell summary::-webkit-details-marker{display:none;}
        .withu-moment-cell-value{margin-left:auto;color:#8a8a8a;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:14rem;}
        .withu-moment-chev{color:#c4c9d4;font-style:normal;font-size:.95rem;margin-left:.3rem;transition:transform .15s;flex:none;}
        details[open] .withu-moment-chev{transform:rotate(90deg);}
        .withu-moment-select-wrap{margin-left:auto;display:inline-flex;align-items:center;min-width:0;}
        .withu-moment-select-wrap select{appearance:none;-webkit-appearance:none;border:0;background:transparent;font-size:.85rem;color:#8a8a8a;text-align:right;padding:0 .3rem 0 0;outline:none;cursor:pointer;max-width:12rem;font-family:inherit;}
        .withu-moment-select-wrap::after{content:'›';color:#c4c9d4;font-size:.95rem;}
        .withu-moment-loc-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;padding:.35rem 0 .7rem;width:100%;}
        .withu-moment-loc-grid .loc-full{grid-column:1/-1;}
        .withu-moment-loc-grid input{padding:.5rem .65rem;border:1px solid rgba(148,163,184,.5);border-radius:.55rem;font-size:.85rem;width:100%;box-sizing:border-box;outline:none;font-family:inherit;}
        .withu-moment-loc-grid input:focus{border-color:rgba(231,84,128,.5);}
        .withu-moment-cell-switch .switch{margin:0;}
        .withu-moment-note{padding:.1rem .1rem .6rem;font-size:.76rem;color:var(--text-light,#94a3b8);border-bottom:1px solid rgba(0,0,0,.05);}
        /* 可见范围选择器融入 cell 列表 */
        .withu-moments-form .withu-vis-picker{margin:0;width:100%;}
        .withu-moments-form .withu-vis-toggle{border:none;border-radius:0;padding:.82rem .1rem;box-shadow:none;}
        .withu-moments-form .withu-vis-picker.is-open .withu-vis-toggle{border:none;box-shadow:none;}
        .withu-moments-form .withu-vis-toggle-label{font-weight:400;font-size:.9rem;}
        .withu-moments-form .withu-vis-options{margin:.2rem 0 .5rem;}
        .withu-moments-form .withu-vis-hint{display:none;}
    </style>

    <div class="withu-moments-wrap">
    <?php if ($error): ?>
        <div class="withu-moments-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo e($error); ?></span>
        </div>
    <?php endif; ?>

    <div class="withu-moments-topbar">
        <a href="/admin/albums.php" class="withu-moments-back"><i class="fas fa-arrow-left"></i>相册列表</a>
        <span class="withu-moments-title">发布相册</span>
        <button type="submit" form="withuMomentsForm" id="withuMomentsPublish" class="withu-moments-publish">发表</button>
    </div>

    <form method="POST" enctype="multipart/form-data" id="withuMomentsForm" class="withu-moments-form" novalidate>
        <?php echo csrf_field(); ?>

        <div class="withu-moments-main">

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">相册照片 <span style="font-weight:400;color:var(--text-light);">像朋友圈一样添加照片，拖动即可调整排版，第一张自动作为封面</span></label>
            <input type="file" name="album_images[]" id="albumPhotosInput" accept="image/*" multiple class="withu-album-photo-input">
            <div id="albumPhotosPreview" class="withu-album-photos-preview" aria-live="polite"></div>
            <?php
            $maxUploadBytesCover = get_max_upload_size_bytes();
            $maxUploadMbCover    = round($maxUploadBytesCover / 1024 / 1024, 1);
            ?>
            <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                支持 JPG / PNG / GIF / WebP，点「+」继续添加、点 × 移除，按住缩略图拖动到目标位置可自由调整排序，单文件最大约 <?php echo $maxUploadMbCover; ?>MB。切换下方「相册排版」，这里的预览会同步变化。
            </div>
            <style>
                .withu-album-photo-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
                .withu-album-photos-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin-top:.6rem;max-width:24rem;}
                .withu-album-photo-thumb{position:relative;border-radius:.35rem;overflow:hidden;aspect-ratio:1/1;background:rgba(148,163,184,.15);cursor:grab;touch-action:none;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none;}
                .withu-album-photo-thumb.is-dragging{opacity:.35;cursor:grabbing;}
                .withu-album-photo-thumb.is-drop-target{box-shadow:inset 0 0 0 2px rgba(231,84,128,.85);}
                .withu-album-photo-thumb img{width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;}
                .withu-album-photo-idx{position:absolute;left:0;bottom:0;padding:.08rem .4rem;font-size:.66rem;line-height:1.5;color:#fff;background:rgba(0,0,0,.45);border-top-right-radius:.35rem;pointer-events:none;}
                .withu-album-photo-del{position:absolute;top:.2rem;right:.2rem;width:1.3rem;height:1.3rem;border:0;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;font-size:.8rem;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;touch-action:auto;}
                .withu-album-photo-del:hover{background:rgba(231,84,128,.9);}
                .withu-photo-ghost{position:fixed;z-index:60;pointer-events:none;cursor:grabbing;box-shadow:0 8px 24px rgba(0,0,0,.25);transform:translate(-50%,-50%) scale(1.05);opacity:.9;}
                .withu-album-photo-add{position:relative;border-radius:.35rem;aspect-ratio:1/1;border:1px dashed rgba(148,163,184,.7);background:rgba(148,163,184,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:1.6rem;font-weight:300;transition:border-color .15s,background .15s;}
                .withu-album-photo-add:hover{border-color:rgba(231,84,128,.6);background:rgba(231,84,128,.05);color:#e75480;}
                .withu-album-photo-add.is-drop-target{box-shadow:inset 0 0 0 2px rgba(231,84,128,.85);}
                /* 瀑布流预览：按图片真实比例分列排布 */
                .withu-album-photos-preview.mode-waterfall{display:block;columns:3;column-gap:.4rem;}
                .withu-album-photos-preview.mode-waterfall .withu-album-photo-thumb{margin-bottom:.4rem;break-inside:avoid;aspect-ratio:auto;}
                .withu-album-photos-preview.mode-waterfall .withu-album-photo-thumb img{height:auto;}
                .withu-album-photos-preview.mode-waterfall .withu-album-photo-add{display:flex;margin-bottom:.4rem;break-inside:avoid;}
                /* 心型拼图预览：5 列网格，前 17 张按心形图案定位 */
                .withu-album-photos-preview.mode-heart{grid-template-columns:repeat(5,1fr);}
            </style>
            <script>
            (function () {
                var input = document.getElementById('albumPhotosInput');
                var previewBox = document.getElementById('albumPhotosPreview');
                var layoutSelect = document.querySelector('#withuMomentsForm select[name="layout_mode"]');
                if (!input || !previewBox) return;

                // 心型拼图的 17 个格位（5 列网格的 row/column，图案：3-5-5-3-1）
                var HEART_CELLS = [[1,2],[1,3],[1,4],[2,1],[2,2],[2,3],[2,4],[2,5],[3,1],[3,2],[3,3],[3,4],[3,5],[4,2],[4,3],[4,4],[5,3]];

                function currentMode() {
                    return layoutSelect ? layoutSelect.value : 'grid';
                }

                // 心型模式下把元素摆到心形格位；超出图案容量的排在心形下方的普通行
                function placeHeart(el, i) {
                    if (i < HEART_CELLS.length) {
                        el.style.gridRow = HEART_CELLS[i][0];
                        el.style.gridColumn = HEART_CELLS[i][1];
                    } else {
                        el.style.gridRow = 6 + Math.floor((i - HEART_CELLS.length) / 5);
                    }
                }

                // 文件队列：顺序即提交顺序（第一张为封面），拖拽重排后通过 DataTransfer 同步回 input.files
                var selected = [];

                input.addEventListener('change', function () {
                    for (var i = 0; i < input.files.length; i++) {
                        selected.push(input.files[i]);
                    }
                    syncInput();
                });

                function resetFiles() {
                    try {
                        var dt = new DataTransfer();
                        for (var i = 0; i < selected.length; i++) {
                            dt.items.add(selected[i]);
                        }
                        input.files = dt.files;
                    } catch (e) { /* 老浏览器不支持 DataTransfer 时保留原始列表 */ }
                }

                function syncInput() {
                    resetFiles();
                    render();
                }

                function render() {
                    // 排版方式变化时预览同步切换：grid=九宫格，waterfall=瀑布流，heart=心型拼图
                    var mode = currentMode();
                    previewBox.className = 'withu-album-photos-preview' + (mode === 'grid' ? '' : ' mode-' + mode);
                    previewBox.innerHTML = '';
                    selected.forEach(function (file, idx) {
                        previewBox.appendChild(photoCell(file, idx, mode));
                    });
                    // 朋友圈式「+」格子：始终排在末尾，点击继续添加
                    var add = document.createElement('label');
                    add.className = 'withu-album-photo-add';
                    add.setAttribute('for', 'albumPhotosInput');
                    add.setAttribute('aria-label', '添加照片');
                    add.setAttribute('data-idx', selected.length);
                    add.textContent = '+';
                    if (mode === 'heart') {
                        placeHeart(add, selected.length);
                    }
                    previewBox.appendChild(add);
                }

                function photoCell(file, idx, mode) {
                    var item = document.createElement('div');
                    item.className = 'withu-album-photo-thumb';
                    item.setAttribute('data-idx', idx);
                    if (mode === 'heart') {
                        placeHeart(item, idx);
                    }
                    var url = URL.createObjectURL(file);
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = file.name || ('照片' + (idx + 1));
                    img.setAttribute('draggable', 'false');
                    img.onload = function () { URL.revokeObjectURL(url); };
                    var badge = document.createElement('span');
                    badge.className = 'withu-album-photo-idx';
                    badge.textContent = idx === 0 ? '封面' : '第' + (idx + 1) + '张';
                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'withu-album-photo-del';
                    del.setAttribute('aria-label', '移除' + (file.name || '这张照片'));
                    del.innerHTML = '&times;';
                    del.addEventListener('click', function () {
                        selected.splice(idx, 1);
                        syncInput();
                    });
                    item.appendChild(img);
                    item.appendChild(badge);
                    item.appendChild(del);
                    item.addEventListener('pointerdown', onDragStart);
                    return item;
                }

                // —— 拖拽排序（Pointer Events，桌面与触屏通用）——
                var drag = null;

                function onDragStart(e) {
                    if (e.button !== undefined && e.button !== 0) return;
                    if (e.target.closest('.withu-album-photo-del')) return; // 删除按钮不触发拖拽
                    drag = {
                        idx: parseInt(e.currentTarget.getAttribute('data-idx'), 10),
                        cellEl: e.currentTarget,
                        startX: e.clientX,
                        startY: e.clientY,
                        active: false,
                        ghost: null,
                        overIdx: parseInt(e.currentTarget.getAttribute('data-idx'), 10)
                    };
                    document.addEventListener('pointermove', onDragMove);
                    document.addEventListener('pointerup', onDragEnd);
                    document.addEventListener('pointercancel', onDragEnd);
                }

                function onDragMove(e) {
                    if (!drag) return;
                    var dx = e.clientX - drag.startX;
                    var dy = e.clientY - drag.startY;
                    if (!drag.active) {
                        if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
                        drag.active = true;
                        var rect = drag.cellEl.getBoundingClientRect();
                        var ghost = drag.cellEl.cloneNode(true);
                        ghost.classList.add('withu-photo-ghost');
                        ghost.classList.remove('is-dragging', 'is-drop-target');
                        ghost.style.width = rect.width + 'px';
                        ghost.style.height = rect.height + 'px';
                        document.body.appendChild(ghost);
                        drag.ghost = ghost;
                        drag.cellEl.classList.add('is-dragging');
                    }
                    e.preventDefault();
                    drag.ghost.style.left = e.clientX + 'px';
                    drag.ghost.style.top = e.clientY + 'px';
                    // 命中测试：拖到哪张的中间就插入到哪个位置（含末尾「+」格子）
                    var target = null, bestDist = Infinity;
                    var cells = previewBox.querySelectorAll('[data-idx]');
                    for (var i = 0; i < cells.length; i++) {
                        if (cells[i] === drag.cellEl) continue;
                        var r = cells[i].getBoundingClientRect();
                        var d = Math.pow(e.clientX - (r.left + r.width / 2), 2) + Math.pow(e.clientY - (r.top + r.height / 2), 2);
                        if (d < bestDist) { bestDist = d; target = cells[i]; }
                    }
                    var nextIdx = target ? parseInt(target.getAttribute('data-idx'), 10) : drag.idx;
                    if (nextIdx !== drag.overIdx) {
                        drag.overIdx = nextIdx;
                        var all = previewBox.querySelectorAll('[data-idx]');
                        for (var j = 0; j < all.length; j++) {
                            all[j].classList.toggle('is-drop-target', all[j] === target);
                        }
                    }
                }

                function onDragEnd() {
                    document.removeEventListener('pointermove', onDragMove);
                    document.removeEventListener('pointerup', onDragEnd);
                    document.removeEventListener('pointercancel', onDragEnd);
                    if (!drag) return;
                    var st = drag;
                    drag = null;
                    if (st.ghost) st.ghost.remove();
                    st.cellEl.classList.remove('is-dragging');
                    var targets = previewBox.querySelectorAll('.is-drop-target');
                    for (var i = 0; i < targets.length; i++) targets[i].classList.remove('is-drop-target');
                    if (st.active && st.overIdx !== st.idx) {
                        var moved = selected.splice(st.idx, 1)[0];
                        selected.splice(st.overIdx, 0, moved);
                    }
                    syncInput();
                }

                // 切换排版方式时，上方预览实时跟随变化
                if (layoutSelect) {
                    layoutSelect.addEventListener('change', render);
                }

                render();
            })();
            </script>
        </div>

        <input type="text" name="name" class="withu-moment-title" placeholder="相册标题" aria-label="相册标题" value="<?php echo e($_POST['name'] ?? ''); ?>" autocomplete="off">

        <textarea name="description" class="withu-moment-think" placeholder="这一刻的想法…" aria-label="相册描述"><?php echo e($_POST['description'] ?? ''); ?></textarea>

        </div>

        <aside class="withu-moments-side">
            <p class="withu-moments-side-title">内容设置</p>
        <div class="withu-moment-cells">
            <div class="withu-moment-cell">
                <span>相册排版</span>
                <span class="withu-moment-select-wrap">
                    <select name="layout_mode">
                        <option value="grid" <?php echo ($_POST['layout_mode'] ?? 'grid') === 'grid' ? 'selected' : ''; ?>>四宫格 / 九宫格</option>
                        <option value="waterfall" <?php echo ($_POST['layout_mode'] ?? '') === 'waterfall' ? 'selected' : ''; ?>>瀑布流</option>
                        <option value="heart" <?php echo ($_POST['layout_mode'] ?? '') === 'heart' ? 'selected' : ''; ?>>心型拼图</option>
                    </select>
                </span>
            </div>
            <div class="withu-moment-cell">
                <span>缩略图蒙版</span>
                <span class="withu-moment-select-wrap">
                    <select name="mask_type">
                        <option value="none" <?php echo ($_POST['mask_type'] ?? 'none') === 'none' ? 'selected' : ''; ?>>不裁切</option>
                        <option value="circle" <?php echo ($_POST['mask_type'] ?? '') === 'circle' ? 'selected' : ''; ?>>圆形</option>
                        <option value="heart" <?php echo ($_POST['mask_type'] ?? '') === 'heart' ? 'selected' : ''; ?>>心形</option>
                    </select>
                </span>
            </div>
            <details class="withu-moment-cell withu-moment-cell-details">
                <summary>
                    <span>所在位置</span>
                    <span class="withu-moment-cell-value" id="withuLocValue"><?php echo ($_POST['location_name'] ?? '') !== '' ? e($_POST['location_name']) : '未选择'; ?></span>
                    <i class="withu-moment-chev">›</i>
                </summary>
                <div class="withu-moment-loc-grid">
                    <input class="loc-full" name="location_name" placeholder="相册地点（详细地址仅情侣可见）" value="<?php echo e($_POST['location_name'] ?? ''); ?>" oninput="document.getElementById('withuLocValue').textContent=this.value||'未选择'">
                    <input type="number" step="any" name="latitude" placeholder="纬度" value="<?php echo e($_POST['latitude'] ?? ''); ?>">
                    <input type="number" step="any" name="longitude" placeholder="经度" value="<?php echo e($_POST['longitude'] ?? ''); ?>">
                </div>
            </details>
        </div>


        <div class="withu-moment-cells withu-moment-cells-cont">
            <?php echo withu_visibility_picker('visibility', withu_visibility_normalize($_POST['visibility'] ?? 'public'), '创建后可随时在「相册管理」中调整可见范围。'); ?>
            <div class="withu-moment-cell withu-moment-cell-switch">
                <span>允许另一半编辑与上传</span>
                <label class="switch">
                    <input
                        type="checkbox"
                        name="allow_partner_edit"
                        value="1"
                        aria-label="允许另一半编辑与上传"
                        <?php echo ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['allow_partner_edit'])) ? 'checked' : ''; ?>>
                    <span class="switch-track">
                        <span class="switch-thumb"></span>
                    </span>
                </label>
            </div>
            <div class="withu-moment-cell withu-moment-cell-switch" style="border-bottom:0;">
                <span>保持原始画质（不压缩）</span>
                <label class="switch<?php echo ($imageOptimizeEnabled === '1') ? '' : ' switch-disabled'; ?>">
                    <input
                        type="checkbox"
                        name="keep_original_quality"
                        value="1"
                        aria-label="不对该相册应用图片压缩"
                        <?php echo isset($_POST['keep_original_quality']) ? 'checked' : ''; ?>
                        <?php echo ($imageOptimizeEnabled === '1') ? '' : 'disabled'; ?>>
                    <span class="switch-track">
                        <span class="switch-thumb"></span>
                    </span>
                </label>
            </div>
            <?php if ((string)$imageOptimizeEnabled !== '1'): ?>
                <div class="withu-moment-note" style="border-bottom:0;padding-top:0;">
                    当前已在系统设置中关闭图片压缩，本选项暂不生效。如需单独控制，请先在「系统设置 → 上传与其他」中开启图片压缩。
                </div>
            <?php endif; ?>
        </div>
        </aside>
    </form>

    <script>
    // 提交反馈（UX 准则：提交需有 loading→成功/失败状态；8s 兜底恢复防卡死）
    (function () {
        var form = document.getElementById('withuMomentsForm');
        var btn = document.getElementById('withuMomentsPublish');
        if (!form || !btn) return;
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = '发表中…';
            setTimeout(function () { btn.disabled = false; btn.textContent = '发表'; }, 8000);
        });
    })();
    </script>
    </div>

<?php include __DIR__ . '/footer.php'; ?>
