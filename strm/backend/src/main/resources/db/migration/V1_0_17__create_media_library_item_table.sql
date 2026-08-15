CREATE TABLE media_library_item
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
    UNIQUE (task_id, source_path)
);

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
