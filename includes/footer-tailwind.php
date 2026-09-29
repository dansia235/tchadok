    <?php if (empty($hideFooter)): ?>
    <footer class="site-footer mt-16 border-t border-white/10 bg-bg">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <div class="site-footer-brand flex items-center gap-2 text-lg font-display font-semibold text-text">
                        <svg class="site-footer-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                        </svg>
                        <span class="site-footer-brand-text">Tchadok</span>
                    </div>
                    <p class="mt-4 max-w-md text-sm text-muted">
                        La première plateforme dédiée à la musique tchadienne. Découvrez,
                        écoutez et soutenez nos artistes locaux.
                    </p>
                    <div class="mt-4 flex items-center gap-3 text-muted">
                        <a href="<?php echo FACEBOOK_URL ?? '#'; ?>" aria-label="Facebook" class="site-footer-link rounded-full border border-white/10 p-2 hover:text-text"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?php echo TWITTER_URL ?? '#'; ?>" aria-label="Twitter" class="site-footer-link rounded-full border border-white/10 p-2 hover:text-text"><i class="fab fa-twitter"></i></a>
                        <a href="<?php echo INSTAGRAM_URL ?? '#'; ?>" aria-label="Instagram" class="site-footer-link rounded-full border border-white/10 p-2 hover:text-text"><i class="fab fa-instagram"></i></a>
                        <a href="<?php echo YOUTUBE_URL ?? '#'; ?>" aria-label="YouTube" class="site-footer-link rounded-full border border-white/10 p-2 hover:text-text"><i class="fab fa-youtube"></i></a>
                        <a href="#" aria-label="TikTok" class="site-footer-link rounded-full border border-white/10 p-2 hover:text-text"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div>
                    <h5 class="text-sm font-semibold text-text">Plateforme</h5>
                    <ul class="mt-4 space-y-2 text-sm text-muted">
                        <li><a href="<?php echo SITE_URL; ?>/decouvrir.php" class="site-footer-link hover:text-text">Découvrir</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/artists.php" class="site-footer-link hover:text-text">Artistes</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/albums.php" class="site-footer-link hover:text-text">Albums</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/genres.php" class="site-footer-link hover:text-text">Genres</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/radio-live.php" class="site-footer-link hover:text-text">Radio Live</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-sm font-semibold text-text">Artistes</h5>
                    <ul class="mt-4 space-y-2 text-sm text-muted">
                        <li><a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="site-footer-link hover:text-text">Devenir Artiste</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/publier.php" class="site-footer-link hover:text-text">Upload Music</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="site-footer-link hover:text-text">Analytics</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/premium.php" class="site-footer-link hover:text-text">Promotions</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-sm font-semibold text-text">Support</h5>
                    <ul class="mt-4 space-y-2 text-sm text-muted">
                        <li><a href="<?php echo SITE_URL; ?>/aide.php" class="site-footer-link hover:text-text">Aide</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php" class="site-footer-link hover:text-text">Contact</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/conditions.php" class="site-footer-link hover:text-text">Conditions</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/confidentialite.php" class="site-footer-link hover:text-text">Confidentialité</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-col gap-2 border-t border-white/10 pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>© <?php echo date('Y'); ?> Tchadok. Tous droits réservés.</p>
                <p>Développé avec passion pour la musique tchadienne.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <?php
    $themeToggleVersion = @filemtime(__DIR__ . '/../assets/js/theme-toggle.js') ?: time();
    $playerJsVersion = @filemtime(__DIR__ . '/../assets/js/player.js') ?: time();
    $panierJsVersion = @filemtime(__DIR__ . '/../assets/js/panier.js') ?: time();
    ?>
    <script src="<?php echo SITE_URL; ?>/assets/js/theme-toggle.js?v=<?php echo $themeToggleVersion; ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/player.js?v=<?php echo $playerJsVersion; ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/panier.js?v=<?php echo $panierJsVersion; ?>"></script>

    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
