# Ostrm UI Design System

## Investment Platform Dark Mode + Data-Dense Dashboard

本文档定义了 Ostrm 应用的 UI 设计规范，参考 Investment Platform Dark Mode + Data-Dense Dashboard 风格。

---

## 1. 设计理念

### 核心原则
- **OLED 深色主题**: 纯黑背景 (#000000) 配合高对比度强调色
- **数据密集型布局**: 最大化信息密度，清晰的数据可视化
- **专业金融感**: 冷静、精确、值得信赖的视觉风格
- **高效交互**: 快速响应，减少视觉噪音

### 风格关键词
`Dark Mode` | `OLED Black` | `Data-Dense` | `Professional` | `Fintech` | `Investment Platform`

---

## 2. 色彩系统

### 主色调

| 角色 | 颜色值 | CSS 变量 | 用途 |
|------|--------|----------|------|
| 品牌主色 | `#3B82F6` | `--color-primary` | 主要操作按钮、链接、激活状态 |
| 品牌强调色 | `#8B5CF6` | `--color-accent` | 次要强调、渐变、特色元素 |
| 成功色 | `#10B981` | `--color-success` | 成功状态、完成指示 |
| 警告色 | `#F59E0B` | `--color-warning` | 警告状态、需要注意 |
| 错误色 | `#EF4444` | `--color-error` | 错误状态、危险操作 |
| 信息色 | `#06B6D4` | `--color-info` | 信息提示 |

### 深色背景层级

| 层级 | 颜色值 | CSS 变量 | 用途 |
|------|--------|----------|------|
| 背景 - 最深 | `#000000` | `--bg-deepest` | 主背景、页面底部 |
| 背景 - 深 | `#0A0A0F` | `--bg-dark` | 卡片背景、容器 |
| 背景 - 中 | `#12121A` | `--bg-medium` | 悬停状态、分隔区域 |
| 背景 - 浅 | `#1A1A24` | `--bg-light` | 输入框、表格头部 |
| 背景 - 最浅 | `#242432` | `--bg-lightest` | 悬停高亮、选中状态 |

### 边框和分割线

| 类型 | 颜色值 | CSS 变量 | 用途 |
|------|--------|----------|------|
| 分割线 - 暗 | `rgba(255,255,255,0.06)` | `--border-subtle` | 常规分隔 |
| 分割线 - 中 | `rgba(255,255,255,0.10)` | `--border-normal` | 重要分隔 |
| 分割线 - 亮 | `rgba(255,255,255,0.16)` | `--border-strong` | 高对比分隔 |

### 文字颜色

| 类型 | 颜色值 | CSS 变量 | 用途 |
|------|--------|----------|------|
| 主要文字 | `#FFFFFF` | `--text-primary` | 标题、重要内容 |
| 次要文字 | `rgba(255,255,255,0.70)` | `--text-secondary` | 正文、描述 |
| 辅助文字 | `rgba(255,255,255,0.50)` | `--text-muted` | 占位符、标签 |
| 禁用文字 | `rgba(255,255,255,0.30)` | `--text-disabled` | 禁用状态 |

---

## 3. 字体系统

### 字体族
- **主要字体**: `Inter` - 专业的现代无衬线字体
- **等宽字体**: `JetBrains Mono` - 代码、数字显示
- **系统回退**: `system-ui`, `-apple-system`, `sans-serif`

### 字号层级

| 用途 | 字号 | 行高 | 字重 | CSS 类 |
|------|------|------|------|--------|
| Display / Hero | 48px | 1.1 | 700 | `text-display` |
| H1 / 大标题 | 32px | 1.2 | 700 | `text-h1` |
| H2 / 中标题 | 24px | 1.3 | 600 | `text-h2` |
| H3 / 小标题 | 20px | 1.4 | 600 | `text-h3` |
| Body / 正文 | 14px | 1.6 | 400 | `text-body` |
| Body Small | 12px | 1.5 | 400 | `text-body-sm` |
| Caption | 11px | 1.4 | 500 | `text-caption` |
| Tag | 10px | 1.3 | 600 | `text-tag` |

### 数字字体
```css
tabular-nums {
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}
```

---

## 4. 间距系统

### 基础间距单位
- **基础单位**: 4px (0.25rem)
- **标准间距**: `4px × n`

### 间距层级

| 名称 | 值 | 用途 |
|------|-----|------|
| `space-xs` | 4px | 标签内边距、图标间距 |
| `space-sm` | 8px | 紧凑间距 |
| `space-md` | 16px | 标准间距 |
| `space-lg` | 24px | 区块间距 |
| `space-xl` | 32px | 大区块间距 |
| `space-2xl` | 48px | 页面边距 |

### 布局容器
- **最大宽度**: 1400px (`max-w-screen-2xl`)
- **标准容器**: 1280px (`max-w-7xl`)
- **窄容器**: 800px (`max-w-4xl`)

---

## 5. 组件设计

### 5.1 按钮 (Button)

#### 主要按钮
```css
.btn-primary {
  @apply px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700
         text-white font-semibold rounded-xl
         border border-blue-500/30
         shadow-lg shadow-blue-900/20
         hover:from-blue-500 hover:to-blue-600
         active:scale-[0.98]
         transition-all duration-200;
}
```

#### 次要按钮
```css
.btn-secondary {
  @apply px-6 py-3 bg-white/5
         text-white font-medium rounded-xl
         border border-white/10
         hover:bg-white/10 hover:border-white/20
         active:scale-[0.98]
         transition-all duration-200;
}
```

#### 危险按钮
```css
.btn-danger {
  @apply px-6 py-3 bg-gradient-to-r from-red-600 to-red-700
         text-white font-semibold rounded-xl
         border border-red-500/30
         shadow-lg shadow-red-900/20
         hover:from-red-500 hover:to-red-600
         active:scale-[0.98]
         transition-all duration-200;
}
```

#### 图标按钮
```css
.btn-icon {
  @apply p-2.5 rounded-xl
         text-white/70 hover:text-white
         bg-white/5 hover:bg-white/10
         border border-white/5 hover:border-white/15
         transition-all duration-200;
}
```

### 5.2 输入框 (Input)

```css
.input-field {
  @apply w-full px-4 py-3
         bg-white/5 border border-white/10
         rounded-xl text-white placeholder-white/30
         focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20
         transition-all duration-200;
}
```

### 5.3 卡片 (Card)

#### 基础卡片
```css
.card {
  @apply bg-[#0A0A0F] border border-white/6
         rounded-2xl p-6
         hover:border-white/12 hover:bg-[#0D0D14]
         transition-all duration-200;
}
```

#### 悬停效果卡片
```css
.card-hover {
  @apply hover:shadow-xl hover:shadow-black/40
         hover:-translate-y-1 cursor-pointer;
}
```

### 5.4 数据卡片 (Stat Card)

```css
.stat-card {
  @apply bg-[#0A0A0F] border border-white/6
         rounded-2xl p-6
         relative overflow-hidden;
}

.stat-card::before {
  content: '';
  @apply absolute inset-0 bg-gradient-to-br from-white/5 to-transparent opacity-0
         transition-opacity duration-300;
}

.stat-card:hover::before {
  @apply opacity-100;
}
```

### 5.5 表格 (Data Table)

```css
.data-table {
  @apply w-full border-collapse;
}

.data-table th {
  @apply px-4 py-3 text-left text-xs font-semibold text-white/50 uppercase tracking-wider
         bg-[#1A1A24] border-b border-white/6;
}

.data-table td {
  @apply px-4 py-4 text-sm text-white/90
         border-b border-white/6;
}

.data-table tr:hover td {
  @apply bg-white/5;
}
```

### 5.6 徽章 (Badge)

#### 成功徽章
```css
.badge-success {
  @apply inline-flex items-center px-2.5 py-1
         bg-emerald-500/10 text-emerald-400
         border border-emerald-500/20
         rounded-full text-xs font-semibold;
}
```

#### 警告徽章
```css
.badge-warning {
  @apply inline-flex items-center px-2.5 py-1
         bg-amber-500/10 text-amber-400
         border border-amber-500/20
         rounded-full text-xs font-semibold;
}
```

#### 错误徽章
```css
.badge-error {
  @apply inline-flex items-center px-2.5 py-1
         bg-red-500/10 text-red-400
         border border-red-500/20
         rounded-full text-xs font-semibold;
}
```

---

## 6. 导航设计

### 顶部导航栏 (Header)

```css
.header {
  @apply sticky top-0 z-50
         bg-[#000000]/80 backdrop-blur-xl
         border-b border-white/6;
  height: 64px;
}
```

### 底部导航栏 (Footer)

```css
.footer {
  @apply bg-[#000000] border-t border-white/6;
  padding: 24px 0;
}
```

---

## 7. 动画和过渡

### 过渡时长
- **快速**: 150ms - 微交互、按钮悬停
- **标准**: 200-300ms - 卡片悬停、模态框
- **慢速**: 400-500ms - 页面过渡、大型元素

### 过渡曲线
```css
/* 标准曲线 */
transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);

/* 弹性曲线 */
transition-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1);
```

### 关键动画
```css
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
```

---

## 8. 响应式设计

### 断点定义

| 断点 | 宽度 | 用途 |
|------|------|------|
| `sm` | 640px | 小型平板 |
| `md` | 768px | 平板 |
| `lg` | 1024px | 桌面 |
| `xl` | 1280px | 大型桌面 |
| `2xl` | 1536px | 超大屏幕 |

### 响应式策略
- **移动优先**: 先设计移动端，向上扩展
- **信息密度**: 根据屏幕尺寸调整内容密度
- **交互方式**: 触控 vs 指针

---

## 9. Tailwind CSS 配置

### 颜色配置 (tailwind.config.js)

```javascript
module.exports = {
  theme: {
    extend: {
      colors: {
        // 主色调
        primary: {
          DEFAULT: '#3B82F6',
          hover: '#2563EB',
          light: '#60A5FA',
          dark: '#1D4ED8',
        },
        accent: {
          DEFAULT: '#8B5CF6',
          hover: '#7C3AED',
          light: '#A78BFA',
          dark: '#6D28D9',
        },
        // 背景色
        background: {
          deepest: '#000000',
          dark: '#0A0A0F',
          medium: '#12121A',
          light: '#1A1A24',
          lightest: '#242432',
        },
        // 文字色
        content: {
          primary: '#FFFFFF',
          secondary: 'rgba(255, 255, 255, 0.70)',
          muted: 'rgba(255, 255, 255, 0.50)',
          disabled: 'rgba(255, 255, 255, 0.30)',
        },
        // 状态色
        success: '#10B981',
        warning: '#F59E0B',
        error: '#EF4444',
        info: '#06B6D4',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
    },
  },
}
```

---

## 10. 设计规范检查清单

### 视觉一致性
- [ ] 所有按钮使用统一的渐变和阴影
- [ ] 卡片背景统一使用 `#0A0A0F`
- [ ] 边框使用 `rgba(255,255,255,0.06)` 变体
- [ ] 文字颜色按层级使用对应的透明度

### 交互一致性
- [ ] 所有可点击元素有 `cursor-pointer`
- [ ] 悬停状态有明确的视觉反馈
- [ ] 过渡动画时长统一 (150-300ms)
- [ ] 点击有 `active:scale-[0.98]` 效果

### 可访问性
- [ ] 颜色对比度符合 WCAG AA 标准
- [ ] 按钮和输入框有清晰的聚焦状态
- [ ] 重要操作有明确的状态反馈

---

## 11. 页面设计指南

### 11.1 首页 (Dashboard)
- 顶部统计卡片展示关键数据
- 列表视图展示配置和任务
- 悬浮操作按钮 (FAB) 添加新配置

### 11.2 登录/注册页
- 居中表单布局
- 暗色背景配合品牌渐变
- 输入框高对比度边框

### 11.3 设置页
- 分区块卡片设计
- 清晰的分区标题
- 紧凑的表单布局

### 11.4 任务详情页
- 步骤进度指示器
- 实时日志显示区域
- 状态卡片展示关键指标

---

## 12. 资源链接

- **字体**: [Inter](https://fonts.google.com/specimen/Inter)
- **图标**: [Heroicons](https://heroicons.com/)
- **Tailwind CSS**: https://tailwindcss.com/
- **颜色工具**: https://coolors.co/

---

## 版本历史

| 版本 | 日期 | 更新内容 |
|------|------|----------|
| 2.0 | 2024-02-04 | 全新 Investment Platform Dark Mode 设计 |
| 1.0 | 2024-01-01 | 初始设计系统 |
