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

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>新建相册</h1>
        <p>为一组特别的照片创建一个家</p>
    </section>

    <?php if ($error): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(248,113,113,0.05);border:1px solid rgba(248,113,113,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#b91c1c;font-size:0.9rem;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo e($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="admin-card" novalidate>
        <?php echo csrf_field(); ?>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">相册名称 *</label>
            <input
                type="text"
                name="name"
                value="<?php echo e($_POST['name'] ?? ''); ?>"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <div><label>相册排版</label><select name="layout_mode" style="width:100%;padding:.55rem"><option value="grid">四宫格 / 九宫格</option><option value="waterfall">瀑布流</option><option value="heart">心型拼图</option></select></div>
            <div><label>缩略图蒙版</label><select name="mask_type" style="width:100%;padding:.55rem"><option value="none">不裁切</option><option value="circle">圆形</option><option value="heart">心形</option></select></div>
        </div>
        <div class="form-group" style="margin-bottom:0.75rem;display:grid;grid-template-columns:2fr 1fr 1fr;gap:.75rem;">
            <input name="location_name" placeholder="相册地点（详细地址仅情侣可见）" value="<?php echo e($_POST['location_name'] ?? ''); ?>" style="padding:.55rem"><input type="number" step="any" name="latitude" placeholder="纬度" value="<?php echo e($_POST['latitude'] ?? ''); ?>" style="padding:.55rem"><input type="number" step="any" name="longitude" placeholder="经度" value="<?php echo e($_POST['longitude'] ?? ''); ?>" style="padding:.55rem">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">描述</label>
            <textarea
                name="description"
                style="width:100%;min-height:80px;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;resize:vertical;"><?php echo e($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">相册照片 <span style="font-weight:400;color:var(--text-light);">像朋友圈一样添加照片，拖动即可调整排版，第一张自动作为封面</span></label>
            <input type="file" name="album_images[]" id="albumPhotosInput" accept="image/*" multiple class="withu-album-photo-input">
            <div id="albumPhotosPreview" class="withu-album-photos-preview" aria-live="polite"></div>
            <?php
            $maxUploadBytesCover = get_max_upload_size_bytes();
            $maxUploadMbCover    = round($maxUploadBytesCover / 1024 / 1024, 1);
            ?>
            <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                支持 JPG / PNG / GIF / WebP，点「+」继续添加、点 × 移除，按住缩略图拖动到目标位置可自由调整排序，单文件最大约 <?php echo $maxUploadMbCover; ?>MB。
            </div>
            <style>
                .withu-album-photo-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
                .withu-album-photos-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin-top:.6rem;max-width:20rem;}
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
            </style>
            <script>
            (function () {
                var input = document.getElementById('albumPhotosInput');
                var previewBox = document.getElementById('albumPhotosPreview');
                if (!input || !previewBox) return;

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
                    previewBox.innerHTML = '';
                    selected.forEach(function (file, idx) {
                        previewBox.appendChild(photoCell(file, idx));
                    });
                    // 朋友圈式「+」格子：始终排在末尾，点击继续添加
                    var add = document.createElement('label');
                    add.className = 'withu-album-photo-add';
                    add.setAttribute('for', 'albumPhotosInput');
                    add.setAttribute('aria-label', '添加照片');
                    add.setAttribute('data-idx', selected.length);
                    add.textContent = '+';
                    previewBox.appendChild(add);
                }

                function photoCell(file, idx) {
                    var item = document.createElement('div');
                    item.className = 'withu-album-photo-thumb';
                    item.setAttribute('data-idx', idx);
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

                render();
            })();
            </script>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label class="switch">
                <input
                    type="checkbox"
                    name="allow_partner_edit"
                    value="1"
                    <?php echo ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['allow_partner_edit'])) ? 'checked' : ''; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">允许另一半编辑与上传（默认开启）</span>
            </label>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label class="switch<?php echo ($imageOptimizeEnabled === '1') ? '' : ' switch-disabled'; ?>">
                <input
                    type="checkbox"
                    name="keep_original_quality"
                    value="1"
                    <?php echo isset($_POST['keep_original_quality']) ? 'checked' : ''; ?>
                    <?php echo ($imageOptimizeEnabled === '1') ? '' : 'disabled'; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">不对该本相册应用图片压缩</span>
            </label>
            <div style="margin-top:0.2rem;font-size:0.78rem;color:var(--text-light);">
                <?php if ((string)$imageOptimizeEnabled === '1'): ?>
                    关闭时，本相册中的新图片将按照全局“图片压缩与 WebP 优化”规则进行压缩（推荐）。开启后，本相册的新图片会跳过主图压缩，仅生成缩略图与 WebP，更偏向保留原始画质，适合少量精修照片。
                <?php else: ?>
                    当前已在系统设置中关闭图片压缩，本选项暂不生效。如需单独控制，请先在“系统设置 → 上传与其他”中开启图片压缩。
                <?php endif; ?>
            </div>
        </div>

        <?php echo withu_visibility_picker('visibility', withu_visibility_normalize($_POST['visibility'] ?? 'public'), '创建后可随时在「相册管理」中调整可见范围。'); ?>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i>
                <span>创建相册</span>
            </button>
            <a href="/admin/albums.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <span>返回列表</span>
            </a>
        </div>
    </form>

<?php include __DIR__ . '/footer.php'; ?>
