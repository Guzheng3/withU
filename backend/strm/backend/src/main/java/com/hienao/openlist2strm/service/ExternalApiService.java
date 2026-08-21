package com.hienao.openlist2strm.service;

import com.hienao.openlist2strm.dto.external.ExternalApiDtos;
import com.hienao.openlist2strm.dto.media.MediaLibraryDtos;
import com.hienao.openlist2strm.entity.MediaLibraryItem;
import com.hienao.openlist2strm.exception.BusinessException;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import java.util.ArrayList;
import java.util.List;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.util.StringUtils;

/**
 * 外部媒体库接口服务（Emby 风格）。
 *
 * <p>面向第三方播放器/客户端，提供媒体库导航、详情与播放地址解析。数据源复用内部媒体库的
 * 剧集聚合与播放解析逻辑，字段命名对齐 Emby/Jellyfin 的 Item 结构。
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class ExternalApiService {

  private final MediaLibraryService mediaLibraryService;
  private final MediaLibraryItemMapper mediaLibraryItemMapper;

  /** 服务信息。 */
  public ExternalApiDtos.ServerInfo serverInfo() {
    return new ExternalApiDtos.ServerInfo(
        "ostrm",
        "2.2.6",
        "/api/external",
        true,
        List.of("movie", "tv", "anime"));
  }

  /** 分页查询媒体列表。 */
  public ExternalApiDtos.ItemPage listMedia(
      String mediaType, String keyword, int page, int pageSize) {
    int size = Math.max(1, Math.min(pageSize <= 0 ? 24 : pageSize, 100));
    int current = Math.max(1, page);
    List<MediaLibraryDtos.Summary> groups =
        mediaLibraryService.listAllGroups(null, mediaType, keyword);
    int from = Math.min((current - 1) * size, groups.size());
    int to = Math.min(from + size, groups.size());
    List<ExternalApiDtos.Item> items = new ArrayList<>();
    for (MediaLibraryDtos.Summary s : groups.subList(from, to)) {
      items.add(toItem(s));
    }
    return new ExternalApiDtos.ItemPage(groups.size(), current, size, items);
  }

  /** 获取媒体详情（含剧集列表）。 */
  public ExternalApiDtos.Detail getDetail(Long id) {
    MediaLibraryDtos.Detail detail = mediaLibraryService.getDetail(id);
    List<ExternalApiDtos.Episode> episodes =
        detail.episodes().stream()
            .map(e -> new ExternalApiDtos.Episode(e.id(), e.episodeNo(), e.sourceFileName(), e.sourcePath()))
            .toList();
    return new ExternalApiDtos.Detail(
        detail.id(),
        detail.title(),
        toType(detail.mediaType()),
        detail.mediaType(),
        detail.originalTitle(),
        detail.releaseYear(),
        detail.overview(),
        detail.posterUrl(),
        detail.backdropUrl(),
        detail.voteAverage(),
        detail.tmdbId(),
        detail.scrapeStatus(),
        episodes);
  }

  /** 媒体类型计数。 */
  public ExternalApiDtos.Counts counts() {
    List<MediaLibraryDtos.Summary> groups = mediaLibraryService.listAllGroups(null, null, null);
    long movie = groups.stream().filter(g -> "movie".equals(g.mediaType())).count();
    long series = groups.stream().filter(g -> !"movie".equals(g.mediaType())).count();
    return new ExternalApiDtos.Counts(groups.size(), movie, series);
  }

  /** 解析播放地址（供 302 重定向）。 */
  public String resolveStreamUrl(Long id) {
    return mediaLibraryService.resolvePlaybackRawUrl(id);
  }

  /** 解析指定剧集播放地址（供 302 重定向）。 */
  public String resolveEpisodeStreamUrl(Long episodeId) {
    return mediaLibraryService.resolvePlaybackRawUrl(episodeId);
  }

  private ExternalApiDtos.Item toItem(MediaLibraryDtos.Summary s) {
    return new ExternalApiDtos.Item(
        s.id(),
        s.title(),
        toType(s.mediaType()),
        s.mediaType(),
        s.originalTitle(),
        s.releaseYear(),
        null,
        s.posterUrl(),
        s.backdropUrl(),
        s.voteAverage(),
        s.tmdbId(),
        s.episodeCount(),
        null,
        null);
  }

  private String toType(String mediaType) {
    if ("movie".equals(mediaType)) {
      return "Movie";
    }
    return "Series";
  }
}
