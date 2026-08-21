# 文件处理管道优化 - 任务清单

## 1. 基础设施创建

### 1.1 核心接口和枚举定义

- [x] 1.1.1 创建 `FileProcessorHandler` 接口，定义 `process()`、`getOrder()`、`getHandledTypes()` 方法
- [x] 1.1.2 创建 `ProcessingResult` 枚举（SUCCESS、SKIPPED、FAILED、FALLBACK）
- [x] 1.1.3 创建 `FileType` 枚举（VIDEO、NFO、IMAGE、SUBTITLE、ALL）
- [x] 1.1.4 创建 `Priority` 枚举（LOCAL、OPENLIST、SCRAPING、SKIPPED）

### 1.2 上下文和工具类定义

- [x] 1.2.1 创建 `FileProcessingContext` 类，包含 openlistConfig、taskConfig、currentFile、relativePath、saveDirectory、directoryFiles 等属性
- [x] 1.2.2 创建 `FileProcessingContext.ProcessingStats` 内部类，用于统计处理结果
- [x] 1.2.3 创建 `PriorityResult` 类，封装优先级判断结果
- [x] 1.2.4 创建 `FilePriorityResolver` 组件，实现三级优先级判断逻辑

### 1.3 执行器创建

- [x] 1.3.1 创建 `FileProcessorChain` 类，实现 Handler 排序和链式执行
- [x] 1.3.2 实现 `supports()` 方法，判断 Handler 是否支持当前文件类型
- [x] 1.3.3 实现 `execute()` 方法，按顺序执行所有 Handler

## 2. 核心 Handler 实现

### 2.1 FileDiscoveryHandler

- [x] 2.1.1 创建 `FileDiscoveryHandler` 类，实现 `FileProcessorHandler` 接口
- [x] 2.1.2 实现递归目录遍历逻辑，从任务配置的 path 开始
- [x] 2.1.3 调用 OpenList API 获取目录内容
- [x] 2.1.4 实现 `OpenlistFile` 模型的创建和填充
- [x] 2.1.5 实现内存优化策略，分批处理目录
- [x] 2.1.6 实现降级处理，分批处理失败时使用全量加载

### 2.2 FileFilterHandler

- [x] 2.2.1 创建 `FileFilterHandler` 类
- [x] 2.2.2 实现 `isVideoFile()` 方法，支持常见视频格式识别
- [x] 2.2.3 实现视频文件过滤逻辑
- [x] 2.2.4 实现字幕文件识别（.srt、.ass、.vtt、.ssa、.sub、.idx）
- [x] 2.2.5 实现图片文件识别（.jpg、.png、.webp、.bmp 等）

### 2.3 StrmGenerationHandler

- [x] 2.3.1 创建 `StrmGenerationHandler` 类
- [x] 2.3.2 实现文件名处理逻辑（应用重命名规则、添加 .strm 扩展名）
- [x] 2.3.3 实现 STRM 文件路径构建
- [x] 2.3.4 实现 URL 处理（baseUrl 替换、sign 参数添加、URL 编码）
- [x] 2.3.5 实现文件存在性检查和内容比较（增量模式优化）
- [x] 2.3.6 实现 STRM 文件写入

## 3. 文件下载 Handler 实现

### 3.1 NfoDownloadHandler

- [x] 3.1.1 创建 `NfoDownloadHandler` 类
- [x] 3.1.2 实现本地 NFO 文件存在性检查（参考 `isAlreadyScraped()` 逻辑）
- [x] 3.1.3 实现优先级判断逻辑（本地 → OpenList → 刮削）
- [x] 3.1.4 实现从 OpenList 下载 NFO 文件
- [x] 3.1.5 集成 MediaScrapingService 作为 Fallback
- [x] 3.1.6 实现配置联动（`useExistingScrapingInfo`）

### 3.2 ImageDownloadHandler

- [x] 3.2.1 创建 `ImageDownloadHandler` 类
- [x] 3.2.2 实现本地图片文件存在性检查（poster、fanart、thumb）
- [x] 3.2.3 实现优先级判断逻辑
- [x] 3.2.4 实现从 OpenList 下载图片文件
- [x] 3.2.5 集成 TMDB 图片下载作为 Fallback
- [x] 3.2.6 实现电视剧共用图片处理（poster.jpg、fanart.jpg）

### 3.3 SubtitleCopyHandler

- [x] 3.3.1 创建 `SubtitleCopyHandler` 类
- [x] 3.3.2 实现本地字幕文件存在性检查
- [x] 3.3.3 实现优先级判断逻辑（本地 → OpenList，无刮削）
- [x] 3.3.4 实现从 OpenList 下载字幕文件
- [x] 3.3.5 实现防止重复下载逻辑（已下载文件列表）
- [x] 3.3.6 实现配置联动（`keepSubtitleFiles`）

## 4. 辅助 Handler 实现

### 4.1 MediaScrapingHandler

- [x] 4.1.1 创建 `MediaScrapingHandler` 类
- [x] 4.1.2 实现 TMDB API 搜索功能
- [x] 4.1.3 实现电影信息获取和刮削
- [x] 4.1.4 实现电视剧信息获取和刮削
- [x] 4.1.5 实现 NFO 文件生成
- [x] 4.1.6 实现图片下载（海报、背景图）
- [x] 4.1.7 实现置信度检查和 AI 识别集成

### 4.2 OrphanCleanupHandler

- [x] 4.2.1 创建 `OrphanCleanupHandler` 类
- [x] 4.2.2 实现深度优先遍历 STRM 目录
- [x] 4.2.3 实现孤立文件识别逻辑
- [x] 4.2.4 实现重命名规则反向匹配
- [x] 4.2.5 实现孤立文件删除
- [x] 4.2.6 实现关联刮削文件清理（NFO、图片、字幕）
- [x] 4.2.7 实现空目录清理
- [x] 4.2.8 实现清理统计报告

## 5. 集成和配置

### 5.1 TaskExecutionService 集成

- [x] 5.1.1 创建 `FileProcessorChain` 实例并注册所有 Handler
- [x] 5.1.2 实现从原有逻辑到新处理链的迁移
- [x] 5.1.3 保留原有 `executeTaskLogic()` 方法作为 Fallback
- [x] 5.1.4 添加特性开关，支持配置切换新旧实现

### 5.2 配置整合

- [x] 5.2.1 整合刮削配置（`scrapingConfig`）
- [x] 5.2.2 整合 TMDB 配置（`tmdbConfig`）
- [x] 5.2.3 整合 AI 配置（`aiConfig`）
- [x] 5.2.4 实现配置热更新支持

### 5.3 日志和监控

- [x] 5.3.1 添加处理流程 DEBUG 日志
- [x] 5.3.2 添加处理统计 INFO 日志
- [x] 5.3.3 添加错误 WARN 日志
- [x] 5.3.4 实现处理进度监控

## 6. 测试

### 6.1 单元测试

- [x] 6.1.1 测试 `FileProcessorHandler` 接口实现
- [x] 6.1.2 测试 `FilePriorityResolver` 优先级判断
- [x] 6.1.3 测试各 Handler 的 `process()` 方法
- [x] 6.1.4 测试 `FileProcessorChain` 执行逻辑

### 6.2 集成测试

- [x] 6.2.1 测试完整处理流程
- [x] 6.2.2 测试优先级判断流程
- [x] 6.2.3 测试配置联动逻辑
- [x] 6.2.4 测试增量模式处理

### 6.3 场景测试

- [x] 6.3.1 测试单视频文件处理
- [x] 6.3.2 测试多视频文件共享字幕
- [x] 6.3.3 测试电视剧目录刮削
- [x] 6.3.4 测试孤立文件清理

## 7. 文档和发布

### 7.1 文档更新

- [x] 7.1.1 更新 CLAUDE.md 添加新架构说明
- [x] 7.1.2 更新架构文档
- [x] 7.1.3 添加 Handler 开发指南

### 7.2 代码清理

- [x] 7.2.1 删除重复的 STRM 生成代码
- [x] 7.2.2 删除重复的文件过滤代码
- [x] 7.2.3 清理无用的 import 和依赖
- [x] 7.2.4 重构相关单元测试

### 7.3 发布准备

- [x] 7.3.1 更新版本号
- [x] 7.3.2 更新 CHANGELOG
- [x] 7.3.3 创建迁移指南文档
