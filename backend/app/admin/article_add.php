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
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">内容 *（富文本 / Markdown 双模式 · 实时预览 · 无缝互转）</label>

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

            <div class="md-toolbar" id="mdToolbar">
                <div class="md-toolbar-left">
                    <span class="md-toolbar-hint">富文本与 Markdown 可随时互转，内容不丢失</span>
                </div>
                <div class="md-toolbar-right">
                    <button type="button" id="editorModeVisual" class="editor-mode-btn active">
                        <i class="fas fa-eye"></i> 可视化
                    </button>
                    <button type="button" id="editorModeMarkdown" class="editor-mode-btn">
                        <i class="fab fa-markdown"></i> Markdown
                    </button>
                    <button type="button" id="editorModeCode" class="editor-mode-btn">
                        <i class="fas fa-code"></i> HTML
                    </button>
                </div>
            </div>

            <!-- 代码模式下的快捷插入按钮（如 H2/H3/H4 标题模板、引用块变体、代码块、下载按钮） -->
            <div class="code-snippet-toolbar" id="codeSnippetToolbar" style="display:none;">
                <button type="button" data-snippet="h2">H2 标题</button>
                <button type="button" data-snippet="h3">H3 标题</button>
                <button type="button" data-snippet="h4">H4 标题</button>
                <button type="button" data-snippet="blockquote">普通引用</button>
                <button type="button" data-snippet="blockquote-note">提示块</button>
                <button type="button" data-snippet="blockquote-warning">警告块</button>
                <button type="button" data-snippet="blockquote-success">成功块</button>
                <button type="button" data-snippet="code">代码块</button>
                <button type="button" data-snippet="download">下载按钮</button>
            </div>

            <!-- Markdown 编辑器（左书写 / 右实时渲染预览，与富文本无缝互转） -->
            <div id="markdownEditorWrapper" style="display:none;">
                <div class="code-snippet-toolbar" id="mdQuickToolbar" style="display:none;">
                    <button type="button" data-md="h2">H2</button>
                    <button type="button" data-md="h3">H3</button>
                    <button type="button" data-md="h4">H4</button>
                    <button type="button" data-md="bold"><b>B</b></button>
                    <button type="button" data-md="italic"><i>I</i></button>
                    <button type="button" data-md="strike"><s>S</s></button>
                    <button type="button" data-md="inlinecode">&lt;/&gt;</button>
                    <button type="button" data-md="quote">引用</button>
                    <button type="button" data-md="ul">• 列表</button>
                    <button type="button" data-md="ol">1. 列表</button>
                    <button type="button" data-md="codeblock">代码块</button>
                    <button type="button" data-md="link">链接</button>
                    <button type="button" data-md="image">图片</button>
                    <button type="button" data-md="hr">分割线</button>
                    <button type="button" data-md="note">提示块</button>
                    <button type="button" data-md="warning">警告块</button>
                    <button type="button" data-md="success">成功块</button>
                    <button type="button" data-md="download">下载按钮</button>
                </div>
                <div class="md-split">
                    <textarea
                        id="articleMarkdownEditor"
                        placeholder="在这里书写 Markdown，右侧实时预览渲染效果……"></textarea>
                    <div class="md-preview-pane" id="markdownPreview"></div>
                </div>
                <p class="md-hint">左侧书写 Markdown，右侧实时渲染预览；切换「可视化」会自动双向转换。发布时统一存储 HTML，前台展示不受影响。</p>
            </div>

            <!-- 男女主标记快捷按钮（独立于 wangEditor 菜单） -->
            <div class="author-mark-toolbar">
                <button type="button" id="btnMarkMale">标记为男主</button>
                <button type="button" id="btnMarkFemale">标记为女主</button>
                <button type="button" id="btnUnmarkAuthor">取消标记</button>
            </div>
            <!-- 实际提交用的隐藏 textarea，JS 在提交前同步编辑器的 HTML -->
            <textarea
                name="content"
                id="articleContent"
                style="display:none;"><?php echo e($initialContent); ?></textarea>

            <!-- 本次创建过程中上传过的文件相对路径（JSON 数组，由前端 JS 填充） -->
            <input type="hidden" name="new_uploads" id="newUploadsField" value="">

            <!-- 内容格式标记：正常总是 html（前端已转换）；前端 Markdown 渲染库缺失时为 markdown，由服务端 Parsedown 兜底 -->
            <input type="hidden" name="content_format" id="contentFormatField" value="html">

            <!-- wangEditor 容器（可视化编辑器：上方为菜单，下方为正文） -->
            <div id="articleEditorWrapper" style="width:100%;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);overflow:visible;background:#ffffff;">
                <div id="editorToolbar"></div>
                <div id="articleEditor" style="min-height:260px;"><?php echo $initialContent; ?></div>
            </div>
            
            <!-- 代码编辑器（保留原有代码模式，用于查看 / 微调 HTML） -->
            <textarea
                id="articleCodeEditor"
                style="display:none;width:100%;min-height:260px;padding:0.65rem 0.8rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.85rem;line-height:1.5;background:#1e293b;color:#e2e8f0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;resize:vertical;overflow:auto;"><?php echo e($initialContent); ?></textarea>
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
                <span class="switch-label">允许另一半在后台编辑这篇文章</span>
            </label>
            <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                关闭后，另一半将无法在后台编辑或删除这篇文章，但前台阅读不受影响。
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

    <!-- wangEditor 脚本（本地） -->
    <script src="/admin-assets/js/wangeditor.min.js"></script>

    <!-- Markdown 渲染（marked）与 HTML→Markdown 转换（turndown），均本地内置 -->
    <script src="/admin-assets/js/marked.min.js"></script>
    <script src="/admin-assets/js/turndown.js"></script>

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
    const WANG_CSRF_TOKEN = <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE); ?>;
    (function () {
        const editorWrapper = document.getElementById('articleEditorWrapper');
        const toolbarContainer = document.getElementById('editorToolbar');
        const editorContainer = document.getElementById('articleEditor');
        const codeEditor = document.getElementById('articleCodeEditor');
        const textarea = document.getElementById('articleContent');
        const uploadsField = document.getElementById('newUploadsField');
        const contentFormatField = document.getElementById('contentFormatField');
        const visualBtn = document.getElementById('editorModeVisual');
        const markdownBtn = document.getElementById('editorModeMarkdown');
        const codeBtn = document.getElementById('editorModeCode');
        const codeSnippetToolbar = document.getElementById('codeSnippetToolbar');
        const markdownWrapper = document.getElementById('markdownEditorWrapper');
        const mdEditor = document.getElementById('articleMarkdownEditor');
        const markdownPreview = document.getElementById('markdownPreview');
        const mdQuickToolbar = document.getElementById('mdQuickToolbar');
        const btnMarkMale = document.getElementById('btnMarkMale');
        const btnMarkFemale = document.getElementById('btnMarkFemale');
        const btnUnmarkAuthor = document.getElementById('btnUnmarkAuthor');
        
        if (!editorWrapper || !toolbarContainer || !editorContainer || !codeEditor || !textarea) return;

        // 防止重复初始化（某些情况下脚本可能被执行两次）
        if (editorContainer.getAttribute('data-we-inited') === '1') {
            return;
        }
        editorContainer.setAttribute('data-we-inited', '1');

        // 记录本次新建文章过程中成功上传过的文件相对路径（相对于 uploads/）
        const newUploads = [];
        function normalizeUploadPath(url) {
            if (!url || typeof url !== 'string') return null;
            // 去掉协议与域名部分
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
            if (newUploads.indexOf(rel) === -1) {
                newUploads.push(rel);
            }
        }

        // ------------------------
        // 初始化 wangEditor
        // ------------------------
        const E = window.wangEditor;
        // 使用 toolbar + text 双容器模式，避免多余的包裹结构
        const weEditor = new E(toolbarContainer, editorContainer);

        // 基础配置：同步内容到隐藏 textarea
        weEditor.config.zIndex = 10;
        weEditor.config.uploadImgServer = '/api/upload_image.php';
        // 新建文章阶段尚未有 article_id，这里仅传 CSRF，实际文件仍暂存于 uploads/articles 根目录；
        // 后续在文章编辑阶段上传的图片 / 视频会按文章 ID 自动分目录。
        weEditor.config.uploadImgParams = {
            _token: WANG_CSRF_TOKEN
        };
        weEditor.config.uploadVideoServer = '/api/upload_video.php';
        weEditor.config.uploadVideoParams = {
            _token: WANG_CSRF_TOKEN
        };
        // 图片上传：记录成功插入的路径，便于新建文章时清理未引用文件
        weEditor.config.uploadImgHooks = {
            customInsert: function (insertImgFn, result) {
                try {
                    if (!result) return;
                    if (typeof result.errno !== 'undefined' && result.errno !== 0) {
                        var errMsg = result.message || '图片上传失败，请稍后重试';
                        window.showToast(errMsg, 'error');
                        return;
                    }
                    var url = '';
                    if (result.data) {
                        if (Array.isArray(result.data) && result.data.length > 0) {
                            url = result.data[0];
                        } else if (typeof result.data === 'object' && result.data.url) {
                            url = result.data.url;
                        }
                    }
                    if (url) {
                        recordUploadPath(url);
                        insertImgFn(url);
                    }
                } catch (e) {}
            },
            fail: function (xhr, editor, res) {
                try {
                    var msg = (res && res.message) ? res.message : '';
                    if (!msg && xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) msg = parsed.message;
                        } catch (e) {}
                    }
                    if (!msg) {
                        msg = '图片上传失败，请稍后重试';
                    }
                    window.showToast(msg, 'error');
                } catch (e) {}
            },
            error: function (xhr, editor, res) {
                try {
                    var msg = (res && res.message) ? res.message : '';
                    if (!msg && xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) msg = parsed.message;
                        } catch (e) {}
                    }
                    if (!msg) {
                        msg = '图片上传失败，请稍后重试';
                    }
                    window.showToast(msg, 'error');
                } catch (e) {}
            }
        };
        // 适配当前后端返回结构：{ errno:0, data:[url] }
        weEditor.config.uploadVideoHooks = {
            customInsert: function (insertVideoFn, result) {
                try {
                    if (!result) return;
                    // 后端约定：errno != 0 表示失败，需要提示 message
                    if (typeof result.errno !== 'undefined' && result.errno !== 0) {
                        var errMsg = result.message || '视频上传失败，请稍后重试';
                        window.showToast(errMsg, 'error');
                        return;
                    }
                    var url = '';
                    if (result.data) {
                        if (Array.isArray(result.data) && result.data.length > 0) {
                            url = result.data[0];
                        } else if (typeof result.data === 'object' && result.data.url) {
                            url = result.data.url;
                        }
                    }
                    if (url) {
                        recordUploadPath(url);
                        insertVideoFn(url);
                    }
                } catch (e) {}
            },
            // 上传失败/出错时使用站内通知样式提示
            fail: function (xhr, editor, res) {
                try {
                    var msg = (res && res.message) ? res.message : '';
                    if (!msg && xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                msg = parsed.message;
                            }
                        } catch (e) {}
                    }
                    if (!msg) {
                        msg = '视频上传失败。可能是文件过大：可以在“系统设置 → 上传与其他 → 单文件最大上传大小（MB）”中调整，或开启“视频上传仅受服务器限制”开关；也可能是服务器上传大小限制导致，请压缩后重试或联系管理员。';
                    }
                    window.showToast(msg, 'error');
                } catch (e) {}
            },
            error: function (xhr, editor, res) {
                try {
                    var msg = (res && res.message) ? res.message : '';
                    if (!msg && xhr && xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed && parsed.message) {
                                msg = parsed.message;
                            }
                        } catch (e) {}
                    }
                    if (!msg) {
                        msg = '视频上传失败。可能是文件过大：可以在“系统设置 → 上传与其他 → 单文件最大上传大小（MB）”中调整，或开启“视频上传仅受服务器限制”开关；也可能是服务器上传大小限制导致，请压缩后重试或联系管理员。';
                    }
                    window.showToast(msg, 'error');
                } catch (e) {}
            }
        };
        // 禁用「网络图片」入口，避免外链失败带来的困惑
        weEditor.config.showLinkImg = false;
        weEditor.config.onchange = function (html) {
            textarea.value = html;
        };
        weEditor.create();
        // 初始化内容（优先 textarea，再回退到容器初始 HTML）
        weEditor.txt.html(textarea.value || editorContainer.innerHTML || '');
        
        // 兼容性处理：点击编辑区域任意空白处时，将光标移动到内容末尾
        editorContainer.addEventListener('click', function () {
            try {
                var textElem = editorContainer.querySelector('.w-e-text');
                if (!textElem) return;
                var range = document.createRange();
                range.selectNodeContents(textElem);
                range.collapse(false); // 光标到末尾
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            } catch (e) {}
        });

        // ------------------------
        // 三模式管理：visual（wangEditor 富文本）/ markdown（左书写右实时预览）/ code（HTML 源码）
        // 切换时以当前模式的“权威内容”为准，统一经 HTML 中转，双向无缝转换
        // ------------------------
        let currentMode = 'visual'; // 'visual' | 'markdown' | 'code'
        let turndownService = null;
        let mdFallbackNotified = false;

        function createTurndownService() {
            var td = new TurndownService({
                headingStyle: 'atx',
                codeBlockStyle: 'fenced',
                bulletListMarker: '-',
                emDelimiter: '*',
                strongDelimiter: '**'
            });
            // 注意：turndown 的字符串/数组 filter 只按标签名匹配，不支持选择器，
            // 且内置规则先于 keep 匹配，因此作者标记用函数 keep、变体引用块用 addRule 覆盖内置规则
            td.keep(function (node) {
                if (node.nodeType !== 1) return false;
                var tag = node.nodeName.toLowerCase();
                if (['audio', 'video', 'source', 'iframe', 'center', 'desc', 'quote', 'embed', 'object'].indexOf(tag) > -1) {
                    return true;
                }
                if (tag === 'span' && node.hasAttribute('data-author')) {
                    return true;
                }
                return false;
            });
            // 带样式变体的引用块（提示/警告/成功块）：整体保留 HTML，避免丢类名
            td.addRule('variantBlockquote', {
                filter: function (node) {
                    return node.nodeName === 'BLOCKQUOTE'
                        && /(^|\s)(bq-note|bq-warning|bq-success)(\s|$)/.test(node.className || '');
                },
                replacement: function (content, node) {
                    return '\n\n' + node.outerHTML + '\n\n';
                }
            });
            td.addRule('strikethrough', {
                filter: ['del', 's', 'strike'],
                replacement: function (content) { return '~~' + content + '~~'; }
            });
            td.addRule('horizontalRule', {
                filter: 'hr',
                replacement: function () { return '\n\n---\n\n'; }
            });
            return td;
        }

        // Markdown → HTML（返回 null 表示渲染库不可用，调用方走兜底）
        function mdToHtml(md) {
            if (!window.marked || typeof window.marked.parse !== 'function') return null;
            try {
                return window.marked.parse(md || '', { gfm: true, breaks: true, async: false });
            } catch (e) {
                return '';
            }
        }

        // HTML → Markdown（返回 null 表示转换库不可用，调用方走兜底）
        function htmlToMd(html) {
            if (!window.TurndownService) return null;
            try {
                if (!turndownService) turndownService = createTurndownService();
                return turndownService.turndown(html || '');
            } catch (e) {
                return null;
            }
        }

        function renderMarkdownPreview() {
            if (!markdownPreview || !mdEditor) return;
            var html = mdToHtml(mdEditor.value);
            markdownPreview.innerHTML = (html === null) ? '' : html;
        }

        // 取某一模式当前内容并统一转为 HTML
        function getModeHtml(mode) {
            if (mode === 'visual') return weEditor.txt.html() || '';
            if (mode === 'markdown' && mdEditor) {
                var html = mdToHtml(mdEditor.value);
                return (html === null) ? (mdEditor.value || '') : html;
            }
            return codeEditor.value || '';
        }

        function applyMode(mode, html) {
            editorWrapper.style.display = (mode === 'visual') ? 'block' : 'none';
            if (markdownWrapper) markdownWrapper.style.display = (mode === 'markdown') ? 'block' : 'none';
            codeEditor.style.display = (mode === 'code') ? 'block' : 'none';
            if (codeSnippetToolbar) codeSnippetToolbar.style.display = (mode === 'code') ? 'flex' : 'none';
            if (mdQuickToolbar) mdQuickToolbar.style.display = (mode === 'markdown') ? 'flex' : 'none';

            visualBtn.classList.toggle('active', mode === 'visual');
            if (markdownBtn) markdownBtn.classList.toggle('active', mode === 'markdown');
            codeBtn.classList.toggle('active', mode === 'code');

            if (mode === 'visual') {
                weEditor.txt.html(html || '');
            } else if (mode === 'markdown' && mdEditor) {
                var md = htmlToMd(html);
                if (md === null) {
                    // 转换库缺失：按原文保留（Markdown 兼容内嵌 HTML），仅提示一次
                    md = html || '';
                    if (!mdFallbackNotified) {
                        window.showToast('HTML 转 Markdown 组件未加载，已按原文保留（Markdown 兼容内嵌 HTML）', 'info');
                        mdFallbackNotified = true;
                    }
                }
                mdEditor.value = md;
                renderMarkdownPreview();
            } else {
                codeEditor.value = html || '';
            }
        }

        function switchToMode(next) {
            if (next === currentMode) return;
            if (next === 'markdown' && !window.marked) {
                window.showToast('Markdown 渲染组件未加载，请刷新页面重试', 'error');
                return;
            }
            var html = getModeHtml(currentMode);
            currentMode = next;
            applyMode(next, html);
        }

        if (visualBtn) {
            visualBtn.addEventListener('click', function () { switchToMode('visual'); });
        }
        if (markdownBtn) {
            markdownBtn.addEventListener('click', function () { switchToMode('markdown'); });
        }
        if (codeBtn) {
            codeBtn.addEventListener('click', function () { switchToMode('code'); });
        }

        // Markdown 实时预览（输入防抖）
        if (mdEditor) {
            var previewTimer = null;
            mdEditor.addEventListener('input', function () {
                if (previewTimer) clearTimeout(previewTimer);
                previewTimer = setTimeout(renderMarkdownPreview, 120);
            });
        }

        // ---- Markdown 光标 / 选区工具 ----
        function mdWrap(open, close, placeholder) {
            var start = mdEditor.selectionStart, end = mdEditor.selectionEnd;
            var text = mdEditor.value;
            var sel = text.substring(start, end) || (placeholder || '');
            mdEditor.value = text.substring(0, start) + open + sel + close + text.substring(end);
            mdEditor.selectionStart = start + open.length;
            mdEditor.selectionEnd = start + open.length + sel.length;
            mdEditor.focus();
            renderMarkdownPreview();
        }

        function mdInsert(snippet) {
            var start = mdEditor.selectionStart, end = mdEditor.selectionEnd;
            var text = mdEditor.value;
            mdEditor.value = text.substring(0, start) + snippet + text.substring(end);
            var pos = start + snippet.length;
            mdEditor.selectionStart = pos;
            mdEditor.selectionEnd = pos;
            mdEditor.focus();
            renderMarkdownPreview();
        }

        function mdPrefixLines(prefix, placeholder) {
            var start = mdEditor.selectionStart, end = mdEditor.selectionEnd;
            var text = mdEditor.value;
            var lineStart = text.lastIndexOf('\n', start - 1) + 1;
            var seg = text.substring(lineStart, end);
            var lines = (seg === '') ? [placeholder || ''] : seg.split('\n');
            var out = lines.map(function (l) { return (l.trim() === '') ? l : prefix + l; }).join('\n');
            mdEditor.value = text.substring(0, lineStart) + out + text.substring(end);
            mdEditor.selectionStart = lineStart;
            mdEditor.selectionEnd = lineStart + out.length;
            mdEditor.focus();
            renderMarkdownPreview();
        }

        // Markdown 快捷插入工具栏（标题 / 行内样式 / 列表 / 引用块变体 / 下载按钮）
        if (mdQuickToolbar && mdEditor) {
            mdQuickToolbar.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-md]');
                if (!btn) return;
                var type = btn.getAttribute('data-md');
                switch (type) {
                    case 'h2': mdPrefixLines('## ', '在这里输入标题'); break;
                    case 'h3': mdPrefixLines('### ', '在这里输入小标题'); break;
                    case 'h4': mdPrefixLines('#### ', '在这里输入小节标题'); break;
                    case 'bold': mdWrap('**', '**', '粗体文字'); break;
                    case 'italic': mdWrap('*', '*', '斜体文字'); break;
                    case 'strike': mdWrap('~~', '~~', '删除文字'); break;
                    case 'inlinecode': mdWrap('`', '`', '代码'); break;
                    case 'quote': mdPrefixLines('> ', '在这里输入引用内容'); break;
                    case 'ul': mdPrefixLines('- ', '列表项'); break;
                    case 'ol': mdPrefixLines('1. ', '列表项'); break;
                    case 'codeblock': mdInsert('\n```\n// 在这里粘贴代码\n```\n'); break;
                    case 'link': mdWrap('[', '](https://)', '链接文字'); break;
                    case 'image': mdInsert('![图片描述](图片地址)'); break;
                    case 'hr': mdInsert('\n\n---\n\n'); break;
                    case 'note': mdInsert('\n<blockquote class="bq-note">在这里输入提示内容</blockquote>\n'); break;
                    case 'warning': mdInsert('\n<blockquote class="bq-warning">在这里输入警告内容</blockquote>\n'); break;
                    case 'success': mdInsert('\n<blockquote class="bq-success">在这里输入成功提示</blockquote>\n'); break;
                    case 'download': mdInsert('\n<p><a class="btn-download" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> 下载附件</a></p>\n'); break;
                }
            });
        }

        // Markdown 模式下的男女主标记：用内嵌 HTML 包裹选中文本（前台与可视化模式同样识别）
        function mdWrapAuthor(role) {
            if (mdEditor.selectionStart === mdEditor.selectionEnd) {
                window.showToast('请先选中要标记的文字', 'info');
                return;
            }
            mdWrap('<span data-author="' + role + '">', '</span>', '');
        }

        function mdUnmarkSelection() {
            var start = mdEditor.selectionStart, end = mdEditor.selectionEnd;
            if (start === end) {
                window.showToast('请先选中要取消标记的文字', 'info');
                return;
            }
            var text = mdEditor.value;
            var seg = text.substring(start, end);
            var cleaned = seg.replace(/<span data-author="(?:male|female)">([\s\S]*?)<\/span>/g, '$1');
            mdEditor.value = text.substring(0, start) + cleaned + text.substring(end);
            mdEditor.selectionStart = start;
            mdEditor.selectionEnd = start + cleaned.length;
            mdEditor.focus();
            renderMarkdownPreview();
        }

        // 代码编辑器插入函数（仅代码模式）
        function insertCodeSnippet(snippet) {
            const start = codeEditor.selectionStart;
            const end = codeEditor.selectionEnd;
            const text = codeEditor.value;
            codeEditor.value = text.substring(0, start) + snippet + text.substring(end);
            const newPos = start + snippet.length;
            codeEditor.selectionStart = newPos;
            codeEditor.selectionEnd = newPos;
            codeEditor.focus();
        }

        // 代码模式下：绑定插入 H2/H3/H4 标题模板、各种引用块、代码块和下载按钮
        if (codeSnippetToolbar) {
            codeSnippetToolbar.addEventListener('click', function (e) {
                const btn = e.target.closest('button[data-snippet]');
                if (!btn) return;
                const type = btn.getAttribute('data-snippet');
                let snippet = '';
                if (type === 'h2') {
                    snippet = '<h2 class="h-title">在这里输入标题</h2>\n';
                } else if (type === 'h3') {
                    snippet = '<h3 class="h-title">在这里输入小标题</h3>\n';
                } else if (type === 'h4') {
                    snippet = '<h4 class="h-title">在这里输入小节标题</h4>\n';
                } else if (type === 'blockquote') {
                    snippet = '<blockquote>在这里输入引用内容</blockquote>\n';
                } else if (type === 'blockquote-note') {
                    snippet = '<blockquote class="bq-note">在这里输入提示内容</blockquote>\n';
                } else if (type === 'blockquote-warning') {
                    snippet = '<blockquote class="bq-warning">在这里输入警告内容</blockquote>\n';
                } else if (type === 'blockquote-success') {
                    snippet = '<blockquote class="bq-success">在这里输入成功提示</blockquote>\n';
                } else if (type === 'code') {
                    snippet = '<pre><code>// 在这里粘贴代码\n</code></pre>\n';
                } else if (type === 'download') {
                    snippet = '<p><a class="btn-download" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> 下载附件</a></p>\n';
                }
                if (!snippet) return;
                if (currentMode !== 'code') {
                    switchToMode('code');
                }
                insertCodeSnippet(snippet);
            });
        }

        // 原有的编辑器功能（依然使用 data-author 标记，作用于 HTML 片段）
        const editor = editorContainer;
        let lastRange = null;

        function saveSelection() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) return;
            const range = sel.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;
            if (range.collapsed) return;
            // 记录最近一次在编辑器内的非空选区
            lastRange = range.cloneRange();
        }

        editor.addEventListener('mouseup', saveSelection);
        editor.addEventListener('keyup', saveSelection);

        function getCurrentAuthor() {
            const autoBox = document.querySelector('input[name="enable_auto_author"]');
            const autoOn = autoBox && autoBox.checked;
            if (!autoOn) return '';
            const roleKey = window.CO_CURRENT_AUTHOR_ROLE || '';
            if (roleKey === 'male' || roleKey === 'female') {
                return roleKey;
            }
            return '';
        }

        function withAuthor(snippet) {
            // 极简、稳定：若设置了当前作者，则直接用 span[data-author] 包裹整个片段
            const author = getCurrentAuthor();
            if (!author) return snippet;
            return '<span data-author="' + author + '">' + snippet + '</span>';
        }

        function insertSnippet(snippet) {
            editor.focus();
            const finalSnippet = withAuthor(snippet);
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) {
                editor.insertAdjacentHTML('beforeend', finalSnippet);
                return;
            }
            const range = sel.getRangeAt(0);
            // 若选区不在编辑器内，则将光标移动到编辑器末尾
            if (!editor.contains(range.commonAncestorContainer)) {
                const newRange = document.createRange();
                newRange.selectNodeContents(editor);
                newRange.collapse(false);
                sel.removeAllRanges();
                sel.addRange(newRange);
            }
            const useRange = sel.getRangeAt(0);
            useRange.deleteContents();
            const fragment = useRange.createContextualFragment(finalSnippet);
            const lastNode = fragment.lastChild;
            useRange.insertNode(fragment);
            // 将光标移动到插入内容之后
            if (lastNode) {
                const newRange2 = document.createRange();
                newRange2.setStartAfter(lastNode);
                newRange2.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange2);
            }
        }

        function markSelectionAuthor(author) {
            if (!author) return;
            const sel = window.getSelection();
            let range = null;

            if (sel && sel.rangeCount > 0) {
                const current = sel.getRangeAt(0);
                if (editor.contains(current.commonAncestorContainer) && !current.collapsed) {
                    range = current;
                }
            }

            // 若当前选区不可用，则尝试使用最近一次记录的选区
            if (!range && lastRange) {
                range = lastRange.cloneRange();
                if (sel) {
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }

            // 情况一：有有效选区，按原逻辑包裹选中文本
            if (range && !range.collapsed && editor.contains(range.commonAncestorContainer)) {
                // 提前清理选区内部已有的 data-author span，避免重叠 / 套娃
                const contents = range.extractContents();
                const walker = document.createTreeWalker(contents, NodeFilter.SHOW_ELEMENT, null);
                const toUnwrap = [];
                while (true) {
                    const node = walker.nextNode();
                    if (!node) break;
                    if (node.nodeType === 1 && node.matches('span[data-author]')) {
                        toUnwrap.push(node);
                    }
                }
                toUnwrap.forEach(function (node) {
                    const parent = node.parentNode;
                    if (!parent) return;
                    while (node.firstChild) {
                        parent.insertBefore(node.firstChild, node);
                    }
                    parent.removeChild(node);
                });

                const wrapper = document.createElement('span');
                wrapper.setAttribute('data-author', author);
                wrapper.appendChild(contents);
                range.insertNode(wrapper);

                // 在标记块后面插入一个零宽字符，让光标落在一个“干净”的文本节点里
                const placeholder = document.createTextNode('\u200b');
                if (wrapper.parentNode) {
                    wrapper.parentNode.insertBefore(placeholder, wrapper.nextSibling);
                }

                // 将光标移动到占位文本之后，避免后续输入继续落在 span 内
                const newRange = document.createRange();
                newRange.setStart(placeholder, 1);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);

                // 本次标记完成后清空 lastRange，避免错误复用旧选区
                lastRange = null;
                return;
            }

            // 情况二：没有有效选区（光标在空白处）——插入一个带作者标记的空段落
            editor.focus();
            const insertSel = window.getSelection();
            let insertRange = null;

            if (insertSel && insertSel.rangeCount > 0) {
                const current = insertSel.getRangeAt(0);
                if (editor.contains(current.commonAncestorContainer)) {
                    insertRange = current;
                }
            }

            if (!insertRange) {
                insertRange = document.createRange();
                insertRange.selectNodeContents(editor);
                insertRange.collapse(false);
            }

            const span = document.createElement('span');
            span.setAttribute('data-author', author);
            const zw = document.createTextNode('\u200b');
            span.appendChild(zw);

            const p = document.createElement('p');
            p.appendChild(span);

            insertRange.insertNode(p);

            const caretRange = document.createRange();
            caretRange.setStart(zw, 1);
            caretRange.collapse(true);
            insertSel.removeAllRanges();
            insertSel.addRange(caretRange);

            lastRange = null;
        }

        function clearSelectionAuthor() {
            const sel = window.getSelection();
            if (!sel || sel.rangeCount === 0) {
                if (lastRange) {
                    sel.addRange(lastRange.cloneRange());
                } else {
                    return;
                }
            }
            const range = sel.getRangeAt(0);
            if (!editor.contains(range.commonAncestorContainer)) return;

            const isCollapsed = range.collapsed;
            let targetNodes = [];

            if (isCollapsed) {
                let node = range.startContainer;
                if (node.nodeType === 3) {
                    node = node.parentNode;
                }
                if (node && node.nodeType === 1 && node.matches('span[data-author]')) {
                    targetNodes.push(node);
                }
            } else {
                const contents = range.extractContents();
                const walker = document.createTreeWalker(contents, NodeFilter.SHOW_ELEMENT, null);
                while (true) {
                    const node = walker.nextNode();
                    if (!node) break;
                    if (node.nodeType === 1 && node.matches('span[data-author]')) {
                        targetNodes.push(node);
                    }
                }
                range.insertNode(contents);
            }

            targetNodes.forEach(function (node) {
                const parent = node.parentNode;
                if (!parent) return;
                while (node.firstChild) {
                    parent.insertBefore(node.firstChild, node);
                }
                parent.removeChild(node);
            });
        }

        // 男女主标记按钮绑定（可视化模式作用于选区；Markdown 模式用内嵌 HTML 包裹）
        if (btnMarkMale) {
            btnMarkMale.addEventListener('click', function () {
                if (currentMode === 'markdown' && mdEditor) {
                    mdWrapAuthor('male');
                    return;
                }
                markSelectionAuthor('male');
            });
        }
        if (btnMarkFemale) {
            btnMarkFemale.addEventListener('click', function () {
                if (currentMode === 'markdown' && mdEditor) {
                    mdWrapAuthor('female');
                    return;
                }
                markSelectionAuthor('female');
            });
        }
        if (btnUnmarkAuthor) {
            btnUnmarkAuthor.addEventListener('click', function () {
                if (currentMode === 'markdown' && mdEditor) {
                    mdUnmarkSelection();
                    return;
                }
                clearSelectionAuthor();
            });
        }

        // 在表单提交前，将编辑器内容同步到隐藏 textarea
        const form = document.querySelector('form.admin-card');
        if (form) {
            form.addEventListener('submit', function () {
                // 根据当前模式同步内容到隐藏的textarea
                var value = '';
                var format = 'html';
                if (currentMode === 'visual') {
                    // 可视化模式：直接使用 wangEditor 提供的 HTML，避免带上内部容器结构
                    value = weEditor.txt.html();
                } else if (currentMode === 'markdown' && mdEditor) {
                    // Markdown 模式：提交前转换为与富文本一致的 HTML 存储，前台展示不受影响
                    var html = mdToHtml(mdEditor.value);
                    if (html === null) {
                        // 前端渲染库缺失：上传 Markdown 原文，由服务端 Parsedown 兜底转换
                        value = mdEditor.value;
                        format = 'markdown';
                    } else {
                        value = html;
                    }
                } else {
                    value = codeEditor.value;
                }
                textarea.value = value || '';
                if (contentFormatField) {
                    contentFormatField.value = format;
                }

                // 在提交前，将本次上传过的文件相对路径写入隐藏字段
                if (uploadsField) {
                    if (newUploads.length) {
                        // 去重后写入 JSON，便于服务端解析
                        var dedup = Array.from(new Set(newUploads));
                        uploadsField.value = JSON.stringify(dedup);
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
/* 模式切换工具栏布局（可视化 / Markdown / HTML） */
.md-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
}

.md-toolbar-hint {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* Markdown 分栏：左书写 / 右实时预览 */
.md-split {
    display: flex;
    align-items: stretch;
    gap: 0.75rem;
}

#articleMarkdownEditor {
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
}

.md-preview-pane {
    flex: 1 1 0;
    min-width: 0;
    min-height: 360px;
    max-height: 560px;
    overflow: auto;
    padding: 0.9rem 1rem;
    border-radius: 0.75rem;
    border: 1px dashed rgba(148, 163, 184, 0.7);
    background: #f8fafc;
}

.md-hint {
    margin: 0.4rem 0 0;
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .md-split {
        flex-direction: column;
        gap: 0.5rem;
    }

    #articleMarkdownEditor {
        min-height: 200px;
    }

    .md-preview-pane {
        min-height: 180px;
        max-height: 320px;
    }
}

/* Markdown 预览排版（尽量贴近前台文章展示效果） */
#markdownPreview {
    font-size: 0.9rem;
    line-height: 1.75;
    color: #334155;
    word-break: break-word;
}

#markdownPreview:empty::before {
    content: "开始输入后，这里会实时显示渲染效果";
    color: #94a3b8;
    font-size: 0.85rem;
}

#markdownPreview h2,
#markdownPreview h3,
#markdownPreview h4 {
    margin: 1.1em 0 0.5em;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.4;
}

#markdownPreview h2 {
    font-size: 1.25rem;
    padding-left: 0.6rem;
    border-left: 4px solid #667eea;
}

#markdownPreview h3 {
    font-size: 1.1rem;
}

#markdownPreview h4 {
    font-size: 1rem;
}

#markdownPreview p {
    margin: 0.6em 0;
}

#markdownPreview ul,
#markdownPreview ol {
    margin: 0.6em 0;
    padding-left: 1.5em;
}

#markdownPreview li {
    margin: 0.25em 0;
}

#markdownPreview blockquote {
    margin: 0.8em 0;
    padding: 0.6em 0.9em;
    border-left: 4px solid #cbd5e1;
    background: #ffffff;
    border-radius: 0.5rem;
    color: #475569;
}

#markdownPreview blockquote.bq-note {
    border-left-color: #3b82f6;
    background: rgba(59, 130, 246, 0.06);
}

#markdownPreview blockquote.bq-warning {
    border-left-color: #f59e0b;
    background: rgba(245, 158, 11, 0.08);
}

#markdownPreview blockquote.bq-success {
    border-left-color: #10b981;
    background: rgba(16, 185, 129, 0.07);
}

#markdownPreview a {
    color: #6366f1;
    text-decoration: underline;
    word-break: break-all;
}

#markdownPreview img {
    max-width: 100%;
    border-radius: 0.5rem;
}

#markdownPreview code {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 0.25rem;
    padding: 0.1em 0.35em;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.85em;
}

#markdownPreview pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 0.8rem 1rem;
    border-radius: 0.6rem;
    overflow: auto;
}

#markdownPreview pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}

#markdownPreview hr {
    border: none;
    border-top: 1px dashed rgba(148, 163, 184, 0.8);
    margin: 1.2em 0;
}

#markdownPreview center {
    text-align: center;
}

#markdownPreview del,
#markdownPreview s {
    color: #94a3b8;
}

#markdownPreview table {
    border-collapse: collapse;
    width: 100%;
    margin: 0.8em 0;
}

#markdownPreview th,
#markdownPreview td {
    border: 1px solid rgba(148, 163, 184, 0.6);
    padding: 0.4em 0.6em;
}

#markdownPreview .btn-download {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    text-decoration: none;
    font-size: 0.85rem;
}

/* 预览中的男女主标记（与可视化编辑器一致） */
#markdownPreview span[data-author="male"] {
    background: rgba(129, 140, 248, 0.18);
    border-radius: 0.25rem;
    padding: 0 0.12em;
}

#markdownPreview span[data-author="female"] {
    background: rgba(244, 114, 182, 0.18);
    border-radius: 0.25rem;
    padding: 0 0.12em;
}

/* 编辑器模式切换按钮 */
.editor-mode-btn {
    padding: 0.4rem 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.4);
    background: #ffffff;
    color: #64748b;
    border-radius: 0.5rem;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.editor-mode-btn:hover {
    background: #f8fafc;
    border-color: rgba(148, 163, 184, 0.6);
    color: #475569;
}

.editor-mode-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.editor-mode-btn i {
    font-size: 0.75rem;
}

/* 男女主标记按钮区域 */
.author-mark-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.5rem;
}

.author-mark-toolbar button {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid rgba(148,163,184,0.7);
    background: #ffffff;
    font-size: 0.78rem;
    cursor: pointer;
}

.author-mark-toolbar button:hover {
    background: #f8fafc;
}

/* 代码模式下的快捷插入按钮工具栏 */
.code-snippet-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin: 0.4rem 0 0.5rem;
}

.code-snippet-toolbar button {
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid rgba(148,163,184,0.7);
    background: #ffffff;
    font-size: 0.78rem;
    cursor: pointer;
}

.code-snippet-toolbar button:hover {
    background: #f8fafc;
}

/* 代码编辑器滚动条美化 */
#articleCodeEditor::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#articleCodeEditor::-webkit-scrollbar-track {
    background: #0f172a;
    border-radius: 4px;
}

#articleCodeEditor::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 4px;
}

#articleCodeEditor::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

/* 原有样式 */
#articleEditor span[data-author="male"] {
    background: rgba(129, 140, 248, 0.18);
    border-radius: 0.25rem;
    padding: 0 0.12em;
    position: relative;
    display: inline;
}

#articleEditor span[data-author="female"] {
    background: rgba(244, 114, 182, 0.18);
    border-radius: 0.25rem;
    padding: 0 0.12em;
    position: relative;
    display: inline;
}

#articleEditor span[data-author="male"]::after,
#articleEditor span[data-author="female"]::after {
    position: absolute;
    bottom: 100%;
    left: 0; /* 改为左对齐，而不是居中 */
    transform: translateY(-0.1rem) scale(0.92);
    font-size: 0.75rem;
    line-height: 1.2;
    padding: 0.12rem 0.4rem;
    border-radius: 999px;
    color: #f9fafb;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
}

#articleEditor span[data-author="male"]:hover::after {
    content: "男主写的";
    background: rgba(59, 130, 246, 0.96);
    animation: co-bubble-in 0.26s cubic-bezier(0.16, 0.84, 0.44, 1) forwards;
}

#articleEditor span[data-author="female"]:hover::after {
    content: "女主写的";
    background: rgba(236, 72, 153, 0.96);
    animation: co-bubble-in 0.26s cubic-bezier(0.16, 0.84, 0.44, 1) forwards;
}

@keyframes co-bubble-in {
    0% {
        opacity: 0;
        transform: translateY(-0.1rem) scale(0.9);
    }
    100% {
        opacity: 1;
        transform: translateY(-0.22rem) scale(1);
    }
}

/* 提示编辑行为的鼠标样式 */
#articleEditor,
.co-block-editor {
    cursor: text;
}
</style>
