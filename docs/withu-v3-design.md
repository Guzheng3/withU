# withU v3 视觉精修层

> 在 v2 基础上做的一层“纸感玫瑰金”精修，不改业务结构，只统一色温、层级、留白与动效。
> 所有页面通过公共页脚自动加载，新增页面无需单独引入。

## 1. 设计方向

- **关键词**：晨雾玫瑰、香槟金、手账纸感、低饱和宝石色
- **气质**：温暖、安静、耐看；装饰退后，内容优先
- **原则**：
  1. 不改变任何业务布局与交互逻辑；
  2. 所有装饰性动效在 `prefers-reduced-motion` 下关闭；
  3. JS 失效时内容完整可见（揭示类动画均以 `wu3-js` 为前置条件）；
  4. 触控目标、键盘焦点、对比度保持 v2 的无障碍基线。

## 2. 文件结构

| 文件 | 作用 |
| --- | --- |
| `frontend/Style/css/withu-v3.css` | v3 视觉精修层，最后加载 |
| `frontend/assets/js/withu-v3.js` | 渐进增强：标题下划线、顶部栏状态、回到顶部进度环 |
| `frontend/inc/footer.php` | 统一挂载 v2 + v3 资源，并输出页脚签名 |

加载顺序：页面原有 CSS → `withu-v2.css` → `withu-v3.css`。

修改 v3 文件后请同步递增 `inc/footer.php` 中的 `?v=` 版本号。

## 3. 主要改动

### 画布
- 暖纸底 `#faf7f4`，叠加玫瑰 / 香槟 / 鼠尾草三层柔光；
- 保留点阵与颗粒，降低噪声强度；
- 缓慢流动渐变继续保留，周期放慢到 34s。

### 顶部与导航
- 顶部栏改为暖白玻璃 + 香槟金发丝线，滚动后加深阴影；
- 导航岛玻璃更细腻，激活指示器改为玫瑰金渐变并增加内高光；
- 移动端底部导航的黑色圆点改为玫瑰金指示器。

### 标题与分区
- 页面标题增加柔光背景与香槟金下划线；
- 分区标题改为低饱和宝石色，补充英文小标；
- 下划线通过 IntersectionObserver 绘制，尊重 reduced-motion。

### 卡片
- 清单卡从灰色石板改为玫瑰香槟渐变；
- 天气卡改为雾蓝 / 焦糖色，增加光环装饰与图标轻浮动；
- 智能卡、点滴、相册、留言、纪念日卡统一暖纸底、金线边框与悬停上浮；
- 单条留言真正居中（修复原先只居中半栏的问题）。

### 空状态与页脚
- 空状态提高对比度，首页空状态增加漂浮心形标记；
- 页脚增加 withU 签名、香槟金分隔线与更柔和的备案徽章。

### 登录页与后台
- 登录页在原有独立样式上补充氛围光、按钮光泽与移动端细节；
- 后台 `admin_v4.css` 追加 v4.1 精修段：侧栏激活态、统计卡、表格、表单、移动端 tabbar 与命令面板。

## 4. 动效清单

| 动效 | 触发 | 实现 | 备注 |
| --- | --- | --- | --- |
| 画布柔光流动 | 常驻 | CSS keyframes | 34s，reduced-motion 关闭 |
| 标题下划线绘制 | 进入视口 | IntersectionObserver + scaleX | 0.9–1s |
| 卡片悬停上浮 | 指针悬停 | CSS transform / shadow | 4–5px |
| 天气图标浮动 | 常驻 | CSS keyframes | 6.5s |
| 空状态心形浮动 | 常驻 | CSS keyframes | 5s |
| 按钮光泽扫过 | 悬停 | 伪元素 + transition | 0.65s |
| 回到顶部进度环 | 滚动 | CSS conic-gradient + JS 变量 | 0.08s 更新 |
| 跨页过渡 | 同源跳转 | View Transitions API | 不支持时自动降级 |

## 5. 维护注意

1. 不要把揭示动画直接写死在元素上，先以 `html.wu3-js` 为条件，确保 JS 失效可见；
2. 新增卡片若带 `[data-aos]`，v3 不会重复添加揭示，避免双重动画；
3. `#scrollTopBtn` 的 `::before` 已被进度环占用，不要再给该按钮添加同名伪元素；
4. 移动端底部导航高度仍为 56px + 安全区，指示器尺寸与 v5 模板保持一致；
5. 登录页与后台不在公共页脚作用域内，需分别维护。

## 6. 首页内容区配色回滚

根据用户反馈，`index.php` 的 `#pjax-container` 内容区保留 v3 的排版与动效，但配色已回滚到 v2：

- 作用域：`html:not(.withu-subpage) #pjax-container .withu-home`（仅首页内容区）
- 回滚内容：分区标题/图标/徽章颜色、倒计时数字与卡片、天气卡、清单卡、纪念日卡、点滴/相册/事件/留言卡、结尾卡、空状态、智能卡遮罩、更多按钮
- 未回滚：字号、字距、图标、间距、动效，以及顶部导航、页脚和其他页面
- 位置：`withu-v3.css` 第 14 节，便于后续单独调整或整体删除

## 7. 全站背景：观影界面渐变融合（v3.6）

- 作用域：`body.bg-pdot-vignette`（全部前台页面，v3.6 起由首页扩展到全站）
- 底色与柔光取自观影页 `backend/app/watch.php`：`#fff7fa` + 蓝 `#7ec8e3`、粉 `#f78da7`、绿 `#7fbf9d`
- 保留原点阵与颗粒层，密度 26px 不变，点色改为观影页墨色 `#3d3038`，颗粒透明度降至 0.24
- 流动周期 42s，移动端与 `prefers-reduced-motion` 下关闭
- 页脚：所有前台页面取消纯白底与全宽分隔线，让渐变与点阵穿透，仅保留 v3 居中的香槟金发丝线
- 位置：`withu-v3.css` 第 15 节，便于后续扩展或整体删除

### 版本与回退

| 标签 | 含义 |
| --- | --- |
| `home-bg-baseline-v3` | 首页背景实验前的基线（玫瑰 / 香槟 / 鼠尾草 + 点阵颗粒） |
| `home-bg-watch-fusion-v1` | 首页画布融合观影渐变的第一版 |
| `home-bg-watch-fusion-v2` | 让首页页脚背景透明并融入渐变 |
| `site-unify-watch-v1` | v3.6：背景扩展到全站，并统一空状态与卡片边缘 |
| `keep-bg-drop-feather-v1` | v3.7：保留全站背景与卡片边缘，撤回空状态羽化 |

回退到基线：`git checkout home-bg-baseline-v3 -- frontend/Style/css/withu-v3.css frontend/inc/footer.php`
或整体切换：`git switch main`（实验分支为 `feat/home-watch-gradient`）。

## 8. 空状态羽化已撤回（v3.7）

- 根据用户反馈，空状态不再使用羽化背景
- `withu-v3.css` 第 16 节的羽化规则已整段移除
- 空状态恢复 v3 原有的虚线卡片样式（首页沿用第 14 节的浅玫瑰虚线；子页为香槟金虚线）
- 全站观影渐变背景与第 17 节卡片边缘统一保持不变

### 版本与回退

| 标签 | 含义 |
| --- | --- |
| `lovelist-empty-soft-v1` | 无背景版本（已弃用） |
| `lovelist-empty-soft-v2` | 仅清单页：保留背景 + 22px 羽化（已弃用） |
| `site-unify-watch-v1` | v3.6：全站背景 + 卡片边缘 + 空状态 15px 羽化 |
| `keep-bg-drop-feather-v1` | v3.7：保留背景与卡片边缘，撤回空状态羽化（当前） |

如需重新取回羽化版本：
`git checkout site-unify-watch-v1 -- frontend/Style/css/withu-v3.css frontend/inc/footer.php`。

## 9. 全站卡片边缘统一（v3.6 起保留）

- 作用域：`html body.bg-pdot-vignette #pjax-container ...`（前台全部页面）
- 统一为 24px 圆角、1px 白色发丝边框、同一组柔和扩散阴影
- 悬停只加深阴影与发丝线亮度，不再切换到金色边框
- 覆盖：`.withu-card`、`.withu-article-card-base`、`.withu-time-line-card`、`.love-card`、`.MessageCard`、`.withu-detail-*`、`.withu-chat-device`，以及首页事件、点滴、相册、留言、倒计时、智能卡、天气、清单、纪念日卡片
- 首页卡片使用更高优先级覆盖第 14 节的回滚阴影
- 位置：`withu-v3.css` 第 17 节
