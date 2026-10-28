# 首页天气详情弹层

## 功能

- 点击首页任一天气卡（蓝色 / 橙色）打开手机天气应用风格的详情弹层。
- 内容：当前天气、未来 24 小时、未来 7 天、天气详情（体感、湿度、风力、能见度、气压、紫外线）。
- 字体统一为 `system-ui`，与天气卡一致。
- 交互：点击 / `Enter` / `Space` 打开；关闭按钮、遮罩、`Esc` 关闭；打开时锁定页面滚动并隐藏彩带画布与顶部进度条。
- 接口不可用时显示 `--` 与“暂无逐小时 / 每日预报数据”，不编造数据。

## 文件

| 文件 | 作用 |
| --- | --- |
| `frontend/Style/css/withu-home-style.css` | 天气卡字体改为 system-ui；增加可点击指针、focus 样式、长昵称/地名省略 |
| `frontend/Style/css/withu-weather-modal.css` | 弹层样式，仅 `index.php` 加载 |
| `frontend/index.php` | 引入弹层样式，升级资源版本 |
| `frontend/assets/js/page-index.js` | WeatherModule 增加卡片点击、键盘交互、弹层渲染与预报请求 |
| `frontend/services/weather.php` | 新增 `include=forecast`，按需附带 24 小时 / 7 天预报 |

## 数据请求

- 卡片原有的 5 分钟轮询不变，只请求当前天气。
- 仅在打开弹层时请求 `services/weather.php?...&include=forecast`，避免额外消耗和风额度。
- 预报复用服务端缓存：24 小时预报 30 分钟，7 天预报 60 分钟。

## 响应式

- 桌面端：弹层宽 `min(440px, 100vw - 40px)`，最大高度 `min(88vh, 820px)`，内容可滚动。
- `<= 640px`：全屏面板，使用 `100dvh`（`100vh` 回退），保留安全区内边距。
- `<= 360px`：天气详情改为单列，每日预报列宽收紧。
- 长地名、长昵称、超长日期均为省略号；小时预报横向滚动，不产生页面横向溢出。
- 已覆盖测试宽度：1440 / 1024 / 768 / 480 / 390 / 320。

## 版本与回退

| 标签 | 含义 |
| --- | --- |
| `weather-detail-baseline-v1` | 本次功能开发前的基线 |
| `weather-detail-modal-v1` | 天气卡片点击 + 手机风格详情弹层 |

回退：

```bash
git revert <commit>
# 或
git checkout weather-detail-baseline-v1 -- \
  frontend/Style/css/withu-home-style.css \
  frontend/Style/css/withu-weather-modal.css \
  frontend/index.php \
  frontend/assets/js/page-index.js \
  frontend/services/weather.php
```
