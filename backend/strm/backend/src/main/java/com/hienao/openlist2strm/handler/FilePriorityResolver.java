package com.hienao.openlist2strm.handler;

import com.hienao.openlist2strm.handler.context.FileProcessingContext;
import com.hienao.openlist2strm.service.SystemConfigService;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.Set;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

/**
 * 文件优先级解析器
 *
 * <p>实现三级优先级判断逻辑：
 *
 * <ol>
 *   <li>优先级 1 - 本地文件：检查本地是否存在对应文件
 *   <li>优先级 2 - OpenList 文件：本地不存在时从 OpenList 同级目录下载
 *   <li>优先级 3 - 刮削文件：前两级都不存在时执行 API 刮削
 * </ol>
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class FilePriorityResolver {

  private final SystemConfigService systemConfigService;

  // ==================== 字幕文件扩展名 ====================

  private static final Set<String> SUBTITLE_EXTENSIONS =
      Set.of(".srt", ".ass", ".vtt", ".ssa", ".sub", ".idx");

  // ==================== 优先级判断 ====================

  /**
   * 解析文件优先级
   *
   * @param fileType 文件类型
   * @param fileName 文件名
   * @param saveDirectory 保存目录
   * @return 优先级判断结果
   */
  public PriorityResult resolve(FileType fileType, String fileName, String saveDirectory) {
    String baseFileName = removeExtension(fileName);

    // 1. 检查本地文件是否存在
    if (isLocalFileExists(fileType, saveDirectory, baseFileName)) {
      return PriorityResult.localExists(baseFileName);
    }

    // 2. 检查配置是否启用
    if (!isConfigEnabled(fileType)) {
      return PriorityResult.configDisabled();
    }

    // 3. 返回 OpenList 下载优先级
    return PriorityResult.needDownloadFromOpenList(baseFileName);
  }

  /**
   * 解析文件优先级（使用上下文）
   *
   * @param fileType 文件类型
   * @param context 处理上下文
   * @return 优先级判断结果
   */
  public PriorityResult resolve(FileType fileType, FileProcessingContext context) {
    String baseFileName = context.getBaseFileName();
    if (baseFileName == null || baseFileName.isEmpty()) {
      baseFileName = removeExtension(context.getCurrentFile().getName());
    }

    String saveDirectory = context.getSaveDirectory();

    // 1. 检查本地文件是否存在
    if (isLocalFileExists(fileType, saveDirectory, baseFileName)) {
      return PriorityResult.localExists(baseFileName);
    }

    // 2. 检查配置是否启用
    if (!isConfigEnabled(fileType)) {
      return PriorityResult.configDisabled();
    }

    // 3. 返回 OpenList 下载优先级
    return PriorityResult.needDownloadFromOpenList(baseFileName);
  }

  // ==================== 本地文件存在性检查 ====================

  /**
   * 检查本地文件是否存在
   *
   * @param fileType 文件类型
   * @param saveDirectory 保存目录
   * @param baseFileName 基础文件名（无扩展名）
   * @return 是否存在
   */
  public boolean isLocalFileExists(FileType fileType, String saveDirectory, String baseFileName) {
    if (saveDirectory == null || baseFileName == null) {
      return false;
    }

    Path savePath = Paths.get(saveDirectory);
    if (!Files.exists(savePath) || !Files.isDirectory(savePath)) {
      return false;
    }

    switch (fileType) {
      case NFO:
        return hasNfoFile(savePath, baseFileName);
      case IMAGE:
        return hasImageFiles(savePath, baseFileName);
      case SUBTITLE:
        return hasSubtitleFiles(savePath, baseFileName);
      case VIDEO:
        return hasVideoFile(savePath, baseFileName);
      default:
        return false;
    }
  }

  /** 检查 NFO 文件是否存在 */
  private boolean hasNfoFile(Path saveDirectory, String baseFileName) {
    try (var stream = Files.list(saveDirectory)) {
      return stream.anyMatch(
          path -> {
            String name = path.getFileName().toString().toLowerCase();
            return name.endsWith(".nfo") && name.startsWith(normalizeForComparison(baseFileName));
          });
    } catch (Exception e) {
      log.warn("检查NFO文件是否存在失败: {}", saveDirectory, e);
      return false;
    }
  }

  /** 检查图片文件是否存在 */
  private boolean hasImageFiles(Path saveDirectory, String baseFileName) {
    String[] extensions = {"-poster.jpg", "-fanart.jpg", "-thumb.jpg"};
    for (String ext : extensions) {
      Path imagePath = saveDirectory.resolve(baseFileName + ext);
      if (Files.exists(imagePath)) {
        return true;
      }
    }
    return false;
  }

  /** 检查字幕文件是否存在 */
  private boolean hasSubtitleFiles(Path saveDirectory, String baseFileName) {
    for (String ext : SUBTITLE_EXTENSIONS) {
      Path subtitlePath = saveDirectory.resolve(baseFileName + ext);
      if (Files.exists(subtitlePath)) {
        return true;
      }
    }
    return false;
  }

  /** 检查视频文件是否存在 */
  private boolean hasVideoFile(Path saveDirectory, String baseFileName) {
    try (var stream = Files.list(saveDirectory)) {
      return stream.anyMatch(
          path -> {
            String name = path.getFileName().toString().toLowerCase();
            return isVideoExtension(name) && name.startsWith(normalizeForComparison(baseFileName));
          });
    } catch (Exception e) {
      log.warn("检查视频文件是否存在失败: {}", saveDirectory, e);
      return false;
    }
  }

  // ==================== 配置检查 ====================

  /** 检查配置是否启用 */
  private boolean isConfigEnabled(FileType fileType) {
    switch (fileType) {
      case NFO:
      case IMAGE:
        var scrapingConfig = systemConfigService.getScrapingConfig();
        return Boolean.TRUE.equals(scrapingConfig.get("useExistingScrapingInfo"));
      case SUBTITLE:
        var subtitleConfig = systemConfigService.getScrapingConfig();
        return Boolean.TRUE.equals(subtitleConfig.get("keepSubtitleFiles"));
      default:
        return true;
    }
  }

  // ==================== 工具方法 ====================

  /** 移除文件扩展名 */
  public String removeExtension(String fileName) {
    if (fileName == null || fileName.isEmpty()) {
      return fileName;
    }
    int lastDotIndex = fileName.lastIndexOf('.');
    if (lastDotIndex > 0) {
      return fileName.substring(0, lastDotIndex);
    }
    return fileName;
  }

  /** 标准化文件名用于比较 */
  private String normalizeForComparison(String fileName) {
    return fileName.toLowerCase().replaceAll("[^a-z0-9]", "");
  }

  /** 检查是否为视频文件扩展名 */
  public boolean isVideoExtension(String fileName) {
    if (fileName == null) {
      return false;
    }
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

  /** 检查是否为字幕文件扩展名 */
  public boolean isSubtitleExtension(String fileName) {
    if (fileName == null) {
      return false;
    }
    String lower = fileName.toLowerCase();
    for (String ext : SUBTITLE_EXTENSIONS) {
      if (lower.endsWith(ext)) {
        return true;
      }
    }
    return false;
  }
}
