## 问题背景

在任务执行过程中，视频目录中的字幕文件和图片文件没有被下载。根本原因是：

1. **处理器类型匹配问题**：`FileProcessorChain` 在处理视频文件时，只调用 `getHandledTypes()` 包含 `VIDEO` 的处理器
   - `SubtitleCopyHandler` (Order 42) 的 `getHandledTypes()` 返回 `SUBTITLE`
   - `ImageDownloadHandler` (Order 41) 的 `getHandledTypes()` 返回 `IMAGE`
   - 结果：这两个处理器在处理视频文件时被跳过

2. **配置读取问题**：`SubtitleCopyHandler` 依赖 `scrapingConfig.keepSubtitleFiles` 配置项，但该配置可能未被正确传递

3. **图片下载逻辑限制**：`ImageDownloadHandler` 只查找特定命名的图片文件，无法匹配任意命名的图片文件

## 解决方案

- 修改 `SubtitleCopyHandler.getHandledTypes()` 返回 `Set.of(FileType.SUBTITLE, FileType.VIDEO)`
- 修改 `ImageDownloadHandler.getHandledTypes()` 返回 `Set.of(FileType.IMAGE, FileType.VIDEO)`
- 修改配置读取方式，直接从 `FileProcessingContext.attributes` 中获取配置值
- 修改 `ImageDownloadHandler`，支持下载目录中任意命名的图片文件
- 添加 URL 编码支持，解决中文路径下载问题

## 新增功能

- **字幕下载**：处理视频文件时，自动下载同目录的字幕文件（.srt, .ass, .vtt 等）
- **任意图片下载**：处理视频文件时，自动下载同目录的任意图片文件
- **智能 URL 编码**：支持中文路径和特殊字符的 URL 编码

## 修改文件

- `FileProcessorChain.java`: 验证 supports() 方法的处理器匹配逻辑
- `SubtitleCopyHandler.java`: 添加 VIDEO 类型支持，修复配置读取
- `ImageDownloadHandler.java`: 添加 VIDEO 类型支持，支持任意命名图片
- `OpenlistApiService.java`: 添加 downloadWithEncodedUrl() 方法
- `SystemConfigService.java`: 修复嵌套 Map 配置合并

## 影响范围

- **字幕文件下载**：处理视频文件时自动下载同目录的字幕
- **图片文件下载**：处理视频文件时自动下载同目录的图片文件
- **配置依赖**：移除对 scrapingConfig 的依赖，直接从 context.attributes 读取
