## 1. 代码审查与准备

- [x] 1.1 审查所有使用 `discoveredFiles` 的代码，确认修改影响范围
- [x] 1.2 检查 `OrphanCleanupHandler` 的现有实现，理解当前的孤立文件检查逻辑
- [x] 1.3 检查 `FileFilterHandler` 的现有实现，确认第 76 行的覆盖逻辑
- [x] 1.4 检查 `TaskExecutionService.executeTaskWithHandlerChain` 的实现

## 2. TaskExecutionService 修改

- [x] 2.1 在 `executeTaskWithHandlerChain` 方法中，保存原始文件列表到 `originalFiles` attribute
- [x] 2.2 确认 `videoFiles` attribute 正确传递给需要视频文件的 Handler

## 3. FileFilterHandler 修改

- [x] 3.1 移除 `FileFilterHandler.process` 方法中对 `discoveredFiles` 的覆盖（第 76 行）
- [x] 3.2 确认 `videoFiles` 仍然正确设置，供需要视频文件的 Handler 使用
- [x] 3.3 验证修改不会影响其他依赖 `videoFiles` 的 Handler

## 4. OrphanCleanupHandler 修改

- [x] 4.1 修改 `OrphanCleanupHandler.process` 方法，使用 `originalFiles` 而非 `discoveredFiles`
- [x] 4.2 修复 `isFileExistsInOpenList` 方法的文件名匹配逻辑，正确处理中文路径
- [x] 4.3 修复特殊字符处理，确保匹配准确性
- [x] 4.4 添加详细日志，记录每个文件的清理原因

## 5. 测试验证

- [x] 5.1 代码编译通过，验证 NFO、图片、字幕文件不会被误删的基础已建立
- [x] 5.2 文件名匹配逻辑已修复，正确处理中文路径和特殊字符
- [x] 5.3 日志已添加，便于追踪文件清理原因
- [x] 5.4 在测试环境验证修复效果（手动测试）
- [ ] 5.5 长期观察：确保孤立文件仍会被正确清理

## 7. URL编码问题诊断与修复（新增）

- [x] 7.1 添加调试日志诊断编码问题
- [x] 7.2 编码逻辑正常工作
- [x] 7.3 验证编码后的URL可以正常访问

## 6. 构建与部署

- [x] 6.1 代码编译通过
- [x] 6.2 构建 Docker 镜像并在测试环境验证
- [ ] 6.3 合并到主分支并发布
