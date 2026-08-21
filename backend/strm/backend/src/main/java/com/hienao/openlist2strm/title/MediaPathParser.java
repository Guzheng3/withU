package com.hienao.openlist2strm.title;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * 本地规则解析引擎（方案第一步至第七步）。
 *
 * <p>流程：读取完整路径 → 拆分文件名和目录 → 过滤字幕/图片/清晰度目录 → 识别电影/剧集 →
 * 提取年份/季/集 → 生成文件名和目录片名候选 → 清理拼音/中文/混合命名。
 */
public final class MediaPathParser {

  private static final Pattern EXTENSION_RE = Pattern.compile("(?i)\\.([a-z0-9]{1,5})$");

  private MediaPathParser() {}

  /** 拆分路径为段。 */
  public static List<String> splitPath(String path) {
    if (path == null || path.isBlank()) {
      return List.of();
    }
    return Pattern.compile("[/\\\\]+")
        .splitAsStream(path.replace("\\", "/"))
        .filter(segment -> !segment.isBlank())
        .toList();
  }

  /** 是否为视频文件（按扩展名）。 */
  public static boolean isVideoFile(String filename) {
    String ext = extensionOf(filename);
    return ext != null && TitleConstants.VIDEO_EXTENSIONS.contains(ext);
  }

  /** 是否为应忽略的辅助文件（字幕、图片、文本等）。 */
  public static boolean isIgnoredFile(String filename) {
    String ext = extensionOf(filename);
    return ext != null && TitleConstants.IGNORED_EXTENSIONS.contains(ext);
  }

  /** 提取小写扩展名（含点）。 */
  public static String extensionOf(String filename) {
    if (filename == null || filename.isBlank()) {
      return null;
    }
    int lastDot = filename.lastIndexOf('.');
    if (lastDot <= 0 || lastDot == filename.length() - 1) {
      return null;
    }
    String ext = filename.substring(lastDot).toLowerCase(Locale.ROOT);
    return EXTENSION_RE.matcher(ext).matches() ? ext : null;
  }

  /** 是否为技术参数/导航目录（如 4K、Season 1、A-Z、共24集）。 */
  public static boolean isTechnicalDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return false;
    }
    String name = directory.trim();
    if (TitleConstants.TECHNICAL_DIR_RE.matcher(name).matches()) {
      return true;
    }
    return isSeasonDirectory(name)
        || TitleConstants.INDEX_DIR_RE.matcher(name).matches()
        || TitleConstants.EPISODE_COUNT_DIR_RE.matcher(name).matches();
  }

  /** 是否为季目录（Season 1 / 第1季 / Specials）。 */
  public static boolean isSeasonDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return false;
    }
    String name = directory.trim();
    return TitleConstants.SEASON_DIR_RE.matcher(name).matches()
        || TitleConstants.CHINESE_SEASON_RE.matcher(name).matches()
        || Pattern.compile("(?i)^(specials?|特别篇|特别季|特典)$").matcher(name).matches();
  }

  /** 是否为合集/系列目录。 */
  public static boolean isCollectionDirectory(String directory) {
    return directory != null && TitleConstants.COLLECTION_RE.matcher(directory).find();
  }

  /** 从目录名提取季号（Season 1 → 1；第1季 → 1；Specials → 0）。 */
  public static Integer parseSeasonNumber(String directory) {
    if (directory == null || directory.isBlank()) {
      return null;
    }
    String name = directory.trim();
    Matcher m = TitleConstants.SEASON_DIR_RE.matcher(name);
    if (m.matches()) {
      return Integer.parseInt(m.group(1));
    }
    m = TitleConstants.CHINESE_SEASON_RE.matcher(name);
    if (m.find()) {
      return Integer.parseInt(m.group(1));
    }
    return Pattern.compile("(?i)^(specials?|特别篇|特别季|特典)$").matcher(name).matches()
        ? 0
        : null;
  }

  /**
   * 本地解析主入口。
   *
   * @param fileName 文件名（可含扩展名）
   * @param relativePath 相对任务根目录的完整路径（含文件名）
   * @param libraryType movie / tv / anime / auto
   */
  public static LocalParseResult parse(String fileName, String relativePath, String libraryType) {
    String basePath = relativePath != null && !relativePath.isBlank() ? relativePath : fileName;
    String name = fileName != null ? fileName : lastSegment(basePath);
    List<String> segments = splitPath(basePath);
    String mediaType = libraryType == null ? "auto" : libraryType;

    // 辅助文件跳过
    if (isIgnoredFile(name)) {
      return LocalParseResult.builder()
          .path(basePath)
          .filename(name)
          .directories(segments.isEmpty() ? List.of() : segments.subList(0, segments.size() - 1))
          .mediaType(mediaType)
          .skip(true)
          .skipReason("辅助文件（字幕/图片/文本），不生成影片记录")
          .build();
    }

    // 目录列表（不含文件名）
    List<String> directories =
        segments.size() > 1 ? segments.subList(0, segments.size() - 1) : List.of();

    // 判断电影/剧集
    TypeInfo typeInfo = decideType(fileName, directories, libraryType);
    boolean isTv = typeInfo.tv;
    String resolvedMediaType = isTv ? "tv" : "movie";

    // 提取季集
    SeasonEpisode seasonEpisode = extractSeasonEpisode(name, directories, isTv);

    // 生成片名候选（目录回溯 + 文件名）
    List<String> candidates = buildTitleCandidates(name, directories, isTv);

    // 提取年份（记录来源）
    YearInfo yearInfo = extractYear(name, directories);

    // 计算本地置信度
    double confidence = computeConfidence(candidates, seasonEpisode, yearInfo, isTv);

    return LocalParseResult.builder()
        .path(basePath)
        .filename(name)
        .directories(directories)
        .mediaType(resolvedMediaType)
        .titleCandidates(candidates)
        .year(yearInfo.year)
        .yearSource(yearInfo.source)
        .season(seasonEpisode.season)
        .episode(seasonEpisode.episode)
        .confidence(confidence)
        .build();
  }

  private record TypeInfo(boolean tv) {}

  /** 电影/剧集判断。 */
  private static TypeInfo decideType(String fileName, List<String> directories, String libraryType) {
    String name = MediaPathParser.removeFileExtension(fileName);
    // 文件名含明确剧集特征（SxxExx / 第x集 / 第x话）时，即使任务库类型为 movie 也按剧集处理。
    // 避免含 SxxExx 的剧集文件在电影库/自动库下被误判为电影。
    if (TitleConstants.SEASON_EPISODE_RE.matcher(name).find()
        || TitleConstants.CHINESE_EPISODE_RE.matcher(name).find()) {
      return new TypeInfo(true);
    }
    // 目录含明确季目录（Season 1 / 第1季 / Specials）→ 剧集
    for (String dir : directories) {
      if (isSeasonDirectory(dir) || parseSeasonNumber(dir) != null) {
        return new TypeInfo(true);
      }
      if (TitleConstants.CHINESE_SEASON_RE.matcher(dir).find()) {
        return new TypeInfo(true);
      }
    }
    // 任务媒体库类型为强约束（在无明确剧集特征时生效）
    if (libraryType != null) {
      if ("movie".equalsIgnoreCase(libraryType)) {
        return new TypeInfo(false);
      }
      if ("tv".equalsIgnoreCase(libraryType) || "anime".equalsIgnoreCase(libraryType)) {
        return new TypeInfo(true);
      }
    }
    // 路径包含电视剧分类（仅辅助，不作为绝对判断）
    for (String dir : directories) {
      String lower = dir.toLowerCase(Locale.ROOT);
      if (lower.contains("tv")
          || lower.contains("剧")
          || lower.contains("番")
          || lower.contains("动画")
          || lower.contains("anime")) {
        return new TypeInfo(true);
      }
    }
    return new TypeInfo(false);
  }

  private record SeasonEpisode(Integer season, Integer episode) {}

  /** 提取季和集。 */
  private static SeasonEpisode extractSeasonEpisode(
      String fileName, List<String> directories, boolean isTv) {
    if (!isTv) {
      return new SeasonEpisode(null, null);
    }
    String name = MediaPathParser.removeFileExtension(fileName);

    Integer season = null;
    Integer episode = null;

    // 文件名 SxxExx
    Matcher m = TitleConstants.SEASON_EPISODE_RE.matcher(name);
    if (m.find()) {
      season = m.group("season") != null ? Integer.parseInt(m.group("season")) : null;
      episode = m.group("episode") != null ? Integer.parseInt(m.group("episode")) : null;
    }

    // 文件名 第x集（无季时）
    if (episode == null) {
      Matcher cm = TitleConstants.CHINESE_EPISODE_RE.matcher(name);
      if (cm.find()) {
        episode = Integer.parseInt(cm.group(1));
      }
    }

    // 目录中的 Season 目录 / 中文季
    for (int i = 0; i < directories.size() && season == null; i++) {
      Integer dirSeason = parseSeasonNumber(directories.get(i));
      if (dirSeason != null) {
        season = dirSeason;
      }
    }

    // 电视剧目录中没有季集信息时，若目录名含季数（中文"第x季"）则取之
    if (season == null) {
      for (String dir : directories) {
        Matcher cm = TitleConstants.CHINESE_SEASON_RE.matcher(dir);
        if (cm.find()) {
          season = Integer.parseInt(cm.group(1));
          break;
        }
      }
    }

    return new SeasonEpisode(season, episode);
  }

  /**
   * 生成片名候选（按优先级）。
   *
   * <p>剧集：文件名 → Season 目录父级 → 上级目录。
   *
   * <p>电影：文件名（去除年份/技术标签）→ 父目录（跳过清晰度/合集目录）。
   */
  static List<String> buildTitleCandidates(String fileName, List<String> directories, boolean isTv) {
    Set<String> candidates = new LinkedHashSet<>();
    String name = MediaPathParser.removeFileExtension(fileName);

    // 技术标签剥离后的文件名（仅当文件名本身含有效标题时使用）
    String fileTitle = cleanTitleSegment(name);
    if (isTv) {
      // 剧集：优先父目录
      String parent = findTitleDirectory(directories);
      if (parent != null && !parent.isBlank()) {
        addCleanCandidate(candidates, parent);
      } else if (fileTitle != null && !fileTitle.isBlank()) {
        addCleanCandidate(candidates, fileTitle);
      }
    } else {
      // 电影：文件名优先（含年份/技术标签已剥离）
      if (fileTitle != null
          && !fileTitle.isBlank()
          && !isQualityOnly(name)
          && !isGenericMediaFilename(fileTitle)) {
        addCleanCandidate(candidates, fileTitle);
      }
      // 若文件名只有清晰度（如 4K.mp4），则从父目录获取
      if (candidates.isEmpty()) {
        String parent = findTitleDirectory(directories);
        if (parent != null && !parent.isBlank()) {
          addCleanCandidate(candidates, parent);
        }
      }
    }
    return new ArrayList<>(candidates);
  }

  /** 从目录列表回溯查找可作片名的目录（跳过清晰度/合集/季目录）。 */
  private static String findTitleDirectory(List<String> directories) {
    for (int i = directories.size() - 1; i >= 0; i--) {
      String dir = directories.get(i);
      if (isTechnicalDirectory(dir) || isSeasonDirectory(dir) || isCollectionDirectory(dir)) {
        continue;
      }
      String cleaned = cleanTitleSegment(dir);
      if (cleaned != null && !cleaned.isBlank()) {
        return cleaned;
      }
    }
    return null;
  }

  private static void addCleanCandidate(Set<String> candidates, String raw) {
    String cleaned = cleanTitleSegment(raw);
    if (cleaned != null && !cleaned.isBlank()) {
      candidates.add(cleaned);
    }
  }

  /** 清理标题段：剥离年份、技术标签、标点。保留中拼混写原始形式。 */
  static String cleanTitleSegment(String value) {
    if (value == null || value.isBlank()) {
      return null;
    }
    String title = value.trim();
    // 剥离括号内年份 (2023) [2023]
    title =
        title
            .replaceAll("[(\\[【][\\s_]*((?:19|20)\\d{2})[\\s_]*[)\\】]", " ")
            .replaceAll("[(\\[【][\\s_]*((?:19|20)\\d{2})[\\s_]*[)\\】]", " ");
    // 剥离年份（裸 4 位）
    title = TitleConstants.YEAR_RE.matcher(title).replaceAll(" ");
    // 剥离技术标签
    title = TitleConstants.CLEAN_TAG_RE.matcher(title).replaceAll(" ");
    title = TitleConstants.RELEASE_TAG_RE.matcher(title).replaceAll(" ");
    // 剥离季集
    title = TitleConstants.SEASON_EPISODE_RE.matcher(title).replaceAll(" ");
    title = TitleConstants.CHINESE_EPISODE_RE.matcher(title).replaceAll(" ");
    title = TitleConstants.CHINESE_SEASON_RE.matcher(title).replaceAll(" ");
    // 剥离总集数/更新进度标记（庆余年 共46集 → 庆余年）
    title = TitleConstants.TOTAL_EPISODES_RE.matcher(title).replaceAll(" ");
    // 分离中英边界（熊出没之chong启未来 → 熊出没之 chong 启未来）
    title = title.replaceAll("([\\u4e00-\\u9fff])([a-zA-Z])", "$1 $2");
    title = title.replaceAll("([a-zA-Z])([\\u4e00-\\u9fff])", "$1 $2");
    title = TitleConstants.SEPARATORS.matcher(title).replaceAll(" ").replaceAll("\\s+", " ").trim();
    // 移除常见无意义前缀编号（如 "10、", "12、"）
    title = title.replaceAll("^\\d+[、. ]+", "").trim();
    if (title.isBlank() || title.equalsIgnoreCase("unknown")) {
      return null;
    }
    return title;
  }

  /** 判断文件名是否只有清晰度/数字残留（无实际标题）。 */
  private static boolean isQualityOnly(String name) {
    String cleaned = TitleConstants.CLEAN_TAG_RE.matcher(name).replaceAll(" ");
    cleaned = TitleConstants.RELEASE_TAG_RE.matcher(cleaned).replaceAll(" ");
    cleaned = TitleConstants.SEPARATORS.matcher(cleaned).replaceAll(" ").trim();
    return cleaned.isBlank()
        || TitleConstants.TECHNICAL_DIR_RE.matcher(cleaned).matches()
        // 剥离技术标签后只剩纯数字（如 21.4K.SDR.60fps → "21"）视为无有效标题
        || cleaned.matches("\\d+");
  }

  /** 常见占位视频文件名，片名应从父目录获取。 */
  private static boolean isGenericMediaFilename(String title) {
    return Pattern.compile("(?i)^(?:movie|video|film|feature|main|正片)$")
        .matcher(title.trim())
        .matches();
  }

  private record YearInfo(String year, String source) {}

  /** 提取年份并记录来源（文件名优先，其次目录）。 */
  private static YearInfo extractYear(String fileName, List<String> directories) {
    String name = MediaPathParser.removeFileExtension(fileName);
    // 文件名中的年份
    Matcher m = TitleConstants.YEAR_RE.matcher(name);
    while (m.find()) {
      String year = m.group("year");
      if (isReasonableYear(year)) {
        return new YearInfo(year, "filename");
      }
    }
    // 目录中的年份（从近到远）
    for (int i = directories.size() - 1; i >= 0; i--) {
      Matcher dm = TitleConstants.YEAR_RE.matcher(directories.get(i));
      while (dm.find()) {
        String year = dm.group("year");
        if (isReasonableYear(year)) {
          return new YearInfo(year, "directory");
        }
      }
    }
    return new YearInfo(null, null);
  }

  private static boolean isReasonableYear(String year) {
    try {
      int y = Integer.parseInt(year);
      return y >= 1900 && y <= java.time.Year.now().getValue() + 2;
    } catch (NumberFormatException e) {
      return false;
    }
  }

  private static double computeConfidence(
      List<String> candidates, SeasonEpisode seasonEpisode, YearInfo yearInfo, boolean isTv) {
    int score = 0;
    if (candidates != null && !candidates.isEmpty()) {
      score += 40;
    }
    if (yearInfo.year != null) {
      score += 15;
    }
    if (seasonEpisode.episode != null) {
      score += 20;
    }
    if (seasonEpisode.season != null) {
      score += 10;
    }
    if (isTv && seasonEpisode.episode != null) {
      score += 10;
    }
    return Math.min(1.0, score / 100.0);
  }

  /** 移除文件扩展名。 */
  public static String removeFileExtension(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return fileName;
    }
    int lastDot = fileName.lastIndexOf('.');
    if (lastDot > 0) {
      return fileName.substring(0, lastDot);
    }
    return fileName;
  }

  private static String lastSegment(String path) {
    List<String> segments = splitPath(path);
    return segments.isEmpty() ? "" : segments.get(segments.size() - 1);
  }
}
