package com.hienao.openlist2strm.config.security;

import com.hienao.openlist2strm.service.SystemConfigService;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import java.io.IOException;
import lombok.extern.slf4j.Slf4j;
import org.apache.commons.lang3.StringUtils;
import org.springframework.web.filter.OncePerRequestFilter;

/**
 * 外部媒体库接口鉴权过滤器。
 *
 * <p>校验 {@code /api/external/**} 请求携带的 API Key（支持 {@code X-API-Key} 请求头或
 * {@code ?apiKey=} 查询参数）。未启用接口或 Key 不匹配时返回 401。
 */
@Slf4j
public class ExternalApiFilter extends OncePerRequestFilter {

  private final SystemConfigService systemConfigService;

  public ExternalApiFilter(SystemConfigService systemConfigService) {
    this.systemConfigService = systemConfigService;
  }

  @Override
  protected boolean shouldNotFilter(HttpServletRequest request) {
    String path = request.getRequestURI();
    return !path.startsWith("/api/external/");
  }

  @Override
  protected void doFilterInternal(
      HttpServletRequest request, HttpServletResponse response, FilterChain filterChain)
      throws ServletException, IOException {
    if (!systemConfigService.isExternalApiEnabled()) {
      writeUnauthorized(response, "外部媒体库接口未启用");
      return;
    }

    String configuredKey = systemConfigService.getExternalApiKey();
    if (StringUtils.isBlank(configuredKey)) {
      writeUnauthorized(response, "外部媒体库接口未配置 API Key");
      return;
    }

    String providedKey = request.getHeader("X-API-Key");
    if (StringUtils.isBlank(providedKey)) {
      providedKey = request.getParameter("apiKey");
    }
    if (StringUtils.isBlank(providedKey) || !constantTimeEquals(configuredKey, providedKey)) {
      writeUnauthorized(response, "API Key 无效");
      return;
    }

    filterChain.doFilter(request, response);
  }

  private void writeUnauthorized(HttpServletResponse response, String message) throws IOException {
    response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
    response.setContentType("application/json;charset=UTF-8");
    response.getWriter().write("{\"code\":401,\"message\":\"" + message + "\",\"data\":null}");
  }

  private boolean constantTimeEquals(String expected, String actual) {
    if (expected.length() != actual.length()) {
      return false;
    }
    int result = 0;
    for (int i = 0; i < expected.length(); i++) {
      result |= expected.charAt(i) ^ actual.charAt(i);
    }
    return result == 0;
  }
}
