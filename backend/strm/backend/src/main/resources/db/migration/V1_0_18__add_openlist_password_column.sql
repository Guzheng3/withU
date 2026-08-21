-- 添加password字段用于OpenList自动刷新Token
-- 仅在 auth_type=password 时存储，用于token过期后自动登录换取新token
ALTER TABLE openlist_config ADD COLUMN password VARCHAR(500);
