package com.hienao.openlist2strm.title;

import java.util.List;
import java.util.regex.Pattern;

/** 片名解析引擎使用的常量与正则。 */
public final class TitleConstants {

  private TitleConstants() {}

  /** 视频文件扩展名。 */
  public static final List<String> VIDEO_EXTENSIONS =
      List.of(
          ".mp4", ".mkv", ".avi", ".mov", ".wmv", ".flv", ".ts", ".m2ts", ".rmvb", ".webm",
          ".iso", ".mpg", ".mpeg", ".m4v", ".3gp", ".3g2", ".asf", ".divx", ".f4v", ".m2v",
          ".mts", ".ogv", ".rm", ".vob", ".xvid");

  /** 忽略的辅助文件扩展名（字幕、图片、文本等）。 */
  public static final List<String> IGNORED_EXTENSIONS =
      List.of(
          ".srt", ".ass", ".ssa", ".sup", ".sub", ".bif", ".nfo", ".idx", ".vtt",
          ".jpg", ".jpeg", ".png", ".webp", ".bmp", ".gif", ".tif", ".tiff", ".txt");

  /** 技术参数 / 清晰度目录（不能作为片名）。 */
  public static final Pattern TECHNICAL_DIR_RE =
      Pattern.compile(
          "(?i)^(4k|8k|2160p|1080p|720p|480p|360p|"
              + "web[-. ]?dl|webrip|bluray|remux|hdr|dv|hdr10|"
              + "disc[\\s._-]?\\d+|discs[\\s._-]?\\d+|specials|extras|"
              + "杜比视界|原盘|中字|国语|粤语|国粤双语|无字幕|简中|繁中|chinese|"
              + "x26[45]|h26[45]|hevc|avc|aac|ac3|dts|truehd|atmos|"
              + "hq|hd|fhd|uhd|fps|\\d+fps)$");

  /** 合集 / 系列目录（只能作为集合目录，不能作为影片名称）。 */
  public static final Pattern COLLECTION_RE =
      Pattern.compile("(?i)(合集|全集|系列|典藏|collection|boxset|complete|bundle|套装)");

  /** 导航/索引目录（如 A、A-Z、0-9），不能作为片名。 */
  public static final Pattern INDEX_DIR_RE =
      Pattern.compile("(?i)^(?:[a-z]|[a-z]\\s*[-~至]\\s*[a-z]|0\\s*[-~至]\\s*9)$");

  /** 总集数标记：共40集、全24集、24集全、更新至12集。 */
  public static final Pattern TOTAL_EPISODES_RE =
      Pattern.compile(
          "(?i)(?:共\\s*\\d{1,4}\\s*[集话]|全\\s*\\d{1,4}\\s*[集话]|"
              + "\\d{1,4}\\s*[集话]\\s*全|更新至\\s*\\d{1,4}\\s*[集话])");

  /** 仅由总集数标记组成的目录。 */
  public static final Pattern EPISODE_COUNT_DIR_RE =
      Pattern.compile(
          "(?i)^(?:共\\s*\\d{1,4}\\s*[集话]|全\\s*\\d{1,4}\\s*[集话]|"
              + "\\d{1,4}\\s*[集话]\\s*全|更新至\\s*\\d{1,4}\\s*[集话])$");

  /** 年份：19xx / 20xx，前后不能紧跟数字。 */
  public static final Pattern YEAR_RE = Pattern.compile("(?<!\\d)(?<year>(?:19|20)\\d{2})(?!\\d)");

  /** SxxExx / Sxx.EExx / Sxx-EExx。 */
  public static final Pattern SEASON_EPISODE_RE =
      Pattern.compile("(?i)\\bS(?<season>\\d{1,2})(?:[\\s._-]*E(?<episode>\\d{1,4}))?\\b");

  /** Season 1 / Season01 目录。 */
  public static final Pattern SEASON_DIR_RE =
      Pattern.compile("(?i)^(?:season|seasons)[\\s._-]*(\\d{1,2})$");

  /** 第1季 / 第 12 季。 */
  public static final Pattern CHINESE_SEASON_RE = Pattern.compile("第\\s*(\\d{1,3})\\s*季");

  /** 第1集 / 第 12 话。 */
  public static final Pattern CHINESE_EPISODE_RE = Pattern.compile("第\\s*(\\d{1,4})\\s*[集话]");

  /** 清晰度等文件标签，用于清理文件名时剥离。 */
  public static final Pattern RELEASE_TAG_RE =
      Pattern.compile(
          "(?i)\\b(?:2160p|1080p|720p|480p|4k|8k|uhd|hdr|sdr|bluray|web-?dl|webrip|hdtv|"
              + "remux|x26[45]|h26[45]|hevc|avc|aac|dts|truehd|atmos|10bit|"
              + "dts-hd(?:\\.ma)?|true[-_]?hd|hd\\.ma|"
              + "hq|hd|fhd|webdl|\\d+fps|30fps|25fps|24fps|23.976fps|"
              + "repack|proper|extended|remastered|imax|hdcam|dvdscr|bdrip|dvdrip)\\b");

  /** 文件名中的技术标签（含中文音轨/字幕标签）。 */
  public static final Pattern CLEAN_TAG_RE =
      Pattern.compile(
          "(?i)(?:\\b(?:bluray|bdrip|dvdrip|webrip|web-?dl|webdl|web|hdtv|hdcam|ts|tc|scr|r5|dvdscr|"
              + "1080p|720p|480p|2160p|4k|uhd|hdr|sdr|hdr10|dv|x26[45]|h26[45]|hevc|avc|"
              + "aac|ac3|dts|truehd|atmos|5\\.1|7\\.1|"
              + "dts-hd(?:\\.ma)?|true[-_]?hd|hd\\.ma|"
              + "chinese|english|mandarin|cantonese|subtitle|sub|chs|cht|eng|"
              + "complete|repack|proper|limited|unrated|extended|director|cut|"
              + "hq|hd|fhd|webdl|fps|\\d+fps)\\b)"
              + "|(内封|外挂|简体|繁体|中字|英字|双语|国语|粤语|原声)");

  /** 分隔符。 */
  public static final Pattern SEPARATORS = Pattern.compile("[.\\-_\\[\\]()、，,；;：:\\s]+");
}
