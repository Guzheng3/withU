package com.hienao.openlist2strm.notification;

/** 单个媒体文件的 TMDB 刮削结果，用于准确区分未识别与技术失败。 */
public record ScrapeOutcome(
    Status status,
    String reason,
    String mediaTitle,
    Integer tmdbId,
    Integer releaseYear,
    String overview,
    String posterUrl,
    String backdropUrl,
    Double voteAverage) {

  public enum Status {
    MATCHED,
    LOW_CONFIDENCE,
    TITLE_UNAVAILABLE,
    TMDB_NOT_MATCHED,
    UNSUPPORTED_MEDIA_TYPE,
    FAILED
  }

  public static ScrapeOutcome matched(
      String title,
      Integer tmdbId,
      Integer releaseYear,
      String overview,
      String posterUrl,
      String backdropUrl,
      Double voteAverage) {
    return new ScrapeOutcome(
        Status.MATCHED, null, title, tmdbId, releaseYear, overview, posterUrl, backdropUrl, voteAverage);
  }

  public static ScrapeOutcome unmatched(Status status, String reason) {
    return new ScrapeOutcome(status, reason, null, null, null, null, null, null, null);
  }

  public boolean isUnrecognized() {
    return status == Status.LOW_CONFIDENCE
        || status == Status.TITLE_UNAVAILABLE
        || status == Status.TMDB_NOT_MATCHED
        || status == Status.UNSUPPORTED_MEDIA_TYPE;
  }
}
