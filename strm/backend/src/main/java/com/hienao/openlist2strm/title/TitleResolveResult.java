package com.hienao.openlist2strm.title;

import java.util.ArrayList;
import java.util.List;
import lombok.Builder;
import lombok.Data;

/**
 * 片名解析最终结果（方案第四步/第十七至二十一步）。
 *
 * <p>对应外部输出：片名、年份、季，以及置信度、证据链与状态。
 */
@Data
@Builder
public class TitleResolveResult {

  private String path;
  private String id;
  private String title;
  private String year;
  private Integer season;
  private Integer episode;
  private String mediaType;
  private String tmdbId;
  private double confidence;
  private String status; // confirmed / need_review / unresolved
  private List<String> evidenceIds;
  private String message;
  private LocalParseResult local;
  @Builder.Default private List<MetadataCandidate> metadataCandidates = new ArrayList<>();

  /** 是否需要人工复核。 */
  public boolean needsReview() {
    return "need_review".equals(status) || "unresolved".equals(status);
  }
}
