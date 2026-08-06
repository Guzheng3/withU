# withU 影视资源分库初始化

影视资源库与主站库分开：

- 主站库：`withu`
- 影视资源库：`withu_media`

## 1. 用 MySQL 管理员执行建库授权

```powershell
C:\WithU\tools\mysql80\mysql-8.0.28-winx64\bin\mysql.exe --host=127.0.0.1 --port=3307 --user=root --password=你的root密码 < C:\WithU\withU\deploy\init-media-db.sql
```

服务器上同理，用宝塔/终端里的 MySQL 管理员执行 `init-media-db.sql`。

## 2. 创建影视库表结构

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini C:\WithU\withU\scripts\migrate_media_db.php
```

## 3. 从 OpenList/WebDAV 导入影视资源

只入库，不提前解析直链：

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini C:\WithU\withU\scripts\import_openlist_to_media.php
```

边扫描边抓取直链：

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini C:\WithU\withU\scripts\import_openlist_to_media.php --resolve-direct
```

限量测试：

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini C:\WithU\withU\scripts\import_openlist_to_media.php --limit=100
```

限时 6 小时：

```powershell
C:\WithU\tools\php82\php.exe -c C:\WithU\dev\php.ini C:\WithU\withU\scripts\import_openlist_to_media.php --time-limit=21600
```
