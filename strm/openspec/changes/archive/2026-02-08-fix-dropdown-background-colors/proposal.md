## Why

日志页面和其他页面中使用的原生 `<select>` 下拉选框在深色主题下存在样式问题。`.input-field` 类设置了 `bg-white/5` 和 `text-white`，但浏览器原生的 `<select>` 控件样式可能在某些浏览器中显示为白色背景和文字，导致用户无法看清选项。需要统一修复所有使用 `<select>` 元素的下拉选框样式，确保在深色主题下正常显示。

## What Changes

1. **增强 `.input-field` 样式**：为 `<select>` 元素添加浏览器原生控件样式覆盖，确保深色背景和白色文字正确显示
2. **修复日志页面下拉选框**：
   - 日志类型选择下拉框
   - 日志级别筛选下拉框
3. **修复设置页面的下拉选框**：
   - TMDB 语言选择
   - TMDB 地区选择
   - 日志保留天数选择
   - 日志级别选择
4. **检查并修复其他页面的类似问题**

## Capabilities

### New Capabilities
- `dropdown-select-styles`: 为项目中的所有原生 `<select>` 元素定义统一的深色主题样式规范

### Modified Capabilities
- 无（这是 UI 样式修复，不涉及功能需求变更）

## Impact

- **受影响文件**：
  - `frontend/app/assets/css/main.css` - `.input-field` 样式定义
  - `frontend/app/pages/logs/index.vue` - 日志页面下拉选框
  - `frontend/app/pages/settings/index.vue` - 设置页面的下拉选框
- **无功能变更**：仅样式调整，不影响业务逻辑
- **无依赖变更**：不需要引入新依赖
