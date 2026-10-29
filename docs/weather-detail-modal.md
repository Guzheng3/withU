# 首页天气详情弹层

## 功能

- 点击首页任一天气卡（蓝色 / 橙色）打开浮动的天气详情卡。
- 当前天气：温度、图标、天气描述、最高/最低温、更新时间。
- 逐小时预报：未来 24 小时，带平滑温度曲线，可横向滑动。
- 未来 7 天：每日天气、图标、最高/最低温，附每日温度区间条。
- 过去 7 天：和风「时光机」历史天气，最新一天在前（昨天 / 前天 / N 天前）。
- 天气详情：体感、湿度、风力、能见度、气压、紫外线、日出、日落和云量。
- 顶部关闭按钮，底部「完成」按钮；支持点击遮罩、`Esc` 关闭。
- 动效：弹层入场、当前天气图标轻浮动、小时温度曲线绘制、条目依次浮现；`prefers-reduced-motion` 下全部关闭。

## 视觉方向

- 参考博客/网站里的天气弹窗和 OPPO 天气的常见结构：
  - 浅色纸感浮动卡片，而不是整屏高饱和渐变；
  - 顶部柔和天气渐变 hero（蓝 / 橙两套），大字温度与天气图标；
  - 白色内容卡片、细边框和轻阴影；
  - 24 小时温度曲线；
  - 7 天温度区间条；
  - 日出 / 日落、云量等详情。
- 云图已按反馈移除，不再渲染 Canvas 或空的云图占位。

## 文件

| 文件 | 作用 |
| --- | --- |
| `frontend/Style/css/withu-home-style.css` | 天气卡字体、可点击指针、focus 样式、长昵称/地名换行 |
| `frontend/Style/css/withu-weather-modal.css` | 浅色浮动天气卡、渐变 hero、小时曲线、每日区间条、动效 |
| `frontend/index.php` | 引入弹层样式，升级资源版本 |
| `frontend/assets/js/page-index.js` | WeatherModule：打开/关闭、预报与历史请求、小时曲线、每日区间条 |
| `frontend/services/weather.php` | 按需返回 `forecast` 与 `history` |
| `backend/app/core/Weather.php` | `withu_weather_historical()`，封装和风时光机 |

## 数据请求

- 天气卡 5 分钟轮询保持原样，只请求当前天气。
- 打开弹层后并行发起两个请求：
  - `services/weather.php?...&include=forecast`：当前天气 + 24 小时 + 7 天预报。
  - `services/weather.php?...&include=history`：过去 7 天历史天气。
- 历史天气按日期逐日请求和风时光机，单日缓存 24 小时；遇到不可用立即停止后续请求，前端 12 秒超时后显示「暂无历史天气数据」。
- 预报复用服务端缓存：24 小时 30 分钟，7 天 60 分钟。

## 响应式

- 桌面端：浮动卡片宽 `min(420px, 100vw - 32px)`，最大高度 `min(88vh, 780px)`，内容可滚动。
- `<= 640px`：卡片保留 10px 外边距，宽 `100vw - 20px`，最大高度 `100dvh - 20px`，圆角缩小。
- `<= 360px`：天气详情单列，每日预报列宽收紧。
- 长地名、长昵称、超长日期均为省略号或自然换行；小时预报横向滚动。
- 已覆盖测试宽度：1440 / 1024 / 768 / 641 / 390 / 320，均无横向溢出。

## 已知限制

- 历史天气依赖和风「时光机」接口；如果当前账号/套餐未开通，弹层会显示「暂无历史天气数据」，不影响当前、逐小时和未来预报。
- 云图功能已移除，不再请求或渲染云图。

## 版本与回退

| 标签 | 含义 |
| --- | --- |
| `weather-detail-baseline-v1` | 天气详情功能开发前基线 |
| `weather-detail-modal-v1` | 首版天气卡点击 + 当前/逐小时/7 天详情 |
| `weather-modal-extended-v1` | 增加过去 7 天、云图与动效 |
| `weather-modal-oppo-v1` | 参考 OPPO 天气：小时曲线、7 天区间条 |
| `weather-modal-light-v1` | 移除云图，改为浅色浮动卡片与柔和渐变头部 |

回退：

```bash
git revert <commit>
# 或
git checkout weather-modal-light-baseline-v1 -- \
  frontend/Style/css/withu-home-style.css \
  frontend/Style/css/withu-weather-modal.css \
  frontend/index.php \
  frontend/assets/js/page-index.js \
  frontend/services/weather.php \
  backend/app/core/Weather.php
```
