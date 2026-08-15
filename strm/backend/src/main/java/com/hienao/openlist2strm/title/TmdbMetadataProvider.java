package com.hienao.openlist2strm.title;

import com.hienao.openlist2strm.dto.tmdb.TmdbSearchResponse;
import com.hienao.openlist2strm.service.SystemConfigService;
import com.hienao.openlist2strm.service.TmdbApiService;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Component;

/** TMDB 数据源适配器。 */
@Slf4j
@Component
@RequiredArgsConstructor
public class TmdbMetadataProvider implements MetadataProvider {

  private final TmdbApiService tmdbApiService;
  private final SystemConfigService systemConfigService;

  @Override
  public String name() {
    return "tmdb";
  }

  @Override
  public boolean isEnabled() {
    try {
      Map<String, Object> tmdb = systemConfigService.getTmdbConfig();
      String apiKey = (String) tmdb.getOrDefault("apiKey", "");
      return apiKey != null && !apiKey.trim().isEmpty();
    } catch (Exception e) {
      return false;
    }
  }

  @Override
  public List<MetadataCandidate> search(String title, String year, String mediaType) {
    List<MetadataCandidate> results = new ArrayList<>();
    if (title == null || title.isBlank()) {
      return results;
    }
    try {
      String normalizedYear = year != null && !year.isBlank() ? year.substring(0, 4) : null;
      boolean movie = mediaType == null || "movie".equalsIgnoreCase(mediaType);
      boolean tv = mediaType == null || "tv".equalsIgnoreCase(mediaType);

      if (movie) {
        TmdbSearchResponse response = tmdbApiService.searchMovies(title.trim(), normalizedYear);
        if (response != null && response.getResults() != null) {
          for (TmdbSearchResponse.TmdbSearchResult r : response.getResults()) {
            results.add(
                MetadataCandidate.builder()
                    .source("tmdb")
                    .id(String.valueOf(r.getId()))
                    .title(r.getDisplayTitle())
                    .originalTitle(
                        r.getOriginalTitle() != null ? r.getOriginalTitle() : r.getOriginalName())
                    .year(r.getReleaseYear())
                    .mediaType("movie")
                    .voteAverage(r.getVoteAverage())
                    .url("https://www.themoviedb.org/movie/" + r.getId())
                    .evidence("TMDB 电影搜索")
                    .build());
          }
        }
      }
      if (tv) {
        TmdbSearchResponse response = tmdbApiService.searchTvShows(title.trim(), normalizedYear);
        if (response != null && response.getResults() != null) {
          for (TmdbSearchResponse.TmdbSearchResult r : response.getResults()) {
            results.add(
                MetadataCandidate.builder()
                    .source("tmdb")
                    .id(String.valueOf(r.getId()))
                    .title(r.getDisplayTitle())
                    .originalTitle(
                        r.getOriginalName() != null ? r.getOriginalName() : r.getOriginalTitle())
                    .year(r.getReleaseYear())
                    .mediaType("tv")
                    .voteAverage(r.getVoteAverage())
                    .url("https://www.themoviedb.org/tv/" + r.getId())
                    .evidence("TMDB 电视剧搜索")
                    .build());
          }
        }
      }
    } catch (Exception e) {
      log.warn("TMDB 搜索失败: title={}, year={}, type={}", title, year, mediaType, e);
    }
    return results;
  }
}
