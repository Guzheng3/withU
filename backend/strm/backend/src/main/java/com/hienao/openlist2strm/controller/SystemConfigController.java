package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.ApiResponse;
import com.hienao.openlist2strm.service.AiFileNameRecognitionService;
import com.hienao.openlist2strm.service.NotificationService;
import com.hienao.openlist2strm.service.SystemConfigService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

/**
 * 系统配置管理控制器
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@RestController
@RequestMapping("/api/system")
@RequiredArgsConstructor
@Tag(name = "系统配置管理", description = "系统配置的读取和保存接口")
public class SystemConfigController {

  private final SystemConfigService systemConfigService;
  private final AiFileNameRecognitionService aiFileNameRecognitionService;
  private final NotificationService notificationService;
  private final com.hienao.openlist2strm.service.MihomoService mihomoService;

  /** 获取系统配置 */
  @GetMapping("/config")
  @Operation(summary = "获取系统配置", description = "获取当前系统配置信息")
  public ResponseEntity<ApiResponse<Map<String, Object>>> getSystemConfig() {
    try {
      Map<String, Object> config = systemConfigService.getPublicSystemConfig();
      return ResponseEntity.ok(ApiResponse.success(config));
    } catch (Exception e) {
      log.error("获取系统配置失败", e);
      return ResponseEntity.ok(ApiResponse.error("获取系统配置失败: " + e.getMessage()));
    }
  }

  /** 保存系统配置 */
  @PostMapping("/config")
  @Operation(summary = "保存系统配置", description = "保存系统配置信息")
  public ResponseEntity<ApiResponse<String>> saveSystemConfig(
      @RequestBody Map<String, Object> config) {
    try {
      // 验证媒体文件后缀配置
      if (config.containsKey("mediaExtensions")) {
        Object mediaExtensions = config.get("mediaExtensions");
        if (!(mediaExtensions instanceof List)) {
          return ResponseEntity.ok(ApiResponse.error("mediaExtensions必须是数组类型"));
        }

        @SuppressWarnings("unchecked")
        List<String> extensions = (List<String>) mediaExtensions;

        // 验证后缀格式
        for (String ext : extensions) {
          if (!ext.startsWith(".")) {
            return ResponseEntity.ok(ApiResponse.error("文件后缀必须以.开头: " + ext));
          }
        }
      }

      // 验证TMDB配置
      if (config.containsKey("tmdb")) {
        Object tmdbConfig = config.get("tmdb");
        if (!(tmdbConfig instanceof Map)) {
          return ResponseEntity.ok(ApiResponse.error("tmdb配置必须是对象类型"));
        }

        @SuppressWarnings("unchecked")
        Map<String, Object> tmdb = (Map<String, Object>) tmdbConfig;

        // 验证API Key
        if (tmdb.containsKey("apiKey")) {
          String apiKey = (String) tmdb.get("apiKey");
          if (apiKey != null
              && !apiKey.trim().isEmpty()
              && !systemConfigService.validateTmdbApiKey(apiKey)) {
            return ResponseEntity.ok(ApiResponse.error("TMDB API Key格式不正确"));
          }
        }
      }

      systemConfigService.saveSystemConfig(config);
      // mihomo 订阅地址或轮询间隔变更时异步应用并启动代理
      Object mihomoObj = config.get("mihomo");
      if (mihomoObj instanceof Map<?, ?> rawMihomo) {
        Object subUrl = rawMihomo.get("subUrl");
        Object pollInterval = rawMihomo.get("pollInterval");
        int pollSec = 1800;
        if (pollInterval != null) {
          try { pollSec = Math.max(60, Integer.parseInt(String.valueOf(pollInterval))); }
          catch (NumberFormatException ignored) { pollSec = 1800; }
        }
        mihomoService.applyConfigAsync(
            subUrl == null ? "" : String.valueOf(subUrl), pollSec);
      }
      return ResponseEntity.ok(ApiResponse.success("配置保存成功"));
    } catch (Exception e) {
      log.error("保存系统配置失败", e);
      return ResponseEntity.ok(ApiResponse.error("保存系统配置失败: " + e.getMessage()));
    }
  }

  /** 获取内置 mihomo 代理运行状态 */
  @GetMapping("/mihomo/status")
  @Operation(summary = "获取 mihomo 代理状态", description = "获取内置 mihomo 代理的订阅与运行状态")
  public ResponseEntity<ApiResponse<Map<String, Object>>> getMihomoStatus() {
    try {
      Map<String, Object> status = mihomoService.getStatus();
      status.put("subUrl", systemConfigService.getMihomoConfig().getOrDefault("subUrl", ""));
      return ResponseEntity.ok(ApiResponse.success(status));
    } catch (Exception e) {
      log.error("获取 mihomo 状态失败", e);
      return ResponseEntity.ok(ApiResponse.error("获取 mihomo 状态失败: " + e.getMessage()));
    }
  }

  /** 使用当前表单中的 Apprise 配置发送测试通知。 */
  @PostMapping("/test-notification")
  @Operation(summary = "测试通知配置", description = "通过 Apprise 发送一条测试通知")
  public ResponseEntity<ApiResponse<String>> testNotification(
      @RequestBody Map<String, Object> config) {
    try {
      notificationService.testApprise(config);
      return ResponseEntity.ok(ApiResponse.success("测试通知发送成功"));
    } catch (Exception e) {
      log.error("测试通知发送失败: {}", e.getMessage());
      return ResponseEntity.ok(ApiResponse.error("测试通知发送失败: " + e.getMessage()));
    }
  }

  /** 测试 AI 配置 */
  @PostMapping("/test-ai-config")
  @Operation(summary = "测试 AI 配置", description = "测试 AI 识别配置是否有效")
  public ResponseEntity<ApiResponse<String>> testAiConfig(
      @RequestBody Map<String, Object> testConfig) {
    try {
      String baseUrl = (String) testConfig.get("baseUrl");
      String apiKey = (String) testConfig.get("apiKey");
      String model = (String) testConfig.get("model");

      if (baseUrl == null || baseUrl.trim().isEmpty()) {
        return ResponseEntity.ok(ApiResponse.error("API 基础 URL 不能为空"));
      }

      if (apiKey == null || apiKey.trim().isEmpty()) {
        return ResponseEntity.ok(ApiResponse.error("API Key 不能为空"));
      }

      if (model == null || model.trim().isEmpty()) {
        return ResponseEntity.ok(ApiResponse.error("模型名称不能为空"));
      }

      // 调用 AI 服务验证配置
      boolean isValid = aiFileNameRecognitionService.validateAiConfig(baseUrl, apiKey, model);

      if (isValid) {
        return ResponseEntity.ok(ApiResponse.success("AI 配置测试成功"));
      } else {
        return ResponseEntity.ok(ApiResponse.error("AI 配置测试失败，请检查配置信息"));
      }

    } catch (Exception e) {
      log.error("测试 AI 配置失败", e);
      return ResponseEntity.ok(ApiResponse.error("测试 AI 配置失败: " + e.getMessage()));
    }
  }
}
