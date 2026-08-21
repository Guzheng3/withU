package com.hienao.openlist2strm.title;

import com.hienao.openlist2strm.service.SystemConfigService;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

/**
 * DMDB 数据源适配器（可替换）。
 *
 * <p>当前环境未配置 DMDB 接口时自动降级为空结果。地址、参数、密钥从配置读取，配置项：dmdb.baseUrl /
 * dmdb.apiKey / dmdb.enabled。
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class DmdbMetadataProvider implements MetadataProvider {

  private final SystemConfigService systemConfigService;

  @Override
  public String name() {
    return "dmdb";
  }

  @Override
  public boolean isEnabled() {
    try {
      Map<String, Object> config = dmdbConfig();
      Object enabled = config.getOrDefault("enabled", false);
      String baseUrl = (String) config.getOrDefault("baseUrl", "");
      return Boolean.TRUE.equals(enabled)
          && baseUrl != null
          && !baseUrl.trim().isEmpty();
    } catch (Exception e) {
      return false;
    }
  }

  @Override
  public List<MetadataCandidate> search(String title, String year, String mediaType) {
    if (!isEnabled()) {
      log.debug("DMDB 未配置，跳过");
      return List.of();
    }
    // TODO: 接入具体 DMDB 接口；未接入前返回空结果
    log.info("DMDB 数据源已配置但尚未接入具体接口，返回空结果: {}", title);
    return List.of();
  }

  @SuppressWarnings("unchecked")
  private Map<String, Object> dmdbConfig() {
    return (Map<String, Object>)
        systemConfigService.getSystemConfig().getOrDefault("dmdb", Map.of());
  }
}
