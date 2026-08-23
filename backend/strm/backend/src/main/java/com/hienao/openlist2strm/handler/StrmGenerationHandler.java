package com.hienao.openlist2strm.handler;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.handler.context.FileProcessingContext;
import com.hienao.openlist2strm.service.OpenlistApiService;
import com.hienao.openlist2strm.service.StrmFileService;
import java.util.Set;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

/**
 * STRM 文件生成处理器
 *
 * <p>负责为视频文件生成 STRM 文件。
 *
 * <p>Order: 30
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
@Order(30)
@RequiredArgsConstructor
public class StrmGenerationHandler implements FileProcessorHandler {

  private final StrmFileService strmFileService;

  // ==================== 接口实现 ====================

  @Override
  public ProcessingResult process(FileProcessingContext context) {
    OpenlistApiService.OpenlistFile currentFile = context.getCurrentFile();
    if (currentFile == null) {
      log.debug("没有当前文件，跳过 STRM 生成");
      return ProcessingResult.SUCCESS;
    }

    try {
      String fileName = currentFile.getName();
      String relativePath = context.getRelativePath();
      String strmPath = context.getTaskConfig().getStrmPath();
      String renameRegex = context.getTaskConfig().getRenameRegex();
      OpenlistConfig openlistConfig = context.getOpenlistConfig();
      boolean isIncrement =
          Boolean.TRUE.equals(
              context.getAttribute(
                  "executionIncremental",
                  Boolean.TRUE.equals(context.getTaskConfig().getIsIncrement())));

      // 构建文件 URL：优先使用应用播放代理（strmBaseUrl 配置为应用对外地址），否则使用 OpenList /d/ 地址加 sign
      String fileUrl = buildFileUrl(openlistConfig, currentFile);

      // 生成 STRM 文件
      strmFileService.generateStrmFile(
          strmPath,
          relativePath,
          fileName,
          fileUrl,
          isIncrement,
          renameRegex,
          openlistConfig);

      context.getStats().incrementProcessed();
      return ProcessingResult.SUCCESS;

    } catch (Exception e) {
      log.error("生成 STRM 文件失败: {}, 错误: {}", currentFile.getName(), e.getMessage(), e);
      context.getStats().incrementFailed();
      return ProcessingResult.FAILED;
    }
  }

  @Override
  public Set<FileType> getHandledTypes() {
    return Set.of(FileType.VIDEO);
  }

  // ==================== URL 处理 ====================

  /**
   * 构建 STRM 文件内容中的文件 URL。
   *
   * <p>当 OpenList 配置了 strmBaseUrl（应用对外可访问地址）时，写入应用播放代理地址，由后端在
   * 媒体库请求时实时换取 raw_url，避免 OpenList /d/ 下载路径因鉴权或签名过期无法访问；否则回退
   * 到 OpenList /d/ 地址加 sign。
   */
  private String buildFileUrl(OpenlistConfig openlistConfig, OpenlistApiService.OpenlistFile file) {
    String strmBaseUrl = openlistConfig != null ? openlistConfig.getStrmBaseUrl() : null;
    if (strmBaseUrl != null && !strmBaseUrl.trim().isEmpty() && file.getPath() != null) {
      String base = strmBaseUrl.trim();
      if (!base.endsWith("/")) {
        base += "/";
      }
      Long configId = openlistConfig.getId();
      String filePath = file.getPath();
      if (!filePath.startsWith("/")) {
        filePath = "/" + filePath;
      }
      String proxyUrl = base + "api/strm-play/" + configId + filePath;
      log.debug("STRM使用应用播放代理地址: {}", proxyUrl);
      return proxyUrl;
    }
    return buildFileUrlWithSign(file.getUrl(), file.getSign());
  }

  /** 构建包含 sign 参数的文件 URL */
  private String buildFileUrlWithSign(String originalUrl, String sign) {
    if (originalUrl == null) {
      return null;
    }

    String processedUrl = originalUrl;

    // 添加 sign 参数
    if (sign != null && !sign.trim().isEmpty()) {
      String separator = processedUrl.contains("?") ? "&" : "?";
      processedUrl = processedUrl + separator + "sign=" + sign;
    }

    return processedUrl;
  }
}
