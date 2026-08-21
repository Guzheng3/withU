package com.hienao.openlist2strm.dto.external;

import java.util.List;

/**
 * 外部媒体库接口（Emby 风格）数据结构。
 *
 * <p>字段命名对齐 Emby/Jellyfin 的 Item 结构，便于第三方播放器/客户端直接接入。
 */
public final class ExternalApiDtos {

  private ExternalApiDtos() {}

  /** 服务信息。 */
  public record ServerInfo(
      String serverName,
      String version,
      String baseUrl,
      boolean authEnabled,
      List<String> supportedMediaTypes) {}

  /** 媒体列表分页结果。 */
  public record ItemPage(long total, int page, int pageSize, List<Item> items) {}

  /** 媒体列表项（Emby 风格）。 */
  public record Item(
      Long id,
      String name,
      String type,
      String mediaType,
      String originalTitle,
      String year,
      String overview,
      String posterUrl,
      String backdropUrl,
      Double voteAverage,
      Integer tmdbId,
      Integer episodeCount,
      String sourceFileName,
      String sourcePath) {}

  /** 媒体详情。 */
  public record Detail(
      Long id,
      String name,
      String type,
      String mediaType,
      String originalTitle,
      String year,
      String overview,
      String posterUrl,
      String backdropUrl,
      Double voteAverage,
      Integer tmdbId,
      String scrapeStatus,
      List<Episode> episodes) {}

  /** 剧集条目。 */
  public record Episode(
      Long id,
      Integer episodeNo,
      String sourceFileName,
      String sourcePath) {}

  /** 媒体类型计数。 */
  public record Counts(long total, long movie, long series) {}
}
