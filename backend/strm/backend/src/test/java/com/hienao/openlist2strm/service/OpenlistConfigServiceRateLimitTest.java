package com.hienao.openlist2strm.service;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.verify;
import static org.mockito.Mockito.when;

import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.mapper.OpenlistConfigMapper;
import org.junit.jupiter.api.Test;

class OpenlistConfigServiceRateLimitTest {

  private OpenlistConfigService newService(
      OpenlistConfigMapper mapper, MediaLibraryItemMapper mediaMapper) {
    TaskConfigService taskConfigService = mock(TaskConfigService.class);
    return new OpenlistConfigService(mapper, mediaMapper, taskConfigService);
  }

  @Test
  void createDefaultsMissingLimitToUnlimited() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    OpenlistConfigService service = newService(mapper, mediaMapper);
    OpenlistConfig config = validConfig();
    when(mapper.insert(config)).thenReturn(1);

    service.createConfig(config);

    assertEquals(0, config.getFsApiQpmLimit());
    assertEquals(0, config.getFsApiQpsLimit());
    verify(mapper).insert(config);
  }

  @Test
  void updatePreservesLimitWhenOlderClientOmitsField() {
    OpenlistConfigMapper mapper = mock(OpenlistConfigMapper.class);
    MediaLibraryItemMapper mediaMapper = mock(MediaLibraryItemMapper.class);
    OpenlistConfigService service = newService(mapper, mediaMapper);
    OpenlistConfig existing = validConfig().setId(1L).setFsApiQpmLimit(30).setFsApiQpsLimit(3);
    OpenlistConfig update = validConfig().setId(1L);
    when(mapper.selectById(1L)).thenReturn(existing);
    when(mapper.updateById(update)).thenReturn(1);

    service.updateConfig(update);

    assertEquals(30, update.getFsApiQpmLimit());
    assertEquals(3, update.getFsApiQpsLimit());
    verify(mapper).updateById(update);
  }

  private OpenlistConfig validConfig() {
    return new OpenlistConfig()
        .setBaseUrl("https://openlist.example.com")
        .setToken("token")
        .setUsername("user");
  }
}
