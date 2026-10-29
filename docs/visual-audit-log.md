# 视觉巡检记录（2026-10-28）

- 基线标签：`visual-audit-baseline-v1`（提交 `43d8040`）
- 巡检方式：Playwright 逐页渲染截图，覆盖 1440 / 1024 / 768 / 520 / 480 / 390 / 320 宽度；同时用脚本检查横向溢出、文字裁切、虚线/金色边框残留、页脚与桌宠重叠。
- 覆盖页面：首页、清单、相册、点滴、轨迹、留言、关于、`page.php?id=1`、`album-detail.php?id=1`。

## 已修复

### 1. 空状态虚线框与统一卡片边缘不一致

- 现象：全站卡片已统一为 24px 圆角 + 白色发丝边框 + 柔和阴影，但 `.withu-no-data` 仍是金色/粉色虚线框，首页与子页都显得突兀。
- 修复：空状态保留原有背景，边缘改为与内容卡相同的 `--wu3-edge-hairline`、`--wu3-edge-radius`、`--wu3-edge-shadow`。
- 位置：`frontend/Style/css/withu-v3.css` 第 18 节。
- 验证：首页、清单、点滴、轨迹、`page.php` 空状态均为统一卡片边缘，无虚线框。

### 2. 相册页“卡片套卡片”

- 现象：外层 `.withu-card` 已有统一边缘，内层 `.withu-content.withu-no-data` 仍带一圈金色虚线框。
- 修复：卡片内嵌的空状态取消边框、背景、阴影和 `backdrop-filter`，只保留内容。
- 位置：`withu-v3.css` 第 18 节。
- 验证：相册页只剩外层一张卡片，内部无二次描边。

### 3. 天气弹层小时预报横向滚动缺少提示

- 现象：桌面端小时预报右端卡片被直接切断，看不出可以横向滑动。
- 修复：给 `.wu-weather-modal__hourly` 增加右侧 18px 渐隐遮罩。
- 位置：`frontend/Style/css/withu-weather-modal.css`。
- 验证：桌面与移动端右端出现柔和渐隐，滑动后内容完整。

### 4. 移动端页脚版权被桌宠遮挡

- 现象：`<=520px` 时桌宠覆盖 Copyright 徽章左侧文字。
- 修复：仅在小屏给页脚增加 64px 底部留白，让版权徽章落在桌宠上方；桌宠仍完整显示。
- 位置：`withu-v3.css` 第 18 节。
- 验证：320 / 390 / 480 / 520 / 640 宽度下徽章与桌宠不重叠，页面无横向溢出。

### 5. 页脚未贴底（短页下方留空）

- 现象：高视口下页脚停在内容末尾，下方留 138–251px 空白；清单页隐藏的骨架屏还额外撑高约 154px。
- 修复：`body.bg-pdot-vignette` 改为纵向 flex，`.div_marb_7rem-none` 使用 `margin-top: auto`，页脚始终贴住视口底部；未激活的骨架屏 `display: none`，不再参与页面高度；桌面端保留 `4rem` 底部留白。
- 位置：`frontend/Style/css/withu-v3.css` 第 10 节、第 18 节。
- 验证：1600px 高度下首页、清单、相册、留言、关于页脚均贴底；1000px 与移动端滚动正常；骨架屏加载态仍能正常显示。

### 6. 播放页简介右侧文字截断

- 现象：`#detailSummary` 内的演职员行被 `white-space: nowrap` + `text-overflow: ellipsis` 强制单行，长名单在右侧出现省略号。
- 修复：演职员行改为可换行（`white-space: normal`、`overflow: visible`、`text-overflow: clip`），并给演职员文本和简介正文增加 `overflow-wrap: anywhere` / `word-break: break-word`；`is-cast-open` 状态不再限制 `max-height`。
- 位置：`backend/app/watch_play.php` 的 `.media-detail-v4` 样式段。
- 验证：播放页需要登录，使用该页真实 v4 CSS 构建夹具验证；1280px 下演职员单行完整显示，390px 下自然换行，`scrollWidth == clientWidth`，无横向溢出、无相邻内容遮挡。
- 标签：`watch-summary-wrap-v1`。

### 7. 首页留言卡与未完待续卡背景不一致

- 现象：留言卡为纯白底，“未完待续”卡为纯白半渐变，与相册空状态的“柔玫瑰光 + 半透明白”背景不统一。
- 修复：留言卡复用空状态背景；结尾卡保留“上透明 → 下实色”的半渐变，只把落点颜色换成同款 `rgba(255,255,255,.55)`，并在底部叠加柔玫瑰光。
- 位置：`frontend/Style/css/withu-v3.css` 第 19 节。
- 验证：1440 / 390 / 320 下留言卡与空状态计算背景一致；结尾卡顶部仍透明渐变；无横向溢出。
- 标签：`home-cards-bg-v1`。

### 8. 全站常规内容卡背景统一

- 现象：相册、留言、清单、点滴、轨迹、详情页等常规卡片仍是白色或纸感渐变。
- 修复：统一使用相册空状态的“柔玫瑰光 + 半透明白”背景；首页倒计时、事件、点滴、留言卡同步；详情页导航卡保留悬停反馈；详情头图内部白底改为透明，避免遮住统一背景。
- 保留：天气、清单进度、纪念日等彩色功能卡与图片卡保持原设计，避免失去色彩语义与文字对比。
- 位置：`frontend/Style/css/withu-v3.css` 第 20 节。
- 验证：各页卡片计算背景一致；1440 / 390 / 320 无横向溢出，文字与图片正常显示。
- 标签：`all-cards-bg-v1`。

### 9. PJAX 进入清单页时筛选滑块变成整条粉带

- 现象：从其他页面 PJAX 进入清单页时，筛选滑块在样式/布局稳定前测量，宽度被写成约 1130px，形成贯穿页面的粉色横条；直接刷新页面时不出现。
- 修复：滑块初始化增加布局校验与逐帧重试（宽度超过容器时跳过），并在 120ms / 400ms、字体就绪和窗口 resize 时重新测量；Tab 点击切换同样增加合法性保护。所有引入 `page-lovelist.js` 的页面统一升级脚本版本参数。
- 位置：`frontend/assets/js/page-lovelist.js` 及清单脚本引用。
- 验证：桌面 1778px PJAX、移动端 390px PJAX、直接加载、Tab 切换后滑块宽度均等于当前 Tab（桌面 112px / 移动端 101px），无整条横条、无横向溢出。
- 标签：`lovelist-slider-fix-v1`。

### 10. 天气卡长昵称在右侧被省略

- 现象：昵称被强制单行并加 `text-overflow: ellipsis`，长昵称在胶囊右侧被省略/截断。
- 修复：昵称改为在胶囊内自然换行（`white-space: normal`、`overflow: visible`、`text-overflow: clip`、`overflow-wrap: anywhere`），头像设置 `flex-shrink: 0`，时间标签仍固定在右侧。
- 位置：`frontend/Style/css/withu-v3.css` 第 21 节。
- 验证：1440 / 390 / 320 下长昵称完整换行，与时间标签不重叠，无横向溢出；短昵称排版不变。
- 标签：`weather-pill-wrap-v1`。

### 11. 导航岛配色更换

- 现象：导航岛原来是奶油纸感 + 玫瑰粉激活态，用户反馈整体配色不好看。
- 修复：改为“雾蓝石墨”配色——冷白玻璃导航岛、灰蓝文字、雾蓝到石墨的激活指示器；移动端底部导航同步换色。
- 位置：`frontend/Style/css/withu-v3.css` 第 22 节。
- 验证：桌面 1440 与移动端 390 下颜色生效，尺寸/间距/指示器位置不变，无横向溢出。
- 标签：`nav-palette-v1`。

### 12. 导航激活指示器恢复 lgnewui 珊瑚红

- 参照：`lgnewui-nav-indicator` 的 `#f87171`。
- 修复：桌面导航岛与移动底栏的激活指示器恢复为珊瑚红 `#f87171`，悬停色改为珊瑚淡底；冷白玻璃底与灰蓝文字层级保持不变。
- 位置：`frontend/Style/css/withu-v3.css` 第 23 节。
- 验证：计算颜色为 `rgb(248, 113, 113)`；1440 / 390 下尺寸、位置不变，无横向溢出。
- 标签：`nav-indicator-coral-v1`。

### 13. 浮动留言按钮改为珊瑚红

- 目标：`#mes > .mesly`。
- 修复：背景由玫瑰渐变改为与导航指示器一致的珊瑚红 `#f87171`，阴影同步为珊瑚色；悬停时恢复白底 + 珊瑚图标。
- 位置：`frontend/Style/css/withu-v3.css` 第 9 节。
- 验证：计算背景为 `rgb(248, 113, 113)`，悬停为白色底 + 珊瑚图标；尺寸和位置不变，无横向溢出。
- 标签：`mesly-coral-v1`。

### 14. 回顶按钮改为白色

- 目标：`#scrollTopBtn`。
- 修复：按钮背景由玫瑰渐变改为白色，图标改为珊瑚红 `#f87171`；悬停时变为珊瑚底 + 白色图标；金色进度环保留。
- 位置：`frontend/Style/css/withu-v3.css` 第 24 节。
- 验证：静止背景为 `rgb(255, 255, 255)`、图标为 `rgb(248, 113, 113)`；悬停为珊瑚底白图标；按钮尺寸/位置不变，无横向溢出。
- 标签：`scrolltop-white-v1`。

### 15. 观影页媒体库空列表的虚线边框

- 目标：`#strmGridMovie` 的空状态。
- 修复：电影 / 剧集媒体库网格的空状态去掉虚线边框；空状态的背景、圆角和图标保留。其他区域（如“最近观看”）的虚线空状态不受影响。
- 位置：`backend/app/watch.php` 的 `.empty` 样式段。
- 验证：用最小夹具确认 `#strmGridMovie > .empty` 与 `#strmGridTv > .empty` 计算边框为 `0`，“最近观看”空状态仍为 `1px dashed`；无横向溢出。观影页需登录，未直接截取实际页面。
- 标签：`watch-empty-border-v1`。

## 检查后保留、未改

- 桌面端桌宠漂浮在正文上方：站点既有交互设计，未改动。
- 关于页聊天回放的大块留白：回放舞台会随后续消息推进而填充，属于设计行为。
- 私密相册解锁页的深色独立主题：不属于前台 v3 页面，未改。
- 搜索栏与筛选 pill 的金色描边：属于控件而非卡片边缘，保持原样。

## 回归结果

- 所有巡检页面无横向溢出。
- 文字裁切扫描未发现异常（长地名、长昵称按预期省略）。
- 天气弹层 640/641 断点、320 窄屏无溢出；打开/关闭、滚动锁定、降级状态正常。
- 浏览器控制台无新增脚本错误。

## 版本与回退

| 标签 | 含义 |
| --- | --- |
| `visual-audit-baseline-v1` | 本次巡检前基线 |
| `visual-audit-v1` | 本次巡检修复完成 |
| `footer-bottom-spacing-v1` | 桌面端页脚备案信息留白调整 |
| `footer-sticky-bottom-v1` | 全站页脚贴底 + 隐藏骨架屏占位修复 |
| `watch-summary-wrap-v1` | 播放页简介演职员行取消右侧截断 |
| `home-cards-bg-v1` | 首页留言卡与未完待续卡背景统一 |
| `all-cards-bg-v1` | 全站常规内容卡背景统一 |
| `lovelist-slider-fix-v1` | PJAX 进入清单页时筛选滑块宽度修复 |
| `weather-pill-wrap-v1` | 天气卡长昵称改为换行完整显示 |

回退：

```bash
git revert <commit>
# 或
git checkout visual-audit-baseline-v1 -- \
  frontend/Style/css/withu-v3.css \
  frontend/Style/css/withu-weather-modal.css \
  frontend/inc/footer.php \
  frontend/index.php
```
