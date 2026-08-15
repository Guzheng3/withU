package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.external.ExternalApiDtos;
import com.hienao.openlist2strm.service.ExternalApiService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

/**
 * 外部媒体库接口（Emby 风格）。
 *
 * <p>面向第三方播放器/客户端，提供媒体库导航、详情与播放地址解析。鉴权通过
 * {@code X-API-Key} 请求头或 {@code ?apiKey=} 查询参数，由 {@code ExternalApiFilter} 完成。
 */
@Slf4j
@Tag(name = "外部媒体库接口", description = "Emby 风格的外部媒体库接口，供第三方播放器/客户端接入")
@RestController
@RequestMapping("/api/external")
@RequiredArgsConstructor
public class ExternalApiController {

  private final ExternalApiService externalApiService;

  @Operation(summary = "服务信息")
  @GetMapping("/info")
  public ResponseEntity<ExternalApiDtos.ServerInfo> info() {
    return ResponseEntity.ok(externalApiService.serverInfo());
  }

  @Operation(summary = "健康检查")
  @GetMapping("/health")
  public ResponseEntity<String> health() {
    return ResponseEntity.ok("ok");
  }

  @Operation(summary = "分页查询媒体列表")
  @GetMapping("/media")
  public ResponseEntity<ExternalApiDtos.ItemPage> listMedia(
      @RequestParam(required = false) String type,
      @RequestParam(required = false) String keyword,
      @RequestParam(defaultValue = "1") int page,
      @RequestParam(defaultValue = "24") int pageSize) {
    return ResponseEntity.ok(externalApiService.listMedia(type, keyword, page, pageSize));
  }

  @Operation(summary = "获取媒体详情")
  @GetMapping("/media/{id}")
  public ResponseEntity<ExternalApiDtos.Detail> getDetail(@PathVariable Long id) {
    return ResponseEntity.ok(externalApiService.getDetail(id));
  }

  @Operation(summary = "媒体类型计数")
  @GetMapping("/counts")
  public ResponseEntity<ExternalApiDtos.Counts> counts() {
    return ResponseEntity.ok(externalApiService.counts());
  }

  @Operation(summary = "解析媒体播放地址（302 重定向）")
  @GetMapping("/stream/{id}")
  public ResponseEntity<Void> stream(@PathVariable Long id) {
    return redirect(externalApiService.resolveStreamUrl(id), id);
  }

  @Operation(summary = "解析剧集播放地址（302 重定向）")
  @GetMapping("/episode/{episodeId}/stream")
  public ResponseEntity<Void> episodeStream(@PathVariable Long episodeId) {
    return redirect(externalApiService.resolveEpisodeStreamUrl(episodeId), episodeId);
  }

  private ResponseEntity<Void> redirect(String rawUrl, Long id) {
    log.debug("外部接口播放重定向: id={}, url={}", id, rawUrl);
    return ResponseEntity.status(HttpStatus.FOUND).header(HttpHeaders.LOCATION, rawUrl).build();
  }
}
