package com.hienao.openlist2strm.title;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;

/**
 * 批量片名解析与去重服务（方案第十五步）。
 *
 * <p>去重键：标准片名 + 年份 + 季。
 */
@Slf4j
@Service
@RequiredArgsConstructor
public class BatchTitleResolver {

  private final MediaTitleResolver resolver;

  /** 批量解析并去重。 */
  public List<TitleResolveResult> resolveAll(List<String> paths, String libraryType) {
    List<TitleResolveResult> results = new ArrayList<>();
    for (String path : paths) {
      try {
        results.add(resolver.resolve(path, libraryType));
      } catch (Exception e) {
        log.warn("解析失败: path={}", path, e);
        results.add(
            TitleResolveResult.builder()
                .path(path)
                .id("item_" + Math.abs(path.hashCode()))
                .title(null)
                .year(null)
                .season(null)
                .mediaType(libraryType)
                .confidence(0)
                .status("unresolved")
                .message("解析异常: " + e.getMessage())
                .build());
      }
    }
    return dedup(results);
  }

  /** 去重：相同 片名+年份+季 只保留置信度最高的一条。 */
  public List<TitleResolveResult> dedup(List<TitleResolveResult> results) {
    Map<String, TitleResolveResult> byKey = new LinkedHashMap<>();
    for (TitleResolveResult result : results) {
      if (result.getTitle() == null || result.getTitle().isBlank()) {
        continue;
      }
      String key = dedupKey(result);
      TitleResolveResult existing = byKey.get(key);
      if (existing == null || result.getConfidence() > existing.getConfidence()) {
        byKey.put(key, result);
      }
    }
    return new ArrayList<>(byKey.values());
  }

  /** 去重键：规范化片名 + 年份 + 季。 */
  public static String dedupKey(TitleResolveResult result) {
    return PinyinNormalizer.normalizeName(result.getTitle())
        + "|"
        + (result.getYear() == null ? "" : result.getYear())
        + "|"
        + (result.getSeason() == null ? "" : result.getSeason());
  }
}
