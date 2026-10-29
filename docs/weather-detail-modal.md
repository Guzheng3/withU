# 首页天气详情弹层

## 功能

- 点击首页任一天气卡（蓝色 / 橙色）打开手机天气应用风格的详情弹层。
- 当前天气：温度、图标、天气描述、最高/最低温、更新时间。
- 云图：按当前云量绘制的动态云图，含漂移云层、雨雪粒子、位置脉冲和地图网格；晴空时显示柔和日光。
- 逐小时预报：未来 24 小时，可横向滑动。
- 未来 7 天：每日天气、图标、最高/最低温。
- 过去 7 天：和风「时光机」历史天气，最新一天在前（昨天 / 前天 / N 天前）。
- 天气详情：体感、湿度、风力、能见度、气压、紫外线。
- 弹层顶部有关闭按钮，底部有「完成」按钮；支持点击遮罩、`Esc` 关闭。
- 动效：弹层入场、云图持续动画、当前天气图标轻浮动、逐小时 / 每日条目依次浮现；`prefers-reduced-motion` 下全部关闭，云图静止。

## 文件

| 文件 | 作用 |
| --- | --- |
| `frontend/Style/css/withu-home-style.css` | 天气卡字体、可点击指针、focus 样式、长昵称/地名换行 |
| `frontend/Style/css/withu-weather-modal.css` | 弹层、云图、历史/未来列表、动效 |
| `frontend/index.php` | 引入弹层样式，升级资源版本 |
| `frontend/assets/js/page-index.js` | WeatherModule：打开/关闭、预报与历史请求、云图 Canvas |
| `frontend/services/weather.php` | 按需返回 `forecast` 与 `history` |
| `backend/app/core/Weather.php` | 新增 `withu_weather_historical()`，封装和风时光机 |
| `backend/app/core/Weather.php` | 历史天气接口（最近 10 天，不含今天） |

## 数据请求

- 天气卡 5 分钟轮询保持原样，只请求当前天气。
- 打开弹层后并行发起两个请求：
  - `services/weather.php?...&include=forecast`：当前天气 + 24 小时 + 7 天预报。
  - `services/weather.php?...&include=history`：过去 7 天历史天气。
- 历史天气按日期逐日请求和风时光机，单日缓存 24 小时；遇到不可用立即停止后续请求，前端 12 秒超时后显示「暂无历史天气数据」。
- 预报复用服务端缓存：24 小时 30 分钟，7 天 60 分钟。

## 云图说明

- 云图是**云量示意**，不是卫星云图：根据当前云量百分比驱动云层数量与浓度，按天气描述叠加雨/雪粒子。
- 底部文案已标注「云图为云量示意」。
- 如需真实卫星云图或雷达回波，需要另行接入第三方图层（如 RainViewer / Windy / 卫星瓦片服务）。

## 响应式

- 桌面端：弹层宽 `min(440px, 100vw - 40px)`，最大高度 `min(88vh, 820px)`，内容可滚动。
- `<= 640px`：全屏面板，`100dvh`（`100vh` 回退），保留安全区内边距。
- `<= 360px`：天气详情单列，每日预报列宽收紧。
- 长地名、长昵称、超长日期均为省略号或自然换行；小时预报横向滚动。
- 已覆盖测试宽度：1440 / 1024 / 768 / 641 / 390 / 320，均无横向溢出。

## 已知限制

- 历史天气依赖和风「时光机」接口；如果当前账号/套餐未开通，弹层会显示「暂无历史天气数据」，不影响当前、逐小时和未来预报。
- 云图为动态示意图，不是卫星影像。

## 版本与回退

| 标签 | 含义 |
| --- | --- |
| `weather-detail-baseline-v1` | 天气详情功能开发前基线 |
| `weather-detail-modal-v1` | 首版天气卡点击 + 当前/逐小时/7 天详情 |
| `weather-modal-extended-v1` | 增加过去 7 天、云图与动效 |

回退：

```bash
git revert <commit>
# 或
git checkout weather-detail-baseline-v1 -- \
  frontend/Style/css/withu-home-style.css \
  frontend/Style/css/withu-weather-modal.css \
  frontend/index.php \
  frontend/assets/js/page-index.js \
  frontend/services/weather.php \
  backend/app/core/Weather.php
```
