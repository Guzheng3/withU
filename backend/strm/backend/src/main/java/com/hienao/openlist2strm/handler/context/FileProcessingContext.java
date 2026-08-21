package com.hienao.openlist2strm.handler.context;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.handler.FileType;
import com.hienao.openlist2strm.service.OpenlistApiService;
import java.util.EnumMap;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

/**
 * 文件处理上下文
 *
 * <p>在 Handler 之间传递处理上下文，包含当前处理的文件信息和配置。
 *
 * @author hienao
 * @since 2024-01-01
 */
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class FileProcessingContext {

  // ==================== 配置信息 ====================

  /** OpenList 配置 */
  private OpenlistConfig openlistConfig;

  /** 任务配置 */
  private TaskConfig taskConfig;

  // ==================== 当前处理的文件信息 ====================

  /** 当前处理的文件 */
  private OpenlistApiService.OpenlistFile currentFile;

  /** 相对路径（相对于任务配置的 path） */
  private String relativePath;

  /** 保存目录（STRM 文件目录） */
  private String saveDirectory;

  /** 基础文件名（无扩展名） */
  private String baseFileName;

  // ==================== 文件列表 ====================

  /** 目录文件列表（用于相关文件查找） */
  private List<OpenlistApiService.OpenlistFile> directoryFiles;

  // ==================== 处理状态 ====================

  /** 处理状态 */
  private ProcessingState state;

  /** 扩展属性 */
  @Builder.Default private Map<String, Object> attributes = new HashMap<>();

  /** 统计数据 */
  @Builder.Default private ProcessingStats stats = new ProcessingStats();

  /** 处理状态 */
  public enum ProcessingState {
    INITIALIZED,
    PROCESSING,
    SUCCESS,
    SKIPPED,
    FAILED
  }

  /** 处理统计数据 */
  @Data
  @Builder
  @NoArgsConstructor
  @AllArgsConstructor
  public static class ProcessingStats {

    /** 总文件数 */
    @Builder.Default private int totalFiles = 0;

    /** 已处理文件数 */
    @Builder.Default private int processedFiles = 0;

    /** 跳过的文件数 */
    @Builder.Default private int skippedFiles = 0;

    /** 失败的文件数 */
    @Builder.Default private int failedFiles = 0;

    /** 按文件类型统计 */
    @Builder.Default private Map<FileType, Integer> typeCounts = new EnumMap<>(FileType.class);

    /** 增加处理计数 */
    public void incrementProcessed() {
      this.processedFiles++;
    }

    /** 增加跳过计数 */
    public void incrementSkipped() {
      this.skippedFiles++;
    }

    /** 增加失败计数 */
    public void incrementFailed() {
      this.failedFiles++;
    }

    /** 增加文件类型计数 */
    public void incrementTypeCount(FileType type) {
      this.typeCounts.merge(type, 1, Integer::sum);
    }
  }

  /**
   * 获取扩展属性
   *
   * @param key 属性键
   * @param <T> 属性类型
   * @return 属性值，不存在返回 null
   */
  @SuppressWarnings("unchecked")
  public <T> T getAttribute(String key) {
    return (T) attributes.get(key);
  }

  /**
   * 获取扩展属性（带默认值）
   *
   * @param key 属性键
   * @param defaultValue 默认值
   * @param <T> 属性类型
   * @return 属性值，不存在返回默认值
   */
  @SuppressWarnings("unchecked")
  public <T> T getAttribute(String key, T defaultValue) {
    return (T) attributes.getOrDefault(key, defaultValue);
  }

  /**
   * 设置扩展属性
   *
   * @param key 属性键
   * @param value 属性值
   */
  public void setAttribute(String key, Object value) {
    this.attributes.put(key, value);
  }

  /**
   * 初始化统计数据
   *
   * @param totalFiles 总文件数
   */
  public void initStats(int totalFiles) {
    this.stats.setTotalFiles(totalFiles);
    this.stats.setProcessedFiles(0);
    this.stats.setSkippedFiles(0);
    this.stats.setFailedFiles(0);
  }

  /**
   * 更新进度
   *
   * @param processed 已处理数量
   * @return 进度百分比
   */
  public int updateProgress(int processed) {
    this.stats.setProcessedFiles(processed);
    if (this.stats.getTotalFiles() > 0) {
      return (int) ((processed * 100.0) / this.stats.getTotalFiles());
    }
    return 0;
  }
}
