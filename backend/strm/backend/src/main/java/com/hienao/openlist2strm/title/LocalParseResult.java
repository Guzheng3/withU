package com.hienao.openlist2strm.title;

import java.util.List;
import lombok.Builder;
import lombok.Data;

/**
 * 本地规则解析结果。
 *
 * <p>对应方案中的"内部解析结果"：包含路径拆分、本地片名候选、年份、季、集与媒体类型判断。
 */
@Data
@Builder
public class LocalParseResult {

  private String path;
  private String filename;
  private List<String> directories;
  private String mediaType;
  private List<String> titleCandidates;
  private String year;
  private String yearSource;
  private Integer season;
  private Integer episode;
  private double confidence;
  private boolean skip; // 是否为需要跳过的辅助文件（字幕、图片等）
  private String skipReason;
}
