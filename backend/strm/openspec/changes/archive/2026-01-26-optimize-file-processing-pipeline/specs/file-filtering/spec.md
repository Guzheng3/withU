# 文件过滤规格说明

## ADDED Requirements

### Requirement: 视频文件识别

系统 SHALL 识别并过滤视频文件，支持以下扩展名：

1. 常见视频格式：`.mp4`、`.mkv`、`.avi`、`.mov`、`.wmv`、`.flv`、`.webm`、`.m4v`
2. 蓝光光盘格式：`.m2ts`、`.ts`
3. 其他格式：`.rmvb`、`.rm`、`.3gp`、`.mpeg`、`.mpg`

#### Scenario: 识别 MP4 文件
- **WHEN** 文件扩展名为 `.mp4`
- **THEN** 系统 SHALL 识别该文件为视频文件

#### Scenario: 识别 MKV 文件
- **WHEN** 文件扩展名为 `.mkv`
- **THEN** 系统 SHALL 识别该文件为视频文件

#### Scenario: 识别蓝光格式
- **WHEN** 文件扩展名为 `.m2ts` 或 `.ts`
- **THEN** 系统 SHALL 识别该文件为视频文件

#### Scenario: 忽略隐藏文件
- **WHEN** 文件名以 `.` 开头
- **THEN** 系统 SHALL 跳过该文件不进行识别

---

### Requirement: 视频文件过滤

系统 SHALL 在文件处理管道中过滤出视频文件，只将视频文件传递给后续处理器：

1. 检查每个文件的类型和扩展名
2. 过滤出类型为 "file" 且扩展名为视频格式的文件
3. 跳过目录、非视频文件和隐藏文件

#### Scenario: 过滤视频文件
- **WHEN** 目录包含视频文件和非视频文件
- **THEN** 系统 SHALL 只将视频文件传递给后续处理器

#### Scenario: 跳过目录
- **WHEN** 处理对象是目录
- **THEN** 系统 SHALL 跳过目录，让文件发现处理器递归处理

#### Scenario: 跳过非视频文件
- **WHEN** 文件扩展名不在视频格式列表中
- **THEN** 系统 SHALL 跳过该文件

#### Scenario: 跳过字幕文件
- **WHEN** 文件扩展名为 `.srt`、`.ass` 等字幕格式
- **THEN** 系统 SHALL 跳过该文件，由字幕处理器单独处理

---

### Requirement: 文件类型判断方法

系统 SHALL 提供 `isVideoFile()` 方法用于判断文件是否为视频文件：

1. 接受文件名或文件路径作为输入
2. 提取文件扩展名（不区分大小写）
3. 与预定义的视频扩展名列表进行匹配

#### Scenario: 根据文件名判断
- **WHEN** 调用 `isVideoFile("movie.mp4")`
- **THEN** 系统 SHALL 返回 `true`

#### Scenario: 根据路径判断
- **WHEN** 调用 `isVideoFile("/path/to/movie.mkv")`
- **THEN** 系统 SHALL 返回 `true`

#### Scenario: 非视频文件判断
- **WHEN** 调用 `isVideoFile("document.txt")`
- **THEN** 系统 SHALL 返回 `false`

#### Scenario: 扩展名大小写不敏感
- **WHEN** 调用 `isVideoFile("movie.MP4")`
- **THEN** 系统 SHALL 返回 `true`

---

### Requirement: 批量文件过滤

系统 SHALL 支持批量过滤目录中的所有文件：

1. 接收文件列表作为输入
2. 过滤出所有视频文件
3. 返回过滤后的视频文件列表

#### Scenario: 批量过滤视频文件
- **WHEN** 传入包含 10 个文件的列表，其中 6 个视频文件
- **THEN** 系统 SHALL 返回包含 6 个视频文件的列表

#### Scenario: 过滤结果顺序保持
- **WHEN** 批量过滤文件
- **THEN** 过滤后的文件列表 SHALL 保持原始顺序

#### Scenario: 空列表处理
- **WHEN** 传入空文件列表
- **THEN** 系统 SHALL 返回空列表

#### Scenario: 全非视频文件处理
- **WHEN** 传入的文件列表中无视频文件
- **THEN** 系统 SHALL 返回空列表
