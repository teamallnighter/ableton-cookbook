# Ableton Cookbook Database Schema Design

## Core Tables

### users
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NULL,
    bio TEXT NULL,
    location VARCHAR(100) NULL,
    website VARCHAR(255) NULL,
    avatar_path VARCHAR(500) NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    followers_count INT DEFAULT 0,
    following_count INT DEFAULT 0,
    workflows_count INT DEFAULT 0,
    last_active_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_is_active (is_active),
    INDEX idx_last_active (last_active_at),
    INDEX idx_created_at (created_at)
);
```

### workflows
```sql
CREATE TABLE workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    rack_type ENUM('audio_effect', 'instrument', 'midi_effect') NOT NULL,
    genre VARCHAR(100) NULL,
    bpm INT NULL,
    key_signature VARCHAR(10) NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'intermediate',
    
    -- File paths
    adg_file_path VARCHAR(500) NOT NULL,
    preview_audio_path VARCHAR(500) NULL,
    preview_image_path VARCHAR(500) NULL,
    
    -- Engagement metrics
    downloads_count INT DEFAULT 0,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    ratings_count INT DEFAULT 0,
    
    -- Metadata
    file_size INT NULL, -- in bytes
    ableton_version VARCHAR(20) NULL,
    devices_used JSON NULL, -- Array of device names used in rack
    macro_controls JSON NULL, -- Macro control information
    
    -- Status
    is_published BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_rack_type (rack_type),
    INDEX idx_genre (genre),
    INDEX idx_published (is_published, published_at),
    INDEX idx_featured (is_featured),
    INDEX idx_average_rating (average_rating),
    INDEX idx_downloads (downloads_count),
    INDEX idx_created_at (created_at),
    INDEX idx_slug (slug),
    FULLTEXT idx_search (title, description)
);
```

### workflow_tags
```sql
CREATE TABLE workflow_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_usage_count (usage_count)
);
```

### workflow_tag_pivot
```sql
CREATE TABLE workflow_tag_pivot (
    workflow_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (workflow_id, tag_id),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES workflow_tags(id) ON DELETE CASCADE
);
```

## Social Features Tables

### user_follows
```sql
CREATE TABLE user_follows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id BIGINT UNSIGNED NOT NULL,
    following_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_follower (follower_id),
    INDEX idx_following (following_id),
    INDEX idx_created_at (created_at)
);
```

### workflow_ratings
```sql
CREATE TABLE workflow_ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT NULL,
    is_recommended BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_rating (workflow_id, user_id),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
);
```

### workflow_comments
```sql
CREATE TABLE workflow_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL, -- For nested comments
    content TEXT NOT NULL,
    likes_count INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES workflow_comments(id) ON DELETE CASCADE,
    
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_created_at (created_at)
);
```

### workflow_likes
```sql
CREATE TABLE workflow_likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_like (workflow_id, user_id),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

### comment_likes
```sql
CREATE TABLE comment_likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_comment_like (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES workflow_comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id)
);
```

## Activity & Feed Tables

### user_activities
```sql
CREATE TABLE user_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_type ENUM('workflow_upload', 'workflow_like', 'workflow_comment', 'user_follow', 'workflow_rating') NOT NULL,
    activity_data JSON NULL, -- Store related IDs and metadata
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
);
```

### user_feed_cache
```sql
CREATE TABLE user_feed_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    activity_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_activity_feed (user_id, activity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES user_activities(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

## Analytics & Metrics Tables

### workflow_downloads
```sql
CREATE TABLE workflow_downloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL, -- NULL for anonymous downloads
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

### workflow_views
```sql
CREATE TABLE workflow_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
);
```

## Collections & Playlists

### workflow_collections
```sql
CREATE TABLE workflow_collections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_public BOOLEAN DEFAULT TRUE,
    workflows_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_is_public (is_public),
    INDEX idx_created_at (created_at)
);
```

### collection_workflows
```sql
CREATE TABLE collection_workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection_id BIGINT UNSIGNED NOT NULL,
    workflow_id BIGINT UNSIGNED NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_collection_workflow (collection_id, workflow_id),
    FOREIGN KEY (collection_id) REFERENCES workflow_collections(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    
    INDEX idx_collection_id (collection_id),
    INDEX idx_workflow_id (workflow_id),
    INDEX idx_sort_order (sort_order)
);
```

## Optimization Considerations

1. **Partitioning**: Consider partitioning large tables like `user_activities` and `workflow_views` by date
2. **Caching Strategy**: Use Redis for frequently accessed data like user feeds and trending workflows
3. **Denormalization**: Counts are denormalized for performance (likes_count, followers_count, etc.)
4. **Search Indexes**: Full-text indexes on searchable content
5. **Composite Indexes**: Carefully designed for common query patterns
6. **JSON Columns**: Used for flexible metadata storage (devices_used, macro_controls, activity_data)

## Database Triggers for Count Maintenance

```sql
-- Example trigger for maintaining workflow likes count
DELIMITER $$
CREATE TRIGGER update_workflow_likes_count_insert
    AFTER INSERT ON workflow_likes
    FOR EACH ROW
BEGIN
    UPDATE workflows 
    SET likes_count = likes_count + 1 
    WHERE id = NEW.workflow_id;
END$$

CREATE TRIGGER update_workflow_likes_count_delete
    AFTER DELETE ON workflow_likes
    FOR EACH ROW
BEGIN
    UPDATE workflows 
    SET likes_count = likes_count - 1 
    WHERE id = OLD.workflow_id;
END$$
DELIMITER ;
```