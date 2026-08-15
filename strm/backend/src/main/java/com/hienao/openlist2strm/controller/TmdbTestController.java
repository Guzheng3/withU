package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.ApiResponse;
import com.hienao.openlist2strm.dto.tmdb.TmdbCredits;
import com.hienao.openlist2strm.dto.tmdb.TmdbMovieDetail;
import com.hienao.openlist2strm.dto.tmdb.TmdbSearchResponse;
import com.hienao.openlist2strm.dto.tmdb.TmdbSearchResponse.TmdbSearchResult;
import com.hienao.openlist2strm.dto.tmdb.TmdbTvDetail;
import com.hienao.openlist2strm.service.TmdbApiService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.util.HashMap;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

/**
 * TMDB API 测试控制器
 *
 * <p>提供 TMDB 搜索与详情查询接口，用于前端 API 测试页面调试。
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@RestController
@RequestMapping("/api/test/tmdb")
@RequiredArgsConstructor
@Tag(name = "TMDB测试", description = "TMDB API 测试接口")
public class TmdbTestController {

  private final TmdbApiService tmdbApiService;

  /**
   * 搜索剧集/电影
   *
   * @param query 关键词
   * @param year 年份（可选）
   * @param type 类型：movie 或 tv（可选，默认 movie）
   * @return 搜索结果（含海报URL）
   */
  @GetMapping("/search")
  @Operation(summary = "TMDB 搜索", description = "按剧名搜索电影或电视剧，返回搜索结果")
  public ResponseEntity<ApiResponse<Object>> search(
      @RequestParam("query") String query,
      @RequestParam(value = "year", required = false) String year,
      @RequestParam(value = "type", defaultValue = "movie") String type) {

    String trimmedQuery = query.trim();
    if (trimmedQuery.isEmpty()) {
      return ResponseEntity.badRequest().body(ApiResponse.error(400, "搜索关键词不能为空"));
    }

    TmdbSearchResponse response;
    if ("tv".equalsIgnoreCase(type)) {
      response = tmdbApiService.searchTvShows(trimmedQuery, year);
    } else {
      response = tmdbApiService.searchMovies(trimmedQuery, year);
    }

    List<Map<String, Object>> results =
        response.getResults().stream()
            .map(
                r -> {
                  Map<String, Object> m = new LinkedHashMap<>();
                  m.put("id", r.getId());
                  m.put("title", r.getDisplayTitle());
                  m.put("originalTitle", r.getOriginalTitle() != null ? r.getOriginalTitle() : r.getOriginalName());
                  m.put("mediaType", r.isTvShow() ? "tv" : "movie");
                  m.put("year", r.getReleaseYear());
                  m.put("releaseDate", r.getReleaseDate() != null ? r.getReleaseDate() : r.getFirstAirDate());
                  m.put("overview", r.getOverview());
                  m.put("voteAverage", r.getVoteAverage());
                  m.put("voteCount", r.getVoteCount());
                  m.put("posterUrl", tmdbApiService.buildPosterUrl(r.getPosterPath()));
                  m.put("backdropUrl", tmdbApiService.buildBackdropUrl(r.getBackdropPath()));
                  m.put("originalJson", r);
                  return m;
                })
            .collect(Collectors.toList());

    Map<String, Object> data = new HashMap<>();
    data.put("type", type.toLowerCase());
    data.put("query", trimmedQuery);
    data.put("totalResults", response.getTotalResults());
    data.put("results", results);
    data.put("raw", response);

    return ResponseEntity.ok(ApiResponse.success(data));
  }

  /**
   * 查询剧集/电影详情（含演员表）
   *
   * @param type 类型：movie 或 tv
   * @param id TMDB ID
   * @return 详情（含海报、演员头像URL）
   */
  @GetMapping("/detail")
  @Operation(summary = "TMDB 详情", description = "按 TMDB ID 查询电影或电视剧详情及演员表")
  public ResponseEntity<ApiResponse<Object>> detail(
      @RequestParam("type") String type, @RequestParam("id") Integer id) {

    Map<String, Object> data = new LinkedHashMap<>();
    data.put("type", type.toLowerCase());
    data.put("id", id);

    if ("tv".equalsIgnoreCase(type)) {
      TmdbTvDetail detail = tmdbApiService.getTvDetail(id);
      data.put("title", detail.getName());
      data.put("originalTitle", detail.getOriginalName());
      data.put("year", detail.getFirstAirYear());
      data.put("firstAirDate", detail.getFirstAirDate());
      data.put("overview", detail.getOverview());
      data.put("voteAverage", detail.getVoteAverage());
      data.put("voteCount", detail.getVoteCount());
      data.put("genres", detail.getGenreString());
      data.put("status", detail.getStatus());
      data.put("numberOfSeasons", detail.getNumberOfSeasons());
      data.put("numberOfEpisodes", detail.getNumberOfEpisodes());
      data.put("posterUrl", tmdbApiService.buildPosterUrl(detail.getPosterPath()));
      data.put("backdropUrl", tmdbApiService.buildBackdropUrl(detail.getBackdropPath()));
      data.put("raw", detail);
    } else {
      TmdbMovieDetail detail = tmdbApiService.getMovieDetail(id);
      data.put("title", detail.getTitle());
      data.put("originalTitle", detail.getOriginalTitle());
      data.put("year", detail.getReleaseYear());
      data.put("releaseDate", detail.getReleaseDate());
      data.put("runtime", detail.getRuntime());
      data.put("overview", detail.getOverview());
      data.put("voteAverage", detail.getVoteAverage());
      data.put("voteCount", detail.getVoteCount());
      data.put("genres", detail.getGenreString());
      data.put("status", detail.getStatus());
      data.put("tagline", detail.getTagline());
      data.put("posterUrl", tmdbApiService.buildPosterUrl(detail.getPosterPath()));
      data.put("backdropUrl", tmdbApiService.buildBackdropUrl(detail.getBackdropPath()));
      data.put("raw", detail);
    }

    // 演员表
    TmdbCredits credits;
    try {
      credits = tmdbApiService.getCredits(type.toLowerCase(), id);
    } catch (Exception e) {
      log.warn("获取演员表失败（可选信息）: {}", e.getMessage());
      credits = new TmdbCredits();
    }

    List<Map<String, Object>> cast =
        credits.getCast() == null
            ? List.of()
            : credits.getCast().stream()
                .map(
                    c -> {
                      Map<String, Object> m = new LinkedHashMap<>();
                      m.put("id", c.getId());
                      m.put("name", c.getName());
                      m.put("originalName", c.getOriginalName());
                      m.put("character", c.getCharacter());
                      m.put("order", c.getOrder());
                      m.put("profileUrl", tmdbApiService.buildImageUrl(c.getProfilePath(), "w185"));
                      m.put("profilePath", c.getProfilePath());
                      return m;
                    })
                .collect(Collectors.toList());

    data.put("cast", cast);
    data.put("creditsRaw", credits);

    return ResponseEntity.ok(ApiResponse.success(data));
  }
}
