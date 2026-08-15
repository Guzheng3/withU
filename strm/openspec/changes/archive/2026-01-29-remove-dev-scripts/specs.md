# 规格说明：移除冗余开发脚本

## 变更ID
`remove-dev-scripts`

## 变更类型
- [x] 功能变更
- [ ] 架构变更
- [ ] 配置变更
- [x] 文档变更

## 变更范围

### 变更内容

#### 删除文件
```
dev-start.sh      - 简单Docker启动脚本
dev-start.bat     - Windows版简单启动脚本
dev-docker.bat    - Windows版完整Docker脚本
```

#### 保留文件
```
dev-docker.sh     - 完整功能的开发环境脚本
```

#### 更新文档
```
openlist-file-tree-traversal-analysis.md - 添加处理器链章节
```

## 兼容性

### 向后兼容性
- **完全兼容**：仅删除冗余文件，不影响任何功能

### 依赖关系
- 无外部依赖变更
- 无配置文件变更

## 验收标准

- [x] dev-start.sh 已删除
- [x] dev-start.bat 已删除
- [x] dev-docker.bat 已删除
- [x] dev-docker.sh 保留且可正常运行
- [x] openlist-file-tree-traversal-analysis.md 已更新

## 风险评估

| 风险 | 可能性 | 影响 | 等级 |
|------|--------|------|------|
| 用户找不到启动方式 | 低 | 低 | 低 |
| Windows 用户无法运行 | 低 | 低 | 低 |

## 部署说明

此变更无需特殊部署步骤，仅删除文件。
