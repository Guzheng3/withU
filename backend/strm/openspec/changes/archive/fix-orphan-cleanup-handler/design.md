## Context

### 当前状态

在任务执行流程中，处理器链按顺序执行各个 Handler：
1. `FileDiscoveryHandler` (Order 10): 递归遍历 OpenList 目录，发现所有文件
2. `FileFilterHandler` (Order 20): 过滤出视频文件，但**覆盖了 `discoveredFiles`**
3. `StrmGenerationHandler` (Order 30): 生成 STRM 文件
4. `NfoDownloadHandler` (Order 40): 下载 NFO 文件
5. `ImageDownloadHandler` (Order 41): 下载图片文件
6. `SubtitleCopyHandler` (Order 42): 复制字幕文件
7. `OrphanCleanupHandler` (Order 60): 清理孤立文件

### 问题根因

`FileFilterHandler` 在第 76 行执行：
```java
context.setAttribute("discoveredFiles", videoFiles);
```

这会导致后续处理器（特别是 `OrphanCleanupHandler`）使用的 `discoveredFiles` 只包含视频文件，而 NFO、图片、字幕等刮削文件不在列表中。

当 `OrphanCleanupHandler` 执行 `isFileExistsInOpenList` 检查时：
- NFO 文件不在视频文件列表中 → 被标记为"孤立"
- 图片文件不在视频文件列表中 → 被标记为"孤立"
- STRM 文件因文件名匹配逻辑问题也可能被误删

### 约束条件

- 需要保持现有 Handler 链的执行顺序
- 不能破坏其他 Handler 的正常功能
- 修改应尽量小而集中，避免引入新的问题
- 需要兼容现有的任务配置和文件结构

## Goals / Non-Goals

**Goals:**
- 修复 `OrphanCleanupHandler` 误删刮削文件的问题
- 确保 STRM、NFO、海报、背景图、字幕文件不会被误删
- 保持增量模式下正确清理真正孤立文件的能力
- 修复文件名匹配逻辑，正确处理中文路径和特殊字符

**Non-Goals:**
- 不改变 Handler 链的整体架构
- 不修改 `FileProcessorChain` 的执行逻辑
- 不引入新的外部依赖
- 不改变现有的 API 接口或数据库结构

## Decisions

### 决策 1: 文件列表存储方式

**方案 A**: 在 TaskExecutionService 中保存原始列表，使用 `allFiles` 作为 attribute
- 优点：改动最小，只需要在存储时使用不同的 key
- 缺点：需要修改多个地方

**方案 B**: 创建新的 context attribute `originalFiles` 保存原始列表
- 优点：清晰分离原始数据和过滤后数据
- 缺点：需要修改 OrphanCleanupHandler 的实现

**最终选择**: 方案 B
- 在 `TaskExecutionService.executeTaskWithHandlerChain` 中，将原始 `allFiles` 保存为 `originalFiles`
- `OrphanCleanupHandler` 使用 `originalFiles` 而非 `discoveredFiles` 进行检查

### 决策 2: FileFilterHandler 修改

**方案 A**: 移除对 `discoveredFiles` 的覆盖，只设置 `videoFiles`
- 优点：保持 `discoveredFiles` 的原始含义
- 缺点：可能影响其他依赖 `discoveredFiles` 的 Handler

**方案 B**: 在设置 `videoFiles` 之前，先保存原始列表到 `originalFiles`
- 优点：不改变现有行为，向后兼容
- 缺点：需要额外的存储操作

**最终选择**: 方案 A
- 移除 `FileFilterHandler` 中对 `discoveredFiles` 的覆盖
- 改为使用独立的 `videoFiles` attribute 传递给需要视频文件的 Handler

### 决策 3: 文件名匹配逻辑

**问题**: 现有实现使用 `replaceAll("[^a-z0-9]", "")` 移除所有非字母数字字符，对于中文文件名会导致匹配失败。

**解决方案**:
- 保留原始文件名用于匹配
- 使用文件名（不含扩展名）进行精确匹配
- 对重命名规则应用后的文件名也进行匹配

### 决策 4: 孤立文件清理策略

**方案 A**: 只清理真正的孤立文件（视频源文件不存在时）
- 优点：保守策略，不会误删
- 缺点：可能残留一些临时文件

**方案 B**: 清理时检查视频源文件的存在性
- 优点：更准确
- 缺点：需要额外的 API 调用

**最终选择**: 方案 A + B 混合
- 优先使用方案 B（检查源文件存在性）
- 降级到方案 A（如果检查失败）

## Risks / Trade-offs

| 风险 | 可能性 | 影响 | 缓解措施 |
|------|--------|------|----------|
| 其他 Handler 依赖 `discoveredFiles` 包含所有文件 | 低 | 中 | 审查所有使用 `discoveredFiles` 的代码 |
| 性能问题（使用原始文件列表） | 低 | 低 | 原始列表已在内存中，无额外开销 |
| 回归问题 | 中 | 中 | 编写集成测试验证修复 |

## Migration Plan

### 部署步骤
1. 提交代码修改到 feature 分支
2. 构建 Docker 镜像并测试
3. 在测试环境验证修复效果
4. 合并到主分支并发布

### 回滚策略
- 如果发现问题，可以通过 Docker 镜像回滚到 previous 版本
- 数据库无需修改，回滚不影响数据

## Open Questions

1完整性. 是否需要在 `OrphanCleanupHandler` 中添加详细的日志，记录每个文件的清理原因？
2. 是否需要为这个修复添加单元测试？
