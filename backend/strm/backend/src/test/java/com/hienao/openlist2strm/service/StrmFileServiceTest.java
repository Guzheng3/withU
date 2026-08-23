package com.hienao.openlist2strm.service;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.mockito.Mockito.mock;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

class StrmFileServiceTest {

  @TempDir Path tempDir;

  private StrmFileService service;

  @BeforeEach
  void setUp() {
    service = new StrmFileService(mock(SystemConfigService.class), mock(OpenlistApiService.class));
  }

  @Test
  void writesStableUrlWithoutQueryParamsAndFragment() throws IOException {
    String url = "http://ol.example.com:8080/d/电影/Inception.2010.mkv?sign=abc123&token=x#frag";
    Path strmFile = tempDir.resolve("Inception.strm");

    service.generateStrmFile(
        tempDir.toString(), "", "Inception.mkv", url, true, null, config(null, null));

    String content = Files.readString(strmFile).trim();
    assertTrue(content.contains("/Inception.2010.mkv"));
    assertTrue(content.contains("%E7%94%B5%E5%BD%B1"));
    // 保留下载签名（播放鉴权依赖），剥离其余凭据与片段
    assertTrue(content.contains("sign=abc123"), "应保留 sign, 实际: " + content);
    assertFalse(content.contains("token=x"));
    assertFalse(content.contains("#"));
  }

  @Test
  void usesReplacementBaseUrlWhenConfigured() throws IOException {
    String url = "http://old.example.com/d/movie.mkv?sign=abc#frag";
    OpenlistConfig config = config("http://cdn.example.com/strm", null);
    Path strmFile = tempDir.resolve("movie.strm");

    service.generateStrmFile(tempDir.toString(), "", "movie.mkv", url, true, null, config);

    String content = Files.readString(strmFile).trim();
    assertTrue(content.startsWith("http://cdn.example.com/strm/d/movie.mkv"));
    assertTrue(content.contains("sign=abc"), "应保留 sign, 实际: " + content);
    assertFalse(content.contains("#"));
  }

  @Test
  void writesOriginalUrlWhenNoReplacementAndNoCredentials() throws IOException {
    String url = "http://ol.example.com/d/movie.mkv";
    Path strmFile = tempDir.resolve("movie.strm");

    service.generateStrmFile(
        tempDir.toString(), "", "movie.mkv", url, true, null, config(null, null));

    assertEquals(url, Files.readString(strmFile).trim());
  }

  @Test
  void stableUrlIsIndependentOfUrlEncodingEnabled() throws IOException {
    // 无论是否启用 URL 编码，STRM 内容都应保留下载签名、剥离片段
    for (Boolean encode : new Boolean[] {null, Boolean.TRUE, Boolean.FALSE}) {
      String url = "http://ol.example.com/d/电影.mkv?sign=abc#frag";
      Path strmFile = tempDir.resolve("movie-" + encode + ".strm");
      service.generateStrmFile(
          tempDir.toString(), "", "movie-" + encode + ".mkv", url, true, null, config(null, encode));
      String content = Files.readString(strmFile).trim();
      assertTrue(content.contains("sign=abc"), "encode=" + encode + " 实际: " + content);
      assertFalse(content.contains("#"), "encode=" + encode + " 实际: " + content);
    }
  }

  @Test
  void stripsCredentialsFromUrlWithChineseSpacesAndParens() throws IOException {
    // 真实 AList 目录路径：未编码 URL 含中文、空格、括号，URI.create 会解析失败
    String url =
        "http://ol.example.com:5244/d/更新影视库/影视资源库_20260718_084735/电视剧/日韩剧集/白夜行 (2006)/白夜行.2006.S01E01.1080p.BluRay.H.265.mkv?sign=abc123==:0";
    Path strmFile = tempDir.resolve("白夜行.2006.S01E01.1080p.BluRay.H.265.strm");

    service.generateStrmFile(
        tempDir.toString(), "", "白夜行.2006.S01E01.1080p.BluRay.H.265.mkv", url, true, null, config(null, null));

    String content = Files.readString(strmFile).trim();
    assertTrue(content.contains("sign=abc123"), "应保留 sign, 实际: " + content);
    assertFalse(content.contains("#"), "不应包含片段, 实际: " + content);
    assertTrue(content.contains("S01E01"), "应保留文件名, 实际: " + content);
    assertTrue(content.contains("5244"), "应保留端口, 实际: " + content);
  }

  @Test
  void buildStrmFilePathPreservesRelativeDirectories() {
    Path path =
        service.buildStrmFilePath(
            tempDir.toString(), "Movie/2024", "Inception.2010.mkv.strm");
    String str = path.toString().replace("\\", "/");
    assertTrue(
        str.contains("/Movie/2024/Inception.2010.mkv.strm"),
        "tempDir=" + tempDir + " actual=" + str);
  }

  @Test
  void calculateRelativePathDropsTaskPrefix() {
    String rel = service.calculateRelativePath("/data/电影", "/data/电影/Show/S01E01.mkv");
    assertEquals("Show", rel);
  }

  @Test
  void recognizesCommonVideoExtensions() {
    assertTrue(service.isVideoFile("movie.mkv"));
    assertTrue(service.isVideoFile("show.S01E01.mp4"));
    assertTrue(service.isVideoFile("clip.avi"));
    assertTrue(service.isVideoFile("movie.rmvb"));
    assertFalse(service.isVideoFile("video.ts"));
    assertFalse(service.isVideoFile("poster.jpg"));
    assertFalse(service.isVideoFile("subtitle.srt"));
    assertFalse(service.isVideoFile("readme.txt"));
  }

  private OpenlistConfig config(String strmBaseUrl, Boolean enableUrlEncoding) {
    OpenlistConfig config = new OpenlistConfig();
    config.setId(1L);
    config.setStrmBaseUrl(strmBaseUrl);
    config.setEnableUrlEncoding(enableUrlEncoding);
    return config;
  }
}
