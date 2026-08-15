package com.hienao.openlist2strm.title;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import net.sourceforge.pinyin4j.PinyinHelper;
import net.sourceforge.pinyin4j.format.HanyuPinyinCaseType;
import net.sourceforge.pinyin4j.format.HanyuPinyinOutputFormat;
import net.sourceforge.pinyin4j.format.HanyuPinyinToneType;
import net.sourceforge.pinyin4j.format.exception.BadHanyuPinyinOutputFormatCombination;

/**
 * 中文/拼音规范化工具。
 *
 * <p>支持：中文转拼音（无音调）、混合中拼命名变体生成（如 "熊出没之chong启未来" → "熊出没之chong启未来" +
 * "xiongchumeizhichongqiweilai"）、以及用于候选比较的规范化键。
 */
public final class PinyinNormalizer {

  private static final HanyuPinyinOutputFormat FORMAT = new HanyuPinyinOutputFormat();

  static {
    FORMAT.setCaseType(HanyuPinyinCaseType.LOWERCASE);
    FORMAT.setToneType(HanyuPinyinToneType.WITHOUT_TONE);
  }

  private PinyinNormalizer() {}

  /** 中文转拼音（无音调），非汉字原样保留。 */
  public static String toPinyin(String text) {
    if (text == null || text.isBlank()) {
      return text;
    }
    StringBuilder sb = new StringBuilder();
    for (char ch : text.toCharArray()) {
      if (Character.isWhitespace(ch)) {
        continue;
      }
      if (Character.UnicodeBlock.of(ch) == Character.UnicodeBlock.CJK_UNIFIED_IDEOGRAPHS) {
        try {
          String[] pinyins = PinyinHelper.toHanyuPinyinStringArray(ch, FORMAT);
          if (pinyins != null && pinyins.length > 0) {
            sb.append(pinyins[0]);
            continue;
          }
        } catch (BadHanyuPinyinOutputFormatCombination e) {
          sb.append(ch);
          continue;
        }
      }
      sb.append(ch);
    }
    return sb.toString();
  }

  /**
   * 生成标题的搜索变体，按优先级排序。
   *
   * <p>对于包含拼音混写的名称（如 "熊出没之chong启未来"），同时生成：
   *
   * <pre>
   * 熊出没之chong启未来
   * 熊出没之chong启未来（去除标点后）
   * 熊出没之启未来（去除拼音片段后）
   * 熊出没（拼音前的纯中文前缀）
   * xiongchumeizhichongqiweilai
   * </pre>
   */
  public static List<String> buildVariants(String title) {
    if (title == null || title.isBlank()) {
      return List.of();
    }
    String trimmed = title.trim();
    Set<String> variants = new LinkedHashSet<>();
    variants.add(trimmed);

    String compact = normalizeName(trimmed);
    if (!compact.equals(trimmed)) {
      variants.add(compact);
    }

    // 去掉纯拼音片段后的中文候选（如 熊出没之chong启未来 → 熊出没之启未来）
    String chineseOnly = trimmed.replaceAll("[a-zA-Z0-9]+", "").trim();
    if (!chineseOnly.isBlank() && !variants.contains(chineseOnly)) {
      variants.add(chineseOnly);
    }

    // 拼音前的纯中文前缀（如 熊出没），对系列片命中 TMDB 尤为关键
    Matcher firstLatin = Pattern.compile("[a-zA-Z]").matcher(trimmed);
    if (firstLatin.find() && firstLatin.start() > 0) {
      String chinesePrefix =
          trimmed.substring(0, firstLatin.start()).trim().replaceFirst("[之的第]+$", "");
      if (!chinesePrefix.isBlank() && !variants.contains(chinesePrefix)) {
        variants.add(chinesePrefix);
      }
    }

    String pinyin = toPinyin(trimmed);
    if (!pinyin.equals(compact)) {
      variants.add(pinyin);
    }

    // 中英文之间加空格的分词形式（如 熊出没之 chong 启未来）
    String spaced = trimmed.replaceAll("([\\u4e00-\\u9fff])([a-zA-Z])", "$1 $2")
        .replaceAll("([a-zA-Z])([\\u4e00-\\u9fff])", "$1 $2")
        .replaceAll("\\s+", " ")
        .trim();
    if (!spaced.equals(trimmed) && !variants.contains(spaced)) {
      variants.add(spaced);
    }

    return new ArrayList<>(variants);
  }

  /** 规范化为仅小写字母数字（用于候选比较）。 */
  public static String normalizeName(String text) {
    if (text == null) {
      return "";
    }
    return text.toLowerCase().replaceAll("[^a-z0-9\\u4e00-\\u9fff]", "");
  }

  /** 比较两个名称是否等价（考虑中拼混写与标点差异）。 */
  public static boolean namesEquivalent(String a, String b) {
    if (a == null || b == null) {
      return false;
    }
    String na = normalizeName(a);
    String nb = normalizeName(b);
    if (na.isEmpty() || nb.isEmpty()) {
      return false;
    }
    if (na.equals(nb)) {
      return true;
    }
    // 拼音等价
    return toPinyin(na).equals(toPinyin(nb));
  }

  /** 计算名称相似度 0.0-1.0（归一化后字符重叠率）。 */
  public static double nameSimilarity(String a, String b) {
    if (a == null || b == null) {
      return 0.0;
    }
    String na = normalizeName(a);
    String nb = normalizeName(b);
    if (na.isEmpty() || nb.isEmpty()) {
      return 0.0;
    }
    if (na.equals(nb)) {
      return 1.0;
    }
    String pa = toPinyin(na);
    String pb = toPinyin(nb);
    if (pa.equals(pb)) {
      return 1.0;
    }
    // 编辑距离相似度（较短的作为基准）
    String shorter = pa.length() <= pb.length() ? pa : pb;
    String longer = pa.length() <= pb.length() ? pb : pa;
    if (longer.isEmpty()) {
      return 0.0;
    }
    int distance = levenshtein(shorter, longer);
    return Math.max(0.0, 1.0 - (double) distance / longer.length());
  }

  /**
   * 判断候选标题是否包含本地标题的纯中文前缀（如 熊出没之chong启未来 与 熊出没·重启未来 共享 熊出没）。
   */
  public static boolean containsChinesePrefix(String local, String candidate) {
    if (local == null || candidate == null) {
      return false;
    }
    String chinesePrefix = chinesePrefix(local);
    if (chinesePrefix.isEmpty()) {
      return false;
    }
    return normalizeName(candidate).contains(normalizeName(chinesePrefix));
  }

  private static String chinesePrefix(String text) {
    StringBuilder sb = new StringBuilder();
    for (int i = 0; i < text.length(); i++) {
      char c = text.charAt(i);
      if (Character.UnicodeBlock.of(c) == Character.UnicodeBlock.CJK_UNIFIED_IDEOGRAPHS) {
        sb.append(c);
      } else {
        break;
      }
    }
    return sb.toString().trim();
  }

  private static int levenshtein(String a, String b) {
    int[] prev = new int[b.length() + 1];
    int[] curr = new int[b.length() + 1];
    for (int j = 0; j <= b.length(); j++) {
      prev[j] = j;
    }
    for (int i = 1; i <= a.length(); i++) {
      curr[0] = i;
      for (int j = 1; j <= b.length(); j++) {
        int cost = a.charAt(i - 1) == b.charAt(j - 1) ? 0 : 1;
        curr[j] =
            Math.min(
                Math.min(curr[j - 1] + 1, prev[j] + 1), prev[j - 1] + cost);
      }
      int[] tmp = prev;
      prev = curr;
      curr = tmp;
    }
    return prev[b.length()];
  }
}
