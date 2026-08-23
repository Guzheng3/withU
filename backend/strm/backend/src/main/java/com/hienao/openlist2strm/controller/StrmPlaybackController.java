package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.service.OpenlistApiService;
import com.hienao.openlist2strm.service.OpenlistConfigService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.nio.charset.StandardCharsets;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

/**
 * STRM 播放代理接口。
 *
 * <p>STRM 文件中写入应用的播放代理地址（形如 {@code /api/strm-play/{configId}/{完整路径}}），外部
 * 媒体库请求该地址时，本接口使用后端保存的 OpenList 凭据实时换取 raw_url，并以 302 重定向到
 * 对象存储直链，从而避免 STRM 因 OpenList /d/ 下载鉴权或短期签名过期而无法播放。
 */
@Slf4j
@Tag(name = "STRM 播放代理")
@RestController
@RequestMapping("/api/strm-play")
@RequiredArgsConstructor
public class StrmPlaybackController {

  private final OpenlistConfigService openlistConfigService;
  private final OpenlistApiService openlistApiService;

  @Operation(summary = "代理播放：实时换取 raw_url 并重定向")
  @GetMapping("/{configId}/**")
  public ResponseEntity<Void> proxyPlay(
      @PathVariable Long configId, jakarta.servlet.http.HttpServletRequest request) {
    String requestUri = request.getRequestURI();
    String filePath = extractFilePath(requestUri, configId);

    log.debug("STRM 代理播放请求: configId={}, path={}", configId, filePath);

    OpenlistConfig config = openlistConfigService.getById(configId);
    if (config == null) {
      log.warn("STRM 代理播放失败：OpenList 配置不存在, configId={}", configId);
      return ResponseEntity.status(HttpStatus.NOT_FOUND).build();
    }
    if (!Boolean.TRUE.equals(config.getIsActive())) {
      log.warn("STRM 代理播放失败：OpenList 配置已停用, configId={}", configId);
      return ResponseEntity.status(HttpStatus.FORBIDDEN).build();
    }

    try {
      String rawUrl = openlistApiService.resolveRawUrl(config, filePath);
      log.debug("STRM 代理播放获取到 raw_url: {}", rawUrl);
      return ResponseEntity.status(HttpStatus.FOUND)
          .header(HttpHeaders.LOCATION, rawUrl)
          .build();
    } catch (Exception e) {
      log.error("STRM 代理播放失败: configId={}, path={}, 错误: {}", configId, filePath, e.getMessage());
      return ResponseEntity.status(HttpStatus.BAD_GATEWAY).build();
    }
  }

  /** 从请求 URI 中提取 OpenList 完整文件路径（解码后的相对路径，含前导 /）。 */
  private String extractFilePath(String requestUri, Long configId) {
    String prefix = "/api/strm-play/" + configId;
    String remainder = requestUri;
    int idx = remainder.indexOf(prefix);
    if (idx >= 0) {
      remainder = remainder.substring(idx + prefix.length());
    } else {
      int firstSlash = remainder.indexOf('/', 1);
      remainder = firstSlash >= 0 ? remainder.substring(firstSlash) : "/";
    }
    try {
      remainder = java.net.URLDecoder.decode(remainder, StandardCharsets.UTF_8);
    } catch (Exception e) {
      log.warn("STRM 代理路径解码失败: {}, 错误: {}", remainder, e.getMessage());
    }
    if (!remainder.startsWith("/")) {
      remainder = "/" + remainder;
    }
    return remainder;
  }
}
