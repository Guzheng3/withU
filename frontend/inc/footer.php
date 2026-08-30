<?php
/**
 * withU 前台公共页脚（各前台页面共用，替代原先每页硬编码的备案徽章）
 * 备案号来源：后台 → 网站设置 → “网站备案号”（settings.icp_beian），留空则不显示备案徽章
 * 支持一次填多个备案号（换行/逗号/分号分隔），例如同时填 ICP 与公安备案
 * 备案机关自动识别：按号码格式自动匹配 公安备案 / 萌ICP / 工信部ICP 的徽章图标与备案查询链接；
 * 工信部备案号还会根据省份简称自动推导省级通信管理局（悬停提示中显示）
 */
$withuBeianRaw = '';
$withuBeianBadges = [];
try {
    if (isset($db) && is_object($db)) {
        foreach ($db->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` = 'icp_beian'") as $withuBeianRow) {
            $withuBeianRaw = (string)($withuBeianRow['value'] ?? '');
        }
    }
} catch (Throwable $withuBeianEx) {
    $withuBeianRaw = '';
}

// 省份简称 → 省级通信管理局（备案机关自动推导）
$withuIcpAuthorities = [
    '京' => '北京市通信管理局', '津' => '天津市通信管理局', '冀' => '河北省通信管理局',
    '晋' => '山西省通信管理局', '蒙' => '内蒙古自治区通信管理局', '辽' => '辽宁省通信管理局',
    '吉' => '吉林省通信管理局', '黑' => '黑龙江省通信管理局', '沪' => '上海市通信管理局',
    '苏' => '江苏省通信管理局', '浙' => '浙江省通信管理局', '皖' => '安徽省通信管理局',
    '闽' => '福建省通信管理局', '赣' => '江西省通信管理局', '鲁' => '山东省通信管理局',
    '豫' => '河南省通信管理局', '鄂' => '湖北省通信管理局', '湘' => '湖南省通信管理局',
    '粤' => '广东省通信管理局', '桂' => '广西壮族自治区通信管理局', '琼' => '海南省通信管理局',
    '渝' => '重庆市通信管理局', '川' => '四川省通信管理局', '黔' => '贵州省通信管理局',
    '云' => '云南省通信管理局', '藏' => '西藏自治区通信管理局', '陕' => '陕西省通信管理局',
    '甘' => '甘肃省通信管理局', '青' => '青海省通信管理局', '宁' => '宁夏回族自治区通信管理局',
    '新' => '新疆维吾尔自治区通信管理局',
];

foreach (preg_split('/[\r\n,，;；]+/u', $withuBeianRaw) as $withuBeianItem) {
    $withuBeianItem = trim((string)$withuBeianItem);
    if ($withuBeianItem === '') {
        continue;
    }
    if (preg_match('/公网安备|公安备/u', $withuBeianItem)) {
        // 公安备案：查询链接需带备案号数字（recordcode）
        $withuRecordCode = '';
        if (preg_match('/(\d{10,})/u', $withuBeianItem, $withuBeianMatch)) {
            $withuRecordCode = $withuBeianMatch[1];
        }
        $withuBeianBadges[] = [
            'icon' => 'policeICP.svg',
            'class' => 'bg-DIY',
            'text' => $withuBeianItem,
            'href' => $withuRecordCode !== ''
                ? 'http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=' . $withuRecordCode
                : 'https://beian.gov.cn/',
            'title' => '公安机关备案查询',
        ];
    } elseif (preg_match('/萌ICP/u', $withuBeianItem)) {
        // 萌ICP（icp.gov.moe）：查询链接需带数字 keyword
        $withuKeyword = '';
        if (preg_match('/(\d{4,})/u', $withuBeianItem, $withuBeianMatch)) {
            $withuKeyword = $withuBeianMatch[1];
        }
        $withuBeianBadges[] = [
            'icon' => 'moeICP.png',
            'class' => 'bg-pink',
            'text' => $withuBeianItem,
            'href' => $withuKeyword !== '' ? 'https://icp.gov.moe/?keyword=' . $withuKeyword : 'https://icp.gov.moe/',
            'title' => '萌ICP 备案查询',
        ];
    } else {
        // 工信部 ICP：备案机关按号码省份简称自动推导
        $withuAuthority = '';
        if (preg_match('/^([\x{4e00}-\x{9fa5}])ICP/u', $withuBeianItem, $withuBeianMatch)) {
            $withuAuthority = $withuIcpAuthorities[$withuBeianMatch[1]] ?? '';
        }
        // 查询链接直达综合查询页，根路径只是系统门户首页
        $withuBeianBadges[] = [
            'icon' => 'ICP.svg',
            'class' => 'bg-blue',
            'text' => $withuBeianItem,
            'href' => 'https://beian.miit.gov.cn/#/Integrated/index',
            'title' => $withuAuthority !== '' ? $withuAuthority . ' 备案查询' : '工信部 ICP 备案查询',
        ];
    }
}
?>
<div class="div_marb_7rem-none">
    <div class="footer-warp">
        <div class="footer">
            <?php foreach ($withuBeianBadges as $withuBadge): ?>
                <p><a class="github-badge" href="<?php echo htmlspecialchars($withuBadge['href'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars($withuBadge['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="badge-subject"><img src="Style/img/icp/<?php echo htmlspecialchars($withuBadge['icon'], ENT_QUOTES, 'UTF-8'); ?>" alt=""></span>
                    <span class="badge-value <?php echo htmlspecialchars($withuBadge['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($withuBadge['text'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </a></p>
            <?php endforeach; ?>
            <p>
                <a href="javascript:void(0);" class="github-badge">
                    <span class="badge-subject">Copyright</span>
                    <span class="badge-value bg-DIY1">
                        ©
                        <?php echo date('Y'); ?> withU Web All Rights Reserved.
                    </span>
                </a>
            </p>
        </div>
    </div>
</div>
