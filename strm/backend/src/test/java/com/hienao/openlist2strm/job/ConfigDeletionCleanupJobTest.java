package com.hienao.openlist2strm.job;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.when;

import com.hienao.openlist2strm.entity.MediaLibraryItem;
import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.mapper.OpenlistConfigMapper;
import com.hienao.openlist2strm.service.StrmFileService;
import com.hienao.openlist2strm.service.TaskConfigService;
import java.nio.file.Paths;
import java.time.LocalDateTime;
import java.util.List;
import org.junit.jupiter.api.Test;
import org.mockito.ArgumentMatchers;
import org.quartz.JobExecutionException;

class ConfigDeletionCleanupJobTest {

  @Test
  void executePhysicallyDeletesExpiredConfigAndData() throws JobExecutionException {
    OpenlistConfigMapper configMapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    StrmFileService strmFileService = mock(StrmFileService.class);
    TaskConfigService taskConfigService = mock(TaskConfigService.class);
    ConfigDeletionCleanupJob job =
        new ConfigDeletionCleanupJob(configMapper, mediaMapper, strmFileService, taskConfigService);

    OpenlistConfig expired = new OpenlistConfig().setId(1L).setUsername("old-account");
    when(configMapper.selectDeletedBefore(ArgumentMatchers.any()))
        .thenReturn(List.of(expired));

    MediaLibraryItem item =
        new MediaLibraryItem().setStrmPath("/app/backend/strm/movies/x.strm");
    when(mediaMapper.selectByConfigId(1L)).thenReturn(List.of(item));

    TaskConfig task = new TaskConfig().setId(10L);
    when(taskConfigService.getByOpenlistConfigId(1L)).thenReturn(List.of(task));

    job.execute(null);

    verify(strmFileService).deleteStrmFileAndMetadata(Paths.get("/app/backend/strm/movies/x.strm"));
    verify(mediaMapper).deleteByConfigId(1L);
    verify(taskConfigService).deleteConfig(10L);
    verify(configMapper).deleteById(1L);
  }

  @Test
  void executeSkipsNothingWhenNoExpiredConfigs() throws JobExecutionException {
    OpenlistConfigMapper configMapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    StrmFileService strmFileService = mock(StrmFileService.class);
    TaskConfigService taskConfigService = mock(TaskConfigService.class);
    ConfigDeletionCleanupJob job =
        new ConfigDeletionCleanupJob(configMapper, mediaMapper, strmFileService, taskConfigService);

    when(configMapper.selectDeletedBefore(ArgumentMatchers.any())).thenReturn(List.of());

    job.execute(null);

    verify(mediaMapper, org.mockito.Mockito.never()).deleteByConfigId(ArgumentMatchers.any());
    verify(configMapper, org.mockito.Mockito.never()).deleteById(ArgumentMatchers.any());
  }

  @Test
  void executeUsesSevenDayCutoff() throws JobExecutionException {
    OpenlistConfigMapper configMapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    StrmFileService strmFileService = mock(StrmFileService.class);
    TaskConfigService taskConfigService = mock(TaskConfigService.class);
    ConfigDeletionCleanupJob job =
        new ConfigDeletionCleanupJob(configMapper, mediaMapper, strmFileService, taskConfigService);

    when(configMapper.selectDeletedBefore(ArgumentMatchers.any())).thenReturn(List.of());

    job.execute(null);

    org.mockito.ArgumentCaptor<LocalDateTime> captor =
        org.mockito.ArgumentCaptor.forClass(LocalDateTime.class);
    verify(configMapper).selectDeletedBefore(captor.capture());
    LocalDateTime cutoff = captor.getValue();
    LocalDateTime expected = LocalDateTime.now().minusDays(7);
    assertEquals(expected.getDayOfYear(), cutoff.getDayOfYear());
  }
}
