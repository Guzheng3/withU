package com.hienao.openlist2strm.service;

import com.fasterxml.jackson.databind.ObjectMapper;
import java.io.File;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;

/**
 * 内置 mihomo 代理管理服务。
 *
 * <p>负责：写入订阅配置、调用部署脚本生成 mihomo 配置、后台启动 mihomo。
 * 与 withU 仓库的 deploy-local/setup-mihomo.cjs 配合使用，规则仅对 TMDB 域名走代理。
 */
@Slf4j
@Service
public class MihomoService {

  private static final String MIHOMO_JSON = "mihomo.json";

  private final ObjectMapper objectMapper;
  private final ExecutorService executor =
      Executors.newSingleThreadExecutor(
          r -> {
            Thread t = new Thread(r, "mihomo-manager");
            t.setDaemon(true);
            return t;
          });

  public MihomoService(ObjectMapper objectMapper) {
    this.objectMapper = objectMapper;
  }

  /** withU 仓库根目录（部署脚本所在），来自 start-backend.js 注入的 WITHU_REPO。 */
  private String repoDir() {
    String v = System.getenv("WITHU_REPO");
    return v != null && !v.isBlank() ? v : "/workspace/withU";
  }

  /** 工作根目录（runtime 所在），来自 start-backend.js 注入的 WORKROOT。 */
  private String workRoot() {
    String v = System.getenv("WORKROOT");
    return v != null && !v.isBlank() ? v : "/workspace";
  }

  private Path mihomoRuntimeDir() {
    return Paths.get(workRoot(), "runtime", "mihomo");
  }

  private Path mihomoConfigFile() {
    return Paths.get(repoDir(), "config", MIHOMO_JSON);
  }

  /** 异步应用订阅配置并启动 mihomo。 */
  public void applyConfigAsync(String subUrl, int pollInterval) {
    String url = subUrl == null ? "" : subUrl.trim();
    executor.submit(
        () -> {
          try {
            applyConfigSync(url, pollInterval);
          } catch (Exception e) {
            log.error("配置并启动 mihomo 失败: {}", e.getMessage());
          }
        });
  }

  /** 同步应用订阅配置并启动 mihomo。 */
  public synchronized void applyConfigSync(String subUrl, int pollInterval) throws Exception {
    String url = subUrl == null ? "" : subUrl.trim();
    if (pollInterval <= 0) pollInterval = 1800;

    Path configFile = mihomoConfigFile();
    if (url.isEmpty()) {
      if (Files.exists(configFile)) {
        Files.delete(configFile);
        log.info("mihomo 订阅地址已清空，移除配置: {}", configFile);
      }
      log.info("mihomo 订阅地址为空，跳过启动");
      return;
    }

    // 1. 写入订阅配置（setup-mihomo.cjs 从此文件读取）
    Files.createDirectories(configFile.getParent());
    Map<String, Object> cfg = new HashMap<>();
    cfg.put("subUrl", url);
    cfg.put("pollInterval", pollInterval);
    Files.writeString(configFile, objectMapper.writerWithDefaultPrettyPrinter().writeValueAsString(cfg));

    // 2. 调用部署脚本生成配置
    ProcessBuilder setup = new ProcessBuilder("node", Paths.get(repoDir(), "deploy-local", "setup-mihomo.cjs").toString());
    setup.directory(new File(repoDir()));
    log.info("执行 setup-mihomo.cjs 生成 mihomo 配置...");
    String setupOutput = runAndCapture(setup);
    log.info("setup-mihomo.cjs 输出: {}", setupOutput);

    // 3. 读取状态，若启用则后台启动
    Map<String, Object> status = readStatus();
    boolean enabled = Boolean.TRUE.equals(status.get("enabled"));
    log.info("mihomo 状态: enabled={}, port={}, subUrl={}", enabled, status.get("port"), url);
    if (enabled) {
      startMihomoIfNeeded(status);
    }
  }

  private void startMihomoIfNeeded(Map<String, Object> status) {
    Object portObj = status.get("port");
    int port = portObj == null ? 7897 : Integer.parseInt(String.valueOf(portObj));
    if (isListening(port)) {
      log.info("mihomo 已在监听 127.0.0.1:{}，跳过启动", port);
      return;
    }
    Path startJs = mihomoRuntimeDir().resolve("start.cjs");
    if (!Files.exists(startJs)) {
      log.warn("mihomo 启动器不存在: {}", startJs);
      return;
    }
    // 后台启动，脱离当前进程生命周期
    Path logFile = mihomoRuntimeDir().resolve("mihomo.log");
    String cmd =
        "nohup setsid node '" + startJs + "' >> '" + logFile + "' 2>&1 &";
    ProcessBuilder start = new ProcessBuilder("sh", "-c", cmd);
    try {
      start.start();
      log.info("mihomo 启动命令已触发: {}", startJs);
    } catch (IOException e) {
      log.error("启动 mihomo 失败: {}", e.getMessage());
    }
  }

  /** 读取 mihomo 运行状态。 */
  public Map<String, Object> getStatus() {
    Map<String, Object> status = readStatus();
    int port = status.get("port") == null ? 7897 : Integer.parseInt(String.valueOf(status.get("port")));
    status.put("listening", isListening(port));
    return status;
  }

  private Map<String, Object> readStatus() {
    Path statusFile = mihomoRuntimeDir().resolve("status.json");
    try {
      if (Files.exists(statusFile)) {
        @SuppressWarnings("unchecked")
        Map<String, Object> status = objectMapper.readValue(statusFile.toFile(), Map.class);
        return status;
      }
    } catch (Exception e) {
      log.warn("读取 mihomo status.json 失败: {}", e.getMessage());
    }
    Map<String, Object> empty = new HashMap<>();
    empty.put("enabled", false);
    empty.put("port", 7897);
    empty.put("reason", "no-status-file");
    return empty;
  }

  private boolean isListening(int port) {
    try (java.net.Socket socket = new java.net.Socket()) {
      socket.connect(new java.net.InetSocketAddress("127.0.0.1", port), 500);
      return true;
    } catch (IOException e) {
      return false;
    }
  }

  private String runAndCapture(ProcessBuilder pb) throws IOException, InterruptedException {
    Process p = pb.start();
    String out = new String(p.getInputStream().readAllBytes(), StandardCharsets.UTF_8);
    String err = new String(p.getErrorStream().readAllBytes(), StandardCharsets.UTF_8);
    int code = p.waitFor();
    return "exit=" + code + "\n" + out + (err == null || err.isBlank() ? "" : "\n[stderr]\n" + err);
  }
}
