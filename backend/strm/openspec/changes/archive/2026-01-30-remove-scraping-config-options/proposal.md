# remove-scraping-config-options

## Why

简化刮削配置项，将原本的可配置选项（生成NFO、下载海报、下载背景、覆盖已存在）移除，改为固定行为。这些配置项在实际使用中很少需要调整，保持默认值即可满足绝大多数场景，移除后可降低用户认知负担和代码复杂度。

## What Changes

- 移除「生成NFO文件」配置项 → 始终生成NFO
- 移除「下载海报图片」配置项 → 始终下载海报
- 移除「下载背景图片」配置项 → 始终下载背景图
- 移除「覆盖已存在的刮削内容」配置项 → 始终覆盖已存在文件

## Capabilities

### Modified Capabilities
- `media-scraping`: 移除刮削配置项的可配置性，改为固定行为

## Impact

- 后端：`SystemConfigService`、`MediaScrapingService`、`CoverImageService`、`NfoGeneratorService`、`MediaScrapingHandler`
- 前端：`settings.vue` 移除4个checkbox UI
- 配置文件：不再保存相关配置项
