# Frontend Core Layer

## Description

核心层包含技术基础设施，为所有业务模块提供通用能力。

## Directory Structure

```
app/
└── core/
    ├── api/                    # HTTP 客户端封装
    │   ├── client.ts           # 基础 ofetch 配置
    │   ├── interceptors/       # 请求/响应拦截器
    │   │   ├── request.ts      # 请求拦截器
    │   │   └── error.ts        # 错误拦截器
    │   └── types/              # API 相关类型定义
    │       └── api.types.ts
    ├── ui/                     # 基础 UI 组件库
    │   ├── BaseButton.vue
    │   ├── BaseInput.vue
    │   └── BaseModal.vue
    ├── utils/                  # 通用工具函数
    │   ├── validation/         # 校验工具
    │   │   └── validators.ts
    │   ├── formatters/         # 格式化工具
    │   │   └── formatters.ts
    │   └── helpers/            # 辅助函数
    │       └── helpers.ts
    ├── constants/              # 全局常量
    │   └── index.ts
    └── types/                  # 全局 TypeScript 类型定义
        └── global.d.ts
```

## Requirements

### API Client

1. **基础配置**
   - 使用 ofetch 作为 HTTP 客户端
   - 自动注入 Bearer Token
   - 支持请求/响应拦截器
   - 环境变量配置 API 基础路径

2. **错误处理**
   - 定义 ApiError 异常类
   - 401 Token 过期自动刷新
   - 422 表单验证错误透传
   - 429 请求限流提示

### Utility Functions

1. **校验工具**
   - 通用正则校验
   - 表单验证辅助函数
   - 数据清洗工具

2. **格式化工具**
   - 日期格式化
   - 文件大小格式化
   - 数字格式化

## Files to Create

- `app/core/api/client.ts`
- `app/core/api/interceptors/request.ts`
- `app/core/api/interceptors/error.ts`
- `app/core/api/types/api.types.ts`
- `app/core/utils/validation/validators.ts`
- `app/core/utils/formatters/formatters.ts`
- `app/core/utils/helpers/helpers.ts`
- `app/core/constants/index.ts`
- `app/core/types/global.d.ts`
