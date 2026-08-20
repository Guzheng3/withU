/*!
 * LGNewUi Pro - Custom Script
 * Copyright (c) Ki / LGNewUi. All rights reserved.
 *
 * 本文件为 LGNewUi 原创作品，仅限获得合法授权的用户在其自有站点使用。
 * 未经书面许可，禁止以任何形式对本作品进行：
 *   - 代码爬取 / 批量抓取 / 自动化采集
 *   - 复制、改写、二次分发、出售、出租、开源发布
 *   - 反编译 / 反混淆 / 逆向工程，或用于训练与生成相似实现
 *   - 结构复刻 / 界面模仿 / 视觉抄袭等实质性相似行为
 *
 * 侵权一经发现，将固定证据并依法追究（民事 / 行政 / 刑事路径）。
 * 授权定制与商务合作：请通过作者渠道联系。
 */

// 控制台版权提示
(function () {
    try {
        console.log(
            '%c LGNewUi Pro %c Copyright (c) Ki · 本作品受著作权法保护，未经授权禁止使用 / 传播 / 反编译',
            'background:#0a0a0a;color:#fafafa;padding:4px 10px;border-radius:4px;font-weight:600;font-size:12px',
            'color:#888;font-size:12px;padding-left:6px'
        );
    } catch (_) {}
})();

// ==================================================================
//  功能矩阵 · LGNewUi Pro 2026 马年限定版
//  字段说明: open_source=开源可用 / paid=Pro可用 / highlight=特色亮点
//  标 highlight 的功能会以高亮样式显示
// ==================================================================
const allFeatures = {
    "features": [
        // --- 后台仪表盘 ---
        { "feature": "后台首页数据卡 · 点滴 / 留言 / 时间轴 / 相册媒体 统计一屏", "open_source": true, "paid": true },
        { "feature": "首页最新留言 · 最新动态 聚合展示板块", "open_source": true, "paid": true },
        { "feature": "GitHub 风格「时光足迹」日历热力图", "open_source": false, "paid": true, "highlight": true },
        { "feature": "欢迎语 + 上次登录信息（时间 / IP / 城市 / 设备）", "open_source": false, "paid": true },
        { "feature": "系统环境一键自检（PHP 扩展 / 禁用函数 / 时区 / 内存）", "open_source": false, "paid": true },
        { "feature": "快捷操作入口 · 可自定义添加 / 排序 / 图标", "open_source": false, "paid": true },
        { "feature": "富文本便签 · 全屏编辑 · 字数统计 · 自动保存", "open_source": false, "paid": true },
        { "feature": "待办中心 · 清单 / 审核 / 备忘 聚合", "open_source": false, "paid": true },

        // --- 账号 · 权限 · 登录 ---
        { "feature": "管理员后台登录 · 单账号模式", "open_source": true, "paid": true },
        { "feature": "双管理员账号体系 · 男主 / 女主 独立登录", "open_source": false, "paid": true, "highlight": true },
        { "feature": "细粒度权限系统 · 25+ 权限项按菜单和操作分级", "open_source": false, "paid": true, "highlight": true },
        { "feature": "超级管理员 / 普通管理员 角色区分", "open_source": false, "paid": true },
        { "feature": "修改管理员账号 / 密码", "open_source": true, "paid": true },
        { "feature": "敏感信息安全码二次认证（数据库配置码）", "open_source": true, "paid": true },
        { "feature": "记住登录 · RememberMe Token 自动续期", "open_source": false, "paid": true },
        { "feature": "密码重置 · 邮箱找回流程", "open_source": false, "paid": true },
        { "feature": "授权码 + 授权域名 · 自助管理变更", "open_source": false, "paid": true },

        // --- 安全与访问控制 ---
        { "feature": "极验 4.0 滑动验证码 · 登录 / 留言双场景", "open_source": false, "paid": true, "highlight": true },
        { "feature": "登录失败自动封禁 IP · 阈值 / 时长可配置", "open_source": false, "paid": true },
        { "feature": "非法访问自动封禁 · 扫敏感文件即封", "open_source": false, "paid": true },
        { "feature": "IP 封禁管理 · 添加 / 解封 / 备注 / 批量", "open_source": true, "paid": true },
        { "feature": "访问日志 · PV/UV 统计 · 归属地 / 设备 / UA", "open_source": false, "paid": true, "highlight": true },
        { "feature": "登录行为记录 · 账号 / 时间 / IP / 城市 / 设备 / 浏览器", "open_source": false, "paid": true },
        { "feature": "非法访问记录列表 · 路径 / IP / 时间 / 归属地", "open_source": true, "paid": true },
        { "feature": "访客指纹辅助识别", "open_source": false, "paid": true },
        { "feature": "IP 归属地识别 · 国内基础 + 全球增强", "open_source": true, "paid": true },
        { "feature": "可信代理 IP 列表（X-Forwarded-For）", "open_source": false, "paid": true },
        { "feature": "前端开发者工具防护 · F12 / 右键 屏蔽", "open_source": false, "paid": true },
        { "feature": "Ed25519 授权签名校验 · 版权同步", "open_source": false, "paid": true, "highlight": true },
        { "feature": "配置加密存储 · Key 加密落库", "open_source": false, "paid": true },
        { "feature": "日志自动清理 · 保留天数 / 采样率 / 批量大小 可配", "open_source": false, "paid": true },

        // --- 点点滴滴 · 日记 ---
        { "feature": "文章新增 / 编辑 / 删除 / 列表管理", "open_source": true, "paid": true },
        { "feature": "富文本 Markdown 编辑器（EditorMD / Pro 增强）", "open_source": true, "paid": true },
        { "feature": "发布管理员选择 · 自动识别当前登录账号", "open_source": true, "paid": true },
        { "feature": "心情标签 + 实时天气 + 归属地 发布绑定", "open_source": false, "paid": true },
        { "feature": "AI 内容摘要自动生成", "open_source": false, "paid": true },
        { "feature": "音乐插入解析 · 网易云 / QQ 音乐（非 VIP）", "open_source": false, "paid": true },
        { "feature": "自定义音乐信息 · 作者 / 封面 / 名称 / URL", "open_source": false, "paid": true },
        { "feature": "内容加密访问 · 密码保护", "open_source": false, "paid": true },
        { "feature": "编辑时间更新开关 · 草稿 / 已发布状态切换", "open_source": false, "paid": true },
        { "feature": "详情页返回按钮 + 归属地显示", "open_source": false, "paid": true },

        // --- 留言祝福 ---
        { "feature": "访客 QQ 留言 · 自动拉取昵称和头像", "open_source": true, "paid": true },
        { "feature": "留言管理 · 查看 / 删除 / 批量 / 审核", "open_source": true, "paid": true },
        { "feature": "匿名留言模式 · 随机头像 URL 可自定义", "open_source": false, "paid": true },
        { "feature": "留言审核机制 · 开关 / 队列 / 审核结果邮件通知", "open_source": false, "paid": true },
        { "feature": "表情留言（6 种自定义表情 · 可开关）", "open_source": false, "paid": true },
        { "feature": "违禁词过滤 · 单游客单日最大留言次数限制", "open_source": false, "paid": true },
        { "feature": "游客设备 / 浏览器 / IP 归属地 · 自动识别显示", "open_source": false, "paid": true },
        { "feature": "点击游客 IP · 一键快速封禁", "open_source": false, "paid": true },
        { "feature": "随机一言 · 内容库可自定义", "open_source": false, "paid": true },
        { "feature": "留言截取展示 · 首页摘要数量可配置", "open_source": false, "paid": true },
        { "feature": "留言功能总开关 · 管理员回复通知邮件", "open_source": false, "paid": true },

        // --- 恋爱相册 ---
        { "feature": "相册新增 / 编辑 / 删除 / 基础上传", "open_source": true, "paid": true },
        { "feature": "主相册 / 子相册 二级结构", "open_source": false, "paid": true },
        { "feature": "一册多图 / 单图 展示模式", "open_source": false, "paid": true },
        { "feature": "子相册批量上传 · 媒体类型自动判断 · 随机排序", "open_source": false, "paid": true },
        { "feature": "视频上传 · FFmpeg 自动封面截取 · URL 自动写入", "open_source": false, "paid": true, "highlight": true },
        { "feature": "拍摄者 + 管理员 / 匿名 上传区分 · 信息显示", "open_source": false, "paid": true },
        { "feature": "媒体描述 · 加密访问 · 密码保护", "open_source": false, "paid": true },
        { "feature": "封面图 / 内容图 · PC + 移动端高度独立配置", "open_source": false, "paid": true },
        { "feature": "自定义懒加载 Loading GIF", "open_source": false, "paid": true },
        { "feature": "复用当前存储策略已上传的媒体素材", "open_source": false, "paid": true },

        // --- 恋爱清单 · 心愿 ---
        { "feature": "恋爱清单新增 / 编辑 / 删除 · 完成状态切换", "open_source": true, "paid": true },
        { "feature": "清单图片展示（URL 地址）", "open_source": true, "paid": true },
        { "feature": "清单关键词搜索 + 已完成 / 未完成 筛选", "open_source": false, "paid": true },
        { "feature": "清单多图展示模式 · 在线上传 · GPS 坐标", "open_source": false, "paid": true },

        // --- 恋爱纪念日 · 智能提醒 ---
        { "feature": "纪念日 / 倒计时 新增 / 编辑 / 删除", "open_source": false, "paid": true },
        { "feature": "公历 / 农历 双历法支持", "open_source": false, "paid": true },
        { "feature": "循环提醒（周年 / 生日 自动每年触发）", "open_source": false, "paid": true },
        { "feature": "排序权重 · 到期自动隐藏 · 前端首页板块展示", "open_source": false, "paid": true },
        { "feature": "邮件自动提醒 · 宝塔 Cron 定时推送", "open_source": false, "paid": true, "highlight": true },
        { "feature": "邮件模板可视化编辑 · 变量点击插入（title / days_left 等）", "open_source": false, "paid": true, "highlight": true },
        { "feature": "提醒对象（男主 / 女主 / 双方）+ 提前 N 天 + 当天触发", "open_source": false, "paid": true },

        // --- 时光管理 · 时间轴 ---
        { "feature": "时间轴 · 9 种事件类型（文字/照片/视频/语音/机票/清单/礼物/里程碑/地点）", "open_source": false, "paid": true, "highlight": true },
        { "feature": "按年 / 月 / 日 归档胶囊 · 发布 / 草稿状态", "open_source": false, "paid": true },
        { "feature": "事件搜索 + 类型筛选", "open_source": false, "paid": true },
        { "feature": "心情标签 + 天气一键获取（高德 + 和风）", "open_source": false, "paid": true },
        { "feature": "礼物事件 · 名称 / 封面 / 价格 / 送出方 · 接收方", "open_source": false, "paid": true },
        { "feature": "机票事件 · 出发地 / 目的地 / 航班号", "open_source": false, "paid": true },
        { "feature": "里程碑事件 · 自定义数值（100 天 / 1 周年）", "open_source": false, "paid": true },
        { "feature": "地点事件 · 高德地图选点 · 经纬度记录", "open_source": false, "paid": true },
        { "feature": "前端 Masonry 瀑布流展示", "open_source": false, "paid": true },

        // --- 关于页面 · 对话体 ---
        { "feature": "对话体气泡叙事 · 章节配置（多段分块）", "open_source": true, "paid": true, "highlight": true },
        { "feature": "双人头像 + 气泡颜色 自定义", "open_source": false, "paid": true },
        { "feature": "章节音频伴读 · 背景图 · 显示按钮开关", "open_source": false, "paid": true },

        // --- 头像框 · 情侣形象 ---
        { "feature": "头像框管理 · 圆环素材上传 / 启用 / 排序", "open_source": false, "paid": true, "highlight": true },
        { "feature": "头像框缩放比例 · X/Y 偏移量", "open_source": false, "paid": true },
        { "feature": "情侣头像 · QQ 自动拉取", "open_source": true, "paid": true },
        { "feature": "情侣头像 · 自定义 URL / 位置交换 / 显示间距", "open_source": false, "paid": true },
        { "feature": "情侣区域中心图标 自定义", "open_source": false, "paid": true },
        { "feature": "单人 / 双人 模式切换", "open_source": false, "paid": true },

        // --- 媒体管理 ---
        { "feature": "媒体库 · 按存储源过滤 · SSE 实时上传进度", "open_source": false, "paid": true },
        { "feature": "HEIC / WEBP 在线预览", "open_source": false, "paid": true },
        { "feature": "SHA256 文件去重 · 同 Hash 自动合并", "open_source": false, "paid": true, "highlight": true },
        { "feature": "EXIF 详情 · 光圈 / 快门 / 焦距 / GPS", "open_source": false, "paid": true, "highlight": true },
        { "feature": "EXIF 检测诊断 · Imagick / exif / exiftool 三级回退", "open_source": false, "paid": true, "highlight": true },
        { "feature": "视频封面一键更换", "open_source": false, "paid": true },
        { "feature": "批量删除 · URL 复制 · 多存储策略共存", "open_source": false, "paid": true },

        // --- 音乐管理 ---
        { "feature": "MP3 音乐上传 + LRC 歌词文件支持", "open_source": false, "paid": true },
        { "feature": "QQ / 网易云 解析接入（非 VIP 曲目）", "open_source": false, "paid": true },
        { "feature": "自定义音乐信息（作者 / 封面 / 名称 / URL）", "open_source": false, "paid": true },
        { "feature": "音乐波形图预览 · 批量排序", "open_source": false, "paid": true },

        // --- 存储策略 · 五云对接 ---
        { "feature": "五云存储对接（AWS S3 / 腾讯 COS / 阿里 OSS / 七牛 Kodo / 又拍 USS）", "open_source": false, "paid": true, "highlight": true },
        { "feature": "本地 / 云存储 模式一键切换 · 多实例管理", "open_source": false, "paid": true },
        { "feature": "归档目录结构（按日期 / 按年月 / 自定义）", "open_source": false, "paid": true },
        { "feature": "SHA256 文件去重 · 最大文件尺寸限制", "open_source": false, "paid": true },
        { "feature": "图片压缩质量 · 输出格式（保留原格式 / 转 WebP）", "open_source": false, "paid": true },
        { "feature": "图片水印 · 文字 / 颜色 / 字体 / 透明度 / 9 宫格定位", "open_source": false, "paid": true },
        { "feature": "视频封面自动截取（FFmpeg · 时间点 / 格式 / 质量可配）", "open_source": false, "paid": true, "highlight": true },

        // --- 邮箱配置 ---
        { "feature": "SMTP 邮箱配置 · 支持男女主双收件", "open_source": false, "paid": true },
        { "feature": "邮件总开关 · 主题前缀 · 编码 / 格式", "open_source": false, "paid": true },
        { "feature": "登录 / 留言 / 回复 / 审核结果 · 通知邮件", "open_source": false, "paid": true },
        { "feature": "SMTP 认证 + 调试 + 超时 + 失败重试 + 日志", "open_source": false, "paid": true },
        { "feature": "邮件测试发送工具", "open_source": false, "paid": true },

        // --- AI 配置中心 ---
        { "feature": "多 Provider 架构（OpenAI / DeepSeek / 通义 / 任意兼容）", "open_source": false, "paid": true, "highlight": true },
        { "feature": "多模型管理 · 新增 / 启用禁用 / 默认选择", "open_source": false, "paid": true },
        { "feature": "内容摘要自动生成 · Prompt 风格预设", "open_source": false, "paid": true },
        { "feature": "API 配置 · 连接测试 · TLS 跳过调试", "open_source": false, "paid": true },

        // --- 导航 · 邀请 ---
        { "feature": "自定义导航卡片标题 / 描述（3 张基础卡片）", "open_source": true, "paid": true },
        { "feature": "导航卡片管理 · 拖拽排序 · 显隐 · 路径自定义", "open_source": false, "paid": true },
        { "feature": "邀请链接 · Token 一次性 · 分钟级有效期", "open_source": false, "paid": true, "highlight": true },
        { "feature": "邀请对象指定（男主 / 女主）+ 开发者寄语", "open_source": false, "paid": true },
        { "feature": "邀请防暴力 · Referer 校验", "open_source": false, "paid": true },

        // --- 小程序（可选模块） ---
        { "feature": "小程序 API 接口层 + 完整源码", "open_source": false, "paid": true },
        { "feature": "小程序后台配置页面", "open_source": false, "paid": true },
        { "feature": "审核模式开关（3 套模板）", "open_source": false, "paid": true },
        { "feature": "自定义字体 URL / 分享标题 / 封面图", "open_source": false, "paid": true },

        // --- 情侣设置 ---
        { "feature": "男主 / 女主 Name / QQ / 恋爱起始时间", "open_source": true, "paid": true },
        { "feature": "情侣区域背景高斯模糊开关", "open_source": true, "paid": true },
        { "feature": "双人距离感知 · 登录活跃 + IP 坐标", "open_source": false, "paid": true, "highlight": true },
        { "feature": "对方在线状态 · 编辑时自动点亮", "open_source": false, "paid": true },
        { "feature": "两地天气同屏 · 高德地图", "open_source": false, "paid": true },
        { "feature": "自定义经纬度 · 固定 IP 精准坐标", "open_source": false, "paid": true },
        { "feature": "爱情卡片自定义（伴侣信息 / 配色）", "open_source": false, "paid": true },

        // --- 全局外观 ---
        { "feature": "站点标题 / 文字 LOGO / 站点文案", "open_source": true, "paid": true },
        { "feature": "Logo 字体 / 样式（居中 / 左对齐） 选择", "open_source": false, "paid": true },
        { "feature": "首页文案库 · 顺序 / 随机 模式", "open_source": false, "paid": true },
        { "feature": "顶部大图 · 自定义背景图", "open_source": true, "paid": true },
        { "feature": "轮播大图 · PC / 移动端高度 · 全屏 · 遮罩开关", "open_source": false, "paid": true },
        { "feature": "全局页面背景样式", "open_source": false, "paid": true },
        { "feature": "元素渐显加载动画 · 前端基础加载", "open_source": true, "paid": true },
        { "feature": "AOS 动画类型选择 · 进阶滚动动画", "open_source": false, "paid": true },
        { "feature": "PJAX 无刷新局部加载", "open_source": true, "paid": true },
        { "feature": "站点 SEO 开关 + 关键词 / 描述配置", "open_source": false, "paid": true },
        { "feature": "ICP 备案号（基础）", "open_source": true, "paid": true },
        { "feature": "萌 ICP / 公安备案号", "open_source": false, "paid": true },
        { "feature": "底部版权信息自定义", "open_source": true, "paid": true },
        { "feature": "自定义全局 CSS + 头部标签 + 底部代码", "open_source": true, "paid": true },

        // --- 全站体验模式 ---
        { "feature": "前端维护模式 · 标题 / 描述 / 背景图 / 逐字动画", "open_source": false, "paid": true, "highlight": true },
        { "feature": "全站密码访问 · 密码 / 背景图", "open_source": false, "paid": true },
        { "feature": "移动端底部 Tab 栏模板（3 套可切换）", "open_source": false, "paid": true, "highlight": true },

        // --- 系统配置（进阶） ---
        { "feature": "静态资源 CDN + 版本号 + 字体加载模式", "open_source": false, "paid": true },
        { "feature": "API 签名机制（Key ID / Secret / 参数名）+ 自定义请求头", "open_source": false, "paid": true },
        { "feature": "旧地址前缀迁移", "open_source": false, "paid": true },
        { "feature": "高德地图 API + 模拟 IP / 经纬度 调试", "open_source": false, "paid": true },
        { "feature": "封禁时长配置（登录失败 / 非法访问 / 临时锁定 独立）", "open_source": false, "paid": true },
        { "feature": "PHP 错误显示 + Response Data 日志 开关", "open_source": false, "paid": true },

        // --- 在线更新中心 ---
        { "feature": "一键检测新版本 · 增量下载 · 自动部署", "open_source": false, "paid": true, "highlight": true },
        { "feature": "实时终端日志输出 + KPI 进度条", "open_source": false, "paid": true },
        { "feature": "更新历史记录 + 跨大版本迁移向导", "open_source": false, "paid": true },
        { "feature": "授权服务响应状态监测", "open_source": false, "paid": true },

        // --- UX 体验 ---
        { "feature": "后台骨架屏 + 面包屑 + 侧边栏折叠 · 移动端自适应", "open_source": false, "paid": true },
        { "feature": "一键隐私模式 · 敏感信息打码", "open_source": false, "paid": true, "highlight": true },
        { "feature": "一键全屏 · 一键清除缓存 · 心跳保活", "open_source": false, "paid": true },
        { "feature": "管理员已登录前端显示后台图标 · 悬浮入口", "open_source": false, "paid": true },
        { "feature": "前端 Fancybox 图库灯箱", "open_source": false, "paid": true },
        { "feature": "移动端手势 · 下拉刷新 + 侧滑返回", "open_source": false, "paid": true },

        // --- 杂项 ---
        { "feature": "Sitemap.xml 自动生成 + robots.txt 可配", "open_source": false, "paid": true },
        { "feature": "自定义 404 页面 + 维护页面 + 错误页面", "open_source": false, "paid": true },
        { "feature": "Cron 定时任务目录（归档 / 清理 / 提醒）", "open_source": false, "paid": true },
        { "feature": "持续版本维护 · Bug 修复 · 新功能迭代", "open_source": false, "paid": true }
    ]
};

// 2. Render Matrix - 简洁列表
const matrixBody = document.getElementById('matrix-body');
const searchInput = document.getElementById('feature-search');
const totalCountEl = document.getElementById('total-count');
const openCountEl = document.getElementById('open-count');
const proOnlyCountEl = document.getElementById('pro-only-count');
const highlightCountEl = document.getElementById('highlight-count');

function renderMatrix(filterText = '') {
    const filtered = allFeatures.features.filter(f =>
        f.feature.toLowerCase().includes(filterText.toLowerCase())
    );

    // 更新统计
    const total = filtered.length;
    const openCount = filtered.filter(f => f.open_source).length;
    const proOnly = filtered.filter(f => !f.open_source && f.paid).length;
    const highlightCount = filtered.filter(f => f.highlight).length;

    if (totalCountEl) totalCountEl.textContent = total;
    if (openCountEl) openCountEl.textContent = openCount;
    if (proOnlyCountEl) proOnlyCountEl.textContent = proOnly;
    if (highlightCountEl) highlightCountEl.textContent = highlightCount;

    if (filtered.length === 0) {
        matrixBody.innerHTML = `
                    <div class="py-12 text-center text-gray-500 text-sm">
                        未找到匹配 "${filterText}" 的功能
                    </div>
                `;
        return;
    }

    matrixBody.innerHTML = filtered.map((f, i) => {
        const isProOnly = !f.open_source && f.paid;
        const isHighlight = !!f.highlight;
        const rowAccent = isHighlight
            ? 'border-l-2 border-l-amber-400'
            : (isProOnly ? 'border-l-2 border-l-yellow-500/40' : '');
        const textColor = isHighlight
            ? 'text-white'
            : (isProOnly ? 'text-gray-200' : 'text-gray-400');
        // 特色 badge · 作为独立 flex 子项用 gap 控制间距，彻底避开 lucide SVG 替换后尺寸错乱
        const highlightBadge = isHighlight
            ? '<i data-lucide="sparkles" class="w-3.5 h-3.5 shrink-0 text-amber-400 mt-[2px]"></i>'
            : '';
        return `
                    <div class="feature-row grid grid-cols-12 gap-2 md:gap-4 px-4 py-3 items-center ${i % 2 === 0 ? 'bg-transparent' : 'bg-white/[0.02]'} ${rowAccent} hover:bg-white/[0.04] transition-colors cursor-pointer md:cursor-default" onclick="this.classList.toggle('expanded')">
                        <div class="col-span-6 md:col-span-8 text-sm ${textColor} flex items-start gap-2 min-w-0">
                            ${highlightBadge}
                            <span class="feature-text flex-1 min-w-0 truncate md:whitespace-normal">${f.feature}</span>
                        </div>
                        <div class="col-span-3 md:col-span-2 flex justify-center">
                            ${f.open_source
                ? '<i data-lucide="check" class="w-4 h-4 text-white"></i>'
                : '<i data-lucide="x" class="w-4 h-4 text-gray-700"></i>'}
                        </div>
                        <div class="col-span-3 md:col-span-2 flex justify-center">
                            ${f.paid
                ? (isProOnly
                    ? '<i data-lucide="crown" class="w-4 h-4 text-yellow-500"></i>'
                    : '<i data-lucide="check" class="w-4 h-4 text-yellow-500"></i>')
                : '<i data-lucide="x" class="w-4 h-4 text-gray-700"></i>'}
                        </div>
                    </div>
                `;
    }).join('');

    lucide.createIcons();
}

searchInput.addEventListener('input', (e) => {
    renderMatrix(e.target.value);
});

// 3. Init
renderMatrix();
const lenis = new Lenis();
function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
}
requestAnimationFrame(raf);
lucide.createIcons();

// GSAP ScrollTrigger 动画
gsap.registerPlugin(ScrollTrigger);

gsap.utils.toArray(".gsap-card").forEach((el) => {
    gsap.fromTo(el,
        { opacity: 0, y: 30 },
        {
            opacity: 1,
            y: 0,
            duration: 0.6,
            ease: "power2.out",
            scrollTrigger: {
                trigger: el,
                start: "top 92%",
                toggleActions: "play none none none"
            }
        }
    );
});

(function () {
    // Smooth Scroll for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            if (!targetId) return;
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                if (typeof lenis !== 'undefined') {
                    lenis.scrollTo(targetElement);
                } else {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });

    // UI Gallery - Swiper powered (silky smooth)
    const galleryContainerEl = document.getElementById('gallery-container');
    const galleryCurrent = document.getElementById('gallery-current');
    const galleryTotal = document.getElementById('gallery-total');
    const galleryProgress = document.getElementById('gallery-progress');
    const galleryPrev = document.getElementById('gallery-prev');
    const galleryNext = document.getElementById('gallery-next');

    if (galleryContainerEl && window.Swiper) {
        const slideCount = galleryContainerEl.querySelectorAll('.swiper-slide').length;
        if (galleryTotal) galleryTotal.textContent = slideCount.toString().padStart(2, '0');

        const gallerySwiper = new Swiper(galleryContainerEl, {
            slidesPerView: 'auto',
            spaceBetween: 24,
            grabCursor: true,
            speed: 600,
            resistanceRatio: 0.6,
            freeMode: {
                enabled: false
            },
            mousewheel: false,
            keyboard: { enabled: true },
            on: {
                slideChange(s) {
                    const idx = s.activeIndex;
                    if (galleryCurrent) galleryCurrent.textContent = (idx + 1).toString().padStart(2, '0');
                    if (galleryProgress) galleryProgress.style.width = `${((idx + 1) / slideCount) * 100}%`;
                    if (galleryPrev) galleryPrev.disabled = idx === 0;
                    if (galleryNext) galleryNext.disabled = idx === slideCount - 1;
                }
            }
        });

        if (galleryPrev) galleryPrev.onclick = () => gallerySwiper.slidePrev();
        if (galleryNext) galleryNext.onclick = () => gallerySwiper.slideNext();
        if (galleryPrev) galleryPrev.disabled = true;
    }

    // ==================================================================
    //  评价数据 · 真实用户反馈 (QQ 已脱敏为 md5 哈希)
    //  数据源: uapis.cn QQ 昵称接口 + Weavatar 头像哈希
    //  未来可改为从 API 拉取: fetch('/api/reviews').then(r=>r.json()).then(render)
    // ==================================================================
    const REVIEWS = [
        { hash: '90b81c976910dbbfb5bcd331ffda9b73', name: '付费版用户', text: '整体感觉很舒服。界面干净清爽，不管是自己记录生活，还是和对象一起分享日常都很合适，发照片、特别适合保存生活里的小回忆，长期用下来体验非常歪瑞古德。' },
        { hash: '97b53f0aa82e7d1fd24c3d6985664e1b', name: '华年', text: '我应该最早一批买的了，虽然一直没有对象，但是情侣小站的审美真的超级在线，Ki 基本上也是有求必应，需要的功能基本上都满足了，比如单人模式，真的超级喜欢这个 UI。' },
        { hash: '4128e541da6e77db2a35aaa025789a55', name: '江奕浩', text: 'LGNewUi，非常好用，超级推荐，用它来记录和女朋友的点点滴滴，准备结婚的时候放出去得瑟，撒狗粮和记录生活的必备品！！！' },
        { hash: '6ea2ddf8fc659a9d92291d590f3276e1', name: '欲穷千里目', text: '我很喜欢这个情侣小站，原本打算做情侣论坛送女友当生日礼物。偶然在 B 站看到免费 5.2.0 版本教程，联系作者后一直用到现在，使用和维护都很惊喜，和作者也有互动，真心好评。' },
        { hash: 'd293850576ac7057f6e72b36b89497a1', name: '同志们好跟党走', text: '大家都来使用 LGNewUi 情侣小站，记录你们甜蜜时刻，分享幸福瞬间，让我们一起见证你们的爱情，我反正已经用上了，功能非常齐全实用。' },
        { hash: 'a8bbae7cd3b9c5b93db791113fed6ef3', name: 'iseii', text: '整体设计简约温柔，交互很舒服。站点功能很贴合情侣需求，方便了记录日常，能感受到作者用心打磨细节，整体体验感很好。' },
        { hash: '3ce8b50afffcef141111d79215e73a21', name: '小峰', text: '当初入手 LGNewUi 情侣小站，本是满心期待双向奔赴，如今依旧是我一人的小世界。但我依然在好好经营这份美好，静静等待我的那个他，踏着星光来到我身边。' },
        { hash: 'e7a3d5d078b71b5bb253f0e67e98751d', name: '白哲', text: '我也算 LGNewUi 古老级用户了，Ki 总真的太勤奋了，逢年过节就更新版本，而且程序真的没话说，很适合情侣之间记录美好时刻，很推荐入手！支持 Ki 总！' },
        { hash: '3ed884b2bb7f19c01868d88a3a872851', name: '明天只吃一粒米', text: '响应这块没得说，很快。服务不错。' },
        { hash: '0e588b178a766dd2f20862a75fbeff96', name: '願', text: '起初因搭建环境特殊有些担忧，但在和你的沟通中，能感受到你是一个责任感很强的人，后期搭建积极反馈、高效排障，最终效果令人惊喜——功能丰富，细节处更充满「爱」，加油，愿我们越来越好。' },
        { hash: '3cfac7374f8e85791727a1b4704f1afa', name: '浮云', text: '真的挺喜欢这个情侣网站的，界面看着舒服，用起来也简单，平时跟对象存点照片、记点小事都很方便，没有乱七八糟的广告，整体感觉很温馨，推荐大家使用！' },
        { hash: '1ec85c414384d06f7297055a34a56e09', name: '清空', text: '网站非常好用，我和对象一直在用，对于我不爱发朋友圈，相册又不存照片的人太友好了，支持作者！' },
        { hash: '7f35ae9d408e61c2232cb2b0b5fb13fa', name: '小狮', text: 'ki 是真的懂浪漫。网站看着简单，相恋计时、天气、日记这些小细节却特别走心。没有花里胡哨的东西，每一处都能感受到用心，用代码把爱意实实在在地呈现出来，很有温度。' },
        { hash: 'ad67c58ebebca98fd50f8e5edfa3ab33', name: '知仁守义', text: '用户体验非常好，可以支持单人双人切换，并且可以通过邀请链接的方式去邀请自己的另一半，而且会有主题的更新，并且页面切换非常丝滑，简直是爱了爱了。' },
    ];

    // 安全转义 · 防 XSS
    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    // 作者标识 · 评价中的 "Ki / ki / KI" 统一转为 "Ki" 并高亮
    // 仅对 escapeHtml 后的安全文本执行, 避免 XSS
    const highlightAuthor = (escapedHtml) =>
        escapedHtml.replace(/\b[Kk][Ii]\b/g,
            '<span class="author-mention" title="作者">Ki</span>'
        );

    // 单张卡模板
    const reviewTemplate = (r) => `
                <div class="marquee-card">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-10 h-10 bg-gray-700 rounded-full overflow-hidden flex-shrink-0">
                            <img loading="lazy" decoding="async" alt=""
                                src="https://weavatar.com/avatar/${r.hash}?s=100&d=mm&r=g"
                                class="w-full h-full object-cover">
                        </div>
                        <div class="min-w-0">
                            <div class="text-white font-bold text-sm truncate">${escapeHtml(r.name)}</div>
                            <div class="text-xs text-gray-500 font-mono">Pro 用户</div>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed">“${highlightAuthor(escapeHtml(r.text))}”</p>
                </div>
            `;

    // Reviews - rAF Marquee (seamless, draggable, no tick)
    (function initReviewsMarquee() {
        const viewport = document.querySelector('[data-reviews-marquee]');
        if (!viewport) return;
        const track = viewport.querySelector('[data-marquee-track]') || viewport.querySelector('.marquee-track');
        if (!track) return;

        // 数据为空 → 保留骨架屏占位, 不启动动画 (未来接 API 可在此显示 Loading)
        if (!REVIEWS.length) return;

        // 渲染真实数据 (同步, 无 CLS)
        track.innerHTML = REVIEWS.map(reviewTemplate).join('');

        // 动态写入评价总数 (副标题 "来自 N 位 Pro 用户的真实反馈")
        document.querySelectorAll('[data-reviews-count]').forEach(el => {
            el.textContent = REVIEWS.length;
        });

        // 克隆一份实现无缝循环 (当第一份滚完时, 位置瞬跳回 0, 用户视觉无感)
        const originalItems = Array.from(track.children);
        originalItems.forEach(item => {
            const clone = item.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            track.appendChild(clone);
        });

        let halfWidth = 0;
        const computeHalfWidth = () => {
            // 第一份所有卡片 + 它们之间的 gap = 一份的总宽度
            halfWidth = track.scrollWidth / 2;
        };
        computeHalfWidth();
        // 图片加载后重新计算 (图片尺寸变化会影响 scrollWidth)
        track.querySelectorAll('img').forEach(img => {
            if (!img.complete) img.addEventListener('load', computeHalfWidth, { once: true });
        });
        window.addEventListener('resize', computeHalfWidth);

        const SPEED = 0.5; // px per frame @ 60fps ≈ 30px/s
        let offset = 0;
        let paused = false;
        let hovering = false;
        let offscreen = false;
        let dragging = false;
        let dragStartX = 0;
        let dragStartOffset = 0;
        let lastDragX = 0;
        let lastDragTime = 0;
        let velocity = 0;

        const normalize = () => {
            // 将 offset 规范化到 [-halfWidth, 0] 区间 (视觉位置不变, 但数值不会无限增长)
            if (halfWidth <= 0) return;
            while (offset <= -halfWidth) offset += halfWidth;
            while (offset > 0) offset -= halfWidth;
        };

        const apply = () => {
            track.style.transform = `translate3d(${offset}px, 0, 0)`;
        };

        const frame = () => {
            if (!paused && !hovering && !offscreen && !dragging && halfWidth > 0) {
                // 拖拽后残留惯性衰减
                if (Math.abs(velocity) > 0.01) {
                    offset += velocity;
                    velocity *= 0.92;
                } else {
                    velocity = 0;
                    offset -= SPEED;
                }
                normalize();
                apply();
            }
            requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);

        // Hover 暂停
        viewport.addEventListener('mouseenter', () => hovering = true);
        viewport.addEventListener('mouseleave', () => hovering = false);

        // 离屏暂停 (性能优化 + 不浪费 GPU)
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(entries => {
                offscreen = !entries[0].isIntersecting;
            }, { threshold: 0.01 }).observe(viewport);
        }

        // 页面隐藏时暂停
        document.addEventListener('visibilitychange', () => {
            paused = document.hidden;
        });

        // Pointer 拖拽 (鼠标 + 触屏统一) + 区分 click vs drag
        const DRAG_THRESHOLD = 6; // 超过 6px 算拖拽
        let downX = 0, downY = 0, movedPx = 0, downCard = null;

        viewport.addEventListener('pointerdown', (e) => {
            dragging = true;
            viewport.classList.add('is-dragging');
            dragStartX = e.clientX;
            dragStartOffset = offset;
            lastDragX = e.clientX;
            lastDragTime = performance.now();
            velocity = 0;
            downX = e.clientX;
            downY = e.clientY;
            movedPx = 0;
            downCard = e.target.closest('.marquee-card');
            try { viewport.setPointerCapture(e.pointerId); } catch (_) { }
        });
        viewport.addEventListener('pointermove', (e) => {
            if (!dragging) return;
            const delta = e.clientX - dragStartX;
            offset = dragStartOffset + delta;
            normalize();
            apply();
            movedPx = Math.max(movedPx, Math.hypot(e.clientX - downX, e.clientY - downY));
            const now = performance.now();
            const dt = now - lastDragTime;
            if (dt > 0) velocity = (e.clientX - lastDragX) * (16 / dt);
            lastDragX = e.clientX;
            lastDragTime = now;
        });
        const endDrag = (e) => {
            if (!dragging) return;
            dragging = false;
            viewport.classList.remove('is-dragging');
            try { viewport.releasePointerCapture(e.pointerId); } catch (_) { }
            // 若移动距离 < 阈值, 当作 click 处理 → 打开评价弹窗
            if (movedPx < DRAG_THRESHOLD && downCard && e.type === 'pointerup') {
                if (typeof window.openReviewModal === 'function') window.openReviewModal(downCard);
            }
            downCard = null;
        };
        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);
        viewport.addEventListener('pointerleave', endDrag);
    })();

    // Pro 社会证明头像 · 每次从 REVIEWS 随机抽 3 位
    (function initProSocialAvatars() {
        const slots = document.querySelectorAll('[data-pro-social-slot]');
        if (!slots.length || typeof REVIEWS === 'undefined' || !REVIEWS.length) return;
        // Fisher-Yates 取前 N 位, 保证不重复
        const pool = REVIEWS.slice();
        for (let i = pool.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pool[i], pool[j]] = [pool[j], pool[i]];
        }
        const picks = pool.slice(0, slots.length);
        slots.forEach((slot, idx) => {
            const r = picks[idx];
            if (!r) return;
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.decoding = 'async';
            img.alt = '';
            img.src = `https://weavatar.com/avatar/${r.hash}?s=60&d=mm&r=g`;
            slot.appendChild(img);
        });
    })();

    // ==================================================================
    //  授权商数据 · 官方认证合作伙伴 (轮播展示)
    //  头像: 直连 QQ 头像 CDN (https://q1.qlogo.cn/g?b=qq&nk={QQ}&s=640)
    //  昵称: 通过 QQ 昵称 API 一次性查询并固化, 避免运行时请求
    //  未来扩展: 直接往 AUTHORIZED_PARTNERS 数组追加新成员即可
    // ==================================================================
    const AUTHORIZED_PARTNERS = [
        { qq: '212121030',  name: '江奕浩',     pinyin: 'JIANG YI HAO',         region: '内蒙古',   no: '001', serial: 'LGN-2026-001' },
        { qq: '308592173',  name: '郭亮',       pinyin: 'GUO LIANG',            region: '武汉',     no: '002', serial: 'LGN-2026-002' },
        { qq: '2579656536', name: '空',         pinyin: 'KONG',                 region: '吉林长春', no: '003', serial: 'LGN-2026-003' },
        { qq: '1780376541', name: '发光中勿扰', pinyin: 'FA GUANG ZHONG WU RAO', region: '安徽',    no: '004', serial: 'LGN-2026-004' },
        { qq: '11811539',   name: '道言',       pinyin: 'DAO YAN',              region: '高州',     no: '005', serial: 'LGN-2026-005' },
    ];

    // 单张授权商卡片模板 · 官方认证证书风格
    const partnerTemplate = (p) => `
        <div class="swiper-slide">
            <div class="partner-card relative rounded-3xl overflow-hidden">
                <!-- 卡面渐变 + 网格底纹 -->
                <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-b from-[#101010] via-[#080808] to-[#020202]"></div>
                <div aria-hidden="true" class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(to_right,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:24px_24px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_30%,transparent_80%)]"></div>
                <!-- 顶部高光线 -->
                <div aria-hidden="true" class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
                <!-- 主体边框 -->
                <div aria-hidden="true" class="absolute inset-0 rounded-3xl border border-white/10 pointer-events-none"></div>

                <div class="relative px-6 pt-6 pb-5 md:px-8 md:pt-7 md:pb-7">
                    <!-- 顶部 · 信笺抬头 (mono 标签 + Inter 中等字重值, 干净不张扬) -->
                    <div class="flex items-end justify-between mb-7 md:mb-8 leading-none">
                        <!-- 左 · 品牌方 -->
                        <div class="flex flex-col gap-2">
                            <span class="text-[9px] font-mono tracking-[0.4em] text-white/25 uppercase">Authorized</span>
                            <span class="text-[15px] md:text-base font-medium text-white/85 tracking-[0.01em]">LGNewUi</span>
                        </div>
                        <!-- 右 · 编号 (镜像结构) -->
                        <div class="flex flex-col gap-2 text-right">
                            <span class="text-[9px] font-mono tracking-[0.4em] text-white/25 uppercase">Edition</span>
                            <span class="text-[15px] md:text-base font-medium text-white/85 tabular-nums tracking-[0.05em]">№ ${escapeHtml(p.no)}</span>
                        </div>
                    </div>

                    <!-- 装饰背景巨大编号 (水印) -->
                    <div aria-hidden="true" class="pointer-events-none select-none absolute top-12 right-4 md:top-14 md:right-6 text-[6rem] md:text-[8.5rem] leading-none font-serif italic font-bold text-white/[0.025] tracking-tighter">
                        ${escapeHtml(p.no)}
                    </div>

                    <!-- 头像 (干净极简版: 仅光晕 + 单层细边) -->
                    <div class="relative mx-auto mb-6 md:mb-7 w-24 h-24 md:w-28 md:h-28">
                        <!-- 柔和光晕 -->
                        <div aria-hidden="true" class="absolute -inset-3 rounded-full bg-white/[0.03] blur-xl"></div>
                        <!-- 头像本体 (border 替代 ring 防默认蓝色 ring 颜色) -->
                        <div class="relative w-full h-full rounded-full overflow-hidden border border-white/10 bg-zinc-900">
                            <img loading="lazy" decoding="async" alt="${escapeHtml(p.name)}"
                                src="https://q1.qlogo.cn/g?b=qq&nk=${escapeHtml(p.qq)}&s=640"
                                onerror="this.onerror=null;this.src='https://q2.qlogo.cn/headimg_dl?dst_uin=${escapeHtml(p.qq)}&spec=640';"
                                class="w-full h-full object-cover">
                        </div>
                    </div>

                    <!-- 昵称 -->
                    <h3 class="text-center text-2xl md:text-3xl font-bold text-white mb-1.5 tracking-tight">${escapeHtml(p.name)}</h3>
                    <!-- 拼音 -->
                    <p class="text-center text-[10px] md:text-[11px] font-mono tracking-[0.35em] text-white/40 mb-4 md:mb-5">${escapeHtml(p.pinyin)}</p>

                    <!-- 地区 (上下极细线包裹, 显得官方) -->
                    <div class="relative flex items-center justify-center mb-3">
                        <div class="absolute inset-x-6 top-1/2 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <div class="relative inline-flex items-center gap-1.5 px-3 py-0.5 bg-[#080808] text-sm text-gray-300">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-white/50"></i>
                            <span>${escapeHtml(p.region)}</span>
                        </div>
                    </div>

                    <!-- QQ 联系方式 (新增, 与地区行同款样式, 点击可加好友) -->
                    <div class="relative flex items-center justify-center mb-7 md:mb-8">
                        <div class="absolute inset-x-6 top-1/2 h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
                        <a href="https://wpa.qq.com/msgrd?v=3&uin=${escapeHtml(p.qq)}&site=qq&menu=yes"
                           target="_blank" rel="noopener noreferrer"
                           title="点击添加 ${escapeHtml(p.name)} 的 QQ"
                           class="relative inline-flex items-center gap-1.5 px-3 py-0.5 bg-[#080808] text-sm text-white/60 hover:text-white transition-colors">
                            <i data-lucide="message-circle" class="w-3.5 h-3.5 text-white/45"></i>
                            <span class="font-mono tracking-wider">${escapeHtml(p.qq)}</span>
                        </a>
                    </div>

                    <!-- 底部 · 元数据 (恢复原本: 序列号 + 状态) -->
                    <div class="flex items-end justify-between text-[10px] font-mono tracking-[0.3em] uppercase leading-none">
                        <!-- 左 · 序列号 -->
                        <div class="flex flex-col gap-1.5">
                            <span class="text-white/25">Serial</span>
                            <span class="text-white/70 normal-case tracking-[0.15em]">${escapeHtml(p.serial)}</span>
                        </div>
                        <!-- 右 · 状态 -->
                        <div class="flex flex-col gap-1.5 text-right">
                            <span class="text-white/25">Status</span>
                            <span class="inline-flex items-center gap-1.5 self-end">
                                <span aria-hidden="true" class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-white/70">Active</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 底部火漆条 (decorative) -->
                <div aria-hidden="true" class="relative h-[3px] bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
            </div>
        </div>
    `;

    // 渲染 + Swiper 初始化 · 手动箭头切换 (无 autoplay)
    (function initAuthorizedPartners() {
        const wrapper = document.getElementById('authorized-list');
        if (!wrapper || !window.Swiper || !AUTHORIZED_PARTNERS.length) return;

        wrapper.innerHTML = AUTHORIZED_PARTNERS.map(partnerTemplate).join('');

        const countEl = document.getElementById('authorized-count');
        if (countEl) countEl.textContent = AUTHORIZED_PARTNERS.length;

        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }

        new Swiper('.authorized-swiper', {
            slidesPerView: 1,
            spaceBetween: 24,
            centeredSlides: true,
            grabCursor: true,
            speed: 500,
            navigation: {
                prevEl: '.authorized-prev',
                nextEl: '.authorized-next',
            },
            pagination: {
                el: '.authorized-pagination',
                clickable: true,
                bulletClass: 'authorized-bullet',
                bulletActiveClass: 'authorized-bullet-active',
                renderBullet: (index, className) => `<button type="button" class="${className}" aria-label="第 ${index + 1} 位"></button>`,
            },
            keyboard: { enabled: true },
        });
    })();

    // Pro Modal - 综合购买弹窗
    (function initProModal() {
        const modal = document.getElementById('pro-modal');
        if (!modal) return;

        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') lenis.stop();
            // 每次打开都重置滚动位置到顶部
            const body = modal.querySelector('.pro-body');
            if (body) body.scrollTop = 0;
            const dialog = modal.querySelector('.pro-dialog');
            if (dialog) dialog.scrollTop = 0;
        };
        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.start === 'function') lenis.start();
        };
        window.openProModal = openModal;
        window.closeProModal = closeModal;

        // 所有 [data-pro-open] 按钮 → 打开弹窗
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-pro-open]');
            if (trigger) { e.preventDefault(); openModal(); }
        });
        // 关闭按钮 / backdrop
        modal.querySelectorAll('[data-pro-close]').forEach(el => el.addEventListener('click', closeModal));
        // ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });

        // 通用 · 调出大图 QR (复用已有 #qr-modal)
        const showQR = (src, title) => {
            const qrImg = document.getElementById('qr-modal-img');
            const qrTitle = document.getElementById('qr-modal-title');
            const qrModal = document.getElementById('qr-modal');
            if (qrImg && src) qrImg.src = src;
            if (qrTitle) qrTitle.textContent = title || '扫码添加';
            if (qrModal) qrModal.classList.add('is-open');
        };

        // 点击微信 QR → 放大
        const wechatQR = modal.querySelector('.pro-wechat-qr');
        if (wechatQR) {
            wechatQR.addEventListener('click', () => {
                showQR(wechatQR.getAttribute('data-qr'), wechatQR.getAttribute('data-qr-title'));
            });
        }

        // 点击 "QQ 二维码" 按钮 → 调出 QQ QR
        modal.querySelectorAll('[data-pro-qq]').forEach(btn => {
            btn.addEventListener('click', () => {
                showQR(
                    'assets/img/qr-qq.png?v=2.1.0',
                    'QQ · 3439780232'
                );
            });
        });

        // Pro Modal 内 [data-legal-open] 按钮 → 让全局 legal-modal 处理器打开 Legal,
        // 不关闭 Pro Modal, 叠层显示 (z-index: Legal=220 > Pro=210)
        // 这里无需额外代码 · 现有 HTML 上 data-legal-open 属性已被全局监听

        // 子弹窗 (legal / qr) 关闭时, 若 Pro 仍打开, 需要重新锁滚动 (否则背景会浮出)
        const reLockIfProOpen = () => {
            if (!modal.classList.contains('is-open')) return;
            document.body.classList.add('overflow-hidden');
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') lenis.stop();
        };
        ['legal-modal', 'qr-modal'].forEach(id => {
            const sub = document.getElementById(id);
            if (!sub) return;
            new MutationObserver((records) => {
                for (const r of records) {
                    if (r.attributeName === 'aria-hidden' && sub.getAttribute('aria-hidden') === 'true') {
                        // 等子弹窗收尾完后再回锁
                        requestAnimationFrame(reLockIfProOpen);
                    }
                }
            }).observe(sub, { attributes: true, attributeFilter: ['aria-hidden', 'class'] });
        });
    })();

    // Review Modal - 查看完整评价
    (function initReviewModal() {
        const modal = document.getElementById('review-modal');
        if (!modal) return;
        const bodyEl = modal.querySelector('[data-review-body]');
        const nameEl = modal.querySelector('[data-review-name]');
        const avatarEl = modal.querySelector('[data-review-avatar]');

        window.openReviewModal = function (cardEl) {
            if (!cardEl) return;
            const img = cardEl.querySelector('img');
            const nameNode = cardEl.querySelector('.text-white.font-bold');
            const textNode = cardEl.querySelector('p');
            if (avatarEl && img) avatarEl.src = img.src;
            if (nameEl && nameNode) nameEl.textContent = nameNode.textContent;
            // 使用 innerHTML 保留 author-mention 高亮 (源文本已经过 escapeHtml, 安全)
            if (bodyEl && textNode) bodyEl.innerHTML = textNode.innerHTML;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') lenis.stop();
        };
        const close = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.start === 'function') lenis.start();
        };
        modal.querySelectorAll('[data-review-close]').forEach(el => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
        });
        window.closeReviewModal = close;
    })();
})();

// 5. Scroll Spy (rAF throttled + cached sections)
let cachedSections = [];
const rebuildSectionCache = () => {
    const ids = ['modules', 'ui', 'timeline', 'compare', 'license', 'reviews', 'pricing', 'about'];
    cachedSections = [{ id: 'hero', top: 0 }];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) cachedSections.push({ id, top: el.offsetTop - 300 });
    });
};
rebuildSectionCache();

const navLinksCache = document.querySelectorAll('.nav-link');
const elevatorDotsCache = document.querySelectorAll('.elevator-dot');
const mobileNavCache = document.querySelectorAll('.mobile-nav-item');
const navPillEl = document.getElementById('nav-pill');

function _doUpdateNavigationState() {
    const scrollY = window.scrollY;
    const windowHeight = window.innerHeight;
    const docHeight = document.documentElement.scrollHeight;
    const sections = cachedSections;

    // 找当前 section
    let currentSection = 'hero';
    for (let i = sections.length - 1; i >= 0; i--) {
        if (scrollY >= sections[i].top) {
            currentSection = sections[i].id;
            break;
        }
    }

    // 页面底部强制 about
    if (scrollY + windowHeight >= docHeight - 50) {
        currentSection = 'about';
    }

    // 更新顶部导航
    let activeLink = null;
    navLinksCache.forEach(link => {
        const isActive = link.getAttribute('href') === `#${currentSection}`;
        link.classList.toggle('text-white', isActive);
        link.classList.toggle('text-gray-300', !isActive);
        if (isActive) activeLink = link;
    });

    if (activeLink && navPillEl) {
        const container = activeLink.parentElement.getBoundingClientRect();
        const link = activeLink.getBoundingClientRect();
        navPillEl.style.left = `${link.left - container.left}px`;
        navPillEl.style.width = `${link.width}px`;
    }

    elevatorDotsCache.forEach(dot => {
        const inner = dot.querySelector('div');
        if (!inner) return;
        const isActive = dot.getAttribute('data-target') === currentSection;
        inner.classList.toggle('bg-white', isActive);
        inner.classList.toggle('scale-150', isActive);
        inner.classList.toggle('ring-4', isActive);
        inner.classList.toggle('ring-white/20', isActive);
        inner.classList.toggle('bg-white/20', !isActive);
    });

    mobileNavCache.forEach(item => {
        const isActive = item.getAttribute('data-target') === currentSection;
        item.classList.toggle('bg-white', isActive);
        item.classList.toggle('text-black', isActive);
        item.classList.toggle('text-gray-400', !isActive);
    });
}

// rAF 节流包装
let _navStateTicking = false;
function updateNavigationState() {
    if (_navStateTicking) return;
    _navStateTicking = true;
    requestAnimationFrame(() => {
        _doUpdateNavigationState();
        _navStateTicking = false;
    });
}

// 绑定事件（passive 监听避免阻塞滚动）
window.addEventListener('scroll', updateNavigationState, { passive: true });
window.addEventListener('resize', () => { rebuildSectionCache(); updateNavigationState(); });
window.addEventListener('load', () => { rebuildSectionCache(); updateNavigationState(); });
updateNavigationState();

// Initialize ECharts Maps
document.addEventListener('DOMContentLoaded', () => {
    const map1Dom = document.getElementById('echarts-travel-map');
    const map2Dom = document.getElementById('echarts-footprint-map');

    if (!map1Dom || !map2Dom) return;

    // Create Dot Pattern Canvas for premium tech feel (Lower resolution pattern to save GPU)
    const dotCanvas = document.createElement('canvas');
    dotCanvas.width = 12;
    dotCanvas.height = 12;
    const ctx = dotCanvas.getContext('2d');
    ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
    ctx.beginPath();
    ctx.arc(6, 6, 1, 0, Math.PI * 2);
    ctx.fill();

    // Fetch China GeoJSON · 本地优先, CDN 兜底 (Aliyun 线上有 403 防盗链)
    const loadChinaGeoJSON = async () => {
        const sources = [
            'assets/data/china.json?v=2.1.0',
            'https://geo.datav.aliyun.com/areas_v3/bound/100000_full.json',
            'https://cdn.jsdelivr.net/gh/echarts-maps/echarts-china-cities-js@master/geojson/china.json'
        ];
        for (const url of sources) {
            try {
                const res = await fetch(url);
                if (!res.ok) continue;
                const text = await res.text();
                if (!text.trim().startsWith('{')) continue; // 过滤掉 HTML 错误页
                return JSON.parse(text);
            } catch (_) { /* 继续下一个 */ }
        }
        throw new Error('All China map sources failed');
    };
    loadChinaGeoJSON()
        .then(geoJson => {
            echarts.registerMap('china', geoJson);

            // --- Map 1: Travel Log (Flight Path) ---
            const map1 = echarts.init(map1Dom, null, { renderer: 'canvas' });
            map1.setOption({
                backgroundColor: 'transparent',
                geo: {
                    map: 'china',
                    roam: false,
                    zoom: 1.2,
                    center: [105, 36],
                    itemStyle: {
                        areaColor: {
                            image: dotCanvas,
                            repeat: 'repeat'
                        },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 0.5
                    },
                    emphasis: {
                        disabled: true
                    },
                    silent: true // Disable interactions to save CPU on scroll
                },
                series: [
                    // Flight path
                    {
                        type: 'lines',
                        zlevel: 2,
                        effect: {
                            show: true,
                            period: 4,
                            trailLength: 0.1,
                            color: '#fff',
                            symbol: 'circle',
                            symbolSize: 3
                        },
                        lineStyle: {
                            color: 'rgba(255, 255, 255, 0.2)',
                            width: 1,
                            opacity: 0.5,
                            curveness: 0.3
                        },
                        silent: true,
                        data: [
                            {
                                fromName: '成都',
                                toName: '哈尔滨',
                                coords: [
                                    [104.0657, 30.6595], // Chengdu
                                    [126.5350, 45.8028]  // Harbin
                                ]
                            }
                        ]
                    },
                    // Static markers for map 1 to save performance
                    {
                        type: 'effectScatter',
                        coordinateSystem: 'geo',
                        zlevel: 2,
                        rippleEffect: {
                            brushType: 'stroke',
                            scale: 3
                        },
                        label: { show: false },
                        symbolSize: 4,
                        itemStyle: {
                            color: '#fff',
                        },
                        silent: true,
                        data: [
                            { name: '成都', value: [104.0657, 30.6595] },
                            { name: '哈尔滨', value: [126.5350, 45.8028] }
                        ]
                    }
                ]
            });

            // All locations for Map 2
            const allLocations = [
                [116.4053, 39.9051], [121.4726, 31.2304], [113.2806, 23.1252],
                [114.0579, 22.5431], [104.0657, 30.6595], [106.5050, 29.5332],
                [108.9480, 34.2632], [120.1551, 30.2741], [120.1551, 32.0415],
                [114.3054, 30.5928], [114.3054, 28.2282], [108.9480, 34.7466],
                [120.3326, 36.0715], [118.0894, 24.4798], [119.2965, 26.0745],
                [102.7123, 25.0406], [100.2290, 25.5822], [100.2277, 26.8550],
                [109.5119, 18.2528], [110.3295, 20.0174], [103.92, 33.26],
                [87.6168, 43.8256], [75.9891, 39.4677], [91.1172, 29.6469],
                [103.8235, 36.0580], [101.7782, 36.6171], [106.2309, 38.4872],
                [111.7510, 40.8415], [126.5350, 45.8028], [125.3235, 43.8171],
                [123.4291, 41.7968], [125.3235, 38.9140], [117.0009, 36.6758],
                [120.1551, 31.8206], [115.8921, 28.6820], [102.7123, 26.5728],
                [108.3661, 22.8172], [110.2902, 25.2736], [112.5489, 37.8706],
                [114.4827, 38.0315], [120.6195, 31.2990], [120.3119, 31.4912]
            ];

            // Select only a few locations to have the ripple effect to save CPU
            const effectLocations = [
                [116.4053, 39.9051], [121.4726, 31.2304], [113.2806, 23.1252],
                [104.0657, 30.6595], [108.9480, 34.2632], [109.5119, 18.2528]
            ];

            // --- Map 2: Footprint Map ---
            const map2 = echarts.init(map2Dom, null, { renderer: 'canvas' });
            map2.setOption({
                backgroundColor: 'transparent',
                geo: {
                    map: 'china',
                    roam: false, // Disabled roam for better scroll performance
                    zoom: 1.25,
                    center: [105, 36],
                    itemStyle: {
                        areaColor: {
                            image: dotCanvas,
                            repeat: 'repeat'
                        },
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 0.5,
                    },
                    emphasis: {
                        disabled: true // Disable hover to save CPU
                    },
                    silent: true
                },
                series: [
                    // Static dots for the majority to save resources
                    {
                        type: 'scatter',
                        coordinateSystem: 'geo',
                        zlevel: 1,
                        symbolSize: 3,
                        itemStyle: {
                            color: 'rgba(255,255,255,0.7)',
                        },
                        silent: true,
                        data: allLocations.map(coord => ({ value: coord }))
                    },
                    // Effect dots for just a few main hubs
                    {
                        type: 'effectScatter',
                        coordinateSystem: 'geo',
                        zlevel: 2,
                        rippleEffect: {
                            brushType: 'stroke',
                            scale: 3,
                            period: 4
                        },
                        label: { show: false },
                        symbolSize: 3, // Uniform size
                        itemStyle: {
                            color: '#fff',
                            shadowBlur: 5,
                            shadowColor: 'rgba(255,255,255,0.8)'
                        },
                        silent: true,
                        data: effectLocations.map(coord => ({ value: coord }))
                    }
                ]
            });

            // Handle window resize (debounced)
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    map1.resize();
                    map2.resize();
                }, 100);
            });
        })
        .catch(err => console.error('Failed to load China map JSON:', err));
});

// ===== 懒加载初始化 (vanilla-lazyload) =====
const lazyLoadInstance = new LazyLoad({
    elements_selector: '.lazy',
    threshold: 400,
    class_loaded: 'loaded',
    class_loading: 'loading',
    class_error: 'error',
});
// 暴露到 window 便于 Swiper 切换后手动 update
window.__lazyLoad = lazyLoadInstance;

// ===== Pro 功能胶囊 · Swiper 自由滑动 + 边缘渐变 =====
document.querySelectorAll('.pro-features.swiper').forEach((el) => {
    const swiper = new Swiper(el, {
        slidesPerView: 'auto',
        spaceBetween: 8,
        freeMode: {
            enabled: true,
            momentum: true,
            momentumBounce: false,
            sticky: false,
        },
        grabCursor: true,
        mousewheel: {
            forceToAxis: true,
            sensitivity: 1,
        },
        resistance: true,
        resistanceRatio: 0.7,
        watchSlidesProgress: true,
    });
    const updateEdges = () => {
        el.classList.toggle('is-at-start', swiper.isBeginning);
        el.classList.toggle('is-at-end', swiper.isEnd);
    };
    swiper.on('progress', updateEdges);
    swiper.on('reachBeginning', updateEdges);
    swiper.on('reachEnd', updateEdges);
    updateEdges();
});

// ===== Logo 点击 · 平滑滚动回顶 (Lenis) =====
document.querySelectorAll('a[aria-label="LGNewUi"]').forEach((logo) => {
    logo.addEventListener('click', (e) => {
        e.preventDefault();
        if (typeof lenis !== 'undefined' && lenis && typeof lenis.scrollTo === 'function') {
            lenis.scrollTo(0, { duration: 1.2 });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});

// ===== Fancybox 灯箱 =====
if (typeof Fancybox !== 'undefined') {
    const fancyboxOn = {
        reveal: () => {
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') {
                lenis.stop();
            }
            document.body.classList.add('fancybox-locked');
        },
        destroy: () => {
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.start === 'function') {
                lenis.start();
            }
            document.body.classList.remove('fancybox-locked');
        },
    };
    // 界面一览 (gallery · 49 张 · 相互成组)
    Fancybox.bind('[data-fancybox="gallery"]', {
        groupAll: true,
        Hash: false,
        Thumbs: { showOnStart: false },
        Toolbar: {
            display: {
                left: ['infobar'],
                middle: [],
                right: ['slideshow', 'zoomIn', 'zoomOut', 'toggle1to1', 'download', 'close'],
            },
        },
        on: fancyboxOn,
    });
    // 版权证书 (copyright · 单图)
    Fancybox.bind('[data-fancybox="copyright"]', {
        Hash: false,
        Thumbs: false,
        Toolbar: {
            display: {
                left: ['infobar'],
                middle: [],
                right: ['zoomIn', 'zoomOut', 'toggle1to1', 'download', 'close'],
            },
        },
        on: fancyboxOn,
    });
}

// ===== FAQ 手风琴 (互斥 + 高度动画) =====
document.querySelectorAll('[data-accordion]').forEach((group) => {
    const items = group.querySelectorAll('.faq-item');
    items.forEach((item) => {
        const trigger = item.querySelector('.faq-trigger');
        if (!trigger) return;
        trigger.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            // 先关闭同组其他项
            items.forEach((other) => {
                if (other !== item) other.classList.remove('is-open');
            });
            // 再切换当前项
            item.classList.toggle('is-open', !isOpen);
        });
    });
});

// ===== Hero 描边字点击填充 (移动端也能触发, 播完自动回弹) =====
document.querySelectorAll('[data-fill-text]').forEach((el) => {
    let revertTimer = null;
    el.addEventListener('click', () => {
        clearTimeout(revertTimer);
        el.classList.remove('is-filled');
        // 强制重绘以重播动画
        void el.offsetWidth;
        el.classList.add('is-filled');
        // 填充动画约 1.1s, 停留 0.4s 后自动回到描边态
        revertTimer = setTimeout(() => {
            el.classList.remove('is-filled');
            el.blur();
        }, 1500);
    });
});

// ===== Contact QR Modal =====
(function () {
    const modal = document.getElementById('qr-modal');
    if (!modal) return;

    const imgEl = document.getElementById('qr-modal-img');
    const titleEl = document.getElementById('qr-modal-title');
    const hintEl = document.getElementById('qr-modal-hint');
    const iconEl = modal.querySelector('.qr-modal__icon i');

    const presets = {
        qq: {
            title: 'QQ · 3439780232',
            hint: 'SCAN TO ADD FRIEND',
            icon: 'message-circle',
        },
        wechat: {
            title: 'WeChat',
            hint: 'SCAN TO ADD FRIEND',
            icon: 'message-square',
        },
    };

    function openModal(type, qrUrl, idText) {
        const preset = presets[type] || presets.qq;
        modal.setAttribute('data-theme', type);
        modal.setAttribute('aria-hidden', 'false');
        titleEl.textContent = idText ? `${type.toUpperCase()} · ${idText}` : preset.title;
        hintEl.textContent = preset.hint;
        imgEl.src = qrUrl;
        imgEl.alt = preset.title;
        if (iconEl) {
            iconEl.setAttribute('data-lucide', preset.icon);
        }
        modal.classList.add('is-open');
        document.body.classList.add('qr-modal-open');
        // 停止 Lenis 虚拟滚动 (overflow:hidden 挡不住它)
        if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') {
            lenis.stop();
        }
        if (window.lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('qr-modal-open');
        if (typeof lenis !== 'undefined' && lenis && typeof lenis.start === 'function') {
            lenis.start();
        }
    }

    document.querySelectorAll('[data-contact]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const type = btn.getAttribute('data-contact');
            const qrUrl = btn.getAttribute('data-qr');
            const idText = btn.getAttribute('data-contact-id');
            if (!qrUrl) return;
            openModal(type, qrUrl, idText);
        });
    });

    modal.querySelectorAll('[data-qr-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
})();


(function () {
    const modal = document.getElementById('legal-modal');
    if (!modal) return;
    const tabs = modal.querySelectorAll('[data-legal-tab]');
    const indicator = modal.querySelector('[data-legal-indicator]');
    const sections = modal.querySelectorAll('[data-legal-section]');
    const body = modal.querySelector('[data-legal-body]');
    let firstOpen = true;

    function moveIndicator(name) {
        const active = modal.querySelector(`[data-legal-tab="${name}"]`);
        if (!active || !indicator) return;
        indicator.style.width = active.offsetWidth + 'px';
        indicator.style.left = active.offsetLeft + 'px';
    }

    function setTab(name, skipScroll) {
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.legalTab === name));
        // 先统一移除 active，下一帧再加回去，强制重播入场动画
        sections.forEach(s => s.classList.remove('is-active'));
        requestAnimationFrame(() => {
            sections.forEach(s => {
                if (s.dataset.legalSection === name) s.classList.add('is-active');
            });
        });
        moveIndicator(name);
        if (body && !skipScroll) body.scrollTop = 0;
    }

    function open(tab) {
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        if (typeof lenis !== 'undefined' && lenis && typeof lenis.stop === 'function') lenis.stop();

        // 首次打开或每次打开都走骨架屏 loading 效果
        modal.classList.add('is-loading');
        modal.classList.add('is-open');
        setTab(tab || 'license');
        // 首次需要等 layout 完成后再定位指示条
        requestAnimationFrame(() => moveIndicator(tab || 'license'));

        if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons();

        // 骨架屏显示 ~380ms 后过渡到内容
        setTimeout(() => {
            modal.classList.remove('is-loading');
            firstOpen = false;
        }, firstOpen ? 480 : 260);
    }

    function close() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (typeof lenis !== 'undefined' && lenis && typeof lenis.start === 'function') lenis.start();
    }

    // 触发器
    document.querySelectorAll('[data-legal-open]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            open(btn.dataset.legalOpen);
        });
    });

    modal.querySelectorAll('[data-legal-close]').forEach(btn => btn.addEventListener('click', close));
    tabs.forEach(t => t.addEventListener('click', () => setTab(t.dataset.legalTab)));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });

    // 窗口尺寸变化时重新定位指示条
    window.addEventListener('resize', () => {
        const active = modal.querySelector('.legal-tab.is-active');
        if (active) moveIndicator(active.dataset.legalTab);
    });

    // 初始化一次（隐藏状态下也要让指示条有初值）
    requestAnimationFrame(() => moveIndicator('license'));

    // 暴露给外部复用
    window.openLegalModal = open;
    window.closeLegalModal = close;
})();

/* ============================================== */
/*   功能对比列表 · 边界滚轮穿透到 Lenis           */
/* ============================================== */
(function () {
    const el = document.getElementById('matrix-body');
    if (!el) return;

    el.addEventListener('wheel', (e) => {
        const { scrollTop, scrollHeight, clientHeight } = el;
        const up = e.deltaY < 0;
        const atTop = scrollTop <= 0;
        const atBottom = Math.ceil(scrollTop + clientHeight) >= scrollHeight - 1;
        if ((up && atTop) || (!up && atBottom)) {
            e.preventDefault();
            if (typeof lenis !== 'undefined' && lenis && typeof lenis.scrollTo === 'function') {
                const current = (typeof lenis.scroll === 'number') ? lenis.scroll : window.scrollY;
                lenis.scrollTo(current + e.deltaY, { immediate: false, duration: 0.3 });
            } else {
                window.scrollBy({ top: e.deltaY });
            }
        }
    }, { passive: false });
})();

/* ============================================== */
/*   无限动画性能优化 · 视窗外自动暂停             */
/* ============================================== */
(function () {
    if (!('IntersectionObserver' in window)) return;
    const marquees = document.querySelectorAll('[data-marquee]');
    if (!marquees.length) return;

    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            e.target.style.animationPlayState = e.isIntersecting ? 'running' : 'paused';
        });
    }, { rootMargin: '100px 0px', threshold: 0 });

    marquees.forEach((m) => {
        // 默认先暂停，等到滚到视窗内再启动
        m.style.animationPlayState = 'paused';
        io.observe(m);
    });
})();
