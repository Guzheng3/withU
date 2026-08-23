# 文件处理管道优化提案

## Why

当前代码架构存在职责混乱和重复下载等问题。`TaskExecutionService` 承担了文件发现、过滤、STRM生成、刮削协调和清理等多个职责，`MediaScrapingService.copyRelatedFiles()` 方法没有文件存在性检查导致字幕文件重复下载。这些问题导致代码难以维护、性能低下且容易出错。

现在正是重构的最佳时机，因为：
1. 问题已经通过日志和用户反馈得到确认
2. 已有详细的分析文档 (`openlist-file-tree-traversal-analysis.md` 和 `subtitle-duplicate-download-single-task.md`)
3. 系统处于相对稳定状态，可以在不破坏功能的情况下进行重构

## What Changes

### 架构优化
1. **引入责任链模式 (Chain of Responsibility)** 拆分文件处理管道：
   - `FileDiscoveryHandler` - 递归发现目录中的文件
   - `FileFilterHandler` - 过滤视频文件和非视频文件
   - `StrmGenerationHandler` - 生成STRM文件
   - `NfoDownloadHandler` - 下载NFO文件（支持本地 > OpenList > 刮削优先级）
   - `ImageDownloadHandler` - 下载封面/背景图（支持本地 > OpenList > 刮削优先级）
   - `SubtitleCopyHandler` - 复制字幕文件（支持本地 > OpenList > 刮削优先级）
   - `MediaScrapingHandler` - 执行媒体刮削（当OpenList文件不存在时的fallback）
   - `OrphanCleanupHandler` - 清理孤立文件

2. **修复字幕/NFO/图片重复下载问题**：
   - 统一文件保留优先级：**本地文件 > OpenList文件 > 刮削文件**
   - 每个Handler独立处理各自的文件类型
   - 添加文件存在性检查，避免重复下载
   - 配置联动规则：
     - 「优先使用已存在的刮削信息」→ 启用NFO/图片的OpenList下载和刮削fallback
     - 「保留字幕文件」→ 启用字幕的OpenList下载fallback
     - 配置不启用时，仅使用本地文件

3. **消除重复代码**：
   - 统一 `executeTaskLogic()` 和 `processVideoFile()` 中的STRM生成逻辑
   - 统一文件优先级检查逻辑

4. **解耦文件发现与文件处理**：
   - 文件发现阶段只收集文件元数据
   - 文件处理阶段独立执行

### 文件优先级规则
| 文件类型 | 优先级（从高到低） | 说明 |
|---------|------------------|------|
| 本地文件 | 1 | 目标目录已存在对应文件时，直接使用本地文件 |
| OpenList文件 | 2 | 本地文件不存在时，从OpenList同级目录下载 |
| 刮削文件 | 3 | 本地和OpenList都不存在时，从TMDB等API刮削（最后手段） |

**优先级判断逻辑**（以NFO文件为例）：
1. 检查本地是否存在 `movie.nfo`（参考当前代码 `isAlreadyScraped()` 的检查逻辑）
2. 若本地不存在，从OpenList同级目录查找并下载同名NFO文件
3. 若OpenList也不存在，执行TMDB刮削生成NFO文件

**配置联动规则**：
- 「优先使用已存在的刮削信息」= 本地检查通过时直接使用本地文件
- 「保留字幕文件」= 本地检查通过时直接使用本地字幕文件
- 两个配置项控制是否启用第2、3步的fallback逻辑

## Capabilities

### New Capabilities
- `file-processing-chain`: 定义责任链处理器接口和通用行为规范
- `file-discovery`: 递归目录遍历和文件发现能力
- `file-filtering`: 根据文件类型和扩展名过滤能力
- `nfo-file-management`: NFO文件下载和管理，支持三级优先级
- `image-file-management`: 图片文件下载和管理，支持三级优先级
- `subtitle-file-management`: 字幕文件下载和管理，支持三级优先级
- `media-scraping`: 媒体刮削执行能力（作为优先级最低的fallback）
- `orphan-file-cleanup`: 清理孤立文件能力

### Modified Capabilities
- 无（现有规格的行为不变，只是内部实现重构）

## Impact

### 受影响的代码
- `TaskExecutionService.java` - 主要重构对象，拆分为多个Handler
- `MediaScrapingService.java` - 拆分各文件类型的处理逻辑
- `StrmFileService.java` - 可能需要调整以支持Handler模式

### 新增代码
- `handler/` 包 - 存放责任链处理器
  - `FileProcessorHandler.java` (接口)
  - `FileDiscoveryHandler.java`
  - `FileFilterHandler.java`
  - `StrmGenerationHandler.java`
  - `NfoDownloadHandler.java`
  - `ImageDownloadHandler.java`
  - `SubtitleCopyHandler.java`
  - `MediaScrapingHandler.java`
  - `OrphanCleanupHandler.java`
- `FileProcessingContext.java` - 处理器共享上下文
- `FileProcessorChain.java` - 责任链执行器
- `FilePriorityResolver.java` - 文件优先级解析器

### 外部接口
- 无外部API变更
- 内部行为保持向后兼容

### 依赖
- 无新增外部依赖
