# remove-scraping-config-options Tasks

## 1. 后端修改

- [ ] 1.1 修改 `SystemConfigService.java` - 移除 `generateNfo`、`downloadPoster`、`downloadBackdrop`、`overwriteExisting` 配置项定义
- [ ] 1.2 修改 `MediaScrapingService.java` - 移除配置读取逻辑，改为直接执行
- [ ] 1.3 修改 `CoverImageService.java` - 移除配置读取逻辑，改为始终下载
- [ ] 1.4 修改 `NfoGeneratorService.java` - 移除 `overwriteExisting` 判断
- [ ] 1.5 修改 `MediaScrapingHandler.java` - 移除 `generateNfo` 配置使用

## 2. 前端修改

- [ ] 2.1 修改 `settings.vue` - 移除4个 checkbox UI（generateNfo、downloadPoster、downloadBackdrop、overwriteExisting）
- [ ] 2.2 修改 `settings.vue` - 移除保存逻辑中对应字段的发送

## 3. 验证

- [ ] 3.1 构建 Docker 镜像确认无编译错误
- [ ] 3.2 验证配置页面 UI 显示正常
- [ ] 3.3 验证刮削功能正常运行
