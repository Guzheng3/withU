# withU Admin v4 — Quiet Studio

> 后台视觉与交互升级说明。v4 不替换任何业务逻辑，只覆盖视觉与增强交互。

## 1. 设计方向

- **关键词**：暖白纸感、单一玫瑰强调色、安静的工作台
- **参考**：shadcn dashboard 的信息层级、Geist 的中性色阶、Linear / Notion 的紧凑侧栏
- **原则**
  1. 中性色负责层级，玫瑰色只负责强调；
  2. 卡片用细边框与轻阴影，不用大面积彩色块；
  3. 表单控件统一 44px 高、12px 圆角、毛玻璃只用在浮层；
  4. 所有动效尊重 `prefers-reduced-motion`。

## 2. 文件

| 文件 | 作用 |
| --- | --- |
| `backend/app/assets/css/admin_v4.css` | 后台视觉层，最后加载，覆盖 v3/v2/page 级样式 |
| `backend/app/assets/js/admin_v4.js` | 侧栏折叠、命令面板、表格筛选、波纹 |
| `backend/app/admin/header.php` | 引入 v4 资源、折叠按钮、搜索入口、用户胶囊 |
| `backend/app/admin/footer.php` | 引入 v4 脚本 |
| `backend/app/login.php` | 登录页内联样式已重做（双栏面板） |

## 3. 令牌

```css
--a-bg: #f6f2f2;          /* 页面底色 */
--a-surface: #fffdfd;     /* 卡片 */
--a-surface-2: #fbf7f8;   /* 输入框 / 次级面 */
--a-ink: #2b2327;         /* 主文字 */
--a-ink-2: #6d6167;       /* 正文 */
--a-ink-3: #9b8e94;       /* 次要信息 */
--a-line: #eadfe2;        /* 描边 */
--a-rose: #d94f6d;        /* 强调色 */
--a-rose-deep: #be3a58;
--a-rose-soft: #fdeef2;
--a-champagne: #b9855f;   /* 次强调 / 图表第二序列 */
--a-sage: #3f8f6d;        /* 成功 */
--a-radius: 18px;
```

## 4. 组件

- **侧栏**：256px 暖白玻璃，分组标题 10px 大写字距，当前项白底 + 左侧玫瑰短条；桌面端可折叠为 82px 图标栏，状态记忆在 `localStorage.withu_admin_sidebar_collapsed`。
- **顶栏**：66px 毛玻璃，面包屑「分组 / 当前页」，搜索入口 `Ctrl/⌘ + K`，用户胶囊显示昵称与 User 1/2。
- **页面标题**：衬线标题 + 左侧玫瑰竖条；描述行缩进对齐。
- **统计卡片**：3×2 网格，白卡细边、右下柔光、悬停上浮 3px；不再使用拼接网格和彩色左边框。
- **表单**：44px 高、12px 圆角、暖灰底，聚焦时白底 + 玫瑰 4px 光环；文件控件自定义按钮。
- **设置页**：header、帮助文案、表单字段收进同一张卡；分段导航变为胶囊 segmented control。
- **空状态**：虚线卡片 + 玫瑰心形图标 + 居中说明。
- **弹窗/命令面板**：20px 圆角、深阴影、背景模糊。

## 5. 快捷操作

| 操作 | 说明 |
| --- | --- |
| `Ctrl / ⌘ + K` | 打开命令面板，搜索并跳转后台页面 |
| `↑ / ↓` | 命令面板中切换结果 |
| `Enter` | 打开选中页面 |
| `Esc` | 关闭命令面板 |
| 点击顶栏折叠按钮 | 收/展侧栏（桌面端） |
| 表格 ≥ 4 行 | 自动出现「筛选当前列表」搜索条 |

## 6. 维护注意

1. 修改 `admin_v4.css` / `admin_v4.js` 后，同步递增 `header.php` / `footer.php` 的 `?v=`。
2. 页面级内联样式优先级高于普通类；需要覆盖时使用 v4 命名空间 + `!important`（已在表单、文件控件中处理）。
3. 后台旧页面仍有 `.admin-v2` / `.admin-v3` 类名，v4 通过 `body.admin-v4` 覆盖，不需要逐页改类名。
4. 登录页不使用 `admin_v4.css`，其视觉在 `login.php` 的 `<style>` 内独立维护。
5. 桌宠在后台已隐藏，避免遮挡侧栏底部。
