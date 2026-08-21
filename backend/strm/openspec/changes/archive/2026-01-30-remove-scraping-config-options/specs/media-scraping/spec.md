# media-scraping Specification Delta

## REMOVED Requirements

### Requirement: 刮削配置管理

**Reason**: 配置项 `generateNfo`、`downloadPoster`、`downloadBackdrop`、`overwriteExisting` 已移除，改为固定行为
**Migration**: 相关功能现在始终执行，无需配置

**原需求内容：**
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

## MODIFIED Requirements

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
- **THEN** 系统 SHALL 执行 TMDB 刮削下载海报和背景图

#### Scenario: 刮削成功保存文件
- **WHEN** TMDB 刮削成功
- **THEN** 系统 SHALL 自动保存 NFO、海报和背景图文件到本地目录

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
- **THEN** 系统 SHALL 从 TMDB 下载海报和背景图（始终执行）

---

### Requirement: 增量模式刮削优化

系统 SHALL 在增量模式下优化刮削流程：

1. 检查 NFO 文件是否已存在
2. 检查图片文件是否已存在
3. 跳过已刮削的文件或目录

#### Scenario: 检查 NFO 文件存在
- **WHEN** 增量模式下 NFO 文件已存在
- **THEN** 系统 SHALL 跳过该文件的 NFO 生成

#### Scenario: 检查图片文件存在
- **WHEN** 增量模式下海报和背景图文件已存在
- **THEN** 系统 SHALL 跳过该文件的图片下载

#### Scenario: 检查目录完全刮削
- **WHEN** 增量模式下目录中所有视频都已刮削
- **THEN** 系统 SHALL 跳过整个目录的刮削

#### Scenario: 部分文件未刮削
- **WHEN** 增量模式下目录中部分文件已刮削
- **THEN** 系统 SHALL 只处理未刮削的文件

#### Scenario: 目录刮削状态检查
- **WHEN** 需要检查目录是否完全刮削
- **THEN** 系统 SHALL 检查目录中所有视频文件对应的 NFO、海报和背景图是否存在
