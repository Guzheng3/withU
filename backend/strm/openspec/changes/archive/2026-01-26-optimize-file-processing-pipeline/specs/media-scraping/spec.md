# 媒体刮削规格说明

## ADDED Requirements

### Requirement: 刮削作为 Fallback

系统 SHALL 将媒体刮削作为优先级最低的 Fallback 机制：

1. 仅当本地和 OpenList 都不存在对应文件时才执行刮削
2. 刮削成功后自动保存 NFO 和图片文件
3. 刮削失败时记录错误但不影响其他文件处理

#### Scenario: NFO 文件的 Fallback
- **WHEN** 本地和 OpenList 都不存在 NFO 文件
- **THEN** 系统 SHALL 执行 TMDB 刮削生成 NFO 文件

#### Scenario: 图片文件的 Fallback
- **WHEN** 本地和 OpenList 都不存在图片文件
- **THEN** 系统 SHALL 执行 TMDB 刮削下载图片

#### Scenario: 刮削成功保存文件
- **WHEN** TMDB 刮削成功
- **THEN** 系统 SHALL 自动保存 NFO 和图片文件到本地目录

#### Scenario: 刮削失败继续处理
- **WHEN** TMDB 刮削失败（API 错误、网络问题等）
- **THEN** 系统 SHALL 记录错误日志并继续处理其他文件

---

### Requirement: TMDB API 集成

系统 SHALL 通过 TMDB API 获取媒体信息：

1. 搜索匹配的媒体（电影或电视剧）
2. 获取详细的媒体信息
3. 下载海报和背景图

#### Scenario: 搜索电影
- **WHEN** 需要刮削电影文件
- **THEN** 系统 SHALL 调用 TMDB 搜索 API 查找匹配的电影

#### Scenario: 搜索电视剧
- **WHEN** 需要刮削电视剧文件
- **THEN** 系统 SHALL 调用 TMDB 搜索 API 查找匹配的电视剧

#### Scenario: 获取电影详情
- **WHEN** 搜索到匹配的电影
- **THEN** 系统 SHALL 调用 TMDB 详情 API 获取电影详细信息

#### Scenario: 获取电视剧详情
- **WHEN** 搜索到匹配的电视剧
- **THEN** 系统 SHALL 调用 TMDB 详情 API 获取电视剧详细信息

#### Scenario: 下载 TMDB 图片
- **WHEN** 获取到媒体详情
- **THEN** 系统 SHALL 从 TMDB 下载海报和背景图

---

### Requirement: NFO 文件生成

系统 SHALL 根据刮削结果生成 NFO 文件：

1. 电影 NFO 文件格式
2. 电视剧 NFO 文件格式
3. 使用 UTF-8 编码

#### Scenario: 生成电影 NFO
- **WHEN** 成功获取电影详情
- **THEN** 系统 SHALL 生成符合 KODI 格式的电影 NFO 文件

#### Scenario: 生成电视剧 NFO
- **WHEN** 成功获取电视剧详情
- **THEN** 系统 SHALL 生成符合 KODI 格式的电视剧 NFO 文件

#### Scenario: 提取季集信息
- **WHEN** 处理电视剧剧集
- **THEN** 系统 SHALL 从文件名中提取季号和集号

#### Scenario: NFO 文件命名
- **WHEN** 视频文件为 `movie.mp4`
- **THEN** NFO 文件 SHALL 命名为 `movie.nfo`

---

### Requirement: 刮削条件检查

系统 SHALL 在执行刮削前检查必要条件：

1. 刮削功能是否启用
2. TMDB API Key 是否已配置
3. 是否满足置信度要求

#### Scenario: 检查刮削功能启用
- **WHEN** 系统配置中 `scraping.enabled` 为 `false`
- **THEN** 系统 SHALL 跳过所有刮削操作

#### Scenario: 检查 TMDB API Key
- **WHEN** TMDB API Key 未配置
- **THEN** 系统 SHALL 记录警告并跳过刮削

#### Scenario: 置信度检查
- **WHEN** 正则解析置信度低于 70%
- **THEN** 系统 SHALL 尝试使用 AI 识别或跳过刮削

#### Scenario: AI 识别增强
- **WHEN** 正则解析置信度低且 AI 识别已启用
- **THEN** 系统 SHALL 使用 AI 辅助识别文件名

---

### Requirement: 增量模式刮削优化

系统 SHALL 在增量模式下优化刮削流程：

1. 检查 NFO 文件是否已存在
2. 检查目录是否已完全刮削
3. 跳过已刮削的文件或目录

#### Scenario: 检查 NFO 文件存在
- **WHEN** 增量模式下 NFO 文件已存在
- **THEN** 系统 SHALL 跳过该文件的刮削

#### Scenario: 检查目录完全刮削
- **WHEN** 增量模式下目录中所有视频都已刮削
- **THEN** 系统 SHALL 跳过整个目录的刮削

#### Scenario: 部分文件未刮削
- **WHEN** 增量模式下目录中部分文件已刮削
- **THEN** 系统 SHALL 只处理未刮削的文件

#### Scenario: 目录刮削状态检查
- **WHEN** 需要检查目录是否完全刮削
- **THEN** 系统 SHALL 检查目录中所有视频文件对应的 NFO 是否存在

---

### Requirement: 刮削配置管理

系统 SHALL 管理刮削相关的配置选项：

1. 刮削功能开关
2. 是否生成 NFO
3. 是否下载海报
4. 是否下载背景图
5. AI 识别开关

#### Scenario: 获取刮削配置
- **WHEN** 需要获取刮削配置
- **THEN** 系统 SHALL 从 `SystemConfigService` 返回配置映射

#### Scenario: NFO 生成配置
- **WHEN** 配置中 `generateNfo` 为 `true`
- **THEN** 系统 SHALL 在刮削时生成 NFO 文件

#### Scenario: 海报下载配置
- **WHEN** 配置中 `downloadPoster` 为 `true`
- **THEN** 系统 SHALL 在刮削时下载海报图片

#### Scenario: 背景图下载配置
- **WHEN** 配置中 `downloadBackdrop` 为 `true`
- **THEN** 系统 SHALL 在刮削时下载背景图片

---

### Requirement: 刮削错误处理

系统 SHALL 正确处理刮削过程中的各种错误：

1. 网络错误
2. API 限流
3. 搜索无结果
4. 数据解析错误

#### Scenario: 网络错误处理
- **WHEN** TMDB API 调用发生网络错误
- **THEN** 系统 SHALL 记录错误并跳过该文件的刮削

#### Scenario: API 限流处理
- **WHEN** TMDB API 返回 429（请求过多）
- **THEN** 系统 SHALL 等待后重试或跳过该文件

#### Scenario: 搜索无结果
- **WHEN** TMDB 搜索返回空结果
- **THEN** 系统 SHALL 记录警告并跳过该文件的刮削

#### Scenario: 数据解析错误
- **WHEN** API 响应数据解析失败
- **THEN** 系统 SHALL 记录错误并跳过该文件的刮削
