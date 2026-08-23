# 文件发现规格说明

## ADDED Requirements

### Requirement: 递归目录遍历

系统 SHALL 通过 OpenList API 递归遍历指定目录，收集所有文件和子目录信息：

1. 从任务配置的 `path` 起始目录开始
2. 逐层获取每个目录的内容列表
3. 对于子目录，递归调用获取其内容
4. 对于文件，记录文件元数据（名称、路径、URL、签名等）

#### Scenario: 从根目录开始遍历
- **WHEN** 任务配置的 path 为 `/movies`
- **THEN** 系统 SHALL 从 `/movies` 目录开始遍历

#### Scenario: 遍历子目录
- **WHEN** 当前目录包含子目录 `/movies/Action`
- **THEN** 系统 SHALL 递归遍历 `/movies/Action` 目录

#### Scenario: 收集文件元数据
- **WHEN** 遍历过程中发现文件 `movie.mp4`
- **THEN** 系统 SHALL 记录文件名、完整路径、URL 和 sign 参数

#### Scenario: 处理空目录
- **WHEN** 遇到空目录
- **THEN** 系统 SHALL 跳过该目录并继续处理其他内容

---

### Requirement: OpenList API 调用

系统 SHALL 通过 OpenList 的 `/api/fs/list` 接口获取目录内容：

1. 构建正确的 API 请求 URL
2. 设置必要的请求头（Authorization）
3. 发送 POST 请求获取目录列表
4. 解析 API 响应并转换为内部文件模型

#### Scenario: 构建 API 请求
- **WHEN** 需要获取目录 `/movies` 的内容
- **THEN** 系统 SHALL 向 `{baseUrl}/api/fs/list` 发送请求

#### Scenario: 设置请求头
- **WHEN** 发送 API 请求
- **THEN** 系统 SHALL 设置 `Content-Type: application/json` 和 `Authorization` 头

#### Scenario: 解析 API 响应
- **WHEN** OpenList API 返回响应
- **THEN** 系统 SHALL 解析 JSON 响应并转换为 `OpenlistFile` 对象列表

#### Scenario: 处理 API 错误
- **WHEN** OpenList API 返回错误
- **THEN** 系统 SHALL 记录错误日志并继续处理其他目录

---

### Requirement: 文件模型定义

系统 SHALL 定义 `OpenlistFile` 类来表示 OpenList 中的文件/目录，包含以下属性：

1. `name` - 文件/目录名称
2. `type` - 类型（"file" 或 "folder"）
3. `path` - 完整路径
4. `url` - 文件访问 URL
5. `sign` - 签名参数（用于受保护文件）

#### Scenario: 创建文件模型
- **WHEN** 从 API 响应中解析文件信息
- **THEN** 系统 SHALL 创建 `OpenlistFile` 对象并填充属性

#### Scenario: 文件类型判断
- **WHEN** 文件的 `isDir` 属性为 true
- **THEN** 文件类型 SHALL 设置为 "folder"

#### Scenario: 设置签名参数
- **WHEN** API 响应中包含 sign 字段
- **THEN** 系统 SHALL 将 sign 值保存到文件模型中

---

### Requirement: 内存优化处理

系统 SHALL 使用内存优化策略处理大量文件：

1. 分批处理目录，避免一次性加载所有文件
2. 视频文件立即处理，不累积在内存中
3. 处理完一个目录后及时释放内存引用

#### Scenario: 分批处理目录
- **WHEN** 目录包含大量文件（>1000个）
- **THEN** 系统 SHALL 分批次获取和处理文件

#### Scenario: 立即处理视频文件
- **WHEN** 发现视频文件
- **THEN** 系统 SHALL 立即调用后续处理器处理，不等待目录遍历完成

#### Scenario: 内存使用监控
- **WHEN** 处理大量文件时
- **THEN** 系统 SHALL 通过及时释放引用来控制内存使用

#### Scenario: 降级处理
- **WHEN** 分批处理失败
- **THEN** 系统 SHALL 降级到原始的全量加载方法
