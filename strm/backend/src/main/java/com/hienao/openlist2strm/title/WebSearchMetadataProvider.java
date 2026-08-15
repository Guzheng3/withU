package com.hienao.openlist2strm.title;

import com.hienao.openlist2strm.service.SystemConfigService;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

/**
 * 联网搜索数据源适配器。
 *
 * <p>用于 TMDB 没有的最新电影、拼音混写、中文别名、上映年份确认。配置项：web_search.enabled /
 * web_search.provider / web_search.apiKey。未配置时自动降级为空结果。
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class WebSearchMetadataProvider implements MetadataProvider {

  private final SystemConfigService systemConfigService;

  @Override
  public String name() {
    return "web_search";
  }

  @Override
  public boolean isEnabled() {
    try {
      Map<String, Object> config = webSearchConfig();
      Object enabled = config.getOrDefault("enabled", false);
      String apiKey = (String) config.getOrDefault("apiKey", "");
      return Boolean.TRUE.equals(enabled)
          && apiKey != null
          && !apiKey.trim().isEmpty();
    } catch (Exception e) {
      return false;
    }
  }

  @Override
  public List<MetadataCandidate> search(String title, String year, String mediaType) {
    if (!isEnabled()) {
      log.debug("联网搜索未配置，跳过");
      return List.of();
    }
    // TODO: 接入具体搜索 API（Tavily / Bing / Google CSE 等）；未接入前返回空结果
    log.info("联网搜索数据源已配置但尚未接入具体接口，返回空结果: {}", title);
    return List.of();
  }

  @SuppressWarnings("unchecked")
  private Map<String, Object> webSearchConfig() {
    return (Map<String, Object>)
        systemConfigService.getSystemConfig().getOrDefault("web_search", Map.of());
  }
}
