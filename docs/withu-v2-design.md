# withU v2 视觉与交互层

> 本文件记录 v2 的设计令牌、组件覆盖范围、动效清单与维护方式。
> 新增页面只需引入公共页脚，即可自动获得 v2 的全部能力。

## 1. 设计方向

- **关键词**：晨雾玫瑰、纸感留白、克制动效
- **气质**：温暖但不甜腻，像一本被反复翻看的私人手账
- **原则**：
  1. 内容优先，装饰退后；
  2. 动效只用于解释状态与引导注意力；
  3. 任何效果在 `prefers-reduced-motion` 下都必须可关闭；
  4. 触控目标不小于 44px，键盘焦点始终可见。

## 2. 文件结构

| 文件 | 作用 |
| --- | --- |
| `frontend/Style/css/withu-v2.css` | 全站视觉升级层，最后加载，覆盖旧样式 |
| `frontend/assets/js/withu-v2.js` | 渐进增强脚本，JS 失效时页面仍完整可用 |
| `frontend/inc/hero.php` | 内页横幅，读取后台「首页大图」配置并回退默认图 |
| `frontend/inc/footer.php` | 统一挂载 v2 CSS / JS（全站只改这一处） |

### 加载顺序

1. 各页面原有 CSS / JS 保持不动；
2. `inc/hero.php` 替换内页重复的 Hero 标记；
3. `inc/footer.php` 在页面底部加载 `withu-v2.css` 与 `withu-v2.js`，确保覆盖页面级样式；
4. 每个页面 `<head>` 内保留一段**关键首屏 CSS**（内页 Hero 高度），避免底部样式加载前的布局跳动。

> 修改 `withu-v2.css` / `.js` 后，请同步递增 `inc/footer.php` 中的 `?v=` 版本号，否则浏览器会继续使用缓存。

## 3. 设计令牌

```css
--wu-rose-500: #f4587a;      /* 主色 */
--wu-rose-600: #e23e63;      /* 强调 */
--wu-champagne: #f7efe4;     /* 辅助暖色 */
--wu-ink: #2a2226;           /* 主文字 */
--wu-ink-soft: #5d5360;      /* 正文 */
--wu-ink-muted: #8f8490;     /* 次要信息 */
--wu-line: rgba(42,34,38,.08);
--wu-radius-sm/md/lg/xl: 14/18/24/30px;
--wu-shadow-sm/md/lg/rose;
--wu-ease-out: cubic-bezier(.22,1,.36,1);
--wu-ease-spring: cubic-bezier(.34,1.56,.64,1);
```

字体：

- 标题：`"Noto Serif SC", "Noto Serif CJK SC", "Songti SC", serif`
- 正文：`"Inter", "HarmonyOS Sans SC", "PingFang SC", system-ui, sans-serif`
- 英文装饰：`"Playfair Display"` / `"Dancing Script"`（按需）

## 4. 组件规范

| 组件 | 关键行为 |
| --- | --- |
| 内页 Hero | 桌面 38vh / 最高 460px；移动 32vh / 最高 300px；移动端隐藏头像面板 |
| 导航岛 | 玻璃拟态 + 玫瑰渐变指示器 + 悬停上浮 + 键盘焦点环 |
| 卡片 | 24px 圆角、纸感渐变、悬停上浮 5px、图片 1.035 倍缓推 |
| 按钮 | 胶囊圆角、悬停上浮、按下 0.98、点击波纹 |
| 空状态 | 玫瑰色光斑、漂浮图标、说明文案、主行动按钮 |
| 搜索条 | 毛玻璃胶囊、`/` 快捷键聚焦、结果计数、清除按钮 |
| 骨架屏 | 玫瑰色 shimmer，尊重 reduced-motion |

## 5. 动效清单

| 动效 | 触发 | 实现 | 时长 / 曲线 |
| --- | --- | --- | --- |
| 页面入场 | 首次加载 / PJAX | 容器 opacity | 0.45s ease-out |
| Hero 缓推 | 内页首图 | CSS `withuHeroDrift` | 22s alternate |
| 滚动揭示 | 非 AOS 卡片 | IntersectionObserver | 0.7s ease-out |
| 卡片微倾斜 | 指针设备 hover | 内联 transform | 3.2deg 上限 |
| 点击波纹 | 按钮 / 导航 | 注入 span + keyframes | 0.65s |
| 点赞心跳 | 点赞按钮 | Web Animations API | 0.62–0.84s |
| 数字滚动 | 首页天数 | rAF + easeOutCubic | 1.2s |
| 磁吸 | 主要圆形按钮 | mousemove 内联 transform | 0.3s 回弹 |
| 骨架屏 | 数据加载中 | 背景位移 | 1.6s linear |

所有动效在 `prefers-reduced-motion: reduce` 下自动关闭。

## 6. 功能增强

- **内页 Hero 动态图**：优先读取 `settings.home_banner_images`，未配置时回退 `withu_home_carousel_defaults()`；所有轮播图直接写入 `src`，避免切图时出现空白。
- **页面内搜索**：相册、点滴、留言自动注入搜索条，支持 `/` 聚焦与实时过滤；动态列表通过 MutationObserver 重新应用关键词。
- **单卡居中**：只有一条留言 / 一张相册时自动居中，避免半宽悬空。
- **空状态 CTA**：相册、点滴、清单提供登录或后台入口；游客与登录用户文案不同。
- **滚动进度条**：顶部 3px 玫瑰渐变，随滚动更新。
- **无障碍**：跳过导航链接、`aria-live` 搜索结果、`focus-visible` 焦点环、44px 触控尺寸。

## 7. 维护注意事项

1. **不要**在 v2 JS 中给页面容器加 `transform` 动画：祖先一旦有 transform，`position: fixed` 的抽屉/弹窗会以它为包含块，导致定位错乱。
2. **不要**对弹窗遮罩层应用入场动画（会覆盖其 `opacity: 0`）。
3. 新增页面级 CSS 时，若需覆盖 v2，请使用相同或更高特异性；v2 文件本身最后加载。
4. 旧页面 HTML 结构存在“内容先于 `<html>/<head>` 输出”的历史问题；v2 已通过关键内联样式与页脚加载规避视觉影响，后续重构时可统一整理。
5. 后台 / 登录页不在 v2 作用域内，修改 `withu-v2.css` 不会影响后台。
