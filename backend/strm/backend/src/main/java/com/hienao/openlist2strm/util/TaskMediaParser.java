package com.hienao.openlist2strm.util;

import com.hienao.openlist2strm.dto.media.MediaInfo;
import com.hienao.openlist2strm.entity.MediaLibraryType;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.Collections;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/** 根据任务媒体库类型解析相对于任务根目录的媒体路径。 */
public final class TaskMediaParser {

  private static final Pattern YEAR_PATTERN =
      Pattern.compile(
          "(?i)(.*?)(?:^|[\\s._\\-\\[\\]()（）【】]+)((?:19|20)\\d{2})(?:[\\s._\\-\\[\\]()（）【】]+|年|$)");

  /** 括号内的年份，如（2025）、(2022)、[2024]。标题本身是数字年份时优先用括号年份。 */
  private static final Pattern BRACKETED_YEAR_PATTERN =
      Pattern.compile("[（(【\\[]\\s*((?:19|20)\\d{2})\\s*[)）】\\]]");

  private static final Pattern SEASON_PATTERN =
      Pattern.compile("(?i)^(?:season|s(?:eason)?)[\\s._-]*(\\d{1,2})(?:[^\\d].*)?$");
  private static final Pattern CHINESE_SEASON_PATTERN =
      Pattern.compile("^(?:\\d{4}\\s*)?第\\s*(\\d{1,2})\\s*季(?:[^\\d].*)?$");
  private static final Pattern SPECIALS_PATTERN =
      Pattern.compile("(?i)^(?:specials?|special episodes?|特别篇|特别季|特典)$");
  private static final Pattern SEASON_EPISODE_PATTERN =
      Pattern.compile("(?i)(?<![A-Za-z0-9])S(\\d{1,2})[\\s._-]*E(\\d{1,4})(?:\\D|$)");
  private static final Pattern X_EPISODE_PATTERN =
      Pattern.compile("(?i)(?:^|\\D)(\\d{1,2})x(\\d{1,4})(?:\\D|$)");
  private static final Pattern EPISODE_PATTERN =
      Pattern.compile("(?i)(?<![A-Za-z0-9])E(?:PISODE|P)?[\\s._-]*(\\d{1,4})(?:\\D|$)");
  private static final Pattern ABSOLUTE_EPISODE_PATTERN =
      Pattern.compile("(?i)(?:^|[\\s._\\-\\[])(\\d{1,4})(?:v\\d+)?(?:[\\s._\\-\\]]|$)");

  /** 短剧目录中的总集数标注，如（60集）、(40集全)。 */
  private static final Pattern EPISODE_COUNT_TAG_PATTERN =
      Pattern.compile(
          "(?i)(?:[（(【\\[]\\s*)?(?:全\\s*)?\\d{1,4}\\s*集(?:全)?\\s*"
              + "(?:[)）】\\]]|(?=[\\s　（(【\\[]|$))");

  /** 集数开头 + 画质标签，如 04.4K.SDR.60fps。 */
  private static final Pattern LEADING_NUMBER_EPISODE_PATTERN =
      Pattern.compile(
          "^(?!19\\d{2}|20\\d{2})\\s*(\\d{1,4})(?=[\\s._\\-]*\\d{2,4}p|"
              + "[\\s._\\-]*(?:4K|SDR|HDR|WEB-?DL|BLURAY|X26[45]|H26[45]|HEVC))");

  /** 发布组风格集数标记：[KRL][Kamen Rider OOO][28][BDRip]...、【133】【续·醒来的狮子】...。 */
  private static final Pattern BRACKETED_EPISODE_PATTERN =
      Pattern.compile("[\\[【]\\s*((?!19\\d{2}|20\\d{2})\\d{1,4})\\s*[\\]】]");

  /** 裸集数文件名：48.5、09、141(1)、30 等（动漫/剧集 Sxx 目录下的常见写法）。 */
  private static final Pattern BARE_EPISODE_NUMBER_PATTERN =
      Pattern.compile("(?i)^\\s*(\\d{1,4})(?:\\.\\d+)?(?:\\s*\\(\\d+\\))?\\s*$");

  /** 特别篇文件：特别篇.mp4、SP、Specials、特典 等。 */
  private static final Pattern SPECIAL_EPISODE_FILE_PATTERN =
      Pattern.compile("(?i)^(?:specials?|special|sp|sp\\d{1,2}|特别篇|特别篇\\d{0,2}|特典|OVA?)$");

  /** 开头的 [中文片名]（非发布组）结构，如 [新僵尸先生].Mr.Vampire.1992... */
  private static final Pattern LEADING_BRACKET_PATTERN =
      Pattern.compile("^\\s*[\\[【]\\s*([^\\]】]{1,40})\\s*[\\]】]");

  private static final Pattern CJK_PATTERN = Pattern.compile("[\\u4e00-\\u9fff]");
  private static final Pattern LEADING_LIST_NUMBER_PATTERN =
      Pattern.compile("^\\s*\\d{1,4}\\s*[、,，.)）]\\s*");
  /** 资源目录开头的地区/媒体类型标签，不属于片名，如“韩剧.剧名.2026”。 */
  private static final Pattern LEADING_MEDIA_TYPE_LABEL_PATTERN =
      Pattern.compile(
          "(?i)^(?:(?:影视资源|电影|影片|movie|movies|film|films|电视剧|电视|剧集|短剧|"
              + "国产电视剧|国产剧|国产|韩剧|韩国剧|韩国|欧美剧集|欧美剧|美剧|英剧|日剧|港剧|台剧|"
              + "泰剧|日韩剧|港台剧|动漫网剧|动漫|动画|网剧|tv|shows|series|anime)"
              + "(?=[\\s._\\-·:：/]|$)[\\s._\\-·:：/]*\\s*)+");
  private static final Pattern SEPARATORS = Pattern.compile("[._\\-\\[\\]()（）【】]+");
  private static final Pattern CHINESE_EPISODE_PATTERN =
      Pattern.compile("第\\s*(\\d{1,4})\\s*(?:集|话)");
  private static final Pattern TMDB_ID_TAG_PATTERN =
      Pattern.compile(
          "(?i)(?<![A-Za-z0-9])(?:[\\[{]\\s*)?tmdb(?:id)?\\s*[-= ]\\s*\\d+"
              + "(?:[\\s._-]+[A-Za-z0-9]+)*(?:[\\]}])?");

  /** 目录名末尾的“年份(季号)”结构，如“照亮你 2023(1)”。 */
  private static final Pattern INLINE_SEASON_DIRECTORY_PATTERN =
      Pattern.compile("(?i)^.*?(?:19|20)\\d{2}\\s*[\\[(【]\\s*(\\d{1,2})\\s*[\\])】]\\s*$");

  private static final Pattern INLINE_SEASON_SUFFIX_PATTERN =
      Pattern.compile("(?i)[\\[(【]\\s*\\d{1,2}\\s*[\\])】]\\s*$");

  /** 中文标题尾部直接粘连的画质/编码标签：老狗4K、画皮：情灭4KHQ60FPS、龙狱天棺4K。 */
  private static final Pattern TRAILING_QUALITY_PATTERN =
      Pattern.compile(
          "(?i)([\\u4e00-\\u9fff])(?:\\s*(?:4k|1080p|2160p|720p|480p|uhd|hdr(?:10)?|"
              + "hq|60fps|60帧|高清|超清|字幕版|h26[45]|x26[45]|hevc|avc|10bit|12bit|aac|ac3|"
              + "flac|ddp|dts|remux|web-?dl|bluray|bdrip|bd1080p|\\d{3,4}))+"
              + "(?:[A-Za-z0-9]{1,4})?\\s*$");

  /** 目录/文件名中的媒体标签，不应进入搜索标题。 */
  private static final Pattern MEDIA_METADATA_TAG_PATTERN =
      Pattern.compile(
          "(?i)(?:内封(?:中字)?|外挂(?:字幕)?|国语音轨|国粤双语|简繁英字幕|简繁字幕|简繁英|中文字幕|"
              + "国语|粤语|中字|英字|双语|国英|英中|中英|韩语|日语)");

  private static final Pattern SEASON_RANGE_NOISE_PATTERN =
      Pattern.compile("(?i)\\s+第\\s*\\d{1,2}(?:\\s*[-~至]\\s*\\d{1,2}|\\s+\\d{1,2})?\\s*季.*$");
  private static final Pattern ENGLISH_SEASON_NOISE_PATTERN =
      Pattern.compile("(?i)\\s+[A-Za-z][A-Za-z .'-]*\\s+Season(?:\\s+\\d+)?\\s*$");
  private static final Pattern NON_TITLE_DIRECTORY_PATTERN =
      Pattern.compile(
          "(?i)^(?:影视资源|电影|剧集|电视剧|动漫|动漫网剧|合集|全集|系列|collection|boxset|extras?|disc[\\s._-]?\\d+|"
              + "(?:.+)?(?:番外(?:篇)?|特别篇|特典|花絮|彩蛋))$");
  private static final Pattern CAMEL_CASE_BOUNDARY_PATTERN =
      Pattern.compile("(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])");

  private TaskMediaParser() {}

  public static MediaInfo parse(
      String fileName,
      String relativePath,
      List<String> movieRegexps,
      List<String> tvDirRegexps,
      List<String> tvFileRegexps,
      String libraryTypeValue) {
    String safeFileName = fileName == null ? "" : fileName.trim();
    MediaLibraryType libraryType = MediaLibraryType.from(libraryTypeValue);
    String directoryPath = directoryPathFor(fileName, relativePath);

    return switch (libraryType) {
      case MOVIE -> parseMovie(safeFileName, directoryPath, movieRegexps);
      case TV -> parseSeries(safeFileName, directoryPath, tvDirRegexps, tvFileRegexps, false);
      case ANIME -> parseSeries(safeFileName, directoryPath, tvDirRegexps, tvFileRegexps, true);
      case AUTO ->
          parseAuto(safeFileName, directoryPath, movieRegexps, tvDirRegexps, tvFileRegexps);
    };
  }

  /**
   * 自动类型只使用文件名中的媒体证据判断类型。
   *
   * <p>目录可以补全缺失的标题，但不能因为目录名包含“电视剧”或“电影”就直接决定类型。目录类型提示只在 TMDB 同名电影和电视剧同时存在时作为后续消歧依据使用。
   */
  private static MediaInfo parseAuto(
      String fileName,
      String directoryPath,
      List<String> movieRegexps,
      List<String> tvDirRegexps,
      List<String> tvFileRegexps) {
    MediaInfo fileTvSignal =
        MediaFileParser.parse(fileName, "", Collections.emptyList(), tvDirRegexps, tvFileRegexps);
    EpisodeInfo episodeInfo = parseEpisode(fileName, isBareEpisodeFileName(fileName));
    Integer season = firstNonNull(episodeInfo.season(), fileTvSignal.getSeason());
    Integer episode = firstNonNull(episodeInfo.episode(), fileTvSignal.getEpisode());
    if (episode != null && season == null) {
      season = 1;
    }

    // 目录中的季目录（Season 2 / S1 / SE01 / 第3季 / 特别篇）是强电视剧证据：
    // 常见结构 剧集/剧名 (年份)/S1/48.5.mp4 或 动漫/剧名/S1/09 .mp4，文件名只有集数。
    Integer directorySeason = directorySeasonHint(directoryPath);
    if (directorySeason != null && episode == null) {
      Integer bareEpisode = parseBareEpisodeNumber(fileName);
      if (bareEpisode != null) {
        season = directorySeason;
        episode = bareEpisode;
      }
    }
    if (directorySeason != null && season == null) {
      season = directorySeason;
    }

    // 纯序号文件（34.mp4、35(1).mp4）通常是剧集目录中的集数。优先使用媒体父目录，
    // 避免把集数发送给豆瓣后命中同名数字作品。
    Integer bareEpisode = parseBareEpisodeNumber(fileName);
    String parentTitle = resourceDirectoryNameFromDirectory(directoryPath);
    if (bareEpisode != null && !parentTitle.isBlank()) {
      return buildAutoSeries(
          fileName,
          directoryPath,
          fileTvSignal,
          directorySeason == null ? 1 : directorySeason,
          bareEpisode);
    }

    // 季集信息是文件级别的电视剧证据，不能被父级“电影/电视剧”目录覆盖。
    if (season != null || episode != null) {
      return buildAutoSeries(fileName, directoryPath, fileTvSignal, season, episode);
    }

    // “特别篇/SP/Specials” 文件位于带标题的资源目录时视为电视剧特别篇（第 0 季）。
    String autoParentTitle = resourceDirectoryNameFromDirectory(directoryPath);
    if (isSpecialEpisodeFileName(fileName) && !autoParentTitle.isBlank()) {
      return buildAutoSeries(fileName, directoryPath, fileTvSignal, 0, null);
    }

    // 文件名本身匹配电影规则时，直接采用文件名结果；此处不读取目录来决定类型。
    MediaInfo fileMovieSignal =
        MediaFileParser.parse(
            fileName, "", movieRegexps, Collections.emptyList(), Collections.emptyList());
    if (fileMovieSignal.isMovie()) {
      ParsedTitle parsedTitle =
          parseTitle(
              fileMovieSignal.getTitle() == null
                  ? MediaFileParser.removeFileExtension(fileName)
                  : fileMovieSignal.getTitle());
      String year = parsedTitle.year() != null ? parsedTitle.year() : fileMovieSignal.getYear();
      fileMovieSignal
          .setTitle(parsedTitle.title())
          .setCleanTitle(parsedTitle.title())
          .setYear(year)
          .setHasYear(year != null);
      // 文件名只有年份/画质（如 2025.2160p.WEB-DL...）时标题为空，交给父目录补全标题。
      if (fileMovieSignal.getTitle() != null && !fileMovieSignal.getTitle().isBlank()) {
        fileMovieSignal.setOriginalFileName(fileName);
        return withTitleCandidates(
            fileMovieSignal, fileMovieSignal.getTitle(), fileMovieSignal.getCleanTitle());
      }
    }

    // 电影常见的“电影目录 (年份)/视频文件”结构可以补全标题，但没有年份时仍保持未知。
    // 注意跳过字母索引目录（/电影/A 等），避免把 "A" 当作候选标题。
    String parentDirectory = resourceDirectoryNameFromDirectory(directoryPath);
    if (!parentDirectory.isBlank() && !isLetterIndexDirectory(parentDirectory)) {
      MediaInfo parentMovieSignal =
          MediaFileParser.parse(
              parentDirectory + ".video",
              "",
              movieRegexps,
              Collections.emptyList(),
              Collections.emptyList());
      if (parentMovieSignal.isMovie() && parentMovieSignal.isHasYear()) {
        parentMovieSignal.setOriginalFileName(fileName);
        return withTitleCandidates(
            parentMovieSignal, parentMovieSignal.getTitle(), parentMovieSignal.getCleanTitle());
      }
    }

    // 文件名没有标题时，保留父级媒体目录作为搜索标题，类型交给 TMDB/消歧逻辑决定。
    if (!parentTitle.isBlank()) {
      ParsedTitle parsedTitle = parseTitle(parentTitle);
      return unknownMedia(fileName, parsedTitle);
    }

    return unknownMedia(fileName, parseTitle(MediaFileParser.removeFileExtension(fileName)));
  }

  private static MediaInfo buildAutoSeries(
      String fileName,
      String directoryPath,
      MediaInfo fileTvSignal,
      Integer season,
      Integer episode) {
    String titleSource = resourceDirectoryNameFromDirectory(directoryPath);
    if (titleSource.isBlank() && hasUsableTitle(fileTvSignal, fileName)) {
      titleSource = fileTvSignal.getTitle();
    }
    if (titleSource.isBlank()) {
      titleSource = titleFromEpisodeFileName(fileName);
    }

    ParsedTitle parsedTitle = parseTitle(titleSource);
    // 目录标题被清洗为空时（如 1923 第1季、tmdb 目录），回退从文件名提取标题。
    if ((parsedTitle.title() == null || parsedTitle.title().isBlank()) && titleSource != null) {
      String fromFile = titleFromEpisodeFileName(fileName);
      String fileBaseName = MediaFileParser.removeFileExtension(fileName);
      if (!fromFile.isBlank() && !fromFile.equals(fileBaseName)) {
        parsedTitle = parseTitle(fromFile);
      }
    }
    if (parsedTitle.year() == null) {
      parsedTitle = new ParsedTitle(parsedTitle.title(), extractYearAfterEpisode(fileName));
    }
    int confidence = 20;
    if (parsedTitle.title() != null && !parsedTitle.title().isBlank()) {
      confidence += 40;
    }
    if (season != null) {
      confidence += 10;
    }
    if (episode != null) {
      confidence += 20;
    }
    if (parsedTitle.year() != null) {
      confidence += 10;
    }

    return withTitleCandidates(
        new MediaInfo()
            .setType(MediaInfo.MediaType.TV_SHOW)
            .setTitle(parsedTitle.title())
            .setCleanTitle(parsedTitle.title())
            .setYear(parsedTitle.year())
            .setSeason(season)
            .setEpisode(episode)
            .setHasYear(parsedTitle.year() != null)
            .setHasSeasonEpisode(season != null && episode != null)
            .setOriginalFileName(fileName)
            .setConfidence(Math.min(100, confidence)),
        titleSource,
        parsedTitle.title());
  }

  private static MediaInfo unknownMedia(String fileName, ParsedTitle parsedTitle) {
    return withTitleCandidates(
        new MediaInfo()
            .setType(MediaInfo.MediaType.UNKNOWN)
            .setTitle(parsedTitle.title())
            .setCleanTitle(parsedTitle.title())
            .setYear(parsedTitle.year())
            .setHasYear(parsedTitle.year() != null)
            .setOriginalFileName(fileName)
            .setConfidence(parsedTitle.title() == null || parsedTitle.title().isBlank() ? 0 : 60),
        parsedTitle.title(),
        parsedTitle.title());
  }

  private static boolean hasUsableTitle(MediaInfo mediaInfo, String fileName) {
    return mediaInfo.getTitle() != null
        && !mediaInfo.getTitle().isBlank()
        && !mediaInfo.getTitle().equals(MediaFileParser.removeFileExtension(fileName));
  }

  private static String titleFromEpisodeFileName(String fileName) {
    String title = MediaFileParser.removeFileExtension(fileName);
    title = SEASON_EPISODE_PATTERN.matcher(title).replaceAll(" ");
    title = X_EPISODE_PATTERN.matcher(title).replaceAll(" ");
    title = EPISODE_PATTERN.matcher(title).replaceAll(" ");
    title = CHINESE_EPISODE_PATTERN.matcher(title).replaceAll(" ");
    return title.trim();
  }

  /** 从文件名 S/E 标记之后的发布信息中提取年份，如 骄阳似我.S01E21.2025.2160p... 中的 2025。 */
  private static String extractYearAfterEpisode(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return null;
    }
    String name = MediaFileParser.removeFileExtension(fileName);
    int markerStart = -1;
    Matcher matcher = SEASON_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      markerStart = matcher.end();
    } else {
      matcher = X_EPISODE_PATTERN.matcher(name);
      if (matcher.find()) {
        markerStart = matcher.end();
      } else {
        matcher = EPISODE_PATTERN.matcher(name);
        if (matcher.find()) {
          markerStart = matcher.end();
        }
      }
    }
    if (markerStart < 0) {
      return null;
    }
    // S/E 标记的匹配可能包含尾部分隔符，先剥离开头分隔符再找年份
    String tail = name.substring(markerStart).replaceFirst("^[\\s._\\-\\[\\]()（）【】]+", "");
    Matcher yearMatcher = YEAR_PATTERN.matcher(tail);
    return yearMatcher.find() ? yearMatcher.group(2) : null;
  }

  /** 返回资源目录名，跳过季目录和常见的媒体类型容器目录。 */
  public static String resourceDirectoryName(String relativePath) {
    String directoryPath = extractDirectoryPath(relativePath);
    return resourceDirectoryNameFromDirectory(directoryPath);
  }

  /** 单字母目录（A-Z / a-z），多为按拼音首字母分类的索引目录，如 /电影/A、/剧集/国产剧集/B。 */
  private static final Pattern SINGLE_LETTER_DIRECTORY_PATTERN = Pattern.compile("[A-Za-z]");

  /** 连续字母目录（如 ABCD），用于按首字母范围归档的索引目录。 */
  private static final Pattern GROUPED_LETTER_DIRECTORY_PATTERN = Pattern.compile("[A-Z]{2,26}");

  /** “数字”等非字母索引目录（如 /剧集/国产剧集/数字）。 */
  private static final Pattern INDEX_DIRECTORY_PATTERN = Pattern.compile("^(?:数字|[0-9]+)$");

  /**
   * 判断目录名是否为按拼音首字母（或数字）分类的索引目录。
   *
   * <p>这类目录只用于资源归档归类（如 /电影/A 存放 A 开头的电影），不是媒体标题本身，解析标题时应跳过。
   */
  public static boolean isLetterIndexDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return false;
    }
    String value = directory.trim();
    if (SINGLE_LETTER_DIRECTORY_PATTERN.matcher(value).matches()
        || INDEX_DIRECTORY_PATTERN.matcher(value).matches()) {
      return true;
    }
    if (!GROUPED_LETTER_DIRECTORY_PATTERN.matcher(value).matches()) {
      return false;
    }
    for (int i = 1; i < value.length(); i++) {
      if (value.charAt(i) != value.charAt(i - 1) + 1) {
        return false;
      }
    }
    return true;
  }

  private static String resourceDirectoryNameFromDirectory(String directoryPath) {
    List<String> directories = splitPath(directoryPath);
    for (int i = directories.size() - 1; i >= 0; i--) {
      String directory = directories.get(i);
      if (isLetterIndexDirectory(directory)) {
        if (isTechnicalDirectory(directory)) {
          continue;
        }
        // 拼音首字母/数字索引目录（/电影/A）：它不是标题，且不应穿透到上层容器目录，
        // 直接视为“无目录标题”，交由文件名解析兜底。
        return "";
      }
      if (!isSeasonDirectory(directory)
          && !isMediaTypeDirectory(directory)
          && !isTechnicalDirectory(directory)
          && !isMetadataContainerDirectory(directory)) {
        return directory;
      }
    }
    return "";
  }

  private static boolean isMediaTypeDirectory(String directory) {
    String value = directory == null ? "" : directory.trim().toLowerCase(java.util.Locale.ROOT);
    return switch (value) {
      case "影视资源", "电影", "影片", "movie", "movies", "film", "films" -> true;
      case "电视剧",
          "电视",
          "剧集",
          "短剧",
          "国产电视剧",
          "国产剧",
          "韩剧",
          "欧美剧",
          "日韩剧",
          "港台剧",
          "美剧",
          "英剧",
          "日剧",
          "港剧",
          "台剧",
          "泰剧",
          "国产剧集",
          "动漫网剧",
          "欧美剧集",
          "日韩剧集",
          "港台剧集",
          "tv",
          "shows",
          "series",
          "anime",
          "动画" ->
          true;
      default -> false;
    };
  }

  /** 元数据容器目录：tmdb-233607、{tmdbid-...} 以及 国语/粤语/中字 等语言目录。 */
  private static final Pattern TMDB_DIRECTORY_PATTERN = Pattern.compile("(?i)^tmdb-?\\d+$");

  private static final Pattern LANGUAGE_DIRECTORY_PATTERN =
      Pattern.compile("(?i)^(?:国语|粤语|中字|国语音轨|原声|普通话|台配|日语|英语|韩语)$");

  /** 开头画质/编码标签：4K 圣母在上、1080p Movie → 剥离开头标签保留标题。 */
  private static final Pattern LEADING_TECHNICAL_TAG_PATTERN =
      Pattern.compile(
          "(?i)^(?:4k|1080p|2160p|720p|480p|uhd|hdr(?:10)?|sdr|hevc|x26[45]|h26[45]|"
              + "bluray|bdrip|remux|web-?dl|webrip)\\s*[- 　]?");

  /** 技术目录标签序列：如 1080p、4K、4K SDR 高码、4K高码、高码版、HDR、REMUX 等。 */
  private static final Pattern TECHNICAL_DIRECTORY_PATTERN =
      Pattern.compile(
          "(?i)^(?:\\d{3,4}p|\\d{3,4}|[248]k|uhd|hdr(?:10)?|sdr|h[.]?26[45]|x26[45]|hevc|avc|"
              + "web-?dl|webrip|bluray|bdrip|remux|s[e.]\\d{1,2}|高码|高清|高码版|杜比|音效|"
              + "立体声|立体环绕声|环绕声|全景声|声道|国语配音|粤语配音)"
              + "(?:[\\s._&-]*(?:\\d{3,4}p|\\d{3,4}|[248]k|uhd|hdr(?:10)?|sdr|h[.]?26[45]|"
              + "x26[45]|hevc|avc|web-?dl|webrip|bluray|bdrip|remux|s[e.]\\d{1,2}|高码|高清|"
              + "高码版|杜比|音效|立体声|立体环绕声|环绕声|全景声|声道|国语配音|粤语配音))*$");

  private static boolean isTechnicalDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return false;
    }
    return TECHNICAL_DIRECTORY_PATTERN.matcher(directory.trim()).matches();
  }

  private static boolean isIgnoredContainerDirectory(String directory) {
    return directory != null && NON_TITLE_DIRECTORY_PATTERN.matcher(directory.trim()).matches();
  }

  private static boolean isNonTitleDirectory(String directory) {
    return isLetterIndexDirectory(directory)
        || isSeasonDirectory(directory)
        || isMediaTypeDirectory(directory)
        || isTechnicalDirectory(directory)
        || isMetadataContainerDirectory(directory)
        || isIgnoredContainerDirectory(directory)
        || isEpisodeFileName(directory);
  }

  /** 整目录是发布组/字幕组括号标签：如 【NEONE字幕组】、[ANi]、【幻月字幕组】。 */
  private static final Pattern RELEASE_GROUP_DIRECTORY_PATTERN =
      Pattern.compile("^[\\[【][^\\]】]{1,40}[\\]】]$");

  /** 系列/合集容器目录：一人之下系列、命运之夜(系列)、黄石（系列）。 */
  private static final Pattern FRANCHISE_DIRECTORY_PATTERN =
      Pattern.compile("^.+?(?:系列|合集)$|^[（(]?系列[)）]?$");

  /** tmdb-NNNN / {tmdbid-NNNN} / 国语 / 粤语 / 中字 等容器目录不是标题。 */
  private static boolean isMetadataContainerDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return false;
    }
    String value = directory.trim();
    return TMDB_DIRECTORY_PATTERN.matcher(value).matches()
        || LANGUAGE_DIRECTORY_PATTERN.matcher(value).matches()
        || FRANCHISE_DIRECTORY_PATTERN.matcher(value).matches()
        || RELEASE_GROUP_DIRECTORY_PATTERN.matcher(value).matches()
        || TMDB_ID_TAG_PATTERN.matcher(value).matches();
  }

  /** 返回路径中仅用于同名电影/电视剧消歧的类型目录提示。 */
  public static MediaInfo.MediaType directoryTypeHint(String relativePath) {
    List<String> directories = splitPath(extractDirectoryPath(relativePath));
    boolean movie = false;
    boolean tv = false;
    for (String directory : directories) {
      String value = directory.trim().toLowerCase(java.util.Locale.ROOT);
      movie |= value.equals("电影") || value.equals("movie") || value.equals("movies");
      tv |=
          value.equals("电视剧")
              || value.equals("电视")
              || value.equals("短剧")
              || value.equals("国产电视剧")
              || value.equals("国产剧")
              || value.equals("韩剧")
              || value.equals("欧美剧")
              || value.equals("日韩剧")
              || value.equals("港台剧")
              || value.equals("tv")
              || value.equals("shows")
              || value.equals("series");
    }
    if (movie == tv) {
      return MediaInfo.MediaType.UNKNOWN;
    }
    return movie ? MediaInfo.MediaType.MOVIE : MediaInfo.MediaType.TV_SHOW;
  }

  private static <T> T firstNonNull(T first, T second) {
    return first != null ? first : second;
  }

  private static MediaInfo parseMovie(
      String fileName, String directoryPath, List<String> movieRegexps) {
    String resourceDirectoryName = resourceDirectoryNameFromDirectory(directoryPath);
    // 目录优先：跳过字母索引目录（/电影/A 等），避免把 "A" 当作标题
    if (!resourceDirectoryName.isBlank()) {
      MediaInfo directoryResult =
          MediaFileParser.parse(
              resourceDirectoryName + ".video",
              "",
              movieRegexps,
              Collections.emptyList(),
              Collections.emptyList());
      if (directoryResult.isMovie() && !isLetterIndexDirectory(resourceDirectoryName)) {
        directoryResult.setOriginalFileName(fileName);
        return withTitleCandidates(
            directoryResult, resourceDirectoryName, directoryResult.getDisplayTitle());
      }
    }

    MediaInfo fileResult =
        MediaFileParser.parse(
            fileName, "", movieRegexps, Collections.emptyList(), Collections.emptyList());
    if (fileResult.isMovie()) {
      return withTitleCandidates(fileResult, fileResult.getTitle(), fileResult.getCleanTitle());
    }

    String titleSource =
        !resourceDirectoryName.isBlank()
            ? resourceDirectoryName
            : MediaFileParser.removeFileExtension(fileName);
    ParsedTitle parsedTitle = parseTitle(titleSource);
    return withTitleCandidates(
        new MediaInfo()
            .setType(MediaInfo.MediaType.MOVIE)
            .setTitle(parsedTitle.title())
            .setCleanTitle(parsedTitle.title())
            .setYear(parsedTitle.year())
            .setHasYear(parsedTitle.year() != null)
            .setOriginalFileName(fileName)
            .setConfidence(parsedTitle.year() == null ? 60 : 80),
        resourceDirectoryName,
        parsedTitle.title());
  }

  private static MediaInfo parseSeries(
      String fileName,
      String directoryPath,
      List<String> tvDirRegexps,
      List<String> tvFileRegexps,
      boolean anime) {
    MediaInfo regexResult =
        MediaFileParser.parse(
            fileName, directoryPath, Collections.emptyList(), tvDirRegexps, tvFileRegexps);

    List<String> directories = splitPath(directoryPath);
    Integer season = regexResult.getSeason();
    String titleSource = null;

    for (int i = 0; i < directories.size(); i++) {
      Integer directorySeason = parseSeason(directories.get(i));
      boolean inlineSeason = false;
      if (directorySeason == null) {
        directorySeason = parseInlineSeasonDirectory(directories.get(i));
        inlineSeason = directorySeason != null;
      }
      if (directorySeason != null) {
        season = season != null ? season : directorySeason;
        if (inlineSeason) {
          titleSource = directories.get(i);
          break;
        }
        // 取季目录前一个目录作为标题，跳过索引和媒体类型容器目录。
        for (int j = i - 1; j >= 0; j--) {
          if (!isNonTitleDirectory(directories.get(j))) {
            titleSource = directories.get(j);
            break;
          }
        }
        break;
      }
    }
    if (titleSource == null && !directories.isEmpty()) {
      // 无季目录时取最后一个非索引、非容器目录
      for (int j = directories.size() - 1; j >= 0; j--) {
        if (!isNonTitleDirectory(directories.get(j))) {
          titleSource = directories.get(j);
          break;
        }
      }
    }
    if (titleSource == null || titleSource.isBlank()) {
      titleSource = regexResult.getTitle();
    }
    if (isNonTitleDirectory(titleSource)) {
      titleSource = null;
    }
    // 字母索引目录场景（/剧集/A/开端 S01E01.mkv）：目录没有提供剧名时，从文件名提取
    if (titleSource == null || titleSource.isBlank() || isLetterIndexDirectory(titleSource)) {
      String fromFile = titleFromEpisodeFileName(fileName);
      String fileBaseName = MediaFileParser.removeFileExtension(fileName);
      if (!fromFile.isBlank() && !fromFile.equals(fileBaseName)) {
        titleSource = fromFile;
      }
    }

    EpisodeInfo episodeInfo = parseEpisode(fileName, true);
    if (episodeInfo.season() != null) {
      season = episodeInfo.season();
    }
    Integer episode =
        episodeInfo.episode() != null ? episodeInfo.episode() : regexResult.getEpisode();
    if (episode != null && season == null) {
      season = 1;
    }

    ParsedTitle parsedTitle = parseTitle(titleSource);
    // 目录标题被清洗为空时（如 1923 第1季、tmdb 目录），回退从文件名提取标题。
    if ((parsedTitle.title() == null || parsedTitle.title().isBlank()) && titleSource != null) {
      String fromFile = titleFromEpisodeFileName(fileName);
      String fileBaseName = MediaFileParser.removeFileExtension(fileName);
      if (!fromFile.isBlank() && !fromFile.equals(fileBaseName)) {
        parsedTitle = parseTitle(fromFile);
      }
    }
    if (parsedTitle.year() == null) {
      parsedTitle = new ParsedTitle(parsedTitle.title(), extractYearAfterEpisode(fileName));
    }
    int confidence = 20;
    if (parsedTitle.title() != null && !parsedTitle.title().isBlank()) {
      confidence += 40;
    }
    if (season != null) {
      confidence += 10;
    }
    if (episode != null) {
      confidence += 20;
    }
    if (parsedTitle.year() != null) {
      confidence += 10;
    }

    return new MediaInfo()
        .setType(MediaInfo.MediaType.TV_SHOW)
        .setTitle(parsedTitle.title())
        .setCleanTitle(parsedTitle.title())
        .setYear(parsedTitle.year())
        .setSeason(season)
        .setEpisode(episode)
        .setHasYear(parsedTitle.year() != null)
        .setHasSeasonEpisode(season != null && episode != null)
        .setOriginalFileName(fileName)
        .setConfidence(Math.min(100, confidence));
  }

  /**
   * 从文件名提取季/集信息（不依赖正则配置）。
   *
   * <p>供媒体类型尚未确定时（如刮削前的 NFO/图片处理器）判断文件是否为剧集。
   *
   * @return 季/集信息；两个字段都可能为 null
   */
  public static EpisodeInfo parseSeasonEpisode(String fileName) {
    return parseEpisode(fileName, false);
  }

  /** 判断文件名是否带剧集标记（SxxExx、NxM、第N集等）。 */
  public static boolean isEpisodeFileName(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return false;
    }
    EpisodeInfo info = parseEpisode(fileName, false);
    return info != null && (info.season() != null || info.episode() != null);
  }

  private static EpisodeInfo parseEpisode(String fileName, boolean allowAbsoluteEpisode) {
    String name = MediaFileParser.removeFileExtension(fileName);
    Matcher matcher = SEASON_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(
          Integer.parseInt(matcher.group(1)), Integer.parseInt(matcher.group(2)));
    }
    matcher = X_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(
          Integer.parseInt(matcher.group(1)), Integer.parseInt(matcher.group(2)));
    }
    matcher = EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(null, Integer.parseInt(matcher.group(1)));
    }
    // 中文集数：第01集 / 第 12 集
    matcher = CHINESE_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(null, Integer.parseInt(matcher.group(1)));
    }
    // 方括号/书名号集数：[KRL][Kamen Rider OOO][28][BDRip]、【133】【续·醒来的狮子】
    matcher = BRACKETED_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(null, Integer.parseInt(matcher.group(1)));
    }
    // 集数开头 + 画质标签：04.4K.SDR.60fps
    matcher = LEADING_NUMBER_EPISODE_PATTERN.matcher(name);
    if (matcher.find()) {
      return new EpisodeInfo(null, Integer.parseInt(matcher.group(1)));
    }
    if (allowAbsoluteEpisode) {
      matcher = ABSOLUTE_EPISODE_PATTERN.matcher(name);
      while (matcher.find()) {
        int candidate = Integer.parseInt(matcher.group(1));
        if (candidate > 0
            && candidate < 10000
            && !isReleaseNumber(candidate)
            && !isYear(candidate)) {
          return new EpisodeInfo(null, candidate);
        }
      }
    }
    return new EpisodeInfo(null, null);
  }

  private static boolean isBareEpisodeFileName(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return false;
    }
    return MediaFileParser.removeFileExtension(fileName).trim().matches("\\d{1,4}");
  }

  private static boolean isReleaseNumber(int value) {
    return value == 480 || value == 720 || value == 1080 || value == 2160;
  }

  private static boolean isYear(int value) {
    return value >= 1900 && value <= 2099;
  }

  /** 从常见中英文季目录名中提取季号，特别篇统一为第 0 季。 */
  public static Integer parseSeasonNumber(String directory) {
    if (directory == null || directory.isBlank()) {
      return null;
    }
    Matcher matcher = SEASON_PATTERN.matcher(directory.trim());
    if (matcher.matches()) {
      return Integer.parseInt(matcher.group(1));
    }
    matcher = CHINESE_SEASON_PATTERN.matcher(directory.trim());
    if (matcher.matches()) {
      return Integer.parseInt(matcher.group(1));
    }
    return SPECIALS_PATTERN.matcher(directory.trim()).matches() ? 0 : null;
  }

  private static Integer parseSeason(String directory) {
    return parseSeasonNumber(directory);
  }

  /** 判断目录名是否为任务目录结构支持的季目录。 */
  public static boolean isSeasonDirectory(String directory) {
    return directory != null && parseSeason(directory) != null;
  }

  /** 从路径中提取第一个季目录（Season 2 / S1 / SE01 / 第3季 / 特别篇）。 */
  private static Integer directorySeasonHint(String directoryPath) {
    for (String directory : splitPath(directoryPath)) {
      Integer season = parseSeason(directory);
      if (season != null) {
        return season;
      }
    }
    return null;
  }

  /** 解析裸集数文件名：48.5.mp4、09 .mp4、141(1).MP4、30.mp4 → 48/9/141/30。 */
  private static Integer parseBareEpisodeNumber(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return null;
    }
    String base = MediaFileParser.removeFileExtension(fileName).trim();
    Matcher matcher = BARE_EPISODE_NUMBER_PATTERN.matcher(base);
    if (!matcher.matches()) {
      return null;
    }
    int episode = Integer.parseInt(matcher.group(1));
    if (episode <= 0 || episode >= 10000 || isReleaseNumber(episode)) {
      return null;
    }
    return episode;
  }

  /** 是否为特别篇类文件名（特别篇 / SP / Specials / 特典 / OVA 等）。 */
  private static boolean isSpecialEpisodeFileName(String fileName) {
    if (fileName == null || fileName.isBlank()) {
      return false;
    }
    return SPECIAL_EPISODE_FILE_PATTERN
        .matcher(MediaFileParser.removeFileExtension(fileName).trim())
        .matches();
  }

  private static ParsedTitle parseTitle(String value) {
    if (value == null || value.isBlank()) {
      return new ParsedTitle(null, null);
    }
    String title = value.trim();

    title = LEADING_MEDIA_TYPE_LABEL_PATTERN.matcher(title).replaceFirst("");

    // 0.5) 剥离开头的画质/编码标签：4K 圣母在上 → 圣母在上
    title = LEADING_TECHNICAL_TAG_PATTERN.matcher(title).replaceFirst("");

    // 某些剧集目录把季号放在年份后的括号中，如“Z- 照亮你 2023(1)”。
    if (INLINE_SEASON_SUFFIX_PATTERN.matcher(title).find()) {
      String withoutSeason = INLINE_SEASON_SUFFIX_PATTERN.matcher(title).replaceFirst("").trim();
      if (YEAR_PATTERN.matcher(withoutSeason).find()) {
        title = withoutSeason;
      }
    }

    // 1) 剥离文件名开头的 [发布组/字幕组] 标签（未识别的 [xxx] 保留）
    title = TitleCleanupUtils.stripLeadingReleaseGroup(title);

    // 1.1) 剥离目录/文件名中的 TMDB 标识，它只用于定位元数据，不是媒体标题。
    title = TMDB_ID_TAG_PATTERN.matcher(title).replaceAll(" ");

    // 1.5) 剥离资源列表序号，例如“10、熊出没之狂野大陆”。
    title = LEADING_LIST_NUMBER_PATTERN.matcher(title).replaceFirst("");

    // 2) [中文片名].英文名.年份... 结构：括号内是中文且其后还有内容时，取括号内作为标题
    Matcher bracketMatcher = LEADING_BRACKET_PATTERN.matcher(title);
    if (bracketMatcher.find()) {
      String bracketContent = bracketMatcher.group(1);
      String rest = title.substring(bracketMatcher.end());
      if (CJK_PATTERN.matcher(bracketContent).find() && rest.matches("(?s).*[A-Za-z0-9].*")) {
        String bracketYear = null;
        Matcher restYearMatcher = YEAR_PATTERN.matcher(rest);
        if (restYearMatcher.find()) {
          bracketYear = restYearMatcher.group(2);
        }
        return new ParsedTitle(bracketContent.trim(), bracketYear);
      }
    }

    // 3) 删除总集数说明；保留其后的年份，避免“剧名 12集全（2026）”丢失年份。
    Matcher countMatcher = EPISODE_COUNT_TAG_PATTERN.matcher(title);
    if (countMatcher.find()) {
      String beforeCount = title.substring(0, countMatcher.start()).trim();
      String afterCount = title.substring(countMatcher.end()).trim();
      title =
          !afterCount.isBlank() && YEAR_PATTERN.matcher(afterCount).find()
              ? beforeCount + " " + afterCount
              : beforeCount;
    }

    title = SEASON_RANGE_NOISE_PATTERN.matcher(title).replaceFirst("");
    if (CJK_PATTERN.matcher(title).find()) {
      title = ENGLISH_SEASON_NOISE_PATTERN.matcher(title).replaceFirst("");
    }

    // 清除方括号中的音轨/字幕/画质/编码/集数信息，以及未被括号包裹的目录标签。
    title =
        title.replaceAll(
            "(?i)[\\[【][^\\]】]{0,80}(?:集|国语|粤语|字幕|音轨|中字|英字|蓝光|高清|REMUX|HDR|"
                + "1080p|2160p|720p|4K|WEB-?DL|HEVC|H26[45]|X26[45]|AAC|AC3|全集|全\\d+集)"
                + "[^\\]】]*[\\]】]",
            " ");
    title = MEDIA_METADATA_TAG_PATTERN.matcher(title).replaceAll(" ");

    // 3.5) & / ＆ 噪声剥离：与汉字相邻、多个 &、首尾孤立 & 等脏数据直接去除
    if (TitleCleanupUtils.containsAmpersand(title)
        && TitleCleanupUtils.shouldStripAmpersand(title)) {
      title = TitleCleanupUtils.stripAmpersand(title);
    }

    // 4) 提取并移除年份（支持半角/全角括号与分隔符）。
    //    优先括号中的年份：标题可能是数字年份（1923 (2022) → title=1923, year=2022）。
    String year = null;
    Matcher bracketedYearMatcher = BRACKETED_YEAR_PATTERN.matcher(title);
    if (bracketedYearMatcher.find()) {
      year = bracketedYearMatcher.group(1);
      title =
          title.substring(0, bracketedYearMatcher.start(1))
              + title.substring(bracketedYearMatcher.end(1));
    } else {
      Matcher yearMatcher = YEAR_PATTERN.matcher(title);
      if (yearMatcher.find()) {
        year = yearMatcher.group(2);
        title = title.substring(0, yearMatcher.start(2)) + title.substring(yearMatcher.end(2));
      }
    }

    // 5) 从第一个发布标记处截断（画质/编码/字幕/大小等）
    title = TitleCleanupUtils.truncateAtReleaseMarker(title);

    // 6) 移除残留的“第N集”标记并归一化分隔符
    title = CHINESE_EPISODE_PATTERN.matcher(title).replaceAll(" ");
    title = SEPARATORS.matcher(title).replaceAll(" ").replaceAll("\\s+", " ").trim();

    // 6.1) 中文标题尾部直接粘连的画质/编码标签：老狗4K、特种突袭之觉醒（2024）1080p、封神榜H265字幕版
    title = TRAILING_QUALITY_PATTERN.matcher(title).replaceAll("$1").trim();

    return new ParsedTitle(title.isBlank() ? null : title, year);
  }

  /** 保留同一 item 的原始/清洗候选，供外部来源和 AI 消歧使用。 */
  private static MediaInfo withTitleCandidates(
      MediaInfo mediaInfo, String originalTitle, String cleanedTitle) {
    Set<String> candidates = new LinkedHashSet<>();
    addCandidate(candidates, cleanedTitle);
    addCandidate(candidates, originalTitle);
    if (cleanedTitle != null && cleanedTitle.matches(".*[A-Za-z].*[A-Za-z].*")) {
      addCandidate(candidates, cleanedTitle.replaceAll("(?<=[a-z])(?=[A-Z])", " "));
    }
    mediaInfo.setTitleCandidates(new ArrayList<>(candidates));
    return mediaInfo;
  }

  private static void addCandidate(Set<String> candidates, String value) {
    if (value == null || value.isBlank()) {
      return;
    }
    String normalized = value.trim().replaceAll("\\s+", " ");
    if (!normalized.isBlank()) {
      candidates.add(normalized);
      candidates.add(normalized.toLowerCase(Locale.ROOT));
    }
  }

  private static Integer parseInlineSeasonDirectory(String directory) {
    if (directory == null || directory.isBlank()) {
      return null;
    }
    Matcher matcher = INLINE_SEASON_DIRECTORY_PATTERN.matcher(directory.trim());
    if (!matcher.matches()) {
      return null;
    }
    return Integer.parseInt(matcher.group(1));
  }

  private static String extractDirectoryPath(String relativePath) {
    if (relativePath == null || relativePath.isBlank()) {
      return "";
    }
    try {
      Path parent = Paths.get(relativePath).getParent();
      return parent == null ? "" : parent.toString();
    } catch (Exception e) {
      return "";
    }
  }

  /** 兼容任务执行器传入的目录路径，以及单元测试/识别服务传入的完整文件路径。 */
  private static String directoryPathFor(String fileName, String relativePath) {
    if (relativePath == null || relativePath.isBlank()) {
      return "";
    }
    String lastSegment = lastPathSegment(relativePath);
    if (fileName != null && fileName.trim().equalsIgnoreCase(lastSegment)) {
      return extractDirectoryPath(relativePath);
    }
    return relativePath;
  }

  private static List<String> splitPath(String directoryPath) {
    if (directoryPath == null || directoryPath.isBlank()) {
      return Collections.emptyList();
    }
    return Pattern.compile("[/\\\\]+")
        .splitAsStream(directoryPath)
        .filter(segment -> !segment.isBlank())
        .toList();
  }

  private static String lastPathSegment(String directoryPath) {
    List<String> segments = splitPath(directoryPath);
    return segments.isEmpty() ? "" : segments.get(segments.size() - 1);
  }

  private record ParsedTitle(String title, String year) {}

  public record EpisodeInfo(Integer season, Integer episode) {}
}
