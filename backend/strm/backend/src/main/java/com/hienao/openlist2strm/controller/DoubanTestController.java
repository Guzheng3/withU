package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.ApiResponse;
import com.hienao.openlist2strm.dto.debug.ApiDebugResult;
import com.hienao.openlist2strm.title.DoubanMetadataProvider;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.net.InetAddress;
import java.net.URI;
import java.net.UnknownHostException;
import java.util.List;
import java.util.Locale;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpEntity;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpMethod;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.client.RestTemplate;

/**
 * 豆瓣 API 测试控制器。
 *
 * <p>复用 {@link DoubanMetadataProvider} 的 frodo 签名与串行节流逻辑，提供豆瓣搜索与详情调试接口。
 */
@Slf4j
@RestController
@RequestMapping("/api/test/douban")
@RequiredArgsConstructor
@Tag(name = "豆瓣测试", description = "豆瓣 API 测试接口")
public class DoubanTestController {

  private final DoubanMetadataProvider doubanMetadataProvider;
  private final RestTemplate restTemplate;

  @GetMapping
  @Operation(summary = "调试豆瓣接口", description = "action: search / movie-detail / tv-detail / movie-celebrities / tv-celebrities")
  public ApiResponse<ApiDebugResult> douban(
      @RequestParam String action,
      @RequestParam(required = false) String query,
      @RequestParam(required = false) String mediaType,
      @RequestParam(required = false) String id) {
    return ApiResponse.success(doubanMetadataProvider.debug(action, query, mediaType, id));
  }

  @GetMapping("/image")
  @Operation(summary = "代理加载豆瓣图片", description = "解决豆瓣图床防盗链（需豆瓣 Referer）；仅允许 doubanio.com 域名")
  public ResponseEntity<byte[]> image(@RequestParam String url) {
    try {
      URI uri = URI.create(url);
      if (!"https".equalsIgnoreCase(uri.getScheme())) {
        return ResponseEntity.badRequest().build();
      }
      int port = uri.getPort();
      if (port != -1 && port != 443) {
        return ResponseEntity.badRequest().build();
      }
      String host = uri.getHost();
      if (host == null || !isDoubanioHost(host) || !hasPublicAddressOnly(host)) {
        return ResponseEntity.status(HttpStatus.FORBIDDEN).build();
      }
      HttpHeaders headers = new HttpHeaders();
      headers.set("Referer", "https://movie.douban.com/");
      headers.set("Accept", "image/*");
      ResponseEntity<byte[]> response =
          restTemplate.exchange(uri, HttpMethod.GET, new HttpEntity<>(headers), byte[].class);
      if (!response.getStatusCode().is2xxSuccessful() || response.getBody() == null) {
        return ResponseEntity.status(response.getStatusCode()).build();
      }
      MediaType contentType = response.getHeaders().getContentType();
      return ResponseEntity.ok()
          .contentType(contentType != null ? contentType : MediaType.APPLICATION_OCTET_STREAM)
          .body(response.getBody());
    } catch (IllegalArgumentException e) {
      return ResponseEntity.badRequest().build();
    } catch (Exception e) {
      return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR).build();
    }
  }

  private static boolean isDoubanioHost(String host) {
    String normalized = host.toLowerCase(Locale.ROOT);
    return normalized.equals("doubanio.com") || normalized.endsWith(".doubanio.com");
  }

  /** DNS 解析后拒绝内网/回环/保留地址，防止代理被用于访问内网服务（SSRF）。 */
  private static boolean hasPublicAddressOnly(String host) {
    try {
      for (InetAddress address : InetAddress.getAllByName(host)) {
        if (address.isAnyLocalAddress()
            || address.isLoopbackAddress()
            || address.isLinkLocalAddress()
            || address.isSiteLocalAddress()
            || address.isMulticastAddress()) {
          return false;
        }
      }
      return true;
    } catch (UnknownHostException e) {
      return false;
    }
  }
}
