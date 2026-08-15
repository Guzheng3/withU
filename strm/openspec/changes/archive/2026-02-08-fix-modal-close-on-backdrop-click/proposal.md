## Why

编辑 OpenList 配置弹窗、创建任务弹窗、编辑任务弹窗目前存在点击遮罩层（弹窗范围外）时会自动关闭的问题。用户希望禁止此行为，只允许通过点击关闭按钮或弹窗内的取消按钮来关闭弹窗，以避免误操作导致表单数据丢失。

## What Changes

- **移除遮罩层点击关闭行为**：移除所有弹窗 overlay 上的 `@click="closeXXX"` 事件绑定
- **保留弹窗内点击停止冒泡**：保持弹窗内容区的 `@click.stop` 防止事件冒泡到遮罩层

## Capabilities

### New Capabilities
- 无（此修复不涉及新功能）

### Modified Capabilities
- 无（此修复不涉及需求变更，只是实现修复）

## Impact

- **受影响文件**:
  - `frontend/app/pages/index.vue` - 创建/编辑 OpenList 配置弹窗
  - `frontend/app/pages/task-management/[id].vue` - 创建/编辑/执行任务弹窗
