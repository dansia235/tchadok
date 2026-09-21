<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/blog-manager.php';
require_once 'assets/images/placeholders.php';

$pageTitle = 'Blog Musical';
$pageDescription = 'Actualités, interviews, analyses et chroniques de la scène musicale tchadienne.';

$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = trim((string) ($_GET['sort'] ?? 'recent'));
$categoryFilter = trim((string) ($_GET['category'] ?? 'all'));
$tagFilter = trim((string) ($_GET['tag'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));
$perPage = 9;
$offset = ($page - 1) * $perPage;

if (!in_array($sort, ['recent', 'popular', 'oldest', 'featured'], true)) {
    $sort = 'recent';
}

$filters = [
    'sort' => $sort,
    'category' => $categoryFilter,
    'tag' => $tagFilter,
    'q' => $query
];

$stats = blogGetAdminStats();
$categories = blogFetchCategories(true);
$popularTags = blogFetchPopularTags(16);
$totalPosts = blogCountPostsPublic($filters);
$articles = blogFetchPostsPublic($filters + ['limit' => $perPage, 'offset' => $offset]);
$featuredPosts = blogFetchPostsPublic([
    'featured_only' => true,
    'sort' => 'featured',
    'limit' => 3
]);
if (empty($featuredPosts)) {
    $featuredPosts = blogFetchPostsPublic(['sort' => 'popular', 'limit' => 3]);
}

$totalPages = max(1, (int) ceil($totalPosts / $perPage));
$featuredMain = $featuredPosts[0] ?? null;
$featuredSecondary = array_slice($featuredPosts, 1, 2);
if ($page === 1 && !empty($featuredPosts) && !empty($articles)) {
    $featuredIds = array_map(function ($item) {
        return (int) ($item['id'] ?? 0);
    }, $featuredPosts);
    $articles = array_values(array_filter($articles, function ($article) use ($featuredIds) {
        return !in_array((int) ($article['id'] ?? 0), $featuredIds, true);
    }));
}

$buildUrl = function (array $overrides = []) use ($sort, $categoryFilter, $tagFilter, $query, $page) {
    $params = [
        'sort' => $sort,
        'category' => $categoryFilter,
        'tag' => $tagFilter,
        'q' => $query,
        'page' => $page
    ];
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || $value === 'all') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    $queryString = http_build_query($params);
    return SITE_URL . '/blog.php' . ($queryString ? '?' . $queryString : '');
};

$renderCover = function (array $post, $color = '#2F6DE0') {
    $image = blogMakeAbsoluteUrl($post['featured_image'] ?? '');
    if ($image !== '') {
        return '<img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($post['title']) . '" class="h-full w-full object-cover">';
    }
    return str_replace('class="img-fluid"', 'class="h-full w-full object-cover"', createBlogThumbnail($post['title'], $post['category'] ?: 'Article', $color));
};

$additionalCSS = [
    SITE_URL . '/assets/css/blog-tailwind.css'
];
$additionalJS = [
    SITE_URL . '/assets/js/blog.js'
];

include 'includes/header-tailwind.php';
?>

<main class="pt-20">
    <section class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-white/10 bg-surface/60 p-8">
                <p class="text-xs uppercase tracking-[0.2em] text-muted">Blog Tchadok</p>
                <h1 class="mt-2 text-3xl font-display font-bold text-text sm:text-4xl">Articles, podcasts, émissions et culture</h1>
                <p class="mt-3 max-w-3xl text-sm text-muted">Publiez du contenu média enrichi et classez les articles par popularité, catégorie et tags.</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Articles</p>
                        <p class="mt-1 text-2xl font-semibold text-text"><?php echo formatNumber($stats['published_posts']); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Vues</p>
                        <p class="mt-1 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_views']); ?></p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-center">
                        <p class="text-xs uppercase tracking-[0.2em] text-muted">Commentaires</p>
                        <p class="mt-1 text-2xl font-semibold text-text"><?php echo formatNumber($stats['total_comments']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="GET" class="grid gap-3 rounded-3xl border border-white/10 bg-surface/60 p-5 md:grid-cols-4">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Rechercher un article..." class="rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                <select name="sort" class="rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                    <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Plus populaires</option>
                    <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Mise en avant</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Plus anciens</option>
                </select>
                <select name="category" class="rounded-2xl border border-white/10 bg-bg px-4 py-2 text-sm text-text">
                    <option value="all">Toutes catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php echo $categoryFilter === $category['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?> (<?php echo (int) $category['posts_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="rounded-2xl bg-accent px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
            </form>

            <?php if (!empty($popularTags)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($popularTags as $tag): ?>
                        <a href="<?php echo htmlspecialchars($buildUrl(['tag' => $tag['slug'], 'page' => 1])); ?>" class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-text">
                            #<?php echo htmlspecialchars($tag['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($featuredMain)): ?>
    <section class="pb-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-3">
                <article class="lg:col-span-2 rounded-3xl border border-white/10 bg-surface/60 p-4">
                    <div class="aspect-[16/9] overflow-hidden rounded-2xl bg-white/5"><?php echo $renderCover($featuredMain, '#2F6DE0'); ?></div>
                    <div class="mt-4">
                        <p class="text-xs text-muted"><?php echo htmlspecialchars($featuredMain['category'] ?: 'Article'); ?> - <?php echo htmlspecialchars($featuredMain['date_label']); ?></p>
                        <h2 class="mt-1 text-2xl font-semibold text-text"><?php echo htmlspecialchars($featuredMain['title']); ?></h2>
                        <p class="mt-2 text-sm text-muted"><?php echo htmlspecialchars($featuredMain['excerpt']); ?></p>
                        <a href="<?php echo htmlspecialchars($featuredMain['url']); ?>" class="mt-4 inline-flex rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Lire l'article</a>
                    </div>
                </article>
                <div class="space-y-4">
                    <?php foreach ($featuredSecondary as $secondary): ?>
                        <article class="rounded-3xl border border-white/10 bg-surface/60 p-4">
                            <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-white/5"><?php echo $renderCover($secondary, '#FFC107'); ?></div>
                            <h3 class="mt-3 text-base font-semibold text-text"><?php echo htmlspecialchars($secondary['title']); ?></h3>
                            <p class="mt-1 text-xs text-muted"><?php echo htmlspecialchars($secondary['read_time']); ?></p>
                            <a href="<?php echo htmlspecialchars($secondary['url']); ?>" class="mt-3 inline-flex text-xs font-semibold text-accent">Voir</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="pb-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-display font-semibold text-text">Articles (<?php echo formatNumber($totalPosts); ?>)</h2>
                <?php if ($tagFilter !== ''): ?>
                    <a href="<?php echo htmlspecialchars($buildUrl(['tag' => null, 'page' => 1])); ?>" class="text-xs font-semibold text-accent">Retirer tag #<?php echo htmlspecialchars($tagFilter); ?></a>
                <?php endif; ?>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <?php if (empty($articles)): ?>
                    <div class="col-span-full rounded-3xl border border-white/10 bg-surface/60 p-8 text-center text-muted">Aucun article ne correspond à vos filtres.</div>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <?php
                            $mode = $article['display_mode'] ?? 'standard';
                            $cardClasses = 'rounded-3xl border border-white/10 bg-surface/60 p-4';
                            if ($mode === 'hero') {
                                $cardClasses .= ' sm:col-span-2';
                            } elseif ($mode === 'spotlight') {
                                $cardClasses .= ' ring-1 ring-accent/40';
                            }
                            $excerpt = $article['excerpt'];
                            if ($mode === 'compact') {
                                $excerpt = truncateText($excerpt, 95);
                            }
                        ?>
                        <article class="<?php echo $cardClasses; ?>">
                            <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-white/5"><?php echo $renderCover($article, '#2F6DE0'); ?></div>
                            <p class="mt-3 text-xs text-muted"><?php echo htmlspecialchars($article['category'] ?: 'Article'); ?> - <?php echo htmlspecialchars($article['date_label']); ?></p>
                            <h3 class="mt-1 text-lg font-semibold text-text"><?php echo htmlspecialchars($article['title']); ?></h3>
                            <p class="mt-2 text-sm text-muted"><?php echo htmlspecialchars($excerpt); ?></p>
                            <div class="mt-4 flex items-center justify-between text-xs text-muted">
                                <span><i class="fas fa-eye"></i> <?php echo formatNumber($article['views_count']); ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo formatNumber($article['comments_count']); ?></span>
                                <button type="button" class="interaction-btn rounded-full border border-white/10 bg-white/5 px-3 py-1 text-text" data-share-url="<?php echo htmlspecialchars($article['url']); ?>" data-share-title="<?php echo htmlspecialchars($article['title']); ?>" data-share-post="<?php echo (int) $article['id']; ?>" data-share-platform="copy">
                                    <i class="fas fa-share-alt"></i>
                                </button>
                            </div>
                            <a href="<?php echo htmlspecialchars($article['url']); ?>" class="mt-4 inline-flex rounded-full bg-accent px-4 py-2 text-xs font-semibold text-white">Lire</a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" href="<?php echo htmlspecialchars($buildUrl(['page' => $page - 1])); ?>">Précédent</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a class="rounded-full px-3 py-1 text-xs <?php echo $i === $page ? 'bg-accent text-white' : 'border border-white/10 bg-white/5 text-text'; ?>" href="<?php echo htmlspecialchars($buildUrl(['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-text" href="<?php echo htmlspecialchars($buildUrl(['page' => $page + 1])); ?>">Suivant</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer-tailwind.php'; ?>
