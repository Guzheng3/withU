# subtitle-file-management Specification

## Purpose
TBD - created by archiving change optimize-file-processing-pipeline. Update Purpose after archive.
## Requirements
### Requirement: 字幕文件优先级处理

系统 SHALL 实现三级优先级处理字幕文件：

1. **优先级 1 - 本地文件**：检查本地是否存在对应字幕文件
2. **优先级 2 - OpenList 文件**：本地不存在时从 OpenList 同级目录下载
3. **优先级 3 - 无刮削选项**：字幕文件不支持 API 刮削，仅支持本地和 OpenList

#### Scenario: 本地字幕文件已存在
- **WHEN** 目标目录存在 `{baseFileName}.srt` 文件
- **THEN** 系统 SHALL 跳过字幕下载，使用本地文件

#### Scenario: 多种字幕格式存在性检查
- **WHEN** 目标目录存在 `.srt`、`.ass`、`.vtt` 中的任意一种
- **THEN** 系统 SHALL 识别字幕文件已存在

#### Scenario: 本地不存在但 OpenList 存在
- **WHEN** 本地不存在字幕文件且 OpenList 同级目录存在同名字幕
- **THEN** 系统 SHALL 从 OpenList 下载字幕文件并保存到本地

#### Scenario: OpenList 不存在时
- **WHEN** OpenList 同级目录不存在字幕文件
- **THEN** 系统 SHALL 跳过字幕处理，不执行任何操作

---

### Requirement: 支持的字幕格式

系统 SHALL 支持以下格式的字幕文件：

1. `.srt` - SubRip 字幕格式
2. `.ass` - Advanced SubStation Alpha 格式
3. `.vtt` - WebVTT 字幕格式
4. `.ssa` - SubStation Alpha 格式
5. `.sub` - MicroDVD 字幕格式
6. `.idx` - VobSub 字幕格式

#### Scenario: 识别 SRT 字幕
- **WHEN** 文件扩展名为 `.srt`
- **THEN** 系统 SHALL 识别该文件为支持的字幕格式

#### Scenario: 识别 ASS 字幕
- **WHEN** 文件扩展名为 `.ass`
- **THEN** 系统 SHALL 识别该文件为支持的字幕格式

#### Scenario: 识别 VTT 字幕
- **WHEN** 文件扩展名为 `.vtt`
- **THEN** 系统 SHALL 识别该文件为支持的字幕格式

#### Scenario: 多种字幕格式共存
- **WHEN** 目录中存在多种格式的同名字幕文件
- **THEN** 系统 SHALL 按优先级 `.ass` > `.ssa` > `.srt` > `.vtt` > `.sub` > `.idx` 选择使用

---

### Requirement: 本地字幕文件检查

系统 SHALL 提供 `isLocalSubtitleExists()` 方法检查本地字幕文件是否存在：

1. 检查保存目录是否存在
2. 检查所有支持的字幕格式文件
3. 返回存在的字幕文件信息

#### Scenario: 检查 SRT 文件存在
- **WHEN** 目录 `/strm/movies/` 存在 `movie.srt`
- **THEN** 调用 `isLocalSubtitleExists("/strm/movies/", "movie")` SHALL 返回 `true`

#### Scenario: 检查 ASS 文件存在
- **WHEN** 目录 `/strm/movies/` 存在 `movie.ass`
- **THEN** 调用 `isLocalSubtitleExists("/strm/movies/", "movie")` SHALL 返回 `true`

#### Scenario: 多种格式存在
- **WHEN** 目录同时存在 `movie.srt` 和 `movie.ass`
- **THEN** 系统 SHALL 识别为字幕文件已存在，并记录存在的格式

#### Scenario: 目录不存在
- **WHEN** 检查的目录不存在
- **THEN** 系统 SHALL 返回 `false`

---

### Requirement: 从 OpenList 下载字幕

系统 SHALL 在本地不存在字幕文件时从 OpenList 同级目录下载：

1. 在目录文件列表中查找字幕文件
2. 匹配与视频文件同名（去除扩展名）的字幕文件
3. 调用 OpenList API 下载文件内容
4. 将内容保存到本地目录

#### Scenario: 查找 OpenList 中的字幕文件
- **WHEN** 目录文件列表包含 `movie.srt`
- **THEN** 系统 SHALL 识别该文件为可下载的字幕文件

#### Scenario: 下载字幕文件内容
- **WHEN** 找到 OpenList 中的字幕文件
- **THEN** 系统 SHALL 调用 `openlistApiService.getFileContent()` 下载内容

#### Scenario: 保存下载的字幕文件
- **WHEN** 字幕文件下载成功
- **THEN** 系统 SHALL 将内容保存到 `{saveDirectory}/{fileName}.{ext}`

#### Scenario: 处理下载失败
- **WHEN** 字幕文件下载失败
- **THEN** 系统 SHALL 记录日志并跳过字幕处理

---

### Requirement: 防止重复下载字幕

系统 SHALL 防止同一字幕文件被多次下载：

1. 下载前检查本地是否已存在
2. 下载成功后记录已下载的文件列表
3. 后续处理时跳过已下载的文件

#### Scenario: 目录内多个视频共享字幕
- **WHEN** 目录中有 `movie1.mp4`、`movie2.mp4` 和 `movie.srt`
- **THEN** 处理 `movie1.mp4` 时下载 `movie.srt`
- **AND** 处理 `movie2.mp4` 时跳过已存在的 `movie.srt`

#### Scenario: 记录已下载的字幕
- **WHEN** 字幕文件下载成功后
- **THEN** 系统 SHALL 将该文件添加到已下载列表

#### Scenario: 检查已下载列表
- **WHEN** 处理下一个视频文件时
- **THEN** 系统 SHALL 检查字幕是否在已下载列表中

#### Scenario: 日志记录下载跳过
- **WHEN** 字幕文件已存在，跳过下载
- **THEN** 系统 SHALL 记录 DEBUG 日志

---

### Requirement: 配置联动 - 保留字幕文件

系统 SHALL 根据「保留字幕文件」配置控制字幕处理：

1. 配置启用时：执行本地检查 → OpenList 下载的流程
2. 配置禁用时：跳过所有字幕处理

#### Scenario: 配置启用时
- **WHEN** 系统配置中 `keepSubtitleFiles` 为 `true`
- **THEN** 系统 SHALL 执行本地检查 → OpenList 下载的流程

#### Scenario: 配置禁用时
- **WHEN** 系统配置中 `keepSubtitleFiles` 为 `false`
- **THEN** 系统 SHALL 跳过所有字幕处理操作

#### Scenario: 配置未定义
- **WHEN** 系统配置中未定义 `keepSubtitleFiles`
- **THEN** 系统 SHALL 使用默认值 `false`

#### Scenario: 本地存在时配置不影响
- **WHEN** 本地字幕文件已存在
- **THEN** 无论配置如何，系统 SHALL 直接使用本地文件

