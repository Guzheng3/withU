# 图片文件管理规格说明

## ADDED Requirements

### Requirement: 图片文件优先级处理

系统 SHALL 实现三级优先级处理图片文件（海报、背景图、缩略图）：

1. **优先级 1 - 本地文件**：检查本地是否存在对应图片文件
2. **优先级 2 - OpenList 文件**：本地不存在时从 OpenList 同级目录下载
3. **优先级 3 - 刮削文件**：前两级都不存在时执行 TMDB 刮削

#### Scenario: 本地海报文件已存在
- **WHEN** 目标目录存在 `{baseFileName}-poster.jpg` 文件
- **THEN** 系统 SHALL 跳过海报下载，使用本地文件

#### Scenario: 本地背景图文件已存在
- **WHEN** 目标目录存在 `{baseFileName}-fanart.jpg` 文件
- **THEN** 系统 SHALL 跳过背景图下载，使用本地文件

#### Scenario: 本地剧集缩略图已存在
- **WHEN** 目标目录存在 `{baseFileName}-thumb.jpg` 文件
- **THEN** 系统 SHALL 跳过缩略图下载，使用本地文件

#### Scenario: 本地不存在但 OpenList 存在
- **WHEN** 本地不存在图片文件且 OpenList 同级目录存在同名图片
- **THEN** 系统 SHALL 从 OpenList 下载图片文件并保存到本地

---

### Requirement: 支持的图片类型

系统 SHALL 支持以下类型的图片文件：

1. 海报文件：`{baseFileName}-poster.jpg`
2. 背景图文件：`{baseFileName}-fanart.jpg`
3. 剧集缩略图：`{baseFileName}-thumb.jpg`
4. 电视剧海报：`poster.jpg`（电视剧目录共用）
5. 电视剧背景图：`fanart.jpg`（电视剧目录共用）

#### Scenario: 下载海报文件
- **WHEN** 需要下载电影海报
- **THEN** 系统 SHALL 查找并下载 `{baseFileName}-poster.jpg`

#### Scenario: 下载背景图文件
- **WHEN** 需要下载电影背景图
- **THEN** 系统 SHALL 查找并下载 `{baseFileName}-fanart.jpg`

#### Scenario: 下载剧集缩略图
- **WHEN** 需要下载剧集缩略图
- **THEN** 系统 SHALL 查找并下载 `{baseFileName}-thumb.jpg`

#### Scenario: 下载电视剧共用图片
- **WHEN** 处理电视剧剧集
- **THEN** 系统 SHALL 查找目录级的 `poster.jpg` 和 `fanart.jpg`

---

### Requirement: 本地图片文件检查

系统 SHALL 提供 `isLocalImageExists()` 方法检查本地图片文件是否存在：

1. 检查保存目录是否存在
2. 检查海报、背景图、缩略图等文件
3. 返回存在的图片文件类型列表

#### Scenario: 检查海报文件存在
- **WHEN** 目录 `/strm/movies/` 存在 `movie-poster.jpg`
- **THEN** 系统 SHALL 识别海报文件已存在

#### Scenario: 检查背景图文件存在
- **WHEN** 目录 `/strm/movies/` 存在 `movie-fanart.jpg`
- **THEN** 系统 SHALL 识别背景图文件已存在

#### Scenario: 多个图片文件检查
- **WHEN** 目录中存在部分图片文件
- **THEN** 系统 SHALL 返回已存在的图片类型，缺失的类型需要下载

#### Scenario: 目录不存在
- **WHEN** 检查的目录不存在
- **THEN** 系统 SHALL 返回空列表，表示所有图片都需要下载

---

### Requirement: 从 OpenList 下载图片

系统 SHALL 在本地不存在图片文件时从 OpenList 同级目录下载：

1. 在目录文件列表中查找同名图片文件
2. 调用 OpenList API 下载文件内容
3. 将内容保存到本地目录

#### Scenario: 查找 OpenList 中的图片文件
- **WHEN** 目录文件列表包含 `{baseFileName}-poster.jpg`
- **THEN** 系统 SHALL 识别该文件为可下载的海报文件

#### Scenario: 下载图片文件内容
- **WHEN** 找到 OpenList 中的图片文件
- **THEN** 系统 SHALL 调用 `openlistApiService.getFileContent()` 下载内容

#### Scenario: 保存下载的图片文件
- **WHEN** 图片文件下载成功
- **THEN** 系统 SHALL 将内容保存到 `{saveDirectory}/{fileName}.jpg`

#### Scenario: 图片格式验证
- **WHEN** 下载图片文件后
- **THEN** 系统 SHALL 验证文件头以确认图片格式

---

### Requirement: 配置联动 - 优先使用已存在的刮削信息

系统 SHALL 根据「优先使用已存在的刮削信息」配置控制图片处理：

1. 配置启用时：执行完整的三级优先级流程
2. 配置禁用时：仅检查本地文件，不执行下载和刮削

#### Scenario: 配置启用时完整流程
- **WHEN** 系统配置中 `useExistingScrapingInfo` 为 `true`
- **THEN** 系统 SHALL 执行本地检查 → OpenList 下载 → 刮削的完整流程

#### Scenario: 配置禁用时仅本地检查
- **WHEN** 系统配置中 `useExistingScrapingInfo` 为 `false`
- **THEN** 系统 SHALL 仅检查本地图片文件，不执行下载和刮削

#### Scenario: 本地存在时忽略配置
- **WHEN** 本地图片文件已存在
- **THEN** 无论配置如何，系统 SHALL 直接使用本地文件

#### Scenario: 缺失图片的默认处理
- **WHEN** 本地图片缺失且配置禁用
- **THEN** 系统 SHALL 跳过图片下载，不执行刮削
