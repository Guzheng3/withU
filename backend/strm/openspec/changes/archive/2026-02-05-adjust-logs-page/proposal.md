# 调整日志页面 - 提案

## Why

当前日志页面使用 WebSocket 实现实时日志推送，但存在以下问题：
1. WebSocket 连接管理复杂，在页面切换时可能忘记断开连接
2. 自动滚动功能在用户需要查看历史日志时体验不佳
3. 清空显示功能意义不大，因为刷新页面日志会自动重新加载
4. 用户需要手动删除日志文件时缺乏直接入口，需要通过服务器操作

简化日志页面交互，使用手动刷新替代实时推送，可以降低系统复杂度，提升用户体验。

## What Changes

### 前端变更
- 移除 WebSocket 连接相关代码（`connectWebSocket`、`disconnectWebSocket`）
- 取消自动滚动功能开关
- 将自动滚动替换为"重新加载"按钮（手动刷新）
- 移除清空显示按钮
- 添加"删除日志"按钮，点击后删除前后端所有日志文件
- 简化日志显示逻辑，改用轮询或手动刷新获取日志

### 后端变更
- 移除 WebSocket 相关的配置和处理器（`WebSocketConfig`、`LogWebSocketHandler`、`LogFileMonitorService`）
- 添加删除日志文件的 API 接口 `DELETE /api/logs/{logType}` 或 `DELETE /api/logs`
- 保留基础的日志读取接口

## Capabilities

### New Capabilities
- `logs-page-simplification`: 日志页面简化重构

### Modified Capabilities
- 无（这是全新的功能变更，不影响现有规格）

## Impact

### 受影响的代码

| 组件 | 文件路径 | 变更类型 |
|------|----------|----------|
| 前端页面 | `frontend/app/pages/logs/index.vue` | 修改 |
| 后端 Controller | `backend/src/main/java/.../controller/LogController.java` | 修改 |
| 后端 Service | `backend/src/main/java/.../service/LogService.java` | 修改 |
| WebSocket 配置 | `backend/src/main/java/.../config/WebSocketConfig.java` | 移除 |
| WebSocket 处理器 | `backend/src/main/java/.../component/LogWebSocketHandler.java` | 移除 |
| 文件监控服务 | `backend/src/main/java/.../service/LogFileMonitorService.java` | 移除 |

### 受影响的 API

- 新增: `DELETE /api/logs/{logType}` - 删除指定类型日志文件
- 保留: `GET /api/logs/{logType}` - 获取日志内容
- 保留: `GET /api/logs/{logType}/download` - 下载日志文件
- 移除: WebSocket `/ws/logs/{logType}`

### 配置变更
- 移除 WebSocket 相关 Spring 配置
- 可能需要移除 WebSocket 依赖（如果无其他用途）
