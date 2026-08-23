# 文件处理管道优化设计文档

## Context

### 背景

当前 `TaskExecutionService` 承担了过多的职责，导致代码耦合度高、维护困难：
- 文件发现（递归遍历目录）
- 文件过滤（视频文件识别）
- STRM文件生成
- 媒体刮削协调
- 字幕/NFO/图片文件复制
- 孤立文件清理

此外，`MediaScrapingService.copyRelatedFiles()` 方法存在重复下载问题，同一个字幕文件在处理多个视频文件时被多次下载。

### 当前状态

- `TaskExecutionService.java:190-321` - `executeTaskLogic()` 方法集中处理所有逻辑
- `TaskExecutionService.java:505-627` - `processDirectoryBatch()` 和 `processVideoFile()` 存在重复逻辑
- `MediaScrapingService.java:820-874` - `copyRelatedFiles()` 无文件存在性检查

### 约束

- 保持向后兼容，不改变外部API
- 不引入新的外部依赖
- 增量重构，不破坏现有功能

## Goals / Non-Goals

### Goals

1. **职责分离**：将文件处理管道拆分为独立的Handler，每个Handler只负责一个职责
2. **消除重复**：统一STRM生成逻辑，消除 `executeTaskLogic()` 和 `processVideoFile()` 中的重复代码
3. **防止重复下载**：在文件下载时检查本地是否存在，避免重复API调用
4. **配置联动**：支持「优先使用已存在的刮削信息」和「保留字幕文件」配置

### Non-Goals

- 不改变现有的外部API接口
- 不引入新的外部依赖库
- 不重构数据库模型
- 不修改任务调度逻辑（Quartz相关）

## Decisions

### 决策1：采用责任链模式拆分处理管道

**选择**：使用 Chain of Responsibility 模式定义文件处理管道

**理由**：
- 每个Handler独立，便于测试和维护
- Handler可复用（如NfoDownloadHandler可被多个场景使用）
- 易于扩展，新增Handler不影响现有逻辑
- 符合单一职责原则

**替代方案考虑**：
- 策略模式：适合运行时切换算法，但处理流程固定
- 观察者模式：适合事件通知，不适合顺序处理
- 管道模式：与责任链类似，但责任链更符合"链式调用"语义

### 决策2：统一文件优先级检查逻辑

**选择**：创建 `FilePriorityResolver` 组件统一处理优先级判断

**优先级规则**：
```
本地文件 → OpenList文件 → 刮削文件
```

**实现**：
```java
public interface FilePriorityResolver {
    PriorityResult resolve(FileType type, String fileName, String saveDirectory);
}

public enum Priority {
    LOCAL,      // 本地文件
    OPENLIST,   // OpenList文件
    SCRAPING    // 刮削文件
}
```

### 决策3：Handler接口设计

**选择**：采用泛型接口 + 共享上下文

```java
public interface FileProcessorHandler {
    /**
     * 处理文件
     * @param context 处理上下文
     * @return 处理结果
     */
    ProcessingResult process(FileProcessingContext context);

    /**
     * 获取处理器顺序
     */
    default int getOrder() { return 0; }
}
```

### 决策4：共享上下文设计

**选择**：使用 `FileProcessingContext` 在Handler之间传递数据

```java
@Data
public class FileProcessingContext {
    private OpenlistConfig openlistConfig;      // OpenList配置
    private TaskConfig taskConfig;              // 任务配置
    private OpenlistFile currentFile;           // 当前处理的文件
    private String relativePath;                // 相对路径
    private String saveDirectory;               // 保存目录
    private ProcessingState state;              // 处理状态
    private Map<String, Object> attributes;     // 扩展属性
}
```

## Class Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    FileProcessorChain                            │
├─────────────────────────────────────────────────────────────────┤
│ - handlers: List<FileProcessorHandler>                           │
│ - context: FileProcessingContext                                 │
├─────────────────────────────────────────────────────────────────┤
│ + execute(context): ProcessingResult                             │
│ + addHandler(handler): void                                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 FileProcessorHandler (Interface)                 │
├─────────────────────────────────────────────────────────────────┤
│ + process(context): ProcessingResult                             │
│ + getOrder(): int                                                │
│ + getHandledTypes(): Set<FileType>                              │
└─────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          │                   │                   │
          ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│FileDiscovery    │ │FileFilter       │ │StrmGeneration   │
│    Handler      │ │    Handler      │ │    Handler      │
├─────────────────┤ ├─────────────────┤ ├─────────────────┤
│+ 发现文件元数据 │ │+ 过滤视频文件   │ │+ 生成STRM文件   │
└─────────────────┘ └─────────────────┘ └─────────────────┘
          │                   │                   │
          ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│NfoDownload      │ │ImageDownload    │ │SubtitleCopy     │
│    Handler      │ │    Handler      │ │    Handler      │
├─────────────────┤ ├─────────────────┤ ├─────────────────┤
│+ 下载NFO文件    │ │+ 下载封面/背景  │ │+ 复制字幕文件   │
│+ 三级优先级     │ │+ 三级优先级     │ │+ 三级优先级     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
          │                   │                   │
          └───────────────────┼───────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 FilePriorityResolver                             │
├─────────────────────────────────────────────────────────────────┤
│ + resolve(fileType, fileName, directory): Priority              │
│ + isLocalFileExists(fileName, directory): boolean               │
│ + downloadFromOpenList(file): byte[]                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                 MediaScrapingHandler                             │
├─────────────────────────────────────────────────────────────────┤
│ + 执行媒体刮削（仅当OpenList文件不存在时）                       │
└─────────────────────────────────────────────────────────────────┘
```

## Sequence Diagram

### 完整处理流程

```
┌─────────┐  ┌─────────────────┐  ┌────────────────┐  ┌────────────────┐
│  Client │  │FileProcessor    │  │FileDiscovery   │  │FileFilter      │
│         │  │    Chain        │  │    Handler     │  │    Handler     │
└────┬────┘  └────────┬────────┘  └───────┬────────┘  └───────┬────────┘
     │                │                    │                    │
     │ execute()      │                    │                    │
     │───────────────>│                    │                    │
     │                │                    │                    │
     │                │ process()          │                    │
     │                │───────────────────>│                    │
     │                │                    │                    │
     │                │                    │ 发现文件列表       │
     │                │                    │<───────────────────│ (OpenList API)
     │                │                    │                    │
     │                │                    │ 返回文件列表       │
     │                │<───────────────────│                    │
     │                │                    │                    │
     │                │                    │                    │
     │                │ process()          │                    │
     │                │────────────────────────────────────────>│
     │                │                    │                    │
     │                │                    │                    │ 过滤视频文件
     │                │                    │                    │
     │                │                    │                    │ 返回视频文件列表
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ ┌────────────────────────────────────────│
     │                │ │  对每个视频文件执行后续Handler链         │
     │                │ │                                         │
     │                │ ▼                                        │
     │                │ process()                                │
     │                │────────────────────────────────────────>│
     │                │                    │                    │ StrmGenerationHandler
     │                │                    │                    │ 生成STRM文件
     │                │                    │                    │
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ process()          │                    │
     │                │────────────────────────────────────────>│
     │                │                    │                    │ NfoDownloadHandler
     │                │                    │                    │ 1. 检查本地NFO
     │                │                    │                    │ 2. 优先级判断
     │                │                    │                    │ 3. 下载/刮削
     │                │                    │                    │
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ process()          │                    │
     │                │────────────────────────────────────────>│
     │                │                    │                    │ ImageDownloadHandler
     │                │                    │                    │ ... (类似NFO)
     │                │                    │                    │
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ process()          │                    │
     │                │────────────────────────────────────────>│
     │                │                    │                    │ SubtitleCopyHandler
     │                │                    │                    │ ... (类似NFO)
     │                │                    │                    │
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ process()          │                    │
     │                │────────────────────────────────────────>│
     │                │                    │                    │ MediaScrapingHandler
     │                │                    │                    │ (仅当OpenList文件不存在)
     │                │                    │                    │
     │                │<────────────────────────────────────────│
     │                │                    │                    │
     │                │ 返回处理结果       │                    │
     │<───────────────│                    │                    │
     │                │                    │                    │
```

### NfoDownloadHandler 优先级判断流程

```
┌──────────────────────────────────────────────────────────────────────┐
│                    NfoDownloadHandler.process()                       │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  1. 检查本地文件是否存在                                              │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ if (isLocalFileExists(saveDirectory, baseName + ".nfo"))   │ │
│     │     log.debug("本地NFO文件已存在，跳过: {}", fileName);      │ │
│     │     return SKIPPED;                                         │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  2. 检查配置是否启用「优先使用已存在的刮削信息」                      │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ if (!config.isUseExistingScrapingInfo()) {                  │ │
│     │     log.debug("未启用优先使用已有刮削信息，跳过NFO下载");    │ │
│     │     return SKIPPED;                                         │ │
│     │ }                                                          │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  3. 从OpenList下载                                                   │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ byte[] content = openlistApiService.getFileContent(         │ │
│     │     openlistConfig, currentFile.getPath() + ".nfo");       │ │
│     │ if (content != null) {                                      │ │
│     │     saveToLocal(saveDirectory, baseName + ".nfo", content); │ │
│     │     return SUCCESS;                                         │ │
│     │ }                                                          │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  4. 执行TMDB刮削（fallback）                                         │
│     ┌─────────────────────────────────────────────────────────────┐ │
│     │ tmdbApiService.scrapeMovieNfo(mediaInfo, nfoPath);         │ │
│     │ return SCRAPED;                                             │ │
│     └─────────────────────────────────────────────────────────────┘ │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

## Handler 详细设计

### 1. FileProcessorHandler 接口

```java
/**
 * 文件处理器接口
 */
public interface FileProcessorHandler {

    /**
     * 处理文件
     * @param context 处理上下文
     * @return 处理结果
     */
    ProcessingResult process(FileProcessingContext context);

    /**
     * 获取处理器顺序（数值越小越先执行）
     */
    default int getOrder() {
        return 0;
    }

    /**
     * 获取处理器支持的文件类型
     */
    default Set<FileType> getHandledTypes() {
        return EnumSet.allOf(FileType.class);
    }
}

/**
 * 处理结果枚举
 */
public enum ProcessingResult {
    SUCCESS,    // 处理成功
    SKIPPED,    // 跳过（文件已存在/配置禁用）
    FAILED,     // 处理失败
    FALLBACK   // 需要fallback到其他处理方式
}

/**
 * 文件类型枚举
 */
public enum FileType {
    VIDEO,      // 视频文件
    NFO,        // NFO文件
    IMAGE,      // 图片文件
    SUBTITLE,   // 字幕文件
    ALL         // 所有类型
}
```

### 2. FileProcessingContext 上下文

```java
/**
 * 文件处理上下文
 */
@Data
public class FileProcessingContext {

    // 配置信息
    private OpenlistConfig openlistConfig;
    private TaskConfig taskConfig;

    // 当前处理的文件信息
    private OpenlistFile currentFile;
    private String relativePath;
    private String saveDirectory;

    // 处理状态
    private ProcessingState state;
    private String baseFileName;  // 无扩展名的文件名

    // 文件列表（用于相关文件查找）
    private List<OpenlistFile> directoryFiles;

    // 扩展属性
    private Map<String, Object> attributes;

    // 统计数据
    private ProcessingStats stats;

    @Data
    public static class ProcessingStats {
        private int totalFiles;
        private int processedFiles;
        private int skippedFiles;
        private int failedFiles;
        private Map<FileType, Integer> typeCounts;
    }
}
```

### 3. FilePriorityResolver 优先级解析器

```java
/**
 * 文件优先级解析器
 */
@Component
public class FilePriorityResolver {

    private final SystemConfigService systemConfigService;

    /**
     * 解析文件优先级
     * @param fileType 文件类型
     * @param fileName 文件名
     * @param saveDirectory 保存目录
     * @return 优先级结果
     */
    public PriorityResult resolve(FileType fileType, String fileName, String saveDirectory) {
        // 1. 检查本地文件是否存在
        String localFileName = getExpectedFileName(fileType, fileName);
        Path localPath = Paths.get(saveDirectory, localFileName);

        if (Files.exists(localPath)) {
            return PriorityResult.localExists(localFileName);
        }

        // 2. 检查配置是否启用
        if (!isConfigEnabled(fileType)) {
            return PriorityResult.configDisabled();
        }

        // 3. 返回OpenList下载优先级
        return PriorityResult.needDownloadFromOpenList(fileName);
    }

    /**
     * 检查本地文件是否存在（参考 isAlreadyScraped() 逻辑）
     */
    public boolean isLocalFileExists(FileType fileType, String saveDirectory, String baseFileName) {
        switch (fileType) {
            case NFO:
                // 检查NFO文件
                return hasNfoFile(saveDirectory, baseFileName);
            case IMAGE:
                // 检查图片文件
                return hasImageFiles(saveDirectory, baseFileName);
            case SUBTITLE:
                // 检查字幕文件
                return hasSubtitleFiles(saveDirectory, baseFileName);
            default:
                return false;
        }
    }

    private boolean hasNfoFile(String directory, String baseFileName) {
        File dir = new File(directory);
        if (!dir.exists() || !dir.isDirectory()) {
            return false;
        }
        File[] nfoFiles = dir.listFiles((d, name) ->
            name.toLowerCase().endsWith(".nfo") &&
            name.startsWith(baseFileName));
        return nfoFiles != null && nfoFiles.length > 0;
    }

    private boolean hasImageFiles(String directory, String baseFileName) {
        // 检查poster, backdrop, thumb等图片文件
        String[] extensions = {"-poster.jpg", "-fanart.jpg", "-thumb.jpg"};
        for (String ext : extensions) {
            if (Files.exists(Paths.get(directory, baseFileName + ext))) {
                return true;
            }
        }
        return false;
    }

    private boolean hasSubtitleFiles(String directory, String baseFileName) {
        String[] extensions = {".srt", ".ass", ".vtt", ".ssa", ".sub", ".idx"};
        for (String ext : extensions) {
            if (Files.exists(Paths.get(directory, baseFileName + ext))) {
                return true;
            }
        }
        return false;
    }
}

/**
 * 优先级结果
 */
@Data
@AllArgsConstructor
public class PriorityResult {
    private Priority priority;
    private String fileName;
    private String message;

    public static PriorityResult localExists(String fileName) {
        return new PriorityResult(Priority.LOCAL, fileName, "本地文件已存在");
    }

    public static PriorityResult needDownloadFromOpenList(String fileName) {
        return new PriorityResult(Priority.OPENLIST, fileName, "需要从OpenList下载");
    }

    public static PriorityResult needScraping(String fileName) {
        return new PriorityResult(Priority.SCRAPING, fileName, "需要执行刮削");
    }

    public static PriorityResult configDisabled() {
        return new PriorityResult(Priority.SKIPPED, null, "配置已禁用");
    }
}
```

### 4. NfoDownloadHandler 实现

```java
@Component
@Order(30)  // 在StrmGenerationHandler之后执行
public class NfoDownloadHandler implements FileProcessorHandler {

    private final FilePriorityResolver priorityResolver;
    private final OpenlistApiService openlistApiService;
    private final MediaScrapingService mediaScrapingService;

    @Override
    public ProcessingResult process(FileProcessingContext context) {
        // 1. 检查配置是否启用
        if (!isNfoScrapingEnabled(context)) {
            return ProcessingResult.SKIPPED;
        }

        // 2. 优先级判断
        PriorityResult priority = priorityResolver.resolve(
            FileType.NFO,
            context.getBaseFileName(),
            context.getSaveDirectory()
        );

        switch (priority.getPriority()) {
            case LOCAL:
                log.debug("NFO文件本地已存在，跳过: {}", context.getBaseFileName());
                return ProcessingResult.SKIPPED;

            case OPENLIST:
                return downloadFromOpenList(context);

            case SCRAPING:
                return fallbackToScraping(context);

            case SKIPPED:
                return ProcessingResult.SKIPPED;

            default:
                return ProcessingResult.FAILED;
        }
    }

    private ProcessingResult downloadFromOpenList(FileProcessingContext context) {
        try {
            // 从OpenList查找同名NFO文件
            String nfoFileName = context.getBaseFileName() + ".nfo";
            OpenlistFile nfoFile = findFileInDirectory(
                context.getDirectoryFiles(),
                nfoFileName
            );

            if (nfoFile != null) {
                byte[] content = openlistApiService.getFileContent(
                    context.getOpenlistConfig(),
                    nfoFile,
                    false
                );

                if (content != null && content.length > 0) {
                    Path targetPath = Paths.get(
                        context.getSaveDirectory(),
                        nfoFileName
                    );
                    Files.createDirectories(targetPath.getParent());
                    Files.write(targetPath, content);

                    log.info("从OpenList下载NFO文件成功: {}", nfoFileName);
                    return ProcessingResult.SUCCESS;
                }
            }

            // OpenList不存在，执行刮削
            return fallbackToScraping(context);

        } catch (Exception e) {
            log.error("从OpenList下载NFO文件失败: {}", context.getBaseFileName(), e);
            return ProcessingResult.FAILED;
        }
    }

    private ProcessingResult fallbackToScraping(FileProcessingContext context) {
        try {
            // 调用MediaScrapingService执行刮削
            mediaScrapingService.scrapMedia(
                context.getOpenlistConfig(),
                context.getCurrentFile().getName(),
                context.getTaskConfig().getStrmPath(),
                context.getRelativePath(),
                context.getDirectoryFiles(),
                context.getCurrentFile().getPath()
            );

            log.info("NFO文件刮削完成: {}", context.getBaseFileName());
            return ProcessingResult.SUCCESS;

        } catch (Exception e) {
            log.error("NFO文件刮削失败: {}", context.getBaseFileName(), e);
            return ProcessingResult.FAILED;
        }
    }

    private OpenlistFile findFileInDirectory(List<OpenlistFile> files, String fileName) {
        if (files == null) return null;
        return files.stream()
            .filter(f -> "file".equals(f.getType()))
            .filter(f -> f.getName().equalsIgnoreCase(fileName))
            .findFirst()
            .orElse(null);
    }
}
```

### 5. FileProcessorChain 执行器

```java
@Component
public class FileProcessorChain {

    private final List<FileProcessorHandler> handlers;

    @Autowired
    public FileProcessorChain(List<FileProcessorHandler> handlers) {
        // 按Order排序
        this.handlers = handlers.stream()
            .sorted(Comparator.comparingInt(FileProcessorHandler::getOrder))
            .collect(Collectors.toList());

        log.info("初始化文件处理链，共 {} 个处理器", this.handlers.size());
    }

    public ProcessingResult execute(FileProcessingContext context) {
        ProcessingResult result = ProcessingResult.SUCCESS;

        for (FileProcessorHandler handler : handlers) {
            try {
                // 检查处理器是否支持当前文件类型
                if (!supports(handler, context.getCurrentFile())) {
                    continue;
                }

                ProcessingResult handlerResult = handler.process(context);

                // 记录处理结果
                log.debug("处理器 {} 处理结果: {}",
                    handler.getClass().getSimpleName(),
                    handlerResult);

                // 如果处理失败，继续执行其他处理器但记录状态
                if (handlerResult == ProcessingResult.FAILED) {
                    result = ProcessingResult.FAILED;
                }

            } catch (Exception e) {
                log.error("处理器 {} 执行失败: {}",
                    handler.getClass().getSimpleName(),
                    e.getMessage(),
                    e);
                result = ProcessingResult.FAILED;
            }
        }

        return result;
    }

    private boolean supports(FileProcessorHandler handler, OpenlistFile file) {
        Set<FileType> types = handler.getHandledTypes();
        if (types.contains(FileType.ALL)) {
            return true;
        }
        // 根据文件类型判断
        String fileName = file.getName().toLowerCase();
        if (isVideoFile(fileName)) {
            return types.contains(FileType.VIDEO);
        } else if (fileName.endsWith(".nfo")) {
            return types.contains(FileType.NFO);
        } else if (isImageFile(fileName)) {
            return types.contains(FileType.IMAGE);
        } else if (isSubtitleFile(fileName)) {
            return types.contains(FileType.SUBTITLE);
        }
        return false;
    }
}
```

## Risks / Trade-offs

| 风险 | 影响 | 缓解措施 |
|-----|------|---------|
| 重构引入Bug | 可能破坏现有功能 | 增量重构，每步都测试；保持原有方法作为fallback |
| Handler顺序错误 | 处理流程错乱 | 使用 `@Order` 注解；编写单元测试验证顺序 |
| 性能下降 | 责任链调用有轻微开销 | Handler轻量级设计；避免不必要的对象创建 |
| 配置复杂度增加 | 用户难以理解优先级规则 | 完善文档和UI提示；默认值保持当前行为 |

## Migration Plan

### 阶段1：创建基础设施
1. 创建 `FileProcessorHandler` 接口
2. 创建 `FileProcessingContext` 上下文
3. 创建 `FileProcessorChain` 执行器
4. 创建 `FilePriorityResolver` 优先级解析器

### 阶段2：实现核心Handler
1. 实现 `FileDiscoveryHandler`
2. 实现 `FileFilterHandler`
3. 实现 `StrmGenerationHandler`

### 阶段3：实现文件下载Handler
1. 实现 `NfoDownloadHandler`
2. 实现 `ImageDownloadHandler`
3. 实现 `SubtitleCopyHandler`

### 阶段4：集成和切换
1. 实现 `MediaScrapingHandler`
2. 实现 `OrphanCleanupHandler`
3. 在 `TaskExecutionService` 中集成新的处理链
4. 添加特性开关，逐步切换流量

### 回滚策略
- 保留原有 `executeTaskLogic()` 方法作为fallback
- 使用配置开关控制是否使用新的处理链
- 发现问题时可快速回退到原有实现

## Open Questions

1. **是否需要支持Handler动态注册/卸载？**
   - 当前设计使用Spring自动扫描，变更需要重启
   - 考虑未来可能需要动态调整处理链

2. **是否需要支持Handler并行执行？**
   - 部分Handler之间无依赖（如NFO和图片下载）
   - 并行执行可提升性能，但增加复杂度

3. **是否需要支持条件跳过整个Handler链？**
   - 如检测到目录已完全刮削，跳过所有下载Handler
   - 可通过 `ProcessingResult.FALLBACK` 实现部分功能
