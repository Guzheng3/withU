# NFO 文件管理规格说明

## ADDED Requirements

### Requirement: NFO 文件优先级处理

系统 SHALL 实现三级优先级处理 NFO 文件：

1. **优先级 1 - 本地文件**：检查本地是否存在对应 NFO 文件
2. **优先级 2 - OpenList 文件**：本地不存在时从 OpenList 同级目录下载
3. **优先级 3 - 刮削文件**：前两级都不存在时执行 TMDB 刮削

#### Scenario: 本地 NFO 文件已存在
- **WHEN** 目标目录存在 `{baseFileName}.nfo` 文件
- **THEN** 系统 SHALL 跳过 NFO 下载，使用本地文件

#### Scenario: 本地不存在但 OpenList 存在
- **WHEN** 本地不存在 NFO 文件且 OpenList 同级目录存在同名 NFO
- **THEN** 系统 SHALL 从 OpenList 下载 NFO 文件并保存到本地

#### Scenario: OpenList 不存在
- **WHEN** OpenList 同级目录不存在 NFO 文件
- **THEN** 系统 SHALL 执行 TMDB 刮削生成 NFO 文件

#### Scenario: 全部优先级都不存在
- **WHEN** 本地、OpenList 都不存在且刮削失败
- **THEN** 系统 SHALL 记录错误并继续处理其他文件

---

### Requirement: 本地 NFO 文件检查

系统 SHALL 提供 `isLocalNfoExists()` 方法检查本地 NFO 文件是否存在：

1. 检查保存目录是否存在
2. 扫描目录中的 `.nfo` 文件
3. 匹配与视频文件同名（去除扩展名）的 NFO 文件

#### Scenario: 检查 NFO 文件存在
- **WHEN** 目录 `/strm/movies/` 存在 `movie.nfo` 文件
- **THEN** 调用 `isLocalNfoExists("/strm/movies/", "movie")` SHALL 返回 `true`

#### Scenario: 检查 NFO 文件不存在
- **WHEN** 目录 `/strm/movies/` 不存在 `movie.nfo` 文件
- **THEN** 调用 `isLocalNfoExists("/strm/movies/", "movie")` SHALL 返回 `false`

#### Scenario: NFO 文件名大小写不敏感
- **WHEN** 目录存在 `Movie.nfo` 文件
- **THEN** 检查 `movie.nfo` SHALL 返回 `true`

#### Scenario: 目录不存在
- **WHEN** 检查的目录不存在
- **THEN** 系统 SHALL 返回 `false`

---

### Requirement: 从 OpenList 下载 NFO

系统 SHALL 在本地不存在 NFO 文件时从 OpenList 同级目录下载：

1. 在目录文件列表中查找同名 NFO 文件
2. 调用 OpenList API 下载文件内容
3. 将内容保存到本地目录

#### Scenario: 查找 OpenList 中的 NFO 文件
- **WHEN** 目录文件列表包含 `movie.nfo`
- **THEN** 系统 SHALL 识别该文件为可下载的 NFO 文件

#### Scenario: 下载 NFO 文件内容
- **WHEN** 找到 OpenList 中的 NFO 文件
- **THEN** 系统 SHALL 调用 `openlistApiService.getFileContent()` 下载内容

#### Scenario: 保存下载的 NFO 文件
- **WHEN** NFO 文件下载成功
- **THEN** 系统 SHALL 将内容保存到 `{saveDirectory}/{fileName}.nfo`

#### Scenario: 处理下载失败
- **WHEN** NFO 文件下载失败
- **THEN** 系统 SHALL 返回失败状态，继续后续处理

---

### Requirement: 配置联动 - 优先使用已存在的刮削信息

系统 SHALL 根据「优先使用已存在的刮削信息」配置控制 NFO 处理：

1. 配置启用时：执行完整的三级优先级流程
2. 配置禁用时：仅检查本地文件，不执行下载和刮削

#### Scenario: 配置启用时
- **WHEN** 系统配置中 `useExistingScrapingInfo` 为 `true`
- **THEN** 系统 SHALL 执行本地检查 → OpenList 下载 → 刮削的完整流程

#### Scenario: 配置禁用时
- **WHEN** 系统配置中 `useExistingScrapingInfo` 为 `false`
- **THEN** 系统 SHALL 仅检查本地 NFO 文件，不执行下载和刮削

#### Scenario: 配置未定义
- **WHEN** 系统配置中未定义 `useExistingScrapingInfo`
- **THEN** 系统 SHALL 使用默认值 `false`

#### Scenario: 本地存在时配置不影响
- **WHEN** 本地 NFO 文件已存在
- **THEN**无论配置如何，系统 SHALL 直接使用本地文件

---

### Requirement: NFO 文件命名规则

系统 SHALL 确保 NFO 文件命名与 STRM 文件命名一致：

1. 使用与 STRM 文件相同的基础文件名
2. 将 `.strm` 扩展名替换为 `.nfo`

#### Scenario: 视频文件对应 NFO 命名
- **WHEN** 视频文件为 `movie.mp4`
- **THEN** 对应 NFO 文件 SHALL 命名为 `movie.nfo`

#### Scenario: 带重命名规则的 NFO 命名
- **WHEN** 重命名规则为 `^(.*)S\d{2}E\d{2}(.*)$|$1$2`
- **THEN** NFO 文件 SHALL 使用重命名后的基础名

#### Scenario: 剧集文件对应 NFO 命名
- **WHEN** 剧集文件为 `TV.Show.S01E01.mkv`
- **THEN** 对应 NFO 文件 SHALL 命名为 `TV.Show.S01E01.nfo`
