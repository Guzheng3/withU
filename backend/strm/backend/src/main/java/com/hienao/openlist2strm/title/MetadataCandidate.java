package com.hienao.openlist2strm.title;

import java.util.List;
import lombok.Builder;
import lombok.Data;

/** 统一元数据候选结果。 */
@Data
@Builder
public class MetadataCandidate {

  private String source;
  private String id;
  private String title;
  private String originalTitle;
  private List<String> aliases;
  private String year;
  private String mediaType;
  private List<Integer> seasons;
  private String url;
  private String evidence;
  private double popularity;
  private Double voteAverage;
}
