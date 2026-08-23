package com.hienao.openlist2strm.handler;

import lombok.AllArgsConstructor;
import lombok.Data;
import lombok.NoArgsConstructor;

/**
 * 优先级判断结果
 *
 * <p>封装优先级判断的结果，包含优先级等级、文件名和消息。
 *
 * @author hienao
 * @since 2024-01-01
 */
@Data
@NoArgsConstructor
@AllArgsConstructor
public class PriorityResult {

  /** 优先级 */
  private Priority priority;

  /** 文件名 */
  private String fileName;

  /** 消息 */
  private String message;

  /** 本地文件已存在 */
  public static PriorityResult localExists(String fileName) {
    return new PriorityResult(Priority.LOCAL, fileName, "本地文件已存在，跳过下载");
  }

  /** 需要从 OpenList 下载 */
  public static PriorityResult needDownloadFromOpenList(String fileName) {
    return new PriorityResult(Priority.OPENLIST, fileName, "本地不存在，需要从OpenList下载");
  }

  /** 需要执行刮削 */
  public static PriorityResult needScraping(String fileName) {
    return new PriorityResult(Priority.SCRAPING, fileName, "OpenList不存在，需要执行刮削");
  }

  /** 配置已禁用 */
  public static PriorityResult configDisabled() {
    return new PriorityResult(Priority.SKIPPED, null, "配置已禁用，跳过处理");
  }

  /** 检查是否为本地文件 */
  public boolean isLocal() {
    return Priority.LOCAL.equals(this.priority);
  }

  /** 检查是否需要从 OpenList 下载 */
  public boolean isOpenList() {
    return Priority.OPENLIST.equals(this.priority);
  }

  /** 检查是否需要刮削 */
  public boolean isScraping() {
    return Priority.SCRAPING.equals(this.priority);
  }

  /** 检查是否已跳过 */
  public boolean isSkipped() {
    return Priority.SKIPPED.equals(this.priority);
  }
}
