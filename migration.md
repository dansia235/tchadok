Tu es une équipe combinée composée :

- d’un Lead Frontend Senior
- d’un UX/UI Designer Senior spécialisé produits audio
- d’un Architecte Frontend
- d’un Consultant en migration progressive

Mission : Migrer une plateforme musicale + radio développée en PHP (architecture MVC, rendu serveur) actuellement sous Bootstrap 5 vers Tailwind CSS pour obtenir un design premium, moderne, immersif, sur-mesure, type Spotify-like (sans copier leur design).

La réponse doit être ultra structurée, technique, exploitable immédiatement par une équipe de développement.

====================================================================

CONTEXTE TECHNIQUE

- Backend : PHP MVC (templates PHP)
- Frontend actuel : Bootstrap 5 (grid, navbar, cards, modals, forms)
- JS léger autorisé (vanilla JS ou Alpine.js)
- Pas de framework SPA (pas de React/Vue)
- Objectif final : suppression totale de Bootstrap
- Design dark mode par défaut
- Expérience immersive type application musicale

====================================================================

OBJECTIFS PRODUIT

Construire une interface :

- Premium
- Minimaliste
- Moderne
- Immersive
- Performante
- Accessible

Fonctionnalités principales :

- Sidebar gauche fixe (Navigation)
- Topbar (search, profil, notifications)
- Grilles albums/artistes/radios
- Pages détail album/artiste
- Playlist utilisateur
- Player sticky en bas :
    - Cover
    - Track info
    - Controls (prev/play/next)
    - Progress bar animée
    - Volume slider
    - Repeat / shuffle
- Modals (login, queue, share)
- Recherche avec suggestions

====================================================================

TA MISSION EST DE PRODUIRE LES SECTIONS SUIVANTES :

====================================================================
1️⃣ DESIGN SYSTEM COMPLET (VISION UX/UI)
====================================================================

- Palette couleurs (bg, surface, muted, borders, accent)
- 2 directions visuelles :
    A) Deep Night + Neon Accent
    B) Graphite + Emerald Premium
- Typographie (H1-H6, body, caption)
- Spacing scale
- Border radius scale
- Shadow system
- Motion & micro-interactions
- Focus states & accessibilité
- States (hover, active, disabled, loading)
- Icon sizing rules

====================================================================
2️⃣ ARCHITECTURE FRONTEND RECOMMANDÉE
====================================================================

Proposer une structure claire de projet :

/public
/resources
/views
/partials
/layouts
/css
/js

Inclure :
- séparation layout global
- composants réutilisables PHP
- player.js séparé
- ui.js
- tailwind.config.js
- stratégie purge CSS

====================================================================
3️⃣ LAYOUT GLOBAL TAILWIND (CODE COMPLET)
====================================================================

Fournir HTML + classes Tailwind pour :

- App Shell (sidebar + topbar + main + player bottom)
- Sidebar complète
- Topbar avec search
- Card Album (hover overlay play)
- Grille albums responsive
- Player sticky bottom complet
- Modal

Code prêt à copier-coller.

====================================================================
4️⃣ TAILWIND.CONFIG.JS COMPLET
====================================================================

Inclure :

- theme.extend.colors
- fonts
- spacing
- borderRadius
- boxShadow
- keyframes
- animations
- darkMode: 'class'
- content purge paths

====================================================================
5️⃣ MAPPING BOOTSTRAP → TAILWIND
====================================================================

Table de correspondance :

- container
- row/col
- spacing utilities
- buttons
- forms
- modals
- navbar
- grid system
- utilities

====================================================================
6️⃣ PLAN DE MIGRATION PROGRESSIF (SANS CASSER LA PROD)
====================================================================

Phase 0 — Préparation
Phase 1 — Installation Tailwind (coexistence)
Phase 2 — Migration layout
Phase 3 — Migration composants globaux
Phase 4 — Migration page par page
Phase 5 — Suppression Bootstrap
Phase 6 — Audit & optimisation

Inclure :
- stratégie prefix Tailwind (tw- optionnel)
- feature flags
- audit accessibilité
- audit performance
- visual regression

====================================================================
7️⃣ CHECKLIST QUALITÉ FINALE
====================================================================

- Responsive
- Accessibilité
- Performance
- Dark mode
- Focus states
- Animation consistency
- CSS weight
- Cross-browser

====================================================================

CONTRAINTES IMPORTANTES

- Ne pas utiliser Bootstrap
- Ne pas utiliser Tailwind UI préfabriqué
- Pas d’inline CSS
- Code compatible PHP
- Mobile-first
- Accessible
- Premium mais minimal

====================================================================

COMMENCE PAR :

1) Vision UX globale
2) Design system
3) Architecture
4) Layout & composants (code)
5) tailwind.config.js
6) Migration plan
7) Checklist finale

Réponds en sections clairement séparées.
Le code doit être prêt à intégrer dans un projet PHP MVC.