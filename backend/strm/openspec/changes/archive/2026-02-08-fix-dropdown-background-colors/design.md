## Context

当前项目使用深色主题（`bg-[#1A1A24]` 等深色背景），`.input-field` 类为 `input` 和 `select` 元素定义了统一样式：
```css
.input-field {
  @apply w-full px-4 py-3
         bg-white/5 border border-white/10
         rounded-xl text-white placeholder-white/30
         focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20
         transition-all duration-200;
}
```

问题在于原生 `<select>` 元素在不同浏览器中可能保留浏览器默认样式（白色背景），导致：
- 下拉箭头区域显示为白色背景
- 选项文字在某些浏览器中不可见
- 与深色主题不一致

## Goals / Non-Goals

**Goals:**
- 修复所有 `<select>` 下拉选框在深色主题下的背景和文字颜色问题
- 确保下拉箭头图标颜色正确（白色）
- 保持与项目现有深色主题风格一致
- 覆盖所有使用 `input-field` 类的 `<select>` 元素

**Non-Goals:**
- 不引入第三方拉选框组件下（如 `vue-select`）
- 不修改 `<input>` 文本输入框样式
- 不改变现有的布局和功能

## Decisions

### 1. CSS 选择器策略

**决策**：为 `.input-field` 添加 `<select>` 元素专用的样式覆盖

**方案 A - 使用 Tailwind 表单插件**
```css
@apply appearance-none bg-[url(...)] bg-no-repeat bg-right pr-10
```
优点：使用 Tailwind 现有工具类
缺点：需要额外的图标资源

**方案 B - 使用原生 CSS 选择器和自定义箭头**
```css
.input-field,
select.input-field {
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.75rem center;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}
```
优点：内联 SVG 无需额外资源，完全控制样式，与项目风格一致
缺点：需要添加额外的 CSS 规则

**最终选择**：方案 B - 使用内联 SVG 箭头，完全控制样式

### 2. 下拉箭头样式

- 颜色：白色（`stroke='%23ffffff'`）
- 尺寸：1.5em
- 位置：右侧居中
- 样式：简洁的下箭头 SVG

## Risks / Trade-offs

| 风险 | 缓解措施 |
|------|----------|
| 浏览器兼容性 | 使用标准的 `appearance: none` 和 SVG 方案，兼容性良好 |
| 高对比度模式 | 保持与现有深色主题一致，不影响辅助功能 |
| 主题变更 | 使用 CSS 变量或 Tailwind 工具类，便于后续主题调整 |
