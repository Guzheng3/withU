/*
 * OStrm - Stream Management System
 * Copyright (C) 2024 OStrm Project
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

package com.hienao.openlist2strm.job;

import com.hienao.openlist2strm.entity.MediaLibraryItem;
import com.hienao.openlist2strm.entity.OpenlistConfig;
import com.hienao.openlist2strm.entity.TaskConfig;
import com.hienao.openlist2strm.mapper.MediaLibraryItemMapper;
import com.hienao.openlist2strm.mapper.OpenlistConfigMapper;
import com.hienao.openlist2strm.service.StrmFileService;
import com.hienao.openlist2strm.service.TaskConfigService;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.time.LocalDateTime;
import java.util.List;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.quartz.Job;
import org.quartz.JobExecutionContext;
import org.quartz.JobExecutionException;
import org.springframework.stereotype.Component;

/**
 * 账号删除到期清理定时任务
 *
 * <p>扫描软删除时间超过 7 天的 OpenList 配置，物理删除其关联的 STRM 文件、刮削元数据、
 * 媒体库记录与配置记录。7 天内重新添加同账号可取消删除计划，不进入本清理流程。
 *
 * @author hienao
 * @since 2024-01-01
 */
@Slf4j
@Component
@RequiredArgsConstructor
public class ConfigDeletionCleanupJob implements Job {

  private static final long DELETION_GRACE_DAYS = 7L;

  private final OpenlistConfigMapper openlistConfigMapper;
  private final MediaLibraryItemMapper mediaLibraryItemMapper;
  private final StrmFileService strmFileService;
  private final TaskConfigService taskConfigService;

  @Override
  public void execute(JobExecutionContext context) throws JobExecutionException {
    try {
      log.info("开始执行账号删除到期清理任务");

      LocalDateTime cutoff = LocalDateTime.now().minusDays(DELETION_GRACE_DAYS);
      List<OpenlistConfig> expiredConfigs = openlistConfigMapper.selectDeletedBefore(cutoff);

      if (expiredConfigs.isEmpty()) {
        log.info("没有需要清理的过期账号配置");
        return;
      }

      log.info("发现 {} 个软删除超过 {} 天的账号配置，开始物理清理", expiredConfigs.size(), DELETION_GRACE_DAYS);

      int cleanedConfigs = 0;
      for (OpenlistConfig config : expiredConfigs) {
        try {
          cleanupConfig(config);
          cleanedConfigs++;
        } catch (Exception e) {
          log.error("清理账号配置失败，配置ID: {}, 错误: {}", config.getId(), e.getMessage(), e);
        }
      }

      log.info("账号删除到期清理完成，成功清理 {} 个配置", cleanedConfigs);

    } catch (Exception e) {
      log.error("账号删除到期清理失败: {}", e.getMessage(), e);
      throw new JobExecutionException(e);
    }
  }

  /** 物理清理单个已到期的账号配置及其关联数据。 */
  private void cleanupConfig(OpenlistConfig config) {
    Long configId = config.getId();

    // 1. 物理删除关联的 STRM 文件与刮削元数据
    List<MediaLibraryItem> items = mediaLibraryItemMapper.selectByConfigId(configId);
    for (MediaLibraryItem item : items) {
      deleteStrmFileQuietly(item.getStrmPath());
    }

    // 2. 物理删除媒体库记录
    mediaLibraryItemMapper.deleteByConfigId(configId);

    // 3. 删除关联任务（含 Quartz 调度）
    List<TaskConfig> tasks = taskConfigService.getByOpenlistConfigId(configId);
    for (TaskConfig task : tasks) {
      try {
        taskConfigService.deleteConfig(task.getId());
        log.info("删除账号关联任务，任务ID: {}, 账号配置ID: {}", task.getId(), configId);
      } catch (Exception e) {
        log.warn("删除账号关联任务失败，任务ID: {}, 账号配置ID: {}, 错误: {}", task.getId(), configId, e.getMessage());
      }
    }

    // 4. 物理删除配置记录
    openlistConfigMapper.deleteById(configId);

    log.info(
        "账号配置物理清理完成，配置ID: {}, 用户名: {}, 清理媒体库条目: {} 条",
        configId,
        config.getUsername(),
        items.size());
  }

  /** 删除 STRM 文件及其元数据，异常不中断整体清理。 */
  private void deleteStrmFileQuietly(String strmPath) {
    if (strmPath == null || strmPath.isBlank()) {
      return;
    }
    try {
      Path path = Paths.get(strmPath);
      strmFileService.deleteStrmFileAndMetadata(path);
    } catch (Exception e) {
      log.warn("删除STRM文件失败，路径: {}, 错误: {}", strmPath, e.getMessage());
    }
  }
}
