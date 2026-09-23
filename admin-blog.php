<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/blog-manager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

function adminBlogRedirect(array $params = []) {
    $url = SITE_URL . '/admin-blog.php';
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage(FLASH_ERROR, "Session invalide. Rechargez la page et recommencez.");
        adminBlogRedirect();
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save_post') {
        if (isset($_POST['save_as_draft'])) {
            $_POST['status'] = 'draft';
        }
        if (isset($_POST['publish_now'])) {
            $_POST['status'] = 'published';
        }

        $result = blogSavePost(
            $_POST,
            $_FILES,
            (int) ($_SESSION['user_id'] ?? 0),
            (int) ($_POST['post_id'] ?? 0)
        );

        if ($result['success']) {
            setFlashMessage(FLASH_SUCCESS, $result['message']);
            adminBlogRedirect(['edit' => (int) $result['post_id']]);
        }

        setFlashMessage(FLASH_ERROR, $result['message']);
        adminBlogRedirect(['edit' => (int) ($_POST['post_id'] ?? 0)]);
    }

    if ($action === 'delete_post') {
        $result = blogDeletePost((int) ($_POST['post_id'] ?? 0));
        setFlashMessage($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        adminBlogRedirect();
    }

    if ($action === 'save_category') {
        $result = blogCreateOrUpdateCategory(
            $_POST['category_name'] ?? '',
            $_POST['category_color'] ?? '',
            $_POST['category_icon'] ?? '',
            $_POST['category_description'] ?? ''
        );
        setFlashMessage($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        adminBlogRedirect(['edit' => (int) ($_GET['edit'] ?? 0)]);
    }

    if ($action === 'moderate_comment') {
        $result = blogModerateComment((int) ($_POST['comment_id'] ?? 0), $_POST['status'] ?? 'pending');
        setFlashMessage($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        adminBlogRedirect(['tab' => 'comments']);
    }

    if ($action === 'delete_comment') {
        $result = blogDeleteComment((int) ($_POST['comment_id'] ?? 0));
        setFlashMessage($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        adminBlogRedirect(['tab' => 'comments']);
    }
}

$pageTitle = 'Studio Blog Admin';
$pageDescription = "Gestion editoriale professionnelle des articles";
$hideTopNav = true;
$hideFooter = true;

$user = getCurrentUser();

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$categoryFilter = trim((string) ($_GET['category'] ?? 'all'));
$sortFilter = trim((string) ($_GET['sort'] ?? 'updated_desc'));

$stats = blogGetAdminStats();
$categories = blogFetchCategories(false);
$posts = blogFetchPostsAdmin([
    'q' => $search,
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'sort' => $sortFilter,
    'limit' => 80
]);
$pendingComments = blogGetPendingComments(20);
$editingPost = $editId > 0 ? blogGetPostById($editId, true) : null;

$defaultPost = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'category' => '',
    'tags' => '',
    'status' => 'draft',
    'featured' => 0,
    'featured_image' => '',
    'published_at' => '',
    'allow_comments' => 1,
    'display_mode' => 'standard',
    'seo_title' => '',
    'seo_description' => '',
    'social_title' => '',
    'social_description' => '',
    'social_image' => '',
    'video_url' => '',
    'audio_url' => '',
    'meta' => []
];
$postForm = $editingPost ?: $defaultPost;

$publishedAtValue = '';
if (!empty($postForm['published_at'])) {
    $publishedAtValue = date('Y-m-d\TH:i', strtotime($postForm['published_at']));
}

$galleryImages = trim((string) ($postForm['meta']['gallery_images'] ?? ''));
$showToc = !empty($postForm['meta']['show_toc']);
$pinToTop = !empty($postForm['meta']['pin_to_top']);

$adminShellMetrics = [
    ['value' => formatNumber($stats['total_posts']), 'label' => 'articles', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => formatNumber($stats['pending_comments']), 'label' => 'en attente', 'tone' => 'border-white/10 bg-white/5 text-muted'],
    ['value' => formatNumber($stats['published_posts']), 'label' => 'publies', 'tone' => 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200']
];
$dashboardSecondaryNavLabel = 'Blog admin';
$dashboardSecondaryNavItems = [
    ['label' => 'Apercu', 'target' => 'blog-overview', 'icon' => 'home'],
    ['label' => 'Edition', 'target' => 'blog-editor', 'icon' => 'pen-nib'],
    ['label' => 'Moderation', 'target' => 'blog-comments', 'icon' => 'comments'],
    ['label' => 'Bibliotheque', 'target' => 'blog-library', 'icon' => 'folder-open']
];

$additionalCSS = [
    SITE_URL . '/assets/css/admin-blog.css'
];
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js',
    'https://cdn.jsdelivr.net/npm/tinymce-i18n@24/langs6/fr_FR.min.js',
    SITE_URL . '/assets/js/tailwind-shell.js',
    SITE_URL . '/assets/js/dashboard-sticky-header.js',
    SITE_URL . '/assets/js/dashboard-secondary-nav.js',
    SITE_URL . '/assets/js/admin-blog.js'
];

include 'includes/header-tailwind.php';
?>

<main class="min-h-screen bg-[radial-gradient(circle_at_top_right,rgba(47,109,224,0.18),transparent_26%),radial-gradient(circle_at_top_left,rgba(245,158,11,0.12),transparent_24%),#0B0F17] pb-16 pt-8">
    <?php include 'includes/admin-shell-header.php'; ?>

    <section id="blog-overview" class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 place-items-center rounded-2xl bg-accent/20 text-accent">
                        <i class="fas fa-pen-nib"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-display font-bold text-text">Studio Blog</h1>
                        <p class="text-sm text-muted">Creation, publication, moderation et diffusion sociale.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="<?php echo SITE_URL; ?>/admin-dashboard.php" class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-text">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                    <a href="<?php echo SITE_URL; ?>/blog.php" target="_blank" rel="noopener" class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white shadow-elev-1">
                        <i class="fas fa-eye"></i> Voir blog public
                    </a>
                </div>
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-white/10 bg-surface/60 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Articles</p>
                    <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_posts']); ?></p>
                    <p class="text-xs text-muted"><?php echo formatNumber($stats['published_posts']); ?> publies</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-surface/60 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Brouillons</p>
                    <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatNumber($stats['draft_posts']); ?></p>
                    <p class="text-xs text-muted"><?php echo formatNumber($stats['archived_posts']); ?> archives</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-surface/60 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Lectures</p>
                    <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_views']); ?></p>
                    <p class="text-xs text-muted"><?php echo formatNumber($stats['total_comments']); ?> commentaires approuves</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-surface/60 p-4">
                    <p class="text-xs uppercase tracking-[0.2em] text-muted">Moderation</p>
                    <p class="mt-2 text-2xl font-semibold text-text"><?php echo formatNumber($stats['pending_comments']); ?></p>
                    <p class="text-xs text-muted">commentaires en attente</p>
                </div>
            </div>
        </div>
    </section>
    <section id="blog-editor" class="pb-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 xl:grid-cols-3">
                <div class="xl:col-span-2 rounded-3xl border border-white/10 bg-surface/60 p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-text">
                            <?php echo $postForm['id'] ? 'Modifier article #' . (int) $postForm['id'] : 'Nouvel article'; ?>
                        </h2>
                        <?php if ($postForm['id']): ?>
                            <a href="<?php echo SITE_URL; ?>/admin-blog.php" class="text-xs font-semibold text-accent">+ nouvel article</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="save_post">
                        <input type="hidden" name="post_id" value="<?php echo (int) $postForm['id']; ?>">

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Titre *</label>
                                <input type="text" name="title" id="blog-title" value="<?php echo htmlspecialchars($postForm['title']); ?>" required class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Slug URL *</label>
                                <input type="text" name="slug" id="blog-slug" value="<?php echo htmlspecialchars($postForm['slug']); ?>" required class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Categorie</label>
                                <input type="text" name="category" list="blog-categories" value="<?php echo htmlspecialchars($postForm['category']); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" placeholder="Actualites">
                                <datalist id="blog-categories">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Tags (separes par virgule)</label>
                                <input type="text" name="tags" value="<?php echo htmlspecialchars($postForm['tags']); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text" placeholder="musique, culture, interview">
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-muted">Extrait</label>
                            <textarea name="excerpt" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text"><?php echo htmlspecialchars($postForm['excerpt']); ?></textarea>
                        </div>

                        <div class="editor-block">
                            <label class="text-xs font-semibold text-muted">Contenu *</label>
                            <div class="editor-wrapper mt-2">
                                <textarea name="content" id="content-editor" required><?php echo htmlspecialchars($postForm['content']); ?></textarea>
                                <div class="editor-toolbar-info">
                                    <div id="editor-word-count"><i class="fas fa-font me-1"></i>0 mots</div>
                                    <div class="editor-shortcuts">
                                        <span><kbd>Ctrl+B</kbd> Gras</span>
                                        <span><kbd>Ctrl+I</kbd> Italique</span>
                                        <span><kbd>Ctrl+U</kbd> Souligne</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Image de couverture (upload)</label>
                                <input type="file" name="featured_image_file" accept="image/*" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-xs font-semibold text-muted">Statut</label>
                                <select name="status" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <?php foreach (['draft' => 'Brouillon', 'published' => 'Publie', 'archived' => 'Archive'] as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($postForm['status'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Mode affichage</label>
                                <select name="display_mode" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                                    <?php foreach (['standard' => 'Standard', 'hero' => 'Hero', 'spotlight' => 'Spotlight', 'compact' => 'Compact'] as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo (($postForm['display_mode'] ?? 'standard') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Publication planifiee</label>
                                <input type="datetime-local" name="published_at" value="<?php echo htmlspecialchars($publishedAtValue); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">Video principale (URL)</label>
                                <input type="url" name="video_url" value="<?php echo htmlspecialchars($postForm['video_url'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Audio principal (URL)</label>
                                <input type="url" name="audio_url" value="<?php echo htmlspecialchars($postForm['audio_url'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-muted">Galerie d'images (une URL par ligne)</label>
                            <textarea name="gallery_images" rows="3" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text"><?php echo htmlspecialchars($galleryImages); ?></textarea>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold text-muted">SEO titre</label>
                                <input type="text" name="seo_title" value="<?php echo htmlspecialchars($postForm['seo_title'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">SEO description</label>
                                <input type="text" name="seo_description" value="<?php echo htmlspecialchars($postForm['seo_description'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="text-xs font-semibold text-muted">Social titre</label>
                                <input type="text" name="social_title" value="<?php echo htmlspecialchars($postForm['social_title'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Social description</label>
                                <input type="text" name="social_description" value="<?php echo htmlspecialchars($postForm['social_description'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted">Social image URL</label>
                                <input type="url" name="social_image" value="<?php echo htmlspecialchars($postForm['social_image'] ?? ''); ?>" class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="inline-flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="featured" class="h-4 w-4" <?php echo !empty($postForm['featured']) ? 'checked' : ''; ?>> Mettre en vedette</label>
                            <label class="inline-flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="allow_comments" class="h-4 w-4" <?php echo !empty($postForm['allow_comments']) ? 'checked' : ''; ?>> Autoriser commentaires</label>
                            <label class="inline-flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="show_toc" class="h-4 w-4" <?php echo $showToc ? 'checked' : ''; ?>> Afficher sommaire</label>
                            <label class="inline-flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="pin_to_top" class="h-4 w-4" <?php echo $pinToTop ? 'checked' : ''; ?>> Epingler en haut</label>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="submit" name="save_as_draft" class="rounded-full border border-white/15 bg-white/5 px-5 py-2 text-sm font-semibold text-text">Sauvegarder brouillon</button>
                            <button type="submit" name="publish_now" class="rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white shadow-elev-1">Publier</button>
                        </div>
                    </form>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6">
                        <h3 class="text-base font-semibold text-text">Nouvelle categorie</h3>
                        <form method="POST" class="mt-4 space-y-3">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="save_category">
                            <input type="text" name="category_name" required placeholder="Nom categorie" class="w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="category_icon" placeholder="fa-newspaper" class="rounded-xl border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                                <input type="text" name="category_color" value="#2F6DE0" class="rounded-xl border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                            </div>
                            <textarea name="category_description" rows="2" placeholder="Description" class="w-full rounded-xl border border-white/10 bg-bg px-3 py-2 text-sm text-text"></textarea>
                            <button class="w-full rounded-xl bg-white/10 px-3 py-2 text-xs font-semibold text-text">Enregistrer categorie</button>
                        </form>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-surface/60 p-6" id="blog-comments">
                        <h3 class="text-base font-semibold text-text">Moderation commentaires</h3>
                        <div class="mt-4 space-y-3">
                            <?php if (empty($pendingComments)): ?>
                                <p class="text-sm text-muted">Aucun commentaire en attente.</p>
                            <?php else: ?>
                                <?php foreach ($pendingComments as $comment): ?>
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($comment['author']); ?> - <?php echo htmlspecialchars(timeAgoFrench($comment['created_at'])); ?></p>
                                        <p class="mt-1 text-xs font-semibold text-text"><?php echo htmlspecialchars($comment['post_title']); ?></p>
                                        <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars($comment['content_short']); ?></p>
                                        <div class="mt-3 flex gap-2">
                                            <form method="POST">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="moderate_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">Approuver</button>
                                            </form>
                                            <form method="POST">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="moderate_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button class="rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-white">Rejeter</button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Supprimer ce commentaire ?');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                                                <button class="rounded-full bg-rose-600 px-3 py-1 text-xs font-semibold text-white">Supprimer</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="blog-library" class="mt-6 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-text">Articles existants</h3>
                    <form method="GET" class="flex flex-wrap gap-2">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Recherche..." class="rounded-full border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                        <select name="status" class="rounded-full border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                            <?php foreach (['all' => 'Tous statuts', 'published' => 'Publies', 'draft' => 'Brouillons', 'archived' => 'Archives'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($statusFilter === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="category" class="rounded-full border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                            <option value="all">Toutes categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php echo ($categoryFilter === $category['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="sort" class="rounded-full border border-white/10 bg-bg px-3 py-2 text-xs text-text">
                            <?php foreach (['updated_desc' => 'Maj recentes', 'recent' => 'Creation recente', 'popular' => 'Populaires', 'title_asc' => 'A-Z'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($sortFilter === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="rounded-full bg-white/10 px-4 py-2 text-xs font-semibold text-text">Filtrer</button>
                    </form>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm text-muted">
                        <thead class="border-b border-white/10 text-xs uppercase">
                            <tr>
                                <th class="py-3">Article</th>
                                <th class="py-3">Statut</th>
                                <th class="py-3">Vues</th>
                                <th class="py-3">Commentaires</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php if (empty($posts)): ?>
                                <tr><td colspan="5" class="py-4 text-center text-muted">Aucun article trouve.</td></tr>
                            <?php else: ?>
                                <?php foreach ($posts as $post): ?>
                                    <tr>
                                        <td class="py-3">
                                            <p class="font-semibold text-text"><?php echo htmlspecialchars($post['title']); ?></p>
                                            <p class="text-xs text-muted"><?php echo htmlspecialchars($post['category'] ?: 'Sans categorie'); ?> - <?php echo htmlspecialchars($post['author']); ?></p>
                                        </td>
                                        <td class="py-3">
                                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs"><?php echo htmlspecialchars($post['status']); ?></span>
                                        </td>
                                        <td class="py-3"><?php echo formatNumber($post['views_count']); ?></td>
                                        <td class="py-3"><?php echo formatNumber($post['comments_count']); ?></td>
                                        <td class="py-3">
                                            <div class="flex justify-end gap-2">
                                                <a href="<?php echo SITE_URL; ?>/admin-blog.php?edit=<?php echo (int) $post['id']; ?>" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">Editer</a>
                                                <a href="<?php echo htmlspecialchars($post['url']); ?>" target="_blank" rel="noopener" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">Voir</a>
                                                <form method="POST" onsubmit="return confirm('Supprimer cet article ?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete_post">
                                                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                                                    <button class="rounded-full bg-rose-600 px-3 py-1 text-xs font-semibold text-white">Supprimer</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
