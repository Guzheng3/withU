# 孤立文件清理规格说明

## ADDED Requirements

### Requirement: 孤立文件识别

系统 SHALL 识别增量模式下不再存在于 OpenList 中的文件：

1. 对比 OpenList 文件列表和本地 STRM 目录
2. 识别本地存在但 OpenList 不存在的 STRM 文件
3. 识别本地存在但 OpenList 不存在的刮削文件（NFO、图片）

#### Scenario: 识别孤立 STRM 文件
- **WHEN** OpenList 中不存在 `{baseName}.mp4` 但本地存在 `{baseName}.strm`
- **THEN** 系统 SHALL 识别该 STRM 文件为孤立文件

#### Scenario: 识别孤立 NFO 文件
- **WHEN** 对应的视频文件被删除
- **THEN** 系统 SHALL 识别 `{baseName}.nfo` 为孤立文件

#### Scenario: 识别孤立图片文件
- **WHEN** 对应的视频文件被删除
- **THEN** 系统 SHALL 识别 `{baseName}-poster.jpg` 等图片文件为孤立文件

#### Scenario: 识别孤立字幕文件
- **WHEN** 对应的视频文件被删除
- **THEN** 系统 SHALL 识别 `{baseName}.srt` 等字幕文件为孤立文件

---

### Requirement: 孤立文件清理流程

系统 SHALL 在增量模式下清理识别出的孤立文件：

1. 深度优先遍历 STRM 目录
2. 对于每个孤立文件，执行清理操作
3. 清理关联的刮削文件
4. 清理空目录

#### Scenario: 删除孤立 STRM 文件
- **WHEN** 识别到孤立 STRM 文件
- **THEN** 系统 SHALL 删除该 STRM 文件

#### Scenario: 清理关联的刮削文件
- **WHEN** 删除孤立 STRM 文件时
- **THEN** 系统 SHALL 同时删除关联的 NFO、图片、字幕文件

#### Scenario: 清理空目录
- **WHEN** 目录中所有文件都被删除
- **THEN** 系统 SHALL 删除空目录

#### Scenario: 保留非孤立文件
- **WHEN** 文件对应的源文件仍然存在
- **THEN** 系统 SHALL 保留该文件及其关联文件

---

### Requirement: 深度优先遍历

系统 SHALL 使用深度优先策略遍历 STRM 目录：

1. 先递归处理子目录
2. 再处理当前目录的文件
3. 最后检查是否需要删除空目录

#### Scenario: 先处理子目录
- **WHEN** STRM 目录包含子目录
- **THEN** 系统 SHALL 先递归处理子目录中的文件

#### Scenario: 处理当前目录文件
- **WHEN** 子目录处理完成
- **THEN** 系统 SHALL 处理当前目录的文件

#### Scenario: 检查空目录
- **WHEN** 当前目录文件处理完成
- **THEN** 系统 SHALL 检查目录是否为空

#### Scenario: 深度优先顺序
- **WHEN** 目录结构为 `/strm/a/b/` 和 `/strm/a/c/`
- **THEN** 处理顺序 SHALL 为 `/strm/a/b/` → `/strm/a/c/` → `/strm/a/`

---

### Requirement: 关联文件清理

系统 SHALL 清理与孤立 STRM 文件关联的所有文件：

1. NFO 文件
2. 海报文件
3. 背景图文件
4. 剧集缩略图文件
5. 字幕文件
6. 电视剧共用文件（tvshow.nfo、poster.jpg、fanart.jpg）

#### Scenario: 清理电影关联文件
- **WHEN** 删除孤立电影 STRM 文件
- **THEN** 系统 SHALL 同时删除 `{baseName}.nfo`、`{baseName}-poster.jpg`、`{baseName}-fanart.jpg`

#### Scenario: 清理剧集关联文件
- **WHEN** 删除孤立剧集 STRM 文件
- **THEN** 系统 SHALL 同时删除 `{baseName}.nfo`、`{baseName}-thumb.jpg`

#### Scenario: 清理电视剧共用文件
- **WHEN** 剧集目录为空时
- **THEN** 系统 SHALL 删除 `tvshow.nfo`、`poster.jpg`、`fanart.jpg`

#### Scenario: 检查目录中其他视频
- **WHEN** 删除剧集文件前
- **THEN** 系统 SHALL 检查目录中是否还有其他视频文件

---

### Requirement: 文件存在性验证

系统 SHALL 在清理前验证文件是否真的孤立：

1. 验证 STRM 文件对应的源文件是否存在于 OpenList
2. 验证 NFO 文件名是否与某个视频文件对应
3. 考虑重命名规则的影响

#### Scenario: 基础文件名匹配
- **WHEN** STRM 文件名为 `movie.strm`
- **THEN** 系统 SHALL 检查 OpenList 中是否存在 `movie.mp4` 或 `movie.mkv`

#### Scenario: 重命名规则反向匹配
- **WHEN** 任务配置了重命名规则
- **THEN** 系统 SHALL 尝试反向匹配以正确识别文件

#### Scenario: 部分匹配
- **WHEN** OpenList 文件名与 STRM 文件名部分匹配
- **THEN** 系统 SHALL 根据相似度判断是否孤立

#### Scenario: 匹配失败保留文件
- **WHEN** 无法确定文件是否孤立
- **THEN** 系统 SHALL 保留该文件以避免误删

---

### Requirement: 清理统计报告

系统 SHALL 记录清理操作的统计信息：

1. 清理的 STRM 文件数量
2. 清理的 NFO 文件数量
3. 清理的图片文件数量
4. 清理的字幕文件数量
5. 删除的空目录数量

#### Scenario: 统计清理结果
- **WHEN** 清理操作完成后
- **THEN** 系统 SHALL 记录各类文件的清理数量

#### Scenario: 日志输出统计
- **WHEN** 清理操作完成
- **THEN** 系统 SHALL 输出类似「清理了 5 个孤立 STRM 文件」的日志

#### Scenario: 无孤立文件
- **WHEN** 没有发现孤立文件
- **THEN** 系统 SHALL 记录「未发现孤立文件」的日志

#### Scenario: 清理失败处理
- **WHEN** 清理某个文件失败
- **THEN** 系统 SHALL 记录错误并继续清理其他文件

---

### Requirement: 清理条件控制

系统 SHALL 根据配置控制清理操作：

1. 仅在增量模式下执行清理
2. 清理前验证任务配置
3. 支持清理开关配置

#### Scenario: 仅增量模式清理
- **WHEN** 任务以全量模式执行
- **THEN** 系统 SHALL 跳过孤立文件清理

#### Scenario: 任务配置验证
- **WHEN** 任务配置无效或任务已禁用
- **THEN** 系统 SHALL 跳过清理操作

#### Scenario: 清理开关配置
- **WHEN** 系统配置中清理功能禁用
- **THEN** 系统 SHALL 跳过清理操作

#### Scenario: 安全第一原则
- **WHEN** 无法确定文件是否孤立
- **THEN** 系统 SHALL 保留文件而不是误删
