-- 媒体库条目唯一约束改为 (openlist_config_id, source_path)
-- 目的：
--   1. 去重：同一 OpenList 源下同一路径只保留一条记录（此前 (task_id, source_path) 会被多个任务重复收录）
--   2. 支持多来源：不同 OpenList 源的相同路径仍各自保留，作为同一集的多个播放来源
-- SQLite 不支持 DROP CONSTRAINT，采用 重建表 方式迁移
CREATE TABLE media_library_item_new
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    openlist_config_id INTEGER NOT NULL,
    source_path VARCHAR(2000) NOT NULL,
    strm_path VARCHAR(2000) NOT NULL,
    source_file_name VARCHAR(500) NOT NULL,
    media_type VARCHAR(20) NOT NULL DEFAULT 'movie',
    tmdb_id INTEGER,
    title VARCHAR(500) NOT NULL,
    original_title VARCHAR(500),
    release_year VARCHAR(4),
    overview TEXT,
    poster_url VARCHAR(2000),
    backdrop_url VARCHAR(2000),
    vote_average REAL,
    scrape_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (openlist_config_id, source_path)
);

-- 迁移数据：同一 (openlist_config_id, source_path) 只保留 id 最小的一条
INSERT INTO media_library_item_new (
    id, task_id, openlist_config_id, source_path, strm_path, source_file_name, media_type,
    tmdb_id, title, original_title, release_year, overview, poster_url, backdrop_url,
    vote_average, scrape_status, created_at, updated_at
)
SELECT id, task_id, openlist_config_id, source_path, strm_path, source_file_name, media_type,
       tmdb_id, title, original_title, release_year, overview, poster_url, backdrop_url,
       vote_average, scrape_status, created_at, updated_at
FROM media_library_item
WHERE id IN (
    SELECT MIN(id) FROM media_library_item GROUP BY openlist_config_id, source_path
);

DROP TABLE media_library_item;

ALTER TABLE media_library_item_new RENAME TO media_library_item;

CREATE INDEX idx_media_library_task ON media_library_item(task_id);
CREATE INDEX idx_media_library_openlist ON media_library_item(openlist_config_id);
CREATE INDEX idx_media_library_type ON media_library_item(media_type);
CREATE INDEX idx_media_library_tmdb ON media_library_item(tmdb_id);
CREATE INDEX idx_media_library_updated ON media_library_item(updated_at);

CREATE TRIGGER update_media_library_item_updated_at
    AFTER UPDATE ON media_library_item
    FOR EACH ROW
BEGIN
    UPDATE media_library_item SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;
