package com.hienao.openlist2strm.title;

import com.hienao.openlist2strm.dto.media.AiRecognitionResult;
import com.hienao.openlist2strm.dto.tmdb.TmdbMovieDetail;
import com.hienao.openlist2strm.dto.tmdb.TmdbTvDetail;
import com.hienao.openlist2strm.service.AiFileNameRecognitionService;
import com.hienao.openlist2strm.service.SystemConfigService;
import com.hienao.openlist2strm.service.TmdbApiService;
import com.hienao.openlist2strm.util.TmdbIdExtractor;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;

/**
 * 片名解析主流程编排服务（方案第二至二十一步）。
 *
 * <p>流程：本地规则解析 → 生成拼音/中拼变体 → 统一查询元数据数据源（TMDB/DMDB/联网搜索）→
 * 候选评分 → AI 消歧 → 低置信度人工复核 → 去重输出。
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class MediaTitleResolver {

  private final SystemConfigService systemConfigService;
  private final AiFileNameRecognitionService aiFileNameRecognitionService;
  private final List<MetadataProvider> metadataProviders;
  private final TmdbApiService tmdbApiService;

  /**
   * 解析单个资源路径。
   *
   * @param path 完整资源路径（相对或绝对均可，含文件名）
   * @param libraryType movie / tv / anime / auto
   */
  public TitleResolveResult resolve(String path, String libraryType) {
    String fileName = fileNameOf(path);
    return resolve(fileName, path, libraryType);
  }

  /**
   * 解析单个资源。
   *
   * @param fileName 文件名（含扩展名）
   * @param relativePath 完整路径（含文件名）
   * @param libraryType movie / tv / anime / auto
   */
  public TitleResolveResult resolve(String fileName, String relativePath, String libraryType) {
    LocalParseResult local = MediaPathParser.parse(fileName, relativePath, libraryType);

    // 辅助文件跳过
    if (local.isSkip()) {
      return TitleResolveResult.builder()
          .path(relativePath)
          .id(id(relativePath))
          .title(null)
          .year(null)
          .season(null)
          .episode(null)
          .mediaType(local.getMediaType())
          .confidence(0)
          .status("unresolved")
          .message(local.getSkipReason())
          .local(local)
          .build();
    }

    if (local.getTitleCandidates() == null || local.getTitleCandidates().isEmpty()) {
      return TitleResolveResult.builder()
          .path(relativePath)
          .id(id(relativePath))
          .title(null)
          .year(null)
          .season(null)
          .episode(null)
          .mediaType(local.getMediaType())
          .confidence(0)
          .status("unresolved")
          .message("无法从路径识别媒体标题")
          .local(local)
          .build();
    }

    // 路径/文件名含 TMDB ID 标记（如「不能说的秘密 tmdb-20342」或 {tmdbid-20342}）时直接确认，
    // 避免同名翻拍等场景下依赖搜索评分导致误匹配或降级到人工复核。
    TitleResolveResult byTmdbId = resolveByPathTmdbId(relativePath, fileName, libraryType, local);
    if (byTmdbId != null) {
      return byTmdbId;
    }

    // 生成搜索变体（含拼音）
    List<String> queryVariants = new ArrayList<>();
    for (String candidate : local.getTitleCandidates()) {
      for (String variant : PinyinNormalizer.buildVariants(candidate)) {
        if (!queryVariants.contains(variant)) {
          queryVariants.add(variant);
        }
      }
    }
    if (queryVariants.isEmpty()) {
      queryVariants.add(local.getTitleCandidates().get(0));
    }

    // 按配置的元数据来源顺序查询（默认 tmdb → douban）。
    // 与 conference 的 MetadataProviderService 保持一致：单一来源命中高置信度即停止，
    // 避免跨年/低相似度来源的候选稀释评分。
    List<String> sourceOrder = configuredSources();
    List<MetadataCandidate> allCandidates = new ArrayList<>();
    for (String sourceName : sourceOrder) {
      MetadataProvider provider =
          metadataProviders.stream()
              .filter(p -> sourceName.equalsIgnoreCase(p.name()))
              .findFirst()
              .orElse(null);
      if (provider == null || !provider.isEnabled()) {
        continue;
      }
      List<MetadataCandidate> found = new ArrayList<>();
      for (String variant : queryVariants) {
        try {
          List<MetadataCandidate> hits = provider.search(variant, local.getYear(), local.getMediaType());
          for (MetadataCandidate c : hits) {
            c.setEvidence(c.getEvidence() + "（查询词: " + variant + "）");
          }
          found.addAll(hits);
        } catch (Exception e) {
          log.warn("数据源 {} 查询失败: variant={}", provider.name(), variant, e);
        }
      }
      // 跨年过滤：本地年份明确时，丢弃年份不同且已发布的候选（保留无年份候选由详情确认）
      if (local.getYear() != null && !local.getYear().isBlank()) {
        found = filterMismatchedYears(found, local.getYear());
      }
      allCandidates.addAll(found);
      // 来源命中高置信度候选后停止后续来源，减少外部请求
      if (!found.isEmpty() && hasHighConfidence(found, local, queryVariants)) {
        log.debug("数据源 {} 命中高置信度候选，停止查询后续来源", sourceName);
        break;
      }
    }

    // 评分并选择最佳
    BestCandidate best = selectBest(deduplicateCandidates(allCandidates), local, queryVariants);

    // 置信度过低时尝试 AI 消歧
    if ((best == null || best.confidence() < 0.75) && isAiEnabled()) {
      AiRecognitionResult aiResult =
          aiFileNameRecognitionService.recognizeFileName(fileName, relativePath, libraryType);
      if (aiResult != null && aiResult.isSuccess() && aiResult.isNewFormat()) {
        TitleResolveResult aiResolved =
            buildFromAi(aiResult, local, relativePath, fileName);
        if (aiResolved != null && aiResolved.getTitle() != null && !aiResolved.getTitle().isBlank()) {
          return aiResolved;
        }
      }
    }

    if (best == null) {
      // 本地候选直接输出，状态为待复核（不虚构数据）
      String localTitle = local.getTitleCandidates().get(0);
      return TitleResolveResult.builder()
          .path(relativePath)
          .id(id(relativePath))
          .title(localTitle)
          .year(local.getYear())
          .season(local.getSeason())
          .episode(local.getEpisode())
          .mediaType(local.getMediaType())
          .confidence(Math.min(0.7, local.getConfidence()))
          .status("need_review")
          .message("未找到外部元数据确认，使用本地识别结果")
          .local(local)
          .build();
    }

    // 依据置信度确定状态
    String status = best.confidence() >= 0.9 ? "confirmed" : "need_review";
    return TitleResolveResult.builder()
        .path(relativePath)
        .id(id(relativePath))
        .title(best.candidate().getTitle())
        .year(best.candidate().getYear() != null ? best.candidate().getYear() : local.getYear())
        .season(local.getSeason())
        .episode(local.getEpisode())
        .mediaType(best.candidate().getMediaType() != null ? best.candidate().getMediaType() : local.getMediaType())
        .tmdbId(best.candidate().getId())
        .confidence(best.confidence())
        .status(status)
        .evidenceIds(best.evidenceIds())
        .message("匹配到外部元数据: " + best.candidate().getTitle())
        .local(local)
        .metadataCandidates(best.matched())
        .build();
  }

  private record BestCandidate(
      MetadataCandidate candidate, double confidence, List<String> evidenceIds, List<MetadataCandidate> matched) {}

  private List<MetadataCandidate> deduplicateCandidates(List<MetadataCandidate> candidates) {
    Map<String, MetadataCandidate> unique = new LinkedHashMap<>();
    for (MetadataCandidate candidate : candidates) {
      String key = candidate.getSource() + ":" + candidate.getId();
      MetadataCandidate existing = unique.get(key);
      if (existing == null) {
        unique.put(key, candidate);
      } else if (candidate.getEvidence() != null
          && !candidate.getEvidence().equals(existing.getEvidence())) {
        existing.setEvidence(existing.getEvidence() + "; " + candidate.getEvidence());
      }
    }
    return new ArrayList<>(unique.values());
  }

  /** 对候选评分并选择最佳。 */
  private BestCandidate selectBest(
      List<MetadataCandidate> allCandidates, LocalParseResult local, List<String> queryVariants) {
    if (allCandidates.isEmpty()) {
      return null;
    }
    List<ScoredCandidate> scored = new ArrayList<>();
    for (MetadataCandidate candidate : allCandidates) {
      CandidateScorer.Score s = CandidateScorer.score(candidate, local, queryVariants);
      scored.add(new ScoredCandidate(candidate, s.score(), s.confidence()));
    }
    scored.sort(
        Comparator.comparingDouble((ScoredCandidate sc) -> sc.score())
            .reversed()
            .thenComparing(
                sc ->
                    sc.candidate().getVoteAverage() == null
                        ? 0.0
                        : sc.candidate().getVoteAverage(),
                Comparator.reverseOrder()));

    ScoredCandidate top = scored.get(0);
    // 同名候选（不同 id）惩罚
    double confidence = top.confidence();
    long sameName = scored.stream()
        .filter(sc -> PinyinNormalizer.namesEquivalent(sc.candidate().getTitle(), top.candidate().getTitle()))
        .count();
    if (sameName > 1) {
      confidence = Math.max(0, confidence - 0.25);
    }
    List<MetadataCandidate> matched =
        scored.stream()
            .filter(sc -> PinyinNormalizer.namesEquivalent(sc.candidate().getTitle(), top.candidate().getTitle()))
            .map(ScoredCandidate::candidate)
            .toList();
    List<String> evidenceIds = new ArrayList<>();
    for (MetadataCandidate c : matched) {
      evidenceIds.add(c.getSource() + ":" + c.getId());
    }
    return new BestCandidate(top.candidate(), Math.min(1.0, confidence), evidenceIds, matched);
  }

  private record ScoredCandidate(MetadataCandidate candidate, double score, double confidence) {}

  private TitleResolveResult buildFromAi(
      AiRecognitionResult aiResult, LocalParseResult local, String path, String fileName) {
    return TitleResolveResult.builder()
        .path(path)
        .id(id(path))
        .title(aiResult.getTitle())
        .year(aiResult.getYear() != null ? aiResult.getYear() : local.getYear())
        .season(aiResult.getSeason() != null ? aiResult.getSeason() : local.getSeason())
        .episode(aiResult.getEpisode() != null ? aiResult.getEpisode() : local.getEpisode())
        .mediaType(aiResult.getType() != null ? aiResult.getType() : local.getMediaType())
        .confidence(0.95)
        .status("confirmed")
        .evidenceIds(List.of("ai:" + fileName))
        .message("AI 识别结果")
        .local(local)
        .build();
  }

  private boolean isAiEnabled() {
    try {
      Map<String, Object> aiConfig = systemConfigService.getAiConfig();
      return Boolean.TRUE.equals(aiConfig.getOrDefault("enabled", false));
    } catch (Exception e) {
      return false;
    }
  }

  /** 读取配置的元数据来源顺序，未配置或为空时使用默认顺序。 */
  /**
   * 从路径/文件名中提取 TMDB ID 标记并直接确认匹配（跳过搜索评分）。
   *
   * <p>支持「不能说的秘密 tmdb-20342」「{tmdbid-20342}」等目录/文件命名。命中时以 TMDB 详情
   * 构造 confirmed 结果，媒体类型由路径标记中的 movie/tv 或本地解析决定。
   */
  private TitleResolveResult resolveByPathTmdbId(
      String relativePath, String fileName, String libraryType, LocalParseResult local) {
    Integer tmdbId = TmdbIdExtractor.extractTmdbIdFromPath(relativePath);
    if (tmdbId == null) {
      tmdbId = TmdbIdExtractor.extractTmdbIdFromFileName(fileName);
    }
    if (tmdbId == null) {
      return null;
    }
    try {
      boolean movie = !isTvLike(libraryType);
      if ("tv".equalsIgnoreCase(local.getMediaType()) || "anime".equalsIgnoreCase(libraryType)) {
        movie = false;
      }
      if (movie) {
        TmdbMovieDetail detail = tmdbApiService.getMovieDetail(tmdbId);
        return TitleResolveResult.builder()
            .path(relativePath)
            .id(id(relativePath))
            .title(detail.getTitle())
            .year(detail.getReleaseYear())
            .season(local.getSeason())
            .episode(local.getEpisode())
            .mediaType("movie")
            .tmdbId(String.valueOf(detail.getId()))
            .confidence(1.0)
            .status("confirmed")
            .evidenceIds(List.of("tmdb-path-mark:" + detail.getId()))
            .message("路径含 TMDB ID 标记，直接匹配: " + detail.getTitle())
            .local(local)
            .build();
      }
      TmdbTvDetail detail = tmdbApiService.getTvDetail(tmdbId);
      return TitleResolveResult.builder()
          .path(relativePath)
          .id(id(relativePath))
          .title(detail.getName())
          .year(detail.getFirstAirYear())
          .season(local.getSeason())
          .episode(local.getEpisode())
          .mediaType("tv")
          .tmdbId(String.valueOf(detail.getId()))
          .confidence(1.0)
          .status("confirmed")
          .evidenceIds(List.of("tmdb-path-mark:" + detail.getId()))
          .message("路径含 TMDB ID 标记，直接匹配: " + detail.getName())
          .local(local)
          .build();
    } catch (Exception e) {
      log.warn(
          "路径 TMDB ID 标记匹配失败，回退普通识别: path={}, tmdbId={}, err={}",
          relativePath,
          tmdbId,
          e.getMessage());
      return null;
    }
  }

  private boolean isTvLike(String libraryType) {
    return libraryType != null
        && ("tv".equalsIgnoreCase(libraryType) || "anime".equalsIgnoreCase(libraryType));
  }

  @SuppressWarnings("unchecked")
  private List<String> configuredSources() {    try {
      Map<String, Object> scraping = systemConfigService.getScrapingConfig();
      Object raw = scraping.get("metadataSources");
      if (raw instanceof List<?> values && !values.isEmpty()) {
        return values.stream().map(String::valueOf).toList();
      }
    } catch (Exception e) {
      log.warn("读取元数据来源顺序失败，使用默认顺序: {}", e.getMessage());
    }
    return List.of("tmdb", "douban");
  }

  /** 过滤本地年份明确但候选年份不同且已发布的条目（保留无年份候选交给详情确认）。 */
  private List<MetadataCandidate> filterMismatchedYears(
      List<MetadataCandidate> candidates, String localYear) {
    List<MetadataCandidate> kept = new ArrayList<>();
    for (MetadataCandidate c : candidates) {
      if (c.getYear() == null || c.getYear().isBlank() || localYear.equals(c.getYear())) {
        kept.add(c);
      }
    }
    if (kept.isEmpty() && !candidates.isEmpty()) {
      // 全部跨年时保留原列表，交由评分处理（可能本地年份本身有误）
      return candidates;
    }
    return kept;
  }

  /** 判断候选列表是否已出现高置信度匹配（≥0.9，即 confirmed 阈值）。 */
  private boolean hasHighConfidence(
      List<MetadataCandidate> candidates, LocalParseResult local, List<String> queryVariants) {
    for (MetadataCandidate c : candidates) {
      if (CandidateScorer.score(c, local, queryVariants).confidence() >= 0.9) {
        return true;
      }
    }
    return false;
  }

  private static String fileNameOf(String path) {
    if (path == null || path.isBlank()) {
      return "";
    }
    String[] parts = path.split("[/\\\\]+");
    return parts[parts.length - 1];
  }

  private static String id(String path) {
    return "item_" + UUID.nameUUIDFromBytes(String.valueOf(path).getBytes()).toString().substring(0, 8);
  }
}
