package com.hienao.openlist2strm.util;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * 资源名称清洗工具：剥离文件名开头的发布组/字幕组标签，并在发布标记处截断标题。
 *
 * @author hienao
 * @since 2024-01-01
 */
public final class TitleCleanupUtils {

  private TitleCleanupUtils() {}

  /** 开头的 [发布组] / 【字幕组】 标签 */
  private static final Pattern LEADING_BRACKET =
      Pattern.compile("^\\s*[\\[【]\\s*([^\\]】]{1,40})\\s*[\\]】]");

  /** 常见发布组/字幕组名称 */
  private static final Pattern KNOWN_RELEASE_GROUPS =
      Pattern.compile(
          "(?i)^(?:"
              + "喵萌奶茶屋|喵萌字幕|爱恋字幕组|幻之字幕组|千夏字幕组|桜都字幕组|樱花字幕组|"
              + "诸神字幕组|悠哈璃羽|LoliHouse|动漫花园|极影字幕社|澄空学园|华盟字幕社|"
              + "豌豆字幕组|天雪字幕组|银梦字幕组|动漫国|紫音字幕组|风车字幕组|白月字幕组|"
              + "冰封字幕组|天月字幕组|星云字幕组|夜莺字幕组|风之圣殿|曙光社|红茶字幕组|"
              + "漫游字幕组|自由字幕组|恶俗字幕组|幻樱字幕组|动音漫影|"
              + "ANi|SubsPlease|Erai-raws|Judas|HorribleSubs|Commie|UTW|DHR|HKG|KTXP|"
              + "CASO|Sumisora|KNA|DMG|VCB-Studio|Beatrice-Raws|Snow-Raws|Ai-Raws|"
              + "Moozzi2|ReinForce|Ohys-Raws|Yosora|Nekomoe|MingYue|SweetSub|Mabors|"
              + "XKsub|AGE动漫|星空字幕组|猎户座|幻听字幕组|银翼字幕组|桜都)"
              + "\\s*$");

  /** 单个拉丁字母加分隔符的中文片名包装，例如 "Z- 照亮你"。 */
  private static final Pattern SINGLE_LETTER_PREFIX =
      Pattern.compile("^\\s*[A-Za-z](?:[\\s._-]+)(?=\\p{IsHan})");

  /**
   * 去掉中文片名前的单个拉丁字母前缀。
   *
   * <p>只处理单个字母后紧跟分隔符且分隔符后是中文的情况，避免误伤 "The Last of Us"、"Zebra" 等正常英文片名。
   */
  public static String stripSingleLetterPrefix(String value) {
    if (value == null || value.isBlank()) {
      return value;
    }
    Matcher matcher = SINGLE_LETTER_PREFIX.matcher(value);
    return matcher.find() ? value.substring(matcher.end()).trim() : value;
  }

  /** 发布标记：画质/编码/音轨/字幕/大小等，命中后标题只保留其之前的内容 */
  private static final Pattern RELEASE_MARKER =
      Pattern.compile(
          "(?i)[\\[【（(]\\s*\\d+(?:\\.\\d+)?\\s*(?:TB|GB|MB|KB)?\\s*[\\]】）)]"
              + "|(?:^|[\\s._\\-（【\\[])(?:"
              + "2160p|2180p|1080p|720p|480p|360p|8k|4k|uhd|hdr(?:10)?|sdr|dolby|vision|bd1080p|"
              + "bluray|bdrip|dvdrip|web-?dl|webrip|hdtv|hdcam|hdts|remux|"
              + "x26[45]|h26[45]|hevc|avc|av1|vp9|"
              + "aac|ac3|eac3|ddp|dts|truehd|atmos|flac|mp3|opus|"
              + "60fps|120fps|50fps|30fps|24fps|10bit|12bit|"
              + "国语中字|中文字幕|简繁中字|中英双字|国粤双语|国语|粤语|中字|英字|双语|无水印|高清|"
              + "complete|repack|proper|limited|unrated|extended|directors?)");

  /** 剥离文件名开头的发布组/字幕组标签。 只剥离已识别的发布组名称，不认识的 [xxx] 保留（可能是中文片名，如 [新僵尸先生]）。 */
  public static String stripLeadingReleaseGroup(String value) {
    if (value == null || value.isBlank()) {
      return value;
    }
    Matcher matcher = LEADING_BRACKET.matcher(value);
    if (!matcher.find()) {
      return value;
    }
    String group = matcher.group(1);
    if (KNOWN_RELEASE_GROUPS.matcher(group).matches()) {
      return value.substring(matcher.end()).trim();
    }
    return value;
  }

  /** 从第一个发布标记处截断标题，返回标记之前的内容（不含标记）。 */
  public static String truncateAtReleaseMarker(String value) {
    if (value == null || value.isBlank()) {
      return value;
    }
    Matcher matcher = RELEASE_MARKER.matcher(value);
    if (matcher.find()) {
      return value.substring(0, matcher.start()).trim();
    }
    return value;
  }

  /** 全角/半角 & 符号 */
  private static final Pattern AMPERSAND_PATTERN = Pattern.compile("[&＆]");

  /** 已知合法包含 & 的专有名词/品牌（小写匹配，命中即保留） */
  private static final java.util.Set<String> KNOWN_AMP_PHRASES =
      java.util.Set.of(
          "at&t",
          "r&d",
          "m&a",
          "h&m",
          "p&g",
          "l&d",
          "b&b",
          "a&w",
          "s&p",
          "a&p",
          "d&b",
          "c&a",
          "d&g",
          "g&l",
          "e&j",
          "r&j",
          "m&m",
          "tom & jerry",
          "ben & jerry",
          "simon & schuster",
          "barnes & noble",
          "law & order",
          "procter & gamble");

  /** 判断字符串是否包含 & 或 ＆ */
  public static boolean containsAmpersand(String value) {
    return value != null && AMPERSAND_PATTERN.matcher(value).find();
  }

  /**
   * 判断标题中的 & 是否为脏数据（应剥离），而非作品/品牌的一部分。
   *
   * <p>规则优先级：
   *
   * <ol>
   *   <li>已知专有名词/品牌（AT&amp;T、R&amp;D、H&amp;M、Tom &amp; Jerry 等）→ 保留；
   *   <li>同一标题出现 2 个及以上 & → 剥离（正常标题极少有多个有意义的 &amp;）；
   *   <li>&amp; 位于标题首尾 → 剥离（孤立分隔符）；
   *   <li>&amp; 与 CJK 字符相邻（龙&amp;族、委佳宇＆李冰冰）→ 剥离（中文标题不使用 &amp; 做连词）；
   *   <li>&amp; 两侧为拉丁字母（R&amp;D 风格缩写）→ 保留；
   *   <li>其余情况（如 Tom &amp; Jerry 式带空格连词）→ 模棱两可，保留交由 AI/人工判断。
   * </ol>
   */
  public static boolean shouldStripAmpersand(String value) {
    if (value == null || value.isBlank() || !containsAmpersand(value)) {
      return false;
    }
    String normalized = value.replace('＆', '&');
    String lower = normalized.toLowerCase(java.util.Locale.ROOT);

    // 1) 已知专有名词 → 保留
    for (String phrase : KNOWN_AMP_PHRASES) {
      if (lower.contains(phrase)) {
        return false;
      }
    }

    // 2) 多个 & → 剥离
    if (countAmpersands(normalized) >= 2) {
      return true;
    }

    // 3) 首尾孤立 & → 剥离
    if (normalized.startsWith("&") || normalized.endsWith("&")) {
      return true;
    }

    // 4) 逐字符判断相邻字符（跳过空白）
    for (int i = 0; i < normalized.length(); i++) {
      if (normalized.charAt(i) != '&') {
        continue;
      }
      char left = previousNonSpaceChar(normalized, i);
      char right = nextNonSpaceChar(normalized, i);
      // 4.1) 与 CJK 相邻 → 剥离
      if (isCjk(left) || isCjk(right)) {
        return true;
      }
      // 4.2) 两侧为拉丁字母 → 缩写风格，保留
      if (isAsciiLetter(left) && isAsciiLetter(right)) {
        continue;
      }
      // 4.3) 两侧为数字（如 2025&2026）→ 数值区间噪声，剥离
      if (isAsciiDigit(left) && isAsciiDigit(right)) {
        return true;
      }
      // 4.4) 其余情况 → 模棱两可，保留交由 AI 判断
      return false;
    }
    return false;
  }

  /** 剥离标题中的 & / ＆，替换为空格并归一化连续空白。 */
  public static String stripAmpersand(String value) {
    if (value == null || !containsAmpersand(value)) {
      return value;
    }
    return AMPERSAND_PATTERN.matcher(value).replaceAll(" ").replaceAll("\\s+", " ").trim();
  }

  /** 将全角 ＆ 归一化为半角 &，其余不变。 */
  public static String normalizeAmpersand(String value) {
    if (value == null || value.indexOf('＆') < 0) {
      return value;
    }
    return value.replace('＆', '&');
  }

  private static int countAmpersands(String value) {
    int count = 0;
    for (int i = 0; i < value.length(); i++) {
      if (value.charAt(i) == '&') {
        count++;
      }
    }
    return count;
  }

  private static char previousNonSpaceChar(String value, int index) {
    for (int i = index - 1; i >= 0; i--) {
      if (!Character.isWhitespace(value.charAt(i))) {
        return value.charAt(i);
      }
    }
    return '\0';
  }

  private static char nextNonSpaceChar(String value, int index) {
    for (int i = index + 1; i < value.length(); i++) {
      if (!Character.isWhitespace(value.charAt(i))) {
        return value.charAt(i);
      }
    }
    return '\0';
  }

  private static boolean isCjk(char c) {
    return Character.UnicodeScript.of(c) == Character.UnicodeScript.HAN;
  }

  private static boolean isAsciiLetter(char c) {
    return (c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z');
  }

  private static boolean isAsciiDigit(char c) {
    return c >= '0' && c <= '9';
  }
}
