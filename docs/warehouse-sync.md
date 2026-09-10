# 课表数据同步与导入（qingyu_warehouse）

withU 的课表由轻屿课表 App（mikcb）修改后回传，也可以走「解析脚本仓库 + withU 脚本」链路直接导入。本文说明整条链路的用法。

## 数据流

```text
教务系统
   │  （浏览器扩展 / 解析脚本抓取）
   ▼
qingyu_warehouse（解析脚本仓库：学校索引 + *.js 适配脚本）
   │  ① sync-warehouse.php 定时拉取到 withU/runtime/qingyu-warehouse/
   ▼
课表 CSV（kcb_2026-2027-1_可导入.csv 这类解析产物）
   │  ② import-timetable.php 转成 App 回传同构 JSON 包
   ▼
timetables 表（withU 课表设置页可看；App 内可回滚）
```

- **解析脚本仓库**：`https://github.com/Guzheng3/qingyu_warehouse`（fork 自 Mutx163/qingyu_warehouse，上游为 shiguang_warehouse）。仓库里的 `resources/<学校>/adapter.js` 是各教务系统的解析脚本，`index/root_index.yaml` 是学校索引。
- **课表 CSV**：解析脚本在浏览器里跑通后导出的课程表，格式见下文。
- **导入目标**：`timetables` 表（user_id 唯一键），与 App 回传共用同一份数据。

## 1. 定时同步解析脚本仓库

后台也有可视化入口：**管理后台 → 内容管理 → 仓库同步管理**（`/admin/warehouse_sync.php`，课表设置页顶部有快捷入口），可查看同步状态、同步记录、学校与适配器列表，并点击「立即同步」手动触发一次（与定时任务共用同一个脚本）。

页面数据来源：

- 同步状态：`runtime/qingyu-warehouse-meta.json` + 仓库 HEAD（`git log -1`）；
- 同步记录：`runtime/logs/warehouse-sync.jsonl`（一行一条结构化记录：时间 / 类型 clone|pull / 提交变化 / 变更文件列表，学校列表的「最近更新」即由它汇总）；
- 学校与适配器：仓库的 `index/root_index.yaml`（191 所学校）与 `resources/<folder>/*.js` 脚本数。

### 手动/试跑

```bash
php scripts/sync-warehouse.php
```

幂等行为：

- `runtime/qingyu-warehouse/` 不存在 → 浅克隆（`--depth 1`，`--branch main`）；
- 已存在 → `fetch` + `--ff-only` 快进合并；本地有未推送提交时不改动任何文件并以非零码退出；
- 每次运行追加一行到 `runtime/logs/warehouse-sync.log`，并刷新 `runtime/qingyu-warehouse-meta.json`（`last_sync_at` / `head_sha` / `new_commits` 等，供页面或脚本读取）。

参数：

| 参数 | 说明 |
| --- | --- |
| `--repo <url\|本地路径>` | 仓库地址（默认 Guzheng3/qingyu_warehouse） |
| `--branch <名>` | 分支（默认 `main`） |
| `--dir <目录>` | 目标目录（默认 `runtime/qingyu-warehouse`） |
| `--git <路径>` | 指定 git 可执行文件（默认 PATH + Windows 常见安装位置） |
| `--upstream-report` | 额外跑仓库自带的上游同步脚本 `sync_upstream.py --dry-run`，只报告 shiguang_warehouse 上游变化，不提交不推送 |
| `--quiet` | 只写日志不打印 |

### Windows：注册任务计划（每日定时）

```bash
node scripts/register-sync-schedule.cjs            # 默认每天 04:00
node scripts/register-sync-schedule.cjs --time 03:30
node scripts/register-sync-schedule.cjs --unregister   # 卸载
```

注册的是 `withU-SyncWarehouse` 任务，命令自动带上 `deploy-local/php.ini`（启用 pdo_mysql 等扩展，与 `start-withu.cjs` 一致）。注册后可用 `schtasks /run /tn withU-SyncWarehouse` 立即试跑，或在「任务计划程序」中查看/调整。

> 若提示「拒绝访问」：需以有权限的会话（管理员或允许创建任务的用户）执行上述命令。

### Linux / WSL：crontab

```bash
crontab -e
# 每天 04:00 同步（时区跟随服务器）
0 4 * * * cd /path/to/withU && php scripts/sync-warehouse.php >> runtime/logs/warehouse-sync.log 2>&1
```

## 2. 用 withU 脚本导入课表

```bash
# 默认导入 runtime/imports/kcb_2026-2027-1_可导入.csv（可自行替换）
php scripts/import-timetable.php

# 指定文件 / 只预览不落库
php scripts/import-timetable.php --csv /path/to/课表.csv --dry-run

# 指定学期参数
php scripts/import-timetable.php --semester-start 2026-08-31 --week-count 20 --current-week 1
```

参数：

| 参数 | 说明 |
| --- | --- |
| `--csv <文件>` | 课表 CSV（默认 `runtime/imports/kcb_2026-2027-1_可导入.csv`） |
| `--users 1,2` | 只导入指定账号（默认全部启用状态的情侣账号 user1/user2） |
| `--profile 名称` | 课表名 profileName（默认 `2026-2027-1 课表`） |
| `--semester-start YYYY-MM-DD` | 开学日期（学期第 1 周周一，默认 2026-08-31） |
| `--week-count N` | 学期周数（默认 20） |
| `--current-week N` | 当前教学周（默认 1） |
| `--dry-run` | 只解析预览，不写库 |

### CSV 格式

mikcb 课程表 CSV（UTF-8 或 GBK，支持 BOM），表头按名称匹配、顺序不限：

```csv
课程名,星期,开始节,结束节,上课周,教师,教室
概率论与数理统计[29],1,3,4,1-14,韩东方,11号楼 11312
数字电子技术(B)(实验)[04],2,9,11,9-16(双),何秉姣,9号楼 S090307
```

- 列别名：课程名/课程名称、开始节/开始节数、结束节/结束节数、上课周/周数、教师/老师、教室/地点/上课地点；
- 上课周表达式：`1-14`、`14-14`、`1-3,5-8`、`9-16(双)`、`1-16(单)`（语义与 App 内 WeekExpressionParser 一致），超出学期周数自动截断并告警；
- 课程名保留 CSV 原文（含教务课程编号后缀），与在 App 内直接导入该 CSV 的结果一致。

### 导入语义（与 App 回传 / 后台导入一致）

- 内容哈希排除 `packageId` / `exportedAt` 后对规范化 JSON 做 sha256；
- 内容与账号当前课表一致 → 跳过（幂等，重复执行无副作用）；
- 有变化 → 旧内容写入 `timetable_history`（`change_type=script_import`，App 内可回滚，每账号保留最近 13 条），再覆盖 `timetables`。

## 排障

| 现象 | 处理 |
| --- | --- |
| `PHP 缺少 pdo_mysql 扩展` | 用站点 php.ini 运行：`php -c deploy-local/php.ini scripts/import-timetable.php`（Windows 任务计划已自动带上） |
| `Base table ... doesn't exist`（timetable_history 等） | 库结构落后于代码；脚本会自动调用站点同款幂等迁移 `migrate_schema_if_needed()`。若仍缺失，检查 `backend/runtime/schema-version` 标记是否来自另一个数据库实例（双栈 3306/3307 切换会导致标记与库不一致），删除标记后重跑 |
| 同步报「快进合并失败」 | `runtime/qingyu-warehouse/` 里有本地未推送提交；先处理该仓库的本地改动再同步 |
| `git fetch` 失败 | 网络问题，定时任务下次会自动重试，不影响已同步内容 |
