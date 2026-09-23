<?php
/**
 * Blog module helpers (admin + public)
 * Centralizes CRUD, taxonomy, comments, popularity and sharing helpers.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';

/**
 * Return the PDO connection or null.
 */
function blogGetPdo() {
    $db = TchadokDatabase::getInstance();
    if (!$db->isConnected()) {
        return null;
    }
    return $db->getConnection();
}

/**
 * Cached table existence lookup.
 */
function blogTableExists($tableName) {
    static $cache = [];
    $key = strtolower((string) $tableName);
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = tableExists($tableName);
    }
    return $cache[$key];
}

/**
 * Cached column existence lookup.
 */
function blogColumnExists($tableName, $columnName) {
    static $cache = [];
    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $pdo = blogGetPdo();
    if (!$pdo) {
        $cache[$cacheKey] = false;
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);
        $cache[$cacheKey] = (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

/**
 * Safe slug builder.
 */
function blogSlugify($value) {
    if (function_exists('slugifyText')) {
        $slug = slugifyText((string) $value);
        if ($slug !== '') {
            return $slug;
        }
    }

    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string) $value, '-');
}

/**
 * Build an absolute URL from local path or return external URL as-is.
 */
function blogMakeAbsoluteUrl($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $url)) {
        return $url;
    }

    return rtrim(SITE_URL, '/') . '/' . ltrim($url, '/');
}

/**
 * Build canonical post URL.
 */
function blogPostUrl(array $post) {
    if (!empty($post['slug'])) {
        return rtrim(SITE_URL, '/') . '/blog-article.php?slug=' . urlencode($post['slug']);
    }

    return rtrim(SITE_URL, '/') . '/blog-article.php?id=' . (int) ($post['id'] ?? 0);
}

/**
 * Remove unsafe HTML fragments while preserving rich-text formatting.
 */
function blogSanitizeHtml($html) {
    $html = (string) $html;

    $html = preg_replace('~<\s*script[^>]*>.*?<\s*/\s*script\s*>~is', '', $html);
    $html = preg_replace('~<\s*style[^>]*>.*?<\s*/\s*style\s*>~is', '', $html);
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '', $html);

    return trim($html);
}

/**
 * Parse and normalize comma/newline/semicolon separated tags.
 */
function blogNormalizeTags($rawTags) {
    if (is_array($rawTags)) {
        $parts = $rawTags;
    } else {
        $raw = str_replace(["\r\n", "\n", ';'], ',', (string) $rawTags);
        $parts = explode(',', $raw);
    }

    $normalized = [];
    $seen = [];

    foreach ($parts as $part) {
        $tag = trim((string) $part);
        if ($tag === '') {
            continue;
        }
        if (function_exists('mb_substr')) {
            $tag = mb_substr($tag, 0, 40, 'UTF-8');
            $key = mb_strtolower($tag, 'UTF-8');
        } else {
            $tag = substr($tag, 0, 40);
            $key = strtolower($tag);
        }

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = $tag;

        if (count($normalized) >= 20) {
            break;
        }
    }

    return $normalized;
}

/**
 * Return unique slug for blog_posts.
 */
function blogEnsureUniqueSlug(PDO $pdo, $requestedSlug, $title, $excludeId = 0) {
    $baseSlug = blogSlugify($requestedSlug ?: $title);
    if ($baseSlug === '') {
        $baseSlug = 'article-' . date('YmdHis');
    }

    $slug = $baseSlug;
    $counter = 2;

    while (true) {
        $sql = "SELECT id FROM blog_posts WHERE slug = ?";
        $params = [$slug];

        if ($excludeId > 0) {
            $sql .= " AND id <> ?";
            $params[] = (int) $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

/**
 * Normalize datetime string to SQL format or return null.
 */
function blogNormalizeDateTime($input) {
    $value = trim((string) $input);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Ensure category is present in taxonomy table.
 */
function blogEnsureCategory(PDO $pdo, $categoryName, $color = '#2F6DE0', $icon = 'fa-newspaper') {
    $name = trim((string) $categoryName);
    if ($name === '' || !blogTableExists('blog_categories')) {
        return;
    }

    $slug = blogSlugify($name);
    if ($slug === '') {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO blog_categories (name, slug, color, icon, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            color = COALESCE(NULLIF(VALUES(color), ''), color),
            icon = COALESCE(NULLIF(VALUES(icon), ''), icon),
            is_active = 1,
            updated_at = NOW()
    ");
    $stmt->execute([$name, $slug, $color, $icon]);
}

/**
 * Create/update category from admin panel.
 */
function blogCreateOrUpdateCategory($name, $color, $icon, $description = '') {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_categories')) {
        return ['success' => false, 'message' => "Table blog_categories indisponible."];
    }

    $name = trim((string) $name);
    if ($name === '') {
        return ['success' => false, 'message' => "Nom de categorie obligatoire."];
    }

    $slug = blogSlugify($name);
    if ($slug === '') {
        return ['success' => false, 'message' => "Slug de categorie invalide."];
    }

    $color = trim((string) $color);
    if ($color === '' || !preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        $color = '#2F6DE0';
    }

    $icon = trim((string) $icon);
    if ($icon === '') {
        $icon = 'fa-newspaper';
    }

    $description = trim((string) $description);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO blog_categories (name, slug, description, color, icon, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                color = VALUES(color),
                icon = VALUES(icon),
                is_active = 1,
                updated_at = NOW()
        ");
        $stmt->execute([$name, $slug, $description ?: null, $color, $icon]);

        return ['success' => true, 'message' => "Categorie enregistree."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur categorie: " . $e->getMessage()];
    }
}

/**
 * Sync post tags taxonomy map.
 */
function blogSyncPostTags(PDO $pdo, $postId, array $tags) {
    if (!blogTableExists('blog_tags') || !blogTableExists('blog_post_tags')) {
        return;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return;
    }

    $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);

    foreach ($tags as $tagName) {
        $tagName = trim((string) $tagName);
        if ($tagName === '') {
            continue;
        }

        $slug = blogSlugify($tagName);
        if ($slug === '') {
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO blog_tags (name, slug, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name)
        ");
        $stmt->execute([$tagName, $slug]);

        $stmt = $pdo->prepare("SELECT id FROM blog_tags WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $tagId = (int) $stmt->fetchColumn();

        if ($tagId > 0) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO blog_post_tags (post_id, tag_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$postId, $tagId]);
        }
    }
}

/**
 * Fetch post meta map.
 */
function blogGetPostMetaMap($postId) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_post_meta')) {
        return [];
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM blog_post_meta WHERE post_id = ?");
        $stmt->execute([$postId]);
        $rows = $stmt->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row['meta_key']] = $row['meta_value'];
        }
        return $meta;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Attach meta for a list of posts.
 */
function blogAttachMetaToPosts(array $posts, array $keys = []) {
    $pdo = blogGetPdo();
    if (!$pdo || empty($posts) || !blogTableExists('blog_post_meta')) {
        return $posts;
    }

    $postIds = [];
    foreach ($posts as $post) {
        $id = (int) ($post['id'] ?? 0);
        if ($id > 0) {
            $postIds[] = $id;
        }
    }
    $postIds = array_values(array_unique($postIds));
    if (empty($postIds)) {
        return $posts;
    }

    $postPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
    $params = $postIds;

    $sql = "SELECT post_id, meta_key, meta_value FROM blog_post_meta WHERE post_id IN ($postPlaceholders)";
    if (!empty($keys)) {
        $keyPlaceholders = implode(',', array_fill(0, count($keys), '?'));
        $sql .= " AND meta_key IN ($keyPlaceholders)";
        foreach ($keys as $key) {
            $params[] = (string) $key;
        }
    }

    $metaMap = [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int) $row['post_id'];
            if (!isset($metaMap[$pid])) {
                $metaMap[$pid] = [];
            }
            $metaMap[$pid][$row['meta_key']] = $row['meta_value'];
        }
    } catch (Exception $e) {
        return $posts;
    }

    foreach ($posts as &$post) {
        $pid = (int) ($post['id'] ?? 0);
        $meta = $metaMap[$pid] ?? [];
        $post['meta'] = $meta;
        $post['allow_comments'] = isset($meta['allow_comments']) ? (int) $meta['allow_comments'] : 1;
        $post['display_mode'] = $meta['display_mode'] ?? 'standard';
        $post['seo_title'] = $meta['seo_title'] ?? '';
        $post['seo_description'] = $meta['seo_description'] ?? '';
        $post['social_title'] = $meta['social_title'] ?? '';
        $post['social_description'] = $meta['social_description'] ?? '';
        $post['social_image'] = $meta['social_image'] ?? '';
        $post['video_url'] = $meta['video_url'] ?? '';
        $post['audio_url'] = $meta['audio_url'] ?? '';
    }
    unset($post);

    return $posts;
}

/**
 * Upsert post meta value.
 */
function blogSetPostMeta(PDO $pdo, $postId, $key, $value) {
    if (!blogTableExists('blog_post_meta')) {
        return;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return;
    }

    $key = trim((string) $key);
    if ($key === '') {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO blog_post_meta (post_id, meta_key, meta_value, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            meta_value = VALUES(meta_value),
            updated_at = NOW()
    ");
    $stmt->execute([$postId, $key, (string) $value]);
}

/**
 * Return a post for admin usage.
 */
function blogGetPostById($postId, $allowDraft = true) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return null;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return null;
    }

    try {
        $sql = "
            SELECT p.*, u.first_name, u.last_name, u.username
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.id = ?
        ";
        $params = [$postId];
        if (!$allowDraft) {
            $sql .= " AND p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $post = $stmt->fetch();
        if (!$post) {
            return null;
        }

        $post['author'] = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
        if ($post['author'] === '') {
            $post['author'] = $post['username'] ?? 'Tchadok';
        }
        $post['excerpt'] = $post['excerpt'] ?: truncateText(strip_tags((string) $post['content']), 180);
        $post['date_label'] = timeAgoFrench($post['published_at'] ?: $post['created_at']);
        $post['read_time'] = estimateReadTime($post['content'] ?? '');
        $post['url'] = blogPostUrl($post);

        $posts = blogAttachMetaToPosts([$post]);
        return $posts[0] ?? $post;
    } catch (Exception $e) {
        error_log("blogGetPostById error: " . $e->getMessage());
        return null;
    }
}

/**
 * Return a post by slug.
 */
function blogGetPostBySlug($slug, $allowDraft = false) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return null;
    }

    $slug = trim((string) $slug);
    if ($slug === '') {
        return null;
    }

    try {
        $sql = "
            SELECT p.*, u.first_name, u.last_name, u.username
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.slug = ?
        ";
        $params = [$slug];
        if (!$allowDraft) {
            $sql .= " AND p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())";
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $post = $stmt->fetch();
        if (!$post) {
            return null;
        }

        $post['author'] = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
        if ($post['author'] === '') {
            $post['author'] = $post['username'] ?? 'Tchadok';
        }
        $post['excerpt'] = $post['excerpt'] ?: truncateText(strip_tags((string) $post['content']), 180);
        $post['date_label'] = timeAgoFrench($post['published_at'] ?: $post['created_at']);
        $post['read_time'] = estimateReadTime($post['content'] ?? '');
        $post['url'] = blogPostUrl($post);

        $posts = blogAttachMetaToPosts([$post]);
        return $posts[0] ?? $post;
    } catch (Exception $e) {
        error_log("blogGetPostBySlug error: " . $e->getMessage());
        return null;
    }
}

/**
 * Save or update a blog post.
 */
function blogSavePost(array $payload, array $files, $authorId, $postId = 0) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return ['success' => false, 'message' => "Table blog_posts indisponible."];
    }

    $postId = (int) $postId;
    $authorId = (int) $authorId;
    $existing = null;
    if ($postId > 0) {
        $existing = blogGetPostById($postId, true);
        if (!$existing) {
            return ['success' => false, 'message' => "Article introuvable."];
        }
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $slugInput = trim((string) ($payload['slug'] ?? ''));
    $excerpt = trim((string) ($payload['excerpt'] ?? ''));
    $content = blogSanitizeHtml((string) ($payload['content'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $status = trim((string) ($payload['status'] ?? 'draft'));
    $featured = !empty($payload['featured']) ? 1 : 0;
    $publishAtInput = blogNormalizeDateTime($payload['published_at'] ?? null);

    if ($title === '') {
        return ['success' => false, 'message' => "Titre obligatoire."];
    }

    if ($content === '') {
        return ['success' => false, 'message' => "Contenu obligatoire."];
    }

    $validStatuses = ['draft', 'published', 'archived'];
    if (!in_array($status, $validStatuses, true)) {
        $status = 'draft';
    }

    $slug = blogEnsureUniqueSlug($pdo, $slugInput, $title, $postId);

    if ($excerpt === '') {
        $excerpt = truncateText(strip_tags($content), 200);
    }

    $featuredImage = trim((string) ($existing['featured_image'] ?? ''));
    if (!empty($files['featured_image_file']['tmp_name'])) {
        $upload = uploadFile(
            $files['featured_image_file'],
            __DIR__ . '/../' . IMAGES_PATH . 'blog/',
            ALLOWED_IMAGE_TYPES,
            MAX_IMAGE_SIZE
        );

        if (!$upload['success']) {
            return ['success' => false, 'message' => "Image: " . $upload['message']];
        }

        $featuredImage = IMAGES_PATH . 'blog/' . $upload['filename'];
    }

    $featuredImageUrl = trim((string) ($payload['featured_image_url'] ?? ''));
    if ($featuredImageUrl !== '') {
        $featuredImage = $featuredImageUrl;
    }

    $publishedAt = $publishAtInput;
    if ($status === 'published' && !$publishedAt) {
        $publishedAt = $existing['published_at'] ?? date('Y-m-d H:i:s');
    }

    $tags = blogNormalizeTags($payload['tags'] ?? '');
    $tagsCsv = implode(', ', $tags);

    $allowComments = !empty($payload['allow_comments']) ? 1 : 0;
    $displayMode = trim((string) ($payload['display_mode'] ?? 'standard'));
    $validDisplayModes = ['standard', 'hero', 'spotlight', 'compact'];
    if (!in_array($displayMode, $validDisplayModes, true)) {
        $displayMode = 'standard';
    }

    $metaInput = [
        'allow_comments' => $allowComments,
        'display_mode' => $displayMode,
        'seo_title' => trim((string) ($payload['seo_title'] ?? '')),
        'seo_description' => trim((string) ($payload['seo_description'] ?? '')),
        'social_title' => trim((string) ($payload['social_title'] ?? '')),
        'social_description' => trim((string) ($payload['social_description'] ?? '')),
        'social_image' => trim((string) ($payload['social_image'] ?? '')),
        'video_url' => trim((string) ($payload['video_url'] ?? '')),
        'audio_url' => trim((string) ($payload['audio_url'] ?? '')),
        'gallery_images' => trim((string) ($payload['gallery_images'] ?? '')),
        'show_toc' => !empty($payload['show_toc']) ? '1' : '0',
        'pin_to_top' => !empty($payload['pin_to_top']) ? '1' : '0'
    ];

    try {
        $pdo->beginTransaction();

        if ($postId > 0) {
            $stmt = $pdo->prepare("
                UPDATE blog_posts
                SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?,
                    category = ?, tags = ?, status = ?, featured = ?, published_at = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $slug,
                $content,
                $excerpt ?: null,
                $featuredImage ?: null,
                $category ?: null,
                $tagsCsv ?: null,
                $status,
                $featured,
                $publishedAt,
                $postId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts
                (title, slug, content, excerpt, featured_image, author_id, category, tags, status, featured, published_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $title,
                $slug,
                $content,
                $excerpt ?: null,
                $featuredImage ?: null,
                $authorId > 0 ? $authorId : null,
                $category ?: null,
                $tagsCsv ?: null,
                $status,
                $featured,
                $publishedAt
            ]);
            $postId = (int) $pdo->lastInsertId();
        }

        if ($category !== '') {
            blogEnsureCategory($pdo, $category);
        }

        blogSyncPostTags($pdo, $postId, $tags);

        foreach ($metaInput as $metaKey => $metaValue) {
            blogSetPostMeta($pdo, $postId, $metaKey, $metaValue);
        }

        $pdo->commit();

        $message = ($existing ? "Article mis a jour." : "Article cree.") . " Slug: " . $slug;
        return [
            'success' => true,
            'message' => $message,
            'post_id' => $postId,
            'slug' => $slug
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => "Erreur sauvegarde: " . $e->getMessage()];
    }
}

/**
 * Delete post and connected resources.
 */
function blogDeletePost($postId) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return ['success' => false, 'message' => "Table blog_posts indisponible."];
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return ['success' => false, 'message' => "ID article invalide."];
    }

    try {
        $pdo->beginTransaction();

        if (blogTableExists('blog_post_tags')) {
            $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);
        }
        if (blogTableExists('blog_post_meta')) {
            $pdo->prepare("DELETE FROM blog_post_meta WHERE post_id = ?")->execute([$postId]);
        }
        if (blogTableExists('blog_post_views')) {
            $pdo->prepare("DELETE FROM blog_post_views WHERE post_id = ?")->execute([$postId]);
        }
        if (blogTableExists('blog_post_shares')) {
            $pdo->prepare("DELETE FROM blog_post_shares WHERE post_id = ?")->execute([$postId]);
        }
        if (blogTableExists('blog_comments')) {
            $pdo->prepare("DELETE FROM blog_comments WHERE post_id = ?")->execute([$postId]);
        }

        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$postId]);

        $pdo->commit();
        return ['success' => true, 'message' => "Article supprime."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => "Erreur suppression: " . $e->getMessage()];
    }
}

/**
 * Build dynamic SQL parts for public listing.
 */
function blogBuildPublicFilterSql($filters, &$params, &$joins) {
    $where = "p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())";

    if (!empty($filters['category']) && $filters['category'] !== 'all') {
        $where .= " AND p.category = ?";
        $params[] = $filters['category'];
    }

    if (!empty($filters['q'])) {
        $where .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)";
        $like = '%' . $filters['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($filters['tag'])) {
        if (blogTableExists('blog_tags') && blogTableExists('blog_post_tags')) {
            $joins .= " INNER JOIN blog_post_tags bpt ON bpt.post_id = p.id
                        INNER JOIN blog_tags bt ON bt.id = bpt.tag_id";
            $where .= " AND bt.slug = ?";
            $params[] = blogSlugify($filters['tag']);
        } else {
            $where .= " AND p.tags LIKE ?";
            $params[] = '%' . $filters['tag'] . '%';
        }
    }

    if (!empty($filters['featured_only'])) {
        $where .= " AND p.featured = 1";
    }

    return $where;
}

/**
 * Public blog listing.
 */
function blogFetchPostsPublic(array $filters = []) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return [];
    }

    $limit = max(1, min(60, (int) ($filters['limit'] ?? 9)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));
    $sort = trim((string) ($filters['sort'] ?? 'recent'));

    $params = [];
    $joins = " LEFT JOIN users u ON p.author_id = u.id ";
    $where = blogBuildPublicFilterSql($filters, $params, $joins);

    $orderBy = "p.published_at DESC, p.created_at DESC";
    if ($sort === 'popular') {
        $orderBy = "p.views_count DESC, p.comments_count DESC, p.published_at DESC, p.created_at DESC";
    } elseif ($sort === 'oldest') {
        $orderBy = "p.published_at ASC, p.created_at ASC";
    } elseif ($sort === 'featured') {
        $orderBy = "p.featured DESC, p.published_at DESC, p.created_at DESC";
    }

    $sql = "
        SELECT DISTINCT
            p.id, p.title, p.slug, p.content, p.excerpt, p.featured_image,
            p.category, p.tags, p.status, p.featured, p.views_count, p.likes_count, p.comments_count,
            p.published_at, p.created_at, p.updated_at,
            u.first_name, u.last_name, u.username
        FROM blog_posts p
        $joins
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        foreach ($posts as &$post) {
            $author = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? ''));
            if ($author === '') {
                $author = $post['username'] ?? 'Tchadok';
            }

            $post['author'] = $author;
            $post['excerpt'] = $post['excerpt'] ?: truncateText(strip_tags((string) $post['content']), 180);
            $post['date_label'] = timeAgoFrench($post['published_at'] ?: $post['created_at']);
            $post['read_time'] = estimateReadTime($post['content'] ?? '');
            $post['url'] = blogPostUrl($post);
            $post['tags_list'] = blogNormalizeTags($post['tags'] ?? '');
        }
        unset($post);

        $posts = blogAttachMetaToPosts($posts, [
            'display_mode',
            'allow_comments',
            'seo_title',
            'seo_description',
            'social_title',
            'social_description',
            'social_image',
            'video_url',
            'audio_url',
            'pin_to_top'
        ]);

        return $posts;
    } catch (Exception $e) {
        error_log("blogFetchPostsPublic error: " . $e->getMessage());
        return [];
    }
}

/**
 * Count public posts with same filters.
 */
function blogCountPostsPublic(array $filters = []) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return 0;
    }

    $params = [];
    $joins = "";
    if (blogTableExists('blog_tags') && blogTableExists('blog_post_tags') && !empty($filters['tag'])) {
        $joins = " INNER JOIN blog_post_tags bpt ON bpt.post_id = p.id
                   INNER JOIN blog_tags bt ON bt.id = bpt.tag_id";
    }
    $where = blogBuildPublicFilterSql($filters, $params, $joins);

    $sql = "SELECT COUNT(DISTINCT p.id) FROM blog_posts p $joins WHERE $where";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Admin post listing with filters.
 */
function blogFetchPostsAdmin(array $filters = []) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return [];
    }

    $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));
    $search = trim((string) ($filters['q'] ?? ''));
    $status = trim((string) ($filters['status'] ?? 'all'));
    $category = trim((string) ($filters['category'] ?? 'all'));
    $sort = trim((string) ($filters['sort'] ?? 'updated_desc'));

    $where = "1=1";
    $params = [];

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ? OR p.tags LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($status !== '' && $status !== 'all') {
        $where .= " AND p.status = ?";
        $params[] = $status;
    }
    if ($category !== '' && $category !== 'all') {
        $where .= " AND p.category = ?";
        $params[] = $category;
    }

    $orderBy = "p.updated_at DESC";
    if ($sort === 'popular') {
        $orderBy = "p.views_count DESC, p.comments_count DESC, p.updated_at DESC";
    } elseif ($sort === 'recent') {
        $orderBy = "p.created_at DESC";
    } elseif ($sort === 'published_desc') {
        $orderBy = "p.published_at DESC, p.updated_at DESC";
    } elseif ($sort === 'title_asc') {
        $orderBy = "p.title ASC";
    }

    $sql = "
        SELECT
            p.id, p.title, p.slug, p.excerpt, p.category, p.tags, p.status, p.featured,
            p.views_count, p.likes_count, p.comments_count, p.published_at, p.created_at, p.updated_at,
            u.first_name, u.last_name, u.username
        FROM blog_posts p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE $where
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $author = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($author === '') {
                $author = $row['username'] ?? 'Tchadok';
            }
            $row['author'] = $author;
            $row['excerpt'] = $row['excerpt'] ?: truncateText((string) ($row['title'] ?? ''), 120);
            $row['url'] = blogPostUrl($row);
            $row['tags_list'] = blogNormalizeTags($row['tags'] ?? '');
        }
        unset($row);

        return blogAttachMetaToPosts($rows, ['display_mode', 'allow_comments', 'pin_to_top']);
    } catch (Exception $e) {
        error_log("blogFetchPostsAdmin error: " . $e->getMessage());
        return [];
    }
}

/**
 * Categories with counts.
 */
function blogFetchCategories($publishedOnly = true) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return [];
    }

    try {
        if (blogTableExists('blog_categories')) {
            $publishedWhere = $publishedOnly ? " AND p.status = 'published'" : "";
            $stmt = $pdo->query("
                SELECT
                    c.id,
                    c.name,
                    c.slug,
                    c.description,
                    c.color,
                    c.icon,
                    c.is_active,
                    COUNT(p.id) AS posts_count
                FROM blog_categories c
                LEFT JOIN blog_posts p ON p.category = c.name $publishedWhere
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY posts_count DESC, c.name ASC
            ");
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        }

        $where = $publishedOnly ? "WHERE status = 'published'" : "";
        $stmt = $pdo->query("
            SELECT
                category AS name,
                COUNT(*) AS posts_count
            FROM blog_posts
            $where
            GROUP BY category
            HAVING category IS NOT NULL AND category <> ''
            ORDER BY posts_count DESC, category ASC
        ");
        $fallback = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $fallback[] = [
                'id' => 0,
                'name' => $name,
                'slug' => blogSlugify($name),
                'description' => '',
                'color' => '#2F6DE0',
                'icon' => getCategoryIcon($name),
                'is_active' => 1,
                'posts_count' => (int) ($row['posts_count'] ?? 0)
            ];
        }
        return $fallback;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Popular tags with counts.
 */
function blogFetchPopularTags($limit = 20) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return [];
    }
    $limit = max(1, min(100, (int) $limit));

    try {
        if (blogTableExists('blog_tags') && blogTableExists('blog_post_tags')) {
            $stmt = $pdo->prepare("
                SELECT bt.id, bt.name, bt.slug, COUNT(bpt.post_id) AS posts_count
                FROM blog_tags bt
                INNER JOIN blog_post_tags bpt ON bpt.tag_id = bt.id
                INNER JOIN blog_posts p ON p.id = bpt.post_id AND p.status = 'published'
                GROUP BY bt.id
                ORDER BY posts_count DESC, bt.name ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query("
            SELECT tags
            FROM blog_posts
            WHERE status = 'published' AND tags IS NOT NULL AND tags <> ''
            ORDER BY published_at DESC
            LIMIT 500
        ");

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $tags = blogNormalizeTags($row['tags'] ?? '');
            foreach ($tags as $tagName) {
                $slug = blogSlugify($tagName);
                if (!isset($counts[$slug])) {
                    $counts[$slug] = ['name' => $tagName, 'slug' => $slug, 'posts_count' => 0];
                }
                $counts[$slug]['posts_count']++;
            }
        }

        usort($counts, function ($a, $b) {
            if ($a['posts_count'] === $b['posts_count']) {
                return strcmp($a['name'], $b['name']);
            }
            return $b['posts_count'] <=> $a['posts_count'];
        });

        return array_slice(array_values($counts), 0, $limit);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Admin dashboard stats for blog.
 */
function blogGetAdminStats() {
    $defaults = [
        'total_posts' => 0,
        'published_posts' => 0,
        'draft_posts' => 0,
        'archived_posts' => 0,
        'featured_posts' => 0,
        'total_views' => 0,
        'total_comments' => 0,
        'pending_comments' => 0
    ];

    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return $defaults;
    }

    try {
        $defaults['total_posts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
        $defaults['published_posts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
        $defaults['draft_posts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn();
        $defaults['archived_posts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'archived'")->fetchColumn();
        $defaults['featured_posts'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE featured = 1")->fetchColumn();
        $defaults['total_views'] = (int) $pdo->query("SELECT COALESCE(SUM(views_count),0) FROM blog_posts")->fetchColumn();
        $defaults['total_comments'] = (int) $pdo->query("SELECT COALESCE(SUM(comments_count),0) FROM blog_posts")->fetchColumn();

        if (blogTableExists('blog_comments')) {
            $defaults['pending_comments'] = (int) $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'pending'")->fetchColumn();
        }
    } catch (Exception $e) {
        return $defaults;
    }

    return $defaults;
}

/**
 * Pending comments for moderation.
 */
function blogGetPendingComments($limit = 30) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_comments')) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id, c.post_id, c.user_id, c.parent_id, c.content, c.status, c.created_at,
                p.title AS post_title, p.slug AS post_slug,
                u.username, u.first_name, u.last_name
            FROM blog_comments c
            INNER JOIN blog_posts p ON p.id = c.post_id
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.status = 'pending'
            ORDER BY c.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $author = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($author === '') {
                $author = $row['username'] ?? 'Utilisateur';
            }
            $row['author'] = $author;
            $row['content_short'] = truncateText((string) ($row['content'] ?? ''), 140);
            $row['post_url'] = rtrim(SITE_URL, '/') . '/blog-article.php?slug=' . urlencode($row['post_slug'] ?: '');
        }
        unset($row);

        return $rows;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Refresh comments_count on post.
 */
function blogRefreshCommentsCount($postId) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts') || !blogTableExists('blog_comments')) {
        return;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE blog_posts
            SET comments_count = (
                SELECT COUNT(*)
                FROM blog_comments
                WHERE post_id = ? AND status = 'approved'
            )
            WHERE id = ?
        ");
        $stmt->execute([$postId, $postId]);
    } catch (Exception $e) {
    }
}

/**
 * Get approved comments for a post.
 */
function blogGetCommentsForPost($postId, $status = 'approved', $limit = 200) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_comments')) {
        return [];
    }

    $postId = (int) $postId;
    $limit = max(1, min(500, (int) $limit));
    if ($postId <= 0) {
        return [];
    }

    $allowedStatus = ['pending', 'approved', 'rejected'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'approved';
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id, c.post_id, c.user_id, c.parent_id, c.content, c.status, c.likes_count, c.created_at, c.updated_at,
                u.username, u.first_name, u.last_name
            FROM blog_comments c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.post_id = ? AND c.status = ?
            ORDER BY c.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$postId, $status, $limit]);
        $comments = $stmt->fetchAll();

        foreach ($comments as &$comment) {
            $author = trim(($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? ''));
            if ($author === '') {
                $author = $comment['username'] ?? 'Utilisateur';
            }
            $comment['author'] = $author;
            $comment['time_label'] = timeAgoFrench($comment['created_at']);
        }
        unset($comment);

        return $comments;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Add a comment to a post.
 */
function blogCreateComment($postId, $userId, $content, $parentId = null) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_comments')) {
        return ['success' => false, 'message' => "Commentaires indisponibles."];
    }

    $postId = (int) $postId;
    $userId = (int) $userId;
    $parentId = $parentId !== null ? (int) $parentId : null;
    $content = trim((string) $content);

    if ($postId <= 0 || $userId <= 0) {
        return ['success' => false, 'message' => "Utilisateur ou article invalide."];
    }

    if ($content === '') {
        return ['success' => false, 'message' => "Le commentaire ne peut pas etre vide."];
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($content, 'UTF-8') > 2000) {
            $content = mb_substr($content, 0, 2000, 'UTF-8');
        }
    } else {
        if (strlen($content) > 2000) {
            $content = substr($content, 0, 2000);
        }
    }

    $post = blogGetPostById($postId, true);
    if (!$post) {
        return ['success' => false, 'message' => "Article introuvable."];
    }
    $allowComments = isset($post['allow_comments']) ? (int) $post['allow_comments'] : 1;
    if ($allowComments !== 1) {
        return ['success' => false, 'message' => "Les commentaires sont desactives pour cet article."];
    }

    $status = (isLoggedIn() && isAdmin()) ? 'approved' : 'pending';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO blog_comments (post_id, user_id, parent_id, content, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$postId, $userId, $parentId, $content, $status]);

        if ($status === 'approved') {
            blogRefreshCommentsCount($postId);
            return ['success' => true, 'message' => "Commentaire publie."];
        }

        return ['success' => true, 'message' => "Commentaire envoye. Il sera publie apres validation."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur commentaire: " . $e->getMessage()];
    }
}

/**
 * Moderate comment status.
 */
function blogModerateComment($commentId, $status) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_comments')) {
        return ['success' => false, 'message' => "Table commentaires indisponible."];
    }

    $commentId = (int) $commentId;
    $status = trim((string) $status);
    $allowedStatus = ['pending', 'approved', 'rejected'];
    if (!in_array($status, $allowedStatus, true)) {
        return ['success' => false, 'message' => "Statut commentaire invalide."];
    }

    try {
        $stmt = $pdo->prepare("SELECT post_id FROM blog_comments WHERE id = ? LIMIT 1");
        $stmt->execute([$commentId]);
        $postId = (int) $stmt->fetchColumn();
        if ($postId <= 0) {
            return ['success' => false, 'message' => "Commentaire introuvable."];
        }

        $stmt = $pdo->prepare("UPDATE blog_comments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $commentId]);
        blogRefreshCommentsCount($postId);

        return ['success' => true, 'message' => "Commentaire mis a jour."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur moderation: " . $e->getMessage()];
    }
}

/**
 * Delete comment.
 */
function blogDeleteComment($commentId) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_comments')) {
        return ['success' => false, 'message' => "Table commentaires indisponible."];
    }

    $commentId = (int) $commentId;
    if ($commentId <= 0) {
        return ['success' => false, 'message' => "ID commentaire invalide."];
    }

    try {
        $stmt = $pdo->prepare("SELECT post_id FROM blog_comments WHERE id = ? LIMIT 1");
        $stmt->execute([$commentId]);
        $postId = (int) $stmt->fetchColumn();
        if ($postId <= 0) {
            return ['success' => false, 'message' => "Commentaire introuvable."];
        }

        $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id = ?");
        $stmt->execute([$commentId]);

        blogRefreshCommentsCount($postId);
        return ['success' => true, 'message' => "Commentaire supprime."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur suppression commentaire: " . $e->getMessage()];
    }
}

/**
 * Register a view count for one post.
 */
function blogRegisterView($postId) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_posts')) {
        return;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return;
    }

    if (!isset($_SESSION['blog_view_once'])) {
        $_SESSION['blog_view_once'] = [];
    }
    if (isset($_SESSION['blog_view_once'][$postId])) {
        return;
    }
    $_SESSION['blog_view_once'][$postId] = time();

    try {
        if (blogTableExists('blog_post_views')) {
            $userId = isLoggedIn() ? (int) ($_SESSION['user_id'] ?? 0) : null;
            $ip = function_exists('clientIp') ? clientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'ua';
            $viewerSeed = ($userId ?: session_id()) . '|' . $ip . '|' . $agent;
            $viewerHash = hash('sha256', $viewerSeed);

            $stmt = $pdo->prepare("
                INSERT IGNORE INTO blog_post_views (post_id, user_id, viewer_hash, view_date, viewed_at)
                VALUES (?, ?, ?, CURDATE(), NOW())
            ");
            $stmt->execute([$postId, $userId ?: null, $viewerHash]);
            $isNewView = $stmt->rowCount() > 0;

            if ($isNewView) {
                $stmt = $pdo->prepare("UPDATE blog_posts SET views_count = views_count + 1 WHERE id = ?");
                $stmt->execute([$postId]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE blog_posts SET views_count = views_count + 1 WHERE id = ?");
            $stmt->execute([$postId]);
        }
    } catch (Exception $e) {
    }
}

/**
 * Record share interaction.
 */
function blogRecordShare($postId, $platform, $userId = null) {
    $pdo = blogGetPdo();
    if (!$pdo || !blogTableExists('blog_post_shares')) {
        return;
    }

    $postId = (int) $postId;
    if ($postId <= 0) {
        return;
    }

    $platform = strtolower(trim((string) $platform));
    $allowed = ['facebook', 'x', 'twitter', 'whatsapp', 'linkedin', 'telegram', 'email', 'copy'];
    if (!in_array($platform, $allowed, true)) {
        $platform = 'copy';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO blog_post_shares (post_id, user_id, platform, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$postId, $userId ? (int) $userId : null, $platform]);
    } catch (Exception $e) {
    }
}

/**
 * Related posts.
 */
function blogGetRelatedPosts($postId, $category = '', $limit = 3) {
    $filters = [
        'limit' => max(1, min(12, (int) $limit)),
        'sort' => 'popular'
    ];
    if (trim((string) $category) !== '') {
        $filters['category'] = $category;
    }

    $posts = blogFetchPostsPublic($filters);
    $postId = (int) $postId;
    $posts = array_values(array_filter($posts, function ($post) use ($postId) {
        return (int) ($post['id'] ?? 0) !== $postId;
    }));

    return array_slice($posts, 0, $filters['limit']);
}

/**
 * Build social share links for a post.
 */
function blogGetShareLinks(array $post) {
    $url = blogPostUrl($post);
    $title = $post['social_title'] ?: $post['title'];
    $description = $post['social_description'] ?: $post['excerpt'];

    $encodedUrl = rawurlencode($url);
    $encodedTitle = rawurlencode($title);
    $encodedDescription = rawurlencode($description);

    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl,
        'x' => 'https://twitter.com/intent/tweet?url=' . $encodedUrl . '&text=' . $encodedTitle,
        'whatsapp' => 'https://api.whatsapp.com/send?text=' . rawurlencode($title . ' ' . $url),
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl,
        'telegram' => 'https://t.me/share/url?url=' . $encodedUrl . '&text=' . $encodedTitle,
        'email' => 'mailto:?subject=' . $encodedTitle . '&body=' . $encodedDescription . '%0A%0A' . $encodedUrl
    ];
}
