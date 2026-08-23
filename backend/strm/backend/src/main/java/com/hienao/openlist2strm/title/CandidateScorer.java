package com.hienao.openlist2strm.title;

import java.util.List;

/**
 * 候选评分器（方案第十三步）。
 *
 * <p>综合本地规则、TMDB、DMDB、联网搜索的证据为每个候选打分，并给出置信度。
 *
 * <p>置信度基准采用本地标题与候选标题的拼音相似度，再叠加年份/媒体类型/季数等调整项。
 */
public final class CandidateScorer {

  private CandidateScorer() {}

  /** 评分子结果。 */
  public record Score(double score, double confidence) {}

  /**
   * 对单个元数据候选评分。
   *
   * @param candidate 元数据候选
   * @param local 本地解析结果
   * @param queryVariants 本地标题变体（含拼音）
   */
  public static Score score(
      MetadataCandidate candidate, LocalParseResult local, List<String> queryVariants) {
    String localTitle = queryVariants.isEmpty() ? null : queryVariants.get(0);

    double confidence = similarityConfidence(candidate, localTitle);
    double adjustments = 0.0;

    // 本地年份匹配
    if (local.getYear() != null
        && local.getYear().equals(candidate.getYear())) {
      adjustments += 0.10;
    }

    // 媒体类型匹配
    if (local.getMediaType() != null && candidate.getMediaType() != null) {
      boolean localMovie = "movie".equals(local.getMediaType());
      boolean candMovie = "movie".equals(candidate.getMediaType());
      if (localMovie == candMovie) {
        adjustments += 0.05;
      }
    }

    // 季数匹配
    if (local.getSeason() != null && candidate.getSeasons() != null) {
      if (candidate.getSeasons().contains(local.getSeason())) {
        adjustments += 0.05;
      }
    }

    // 仅首字母匹配惩罚
    if (initialsOnlyMatch(localTitle, candidate.getTitle())) {
      adjustments -= 0.20;
    }

    confidence = Math.max(0.0, Math.min(1.0, confidence + adjustments));
    confidence = Math.round(confidence * 100.0) / 100.0;
    return new Score(confidence * 100, confidence);
  }

  /** 基于拼音相似度计算置信度基准。 */
  private static double similarityConfidence(MetadataCandidate candidate, String localTitle) {
    if (candidate == null || candidate.getTitle() == null) {
      return 0.0;
    }
    if (localTitle == null || localTitle.isBlank()) {
      return 0.0;
    }
    String localNormalized = PinyinNormalizer.normalizeName(localTitle);
    String candNormalized = PinyinNormalizer.normalizeName(candidate.getTitle());
    String candOriginal = PinyinNormalizer.normalizeName(candidate.getOriginalTitle());
    if (localNormalized.isEmpty() || candNormalized.isEmpty()) {
      return 0.0;
    }
    if (localNormalized.equals(candNormalized) || localNormalized.equals(candOriginal)) {
      return 0.95;
    }
    double similarity =
        PinyinNormalizer.nameSimilarity(candidate.getTitle(), localTitle);
    if (similarity >= 0.95) {
      return 0.92;
    }
    if (similarity >= 0.85) {
      return 0.80;
    }
    if (similarity >= 0.70) {
      return 0.60;
    }
    if (similarity >= 0.55) {
      return 0.45;
    }
    // 中文前缀命中（如 熊出没之chong启未来 与 熊出没·重启未来 共享 熊出没）
    if (PinyinNormalizer.containsChinesePrefix(localTitle, candidate.getTitle())) {
      return 0.40;
    }
    return 0.20;
  }

  private static boolean initialsOnlyMatch(String local, String candidate) {
    if (local == null || candidate == null) {
      return false;
    }
    String localInitials = initials(local);
    String candInitials = initials(candidate);
    return !localInitials.isEmpty()
        && localInitials.equals(candInitials)
        && !PinyinNormalizer.namesEquivalent(local, candidate);
  }

  private static String initials(String text) {
    String[] words = text.trim().split("[\\s\\-·.]+");
    StringBuilder sb = new StringBuilder();
    for (String word : words) {
      if (word.isEmpty()) {
        continue;
      }
      char first = word.charAt(0);
      if (Character.UnicodeBlock.of(first) == Character.UnicodeBlock.CJK_UNIFIED_IDEOGRAPHS) {
        String pinyin = PinyinNormalizer.toPinyin(String.valueOf(first));
        if (!pinyin.isEmpty()) {
          sb.append(pinyin.charAt(0));
        }
      } else if (Character.isLetter(first)) {
        sb.append(Character.toLowerCase(first));
      }
    }
    return sb.toString();
  }
}
