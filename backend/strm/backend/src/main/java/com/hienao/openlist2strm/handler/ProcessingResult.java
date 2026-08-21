package com.hienao.openlist2strm.handler;

/**
 * 处理结果枚举
 *
 * <p>定义了文件处理器执行的可能结果。
 *
 * @author hienao
 * @since 2024-01-01
 */
public enum ProcessingResult {

  /** 处理成功 */
  SUCCESS,

  /**
   * 跳过
   *
   * <p>文件已存在、配置禁用等原因跳过处理。
   */
  SKIPPED,

  /**
   * 处理失败
   *
   * <p>处理过程中发生错误。
   */
  FAILED,

  /**
   * 需要 Fallback
   *
   * <p>当前处理器无法处理，需要其他处理器处理。
   */
  FALLBACK
}
