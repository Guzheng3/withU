# remove-scraping-config-options Design

## Context

当前系统提供4个刮削相关的配置选项：
- `generateNfo`: 是否生成NFO文件
- `downloadPoster`: 是否下载海报图片
- `downloadBackdrop`: 是否下载背景图片
- `overwriteExisting`: 是否覆盖已存在的刮削内容

这些配置项的默认值已能满足绝大多数用户需求，且很少有用户会修改这些配置。保留可配置性增加了代码复杂度但收益有限。

## Goals / Non-Goals

**Goals:**
- 移除4个不常使用的刮削配置项
- 简化配置界面，降低用户认知负担
- 移除相关代码逻辑，降低维护成本

**Non-Goals:**
- 不修改刮削的核心逻辑（搜索、匹配、信息获取）
- 不影响其他配置项（如 `enabled`、`keepSubtitleFiles`、`useExistingScrapingInfo`）

## Decisions

### 1. 固定行为而非移除功能

将配置项改为固定行为，代码中直接执行相关操作而非读取配置判断。

**变更方式：**
```java
// 之前
boolean generateNfo = (Boolean) scrapingConfig.getOrDefault("generateNfo", true);
if (generateNfo) {
    // 生成NFO
}

// 之后
// 直接执行
nfoGeneratorService.generateMovieNfo(movieDetail, mediaInfo, nfoFilePath);
```

### 2. 保留配置结构但移除相关字段

`SystemConfigService.getDefaultConfig()` 中不再定义这4个配置项，但仍保留 `scraping` 配置对象结构。

### 3. 前端移除对应UI

`settings.vue` 中移除4个checkbox，并移除保存逻辑中对应字段的发送。

## Risks / Trade-offs

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| 少数用户可能有特殊需求 | 无法自定义这些行为 | 默认行为已覆盖99%场景 |
| 已有配置的用户升级后配置文件中残留字段 | 无影响 | 读取时使用 `getOrDefault`，旧配置不影响功能 |
| 背景图下载增加存储空间 | 增加存储占用 | 背景图是重要视觉元素，默认开启更合理 |

## Files to Modify

| 文件 | 修改内容 |
|------|----------|
| `SystemConfigService.java` | 移除4个配置项定义 |
| `MediaScrapingService.java` | 移除配置读取，改为直接执行 |
| `CoverImageService.java` | 移除配置读取，改为直接执行 |
| `NfoGeneratorService.java` | 移除 overwriteExisting 判断 |
| `MediaScrapingHandler.java` | 移除 generateNfo 配置使用 |
| `settings.vue` | 移除4个checkbox UI |
