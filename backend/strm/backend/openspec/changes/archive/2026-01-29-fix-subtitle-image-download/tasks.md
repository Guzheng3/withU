# 任务清单：修复字幕和图片下载问题

## 变更信息
- **变更ID**: fix-subtitle-image-download
- **创建时间**: 2026-01-29
- **负责人**: claude-code

## 任务列表

### 1. 配置保存问题修复
- [x] 1.1 修复 `SystemConfigService.saveSystemConfig` 方法，使用 deepMerge 正确合并嵌套 Map 配置
- [x] 1.2 确保 `keepSubtitleFiles` 配置值正确保存到配置文件
- [x] 1.3 添加配置保存日志，验证配置值是否正确写入

### 2. SubtitleCopyHandler 修改
- [x] 2.1 修改 `getHandledTypes()` 方法，添加 `FileType.VIDEO` 支持
- [x] 2.2 修复配置读取方式，直接从 `context.getAttribute("keepSubtitleFiles")` 获取配置值
- [x] 2.3 添加 URL 编码支持，使用 `UrlEncoder.encodeUrlSmart()` 解决中文路径下载问题
- [x] 2.4 添加调试日志追踪 `context.attributes` 的完整内容

### 3. ImageDownloadHandler 修改
- [x] 3.1 修改 `getHandledTypes()` 方法，添加 `FileType.VIDEO` 支持
- [x] 3.2 修复配置读取方式，直接从 `context.getAttribute("useExistingScrapingInfo")` 获取配置值
- [x] 3.3 实现 `processArbitraryImages()` 方法，支持下载任意命名的图片文件
- [x] 3.4 实现优先级逻辑：优先查找 `{baseFileName}-poster.jpg` 等特定格式
- [x] 3.5 降级逻辑：若特定格式不存在，下载同目录下任意图片文件
- [x] 3.6 保留原文件名，不强制重命名为特定格式
- [x] 3.7 添加 URL 编码支持，解决中文路径下载问题

### 4. OpenlistApiService 修改
- [x] 4.1 添加 `downloadWithEncodedUrl()` 公共方法，支持使用预编码 URL 下载文件
- [x] 4.2 修复 `downloadFileWithUrl` 方法中的参数名引用

### 5. FileProcessorChain 验证
- [x] 5.1 确认 `supports()` 方法正确匹配处理器的 `getHandledTypes()`
- [x] 5.2 验证视频文件处理时，`SubtitleCopyHandler` 和 `ImageDownloadHandler` 都会被调用

### 6. 测试验证
- [x] 6.1 构建 Docker 镜像并编译验证
- [x] 6.2 测试配置保存：确认 `keepSubtitleFiles` 值正确保存
- [x] 6.3 测试字幕下载：处理视频文件时，字幕文件被正确下载
- [x] 6.4 测试特定命名的图片下载：`{baseFileName}-poster.jpg` 等文件被正确下载
- [x] 6.5 测试任意命名的图片下载：如 `FomalhautABC.jpeg` 被正确下载
- [x] 6.6 测试中文路径下载：包含中文和空格的路径能够正确下载
- [x] 6.7 移除调试日志和调试代码

### 7. 文档更新
- [x] 7.1 更新 CLAUDE.md（中文）
- [x] 7.2 更新 openlist-file-tree-traversal-analysis.md
- [x] 7.3 更新变更工件文档（proposal.md, design.md, tasks.md）

### 8. 构建与部署
- [ ] 8.1 合并到主分支并发布

## 完成统计

| 状态 | 数量 |
|------|------|
| 已完成 | 26 |
| 进行中 | 0 |
| 待开始 | 1 |

## 验证步骤

1. 运行 `./dev-docker.sh build` 构建 Docker 镜像
2. 运行 `./dev-docker.sh start` 启动容器
3. 访问 http://localhost:3111
4. 创建一个任务，启用"保留字幕文件"和"使用已有刮削信息"
5. 执行任务，检查字幕文件和图片文件是否被正确下载

## 技术细节

### 配置传递机制

处理器通过 `FileProcessingContext` 的属性传递配置：

```java
// SubtitleCopyHandler
private boolean isKeepSubtitleEnabled(FileProcessingContext context) {
    Object value = context.getAttribute("keepSubtitleFiles");
    return Boolean.TRUE.equals(value);
}

// ImageDownloadHandler
private boolean isImageScrapingEnabled(FileProcessingContext context) {
    Object value = context.getAttribute("useExistingScrapingInfo");
    return Boolean.TRUE.equals(value);
}
```

### URL 编码处理

```java
// 使用 UrlEncoder.encodeUrlSmart() 处理中文路径
String downloadUrl = subtitleFile.getUrl();
if (subtitleFile.getSign() != null && !subtitleFile.getSign().isEmpty()) {
    downloadUrl = downloadUrl + "?sign=" + subtitleFile.getSign();
}
downloadUrl = UrlEncoder.encodeUrlSmart(downloadUrl);

byte[] content = openlistApiService.downloadWithEncodedUrl(
    context.getOpenlistConfig(), subtitleFile, downloadUrl);
```

### 支持的字幕格式

```java
private static final Set<String> SUBTITLE_EXTENSIONS = Set.of(
    ".srt", ".ass", ".vtt", ".ssa", ".sub", ".idx"
);
```

### 支持的图片格式

```java
private static final Set<String> IMAGE_EXTENSIONS = Set.of(
    ".jpg", ".jpeg", ".png", ".webp", ".bmp"
);
```
