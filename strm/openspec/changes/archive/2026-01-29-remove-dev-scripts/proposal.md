# 移除冗余开发脚本

## 变更原因

项目目前存在多个开发启动脚本，功能重叠且维护困难：
- `dev-docker.sh` - 完整的开发环境脚本（10,780 字节）
- `dev-start.sh` - 简单启动脚本（5,481 字节）
- `dev-docker.bat` - Windows 版完整脚本（8,798 字节）
- `dev-start.bat` - Windows 版简单脚本（4,609 字节）

`dev-docker.sh` 已具备完整功能，其他脚本冗余且容易造成用户困惑。

## 变更内容

1. **删除文件**：
   - `dev-start.sh` - 功能被 dev-docker.sh 完全覆盖
   - `dev-start.bat` - 功能被 dev-docker.sh 完全覆盖
   - `dev-docker.bat` - Windows 脚本冗余，dev-docker.sh 在 Git Bash 中可正常工作

2. **保留文件**：
   - `dev-docker.sh` - 唯一开发脚本，支持所有功能

3. **更新文档**：
   - `CLAUDE.md` - 翻译为中文，添加文件处理器链说明
   - `openlist-file-tree-traversal-analysis.md` - 添加处理器链章节

## 影响评估

### 风险
- **低**：GitHub Actions 不使用这些脚本
- **低**：用户可以通过 `./dev-docker.sh --help` 获得完整命令列表

### 兼容性
- `dev-docker.sh` 在 Linux/macOS/Git Bash 中均可正常运行
- 保留了开发环境的完整功能

## 回滚方案

如需回滚，可从 git 历史中恢复已删除的文件：
```bash
git checkout HEAD -- dev-start.sh dev-start.bat dev-docker.bat
```

## 完成标准

- [x] 删除 dev-start.sh
- [x] 删除 dev-start.bat
- [x] 删除 dev-docker.bat
- [x] 保留 dev-docker.sh
- [x] 更新 CLAUDE.md（中文）
- [x] 更新 openlist-file-tree-traversal-analysis.md
