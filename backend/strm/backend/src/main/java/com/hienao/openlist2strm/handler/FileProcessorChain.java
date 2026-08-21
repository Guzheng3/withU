package com.hienao.openlist2strm.handler;

import com.hienao.openlist2strm.handler.context.FileProcessingContext;
import com.hienao.openlist2strm.service.OpenlistApiService;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

/**
 * 文件处理器链执行器
 *
 * <p>负责按顺序执行所有注册的 Handler：
 *
 * <ul>
 *   <li>按 getOrder() 返回值排序
 *   <li>依次调用每个 Handler 的 process() 方法
 *   <li>收集并返回最终处理结果
 * </ul>
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
public class FileProcessorChain {

  private final List<FileProcessorHandler> handlers;

  public FileProcessorChain(List<FileProcessorHandler> handlers) {
    // 按 Order 排序
    this.handlers =
        handlers.stream()
            .sorted(Comparator.comparingInt(FileProcessorHandler::getOrder))
            .collect(Collectors.toList());

    log.info("初始化文件处理链，共 {} 个处理器", this.handlers.size());
    log.debug(
        "处理器顺序: {}",
        this.handlers.stream()
            .map(h -> h.getClass().getSimpleName() + "(" + h.getOrder() + ")")
            .collect(Collectors.joining(", ")));
  }

  /**
   * 执行处理链
   *
   * @param context 处理上下文
   * @return 整体处理结果
   */
  public ProcessingResult execute(FileProcessingContext context) {
    ProcessingResult overallResult = ProcessingResult.SUCCESS;

    for (FileProcessorHandler handler : handlers) {
      try {
        // 检查处理器是否支持当前文件类型
        if (!supports(handler, context)) {
          log.debug("处理器 {} 不支持当前文件类型，跳过", handler.getClass().getSimpleName());
          continue;
        }

        ProcessingResult handlerResult = handler.process(context);

        // 记录处理结果
        log.debug("处理器 {} 处理结果: {}", handler.getClass().getSimpleName(), handlerResult);

        // 如果任何处理器失败，整体结果为失败
        if (handlerResult == ProcessingResult.FAILED) {
          overallResult = ProcessingResult.FAILED;
        }

      } catch (Exception e) {
        log.error("处理器 {} 执行失败: {}", handler.getClass().getSimpleName(), e.getMessage(), e);
        overallResult = ProcessingResult.FAILED;
      }
    }

    return overallResult;
  }

  /** 检查处理器是否支持当前文件 */
  private boolean supports(FileProcessorHandler handler, FileProcessingContext context) {
    OpenlistApiService.OpenlistFile currentFile = context.getCurrentFile();
    if (currentFile == null) {
      // 如果没有当前文件（如发现阶段），返回 true
      return true;
    }

    Set<FileType> handledTypes = handler.getHandledTypes();
    if (handledTypes.contains(FileType.ALL)) {
      return true;
    }

    // 根据文件类型判断
    String fileName = currentFile.getName().toLowerCase();
    FileType fileType = getFileType(fileName);

    return handledTypes.contains(fileType);
  }

  /** 根据文件名获取文件类型 */
  private FileType getFileType(String fileName) {
    if (isVideoExtension(fileName)) {
      return FileType.VIDEO;
    } else if (fileName.endsWith(".nfo")) {
      return FileType.NFO;
    } else if (isImageExtension(fileName)) {
      return FileType.IMAGE;
    } else if (isSubtitleExtension(fileName)) {
      return FileType.SUBTITLE;
    }
    return FileType.VIDEO; // 默认视为视频文件
  }

  private boolean isVideoExtension(String fileName) {
    if (fileName == null) return false;
    String lower = fileName.toLowerCase();
    return lower.endsWith(".mp4")
        || lower.endsWith(".mkv")
        || lower.endsWith(".avi")
        || lower.endsWith(".mov")
        || lower.endsWith(".wmv")
        || lower.endsWith(".flv")
        || lower.endsWith(".webm")
        || lower.endsWith(".m4v")
        || lower.endsWith(".m2ts")
        || lower.endsWith(".ts")
        || lower.endsWith(".rmvb")
        || lower.endsWith(".rm")
        || lower.endsWith(".3gp")
        || lower.endsWith(".mpeg")
        || lower.endsWith(".mpg");
  }

  private boolean isImageExtension(String fileName) {
    if (fileName == null) return false;
    String lower = fileName.toLowerCase();
    return lower.endsWith(".jpg")
        || lower.endsWith(".jpeg")
        || lower.endsWith(".png")
        || lower.endsWith(".webp")
        || lower.endsWith(".bmp")
        || lower.endsWith(".gif")
        || lower.endsWith(".tiff")
        || lower.endsWith(".tif");
  }

  private boolean isSubtitleExtension(String fileName) {
    if (fileName == null) return false;
    String lower = fileName.toLowerCase();
    return lower.endsWith(".srt")
        || lower.endsWith(".ass")
        || lower.endsWith(".vtt")
        || lower.endsWith(".ssa")
        || lower.endsWith(".sub")
        || lower.endsWith(".idx");
  }

  /**
   * 获取所有已注册的处理器
   *
   * @return 处理器列表（已排序）
   */
  public List<FileProcessorHandler> getHandlers() {
    return new ArrayList<>(handlers);
  }

  /**
   * 获取指定类型的处理器
   *
   * @param handlerClass 处理器类
   * @return 处理器实例，不存在返回 null
   */
  public <T extends FileProcessorHandler> T getHandler(Class<T> handlerClass) {
    return handlers.stream()
        .filter(handlerClass::isInstance)
        .map(handlerClass::cast)
        .findFirst()
        .orElse(null);
  }
}
