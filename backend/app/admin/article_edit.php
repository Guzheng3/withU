<?php
// 新版后台 - 编辑文章（移动端优先）
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

    $html = preg_replace('/\scontenteditable="true"/i', '', $html);
    $html = preg_replace('/\scontenteditable="false"/i', '', $html);
    $html = preg_replace('/\sid="text-elem[0-9]+"/i', '', $html);
    $html = preg_replace('/\sclass="([^"]*?)w-e-text[^"]*"/i', '', $html);
    $html = preg_replace('/\sclass="([^"]*?)w-e-[^"]*"/i', ' class="$1"', $html);

    return trim($html);
}

/**
 * 规范块级 HTML，去掉多余的空段落（如大量 <p><br></p>）
 */
function normalize_block_html_for_save(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return $html;
    }

    // 直接移除所有“完全空”的段落：
    // <p>、<p><br></p>、只有空格/&nbsp; 的 <p> 都会被干掉
    $html = preg_replace(
        '/\s*<p>\s*(?:&nbsp;|\xC2\xA0|\s|<br\s*\/?>)*<\/p>\s*/iu',
        '',
        $html
    );

    return trim($html);
}

$auth = new Auth();
$auth->requireLogin();
$db          = Database::getInstance();
$currentUser = $auth->getCurrentUser();
$partner     = $auth->getPartner();

$error   = '';
$success = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: articles.php');
    exit;
}

// 获取当前文章（情侣双方共享，按 id 即可）
$article = $db->fetch(
    "SELECT * FROM articles WHERE id = :id LIMIT 1",
    ['id' => $id]
);

if (!$article) {
    header('Location: articles.php?success=' . urlencode('未找到该文章'));
    exit;
}

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

// 确保文章编辑记录表存在（用于前台判断是否为共创，保留）
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `article_edit_logs` (
            `article_id` int(11) NOT NULL COMMENT '文章ID',
            `user_id` int(11) NOT NULL COMMENT '编辑用户ID',
            `last_edited_at` datetime NOT NULL COMMENT '最后编辑时间',
            PRIMARY KEY (`article_id`, `user_id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章编辑记录';
    ");
} catch (Exception $e) {
    // 表创建失败不影响其它逻辑，仅影响前台共创判断
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
    // 表创建失败不影响其它逻辑，仅影响共创统计
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

// 读取文章权限（若表不存在或查询失败，则默认允许另一半编辑）
$allowPartnerEdit = 1;
try {
    $permRow = $db->fetch(
        "SELECT allow_partner_edit FROM article_permissions WHERE article_id = :article_id",
        ['article_id' => $id]
    );
    if ($permRow) {
        $allowPartnerEdit = (int) $permRow['allow_partner_edit'];
    }
} catch (Exception $e) {
    $allowPartnerEdit = 1;
}

// 计算是否有权限编辑：创建者永远可编辑；另一半仅在允许编辑时可编辑
$isOwner       = isset($article['user_id']) && (int) $article['user_id'] === (int) $currentUser['id'];
$partnerId     = $partner['id'] ?? null;
$isPartnerUser = $partnerId && isset($article['user_id']) && (int) $article['user_id'] === (int) $partnerId;
$canEdit       = $isOwner || ($isPartnerUser && $allowPartnerEdit);

if (!$canEdit) {
    header('Location: articles.php?error=' . urlencode('你没有权限编辑这篇文章'));
    exit;
}

// 读取当前文章的男主 / 女主贡献字数，用于在后台给参与共创的人一个提示
$maleChars   = 0;
$femaleChars = 0;
$articleCoCreated = false;
try {
    // 仅在情侣双方信息齐全时统计
    if (!empty($currentUser) && !empty($partner)) {
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

        $rows = $db->fetchAll(
            "SELECT user_id, contributed_chars 
             FROM article_contributions 
             WHERE article_id = :article_id",
            ['article_id' => $id]
        );

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['user_id']] = (int) $row['contributed_chars'];
        }

        // 通过 role 映射男主 / 女主
        $maleId   = null;
        $femaleId = null;
        if (!empty($currentUser['role']) && !empty($partner['role'])) {
            if ($currentUser['role'] === 'user1') {
                $maleId   = (int) $currentUser['id'];
                $femaleId = (int) $partner['id'];
            } else {
                $maleId   = (int) $partner['id'];
                $femaleId = (int) $currentUser['id'];
            }
        }

        if ($maleId) {
            $maleChars = $stats[$maleId] ?? 0;
        }
        if ($femaleId) {
            $femaleChars = $stats[$femaleId] ?? 0;
        }

        // 共创判定：双方各自贡献至少 10 字
        $threshold = 10;
        if ($maleChars >= $threshold && $femaleChars >= $threshold) {
            $articleCoCreated = true;
        }
    }
} catch (Exception $e) {
    $maleChars   = 0;
    $femaleChars = 0;
    $articleCoCreated = false;
}

// PRG 成功提示
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = '文章更新成功';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $title           = trim($_POST['title'] ?? '');
    $content         = trim($_POST['content'] ?? '');
    $type            = $_POST['type'] ?? 'article';
    $isEncrypted     = isset($_POST['is_encrypted']) ? 1 : 0;
    $tags            = trim($_POST['tags'] ?? '');
    $disableComments = isset($_POST['disable_comments']) ? 1 : 0;
    $postedEditMode  = $_POST['edit_mode'] ?? '';
    $postedEditMode  = in_array($postedEditMode, ['full','blocks','chat'], true) ? $postedEditMode : 'full';
    // 对话框列表模式下，使用块级内容重建全文；整篇/聊天模式则仅以上方已有内容或自动同步结果为准
    $useBlockEditor  = ($postedEditMode === 'blocks');
    $allowPartnerEditNew = $allowPartnerEdit;
    if ($isOwner) {
        $allowPartnerEditNew = isset($_POST['allow_partner_edit']) ? 1 : 0;
    }

    // 对话框模式下，使用块内容重建全文 HTML
    $blocksInput    = $useBlockEditor ? ($_POST['blocks'] ?? null) : null;
    $blocksForSave  = [];
    if (is_array($blocksInput)) {
        $coupleUsers   = get_couple_users();
        $maleUserRow   = $coupleUsers['user1'] ?? null;
        $femaleUserRow = $coupleUsers['user2'] ?? null;
        $maleId        = $maleUserRow ? (int) $maleUserRow['id'] : null;
        $femaleId      = $femaleUserRow ? (int) $femaleUserRow['id'] : null;

        foreach ($blocksInput as $idx => $blockRow) {
            $blockHtml = (string)($blockRow['html'] ?? '');
            // 先清理 wangEditor 外层容器（w-e-text-container 等），只保留正文 HTML
            $blockHtml = clean_wangeditor_html($blockHtml);
            // 再去掉多余的空段落
            $blockHtml = normalize_block_html_for_save($blockHtml);
            $blockHtml = trim($blockHtml);
            if ($blockHtml === '') {
                continue;
            }

            // 根据选择的身份（男主 / 女主 / 系统）推断用户与 speaker
            $identity     = isset($blockRow['user_id']) ? (string) $blockRow['user_id'] : '';
            $blockUserId  = 0;
            $blockSpeaker = null;

            if ($identity === 'male') {
                $blockSpeaker = 'male';
                $blockUserId  = $maleId ?? (int) ($article['user_id'] ?? $currentUser['id']);
            } elseif ($identity === 'female') {
                $blockSpeaker = 'female';
                $blockUserId  = $femaleId ?? (int) ($article['user_id'] ?? $currentUser['id']);
            } elseif ($identity === 'system') {
                $blockSpeaker = 'system';
                $blockUserId  = (int) ($article['user_id'] ?? $currentUser['id']);
            } else {
                // 兼容老表单：直接提交具体 user_id
                $blockUserId = isset($blockRow['user_id']) ? (int) $blockRow['user_id'] : 0;
            }

            $blocksForSave[] = [
                'user_id' => $blockUserId,
                'speaker' => $blockSpeaker,
                'html'    => $blockHtml,
            ];
        }

        if (!empty($blocksForSave)) {
            // 按块顺序拼接全文 HTML
            $rebuilt = '';
            foreach ($blocksForSave as $b) {
                $rebuilt .= $b['html'];
            }
            $content = trim($rebuilt);
        }
    }

    // 兜底：清理 wangEditor 可能带上的内部容器（w-e-text-container 等），只保留正文 HTML
    if ($content !== '') {
        $content = clean_wangeditor_html($content);
    }

    $requireContent = ($postedEditMode !== 'chat');
    if ($title === '' || ($requireContent && $content === '')) {
        $error = '请填写标题和内容';
    } else {
        $oldContent = (string) ($article['content'] ?? '');
        $data = [
            'title'            => $title,
            'type'             => $type,
            'is_encrypted'     => $isEncrypted,
            'comments_enabled' => $disableComments ? 0 : 1,
            'tags'             => $tags,
            'edit_mode'        => $postedEditMode,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        // 非聊天模式下才直接更新 content，聊天模式使用自动同步接口维护全文内容
        if ($postedEditMode !== 'chat') {
            $data['content'] = $content;
        }

        // 非聊天模式下：在更新文章前，清理本次编辑中被移除且不再被引用的上传文件（图片 / 视频）
        if ($postedEditMode !== 'chat' && function_exists('extract_upload_paths_from_html')) {
            try {
                $oldPaths = extract_upload_paths_from_html($oldContent);
                $newPaths = extract_upload_paths_from_html($content);
                if (!empty($oldPaths)) {
                    $oldPaths = array_unique($oldPaths);
                }
                if (!empty($newPaths)) {
                    $newPaths = array_unique($newPaths);
                }
                $removedPaths = array_diff($oldPaths, $newPaths);
                if (!empty($removedPaths)) {
                    foreach ($removedPaths as $relPath) {
                        // 先按全局逻辑尝试删除不再被任何文章引用的上传文件
                        delete_upload_file_if_unused($relPath, $id);

                        // 若是当前文章下的视频文件，则额外清理 article_videos 记录及其封面图
                        try {
                            if (strpos($relPath, 'articles/') === 0 && strpos($relPath, '/videos/') !== false) {
                                $videoRows = $db->fetchAll(
                                    "SELECT id, poster_path, original_video_path 
                                     FROM article_videos 
                                     WHERE article_id = :article_id
                                       AND (video_path = :path OR original_video_path = :path)",
                                    [
                                        'article_id' => $id,
                                        'path'       => $relPath,
                                    ]
                                );

                                if ($videoRows) {
                                    foreach ($videoRows as $vRow) {
                                        $poster = trim((string) ($vRow['poster_path'] ?? ''));
                                        $orig   = trim((string) ($vRow['original_video_path'] ?? ''));

                                        if ($poster !== '') {
                                            deleteFile($poster);
                                        }
                                        // 原始文件通常在转码成功时已删除，这里再次尝试删除仅作兜底
                                        if ($orig !== '' && $orig !== $relPath) {
                                            deleteFile($orig);
                                        }

                                        $db->delete('article_videos', 'id = :id', ['id' => (int) $vRow['id']]);
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // 忽略文章视频封面清理失败，不影响主流程
                        }
                    }
                }
            } catch (Exception $e) {
                // 忽略清理失败，不影响文章保存
            }
        }

        $db->update('articles', $data, 'id = :id', ['id' => $id]);

        // 同步更新文章权限（仅创建者可修改，若权限表不存在则静默忽略）
        if ($isOwner) {
            try {
                $db->query("
                    INSERT INTO article_permissions (article_id, allow_partner_edit, updated_at)
                    VALUES (:article_id, :allow_partner_edit, :updated_at)
                    ON DUPLICATE KEY UPDATE
                        allow_partner_edit = VALUES(allow_partner_edit),
                        updated_at = VALUES(updated_at)
                ", [
                    'article_id'        => $id,
                    'allow_partner_edit'=> $allowPartnerEditNew,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                // 忽略权限表写入失败
            }
        }

        // 非聊天模式才重建统计和块表；聊天模式下由聊天接口维护 article_blocks 和全文
        if ($postedEditMode !== 'chat') {
            // 基于 HTML 中的 data-author 标记，重建逐字级的段落归属与贡献统计
            try {
                $db->delete('article_segments', 'article_id = :article_id', ['article_id' => $id]);
                $db->delete('article_contributions', 'article_id = :article_id', ['article_id' => $id]);

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
                        // 基于 data-author 的逐字级统计（原有逻辑）
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
                                'article_id'   => $id,
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
                                'article_id'        => $id,
                                'user_id'           => $uid,
                                'contributed_chars' => $chars,
                                'last_updated_at'   => $nowSeg,
                            ]);
                        }
                    } elseif (!empty($blocksForSave)) {
                        // 若正文中没有任何 data-author 片段，则回退为基于块级作者的粗略统计，
                        // 用于启用块级编辑时的“共创”判断
                        $blockStats = [];
                        foreach ($blocksForSave as $b) {
                            $uid      = (int) ($b['user_id'] ?? 0);
                            $speakerB = $b['speaker'] ?? null;
                            // 系统身份或无有效用户的块不计入双方贡献
                            if ($speakerB === 'system' || $uid <= 0) {
                                continue;
                            }
                            $html  = (string) ($b['html'] ?? '');
                            if ($html === '') {
                                continue;
                            }
                            // 粗略计算该块的字符数：去掉 HTML 标签和实体
                            $plain = strip_tags($html);
                            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
                            $plain = trim($plain);
                            if ($plain === '') {
                                continue;
                            }
                            $len = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
                            if ($len <= 0) {
                                continue;
                            }
                            if (!isset($blockStats[$uid])) {
                                $blockStats[$uid] = 0;
                            }
                            $blockStats[$uid] += $len;
                        }

                        foreach ($blockStats as $uid => $chars) {
                            $db->query("
                                INSERT INTO article_contributions (article_id, user_id, contributed_chars, last_updated_at)
                                VALUES (:article_id, :user_id, :contributed_chars, :last_updated_at)
                                ON DUPLICATE KEY UPDATE
                                    contributed_chars = VALUES(contributed_chars),
                                    last_updated_at = VALUES(last_updated_at)
                            ", [
                                'article_id'        => $id,
                                'user_id'           => $uid,
                                'contributed_chars' => $chars,
                                'last_updated_at'   => $nowSeg,
                            ]);
                        }
                    }
                }

                // 记录编辑日志（保留）
                $db->query("
                    INSERT INTO article_edit_logs (article_id, user_id, last_edited_at)
                    VALUES (:article_id, :user_id, :last_edited_at)
                    ON DUPLICATE KEY UPDATE
                        last_edited_at = VALUES(last_edited_at)
                ", [
                    'article_id'     => $id,
                    'user_id'        => $currentUser['id'],
                    'last_edited_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {
                // 忽略统计失败，不影响文章保存
            }

            // 更新文章块表：当前阶段以块编辑器提交的块为准；若未使用块编辑器，则整篇作为单块归属文章创建者
            try {
                $nowBlock    = date('Y-m-d H:i:s');
                $coupleUsers = get_couple_users();
                $db->delete('article_blocks', 'article_id = :article_id', ['article_id' => $id]);
                if (!empty($blocksForSave)) {
                    $blockIndex = 0;
                    foreach ($blocksForSave as $b) {
                        $htmlBlock = (string) ($b['html'] ?? '');
                        if ($htmlBlock === '') {
                            continue;
                        }
                        $blockUserId = (int) ($b['user_id'] ?? 0);
                        $speaker     = $b['speaker'] ?? null;
                        if ($speaker === 'system') {
                            if ($blockUserId <= 0) {
                                $blockUserId = (int) ($article['user_id'] ?? $currentUser['id']);
                            }
                        } else {
                            if ($blockUserId <= 0) {
                                $blockUserId = (int) ($article['user_id'] ?? $currentUser['id']);
                            }
                            // 若未显式指定 speaker，则根据 user_id 推断男主 / 女主
                            if ($speaker === null) {
                                if (!empty($coupleUsers['user1']) && (int)$blockUserId === (int)$coupleUsers['user1']['id']) {
                                    $speaker = 'male';
                                } elseif (!empty($coupleUsers['user2']) && (int)$blockUserId === (int)$coupleUsers['user2']['id']) {
                                    $speaker = 'female';
                                }
                            }
                        }

                        $db->query("
                            INSERT INTO article_blocks (article_id, block_index, user_id, speaker, html, created_at, updated_at)
                            VALUES (:article_id, :block_index, :user_id, :speaker, :html, :created_at, :updated_at)
                        ", [
                            'article_id'  => $id,
                            'block_index' => $blockIndex,
                            'user_id'     => $blockUserId,
                            'speaker'     => $speaker,
                            'html'        => $htmlBlock,
                            'created_at'  => $nowBlock,
                            'updated_at'  => $nowBlock,
                        ]);
                        $blockIndex++;
                    }
                    if ($blockIndex === 0 && $content !== '') {
                        $db->query("
                            INSERT INTO article_blocks (article_id, block_index, user_id, speaker, html, created_at, updated_at)
                            VALUES (:article_id, :block_index, :user_id, :speaker, :html, :created_at, :updated_at)
                        ", [
                            'article_id'  => $id,
                            'block_index' => 0,
                            'user_id'     => (int) ($article['user_id'] ?? $currentUser['id']),
                            'speaker'     => null,
                            'html'        => (string) $content,
                            'created_at'  => $nowBlock,
                            'updated_at'  => $nowBlock,
                        ]);
                    }
                } else {
                    $db->query("
                        INSERT INTO article_blocks (article_id, block_index, user_id, speaker, html, created_at, updated_at)
                        VALUES (:article_id, :block_index, :user_id, :speaker, :html, :created_at, :updated_at)
                    ", [
                        'article_id'  => $id,
                        'block_index' => 0,
                        'user_id'     => (int) ($article['user_id'] ?? $currentUser['id']),
                        'speaker'     => null,
                        'html'        => (string) $content,
                        'created_at'  => $nowBlock,
                        'updated_at'  => $nowBlock,
                    ]);
                }
            } catch (Exception $e) {
                // 忽略块级记录失败，不影响文章保存
            }
        }

        header('Location: article_edit.php?id=' . $id . '&success=1');
        exit;
    }

    // 有错误时更新 $article 用于回显
    $article['title']            = $title;
    $article['content']          = $content;
    $article['type']             = $type;
    $article['is_encrypted']     = $isEncrypted;
    $article['comments_enabled'] = $disableComments ? 0 : 1;
    $article['tags']             = $tags;
    $article['edit_mode']        = $postedEditMode;
}

$adminPage = 'articles';

include __DIR__ . '/header.php';
?>

    <script>
        // 块编辑器：增加 / 删除块 + 初始化 wangEditor（仅在编辑页面中使用）
    window.setupCoBlockEditors = function () {
        var form   = document.querySelector('form.admin-card');
        var addBtn = document.getElementById('co-block-add');
        if (!form) return;

        function initBlockEditor(item) {
            if (!item || item._weEditor) return;

            var textarea = item.querySelector('textarea[name^="blocks["]');
            var toolbar  = item.querySelector('.co-block-toolbar');
            var editorEl = item.querySelector('.co-block-editor');
            if (!textarea || !toolbar || !editorEl) return;

            // 与正文保持一致：使用 toolbar + editor 容器模式
            var E = window.wangEditor;
            if (!E) return;
            var editor = new E(toolbar, editorEl);
            editor.config.zIndex = 5;
            // 对话框编辑器：仅保留插入图片 / 视频的工具按钮
            editor.config.menus = ['image', 'video'];
            // 禁用「网络图片」入口，避免因外链受限导致失败
            editor.config.showLinkImg = false;
            // 块级编辑器上传配置（与主编辑器保持一致）
            if (typeof WANG_CSRF_TOKEN !== 'undefined') {
                editor.config.uploadImgServer = '/api/upload_image.php';
                editor.config.uploadImgParams = {
                    _token: WANG_CSRF_TOKEN,
                    article_id: '<?php echo (int)$id; ?>'
                };
                editor.config.uploadVideoServer = '/api/upload_video.php';
                editor.config.uploadVideoParams = {
                    _token: WANG_CSRF_TOKEN,
                    article_id: '<?php echo (int)$id; ?>'
                };
                editor.config.uploadVideoHooks = {
                    customInsert: function (insertVideoFn, result) {
                        try {
                            if (!result) return;
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
                                insertVideoFn(url);
                            }
                        } catch (e) {}
                    },
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
            }
            editor.config.onchange = function (html) {
                textarea.value = html;
            };
            editor.create();
            editor.txt.html(textarea.value || editorEl.innerHTML || '');

            // 兼容性处理：仅在点击空白区域时，将光标移动到内容末尾；
            // 如果点击的是已有文字或元素，则交给浏览器默认行为
            editorEl.addEventListener('click', function (e) {
                try {
                    if (e.target && e.target.closest('.w-e-text')) {
                        return;
                    }
                    var textElem = editorEl.querySelector('.w-e-text');
                    if (!textElem) return;
                    var range = document.createRange();
                    range.selectNodeContents(textElem);
                    range.collapse(false);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                } catch (e) {}
            });

            item._weEditor = editor;
        }

        // 初始化已有块的编辑器
        Array.prototype.forEach.call(
            form.querySelectorAll('.co-block-editor-item'),
            function (item) { initBlockEditor(item); }
        );

        // 工具：获取当前所有对话框项
        function getBlockItems() {
            return Array.prototype.slice.call(form.querySelectorAll('.co-block-editor-item'));
        }

        // 根据当前顺序重排索引与字段 name
        function renumberBlocks() {
            var items = getBlockItems();
            items.forEach(function (item, index) {
                item.setAttribute('data-block-index', String(index));
                var labelSpan = item.querySelector('.co-block-drag-handle + span');
                if (labelSpan) {
                    labelSpan.textContent = '对话框 #' + index;
                }
                var select = item.querySelector('select[name^="blocks["]');
                var textarea = item.querySelector('textarea[name^="blocks["]');
                if (select) {
                    select.name = 'blocks[' + index + '][user_id]';
                }
                if (textarea) {
                    textarea.name = 'blocks[' + index + '][html]';
                }
            });
        }

        // 自定义拖拽：让对话框跟随鼠标移动
        var draggingItem = null;
        var placeholder = null;
        var dragStartY = 0;
        var dragOffsetY = 0;
        var listRect = null;

        function onMouseMove(e) {
            if (!draggingItem) return;
            e.preventDefault();
            dragOffsetY = e.clientY - dragStartY;
            draggingItem.style.transform = 'translateY(' + dragOffsetY + 'px)';

            var items = getBlockItems().filter(function (el) { return el !== draggingItem; });
            var currentTop = draggingItem.getBoundingClientRect().top + draggingItem.offsetHeight / 2;
            var target = null;
            for (var i = 0; i < items.length; i++) {
                var rect = items[i].getBoundingClientRect();
                var mid = rect.top + rect.height / 2;
                if (currentTop < mid) {
                    target = items[i];
                    break;
                }
            }
            var parent = draggingItem.parentNode;
            if (!parent || !placeholder) return;
            var helpNode = parent.querySelector('.co-block-help');
            if (target && placeholder.parentNode === parent) {
                parent.insertBefore(placeholder, target);
            } else if (!target && placeholder.parentNode === parent) {
                // 当拖到列表底部时，将占位块插入到说明文字之前，避免跑到说明和按钮下面
                if (helpNode && helpNode.parentNode === parent) {
                    parent.insertBefore(placeholder, helpNode);
                } else if (addBtn && addBtn.parentNode === parent) {
                    parent.insertBefore(placeholder, addBtn);
                } else {
                    parent.appendChild(placeholder);
                }
            }
        }

        function onMouseUp() {
            if (!draggingItem) return;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            draggingItem.style.position = '';
            draggingItem.style.left = '';
            draggingItem.style.top = '';
            draggingItem.style.width = '';
            draggingItem.style.zIndex = '';
            draggingItem.style.transform = '';
            draggingItem.classList.remove('co-block-dragging');

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(draggingItem, placeholder);
                placeholder.parentNode.removeChild(placeholder);
            }
            placeholder = null;
            draggingItem = null;

            renumberBlocks();
        }

        form.addEventListener('mousedown', function (e) {
            var handle = e.target.closest('.co-block-drag-handle');
            if (!handle) return;
            var item = handle.closest('.co-block-editor-item');
            if (!item) return;
            e.preventDefault();

            draggingItem = item;
            dragStartY = e.clientY;
            dragOffsetY = 0;

            var rect = item.getBoundingClientRect();
            // 确保父容器作为定位参考系
            var parent = item.parentNode;
            var parentStyle = window.getComputedStyle(parent);
            if (parentStyle.position === 'static') {
                parent.style.position = 'relative';
            }
            listRect = parent.getBoundingClientRect();

            placeholder = document.createElement('div');
            placeholder.className = 'co-block-placeholder';
            placeholder.style.height = rect.height + 'px';
            placeholder.style.marginBottom = getComputedStyle(item).marginBottom;
            item.parentNode.insertBefore(placeholder, item.nextSibling);

            item.classList.add('co-block-dragging');
            item.style.position = 'absolute';
            item.style.left = (rect.left - listRect.left) + 'px';
            item.style.top = (rect.top - listRect.top) + 'px';
            item.style.width = rect.width + 'px';
            item.style.zIndex = '20';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        function getCurrentMaxIndex() {
            var items = form.querySelectorAll('.co-block-editor-item');
            var max = -1;
            items.forEach(function (el) {
                var idx = parseInt(el.getAttribute('data-block-index') || '-1', 10);
                if (!isNaN(idx) && idx > max) max = idx;
            });
            return max;
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var items = form.querySelectorAll('.co-block-editor-item');
                if (!items.length) return;
                var last = items[items.length - 1];

                var newIndex = getCurrentMaxIndex() + 1;

                // 新建块容器，而不是克隆已有编辑器内部结构，避免 wangEditor 事件和 DOM 冲突
                var item = document.createElement('div');
                item.className = 'co-block-editor-item';
                item.setAttribute('data-block-index', String(newIndex));
                item.style.border = '1px solid rgba(148,163,184,0.45)';
                item.style.borderRadius = '0.75rem';
                item.style.padding = '0.45rem 0.6rem';
                item.style.marginBottom = '0.5rem';

                // 头部（拖动手柄 + 标题 + 身份选择 + 删除按钮）克隆自最后一个块的头部
                var lastHeader = last.querySelector('div');
                var header = lastHeader ? lastHeader.cloneNode(true) : document.createElement('div');
                header.style.marginBottom = '0.35rem';

                // 更新标题
                var titleSpan = header.querySelector('.co-block-drag-handle + span');
                if (titleSpan) {
                    titleSpan.textContent = '对话框 #' + newIndex;
                }

                // 更新作者选择 name
                var select = header.querySelector('select[name^="blocks["]');
                if (select) {
                    select.name = 'blocks[' + newIndex + '][user_id]';
                }

                item.appendChild(header);

                // 隐藏 textarea（由块级 wangEditor 同步 HTML）
                var lastTextarea = last.querySelector('textarea[name^="blocks["]');
                var textarea = lastTextarea ? lastTextarea.cloneNode(false) : document.createElement('textarea');
                textarea.name = 'blocks[' + newIndex + '][html]';
                textarea.className = 'co-block-textarea';
                textarea.style.display = 'none';
                textarea.value = '';
                item.appendChild(textarea);

                // 编辑器容器（由 wangEditor 接管）
                var wrapper = document.createElement('div');
                wrapper.className = 'co-block-editor-wrapper';
                wrapper.style.borderRadius = '0.65rem';
                wrapper.style.border = '1px solid rgba(148,163,184,0.7)';
                wrapper.style.background = '#ffffff';
                wrapper.style.overflow = 'visible';
                wrapper.style.marginTop = '0.35rem';

                var toolbar = document.createElement('div');
                toolbar.className = 'co-block-toolbar';
                var editorEl = document.createElement('div');
                editorEl.className = 'co-block-editor';
                editorEl.style.minHeight = '120px';

                wrapper.appendChild(toolbar);
                wrapper.appendChild(editorEl);
                item.appendChild(wrapper);

                last.parentNode.insertBefore(item, addBtn);

                // 身份选择：默认根据当前登录者角色自动选择男主 / 女主
                var select = item.querySelector('select[name^="blocks["]');
                if (select && window.CO_CURRENT_AUTHOR_ROLE) {
                    if (window.CO_CURRENT_AUTHOR_ROLE === 'male' || window.CO_CURRENT_AUTHOR_ROLE === 'female') {
                        select.value = window.CO_CURRENT_AUTHOR_ROLE;
                    }
                }

                // 初始化新块的 wangEditor
                initBlockEditor(item);
            });
        }

        // 删除块（事件委托）
        form.addEventListener('click', function (e) {
            var target = e.target;
            if (!(target instanceof HTMLElement)) return;
            if (!target.classList.contains('co-block-remove')) return;

            var item = target.closest('.co-block-editor-item');
            if (!item) return;

            var items = form.querySelectorAll('.co-block-editor-item');
            if (items.length <= 1) {
                // 保留至少一个块，避免完全为空
                var textarea = item.querySelector('textarea[name^="blocks["]');
                if (textarea) {
                    textarea.value = '';
                }
                return;
            }

            // 销毁 wangEditor 实例
            if (item._weEditor && typeof item._weEditor.destroy === 'function') {
                item._weEditor.destroy();
            }

            item.remove();
        });
    };
    </script>

    <section class="admin-page-title">
        <h1>编辑文章</h1>
        <p>修改已经发布的内容</p>
    </section>

    <?php
    $showContribCard = false;
    if (!empty($allowPartnerEdit) && ($maleChars > 0 || $femaleChars > 0)) {
        // 开启共创编辑：只要有人有贡献，就显示提示
        $showContribCard = true;
    } elseif (empty($allowPartnerEdit) && $articleCoCreated) {
        // 关闭共创编辑但已经形成共创内容：给一个特别提醒
        $showContribCard = true;
    }
    ?>

    <?php if ($showContribCard): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;font-size:0.85rem;color:var(--text-light);">
                <div>
                    <div style="margin-bottom:0.25rem;">当前这篇文章的共创贡献统计：</div>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                        <?php if ($maleChars > 0): ?>
                            <span class="badge-role badge-male" style="font-size:0.7rem;padding:0.12rem 0.6rem;">
                                男主 <?php echo $maleChars; ?> 字
                            </span>
                        <?php endif; ?>
                        <?php if ($femaleChars > 0): ?>
                            <span class="badge-role badge-female" style="font-size:0.7rem;padding:0.12rem 0.6rem;">
                                女主 <?php echo $femaleChars; ?> 字
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size:0.78rem;color:#9ca3af;text-align:right;">
                    <?php if (!empty($allowPartnerEdit)): ?>
                        双方各自达到 <strong>10 字</strong> 后，<br>前台会显示共创标签、双头像和彩蛋背景。
                    <?php elseif ($articleCoCreated): ?>
                        当前文章已关闭共创编辑，<br>但男主和女主都曾为这篇文章写下内容，前台仍会展示为共创作品。
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(248,113,113,0.05);border:1px solid rgba(248,113,113,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#b91c1c;font-size:0.9rem;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo e($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="admin-card" style="margin-bottom:0.75rem;background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.35);">
            <div style="display:flex;align-items:center;gap:0.5rem;color:#15803d;font-size:0.9rem;">
                <i class="fas fa-check-circle"></i>
                <span><?php echo e($success); ?></span>
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
                value="<?php echo e($article['title']); ?>"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">类型</label>
            <select
                name="type"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
                <option value="article" <?php echo $article['type'] === 'article' ? 'selected' : ''; ?>>文章</option>
                <option value="diary" <?php echo $article['type'] === 'diary' ? 'selected' : ''; ?>>日记</option>
            </select>
        </div>

        <?php
        $currentEditMode = isset($article['edit_mode']) ? $article['edit_mode'] : 'full';
        ?>
        <div class="form-group" id="editModeToggleRow" style="margin-bottom:0.9rem;padding:0.6rem 0.8rem;border-radius:0.9rem;border:1px solid rgba(148,163,184,0.55);background:linear-gradient(135deg, rgba(248,250,252,0.96), rgba(239,246,255,0.9));">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                <div>
                    <div style="font-size:0.85rem;font-weight:600;color:#0f172a;margin-bottom:0.15rem;">
                        编辑模式
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-light);max-width:19rem;">
                        在这里选择是使用「整篇编辑」还是「对话框模式」。仅当选择对话框模式时，下方的对话框内容才会参与保存与前台展示。
                    </div>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <label style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.7rem;border-radius:999px;border:1px solid rgba(148,163,184,0.8);background:#ffffff;font-size:0.8rem;cursor:pointer;">
                        <input
                            type="radio"
                            name="edit_mode"
                            value="full"
                            style="margin:0;"
                            <?php echo $currentEditMode === 'full' ? 'checked' : ''; ?>>
                        <span>整篇编辑（主编辑器）</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.7rem;border-radius:999px;border:1px solid rgba(129,140,248,0.9);background:rgba(239,246,255,0.95);font-size:0.8rem;cursor:pointer;">
                        <input
                            type="radio"
                            name="edit_mode"
                            value="blocks"
                            style="margin:0;"
                            <?php echo $currentEditMode === 'blocks' ? 'checked' : ''; ?>>
                        <span>对话框模式（气泡对话）</span>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.25rem 0.7rem;border-radius:999px;border:1px solid rgba(16,185,129,0.9);background:rgba(209,250,229,0.95);font-size:0.8rem;cursor:pointer;">
                        <input
                            type="radio"
                            name="edit_mode"
                            value="chat"
                            style="margin:0;"
                            <?php echo $currentEditMode === 'chat' ? 'checked' : ''; ?>>
                        <span>对话创作模式（聊天输入）</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group" id="fullEditorSection" style="margin-bottom:0.75rem;">
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
            $initialContent = $article['content'];
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
            <p class="withu-editor-hint">左侧书写 HTML，右侧实时渲染预览；按钮插入的占位文字选中后直接输入即可覆盖。保存时统一按 HTML 存储，前台展示不受影响。</p>

            <!-- 实际提交用的隐藏 textarea，JS 在提交前同步编辑器的 HTML -->
            <textarea
                name="content"
                id="articleContent"
                style="display:none;"><?php echo e($initialContent); ?></textarea>
        </div>

        <?php
        // 块编辑器：当前简单实现为若干 HTML 块 + 身份选择
        $blocksForEdit = [];
        try {
            $blocksForEdit = $db->fetchAll(
                "SELECT b.id, b.block_index, b.user_id, b.html, b.speaker, u.role, u.nickname 
                 FROM article_blocks b
                 LEFT JOIN users u ON b.user_id = u.id
                 WHERE b.article_id = :article_id
                 ORDER BY b.block_index ASC",
                ['article_id' => $id]
            );
        } catch (Exception $e) {
            $blocksForEdit = [];
        }
        if (empty($blocksForEdit)) {
            // 初始对话框：优先使用当前登录者作为作者身份
            $defaultSpeaker = '';
            if (!empty($currentUser['role'])) {
                if ($currentUser['role'] === 'user1') {
                    $defaultSpeaker = 'male';
                } elseif ($currentUser['role'] === 'user2') {
                    $defaultSpeaker = 'female';
                }
            }
            $blocksForEdit[] = [
                'block_index' => 0,
                'user_id'     => $currentUser['id'],
                'html'        => $article['content'],
                'role'        => $currentUser['role'] ?? '',
                'nickname'    => $currentUser['nickname'] ?? '',
                'speaker'     => $defaultSpeaker,
            ];
        }

        // 允许的身份选项：男主 / 女主 / 系统
        $authorOptions = [];
        $coupleUsers   = get_couple_users();
        $maleUserRow   = $coupleUsers['user1'] ?? null;
        $femaleUserRow = $coupleUsers['user2'] ?? null;
        if ($maleUserRow) {
            $authorOptions[] = [
                'value'   => 'male',
                'text'    => '男主（' . ($maleUserRow['nickname'] ?? '无名氏') . '）',
            ];
        }
        if ($femaleUserRow) {
            $authorOptions[] = [
                'value'   => 'female',
                'text'    => '女主（' . ($femaleUserRow['nickname'] ?? '无名氏') . '）',
            ];
        }
        // 系统身份
        $authorOptions[] = [
            'value' => 'system',
            'text'  => '系统',
        ];
        // 聊天模式下的身份按钮标签：根据当前登录身份自动标注“我”
        $chatMaleLabel   = '男主';
        $chatFemaleLabel = '女主';
        if ($currentAuthorRoleKey === 'male') {
            $chatMaleLabel = '男主（我）';
            if ($femaleUserRow) {
                $chatFemaleLabel = '女主（' . ($femaleUserRow['nickname'] ?? 'Ta') . '）';
            }
        } elseif ($currentAuthorRoleKey === 'female') {
            $chatFemaleLabel = '女主（我）';
            if ($maleUserRow) {
                $chatMaleLabel = '男主（' . ($maleUserRow['nickname'] ?? 'Ta') . '）';
            }
        } else {
            if ($maleUserRow) {
                $chatMaleLabel = '男主（' . ($maleUserRow['nickname'] ?? '用户1') . '）';
            }
            if ($femaleUserRow) {
                $chatFemaleLabel = '女主（' . ($femaleUserRow['nickname'] ?? '用户2') . '）';
            }
        }
        ?>

        <div class="form-group" id="dialogEditorSection" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">对话框内容（块级编辑）</label>
            <p style="margin:0 0 0.4rem;font-size:0.78rem;color:var(--text-light);">
                将文章拆分成多个「对话框」，并为每个对话框指定主要作者。选择对话框模式时，保存会以这些对话框内容重建全文。
            </p>
            <?php foreach ($blocksForEdit as $idx => $blk): ?>
                <div
                    class="co-block-editor-item"
                    data-block-index="<?php echo (int) $idx; ?>"
                    data-block-id="<?php echo isset($blk['id']) ? (int)$blk['id'] : 0; ?>"
                    style="border:1px solid rgba(148,163,184,0.45);border-radius:0.75rem;padding:0.45rem 0.6rem;margin-bottom:0.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;gap:0.5rem;">
                        <div style="display:flex;align-items:center;gap:0.35rem;">
                            <span class="co-block-drag-handle" draggable="true" title="拖动以调整对话顺序" style="cursor:move;font-size:0.9rem;color:#94a3b8;">☰</span>
                            <span style="font-size:0.8rem;color:var(--text-light);">
                                对话框 #<?php echo (int) $idx; ?>
                            </span>
                        </div>
                        <select
                            name="blocks[<?php echo (int) $idx; ?>][user_id]"
                            style="font-size:0.8rem;padding:0.12rem 0.5rem;border-radius:0.5rem;border:1px solid rgba(148,163,184,0.7);">
                            <?php
                            $currentSpeaker = $blk['speaker'] ?? '';
                            // 兼容老数据：若未显式记录 speaker，则通过 role 推断
                            if ($currentSpeaker === '' && !empty($blk['role'])) {
                                if ($blk['role'] === 'user1') {
                                    $currentSpeaker = 'male';
                                } elseif ($blk['role'] === 'user2') {
                                    $currentSpeaker = 'female';
                                }
                            }
                            foreach ($authorOptions as $opt):
                                $selected = ($currentSpeaker === $opt['value']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo e($opt['value']); ?>" <?php echo $selected; ?>>
                                    <?php echo e($opt['text']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button
                            type="button"
                            class="co-block-remove"
                            style="margin-left:0.5rem;font-size:0.75rem;padding:0.1rem 0.4rem;border-radius:999px;border:1px solid rgba(248,113,113,0.6);background:rgba(248,113,113,0.05);color:#b91c1c;">
                            删除块
                        </button>
                    </div>
                    <!-- 隐藏 textarea，由块级 wangEditor 在变更时同步 HTML -->
                    <textarea
                        name="blocks[<?php echo (int) $idx; ?>][html]"
                        class="co-block-textarea"
                        style="display:none;"><?php echo $blk['html']; ?></textarea>

                    <div class="co-block-editor-wrapper" style="border-radius:0.65rem;border:1px solid rgba(148,163,184,0.7);background:#ffffff;overflow:visible;margin-top:0.35rem;">
                        <div class="co-block-toolbar"></div>
                        <div class="co-block-editor" style="min-height:120px;"><?php echo $blk['html']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="co-block-help" style="margin:0;font-size:0.78rem;color:var(--text-light);">
                可以在上方拆分为多个块并指定作者。删除所有块时，系统会退回整篇内容视为一个块。
            </p>
            <button
                type="button"
                id="co-block-add"
                style="margin-top:0.35rem;font-size:0.8rem;padding:0.18rem 0.7rem;border-radius:999px;border:1px solid rgba(59,130,246,0.7);background:rgba(59,130,246,0.06);color:#1d4ed8;">
                新增一个对话框
            </button>
        </div>

        <div class="form-group" id="chatEditorSection" style="margin-bottom:0.75rem;display:none;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">对话创作模式（聊天输入）</label>
            <p style="margin:0 0 0.4rem;font-size:0.78rem;color:var(--text-light);">
                在这里像聊天一样发送每一句话，系统会自动拼成对话块并保存，无需手动点击保存按钮。
            </p>
            <div id="chatMessages" style="border-radius:0.9rem;border:1px solid rgba(148,163,184,0.7);padding:0.65rem 0.75rem;max-height:420px;overflow-y:auto;background:#f9fafb;">
                <?php foreach ($blocksForEdit as $blk): ?>
                    <?php
                    $html    = (string)($blk['html'] ?? '');
                    $speaker = $blk['speaker'] ?? '';
                    $role    = $blk['role'] ?? '';
                    $msgId   = isset($blk['id']) ? (int)$blk['id'] : 0;
                    $msgClass = 'chat-msg-neutral';
                    if ($speaker === 'male' || ($speaker === '' && $role === 'user1')) {
                        $msgClass = 'chat-msg-male';
                    } elseif ($speaker === 'female' || ($speaker === '' && $role === 'user2')) {
                        $msgClass = 'chat-msg-female';
                    } elseif ($speaker === 'system') {
                        $msgClass = 'chat-msg-system';
                    }
                    ?>
                    <div class="chat-msg <?php echo $msgClass; ?>" data-block-id="<?php echo $msgId; ?>">
                        <div class="chat-bubble">
                            <?php echo $html; ?>
                        </div>
                        <?php if ($msgId > 0): ?>
                            <button type="button" class="chat-revoke-btn" data-block-id="<?php echo $msgId; ?>">撤回</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="chatComposer" style="margin-top:0.6rem;padding:0.55rem 0.65rem;border-radius:0.9rem;border:1px solid rgba(148,163,184,0.7);background:#ffffff;">
                <p style="margin:0 0 0.35rem;font-size:0.78rem;color:var(--text-light);">
                    当前登录身份：<?php echo $currentAuthorRoleKey === 'male' ? '男主' : ($currentAuthorRoleKey === 'female' ? '女主' : '未识别'); ?>，默认使用该身份发送，你也可以在下方切换为另一方或系统。
                </p>
                <div style="display:flex;gap:0.4rem;margin-bottom:0.4rem;flex-wrap:wrap;">
                    <button type="button" class="chat-role-btn" data-role="male"><?php echo e($chatMaleLabel); ?></button>
                    <button type="button" class="chat-role-btn" data-role="female"><?php echo e($chatFemaleLabel); ?></button>
                    <button type="button" class="chat-role-btn" data-role="system">系统</button>
                </div>
                <textarea id="chatInput" placeholder="输入要发送的内容（仅文本）" style="width:100%;min-height:72px;padding:0.45rem 0.55rem;border-radius:0.65rem;border:1px solid rgba(148,163,184,0.7);font-size:0.85rem;"></textarea>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.4rem;gap:0.5rem;flex-wrap:wrap;">
                    <div style="display:flex;gap:0.35rem;align-items:center;">
                        <button type="button" id="chatUploadImageBtn" class="btn btn-secondary" style="padding:0.18rem 0.6rem;font-size:0.78rem;">
                            <i class="far fa-image"></i>
                            <span>图片</span>
                        </button>
                        <button type="button" id="chatUploadVideoBtn" class="btn btn-secondary" style="padding:0.18rem 0.6rem;font-size:0.78rem;">
                            <i class="fas fa-video"></i>
                            <span>视频</span>
                        </button>
                        <input type="file" id="chatImageFile" accept="image/*" style="display:none;">
                        <input type="file" id="chatVideoFile" accept="video/*" style="display:none;">
                    </div>
                    <button type="button" id="chatSendBtn" class="btn btn-primary" style="padding:0.25rem 0.9rem;font-size:0.85rem;">
                        <i class="fas fa-paper-plane"></i>
                        <span>发送</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:block;font-size:0.85rem;margin-bottom:0.25rem;">标签（逗号分隔）</label>
            <input
                type="text"
                name="tags"
                value="<?php echo e($article['tags']); ?>"
                placeholder="例如：恋爱、旅行、日常"
                style="width:100%;padding:0.55rem 0.75rem;border-radius:0.75rem;border:1px solid rgba(148,163,184,0.7);font-size:0.9rem;">
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <?php if ($isOwner): ?>
                <label class="switch">
                    <input
                        type="checkbox"
                        name="allow_partner_edit"
                        value="1"
                        <?php echo $allowPartnerEdit ? 'checked' : ''; ?>>
                    <span class="switch-track">
                        <span class="switch-thumb"></span>
                    </span>
                    <span class="switch-label">允许另一半在后台编辑这篇文章</span>
                </label>
                <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-light);">
                    关闭后，另一半将无法在后台编辑或删除这篇文章，但前台阅读不受影响。
                </p>
            <?php else: ?>
                <label class="switch switch-disabled">
                    <input type="checkbox" disabled <?php echo $allowPartnerEdit ? 'checked' : ''; ?>>
                    <span class="switch-track">
                        <span class="switch-thumb"></span>
                    </span>
                    <span class="switch-label">
                        由创建者控制是否允许另一半编辑
                        <?php if (!$allowPartnerEdit): ?>
                            （当前已关闭）
                        <?php endif; ?>
                    </span>
                </label>
            <?php endif; ?>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label class="switch">
                <input type="checkbox" name="is_encrypted" value="1" <?php echo !empty($article['is_encrypted']) ? 'checked' : ''; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">加密内容（仅双方可见）</span>
            </label>
        </div>

        <?php
        $commentsEnabled         = isset($article['comments_enabled']) ? (int) $article['comments_enabled'] : 1;
        $commentsDisabledChecked = $commentsEnabled ? '' : 'checked';
        ?>
        <div class="form-group" style="margin-bottom:1rem;">
            <label class="switch">
                <input type="checkbox" name="disable_comments" value="1" <?php echo $commentsDisabledChecked; ?>>
                <span class="switch-track">
                    <span class="switch-thumb"></span>
                </span>
                <span class="switch-label">关闭评论区</span>
            </label>
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span>保存修改</span>
            </button>
            <a href="/admin/articles.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <span>返回列表</span>
            </a>
        </div>
    </form>

    <!-- wangEditor 脚本（本地） -->
    <script src="/admin-assets/js/wangeditor.min.js"></script>

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
        // ---- 自定义标签正文编辑器（替代原 wangEditor / Markdown 双模式） ----
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

        if (sourceEditor && textarea) {
            // 防止重复初始化
            if (sourceEditor.getAttribute('data-withu-inited') === '1') {
                // 已初始化，直接返回后续块/聊天编辑器流程
            } else {
                sourceEditor.setAttribute('data-withu-inited', '1');

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

                if (tagToolbar) {
                    tagToolbar.addEventListener('click', function (e) {
                        const btn = e.target.closest('[data-tag]');
                        if (!btn) return;
                        e.preventDefault();
                        applyTag(btn.getAttribute('data-tag'));
                    });
                }

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

                function uploadFile(input, kind) {
                    if (!input || !input.files || !input.files.length) return;
                    const file = input.files[0];
                    if (!file) return;
                    const fd = new FormData();
                    fd.append('file', file);
                    fd.append('_token', WANG_CSRF_TOKEN);
                    fd.append('article_id', '<?php echo (int)$id; ?>');
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
            }
        }

        // 初始化块级编辑器（正文编辑器就绪后）
        if (typeof window.setupCoBlockEditors === 'function') {
            window.setupCoBlockEditors();
        }

        // 根据编辑模式显示 / 隐藏对应区域
        const fullSection     = document.getElementById('fullEditorSection');
        const dialogSection   = document.getElementById('dialogEditorSection');
        const chatSection     = document.getElementById('chatEditorSection');
        const editModeRadios  = document.querySelectorAll('input[name="edit_mode"]');
        const chatMessagesEl  = document.getElementById('chatMessages');
        const chatInputEl     = document.getElementById('chatInput');
        const chatSendBtn     = document.getElementById('chatSendBtn');
        const chatRoleBtns    = document.querySelectorAll('.chat-role-btn');
        const chatUploadImageBtn = document.getElementById('chatUploadImageBtn');
        const chatUploadVideoBtn = document.getElementById('chatUploadVideoBtn');
        const chatImageFile   = document.getElementById('chatImageFile');
        const chatVideoFile   = document.getElementById('chatVideoFile');

        function applyEditModeUI(mode) {
            if (!fullSection || !dialogSection || !chatSection) return;
            if (mode === 'blocks') {
                fullSection.style.display = 'none';
                dialogSection.style.display = 'block';
                chatSection.style.display   = 'none';
            } else if (mode === 'chat') {
                fullSection.style.display = 'none';
                dialogSection.style.display = 'none';
                chatSection.style.display   = 'block';
            } else {
                fullSection.style.display   = 'block';
                dialogSection.style.display = 'none';
                chatSection.style.display   = 'none';
            }
        }

        let initialMode = 'full';
        editModeRadios.forEach(function (radio) {
            if (radio.checked) {
                if (radio.value === 'blocks' || radio.value === 'chat') {
                    initialMode = radio.value;
                } else {
                    initialMode = 'full';
                }
            }
            radio.addEventListener('change', function () {
                let mode = 'full';
                if (this.value === 'blocks' || this.value === 'chat') {
                    mode = this.value;
                }
                applyEditModeUI(mode);
            });
        });
        applyEditModeUI(initialMode);

        // 聊天创作模式：身份选择状态
        let currentChatRole = window.CO_CURRENT_AUTHOR_ROLE || 'male';
        function updateChatRoleUI() {
            if (!chatRoleBtns) return;
            chatRoleBtns.forEach(function (btn) {
                const role = btn.getAttribute('data-role');
                if (role === currentChatRole) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        }
        if (chatRoleBtns && chatRoleBtns.length) {
            chatRoleBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const role = this.getAttribute('data-role');
                    if (role === 'male' || role === 'female' || role === 'system') {
                        currentChatRole = role;
                        updateChatRoleUI();
                    }
                });
            });
            updateChatRoleUI();
        }

        // 聊天模式：渲染新消息
        function appendChatMessage(block, isPrepend) {
            if (!chatMessagesEl || !block) return;
            const msgId    = block.id || 0;
            const speaker  = block.speaker || '';
            const html     = block.html || '';

            let cls = 'chat-msg-neutral';
            if (speaker === 'male') cls = 'chat-msg-male';
            else if (speaker === 'female') cls = 'chat-msg-female';
            else if (speaker === 'system') cls = 'chat-msg-system';

            const wrap = document.createElement('div');
            wrap.className = 'chat-msg ' + cls;
            wrap.setAttribute('data-block-id', String(msgId));

            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble';
            bubble.innerHTML = html;
            wrap.appendChild(bubble);

            if (msgId > 0 && speaker !== 'system') {
                const revokeBtn = document.createElement('button');
                revokeBtn.type = 'button';
                revokeBtn.className = 'chat-revoke-btn';
                revokeBtn.setAttribute('data-block-id', String(msgId));
                revokeBtn.textContent = '撤回';
                wrap.appendChild(revokeBtn);
            }

            if (isPrepend) {
                chatMessagesEl.insertBefore(wrap, chatMessagesEl.firstChild);
            } else {
                chatMessagesEl.appendChild(wrap);
            }
            chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }

        // 聊天模式：发送文本消息
        function sendChatMessage(type, payload) {
            if (!payload) return;
            const text = typeof payload === 'string' ? payload : '';
            if (type === 'text' && (!text || !text.trim())) return;

            const fd = new FormData();
            fd.append('_token', WANG_CSRF_TOKEN || '');
            fd.append('article_id', '<?php echo (int)$id; ?>');
            fd.append('speaker', currentChatRole);
            fd.append('type', type);
            fd.append('content', payload);

            fetch('/api/article_chat_send.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (res) {
                return res.text().then(function (text) {
                    return { ok: res.ok, text: text };
                });
            }).then(function (result) {
                var ok = result.ok;
                var text = result.text || '';
                var data = null;
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = null;
                    }
                }

                if (data && data.success && data.block) {
                    if (window.showToast) {
                        window.showToast('已自动保存', 'success');
                    }
                    appendChatMessage(data.block, false);
                    return;
                }

                // 如果 HTTP 正常但解析失败，说明后端很可能已成功写入，这里做乐观处理：
                if (ok && !data) {
                    if (window.showToast) {
                        window.showToast('已自动保存', 'success');
                    }
                    // 前端构造一个临时气泡（不带撤回按钮，刷新后会变成真实块）
                    var html = '';
                    if (type === 'image') {
                        var url = String(payload || '');
                        html = '<p><img src="' + url.replace(/"/g, '&quot;') + '" alt="" /></p>';
                    } else if (type === 'video') {
                        var vurl = String(payload || '');
                        html = '<p><video src="' + vurl.replace(/"/g, '&quot;') + '" controls preload="metadata" style="max-width:100%;height:auto;border-radius:0.9rem;"></video></p>';
                    } else {
                        var safe = String(payload || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        safe = safe.replace(/\r?\n/g, '<br>');
                        html = '<p>' + safe + '</p>';
                    }
                    appendChatMessage({ id: 0, speaker: currentChatRole, html: html }, false);
                    return;
                }

                // 确认失败分支
                var msg = (data && data.message) ? data.message : '发送失败';
                window.showToast(msg, 'error');
            }).catch(function () {
                window.showToast('发送失败，请稍后重试', 'error');
            });
        }

        if (chatSendBtn && chatInputEl) {
            chatSendBtn.addEventListener('click', function () {
                const text = chatInputEl.value || '';
                sendChatMessage('text', text);
                chatInputEl.value = '';
            });
            chatInputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const text = chatInputEl.value || '';
                    sendChatMessage('text', text);
                    chatInputEl.value = '';
                }
            });
        }

        // 聊天模式：撤回
        if (chatMessagesEl) {
            chatMessagesEl.addEventListener('click', function (e) {
                const btn = e.target.closest('.chat-revoke-btn');
                if (!btn) return;
                const blockId = parseInt(btn.getAttribute('data-block-id') || '0', 10);
                if (!blockId) return;

                const fd = new FormData();
                fd.append('_token', WANG_CSRF_TOKEN || '');
                fd.append('article_id', '<?php echo (int)$id; ?>');
                fd.append('block_id', String(blockId));

                fetch('/api/article_chat_revoke.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (!data || !data.success) {
                        var msg = (data && data.message) ? data.message : '撤回失败';
                        window.showToast(msg, 'error');
                        return;
                    }
                    const msgEl = chatMessagesEl.querySelector('.chat-msg[data-block-id="' + blockId + '"]');
                    if (msgEl && msgEl.parentNode) {
                        msgEl.parentNode.removeChild(msgEl);
                    }
                }).catch(function () {
                    window.showToast('撤回失败，请稍后重试', 'error');
                });
            });
        }

        // 聊天模式：上传图片 / 视频，先调用上传接口获取 URL，再当作消息发送
        function uploadMedia(fileInput, type) {
            if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
            const file = fileInput.files[0];
            const fd = new FormData();
            fd.append('_token', WANG_CSRF_TOKEN || '');
            fd.append('article_id', '<?php echo (int)$id; ?>');
            fd.append('file', file);

            const uploadUrl = type === 'image' ? '/api/upload_image.php' : '/api/upload_video.php';

            fetch(uploadUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json();
            }).then(function (data) {
                if (!data || data.errno !== 0 || !data.data || !data.data[0]) {
                    var msg = (data && data.message) ? data.message : '上传失败';
                    window.showToast(msg, 'error');
                    return;
                }
                const url = data.data[0];
                sendChatMessage(type, url);
            }).catch(function () {
                window.showToast('上传失败，请稍后重试', 'error');
            }).finally(function () {
                fileInput.value = '';
            });
        }

        if (chatUploadImageBtn && chatImageFile) {
            chatUploadImageBtn.addEventListener('click', function () {
                chatImageFile.click();
            });
            chatImageFile.addEventListener('change', function () {
                uploadMedia(chatImageFile, 'image');
            });
        }

        if (chatUploadVideoBtn && chatVideoFile) {
            chatUploadVideoBtn.addEventListener('click', function () {
                chatVideoFile.click();
            });
            chatVideoFile.addEventListener('change', function () {
                uploadMedia(chatVideoFile, 'video');
            });
        }

        // 原“可视化 / 代码模式切换 + 富文本作者标记”逻辑已随富文本编辑器一并移除；
        // 正文现在统一由上方的自定义标签源码编辑器负责（含作者标记按钮）。
        const form = document.querySelector('form.admin-card');
        if (form) {
            form.addEventListener('submit', function () {
                // 提交前强制同步所有块级编辑器的内容到各自隐藏 textarea，
                // 避免用户在块中输入后未触发 onchange 导致 html 为空
                const blockItems = form.querySelectorAll('.co-block-editor-item');
                blockItems.forEach(function (item) {
                    const blockTextarea = item.querySelector('textarea[name^="blocks["]');
                    if (!blockTextarea) return;
                    if (item._weEditor && typeof item._weEditor.txt === 'object' && typeof item._weEditor.txt.html === 'function') {
                        blockTextarea.value = item._weEditor.txt.html();
                    }
                });
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

/* ===== 以下为保留的块级 / 对话编辑器样式（原样保留） ===== */

/* 块级编辑器样式 */
.co-block-editor-wrapper .w-e-text-container {
    min-height: 120px;
}

.co-block-editor {
    cursor: text;
}

/* 对话框编辑器拖拽体验优化 */
.co-block-editor-item {
    transition: box-shadow 0.12s ease, transform 0.12s ease;
}

.co-block-drag-handle {
    cursor: move;
}

.co-block-placeholder {
    border-radius: 0.75rem;
    border: 1px dashed rgba(148,163,184,0.7);
    background: rgba(241,245,249,0.7);
}

.co-block-dragging {
    box-shadow: 0 12px 30px rgba(15,23,42,0.18);
}

/* 对话创作模式样式 */
.chat-msg {
    display: flex;
    align-items: flex-end;
    margin-bottom: 0.45rem;
    gap: 0.4rem;
}

.chat-msg-male {
    justify-content: flex-start;
}

.chat-msg-female {
    justify-content: flex-end;
}

.chat-msg-system {
    justify-content: center;
}

.chat-bubble {
    max-width: 80%;
    padding: 0.45rem 0.7rem;
    border-radius: 0.9rem;
    font-size: 0.85rem;
    line-height: 1.5;
    background: #f3f4f6;
    border: 1px solid rgba(148,163,184,0.5);
}

.chat-msg-male .chat-bubble {
    background: rgba(239,246,255,0.98);
    border-color: rgba(129,140,248,0.5);
}

.chat-msg-female .chat-bubble {
    background: rgba(253,242,248,0.98);
    border-color: rgba(244,114,182,0.5);
}

.chat-msg-system .chat-bubble {
    background: #f9fafb;
    border-style: dashed;
}

.chat-bubble img,
.chat-bubble video {
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: 0.9rem;
}

.chat-revoke-btn {
    border: none;
    background: transparent;
    color: #9ca3af;
    font-size: 0.72rem;
    cursor: pointer;
}

.chat-revoke-btn:hover {
    color: #ef4444;
}

.chat-role-btn {
    border-radius: 999px;
    border: 1px solid rgba(148,163,184,0.7);
    background: #ffffff;
    font-size: 0.78rem;
    padding: 0.18rem 0.6rem;
    cursor: pointer;
}

.chat-role-btn.active {
    background: rgba(59,130,246,0.08);
    border-color: rgba(59,130,246,0.8);
    color: #1d4ed8;
}
</style>
