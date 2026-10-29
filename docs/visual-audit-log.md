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
