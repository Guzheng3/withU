package com.hienao.openlist2strm.title;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.hienao.openlist2strm.dto.debug.ApiDebugResult;
import com.hienao.openlist2strm.service.SystemConfigService;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ThreadLocalRandom;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.HttpEntity;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpMethod;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Component;
import org.springframework.web.client.HttpStatusCodeException;
import org.springframework.web.client.RestTemplate;

/**
 * 豆瓣元数据数据源适配器。
 *
 * <p>按配置顺序（TMDB → 豆瓣）中的豆瓣来源查询中文片名、上映年份等 TMDB 缺失的信息。配置项：
 * scraping.metadataSources 含 "douban" 时启用；scraping.doubanCookie 提供登录态（可选）。
 *
 * <p>搜索接口为 /search/weixin，命中后进入详情页补齐年份、简介、海报、演员等信息。
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class DoubanMetadataProvider implements MetadataProvider {

  private static final String DOUBAN_BASE_URL = "https://frodo.douban.com/api/v2";
  private static final String DOUBAN_API_KEY = "0dad551ec0f84ed02907ff5c42e8ec70";
  private static final String DOUBAN_SECRET = "bf7dddc7c9cfe6f7";
  private static final long DOUBAN_REQUEST_INTERVAL_MS = 2_000L;

  /** Frodo 客户端请求头（豆瓣官方 Android 客户端 UA）。 */
  private static final List<String> DOUBAN_USER_AGENTS =
      List.of(
          "api-client/1 com.douban.frodo/7.22.0.beta9(231) Android/23 product/Mate 40 "
              + "vendor/HUAWEI model/Mate 40 brand/HUAWEI rom/android network/wifi "
              + "platform/AndroidPad",
          "api-client/1 com.douban.frodo/7.18.0(230) Android/22 product/MI 9"
              + " vendor/Xiaomi model/MI 9 brand/Android rom/miui6 network/wifi"
              + " platform/mobile nd/1",
          "api-client/1 com.douban.frodo/7.1.0(205) Android/29 product/perseus"
              + " vendor/Xiaomi model/Mi MIX 3 rom/miui6 network/wifi platform/mobile"
              + " nd/1",
          "api-client/1 com.douban.frodo/7.3.0(207) Android/22 product/MI 9 vendor/Xiaomi"
              + " model/MI 9 brand/Android rom/miui6 network/wifi platform/mobile nd/1");

  private final SystemConfigService systemConfigService;
  private final ObjectMapper objectMapper;
  private final RestTemplate restTemplate;
  private final Object doubanRequestLock = new Object();
  private long lastDoubanRequestStartedAt;

  @Override
  public String name() {
    return "douban";
  }

  @Override
  public boolean isEnabled() {
    try {
      Map<String, Object> scraping = systemConfigService.getScrapingConfig();
      List<String> sources = metadataSources(scraping);
      return sources == null || sources.isEmpty() || sources.contains("douban");
    } catch (Exception e) {
      return false;
    }
  }

  /** 调试：直接调用豆瓣接口并返回原始请求 / 响应信息。 */
  public ApiDebugResult debug(String action, String query, String mediaType, String id) {
    String op = action == null ? "" : action;
    String path;
    Map<String, String> params = new LinkedHashMap<>();
    switch (op) {
      case "search" -> {
        path = "/search/weixin";
        params.put("q", query == null ? "" : query);
        params.put("start", "0");
        params.put("count", "20");
      }
      case "movie-detail", "tv-detail" -> {
        if (id == null || id.isBlank()) {
          return ApiDebugResult.builder()
              .source("douban")
              .action(action)
              .success(false)
              .error("详情查询需要提供豆瓣 ID")
              .build();
        }
        path = "/" + ("movie-detail".equals(op) ? "movie" : "tv") + "/" + id.trim();
      }
      case "movie-celebrities", "tv-celebrities" -> {
        if (id == null || id.isBlank()) {
          return ApiDebugResult.builder()
              .source("douban")
              .action(action)
              .success(false)
              .error("演员查询需要提供豆瓣 ID")
              .build();
        }
        path =
            "/"
                + ("movie-celebrities".equals(op) ? "movie" : "tv")
                + "/"
                + id.trim()
                + "/celebrities";
      }
      default -> {
        return ApiDebugResult.builder()
            .source("douban")
            .action(action)
            .success(false)
            .error("不支持的豆瓣调试操作: " + action + "（可选 search / movie-detail / tv-detail）")
            .build();
      }
    }

    String url = DOUBAN_BASE_URL + path;
    String finalUrl;
    URI requestUri = null;
    try {
      Map<String, String> all = new LinkedHashMap<>(params);
      String ts = LocalDate.now().format(DateTimeFormatter.BASIC_ISO_DATE);
      all.put("os_rom", "android");
      all.put("apiKey", DOUBAN_API_KEY);
      all.put("_ts", ts);
      all.put("_sig", sign(url, ts));
      requestUri = URI.create(url + "?" + encodeQuery(all));
      finalUrl = requestUri.toASCIIString();
    } catch (Exception e) {
      return ApiDebugResult.builder()
          .source("douban")
          .action(op)
          .success(false)
          .error("豆瓣签名失败: " + e.getMessage())
          .build();
    }

    long start = System.currentTimeMillis();
    String responseBody = null;
    Integer status = null;
    Object parsed = null;
    String error = null;
    try {
      ResponseEntity<String> response = exchangeDouban(requestUri);
      status = response.getStatusCode().value();
      responseBody = response.getBody();
      if (response.getStatusCode().is2xxSuccessful() && responseBody != null) {
        parsed = objectMapper.readTree(responseBody);
      } else {
        error = "豆瓣返回状态码 " + status;
      }
    } catch (Exception e) {
      error = doubanErrorMessage("douban", e);
      if (e instanceof HttpStatusCodeException hce) {
        status = hce.getStatusCode().value();
        responseBody = hce.getResponseBodyAsString();
      }
    }
    return ApiDebugResult.builder()
        .source("douban")
        .action(op)
        .method("GET")
        .url(finalUrl)
        .statusCode(status)
        .elapsedMs(System.currentTimeMillis() - start)
        .rawResponse(truncateDebugBody(responseBody))
        .parsed(parsed)
        .error(error)
        .success(status != null && status >= 200 && status < 300 && error == null)
        .build();
  }

  /** 将豆瓣登录态错误转换成可执行的提示，避免调试页只显示 Frodo 原始错误 JSON。 */
  private String doubanErrorMessage(String source, Exception exception) {
    if ("douban".equalsIgnoreCase(source) && isDoubanLoginRequired(exception)) {
      return "豆瓣接口要求登录，请在系统设置中填写有效的豆瓣 Cookie";
    }
    return exception.getMessage();
  }

  private boolean isDoubanLoginRequired(Exception exception) {
    if (!(exception instanceof HttpStatusCodeException responseException)
        || responseException.getStatusCode().value() != 403) {
      return false;
    }
    String body = responseException.getResponseBodyAsString();
    return body.contains("need_login")
        || body.matches("(?s).*\\\"code\\\"\\s*:\\s*103.*");
  }

  private String truncateDebugBody(String value) {
    if (value == null) return null;
    return value.length() > 200000 ? value.substring(0, 200000) + "... (truncated)" : value;
  }

  @Override
  public List<MetadataCandidate> search(String title, String year, String mediaType) {
    List<MetadataCandidate> results = new ArrayList<>();
    if (title == null || title.isBlank()) {
      return results;
    }
    try {
      String query = title + (year == null || year.isBlank() ? "" : " " + year);
      JsonNode search = doubanGet("/search/weixin", Map.of("q", query, "start", "0", "count", "20"));
      JsonNode target = findDoubanTarget(search, title, year, mediaType, true);
      if (target == null) {
        // 搜索项有时没有 year，先进入详情页，由详情中的发布日期做最终年份校验。
        target = findDoubanTarget(search, title, year, mediaType, false);
      }
      if (target == null) {
        String keywordQuery = keywordTitle(normalizeTitle(title));
        if (!keywordQuery.equals(normalizeTitle(title))) {
          JsonNode keywordSearch =
              doubanGet(
                  "/search/weixin",
                  Map.of(
                      "q", keywordQuery + (year == null || year.isBlank() ? "" : " " + year),
                      "start", "0",
                      "count", "20"));
          target = findDoubanTarget(keywordSearch, title, year, mediaType, true);
          if (target == null) {
            target = findDoubanTarget(keywordSearch, title, year, mediaType, false);
          }
        }
      }
      if (target == null || target.path("id").isMissingNode()) {
        return results;
      }
      String id = target.path("id").asText();
      String typePath = "tv".equals(mediaType) ? "tv" : "movie";
      JsonNode detail = doubanGet("/" + typePath + "/" + id, Map.of());
      results.add(toCandidate(detail, mediaType, id, title));
    } catch (Exception e) {
      log.warn("豆瓣搜索失败: title={}, year={}, type={}", title, year, mediaType, e);
    }
    return results;
  }

  /** 将豆瓣详情转换为统一元数据候选。 */
  private MetadataCandidate toCandidate(JsonNode d, String mediaType, String id, String query) {
    String releaseDate =
        firstDate(d.path("release_date").asText(null), d.path("pubdate").path(0).asText(null));
    String title = text(d, "title");
    String year = firstYear(d, releaseDate);
    String originalTitle = firstText(d, "original_title");
    String overview = firstText(d, "intro", "card_subtitle");
    Double voteAverage = d.path("rating").path("value").isNumber() ? d.path("rating").path("value").asDouble() : null;
    List<String> genres = stringArray(d.path("genres"));
    List<String> countries = stringArray(d.path("countries"));
    List<String> aliases = new ArrayList<>();
    if (originalTitle != null && !originalTitle.isBlank() && !originalTitle.equals(title)) {
      aliases.add(originalTitle);
    }

    String evidence = "豆瓣搜索";
    if (overview != null && !overview.isBlank()) {
      evidence += "（简介: " + truncate(overview, 60) + "）";
    }
    MetadataCandidate candidate =
        MetadataCandidate.builder()
            .source("douban")
            .id(id)
            .title(title)
            .originalTitle(originalTitle)
            .aliases(aliases)
            .year(year)
            .mediaType(mediaType)
            .url("https://movie.douban.com/subject/" + id + "/")
            .voteAverage(voteAverage)
            .evidence(evidence)
            .build();
    // 记录年份/地区证据供评分与排错使用
    if (year != null) {
      candidate.setEvidence(candidate.getEvidence() + "（年份: " + year + "）");
    }
    if (countries != null && !countries.isEmpty()) {
      candidate.setEvidence(candidate.getEvidence() + "（地区: " + String.join("/", countries) + "）");
    }
    return candidate;
  }

  private JsonNode findDoubanTarget(
      JsonNode search, String title, String year, String mediaType, boolean requireYear) {
    for (JsonNode item : search.path("items")) {
      JsonNode target = item.path("target");
      String type = normalizeMediaType(item.path("type_name").asText());
      if (!mediaType.equals(type) || !titleMatches(title, target.path("title").asText())) {
        continue;
      }
      if (!requireYear
          || year == null
          || year.isBlank()
          || year.equals(target.path("year").asText())) {
        return target;
      }
    }
    return null;
  }

  private JsonNode doubanGet(String path, Map<String, String> params) throws Exception {
    String url = DOUBAN_BASE_URL + path;
    Map<String, String> all = new LinkedHashMap<>(params);
    String ts = LocalDate.now().format(DateTimeFormatter.BASIC_ISO_DATE);
    all.put("os_rom", "android");
    all.put("apiKey", DOUBAN_API_KEY);
    all.put("_ts", ts);
    all.put("_sig", sign(url, ts));
    URI requestUri = URI.create(url + "?" + encodeQuery(all));
    ResponseEntity<String> response = exchangeDouban(requestUri);
    if (!response.getStatusCode().is2xxSuccessful() || response.getBody() == null) {
      throw new IllegalStateException("豆瓣返回状态 " + response.getStatusCode());
    }
    return objectMapper.readTree(response.getBody());
  }

  private String sign(String url, String ts) throws Exception {
    String path = URI.create(url).getPath();
    String raw =
        "GET&"
            + URLEncoder.encode(path, StandardCharsets.UTF_8).replace("+", "%20")
            + "&"
            + ts;
    Mac mac = Mac.getInstance("HmacSHA1");
    mac.init(new SecretKeySpec(DOUBAN_SECRET.getBytes(StandardCharsets.UTF_8), "HmacSHA1"));
    return java.util.Base64.getEncoder().encodeToString(mac.doFinal(raw.getBytes(StandardCharsets.UTF_8)));
  }

  /** 豆瓣对短时间并发请求敏感，所有搜索、详情请求共用一个串行节流器。 */
  private ResponseEntity<String> exchangeDouban(URI requestUri) throws InterruptedException {
    synchronized (doubanRequestLock) {
      long now = System.currentTimeMillis();
      long waitMs = DOUBAN_REQUEST_INTERVAL_MS - (now - lastDoubanRequestStartedAt);
      while (lastDoubanRequestStartedAt > 0 && waitMs > 0) {
        doubanRequestLock.wait(waitMs);
        now = System.currentTimeMillis();
        waitMs = DOUBAN_REQUEST_INTERVAL_MS - (now - lastDoubanRequestStartedAt);
      }
      lastDoubanRequestStartedAt = System.currentTimeMillis();
      return restTemplate.exchange(
          requestUri, HttpMethod.GET, new HttpEntity<>(doubanHeaders()), String.class);
    }
  }

  /** 严格百分号编码查询参数，保证 base64 签名中的 +、/、= 不被豆瓣当特殊字符解码。 */
  private String encodeQuery(Map<String, String> params) {
    StringBuilder query = new StringBuilder();
    params.forEach(
        (key, value) -> {
          if (query.length() > 0) {
            query.append('&');
          }
          query.append(encodeQueryParam(key)).append('=').append(encodeQueryParam(value));
        });
    return query.toString();
  }

  private String encodeQueryParam(String value) {
    return URLEncoder.encode(value, StandardCharsets.UTF_8).replace("+", "%20");
  }

  private HttpHeaders doubanHeaders() {
    Map<String, Object> config = systemConfigService.getScrapingConfig();
    String configuredUserAgent = stringValue(config.get("doubanUserAgent"));
    String userAgent =
        configuredUserAgent != null
                && configuredUserAgent.trim().startsWith("api-client/1 com.douban.frodo/")
            ? configuredUserAgent.trim()
            : randomDoubanUserAgent();
    HttpHeaders headers = new HttpHeaders();
    headers.set(HttpHeaders.USER_AGENT, userAgent);
    headers.set(HttpHeaders.CONTENT_TYPE, "application/x-www-form-urlencoded; charset=UTF-8");
    String cookie = stringValue(config.get("doubanCookie"));
    if (cookie != null && !cookie.isBlank()) {
      headers.set(HttpHeaders.COOKIE, cookie.trim());
      headers.set(HttpHeaders.REFERER, "https://movie.douban.com/");
    }
    return headers;
  }

  private String randomDoubanUserAgent() {
    return DOUBAN_USER_AGENTS.get(ThreadLocalRandom.current().nextInt(DOUBAN_USER_AGENTS.size()));
  }

  @SuppressWarnings("unchecked")
  private List<String> metadataSources(Map<String, Object> scraping) {
    Object raw = scraping.get("metadataSources");
    if (raw instanceof List<?> values) {
      return values.stream().map(String::valueOf).toList();
    }
    return List.of();
  }

  private boolean titleMatches(String query, String title) {
    if (title == null || query == null) {
      return false;
    }
    String normalizedQuery = normalizeTitle(query);
    String normalizedTitle = normalizeTitle(title);
    return !normalizedQuery.isBlank()
        && (normalizedTitle.equals(normalizedQuery)
            || keywordTitle(normalizedTitle).equals(keywordTitle(normalizedQuery))
            || normalizedTitle.contains(normalizedQuery)
            || normalizedQuery.contains(normalizedTitle)
            || fuzzyKeywordMatch(normalizedQuery, normalizedTitle));
  }

  private String normalizeTitle(String value) {
    return value == null
        ? ""
        : value
            .replaceAll("[·•‧・:：]", "之")
            .replaceAll("[\\s._\\-，。、“”‘’'！!？?（）()【】\\[\\]]+", "")
            .toLowerCase(Locale.ROOT);
  }

  private String keywordTitle(String normalizedTitle) {
    return normalizedTitle.replace("之", "");
  }

  /** 允许少量连接词/分隔差异，但要求较长片名的主要关键词保持顺序。 */
  private boolean fuzzyKeywordMatch(String normalizedQuery, String normalizedCandidate) {
    String queryKeywords = keywordTitle(normalizedQuery);
    String candidateKeywords = keywordTitle(normalizedCandidate);
    if (queryKeywords.length() < 4 || candidateKeywords.length() < 4) {
      return false;
    }
    return isSubsequence(queryKeywords, candidateKeywords)
        || isSubsequence(candidateKeywords, queryKeywords);
  }

  private boolean isSubsequence(String shorter, String longer) {
    int index = 0;
    for (int i = 0; i < longer.length() && index < shorter.length(); i++) {
      if (longer.charAt(i) == shorter.charAt(index)) {
        index++;
      }
    }
    return index == shorter.length();
  }

  private String normalizeMediaType(String type) {
    return "电影".equals(type) || "movie".equalsIgnoreCase(type)
        ? "movie"
        : ("电视剧".equals(type) || "tv".equalsIgnoreCase(type) ? "tv" : type);
  }

  private String firstText(JsonNode node, String... keys) {
    for (String key : keys) {
      String value = text(node, key);
      if (value != null && !value.isBlank()) {
        return value;
      }
    }
    return null;
  }

  private String text(JsonNode node, String key) {
    return node.path(key).isMissingNode() || node.path(key).isNull()
        ? null
        : node.path(key).asText(null);
  }

  private String firstYear(JsonNode node, String date) {
    String year = text(node, "year");
    return year != null && year.length() >= 4
        ? year.substring(0, 4)
        : (date != null && date.length() >= 4 ? date.substring(0, 4) : null);
  }

  private String firstDate(String... values) {
    for (String value : values) {
      if (value != null && value.matches(".*\\d{4}-\\d{2}-\\d{2}.*")) {
        return value.replaceAll(".*?(\\d{4}-\\d{2}-\\d{2}).*", "$1");
      }
    }
    return null;
  }

  private List<String> stringArray(JsonNode array) {
    List<String> result = new ArrayList<>();
    if (array.isArray()) {
      array.forEach(v -> result.add(v.isObject() ? v.path("name").asText() : v.asText()));
    }
    return result;
  }

  private String stringValue(Object value) {
    return value == null ? null : String.valueOf(value);
  }

  private String truncate(String value, int max) {
    if (value == null) {
      return null;
    }
    return value.length() > max ? value.substring(0, max) + "..." : value;
  }
}
