-- 添加auth_type字段用于OpenList认证方式
-- token: Token认证（默认，向后兼容）
-- password: 账号密码认证
ALTER TABLE openlist_config ADD COLUMN auth_type VARCHAR(20) DEFAULT 'token';
