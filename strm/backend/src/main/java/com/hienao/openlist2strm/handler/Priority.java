package com.hienao.openlist2strm.handler;

/**
 * 优先级枚举
 *
 * <p>定义了文件获取的优先级顺序，从高到低：
 *
 * <ol>
 *   <li>LOCAL - 本地文件
 *   <li>OPENLIST - 从 OpenList 下载
 *   <li>SCRAPING - 从 API 刮削
 * </ol>
 *
 * @author hienao
 * @since 2024-01-01
 */
public enum Priority {

  /**
   * 本地文件
   *
   * <p>优先级最高，目标目录已存在对应文件时使用。
   */
  LOCAL,

  /**
   * OpenList 文件
   *
   * <p>优先级中等，本地不存在时从 OpenList 同级目录下载。
   */
  OPENLIST,

  /**
   * 刮削文件
   *
   * <p>优先级最低，前两级都不存在时从 API 刮削。
   */
  SCRAPING,

  /**
   * 跳过
   *
   * <p>配置禁用等原因跳过处理。
   */
  SKIPPED
}
