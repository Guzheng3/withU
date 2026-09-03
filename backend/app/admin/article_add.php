<?php
// 新版后台 - 撰写文章（移动端优先）
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/helpers.php';

// 尝试为老版本补充新字段（edit_mode / speaker）
migrate_schema_if_needed();

/**
 * 清理 wangEditor 生成的外层容器，只保留正文 HTML
 */
function clean_wangeditor_html(string $html): string
{
    if ($html === '' || (strpos($html, 'w-e-text') === false && strpos($html, 'w-e-text-container') === false)) {
        return $html;
    }

    // 优先使用 DOM 解析，找到带 w-e-text 类的节点，取其子节点 HTML
    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div>' . $html . '</div>';
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if ($loaded) {
            $xpath = new DOMXPath($dom);
            $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " w-e-text ")]');
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $innerHtml = '';
                foreach ($node->childNodes as $child) {
                    $innerHtml .= $dom->saveHTML($child);
                }
                libxml_clear_errors();
                return trim($innerHtml);
            }
        }
        libxml_clear_errors();
    }

    // 回退：简单粗暴地去掉 contenteditable 和 w-e- 开头的类，但保留标签内容
    $html = preg_replace('/\scontenteditable="true"/i', '', $html);
    $html = preg_replace('/\scontenteditable="false"/i', '', $html);
    $html = preg_replace('/\sid="text-elem[0-9]+"/i', '', $html);
    $html = preg_replace('/\sclass="([^"]*?)w-e-text[^"]*"/i', '', $html);
    $html = preg_replace('/\sclass="([^"]*?)w-e-[^"]*"/i', ' class="$1"', $html);

    return trim($html);
}

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();
// 获取情侣另一半信息（用于在编辑器中显示男主 / 女主）
$partner     = $auth->getPartner();

// 确保文章权限表存在（用于控制另一半是否可编辑）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `article_permissions` (
            `article_id` int(11) NOT NULL COMMENT '文章ID',
            `allow_partner_edit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许另一半编辑',
            `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
            PRIMARY KEY (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章权限表';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，后续仅在表存在时写入权限
}

// 确保文章贡献统计表存在（用于记录双方各自贡献了多少字）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `article_contributions` (
            `article_id` int(11) NOT NULL COMMENT '文章ID',
            `user_id` int(11) NOT NULL COMMENT '用户ID',
            `contributed_chars` int(11) NOT NULL DEFAULT 0 COMMENT '累计贡献字数',
            `last_updated_at` datetime NOT NULL COMMENT '最后更新时间',
            PRIMARY KEY (`article_id`, `user_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章贡献统计';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，仅影响后续共创统计
}

// 确保文章段落归属表存在（用于精确记录每段文字归属谁）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `article_segments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `article_id` int(11) NOT NULL COMMENT '文章ID',
            `user_id` int(11) NOT NULL COMMENT '用户ID',
            `start_offset` int(11) NOT NULL COMMENT '起始字符位置（从0开始）',
            `length` int(11) NOT NULL COMMENT '该段字符长度',
            `created_at` datetime NOT NULL COMMENT '创建时间',
            `updated_at` datetime NOT NULL COMMENT '最后更新时间',
            PRIMARY KEY (`id`),
            KEY `article_id` (`article_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章文字归属段落';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，仅影响精确统计
}

// 确保文章块表存在（用于按块记录每段 HTML 归属谁，便于后续共创展示）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `article_blocks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `article_id` int(11) NOT NULL COMMENT '文章ID',
            `block_index` int(11) NOT NULL COMMENT '块索引（从0开始）',
            `user_id` int(11) NOT NULL COMMENT '作者用户ID',
            `html` mediumtext NOT NULL COMMENT '该块的 HTML 内容',
            `created_at` datetime NOT NULL COMMENT '创建时间',
            `updated_at` datetime NOT NULL COMMENT '最后更新时间',
            PRIMARY KEY (`id`),
            KEY `article_id` (`article_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章块级内容归属';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，仅影响块级展示
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title           = trim($_POST['title'] ?? '');
    $content         = trim($_POST['content'] ?? '');
    $type            = $_POST['type'] ?? 'article';
    $isEncrypted     = isset($_POST['is_encrypted']) ? 1 : 0;
    $tags            = trim($_POST['tags'] ?? '');
    $disableComments = isset($_POST['disable_comments']) ? 1 : 0;
    $allowPartnerEdit = isset($_POST['allow_partner_edit']) ? 1 : 0;

    // 本次新建文章过程中，前端记录的“成功上传过的文件相对路径”列表（JSON 数组）
    $newUploadPaths = [];
    if (!empty($_POST['new_uploads'])) {
        $raw = (string) $_POST['new_uploads'];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $p) {
                if (is_string($p)) {
                    $p = trim($p);
                    if ($p !== '') {
                        $newUploadPaths[] = $p;
                    }
                }
            }
        }
        if (!empty($newUploadPaths)) {
            $newUploadPaths = array_values(array_unique($newUploadPaths));
        }
    }

    // 兜底：清理 wangEditor 可能带上的内部容器（w-e-text-container 等），只保留正文 HTML
    if ($content !== '') {
        $content = clean_wangeditor_html($content);
    }

    // Markdown 兜底：正常情况下前端已在提交前把 Markdown 转成 HTML；
    // 仅当前端渲染库不可用（content_format=markdown）时，用官方 Parsedown（ParsedownMarkdown）在服务端转换。
    // 注意：项目内旧的 core/Parsedown.php 为改过的 1.8.0-beta，会丢弃行内 HTML（如作者标记），故兜底不用它
    if (($_POST['content_format'] ?? '') === 'markdown' && $content !== '') {
        require_once __DIR__ . '/../core/ParsedownMarkdown.php';
        if (class_exists('ParsedownMarkdown')) {
            try {
                $parsedown = new ParsedownMarkdown();
                $content = (string) $parsedown->text($content);
            } catch (Exception $e) {
                // 转换失败则保持原文，不影响发文主流程
            }
        }
    }

    if ($title === '' || $content === '') {
        $error = '请填写标题和内容';
    } else {
        $data = [
            'user_id'          => $currentUser['id'],
            'title'            => $title,
            'content'          => $content,
            'type'             => $type,
            'is_encrypted'     => $isEncrypted,
            'comments_enabled' => $disableComments ? 0 : 1,
            'tags'             => $tags,
            'status'           => 'published',
            'edit_mode'        => 'full',
            'created_at'       => date('Y-m-d H:i:s'),
        ];

        $articleId = $db->insert('articles', $data);

        if ($articleId) {
            // 写入/更新文章权限（若权限表创建失败，此处忽略错误）
            try {
                $db->query("
                    INSERT INTO article_permissions (article_id, allow_partner_edit, updated_at)
                    VALUES (:article_id, :allow_partner_edit, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        allow_partner_edit = VALUES(allow_partner_edit),
                        updated_at = VALUES(updated_at)
                ", [
                    'article_id'        => $articleId,
                    'allow_partner_edit'=> $allowPartnerEdit,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                // 忽略权限表写入失败，保持文章创建主流程可用
            }
            // 基于 HTML 中的 data-author 标记，初始化逐字级的段落归属与贡献统计
            try {
                $db->delete('article_segments', 'article_id = :article_id', ['article_id' => $articleId]);
                $db->delete('article_contributions', 'article_id = :article_id', ['article_id' => $articleId]);

                $contentHtml = (string) $content;
                if ($contentHtml !== '') {
                    // 计算当前情侣中的男主 / 女主用户 ID
                    $maleId   = null;
                    $femaleId = null;
                    if (!empty($currentUser) && !empty($partner) && !empty($currentUser['role']) && !empty($partner['role'])) {
                        if ($currentUser['role'] === 'user1') {
                            $maleId   = (int) $currentUser['id'];
                            $femaleId = (int) $partner['id'];
                        } elseif ($currentUser['role'] === 'user2') {
                            $maleId   = (int) $partner['id'];
                            $femaleId = (int) $currentUser['id'];
                        }
                    }

                    $segments = [];
                    $stats    = [];
                    $nowSeg   = date('Y-m-d H:i:s');
                    $offset   = 0;

                    if (class_exists('DOMDocument')) {
                        libxml_use_internal_errors(true);
                        $dom = new DOMDocument('1.0', 'UTF-8');
                        $htmlWrapped = '<div>' . $contentHtml . '</div>';
                        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlWrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        if ($loaded) {
                            $root = $dom->getElementsByTagName('div')->item(0);
                            if ($root) {
                                $walker = function ($node, $currentAuthor) use (&$walker, &$offset, &$segments, &$stats, $maleId, $femaleId) {
                                    if ($node->nodeType === XML_TEXT_NODE) {
                                        $text = $node->nodeValue;
                                        if ($text === '') {
                                            return;
                                        }
                                        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                                        if ($length <= 0) {
                                            return;
                                        }
                                        $uid = null;
                                        if ($currentAuthor === 'male' && $maleId) {
                                            $uid = $maleId;
                                        } elseif ($currentAuthor === 'female' && $femaleId) {
                                            $uid = $femaleId;
                                        }
                                        if ($uid) {
                                            $segments[] = [
                                                'user_id'      => $uid,
                                                'start_offset' => $offset,
                                                'length'       => $length,
                                            ];
                                            if (!isset($stats[$uid])) {
                                                $stats[$uid] = 0;
                                            }
                                            $stats[$uid] += $length;
                                        }
                                        $offset += $length;
                                        return;
                                    }

                                    if ($node->nodeType === XML_ELEMENT_NODE) {
                                        $author = $currentAuthor;
                                        if ($node->hasAttribute('data-author')) {
                                            $val = $node->getAttribute('data-author');
                                            if ($val === 'male' || $val === 'female') {
                                                $author = $val;
                                            }
                                        }
                                        foreach ($node->childNodes as $child) {
                                            $walker($child, $author);
                                        }
                                    }
                                };

                                $walker($root, '');
                            }
                        }
                        libxml_clear_errors();
                    }

                    if (!empty($segments)) {
                        usort($segments, function ($a, $b) {
                            if ($a['start_offset'] === $b['start_offset']) {
                                return 0;
                            }
                            return ($a['start_offset'] < $b['start_offset']) ? -1 : 1;
                        });

                        $merged = [];
                        foreach ($segments as $seg) {
                            if ($seg['length'] <= 0) {
                                continue;
                            }
                            if (empty($merged)) {
                                $merged[] = $seg;
                                continue;
                            }
                            $lastIndex = count($merged) - 1;
                            $last      = $merged[$lastIndex];
                            if ($last['user_id'] === $seg['user_id']
                                && ($last['start_offset'] + $last['length']) === $seg['start_offset']) {
                                $merged[$lastIndex]['length'] += $seg['length'];
                            } else {
                                $merged[] = $seg;
                            }
                        }

                        foreach ($merged as $seg) {
                            $db->query("
                                INSERT INTO article_segments (article_id, user_id, start_offset, length, created_at, updated_at)
                                VALUES (:article_id, :user_id, :start_offset, :length, :created_at, :updated_at)
                            ", [
                                'article_id'   => $articleId,
                                'user_id'      => $seg['user_id'],
                                'start_offset' => $seg['start_offset'],
                                'length'       => $seg['length'],
                                'created_at'   => $nowSeg,
                                'updated_at'   => $nowSeg,
                            ]);
                        }

                        foreach ($stats as $uid => $chars) {
                            $db->query("
                                INSERT INTO article_contributions (article_id, user_id, contributed_chars, last_updated_at)
                                VALUES (:article_id, :user_id, :contributed_chars, :last_updated_at)
                                ON DUPLICATE KEY UPDATE
                                    contributed_chars = VALUES(contributed_chars),
                                    last_updated_at = VALUES(last_updated_at)
                            ", [
                                'article_id'        => $articleId,
                                'user_id'           => $uid,
                                'contributed_chars' => $chars,
                                'last_updated_at'   => $nowSeg,
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                // 忽略统计失败，不影响发文流程
            }

            // 初始化文章块表：当前实现为整篇文章作为单块归属当前创建者
            try {
                $now = date('Y-m-d H:i:s');
                $db->query("
                    INSERT INTO article_blocks (article_id, block_index, user_id, speaker, html, created_at, updated_at)
                    VALUES (:article_id, :block_index, :user_id, :speaker, :html, :created_at, :updated_at)
                ", [
                    'article_id'  => $articleId,
                    'block_index' => 0,
                    'user_id'     => $currentUser['id'],
                    'speaker'     => (!empty($currentUser['role']) && $currentUser['role'] === 'user1') ? 'male' : 'female',
                    'html'        => $content,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } catch (Exception $e) {
                // 忽略块级记录失败，不影响文章创建
            }

            // 清理本次创建过程中“上传过但最终未在正文中引用”的图片 / 视频文件
            if (!empty($newUploadPaths) && function_exists('extract_upload_paths_from_html')) {
                try {
                    $usedPaths = extract_upload_paths_from_html((string) $content);
                    if (!empty($usedPaths)) {
                        $usedPaths = array_values(array_unique($usedPaths));
                    }
                    $unusedPaths = array_diff($newUploadPaths, $usedPaths);
                    if (!empty($unusedPaths)) {
                        foreach ($unusedPaths as $relPath) {
                            // 当前为新建文章，article_id 传 0 即可，仅用于检查其它文章是否引用
                            delete_upload_file_if_unused($relPath, 0);
                        }
                    }
                } catch (Exception $e) {
                    // 忽略清理失败，避免影响发文主流程
                }
            }

            header('Location: articles.php?success=发布成功');
            exit;
        } else {
            $error = '发布失败，请重试';
        }
    }
}

$adminPage = 'articles';

include __DIR__ . '/header.php';
?>

    <section class="admin-page-title">
        <h1>撰写文章</h1>
        <p>记录一段新的故事</p>
    </section>

    <?php if ($error): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(248,113,113,0.05);border:1px solid rgba(248,113,113,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#b91c1c;font-size:0.9rem;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo e($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="admin-card" novalidate>
        <?php echo csrf_field(); ?>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">标题 *</label>
            <input
                type="text"
                name="title"
                value="<?php echo e($_POST['title'] ?? ''); ?>"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">类型</label>
            <select
                name="type"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
                <option value="article" <?php echo (($_POST['type'] ?? 'article') === 'article') ? 'selected' : ''; ?>>文章</option>
                <option value="diary" <?php echo (($_POST['type'] ?? '') === 'diary') ? 'selected' : ''; ?>>日记</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">内容 *（自定义标签书写 · 点击快捷按钮插入 · 实时预览）</label>

            <?php
            // 计算当前情侣中的男主 / 女主，用于标记按钮和统计
            $maleUser   = null;
            $femaleUser = null;
            $currentAuthorRoleKey = '';
            if (!empty($currentUser) && !empty($partner) && !empty($currentUser['role']) && !empty($partner['role'])) {
                if ($currentUser['role'] === 'user1') {
                    $maleUser   = $currentUser;
                    $femaleUser = $partner;
                } elseif ($currentUser['role'] === 'user2') {
                    $maleUser   = $partner;
                    $femaleUser = $currentUser;
                }
                if ($maleUser && (int)$maleUser['id'] === (int)$currentUser['id']) {
                    $currentAuthorRoleKey = 'male';
                } elseif ($femaleUser && (int)$femaleUser['id'] === (int)$currentUser['id']) {
                    $currentAuthorRoleKey = 'female';
                }
            }
            $initialContent = $_POST['content'] ?? '';
            ?>

            <!-- 自定义标签快捷插入工具栏 -->
            <div class="withu-tag-toolbar" id="withuTagToolbar">
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">标题</span>
                    <button type="button" class="withu-tag-btn" data-tag="h1">H1</button>
                    <button type="button" class="withu-tag-btn" data-tag="h2">H2</button>
                    <button type="button" class="withu-tag-btn" data-tag="h3">H3</button>
                    <button type="button" class="withu-tag-btn" data-tag="h4">H4</button>
                    <button type="button" class="withu-tag-btn" data-tag="h5">H5</button>
                    <button type="button" class="withu-tag-btn" data-tag="h6">H6</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">文字</span>
                    <button type="button" class="withu-tag-btn" data-tag="p">段落</button>
                    <button type="button" class="withu-tag-btn" data-tag="center">居中</button>
                    <button type="button" class="withu-tag-btn" data-tag="b"><b>B</b></button>
                    <button type="button" class="withu-tag-btn" data-tag="i"><i>I</i></button>
                    <button type="button" class="withu-tag-btn" data-tag="s"><s>S</s></button>
                    <button type="button" class="withu-tag-btn" data-tag="code">&lt;/&gt;</button>
                    <button type="button" class="withu-tag-btn" data-tag="a">链接</button>
                    <button type="button" class="withu-tag-btn" data-tag="hr">分割线</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">引用</span>
                    <button type="button" class="withu-tag-btn" data-tag="quote">引言</button>
                    <button type="button" class="withu-tag-btn" data-tag="desc">导语</button>
                    <button type="button" class="withu-tag-btn" data-tag="blockquote">引用块</button>
                    <button type="button" class="withu-tag-btn" data-tag="colorcard">高亮卡</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">媒体</span>
                    <button type="button" class="withu-tag-btn" data-tag="img">图片</button>
                    <button type="button" class="withu-tag-btn" data-tag="video">视频</button>
                    <button type="button" class="withu-tag-btn" data-tag="iframe">嵌入</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">音乐</span>
                    <button type="button" class="withu-tag-btn" data-tag="music-netease">网易云</button>
                    <button type="button" class="withu-tag-btn" data-tag="music-tencent">QQ音乐</button>
                    <button type="button" class="withu-tag-btn" data-tag="music-custom">自定义</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">代码</span>
                    <button type="button" class="withu-tag-btn" data-tag="codeblock">代码块</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">列表</span>
                    <button type="button" class="withu-tag-btn" data-tag="ul">• 列表</button>
                    <button type="button" class="withu-tag-btn" data-tag="ol">1. 列表</button>
                    <button type="button" class="withu-tag-btn" data-tag="table">表格</button>
                </div>
            </div>

            <!-- 男女主标记 + 上传 -->
            <div class="withu-tag-toolbar" style="margin-top:0.5rem;">
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">作者标记</span>
                    <button type="button" class="withu-tag-btn withu-tag-btn-author" id="btnMarkMale">标记为男主</button>
                    <button type="button" class="withu-tag-btn withu-tag-btn-author" id="btnMarkFemale">标记为女主</button>
                    <button type="button" class="withu-tag-btn withu-tag-btn-author" id="btnUnmarkAuthor">取消标记</button>
                </div>
                <div class="withu-tag-group">
                    <span class="withu-tag-group-title">上传</span>
                    <button type="button" class="withu-tag-btn withu-tag-btn-upload" id="btnUploadImage"><i class="fas fa-image"></i> 上传图片</button>
                    <button type="button" class="withu-tag-btn withu-tag-btn-upload" id="btnUploadVideo"><i class="fas fa-video"></i> 上传视频</button>
                    <input type="file" id="uploadImageInput" accept="image/*" style="display:none;">
                    <input type="file" id="uploadVideoInput" accept="video/*" style="display:none;">
                </div>
            </div>

            <!-- 源码编辑 + 实时预览 分栏 -->
            <div class="withu-split">
                <textarea
                    id="articleSourceEditor"
                    placeholder="在这里书写 HTML（用上方按钮快速插入标签）……"
                    spellcheck="false"><?php echo e($initialContent); ?></textarea>
                <div class="withu-preview-pane" id="articlePreview"></div>
            </div>
            <p class="withu-editor-hint">左侧书写 HTML，右侧实时渲染预览；按钮插入的占位文字选中后直接输入即可覆盖。发布时统一按 HTML 存储，前台展示不受影响。</p>

            <!-- 实际提交用的隐藏 textarea，JS 在提交前同步编辑器的 HTML -->
            <textarea
                name="content"
                id="articleContent"
                style="display:none;"><?php echo e($initialContent); ?></textarea>

            <!-- 本次创建过程中上传过的文件相对路径（JSON 数组，由前端 JS 填充） -->
            <input type="hidden" name="new_uploads" id="newUploadsField" value="">

            <!-- 内容格式标记：恒为 html -->
            <input type="hidden" name="content_format" id="contentFormatField" value="html">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">标签（逗号分隔）</label>
            <input
                type="text"
                name="tags"
                value="<?php echo e($_POST['tags'] ?? ''); ?>"
                placeholder="例如：恋爱、旅行、日常"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label class="switch">
                <input
                    type="checkbox"
                    name="allow_partner_edit"
                    value="1"
                    <?php echo (!isset($_POST['allow_partner_edit']) || isset($_POST['allow_partner_edit'])) ? 'checked' : ''; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">允许恋人在后台编辑这篇文章</span>
            </label>
            <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                关闭后，恋人将无法在后台编辑或删除这篇文章，但前台阅读不受影响。
            </p>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label class="switch">
                <input type="checkbox" name="is_encrypted" value="1" <?php echo isset($_POST['is_encrypted']) ? 'checked' : ''; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">加密内容（仅双方可见）</span>
            </label>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
            <label class="switch">
                <input type="checkbox" name="disable_comments" value="1" <?php echo isset($_POST['disable_comments']) ? 'checked' : ''; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">关闭评论区</span>
            </label>
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
                <span>发布文章</span>
            </button>
            <a href="/admin/articles.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <span>返回列表</span>
            </a>
        </div>
    </form>

    <script>
    // 若前台通用的 showToast 尚未定义，则在后台提供一个兼容版本，使用与前台一致的样式
    if (typeof window.showToast !== 'function') {
        (function () {
            function getToastContainer() {
                var container = document.getElementById('toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-container';
                    container.className = 'toast-container';
                    document.body.appendChild(container);
                }
                return container;
            }
            window.showToast = function (message, type) {
                if (!message) return;
                type = type || 'info';
                var container = getToastContainer();
                var toast = document.createElement('div');
                var msg = document.createElement('div');
                toast.className = 'toast' + (type ? ' toast-' + type : '');
                msg.className = 'toast-message';
                msg.textContent = message;
                toast.appendChild(msg);
                container.appendChild(toast);
                toast.addEventListener('click', function () {
                    toast.classList.add('toast-hide');
                    setTimeout(function () {
                        if (toast.parentNode) toast.parentNode.removeChild(toast);
                    }, 220);
                });
                var duration = type === 'error' ? 5200 : 3600;
                setTimeout(function () {
                    toast.classList.add('toast-hide');
                }, duration);
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, duration + 260);
            };
        })();
    }

    window.CO_CURRENT_AUTHOR_ROLE = <?php echo json_encode($currentAuthorRoleKey, JSON_UNESCAPED_UNICODE); ?>;
    const WITHU_CSRF_TOKEN = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE); ?>;

    (function () {
        const sourceEditor = document.getElementById('articleSourceEditor');
        const preview = document.getElementById('articlePreview');
        const textarea = document.getElementById('articleContent');
        const uploadsField = document.getElementById('newUploadsField');
        const contentFormatField = document.getElementById('contentFormatField');
        const tagToolbar = document.getElementById('withuTagToolbar');
        const btnMarkMale = document.getElementById('btnMarkMale');
        const btnMarkFemale = document.getElementById('btnMarkFemale');
        const btnUnmarkAuthor = document.getElementById('btnUnmarkAuthor');
        const btnUploadImage = document.getElementById('btnUploadImage');
        const btnUploadVideo = document.getElementById('btnUploadVideo');
        const uploadImageInput = document.getElementById('uploadImageInput');
        const uploadVideoInput = document.getElementById('uploadVideoInput');

        if (!sourceEditor || !textarea) return;
        // 防止重复初始化
        if (sourceEditor.getAttribute('data-withu-inited') === '1') return;
        sourceEditor.setAttribute('data-withu-inited', '1');

        // 记录本次编辑过程中成功上传过的文件相对路径（相对于 uploads/）
        const newUploads = [];
        function normalizeUploadPath(url) {
            if (!url || typeof url !== 'string') return null;
            var p = url.replace(/^https?:\/\/[^/]+/i, '');
            p = p.replace(/^\/+/, '');
            var idx = p.indexOf('uploads/');
            if (idx === -1) return null;
            var rel = p.substring(idx + 'uploads/'.length);
            rel = rel.replace(/^\/+/, '');
            return rel || null;
        }
        function recordUploadPath(url) {
            var rel = normalizeUploadPath(url);
            if (!rel) return;
            if (newUploads.indexOf(rel) === -1) newUploads.push(rel);
        }

        // 自定义标签模板：insert 为整段模板，sel 为插入后自动选中的占位文字；wrap 为选中/占位包裹
        const TAGS = {
            h1: { insert: '<h1>标题</h1>', sel: '标题' },
            h2: { insert: '<h2>小标题</h2>', sel: '小标题' },
            h3: { insert: '<h3>小标题</h3>', sel: '小标题' },
            h4: { insert: '<h4>小节标题</h4>', sel: '小节标题' },
            h5: { insert: '<h5>小标题</h5>', sel: '小标题' },
            h6: { insert: '<h6>脚注标题</h6>', sel: '脚注标题' },
            p: { insert: '<p>段落文字</p>', sel: '段落文字' },
            center: { insert: '<center>居中文字</center>', sel: '居中文字' },
            hr: { insert: '<hr>' },
            b: { wrap: ['<b>', '</b>'], ph: '加粗文字' },
            i: { wrap: ['<i>', '</i>'], ph: '斜体文字' },
            s: { wrap: ['<s>', '</s>'], ph: '删除文字' },
            code: { wrap: ['<code>', '</code>'], ph: '行内代码' },
            a: { insert: '<a href="https://" target="_blank">链接文字</a>', sel: '链接文字' },
            quote: { insert: '<quote>引言</quote>', sel: '引言' },
            desc: { insert: '<desc>导语或说明</desc>', sel: '导语或说明' },
            blockquote: { insert: '<blockquote>引用内容</blockquote>', sel: '引用内容' },
            colorcard: { insert: '<div class="color-card shadow-blur">高亮文字</div>', sel: '高亮文字' },
            img: { insert: '<img alt="图片描述" src="图片地址">', sel: '图片地址' },
            video: { insert: '<video id="withUPlayerVideo" class="withu-player-video" controls><source src="视频地址" type="video/mp4"></video>', sel: '视频地址' },
            iframe: { insert: '<iframe src="https://" allowfullscreen="true"></iframe>' },
            'music-netease': { insert: '<audio id="music" src data-id="歌曲ID" data-type="netease"></audio>', sel: '歌曲ID' },
            'music-tencent': { insert: '<audio id="music" src data-id="歌曲ID" data-type="tencent"></audio>', sel: '歌曲ID' },
            'music-custom': { insert: '<audio id="music" src="" data-type="custom" data-name="歌名" data-author="歌手" data-cover="封面地址" data-url="音频地址"></audio>', sel: '歌名' },
            codeblock: { insert: '<pre><button id="btn">Copy</button><code contenteditable="false" class="language-html" id="copy"><xmp>在这里粘贴代码</xmp></code></pre>', sel: '在这里粘贴代码' },
            ul: { insert: '<ul>\n<li>列表项</li>\n<li>列表项</li>\n</ul>', sel: '列表项' },
            ol: { insert: '<ol>\n<li>列表项</li>\n<li>列表项</li>\n</ol>', sel: '列表项' },
            table: { insert: '<table border="1">\n<thead><tr><th>表头1</th><th>表头2</th></tr></thead>\n<tbody><tr><td>内容</td><td>内容</td></tr></tbody>\n</table>', sel: '表头1' }
        };

        function insertBlock(snippet, sel) {
            const start = sourceEditor.selectionStart;
            const end = sourceEditor.selectionEnd;
            const val = sourceEditor.value;
            sourceEditor.value = val.substring(0, start) + snippet + val.substring(end);
            if (sel) {
                const idx = snippet.indexOf(sel);
                if (idx > -1) {
                    sourceEditor.selectionStart = start + idx;
                    sourceEditor.selectionEnd = start + idx + sel.length;
                } else {
                    sourceEditor.selectionStart = sourceEditor.selectionEnd = start + snippet.length;
                }
            } else {
                sourceEditor.selectionStart = sourceEditor.selectionEnd = start + snippet.length;
            }
            sourceEditor.focus();
            sync();
            renderPreview();
        }

        function wrapSelection(open, close, ph) {
            const start = sourceEditor.selectionStart;
            const end = sourceEditor.selectionEnd;
            const val = sourceEditor.value;
            const sel = val.substring(start, end);
            const text = sel !== '' ? sel : (ph || '');
            const out = open + text + close;
            sourceEditor.value = val.substring(0, start) + out + val.substring(end);
            sourceEditor.selectionStart = start + open.length;
            sourceEditor.selectionEnd = start + open.length + text.length;
            sourceEditor.focus();
            sync();
            renderPreview();
        }

        function applyTag(key) {
            const def = TAGS[key];
            if (!def) return;
            if (def.wrap) {
                wrapSelection(def.wrap[0], def.wrap[1], def.ph);
            } else if (def.insert) {
                insertBlock(def.insert, def.sel);
            }
        }

        function sync() {
            textarea.value = sourceEditor.value;
            if (contentFormatField) contentFormatField.value = 'html';
        }

        function renderPreview() {
            if (preview) preview.innerHTML = sourceEditor.value || '';
        }

        let previewTimer = null;
        sourceEditor.addEventListener('input', function () {
            sync();
            clearTimeout(previewTimer);
            previewTimer = setTimeout(renderPreview, 120);
        });
        sync();
        renderPreview();

        // 工具栏按钮：插入对应标签模板
        if (tagToolbar) {
            tagToolbar.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-tag]');
                if (!btn) return;
                e.preventDefault();
                applyTag(btn.getAttribute('data-tag'));
            });
        }

        // 作者标记：将选中文字包裹为 <span data-author="male|female">
        function markAuthor(role) {
            const start = sourceEditor.selectionStart;
            const end = sourceEditor.selectionEnd;
            const val = sourceEditor.value;
            const sel = val.substring(start, end);
            if (sel === '') {
                window.showToast('请先选中要标记的文字', 'error');
                return;
            }
            const out = '<span data-author="' + role + '">' + sel + '</span>';
            sourceEditor.value = val.substring(0, start) + out + val.substring(end);
            sourceEditor.selectionStart = start;
            sourceEditor.selectionEnd = start + out.length;
            sourceEditor.focus();
            sync();
            renderPreview();
        }

        function unmarkAuthor() {
            const start = sourceEditor.selectionStart;
            const end = sourceEditor.selectionEnd;
            const val = sourceEditor.value;
            const sel = val.substring(start, end);
            if (sel === '') {
                window.showToast('请先选中要取消标记的文字', 'error');
                return;
            }
            const cleaned = sel.replace(/<span[^>]*data-author=["'](?:male|female)["'][^>]*>([\s\S]*?)<\/span>/gi, '$1');
            sourceEditor.value = val.substring(0, start) + cleaned + val.substring(end);
            sourceEditor.focus();
            sync();
            renderPreview();
        }

        if (btnMarkMale) btnMarkMale.addEventListener('click', function () { markAuthor('male'); });
        if (btnMarkFemale) btnMarkFemale.addEventListener('click', function () { markAuthor('female'); });
        if (btnUnmarkAuthor) btnUnmarkAuthor.addEventListener('click', unmarkAuthor);

        // 上传图片 / 视频：成功后插入对应标签并记录文件路径
        function uploadFile(input, kind) {
            if (!input || !input.files || !input.files.length) return;
            const file = input.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', WITHU_CSRF_TOKEN);
            const endpoint = kind === 'image' ? '/api/upload_image.php' : '/api/upload_video.php';
            const btn = kind === 'image' ? btnUploadImage : btnUploadVideo;
            if (btn) btn.disabled = true;
            window.showToast('正在上传' + (kind === 'image' ? '图片' : '视频') + '……', 'info');
            fetch(endpoint, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (resp) { return resp.json(); })
                .then(function (result) {
                    if (btn) btn.disabled = false;
                    if (result && typeof result.errno !== 'undefined' && result.errno !== 0) {
                        window.showToast(result.message || '上传失败，请稍后重试', 'error');
                        return;
                    }
                    let url = '';
                    if (result && result.data) {
                        if (Array.isArray(result.data) && result.data.length > 0) {
                            url = result.data[0];
                        } else if (typeof result.data === 'object' && result.data.url) {
                            url = result.data.url;
                        }
                    }
                    if (!url) {
                        window.showToast('上传失败，请稍后重试', 'error');
                        return;
                    }
                    recordUploadPath(url);
                    if (kind === 'image') {
                        insertBlock('<img alt="" src="' + url + '">', '');
                    } else {
                        insertBlock('<video id="withUPlayerVideo" class="withu-player-video" controls src="' + url + '"></video>', '');
                    }
                    window.showToast('上传成功', 'success');
                })
                .catch(function () {
                    if (btn) btn.disabled = false;
                    window.showToast('上传失败，请稍后重试', 'error');
                })
                .finally(function () {
                    input.value = '';
                });
        }

        if (btnUploadImage && uploadImageInput) btnUploadImage.addEventListener('click', function () { uploadImageInput.click(); });
        if (btnUploadVideo && uploadVideoInput) btnUploadVideo.addEventListener('click', function () { uploadVideoInput.click(); });
        if (uploadImageInput) uploadImageInput.addEventListener('change', function () { uploadFile(uploadImageInput, 'image'); });
        if (uploadVideoInput) uploadVideoInput.addEventListener('change', function () { uploadFile(uploadVideoInput, 'video'); });

        // 提交前把源码同步进隐藏 textarea，并回填上传文件列表
        const form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                textarea.value = sourceEditor.value;
                if (contentFormatField) contentFormatField.value = 'html';
                if (uploadsField) {
                    if (newUploads.length) {
                        uploadsField.value = JSON.stringify(Array.from(new Set(newUploads)));
                    } else {
                        uploadsField.value = '';
                    }
                }
            });
        }
    })();
    </script>

<?php include __DIR__ . '/footer.php'; ?>

<style>
/* 自定义标签快捷插入工具栏 */
.withu-tag-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 0.9rem;
    padding: 0.5rem 0.7rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: #f8fafc;
    margin-bottom: 0.5rem;
}

.withu-tag-group {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.3rem;
}

.withu-tag-group-title {
    font-size: 0.72rem;
    color: #94a3b8;
    margin-right: 0.15rem;
    white-space: nowrap;
}

.withu-tag-btn {
    padding: 0.22rem 0.5rem;
    font-size: 0.78rem;
    line-height: 1.3;
    border-radius: 0.45rem;
    border: 1px solid rgba(148, 163, 184, 0.55);
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all .15s ease;
    white-space: nowrap;
}

.withu-tag-btn:hover {
    border-color: #667eea;
    color: #667eea;
    background: #eef2ff;
}

.withu-tag-btn-author {
    border-color: rgba(16, 185, 129, 0.5);
    color: #047857;
}

.withu-tag-btn-author:hover {
    border-color: #10b981;
    background: #ecfdf5;
}

.withu-tag-btn-upload {
    border-color: rgba(102, 126, 234, 0.5);
    color: #4338ca;
}

.withu-tag-btn-upload:hover {
    border-color: #667eea;
    background: #eef2ff;
}

/* 源码编辑 + 实时预览 分栏 */
.withu-split {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
}

#articleSourceEditor {
    flex: 1 1 0;
    min-width: 0;
    min-height: 360px;
    padding: 0.65rem 0.8rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.7);
    background: #ffffff;
    color: #0f172a;
    font-size: 0.85rem;
    line-height: 1.7;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    resize: vertical;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
}

.withu-preview-pane {
    flex: 1 1 0;
    min-width: 0;
    min-height: 360px;
    max-height: 560px;
    overflow: auto;
    padding: 0.9rem 1rem;
    border-radius: 0.75rem;
    border: 1px dashed rgba(148, 163, 184, 0.7);
    background: #ffffff;
    font-size: 0.9rem;
    line-height: 1.75;
    color: #334155;
    word-break: break-word;
}

.withu-preview-pane:empty::before {
    content: "点击上方按钮或开始书写后，这里会实时显示渲染效果";
    color: #94a3b8;
    font-size: 0.85rem;
}

.withu-editor-hint {
    margin: 0.4rem 0 0;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.5;
}

/* 预览排版：贴近前台 withu 文章展示效果 */
#articlePreview h1,
#articlePreview h2,
#articlePreview h3,
#articlePreview h4,
#articlePreview h5,
#articlePreview h6 {
    margin: 1.1em 0 0.5em;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.4;
}

#articlePreview h1 { font-size: 1.5rem; }
#articlePreview h2 { font-size: 1.3rem; padding-left: 0.6rem; border-left: 4px solid #667eea; }
#articlePreview h3 { font-size: 1.15rem; }
#articlePreview h4 { font-size: 1rem; }
#articlePreview p { margin: 0.6em 0; }
#articlePreview img { max-width: 100%; border-radius: 0.6rem; }

#articlePreview blockquote {
    margin: 0.8em 0;
    padding: 0.6em 1em;
    border-left: 4px solid rgba(102, 126, 234, 0.6);
    background: #f8fafc;
    border-radius: 0 0.5rem 0.5rem 0;
    color: #475569;
}

#articlePreview quote {
    display: block;
    margin: 0.8em 0;
    padding: 0.5em 1em;
    border-left: 4px solid #f59e0b;
    background: #fffbeb;
    color: #92400e;
    border-radius: 0 0.5rem 0.5rem 0;
    font-style: italic;
}

#articlePreview desc {
    display: block;
    margin: 0.6em 0;
    color: #64748b;
    font-size: 0.9em;
    line-height: 1.6;
}

#articlePreview center { display: block; text-align: center; }

#articlePreview .color-card {
    display: inline-block;
    margin: 0.4em 0;
    padding: 0.5em 1em;
    border-radius: 0.75rem;
    color: #fff;
    background: linear-gradient(135deg, #667eea, #764ba2);
    box-shadow: 0 8px 20px rgba(118, 75, 162, 0.25);
}

#articlePreview table { border-collapse: collapse; width: 100%; margin: 0.8em 0; }
#articlePreview th, #articlePreview td { border: 1px solid #e2e8f0; padding: 0.4em 0.6em; font-size: 0.85em; }
#articlePreview th { background: #f1f5f9; }
#articlePreview ul, #articlePreview ol { margin: 0.6em 0; padding-left: 1.5em; }
#articlePreview hr { border: none; border-top: 1px solid #e2e8f0; margin: 1em 0; }

#articlePreview pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 0.8em 1em;
    border-radius: 0.6rem;
    overflow: auto;
    margin: 0.8em 0;
}

#articlePreview pre code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.8em;
}

#articlePreview span[data-author="male"],
#articlePreview span[data-author="female"] {
    border-bottom: 2px dashed #10b981;
}

@media (max-width: 768px) {
    .withu-split {
        flex-direction: column;
        gap: 0.5rem;
    }

    #articleSourceEditor {
        min-height: 200px;
    }

    .withu-preview-pane {
        min-height: 180px;
        max-height: 320px;
    }
}
</style>
