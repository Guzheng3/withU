## Why

任务执行时，`OrphanCleanupHandler` 错误地删除了刚生成的 STRM、NFO 和图片文件。根本原因是处理器链中的 `FileFilterHandler` 将 `discoveredFiles` 覆盖为只包含视频文件的列表，导致 `OrphanCleanupHandler` 在检查文件是否存在时无法找到对应的刮削文件关联关系，最终将正常的刮削文件误判为"孤立文件"并删除。

## What Changes

- 修改 `TaskExecutionService.executeTaskWithHandlerChain`，将原始文件列表和过滤后的视频文件列表**分开存储**，使用不同的 attribute key
- 修改 `FileFilterHandler.process`，不再覆盖 `discoveredFiles`，而是使用独立的 `videoFiles` attribute
- 修改 `OrphanCleanupHandler`，使用原始完整文件列表（包含所有类型文件）进行孤立文件检查
- 修复 `isFileExistsInOpenList` 方法的文件名匹配逻辑，正确处理中文路径和特殊字符

## Capabilities

### New Capabilities
- `orphan-cleanup-fix`: 改进孤立文件清理逻辑，正确处理视频文件和刮削文件（NFO、图片、字幕）的关联关系

### Modified Capabilities
- 无（这是一个 bug 修复，不涉及需求层面的变化）

## Impact

- **修改文件**：
  - `TaskExecutionService.java`: 文件列表存储逻辑
  - `FileFilterHandler.java`: 移除对 `discoveredFiles` 的覆盖
  - `OrphanCleanupHandler.java`: 使用原始文件列表，修复匹配逻辑

- **影响功能**：
  - 任务执行完整性：确保 STRM、NFO、图片、字幕文件不会被误删
  - 增量模式下的孤立文件清理：更准确地识别真正需要清理的文件
