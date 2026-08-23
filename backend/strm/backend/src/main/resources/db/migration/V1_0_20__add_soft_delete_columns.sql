-- 账号软删除与延迟物理清理支持
-- 目的：
--   1. openlist_config.deleted_at：账号删除时软删除配置，7 天内重新添加同账号可取消删除计划恢复
--   2. media_library_item.deleted_at：账号删除时立即软删除媒体库条目，从媒体库隐藏
-- 到期后由定时任务物理删除 STRM 文件、元数据与相关记录
ALTER TABLE openlist_config ADD COLUMN deleted_at TIMESTAMP;

ALTER TABLE media_library_item ADD COLUMN deleted_at TIMESTAMP;

CREATE INDEX idx_media_library_deleted ON media_library_item(deleted_at);
