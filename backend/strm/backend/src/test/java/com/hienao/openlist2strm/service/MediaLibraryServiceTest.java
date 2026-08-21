package com.hienao.openlist2strm.service;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.isNull;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.when;

import com.hienao.openlist2strm.dto.media.MediaLibraryDtos;
import com.hienao.openlist2strm.entity.MediaLibraryItem;
import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.exception.BusinessException;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.notification.ScrapeOutcome;
import java.util.List;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.mockito.ArgumentCaptor;

class MediaLibraryServiceTest {

  private MediaLibraryItemMapper mapper;
  private OpenlistConfigService openlistConfigService;
  private OpenlistApiService openlistApiService;
  private TaskConfigService taskConfigService;
  private TmdbApiService tmdbApiService;
  private MediaLibraryService service;

  @BeforeEach
  void setUp() {
    mapper = mock(MediaLibraryItemMapper.class);
    openlistConfigService = mock(OpenlistConfigService.class);
    openlistApiService = mock(OpenlistApiService.class);
    taskConfigService = mock(TaskConfigService.class);
    tmdbApiService = mock(TmdbApiService.class);
    service =
        new MediaLibraryService(
            mapper, openlistConfigService, openlistApiService, taskConfigService, tmdbApiService);
  }

  @Test
  void recordsGeneratedFileWithScrapeOutcomeMetadata() {
    TaskConfig task = new TaskConfig().setId(1L).setLibraryType("movie");
    OpenlistConfig config = new OpenlistConfig().setId(2L);
    ScrapeOutcome outcome =
        ScrapeOutcome.matched(
            "盗梦空间",
            27205,
            2010,
            "A thief who steals corporate secrets through the use of dream-sharing technology.",
            "https://image.tmdb.org/t/p/w500/poster.jpg",
            "https://image.tmdb.org/t/p/w1280/backdrop.jpg",
            8.4);

    service.recordGeneratedFile(
        task,
        config,
        "/data/Inception.2010.1080p.mkv",
        "/strm/Inception.2010.mkv.strm",
        "Inception.2010.1080p.mkv",
        "movie",
        "盗梦空间",
        outcome);

    ArgumentCaptor<MediaLibraryItem> captor = ArgumentCaptor.forClass(MediaLibraryItem.class);
    verify(mapper).upsert(captor.capture());
    MediaLibraryItem item = captor.getValue();
    assertEquals(1L, item.getTaskId());
    assertEquals(2L, item.getOpenlistConfigId());
    assertEquals("movie", item.getMediaType());
    assertEquals("盗梦空间", item.getTitle());
    assertEquals(27205, item.getTmdbId());
    assertEquals("MATCHED", item.getScrapeStatus());
    assertEquals("2010", item.getReleaseYear());
    assertEquals("A thief who steals corporate secrets through the use of dream-sharing technology.", item.getOverview());
    assertEquals("https://image.tmdb.org/t/p/w500/poster.jpg", item.getPosterUrl());
    assertEquals("https://image.tmdb.org/t/p/w1280/backdrop.jpg", item.getBackdropUrl());
    assertEquals(8.4, item.getVoteAverage(), 0.001);
  }

  @Test
  void fallsBackToFileNameWhenTitleAndMediaTypeMissing() {
    TaskConfig task = new TaskConfig().setId(1L).setLibraryType("auto");
    OpenlistConfig config = new OpenlistConfig().setId(2L);

    service.recordGeneratedFile(
        task, config, "/data/SomeMovie.mkv", "/strm/SomeMovie.mkv.strm", "SomeMovie.mkv", null, null, null);

    ArgumentCaptor<MediaLibraryItem> captor = ArgumentCaptor.forClass(MediaLibraryItem.class);
    verify(mapper).upsert(captor.capture());
    MediaLibraryItem item = captor.getValue();
    assertEquals("SomeMovie", item.getTitle());
    assertEquals("movie", item.getMediaType());
    assertNull(item.getTmdbId());
    assertNull(item.getScrapeStatus());
  }

  @Test
  void usesTaskLibraryTypeWhenMediaTypeIsAuto() {
    TaskConfig task = new TaskConfig().setId(1L).setLibraryType("tv");
    OpenlistConfig config = new OpenlistConfig().setId(2L);

    service.recordGeneratedFile(
        task, config, "/data/Show.S01E01.mkv", "/strm/Show.mkv.strm", "Show.S01E01.mkv", "auto", "Show", null);

    ArgumentCaptor<MediaLibraryItem> captor = ArgumentCaptor.forClass(MediaLibraryItem.class);
    verify(mapper).upsert(captor.capture());
    assertEquals("tv", captor.getValue().getMediaType());
  }

  @Test
  void queryClampsPageSizeToConfiguredMaximum() {
    MediaLibraryItem item = new MediaLibraryItem().setId(1L).setTitle("测试").setMediaType("movie");
    when(mapper.selectAll(isNull(), isNull(), isNull())).thenReturn(List.of(item));

    MediaLibraryDtos.PageResult result = service.query(null, null, null, 1, 999);

    assertEquals(1, result.total());
    assertEquals(60, result.pageSize());
    assertEquals(1, result.items().size());
    verify(mapper).selectAll(isNull(), isNull(), isNull());
  }

  @Test
  void queryFiltersByTaskTypeAndKeyword() {
    when(mapper.selectAll(3L, "tv", "盗梦")).thenReturn(List.of());

    MediaLibraryDtos.PageResult result = service.query(3L, "TV", "盗梦", 1, 24);

    assertEquals(0, result.total());
    assertEquals(0, result.items().size());
    verify(mapper).selectAll(3L, "tv", "盗梦");
  }

  @Test
  void returnsDetailForExistingItem() {
    MediaLibraryItem item =
        new MediaLibraryItem()
            .setId(5L)
            .setTaskId(1L)
            .setTitle("沙丘")
            .setMediaType("movie")
            .setSourcePath("/data/dune.mkv");
    when(mapper.selectById(5L)).thenReturn(item);
    when(mapper.selectAll(isNull(), isNull(), isNull())).thenReturn(List.of(item));

    MediaLibraryDtos.Detail detail = service.getDetail(5L);

    assertEquals("沙丘", detail.title());
    assertEquals("/data/dune.mkv", detail.sourcePath());
  }

  @Test
  void getDetailThrowsWhenItemMissing() {
    when(mapper.selectById(99L)).thenReturn(null);

    BusinessException ex = assertThrows(BusinessException.class, () -> service.getDetail(99L));
    assertTrue(ex.getMessage().contains("媒体条目不存在"));
  }

  @Test
  void getDetailThrowsWhenIdNull() {
    BusinessException ex = assertThrows(BusinessException.class, () -> service.getDetail(null));
    assertTrue(ex.getMessage().contains("媒体 ID 不能为空"));
  }

  @Test
  void resolvePlaybackReturnsLiveRawUrl() {
    MediaLibraryItem item =
        new MediaLibraryItem()
            .setId(7L)
            .setTitle("星际穿越")
            .setMediaType("movie")
            .setOpenlistConfigId(2L)
            .setSourcePath("/data/interstellar.mkv");
    OpenlistConfig config =
        new OpenlistConfig()
            .setId(2L)
            .setBaseUrl("http://ol:8080")
            .setToken("secret")
            .setIsActive(true);
    when(mapper.selectById(7L)).thenReturn(item);
    when(mapper.selectAll(isNull(), isNull(), isNull())).thenReturn(List.of(item));
    when(openlistConfigService.getById(2L)).thenReturn(config);
    when(openlistApiService.resolveRawUrl(config, "/data/interstellar.mkv"))
        .thenReturn("http://ol:8080/d/raw?sign=abc");

    MediaLibraryDtos.PlaybackResult result = service.resolvePlayback(7L, null);

    assertEquals("星际穿越", result.title());
    assertEquals("http://ol:8080/d/raw?sign=abc", result.url());
  }

  @Test
  void resolvePlaybackThrowsWhenConfigMissing() {
    MediaLibraryItem item = new MediaLibraryItem().setId(7L).setTitle("x").setMediaType("movie").setOpenlistConfigId(2L);
    when(mapper.selectById(7L)).thenReturn(item);
    when(mapper.selectAll(isNull(), isNull(), isNull())).thenReturn(List.of(item));
    when(openlistConfigService.getById(2L)).thenReturn(null);

    BusinessException ex = assertThrows(BusinessException.class, () -> service.resolvePlayback(7L, null));
    assertTrue(ex.getMessage().contains("OpenList 配置不存在"));
  }

  @Test
  void resolvePlaybackThrowsWhenConfigDisabled() {
    MediaLibraryItem item = new MediaLibraryItem().setId(7L).setTitle("x").setMediaType("movie").setOpenlistConfigId(2L);
    OpenlistConfig config = new OpenlistConfig().setId(2L).setIsActive(false);
    when(mapper.selectById(7L)).thenReturn(item);
    when(mapper.selectAll(isNull(), isNull(), isNull())).thenReturn(List.of(item));
    when(openlistConfigService.getById(2L)).thenReturn(config);

    BusinessException ex = assertThrows(BusinessException.class, () -> service.resolvePlayback(7L, null));
    assertTrue(ex.getMessage().contains("已停用"));
  }

  @Test
  void resolvePlaybackThrowsWhenItemMissing() {
    when(mapper.selectById(404L)).thenReturn(null);

    assertThrows(BusinessException.class, () -> service.resolvePlayback(404L, null));
  }

  @Test
  void listsTaskOptions() {
    TaskConfig a = new TaskConfig().setId(1L).setTaskName("电影任务");
    TaskConfig b = new TaskConfig().setId(2L).setTaskName("剧集任务");
    when(taskConfigService.getAllConfigs()).thenReturn(List.of(a, b));

    List<MediaLibraryDtos.TaskOption> options = service.listTaskOptions();

    assertEquals(2, options.size());
    assertEquals(1L, options.get(0).id());
    assertEquals("电影任务", options.get(0).taskName());
  }

  @Test
  void recordGeneratedFileUpsertIsIdempotentByDesign() {
    TaskConfig task = new TaskConfig().setId(1L).setLibraryType("movie");
    OpenlistConfig config = new OpenlistConfig().setId(2L);

    service.recordGeneratedFile(
        task, config, "/data/a.mkv", "/strm/a.mkv.strm", "a.mkv", "movie", "A", null);
    service.recordGeneratedFile(
        task, config, "/data/a.mkv", "/strm/a.mkv.strm", "a.mkv", "movie", "A", null);

    verify(mapper, org.mockito.Mockito.times(2)).upsert(any(MediaLibraryItem.class));
  }
}
