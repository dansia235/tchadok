<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/blog-manager.php';
require_once 'assets/images/placeholders.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = max(0, (int) ($_GET['id'] ?? 0));
$allowDraft = isLoggedIn() && isAdmin();

$post = null;
if ($slug !== '') {
    $post = blogGetPostBySlug($slug, $allowDraft);
} elseif ($id > 0) {
    $post = blogGetPostById($id, $allowDraft);
}

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Article introuvable';
    $pageDescription = 'Le contenu demande est indisponible.';
    include 'includes/header-tailwind.php';
    ?>
    <main class="pt-24">
        <section class="py-16">
            <div class="mx-auto max-w-3xl px-4 text-center">
                <h1 class="text-3xl font-display font-bold text-text">Article introuvable</h1>
                <p class="mt-3 text-muted">Le lien est invalide ou l'article n'est pas encore publie.</p>
                <a href="<?php echo SITE_URL; ?>/blog.php" class="mt-6 inline-flex rounded-full bg-accent px-5 py-2 text-sm font-semibold text-white">Retour au blog</a>
            </div>
        </section>
    </main>
    <?php
    include 'includes/footer-tailwind.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    if (!isLoggedIn()) {
        setFlashMessage(FLASH_ERROR, "Connectez-vous pour commenter.");
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage(FLASH_ERROR, "Session invalide. Merci de recommencer.");
        header('Location: ' . blogPostUrl($post) . '#comments');
        exit();
    }

    // MOD-07 : commenter exige une adresse confirmee.
    require_once __DIR__ . '/includes/comptes.php';
    if (!Comptes::emailVerifie((int) $_SESSION['user_id'])) {
        setFlashMessage(FLASH_ERROR, 'Confirmez votre adresse e-mail pour commenter.');
        header('Location: ' . SITE_URL . '/verifier-email.php?retour=' . urlencode(parse_url(blogPostUrl($post), PHP_URL_PATH) ?: '/blog.php'));
        exit();
    }

    $commentResult = blogCreateComment(
        (int) $post['id'],
        (int) ($_SESSION['user_id'] ?? 0),
        $_POST['comment_content'] ?? ''
    );
    setFlashMessage($commentResult['success'] ? FLASH_SUCCESS : FLASH_ERROR, $commentResult['message']);
    header('Location: ' . blogPostUrl($post) . '#comments');
    exit();
}

blogRegisterView((int) $post['id']);
$post = blogGetPostById((int) $post['id'], $allowDraft);

$comments = blogGetCommentsForPost((int) $post['id'], 'approved', 300);
$related = blogGetRelatedPosts((int) $post['id'], $post['category'] ?? '', 3);
$shareLinks = blogGetShareLinks($post);
$mainImage = blogMakeAbsoluteUrl($post['featured_image'] ?? '');
$socialImage = blogMakeAbsoluteUrl($post['social_image'] ?: ($post['featured_image'] ?? ''));
$renderedContent = blogSanitizeHtml($post['content'] ?? '');

$pageTitle = $post['seo_title'] ?: $post['title'];
$pageDescription = $post['seo_description'] ?: $post['excerpt'];
$pageCanonical = blogPostUrl($post);
$pageImage = $socialImage ?: '';

$additionalCSS = [
    SITE_URL . '/assets/css/blog-tailwind.css'
];
$additionalJS = [
    SITE_URL . '/assets/js/blog.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <a href="<?php echo SITE_URL; ?>/blog.php" class="inline-flex items-center gap-2 text-xs font-semibold text-accent"><i class="fas fa-arrow-left"></i> Retour au blog</a>
            <div class="mt-4 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <p class="text-xs uppercase tracking-[0.2em] text-muted"><?php echo htmlspecialchars($post['category'] ?: 'Article'); ?></p>
                <h1 class="mt-2 text-3xl font-display font-bold text-text"><?php echo htmlspecialchars($post['title']); ?></h1>
                <p class="mt-3 text-sm text-muted"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-muted">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author']); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($post['read_time']); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars(date('d/m/Y', strtotime($post['published_at'] ?: $post['created_at']))); ?></span>
                    <span><i class="fas fa-eye"></i> <?php echo formatNumber($post['views_count']); ?></span>
                    <span><i class="fas fa-comment"></i> <?php echo formatNumber($post['comments_count']); ?></span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" href="<?php echo htmlspecialchars($shareLinks['facebook']); ?>" target="_blank" rel="noopener" data-share-track="<?php echo (int) $post['id']; ?>" data-share-platform="facebook"><i class="fab fa-facebook-f"></i></a>
                    <a class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" href="<?php echo htmlspecialchars($shareLinks['x']); ?>" target="_blank" rel="noopener" data-share-track="<?php echo (int) $post['id']; ?>" data-share-platform="x"><i class="fab fa-x-twitter"></i></a>
                    <a class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" href="<?php echo htmlspecialchars($shareLinks['whatsapp']); ?>" target="_blank" rel="noopener" data-share-track="<?php echo (int) $post['id']; ?>" data-share-platform="whatsapp"><i class="fab fa-whatsapp"></i></a>
                    <button type="button" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text" data-share-url="<?php echo htmlspecialchars(blogPostUrl($post)); ?>" data-share-title="<?php echo htmlspecialchars($post['title']); ?>" data-share-post="<?php echo (int) $post['id']; ?>" data-share-platform="copy"><i class="fas fa-link"></i></button>
                </div>
            </div>

            <article class="article-content mt-6 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <?php if ($mainImage !== ''): ?>
                    <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="mb-6 h-auto w-full rounded-2xl object-cover">
                <?php endif; ?>

                <?php echo $renderedContent; ?>

                <?php if (!empty($post['video_url'])): ?>
                    <div class="mt-6">
                        <h3>Video</h3>
                        <p><a href="<?php echo htmlspecialchars($post['video_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($post['video_url']); ?></a></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($post['audio_url'])): ?>
                    <div class="mt-6">
                        <h3>Audio</h3>
                        <audio controls preload="none" src="<?php echo htmlspecialchars($post['audio_url']); ?>"></audio>
                    </div>
                <?php endif; ?>
            </article>

            <?php if (!empty($post['tags_list'])): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($post['tags_list'] as $tag): ?>
                        <a href="<?php echo SITE_URL; ?>/blog.php?tag=<?php echo urlencode(blogSlugify($tag)); ?>" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">#<?php echo htmlspecialchars($tag); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section id="comments" class="mt-8 rounded-3xl border border-white/10 bg-surface/60 p-6">
                <h2 class="text-xl font-semibold text-text">Commentaires (<?php echo formatNumber($post['comments_count']); ?>)</h2>
                <?php if ((int) $post['allow_comments'] === 1): ?>
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" class="mt-4 space-y-3">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="add_comment">
                            <textarea name="comment_content" rows="4" required class="w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text" placeholder="Votre commentaire..."></textarea>
                            <button class="rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Publier</button>
                        </form>
                    <?php else: ?>
                        <p class="mt-4 text-sm text-muted">Connectez-vous pour laisser un commentaire.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-4 text-sm text-muted">Les commentaires sont desactives pour cet article.</p>
                <?php endif; ?>

                <div class="mt-6 space-y-4">
                    <?php if (empty($comments)): ?>
                        <p class="text-sm text-muted">Aucun commentaire approuve pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-10 w-10 overflow-hidden rounded-full border border-white/10 bg-white/5"><?php echo createAvatarPlaceholder($comment['author'], '#2F6DE0'); ?></span>
                                    <div>
                                        <p class="text-sm font-semibold text-text"><?php echo htmlspecialchars($comment['author']); ?></p>
                                        <p class="text-xs text-muted"><?php echo htmlspecialchars($comment['time_label']); ?></p>
                                    </div>
                                </div>
                                <p class="mt-3 whitespace-pre-line text-sm text-muted"><?php echo htmlspecialchars($comment['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($related)): ?>
                <section class="mt-8">
                    <h2 class="text-xl font-semibold text-text">Articles similaires</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($related as $item): ?>
                            <article class="rounded-2xl border border-white/10 bg-surface/60 p-4">
                                <h3 class="text-base font-semibold text-text"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="mt-2 text-xs text-muted"><?php echo htmlspecialchars($item['excerpt']); ?></p>
                                <a href="<?php echo htmlspecialchars($item['url']); ?>" class="mt-3 inline-flex text-xs font-semibold text-accent">Lire</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
