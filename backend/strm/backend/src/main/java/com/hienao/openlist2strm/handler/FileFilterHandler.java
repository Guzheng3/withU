package com.hienao.openlist2strm.handler;

import com.hienao.openlist2strm.handler.context.FileProcessingContext;
import com.hienao.openlist2strm.service.OpenlistApiService;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

/**
 * 文件过滤处理器
 *
 * <p>负责过滤出视频文件，只将视频文件传递给后续处理器。
 *
 * <p>Order: 20
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
@Order(20)
@RequiredArgsConstructor
public class FileFilterHandler implements FileProcessorHandler {

  // ==================== 支持的视频文件扩展名 ====================

  private static final Set<String> VIDEO_EXTENSIONS =
      Set.of(
          ".mp4", ".mkv", ".avi", ".mov", ".wmv", ".flv", ".webm", ".m4v", ".m2ts", ".ts", ".rmvb",
          ".rm", ".3gp", ".mpeg", ".mpg");

  private static final Set<String> SUBTITLE_EXTENSIONS =
      Set.of(".srt", ".ass", ".vtt", ".ssa", ".sub", ".idx");

  private static final Set<String> IMAGE_EXTENSIONS =
      Set.of(".jpg", ".jpeg", ".png", ".webp", ".bmp", ".gif", ".tiff", ".tif");

  // ==================== 接口实现 ====================

  @Override
  public ProcessingResult process(FileProcessingContext context) {
    try {
      @SuppressWarnings("unchecked")
      List<OpenlistApiService.OpenlistFile> allFiles =
          (List<OpenlistApiService.OpenlistFile>) context.getAttribute("discoveredFiles");

      if (allFiles == null || allFiles.isEmpty()) {
        log.debug("没有发现文件可过滤");
        return ProcessingResult.SUCCESS;
      }

      // 过滤出视频文件
      List<OpenlistApiService.OpenlistFile> videoFiles = filterVideoFiles(allFiles);

      // 过滤出字幕文件
      List<OpenlistApiService.OpenlistFile> subtitleFiles =
          filterByExtensions(allFiles, SUBTITLE_EXTENSIONS);

      // 过滤出图片文件
      List<OpenlistApiService.OpenlistFile> imageFiles =
          filterByExtensions(allFiles, IMAGE_EXTENSIONS);

      // 设置过滤结果到上下文
      context.setAttribute("videoFiles", videoFiles);
      context.setAttribute("subtitleFiles", subtitleFiles);
      context.setAttribute("imageFiles", imageFiles);

      log.debug(
          "文件过滤完成: {} 视频, {} 字幕, {} 图片",
          videoFiles.size(),
          subtitleFiles.size(),
          imageFiles.size());

      return ProcessingResult.SUCCESS;

    } catch (Exception e) {
      log.error("文件过滤失败: {}", e.getMessage(), e);
      return ProcessingResult.FAILED;
    }
  }

  @Override
  public Set<FileType> getHandledTypes() {
    return Set.of(FileType.VIDEO, FileType.ALL);
  }

  // ==================== 过滤方法 ====================

  /** 过滤出视频文件 */
  public List<OpenlistApiService.OpenlistFile> filterVideoFiles(
      List<OpenlistApiService.OpenlistFile> files) {
    return files.stream()
        .filter(f -> "file".equals(f.getType()))
        .filter(f -> isVideoFile(f.getName()))
        .collect(Collectors.toList());
  }

  /** 按扩展名过滤文件 */
  private List<OpenlistApiService.OpenlistFile> filterByExtensions(
      List<OpenlistApiService.OpenlistFile> files, Set<String> extensions) {
    return files.stream()
        .filter(f -> "file".equals(f.getType()))
        .filter(f -> hasExtension(f.getName(), extensions))
        .collect(Collectors.toList());
  }

  // ==================== 工具方法 ====================

  /** 检查是否为视频文件 */
  public boolean isVideoFile(String fileName) {
    if (fileName == null) {
      return false;
    }
    String lower = fileName.toLowerCase();
    // 跳过隐藏文件
    if (lower.startsWith(".")) {
      return false;
    }
    return hasExtension(fileName, VIDEO_EXTENSIONS);
  }

  /** 检查是否为字幕文件 */
  public boolean isSubtitleFile(String fileName) {
    if (fileName == null) {
      return false;
    }
    return hasExtension(fileName.toLowerCase(), SUBTITLE_EXTENSIONS);
  }

  /** 检查是否为图片文件 */
  public boolean isImageFile(String fileName) {
    if (fileName == null) {
      return false;
    }
    return hasExtension(fileName.toLowerCase(), IMAGE_EXTENSIONS);
  }

  /** 检查文件是否具有指定扩展名之一 */
  private boolean hasExtension(String fileName, Set<String> extensions) {
    if (fileName == null) {
      return false;
    }
    String lower = fileName.toLowerCase();
    for (String ext : extensions) {
      if (lower.endsWith(ext)) {
        return true;
      }
    }
    return false;
  }

  /** 获取所有视频扩展名 */
  public Set<String> getVideoExtensions() {
    return new HashSet<>(VIDEO_EXTENSIONS);
  }

  /** 获取所有字幕扩展名 */
  public Set<String> getSubtitleExtensions() {
    return new HashSet<>(SUBTITLE_EXTENSIONS);
  }
}
