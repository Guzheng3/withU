package com.hienao.openlist2strm.service;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.never;
import static org.mockito.Mockito.times;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.when;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.exception.BusinessException;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.mapper.OpenlistConfigMapper;
import java.time.LocalDateTime;
import java.util.List;
import org.junit.jupiter.api.Test;

class OpenlistConfigSoftDeleteTest {

  private OpenlistConfigService newService(
      OpenlistConfigMapper mapper, MediaLibraryItemMapper mediaMapper, TaskConfigService taskService) {
    return new OpenlistConfigService(mapper, mediaMapper, taskService);
  }

  private OpenlistConfig validConfig() {
    return new OpenlistConfig()
        .setBaseUrl("https://openlist.example.com")
        .setToken("token")
        .setUsername("user");
  }

  @Test
  void deleteMarksConfigAndLibraryAsSoftDeleted() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    TaskConfigService taskService = mock(TaskConfigService.class);
    OpenlistConfigService service = newService(mapper, mediaMapper, taskService);

    OpenlistConfig existing = validConfig().setId(1L);
    when(mapper.selectById(1L)).thenReturn(existing);
    when(mapper.updateDeletedAt(org.mockito.ArgumentMatchers.eq(1L),
        org.mockito.ArgumentMatchers.any())).thenReturn(1);
    when(taskService.getByOpenlistConfigId(1L)).thenReturn(List.of());

    service.deleteConfig(1L);

    verify(mapper).updateDeletedAt(org.mockito.ArgumentMatchers.eq(1L),
        org.mockito.ArgumentMatchers.any());
    verify(mediaMapper).softDeleteByConfigId(1L);
    verify(mapper, never()).deleteById(1L);
  }

  @Test
  void deleteDeactivatesRelatedTasks() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    TaskConfigService taskService = mock(TaskConfigService.class);
    OpenlistConfigService service = newService(mapper, mediaMapper, taskService);

    OpenlistConfig existing = validConfig().setId(1L);
    TaskConfig task = new TaskConfig().setId(10L).setIsActive(true);
    when(mapper.selectById(1L)).thenReturn(existing);
    when(mapper.updateDeletedAt(org.mockito.ArgumentMatchers.eq(1L),
        org.mockito.ArgumentMatchers.any())).thenReturn(1);
    when(taskService.getByOpenlistConfigId(1L)).thenReturn(List.of(task));

    service.deleteConfig(1L);

    verify(taskService).updateActiveStatus(10L, false);
  }

  @Test
  void createWithDeletedUsernameRestoresConfig() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    TaskConfigService taskService = mock(TaskConfigService.class);
    OpenlistConfigService service = newService(mapper, mediaMapper, taskService);

    OpenlistConfig deleted = validConfig().setId(1L).setDeletedAt(LocalDateTime.now().minusDays(1));
    OpenlistConfig newConfig = validConfig();
    when(mapper.selectByUsernameIncludeDeleted("user")).thenReturn(deleted);
    when(mapper.updateById(newConfig)).thenReturn(1);
    when(mapper.updateDeletedAt(1L, null)).thenReturn(1);
    OpenlistConfig restored = validConfig().setId(1L).setDeletedAt(null);
    when(mapper.selectById(1L)).thenReturn(restored);
    when(taskService.getByOpenlistConfigId(1L)).thenReturn(List.of());

    OpenlistConfig result = service.createConfig(newConfig);

    assertEquals(1L, result.getId());
    verify(mediaMapper).restoreByConfigId(1L);
    verify(mapper, never()).insert(newConfig);
  }

  @Test
  void createWithActiveUsernameRejectsDuplicate() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    TaskConfigService taskService = mock(TaskConfigService.class);
    OpenlistConfigService service = newService(mapper, mediaMapper, taskService);

    OpenlistConfig active = validConfig().setId(1L).setDeletedAt(null);
    when(mapper.selectByUsernameIncludeDeleted("user")).thenReturn(active);

    assertThrows(BusinessException.class, () -> service.createConfig(validConfig()));
  }

  @Test
  void restoreReactivatesRelatedTasks() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    TaskConfigService taskService = mock(TaskConfigService.class);
    OpenlistConfigService service = newService(mapper, mediaMapper, taskService);

    OpenlistConfig deleted = validConfig().setId(1L).setDeletedAt(LocalDateTime.now().minusDays(1));
    OpenlistConfig newConfig = validConfig();
    TaskConfig task = new TaskConfig().setId(10L).setIsActive(false);
    when(mapper.selectByUsernameIncludeDeleted("user")).thenReturn(deleted);
    when(mapper.updateById(newConfig)).thenReturn(1);
    when(mapper.updateDeletedAt(1L, null)).thenReturn(1);
    OpenlistConfig restored = validConfig().setId(1L).setDeletedAt(null);
    when(mapper.selectById(1L)).thenReturn(restored);
    when(taskService.getByOpenlistConfigId(1L)).thenReturn(List.of(task));

    service.createConfig(newConfig);

    verify(taskService, times(1)).updateActiveStatus(10L, true);
  }
}
