# 调整日志页面 - 实现任务

## 1. 后端修改

- [x] 1.1 在 `LogService` 中添加 `deleteLogFile(String logType)` 方法
- [x] 1.2 在 `LogController` 中添加 `DELETE /api/logs/{logType}` 接口
- [x] 1.3 删除 `LogWebSocketHandler.java` 文件
- [x] 1.4 删除 `WebSocketConfig.java` 文件
- [x] 1.5 删除 `LogFileMonitorService.java` 文件
- [x] 1.6 验证后端启动正常，无 WebSocket 相关错误

## 2. 前端修改

- [x] 2.1 移除 WebSocket 相关变量 (`ws`, `wsConnected`)
- [x] 2.2 移除 `connectWebSocket()` 函数
- [x] 2.3 移除 `onMounted` 中的 `connectWebSocket()` 调用
- [x] 2.4 移除 `onUnmounted` 中的 `ws.close()` 调用
- [x] 2.5 移除自动滚动开关 UI
- [x] 2.6 将自动滚动区域替换为"重新加载"按钮
- [x] 2.7 移除"清空显示"按钮
- [x] 2.8 添加"删除日志"按钮和确认对话框
- [x] 2.9 实现 `deleteLogs()` 函数调用后端删除 API
- [x] 2.10 移除统计区域的"连接状态"卡片
- [x] 2.11 测试日志加载、筛选、下载功能
- [x] 2.12 测试删除日志功能

## 3. 验证测试

- [x] 3.1 构建后端镜像并启动
- [x] 3.2 构建前端镜像并启动
- [x] 3.3 访问日志页面，验证手动刷新功能
- [x] 3.4 验证日志下载功能正常
- [x] 3.5 验证删除日志功能正常
- [x] 3.6 验证后端日志文件被正确删除
