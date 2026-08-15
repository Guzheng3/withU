package com.hienao.openlist2strm.entity;

import java.time.LocalDateTime;
import lombok.Data;
import lombok.EqualsAndHashCode;
import lombok.experimental.Accessors;

@Data
@EqualsAndHashCode(callSuper = false)
@Accessors(chain = true)
public class MediaLibraryItem {
  private Long id;
  private Long taskId;
  private Long openlistConfigId;
  private String sourcePath;
  private String strmPath;
  private String sourceFileName;
  private String mediaType;
  private Integer tmdbId;
  private String title;
  private String originalTitle;
  private String releaseYear;
  private String overview;
  private String posterUrl;
  private String backdropUrl;
  private Double voteAverage;
  private String scrapeStatus;
  private LocalDateTime createdAt;
  private LocalDateTime updatedAt;
  private LocalDateTime deletedAt;
}
