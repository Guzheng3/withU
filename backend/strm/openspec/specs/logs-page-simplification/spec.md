# 日志页面简化

## Purpose

本规格定义了日志页面的简化功能，包括手动刷新和删除日志功能。移除 WebSocket 实时推送，改用手动刷新方式获取日志数据。

## Requirements

### Requirement: 日志页面支持手动刷新
日志页面 SHALL 提供手动刷新功能，用户点击"重新加载"按钮后系统 SHALL 重新获取当前选中类型的日志数据。

#### Scenario: 用户点击重新加载按钮
- **WHEN** 用户点击"重新加载"按钮
- **THEN** 系统显示加载状态指示器
- **THEN** 系统调用 `GET /api/logs/{logType}` 获取日志数据
- **THEN** 系统解析并显示返回的日志行
- **THEN** 系统更新日志统计信息
- **THEN** 系统更新最后更新时间显示

#### Scenario: 页面初次加载
- **WHEN** 用户导航到日志页面
- **THEN** 系统自动调用 `GET /api/logs/{logType}` 获取日志数据
- **THEN** 系统解析并显示返回的日志行
- **THEN** 系统显示日志总数和最后更新时间

#### Scenario: 用户切换日志类型
- **WHEN** 用户从下拉框选择不同的日志类型
- **THEN** 系统自动调用 `GET /api/logs/{newLogType}` 获取新类型的日志数据
- **THEN** 系统清空当前显示的日志
- **THEN** 系统显示新类型日志数据

---

### Requirement: 日志页面支持删除日志
日志页面 SHALL 提供删除日志功能，用户可以删除当前选中类型的日志文件。

#### Scenario: 用户确认删除日志
- **WHEN** 用户点击"删除日志"按钮
- **THEN** 系统显示确认对话框，提示"确定要删除当前日志文件吗？"
- **WHEN** 用户在确认对话框中点击"确认"
- **THEN** 系统调用 `DELETE /api/logs/{logType}` 删除日志文件
- **THEN** 系统清空前端显示的日志数据
- **THEN** 系统显示成功提示"日志已删除"

#### Scenario: 用户取消删除操作
- **WHEN** 用户点击"删除日志"按钮
- **THEN** 系统显示确认对话框
- **WHEN** 用户点击"取消"按钮或点击对话框外部
- **THEN** 系统关闭确认对话框
- **THEN** 日志文件保持不变

#### Scenario: 删除不存在的日志文件
- **WHEN** 用户点击"删除日志"按钮
- **THEN** 系统显示确认对话框
- **WHEN** 用户在确认对话框中点击"确认"
- **THEN** 系统调用 `DELETE /api/logs/{logType}`
- **THEN** 后端返回 404 错误
- **THEN** 系统显示错误提示"日志文件不存在"

---

### Requirement: 后端支持删除日志 API
后端 SHALL 提供 `DELETE /api/logs/{logType}` 接口用于删除指定类型的日志文件。

#### Scenario: 成功删除后端日志文件
- **WHEN** 调用 `DELETE /api/logs/backend`
- **THEN** 系统删除 `backend.log` 文件
- **THEN** 系统返回 HTTP 200 状态码
- **THEN** 响应体包含 `{"code": 200, "message": "删除成功"}`

#### Scenario: 成功删除前端日志文件
- **WHEN** 调用 `DELETE /api/logs/frontend`
- **THEN** 系统删除 `frontend.log` 文件
- **THEN** 系统返回 HTTP 200 状态码
- **THEN** 响应体包含 `{"code": 200, "message": "删除成功"}`

#### Scenario: 无效的日志类型
- **WHEN** 调用 `DELETE /api/logs/invalid`
- **THEN** 系统返回 HTTP 400 状态码
- **THEN** 响应体包含 `{"code": 400, "message": "无效的日志类型"}`

---

### Requirement: 移除 WebSocket 实时日志推送
系统 SHALL 移除 WebSocket 实时日志推送功能，改用手动刷新方式获取日志数据。

#### Scenario: 移除 WebSocket 连接
- **WHEN** 用户访问日志页面
- **THEN** 系统不再建立 WebSocket 连接
- **THEN** 系统使用 `GET /api/logs/{logType}` 接口获取日志数据
- **THEN** 用户通过点击"重新加载"按钮获取最新日志

#### Scenario: 移除 WebSocket 相关组件
- **WHEN** 系统清理 WebSocket 相关代码
- **THEN** 删除 `WebSocketConfig.java` 配置类
- **THEN** 删除 `LogWebSocketHandler.java` 处理器
- **THEN** 删除 `LogFileMonitorService.java` 文件监控服务

---

### Requirement: 移除自动滚动功能
系统 SHALL 移除自动滚动开关，改用手动刷新方式。

#### Scenario: 移除自动滚动开关
- **WHEN** 用户访问日志页面
- **THEN** 不再显示自动滚动开关
- **THEN** 显示"重新加载"按钮供用户手动刷新
- **THEN** 页面不再自动滚动到最新日志

---

### Requirement: 移除清空显示功能
系统 SHALL 移除"清空显示"按钮。

#### Scenario: 移除清空显示按钮
- **WHEN** 用户访问日志页面
- **THEN** 不再显示"清空显示"按钮
- **THEN** 用户可使用"重新加载"按钮刷新日志

---

### Requirement: 移除连接状态显示
系统 SHALL 移除日志统计区域的连接状态卡片。

#### Scenario: 移除连接状态卡片
- **WHEN** 用户访问日志页面
- **THEN** 日志统计区域不再显示连接状态
- **THEN** 统计区域仅显示：总行数、错误日志、警告日志
