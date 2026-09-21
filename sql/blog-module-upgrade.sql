-- ============================================================
-- TCHADOK BLOG PROFESSIONAL MODULE UPGRADE
-- Safe script: does not drop existing data.
-- ============================================================

START TRANSACTION;

CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    color VARCHAR(20) DEFAULT '#2F6DE0',
    icon VARCHAR(50) DEFAULT 'fa-newspaper',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(90) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_blog_post_tags_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_post_tags_tag
        FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_post_meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    meta_key VARCHAR(80) NOT NULL,
    meta_value LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_meta (post_id, meta_key),
    KEY idx_meta_key (meta_key),
    CONSTRAINT fk_blog_post_meta_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_post_views (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NULL,
    viewer_hash CHAR(64) NOT NULL,
    view_date DATE NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_viewer_day (post_id, viewer_hash, view_date),
    KEY idx_post_date (post_id, view_date),
    CONSTRAINT fk_blog_post_views_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_post_views_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_post_shares (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NULL,
    platform ENUM('facebook','x','twitter','whatsapp','linkedin','telegram','email','copy') DEFAULT 'copy',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post_created (post_id, created_at),
    CONSTRAINT fk_blog_post_shares_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_post_shares_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS blog_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT NULL,
    content TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blog_comments_post_status (post_id, status),
    CONSTRAINT fk_blog_comments_post
        FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_comments_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_comments_parent
        FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE blog_posts
    MODIFY COLUMN content LONGTEXT NOT NULL;

INSERT INTO blog_categories (name, slug, description, color, icon, is_active)
VALUES
    ('Actualites', 'actualites', 'News et annonces musicales', '#2F6DE0', 'fa-newspaper', 1),
    ('Interviews', 'interviews', 'Entretiens avec artistes et producteurs', '#FFC107', 'fa-microphone', 1),
    ('Analyses', 'analyses', 'Decodage des tendances et marches', '#22C55E', 'fa-chart-line', 1),
    ('Culture', 'culture', 'Patrimoine, scenes locales et societe', '#F97316', 'fa-palette', 1),
    ('Podcasts', 'podcasts', 'Episodes audio et formats longs', '#14B8A6', 'fa-podcast', 1)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    color = VALUES(color),
    icon = VALUES(icon),
    is_active = 1;

COMMIT;

-- ============================================================
-- END
-- ============================================================
