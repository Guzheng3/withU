package com.hienao.openlist2strm.service;

import com.hienao.openlist2strm.dto.media.MediaLibraryDtos;
import com.hienao.openlist2strm.entity.MediaLibraryItem;
import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.exception.BusinessException;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.notification.ScrapeOutcome;
import java.util.List;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.util.StringUtils;

/** 媒体库索引与播放解析服务。 */
@Slf4j
@Service
@RequiredArgsConstructor
public class MediaLibraryService {

  private static final int MAX_PAGE_SIZE = 60;

  private final MediaLibraryItemMapper mediaLibraryItemMapper;
  private final OpenlistConfigService openlistConfigService;
  private final OpenlistApiService openlistApiService;
  private final TaskConfigService taskConfigService;
  private final TmdbApiService tmdbApiService;

  /** 幂等保存一个成功生成 STRM 的媒体条目。 */
  @Transactional(rollbackFor = Exception.class)
  public void recordGeneratedFile(
      TaskConfig task,
      OpenlistConfig openlistConfig,
      String sourcePath,
      String strmPath,
      String sourceFileName,
      String mediaType,
      String title,
      ScrapeOutcome scrapeOutcome) {

    MediaLibraryItem item = new MediaLibraryItem();
    item.setTaskId(task.getId());
    item.setOpenlistConfigId(openlistConfig.getId());
    item.setSourcePath(sourcePath);
    item.setStrmPath(strmPath);
    item.setSourceFileName(sourceFileName);
    item.setMediaType(resolveMediaType(mediaType, task));
    item.setTitle(StringUtils.hasText(title) ? title : stripExtension(sourceFileName));
    item.setOriginalTitle(item.getTitle());
    if (scrapeOutcome != null) {
      item.setTmdbId(scrapeOutcome.tmdbId());
      item.setScrapeStatus(scrapeOutcome.status() == null ? null : scrapeOutcome.status().name());
      if (scrapeOutcome.releaseYear() != null) {
        item.setReleaseYear(String.valueOf(scrapeOutcome.releaseYear()));
      }
      item.setOverview(scrapeOutcome.overview());
      item.setPosterUrl(scrapeOutcome.posterUrl());
      item.setBackdropUrl(scrapeOutcome.backdropUrl());
      item.setVoteAverage(scrapeOutcome.voteAverage());
    }
    mediaLibraryItemMapper.upsert(item);
  }

  /** 分页查询媒体库（按剧集聚合：同 tmdbId 或同目录视为同一部剧）。 */
  public MediaLibraryDtos.PageResult query(
      Long taskId, String mediaType, String keyword, int page, int pageSize) {
    int size = Math.max(1, Math.min(pageSize <= 0 ? 24 : pageSize, MAX_PAGE_SIZE));
    int current = Math.max(1, page);
    List<MediaLibraryDtos.Summary> groups = listAllGroups(taskId, mediaType, keyword);
    int offset = (current - 1) * size;
    int from = Math.min(offset, groups.size());
    int to = Math.min(offset + size, groups.size());
    List<MediaLibraryDtos.Summary> pageItems = groups.subList(from, to);
    return new MediaLibraryDtos.PageResult(groups.size(), current, size, pageItems);
  }

  /** 返回全量聚合媒体列表（外部接口使用，不分页）。 */
  public List<MediaLibraryDtos.Summary> listAllGroups(
      Long taskId, String mediaType, String keyword) {
    List<MediaLibraryItem> all =
        mediaLibraryItemMapper.selectAll(taskId, normalizeType(mediaType), keyword);
    return aggregate(all);
  }

  /** 获取详情（含集数列表，同集多来源合并）。 */
  public MediaLibraryDtos.Detail getDetail(Long id) {
    MediaLibraryItem item = requireItem(id);
    List<MediaLibraryItem> siblings = siblingsOf(item);
    String groupTitle = groupTitle(siblings);
    List<MediaLibraryDtos.Episode> episodes = buildEpisodes(siblings);
    Integer totalEpisodes = fetchTotalEpisodes(item);
    return toDetail(item, groupTitle, episodes, totalEpisodes);
  }

  /** 查询 TMDB 剧集总集数，仅电视剧返回，失败或电影返回 null。 */
  private Integer fetchTotalEpisodes(MediaLibraryItem item) {
    if (!"tv".equals(item.getMediaType()) || item.getTmdbId() == null) {
      return null;
    }
    try {
      return tmdbApiService.getTvDetail(item.getTmdbId()).getNumberOfEpisodes();
    } catch (Exception e) {
      log.warn("查询 TMDB 总集数失败: tmdbId={}, 错误: {}", item.getTmdbId(), e.getMessage());
      return null;
    }
  }

  /**
   * 解析播放地址。
   *
   * <p>同一集存在多个来源时：未指定 sourceId 则按分辨率优先级（4K→1080P→720P→480P→未知）自动选择；
   * 指定 sourceId 则优先使用该来源。选中的来源解析失败时自动回退到下一个优先级的来源。
   *
   * @param id 媒体条目 ID（用于定位所属剧集分组）
   * @param sourceId 可选，指定使用的来源条目 ID
   */
  public MediaLibraryDtos.PlaybackResult resolvePlayback(Long id, Long sourceId) {
    MediaLibraryItem item = requireItem(id);
    List<MediaLibraryItem> candidates = playbackCandidates(item, sourceId);
    BusinessException lastError = null;
    for (MediaLibraryItem candidate : candidates) {
      try {
        String rawUrl = resolveRawUrl(candidate);
        return new MediaLibraryDtos.PlaybackResult(
            candidate.getId(), candidate.getTitle(), rawUrl, candidate.getMediaType());
      } catch (BusinessException e) {
        lastError = e;
        log.warn(
            "播放来源解析失败，尝试下一来源: id={}, name={}, 错误: {}",
            candidate.getId(),
            candidate.getSourceFileName(),
            e.getMessage());
      }
    }
    if (lastError != null) {
      throw lastError;
    }
    throw new BusinessException("该集没有可用的播放来源");
  }

  /**
   * 解析媒体播放原始地址（供外部接口 302 重定向使用），自动选择最高分辨率来源。
   *
   * @param id 媒体条目 ID
   * @return raw_url 直链地址
   */
  public String resolvePlaybackRawUrl(Long id) {
    MediaLibraryItem item = requireItem(id);
    List<MediaLibraryItem> candidates = playbackCandidates(item, null);
    BusinessException lastError = null;
    for (MediaLibraryItem candidate : candidates) {
      try {
        return resolveRawUrl(candidate);
      } catch (BusinessException e) {
        lastError = e;
        log.warn(
            "播放来源解析失败，尝试下一来源: id={}, name={}, 错误: {}",
            candidate.getId(),
            candidate.getSourceFileName(),
            e.getMessage());
      }
    }
    if (lastError != null) {
      throw lastError;
    }
    throw new BusinessException("该媒体没有可用的播放来源");
  }

  /** 计算播放候选来源：指定 sourceId 时只解析该来源，否则取同集全部来源按分辨率降序。 */
  private List<MediaLibraryItem> playbackCandidates(MediaLibraryItem item, Long sourceId) {
    if (sourceId != null && !sourceId.equals(item.getId())) {
      MediaLibraryItem chosen =
          mediaLibraryItemMapper.selectById(sourceId);
      if (chosen != null && sameEpisode(chosen, item)) {
        return List.of(chosen);
      }
      if (chosen != null) {
        log.warn(
            "指定的来源不属于同一集，忽略 sourceId: {}（目标集号 {}）",
            sourceId,
            extractEpisodeNo(item));
      }
    }
    return siblingsOf(item).stream()
        .filter(s -> sameEpisode(s, item))
        .sorted(java.util.Comparator.comparingInt(this::resolutionRank).reversed())
        .toList();
  }

  /** 判断两个条目是否属于同一集（电影按整个作品聚合，电视剧按集号判断）。 */
  private boolean sameEpisode(MediaLibraryItem a, MediaLibraryItem b) {
    if (!String.valueOf(a.getMediaType()).equals(String.valueOf(b.getMediaType()))) {
      return false;
    }
    if ("movie".equals(a.getMediaType())) {
      return true;
    }
    return extractEpisodeNo(a) == extractEpisodeNo(b);
  }

  /** 将同一剧的全部条目按集号聚合成剧集列表，同集多来源合并并按分辨率降序。 */
  private List<MediaLibraryDtos.Episode> buildEpisodes(List<MediaLibraryItem> siblings) {
    java.util.Map<Integer, List<MediaLibraryItem>> byEpisode = new java.util.LinkedHashMap<>();
    for (MediaLibraryItem s : siblings) {
      int no = extractEpisodeNo(s);
      byEpisode.computeIfAbsent(no, k -> new java.util.ArrayList<>()).add(s);
    }
    List<MediaLibraryDtos.Episode> episodes = new java.util.ArrayList<>();
    for (List<MediaLibraryItem> group : byEpisode.values()) {
      group.sort(java.util.Comparator.comparingInt(this::resolutionRank).reversed());
      MediaLibraryItem primary = group.get(0);
      List<MediaLibraryDtos.Source> sources =
          group.stream()
              .map(
                  s ->
                      new MediaLibraryDtos.Source(
                          s.getId(),
                          s.getSourceFileName(),
                          s.getSourcePath(),
                          s.getStrmPath(),
                          resolutionLabel(s),
                          resolutionRank(s)))
              .toList();
      episodes.add(
          new MediaLibraryDtos.Episode(
              primary.getId(),
              primary.getSourceFileName(),
              primary.getSourcePath(),
              extractEpisodeNo(primary),
              sources));
    }
    episodes.sort(java.util.Comparator.comparingInt(MediaLibraryDtos.Episode::episodeNo));
    return episodes;
  }

  /** 从文件名解析分辨率优先级：4K=4，1080P=3，720P=2，480P=1，未知=0。 */
  private int resolutionRank(MediaLibraryItem item) {
    String name = item.getSourceFileName();
    if (name == null) {
      return 0;
    }
    String lower = name.toLowerCase();
    if (lower.contains("2160p") || lower.contains("4k") || lower.contains("uhd")) {
      return 4;
    }
    if (lower.contains("1080p") || lower.contains("1080")) {
      return 3;
    }
    if (lower.contains("720p")) {
      return 2;
    }
    if (lower.contains("480p") || lower.contains("480")) {
      return 1;
    }
    return 0;
  }

  /** 分辨率显示标签。 */
  private String resolutionLabel(MediaLibraryItem item) {
    return switch (resolutionRank(item)) {
      case 4 -> "4K";
      case 3 -> "1080P";
      case 2 -> "720P";
      case 1 -> "480P";
      default -> "未知";
    };
  }

  private String resolveRawUrl(MediaLibraryItem item) {
    OpenlistConfig config = openlistConfigService.getById(item.getOpenlistConfigId());
    if (config == null) {
      throw new BusinessException("媒体关联的 OpenList 配置不存在");
    }
    if (!Boolean.TRUE.equals(config.getIsActive())) {
      throw new BusinessException("媒体关联的 OpenList 配置已停用");
    }
    return openlistApiService.resolveRawUrl(config, item.getSourcePath());
  }

  /** 可用于筛选的任务列表。 */
  public List<MediaLibraryDtos.TaskOption> listTaskOptions() {
    return taskConfigService.getAllConfigs().stream()
        .map(task -> new MediaLibraryDtos.TaskOption(task.getId(), task.getTaskName()))
        .toList();
  }

  private MediaLibraryItem requireItem(Long id) {
    if (id == null) {
      throw new BusinessException("媒体 ID 不能为空");
    }
    MediaLibraryItem item = mediaLibraryItemMapper.selectById(id);
    if (item == null) {
      throw new BusinessException("媒体条目不存在，ID: " + id);
    }
    return item;
  }

  /** 解析媒体类型：优先使用传入值，auto 或空时回退到任务 libraryType，最终回退 movie。 */
  private String resolveMediaType(String mediaType, TaskConfig task) {
    String t = mediaType == null ? null : mediaType.trim().toLowerCase();
    if ("movie".equals(t) || "tv".equals(t) || "anime".equals(t)) {
      return t;
    }
    if (task.getLibraryType() != null && !"auto".equals(task.getLibraryType())) {
      return task.getLibraryType();
    }
    return "movie";
  }

  private String normalizeType(String mediaType) {
    if (mediaType == null || mediaType.isBlank()) {
      return null;
    }
    String t = mediaType.trim().toLowerCase();
    if ("movie".equals(t) || "tv".equals(t)) {
      return t;
    }
    return null;
  }

  private String stripExtension(String fileName) {
    if (!StringUtils.hasText(fileName)) {
      return "未知媒体";
    }
    int dot = fileName.lastIndexOf('.');
    return dot > 0 ? fileName.substring(0, dot) : fileName;
  }

  private MediaLibraryDtos.Summary toSummary(MediaLibraryItem item) {
    return new MediaLibraryDtos.Summary(
        item.getId(),
        item.getTitle(),
        item.getOriginalTitle(),
        item.getReleaseYear(),
        item.getPosterUrl(),
        item.getBackdropUrl(),
        item.getVoteAverage(),
        item.getMediaType(),
        item.getTmdbId(),
        item.getScrapeStatus(),
        item.getTaskId(),
        1);
  }

  private MediaLibraryDtos.Detail toDetail(MediaLibraryItem item) {
    return toDetail(item, null, List.of(), null);
  }

  private MediaLibraryDtos.Detail toDetail(
      MediaLibraryItem item, String displayTitle, List<MediaLibraryDtos.Episode> episodes) {
    return toDetail(item, displayTitle, episodes, null);
  }

  private MediaLibraryDtos.Detail toDetail(
      MediaLibraryItem item,
      String displayTitle,
      List<MediaLibraryDtos.Episode> episodes,
      Integer totalEpisodes) {
    return new MediaLibraryDtos.Detail(
        item.getId(),
        item.getTaskId(),
        item.getSourcePath(),
        item.getStrmPath(),
        item.getSourceFileName(),
        item.getMediaType(),
        item.getTmdbId(),
        displayTitle == null ? item.getTitle() : displayTitle,
        item.getOriginalTitle(),
        item.getReleaseYear(),
        item.getOverview(),
        item.getPosterUrl(),
        item.getBackdropUrl(),
        item.getVoteAverage(),
        item.getScrapeStatus(),
        item.getCreatedAt() == null ? null : item.getCreatedAt().toString(),
        item.getUpdatedAt() == null ? null : item.getUpdatedAt().toString(),
        totalEpisodes,
        episodes);
  }

  /** 按剧集分组聚合：优先 tmdbId，其次 sourcePath 父目录。 */
  private List<MediaLibraryDtos.Summary> aggregate(List<MediaLibraryItem> all) {
    List<MediaLibraryDtos.Summary> groups = new java.util.ArrayList<>();
    for (java.util.List<MediaLibraryItem> group : groupBy(all).values()) {
      group.sort(java.util.Comparator.comparingInt(this::extractEpisodeNo));
      MediaLibraryItem rep = group.get(0);
      String title = groupTitle(group);
      groups.add(
          new MediaLibraryDtos.Summary(
              rep.getId(),
              title,
              rep.getOriginalTitle(),
              rep.getReleaseYear(),
              rep.getPosterUrl(),
              rep.getBackdropUrl(),
              rep.getVoteAverage(),
              rep.getMediaType(),
              rep.getTmdbId(),
              rep.getScrapeStatus(),
              rep.getTaskId(),
              group.size()));
    }
    groups.sort(
        (a, b) -> {
          int byType = String.valueOf(a.mediaType()).compareTo(String.valueOf(b.mediaType()));
          if (byType != 0) {
            return byType;
          }
          return String.valueOf(a.title()).compareTo(String.valueOf(b.title()));
        });
    return groups;
  }

  private java.util.LinkedHashMap<String, java.util.List<MediaLibraryItem>> groupBy(
      List<MediaLibraryItem> all) {
    java.util.LinkedHashMap<String, java.util.List<MediaLibraryItem>> map =
        new java.util.LinkedHashMap<>();
    for (MediaLibraryItem item : all) {
      String key = groupKey(item);
      map.computeIfAbsent(key, k -> new java.util.ArrayList<>()).add(item);
    }
    return map;
  }

  private String groupKey(MediaLibraryItem item) {
    if (item.getTmdbId() != null) {
      return "tid:" + item.getTmdbId();
    }
    String path = item.getSourcePath();
    int slash = path == null ? -1 : path.lastIndexOf('/');
    return "dir:" + (slash > 0 ? path.substring(0, slash) : path);
  }

  /** 取同一部剧的所有条目（与 groupKey 相同的条目）。 */
  private List<MediaLibraryItem> siblingsOf(MediaLibraryItem item) {
    String key = groupKey(item);
    List<MediaLibraryItem> siblings = new java.util.ArrayList<>();
    for (MediaLibraryItem each :
        mediaLibraryItemMapper.selectAll(null, null, null)) {
      if (groupKey(each).equals(key)) {
        siblings.add(each);
      }
    }
    siblings.sort(java.util.Comparator.comparingInt(this::extractEpisodeNo));
    return siblings;
  }

  /** 从标题或文件名解析集号，无法解析返回 Integer.MAX_VALUE（排后面）。 */
  private int extractEpisodeNo(MediaLibraryItem item) {
    int no = extractFrom(item.getTitle());
    if (no != Integer.MAX_VALUE) {
      return no;
    }
    return extractFrom(item.getSourceFileName());
  }

  private int extractFrom(String text) {
    if (text == null) {
      return Integer.MAX_VALUE;
    }
    java.util.regex.Matcher m =
        java.util.regex.Pattern.compile("第\\s*(\\d+)\\s*集").matcher(text);
    if (m.find()) {
      return Integer.parseInt(m.group(1));
    }
    m = java.util.regex.Pattern.compile("E(\\d+)", java.util.regex.Pattern.CASE_INSENSITIVE)
        .matcher(text);
    if (m.find()) {
      return Integer.parseInt(m.group(1));
    }
    m = java.util.regex.Pattern.compile("\\b(\\d{1,2})\\b").matcher(text);
    if (m.find()) {
      return Integer.parseInt(m.group(1));
    }
    return Integer.MAX_VALUE;
  }

  /** 聚合组标题：优先 tmdbId 组的清洗标题，目录组取目录最后一段，清洗集号/扩展名。 */
  private String groupTitle(List<MediaLibraryItem> group) {
    MediaLibraryItem rep = group.get(0);
    if ("movie".equals(rep.getMediaType())) {
      String t = rep.getTitle();
      if (t != null && !t.isBlank()) {
        String cleaned = t.replaceAll("(?i)[.\\s]*2160p.*$", "")
            .replaceAll("(?i)[.\\s]*1080p.*$", "")
            .replaceAll("(?i)\\s*[（(].*$", "")
            .replaceAll("(?i)[.\\s]*\\d{4}.*$", "")
            .trim();
        if (cleaned.isBlank()) {
          cleaned = t;
        }
        return cleaned;
      }
      String fileName = stripExtension(rep.getSourceFileName());
      return fileName == null ? "未知媒体" : fileName;
    }
    if (rep.getTmdbId() != null) {
      String t = rep.getTitle();
      if (t != null && !t.isBlank()) {
        String cleaned = t.replaceAll("\\s*[（(]?第\\s*\\d+\\s*集[)）]?\\s*$", "")
            .replaceAll("(?i)[.\\s]*S\\d{1,2}E\\d+.*$", "")
            .trim();
        return cleaned.isBlank() ? t : cleaned;
      }
    }
    String path = rep.getSourcePath();
    if (path != null) {
      String dir = path;
      int slash = dir.lastIndexOf('/');
      if (slash > 0) {
        dir = dir.substring(0, slash);
        slash = dir.lastIndexOf('/');
        if (slash >= 0) {
          dir = dir.substring(slash + 1);
        }
      }
      String cleaned = dir.replaceAll("(?i)S\\d{1,2}E\\d+.*$", "").trim();
      return cleaned.isBlank() ? dir : cleaned;
    }
    String t = rep.getTitle();
    return t == null ? "未知媒体" : t;
  }
}
