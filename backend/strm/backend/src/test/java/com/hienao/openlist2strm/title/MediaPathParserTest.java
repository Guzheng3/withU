package com.hienao.openlist2strm.title;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.List;
import java.util.stream.Stream;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;

class MediaPathParserTest {

  @ParameterizedTest(name = "{0}")
  @MethodSource("titleSamples")
  void parsesRepresentativePaths(
      String name,
      String path,
      String type,
      String expectedTitle,
      String expectedYear,
      Integer expectedSeason,
      Integer expectedEpisode,
      String expectedMediaType) {
    LocalParseResult result = MediaPathParser.parse(fileName(path), path, type);

    assertFalse(result.isSkip(), name);
    assertEquals(expectedTitle, result.getTitleCandidates().get(0), name);
    assertEquals(expectedYear, result.getYear(), name);
    assertEquals(expectedSeason, result.getSeason(), name);
    assertEquals(expectedEpisode, result.getEpisode(), name);
    assertEquals(expectedMediaType, result.getMediaType(), name);
  }

  @ParameterizedTest
  @MethodSource("technicalDirectories")
  void filtersTechnicalAndNavigationDirectories(String directory) {
    assertTrue(MediaPathParser.isTechnicalDirectory(directory));
  }

  @ParameterizedTest
  @MethodSource("validEnglishTitles")
  void preservesEnglishTitleDirectories(String directory) {
    assertFalse(MediaPathParser.isTechnicalDirectory(directory));
  }

  @Test
  void skipsAuxiliaryFiles() {
    LocalParseResult result =
        MediaPathParser.parse("poster.jpg", "/电影/A/星际穿越/poster.jpg", "movie");

    assertTrue(result.isSkip());
    assertNull(result.getTitleCandidates());
  }

  private static Stream<Arguments> titleSamples() {
    return Stream.of(
        Arguments.of(
            "混拼电影与清晰度文件",
            "/动漫/A/熊出没系列大电影合集/12、熊出没之chong启未来/4K.mp4",
            "movie",
            "熊出没之 chong 启未来",
            null,
            null,
            null,
            "movie"),
        Arguments.of(
            "电影文件名年份",
            "/电影/I/Inception (2010)/Inception.2010.1080p.mkv",
            "movie",
            "Inception",
            "2010",
            null,
            null,
            "movie"),
        Arguments.of(
            "单字母索引与总集数目录",
            "/电视剧/Q/庆余年/共46集/Season 01/庆余年.S01E02.mkv",
            "tv",
            "庆余年",
            null,
            1,
            2,
            "tv"),
        Arguments.of(
            "标题尾部总集数标记",
            "/电视剧/F/凡人修仙传 共100集/Season 02/凡人修仙传.S02E12.mkv",
            "tv",
            "凡人修仙传",
            null,
            2,
            12,
            "tv"),
        Arguments.of(
            "全24集目录",
            "/动漫/A-Z/葬送的芙莉莲/全24集/第03集.mkv",
            "anime",
            "葬送的芙莉莲",
            null,
            null,
            3,
            "tv"),
        Arguments.of(
            "24集全目录",
            "/电视剧/0-9/三体/24集全/三体.S01E08.2160p.mkv",
            "tv",
            "三体",
            null,
            1,
            8,
            "tv"),
        Arguments.of(
            "更新进度目录",
            "/电视剧/M/漫长的季节/更新至12集/漫长的季节.S01E12.mkv",
            "tv",
            "漫长的季节",
            null,
            1,
            12,
            "tv"),
        Arguments.of(
            "中文季集",
            "/电视剧/L/琅琊榜 第1季 共54集/第12集.mkv",
            "tv",
            "琅琊榜",
            null,
            1,
            12,
            "tv"),
        Arguments.of(
            "英文剧集标题保留",
            "/TV/B/Breaking Bad/Season 05/Breaking.Bad.S05E14.1080p.mkv",
            "tv",
            "Breaking Bad",
            null,
            5,
            14,
            "tv"),
        Arguments.of(
            "电影合集目录回溯",
            "/电影/C/Christopher Nolan Collection/星际穿越 (2014)/4K.mkv",
            "movie",
            "星际穿越",
            "2014",
            null,
            null,
            "movie"),
        Arguments.of(
            "通用电影文件名回溯",
            "/电影/熊出没·重返地球 (2022)/movie.mkv",
            "movie",
            "熊出没·重返地球",
            "2022",
            null,
            null,
            "movie"),
        Arguments.of(
            "英文剧集带技术标签剥离",
            "/悬案 (2026)/Unsettled.Case.S01E05.2026.2160p.HQ.WEB-DL.H265.HDR.60fps.AAC.mkv",
            "tv",
            "悬案",
            "2026",
            1,
            5,
            "tv"),
        Arguments.of(
            "SDR规格词剥离，标题从父目录回溯",
            "/媒体/S.D.R./21.4K.SDR.60fps.mp4",
            "movie",
            "S D R",
            null,
            null,
            null,
            "movie"),
        Arguments.of(
            "SDR规格词不污染真实标题",
            "/电影/T/Toy.Story.3.2010.1080p.HDR.SDR.mkv",
            "movie",
            "Toy Story 3",
            "2010",
            null,
            null,
            "movie"),
        Arguments.of(
            "剧集文件在电影库下仍判为剧集",
            "/Movies/Unsettled Case (2026)/Unsettled.Case.S01E05.2026.2160p.HQ.WEB-DL.H265.HDR.60fps.AAC.mkv",
            "movie",
            "Unsettled Case",
            "2026",
            1,
            5,
            "tv"));
  }

  private static Stream<String> technicalDirectories() {
    return Stream.of(
        "A", "z", "A-Z", "A 至 Z", "0-9", "共46集", "全24集", "24集全", "更新至12集", "4K", "Season 02");
  }

  private static Stream<String> validEnglishTitles() {
    return Stream.of("Inception", "Breaking Bad", "Lost", "Dark", "The Bear");
  }

  private static String fileName(String path) {
    List<String> segments = MediaPathParser.splitPath(path);
    return segments.get(segments.size() - 1);
  }
}
