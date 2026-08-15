## 上下文

### 当前状态

在任务执行流程中，`FileProcessorChain` 按顺序执行各个 Handler，但存在以下问题：

1. **处理器类型匹配问题**：
   - `FileProcessorChain.supports()` 方法会根据当前文件类型匹配处理器
   - 处理视频文件时，只调用 `getHandledTypes()` 包含 `VIDEO` 的处理器
   - `SubtitleCopyHandler` (Order 42) 只返回 `SUBTITLE` 类型
   - `ImageDownloadHandler` (Order 41) 只返回 `IMAGE` 类型
   - 结果：这两个处理器在处理视频文件时被跳过

2. **图片下载逻辑限制**：
   - `ImageDownloadHandler` 只查找特定命名的图片文件
   - 期望：`{baseFileName}-poster.jpg`、`{baseFileName}-fanart.jpg`、`{baseFileName}-thumb.jpg`
   - 实际：用户使用任意命名的图片文件（如 `FomalhautABC.jpeg`）

3. **配置依赖问题**：
   - `SubtitleCopyHandler` 依赖 `scrapingConfig.keepSubtitleFiles` 配置
   - 配置通过 Map 传递，可能存在嵌套层级问题
   - 默认值为 `false`，导致字幕下载被禁用

### 约束条件

- 需要保持现有 Handler 链的执行顺序
- 不能破坏其他 Handler 的正常功能
- 修改应尽量小而集中
- 需要兼容现有的任务配置和文件结构

## 目标 / 非目标

**目标**：
- 修复字幕文件下载问题，在处理视频文件时自动下载同目录的字幕
- 修复图片文件下载问题，支持下载任意命名的本地图片
- 修复配置读取问题，直接从 context.attributes 获取配置值
- 添加 URL 编码支持，解决中文路径下载问题

**非目标**：
- 不改变 Handler 链的整体架构
- 不修改 NFO 文件的下载逻辑
- 不引入新的外部依赖
- 不改变现有的 API 接口或数据库结构

## 设计决策

### 决策 1：处理器匹配逻辑

**方案 A**：修改 `FileProcessorChain.supports()`，让所有处理器都能处理视频文件
- 优点：改动最小，所有处理器都能响应视频文件
- 缺点：可能触发不需要的处理器

**方案 B**：修改 `SubtitleCopyHandler` 和 `ImageDownloadHandler` 的 `getHandledTypes()`，添加 `VIDEO`
- 优点：只修改特定处理器，保持其他处理器逻辑不变
- 缺点：需要修改多个处理器

**最终选择**：方案 B
- 修改 `SubtitleCopyHandler.getHandledTypes()` 返回 `Set.of(FileType.SUBTITLE, FileType.VIDEO)`
- 修改 `ImageDownloadHandler.getHandledTypes()` 返回 `Set.of(FileType.IMAGE, FileType.VIDEO)`
- 这样这两个处理器既能处理对应类型文件，也能在处理视频文件时被调用

### 决策 2：图片下载逻辑

**方案 A**：修改 `ImageDownloadHandler`，查找同目录下所有图片文件
- 优点：简单直接，下载所有图片
- 缺点：可能下载不需要的图片

**方案 B**：保留现有逻辑，优先查找特定命名格式，其次查找任意图片
- 优点：向后兼容，优先使用规范命名的图片
- 缺点：逻辑稍复杂

**最终选择**：方案 B
- 优先查找 `{baseFileName}-poster.jpg` 等特定格式
- 如果不存在，降级下载同目录下任意图片文件
- 保留原文件名，不强制重命名为特定格式

### 决策 3：配置读取方式

**方案 A**：修复 `SystemConfigService.saveSystemConfig` 的 Map 合并逻辑
- 使用递归方式合并嵌套 Map

**方案 B**：直接从 `FileProcessingContext.attributes` 获取配置值
- 绕过配置传递问题
- 更直接可靠

**最终选择**：方案 B
- `SubtitleCopyHandler` 使用 `context.getAttribute("keepSubtitleFiles")`
- `ImageDownloadHandler` 使用 `context.getAttribute("useExistingScrapingInfo")`

### 决策 4：URL 编码

**方案**：使用 `UrlEncoder.encodeUrlSmart()` 方法
- 智能编码：仅对 URL 路径部分编码，保留协议和主机
- 处理中文、空格等特殊字符

## 风险与权衡

| 风险 | 可能性 | 影响 | 缓解措施 |
|------|--------|------|----------|
| 下载过多不需要的图片 | 中 | 低 | 优先查找特定命名格式，再降级到任意图片 |
| 字幕下载与预期不符 | 低 | 中 | 默认启用，添加配置验证日志 |
| 回归问题 | 低 | 中 | 审查修改，确保不影响其他处理器 |

## 处理器执行顺序

| Order | 处理器 | 功能 |
|-------|--------|------|
| 10 | FileDiscoveryHandler | 文件发现 |
| 20 | FileFilterHandler | 文件过滤 |
| 30 | StrmGenerationHandler | STRM 文件生成 |
| 40 | NfoDownloadHandler | NFO 文件下载（优先级：本地 > OpenList > 刮削） |
| 41 | ImageDownloadHandler | 图片文件下载（海报、背景图、缩略图） |
| 42 | SubtitleCopyHandler | 字幕文件复制 |
| 50 | MediaScrapingHandler | 媒体刮削 |
| 60 | OrphanCleanupHandler | 孤立文件清理 |

## 部署与回滚

### 部署步骤
1. 提交代码修改到 feature 分支
2. 构建 Docker 镜像并测试
3. 在测试环境验证修复效果
4. 合并到主分支并发布

### 回滚策略
- 如果发现问题，可以通过 Docker 镜像回滚到 previous 版本
- 数据库无需修改，回滚不影响数据

## 待解决问题

1. 是否需要限制下载的图片文件数量（如只下载 1 张海报、1 张背景图）？
2. 是否需要支持配置排除特定扩展名的图片文件？
