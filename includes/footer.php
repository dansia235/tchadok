    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-logo">
                        <svg width="30" height="30" class="me-2" style="vertical-align: middle;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="#FFD700"/>
                            <path d="M30 45 L30 55 L40 60 L40 40 Z M45 35 L45 65 L55 70 L55 30 Z M60 40 L60 60 L70 55 L70 45 Z" fill="#2C3E50"/>
                        </svg>Tchadok
                    </div>
                    <p>La première plateforme dédiée à la musique tchadienne. Découvrez, écoutez et soutenez nos artistes locaux.</p>
                    <div class="social-icons">
                        <a href="<?php echo FACEBOOK_URL ?? '#'; ?>"><i class="fab fa-facebook"></i></a>
                        <a href="<?php echo TWITTER_URL ?? '#'; ?>"><i class="fab fa-twitter"></i></a>
                        <a href="<?php echo INSTAGRAM_URL ?? '#'; ?>"><i class="fab fa-instagram"></i></a>
                        <a href="<?php echo YOUTUBE_URL ?? '#'; ?>"><i class="fab fa-youtube"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Plateforme</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/#decouvrir" class="text-white-50">Découvrir</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/artists.php" class="text-white-50">Artistes</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/albums.php" class="text-white-50">Albums</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/genres.php" class="text-white-50">Genres</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/#radio" class="text-white-50">Radio Live</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Artistes</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="text-white-50">Devenir Artiste</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/upload.php" class="text-white-50">Upload Music</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/artist-dashboard.php" class="text-white-50">Analytics</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/premium.php" class="text-white-50">Promotions</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>/aide.php" class="text-white-50">Aide</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php" class="text-white-50">Contact</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/conditions.php" class="text-white-50">Conditions</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/confidentialite.php" class="text-white-50">Confidentialité</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5>Mobile Money</h5>
                    <ul class="list-unstyled">
                        <li><span class="text-white-50">Airtel Money</span></li>
                        <li><span class="text-white-50">Moov Money</span></li>
                        <li><span class="text-white-50">Ecobank</span></li>
                        <li><span class="text-white-50">VISA / GIMAC</span></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 bg-white-50">
            
            <div class="text-center">
                <p class="mb-0">© <?php echo date('Y'); ?> Tchadok. Tous droits réservés. Développé avec ❤️ pour la musique tchadienne 🇹🇩</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        /* Footer styles from tchadok-homepage.html */
        footer {
            background: #2C3E50;
            color: white;
            padding: 3rem 0 2rem;
            margin-top: 5rem;
        }
        
        .footer-logo {
            font-size: 2rem;
            font-weight: 900;
            color: #FFD700;
            margin-bottom: 1rem;
        }
        
        .social-icons a {
            color: white;
            font-size: 1.5rem;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }
        
        .social-icons a:hover {
            color: #FFD700;
            transform: translateY(-3px);
        }
        
        footer h5 {
            color: white;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        footer .list-unstyled li {
            margin-bottom: 0.5rem;
        }
        
        footer .text-white-50 {
            color: rgba(255, 255, 255, 0.6) !important;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        footer .text-white-50:hover {
            color: #FFD700 !important;
        }
    </style>

</body>
</html>