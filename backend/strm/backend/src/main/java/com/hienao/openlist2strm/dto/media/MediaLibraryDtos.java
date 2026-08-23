package com.hienao.openlist2strm.dto.media;

import java.util.List;

/** 媒体库接口数据结构。 */
public final class MediaLibraryDtos {

  private MediaLibraryDtos() {}

  /** 分页结果。 */
  public record PageResult(long total, int page, int pageSize, List<Summary> items) {}

  /** 媒体条目列表项。 */
  public record Summary(
      Long id,
      String title,
      String originalTitle,
      String releaseYear,
      String posterUrl,
      String backdropUrl,
      Double voteAverage,
      String mediaType,
      Integer tmdbId,
      String scrapeStatus,
      Long taskId,
      int episodeCount) {}

  /** 播放来源（同一集可能有多个来源文件）。 */
  public record Source(
      Long id,
      String sourceFileName,
      String sourcePath,
      String strmPath,
      String resolution,
      int rank) {}

  /** 集数条目（同集多来源合并，sources 按分辨率优先级降序）。 */
  public record Episode(Long id, String sourceFileName, String sourcePath, int episodeNo, List<Source> sources) {}

  /** 媒体条目详情。 */
  public record Detail(
      Long id,
      Long taskId,
      String sourcePath,
      String strmPath,
      String sourceFileName,
      String mediaType,
      Integer tmdbId,
      String title,
      String originalTitle,
      String releaseYear,
      String overview,
      String posterUrl,
      String backdropUrl,
      Double voteAverage,
      String scrapeStatus,
      String createdAt,
      String updatedAt,
      Integer totalEpisodes,
      List<Episode> episodes) {}

  /** 播放地址解析结果。 */
  public record PlaybackResult(Long id, String title, String url, String mediaType) {}

  /** 任务筛选项。 */
  public record TaskOption(Long id, String taskName) {}
}
