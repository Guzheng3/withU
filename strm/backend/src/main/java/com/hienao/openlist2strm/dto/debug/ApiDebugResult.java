package com.hienao.openlist2strm.dto.debug;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

/**
 * 元数据接口调试调用结果。
 *
 * <p>用于可视化测试豆瓣 / TMDB 等外部元数据接口，包含请求地址、 状态码、耗时、原始响应体以及解析后的结构化结果，方便定位接口问题。
 */
@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ApiDebugResult {

  /** 来源标识：tmdb / douban */
  private String source;

  /** 操作标识，例如 search-movie、movie-detail、douban-search 等 */
  private String action;

  /** 请求方法 */
  private String method;

  /** 实际请求的完整 URL */
  private String url;

  /** HTTP 状态码 */
  private Integer statusCode;

  /** 调用耗时（毫秒） */
  private Long elapsedMs;

  /** 原始响应体（可能被截断） */
  private String rawResponse;

  /** 解析后的结构化结果 */
  private Object parsed;

  /** 错误信息（调用失败或解析失败时不为空） */
  private String error;

  /** 是否成功（HTTP 2xx 且解析成功） */
  private boolean success;
}
