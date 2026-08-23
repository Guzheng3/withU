package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.ApiResponse;
import com.hienao.openlist2strm.title.BatchTitleResolver;
import com.hienao.openlist2strm.title.LocalParseResult;
import com.hienao.openlist2strm.title.MediaPathParser;
import com.hienao.openlist2strm.title.MediaTitleResolver;
import com.hienao.openlist2strm.title.PinyinNormalizer;
import com.hienao.openlist2strm.title.TitleResolveResult;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

/**
 * 片名解析引擎测试控制器
 *
 * <p>提供新版片名解析算法的调试接口（本地规则 / 拼音变体 / 完整解析 / 批量去重）。
 */
@Slf4j
@RestController
@RequestMapping("/api/test/title")
@RequiredArgsConstructor
@Tag(name = "片名解析测试", description = "新版片名解析算法测试接口")
public class TitleTestController {

  private final MediaTitleResolver mediaTitleResolver;
  private final BatchTitleResolver batchTitleResolver;

  /** 仅本地规则解析。 */
  @GetMapping("/local")
  @Operation(summary = "本地规则解析", description = "只执行本地规则解析，不调用外部数据源")
  public ResponseEntity<ApiResponse<Object>> local(
      @RequestParam("path") String path,
      @RequestParam(value = "type", defaultValue = "auto") String type) {
    LocalParseResult result = MediaPathParser.parse(fileName(path), path, type);
    Map<String, Object> data = new LinkedHashMap<>();
    data.put("path", path);
    data.put("filename", result.getFilename());
    data.put("directories", result.getDirectories());
    data.put("mediaType", result.getMediaType());
    data.put("titleCandidates", result.getTitleCandidates());
    data.put("year", result.getYear());
    data.put("yearSource", result.getYearSource());
    data.put("season", result.getSeason());
    data.put("episode", result.getEpisode());
    data.put("confidence", result.getConfidence());
    data.put("skip", result.isSkip());
    data.put("skipReason", result.getSkipReason());
    return ResponseEntity.ok(ApiResponse.success(data));
  }

  /** 拼音/中拼变体生成。 */
  @GetMapping("/pinyin")
  @Operation(summary = "拼音变体", description = "生成中文/中拼混写标题的搜索变体")
  public ResponseEntity<ApiResponse<Object>> pinyin(@RequestParam("title") String title) {
    List<String> variants = PinyinNormalizer.buildVariants(title);
    Map<String, Object> data = new LinkedHashMap<>();
    data.put("title", title);
    data.put("pinyin", PinyinNormalizer.toPinyin(title));
    data.put("normalized", PinyinNormalizer.normalizeName(title));
    data.put("variants", variants);
    return ResponseEntity.ok(ApiResponse.success(data));
  }

  /** 完整解析（本地 + TMDB 确认 + 评分）。 */
  @GetMapping("/resolve")
  @Operation(summary = "完整解析", description = "执行完整解析流程（本地规则 + 元数据数据源 + 评分）")
  public ResponseEntity<ApiResponse<Object>> resolve(
      @RequestParam("path") String path,
      @RequestParam(value = "type", defaultValue = "auto") String type) {
    TitleResolveResult result = mediaTitleResolver.resolve(path, type);
    return ResponseEntity.ok(ApiResponse.success(toMap(result)));
  }

  /** 批量解析 + 去重。 */
  @PostMapping("/batch")
  @Operation(summary = "批量解析", description = "批量解析多个路径并按 片名+年份+季 去重")
  public ResponseEntity<ApiResponse<Object>> batch(
      @RequestBody Map<String, Object> request) {
    @SuppressWarnings("unchecked")
    List<String> paths = (List<String>) request.getOrDefault("paths", List.of());
    String type = String.valueOf(request.getOrDefault("type", "auto"));
    List<TitleResolveResult> results = batchTitleResolver.resolveAll(paths, type);
    List<Map<String, Object>> list = new ArrayList<>();
    for (TitleResolveResult result : results) {
      list.add(toMap(result));
    }
    return ResponseEntity.ok(ApiResponse.success(list));
  }

  private Map<String, Object> toMap(TitleResolveResult result) {
    Map<String, Object> data = new LinkedHashMap<>();
    data.put("id", result.getId());
    data.put("path", result.getPath());
    data.put("title", result.getTitle());
    data.put("year", result.getYear());
    data.put("season", result.getSeason());
    data.put("episode", result.getEpisode());
    data.put("mediaType", result.getMediaType());
    data.put("tmdbId", result.getTmdbId());
    data.put("confidence", result.getConfidence());
    data.put("status", result.getStatus());
    data.put("evidenceIds", result.getEvidenceIds());
    data.put("message", result.getMessage());
    if (result.getLocal() != null) {
      data.put("localTitleCandidates", result.getLocal().getTitleCandidates());
      data.put("localYear", result.getLocal().getYear());
      data.put("localConfidence", result.getLocal().getConfidence());
    }
    List<Map<String, Object>> candidates = new ArrayList<>();
    if (result.getMetadataCandidates() != null) {
      for (var c : result.getMetadataCandidates()) {
        Map<String, Object> cm = new LinkedHashMap<>();
        cm.put("source", c.getSource());
        cm.put("id", c.getId());
        cm.put("title", c.getTitle());
        cm.put("year", c.getYear());
        cm.put("mediaType", c.getMediaType());
        cm.put("voteAverage", c.getVoteAverage());
        cm.put("evidence", c.getEvidence());
        if (c.getTitle() != null
            && result.getLocal() != null
            && result.getLocal().getTitleCandidates() != null
            && !result.getLocal().getTitleCandidates().isEmpty()) {
          cm.put(
              "similarity",
              PinyinNormalizer.nameSimilarity(
                  c.getTitle(), result.getLocal().getTitleCandidates().get(0)));
        }
        candidates.add(cm);
      }
    }
    data.put("metadataCandidates", candidates);
    return data;
  }

  private static String fileName(String path) {
    if (path == null || path.isBlank()) {
      return "";
    }
    String[] parts = path.split("[/\\\\]+");
    return parts[parts.length - 1];
  }
}
