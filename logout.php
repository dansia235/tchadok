<?php
/**
 * Deconnexion - Tchadok Platform
 *
 * SEC-09 : deconnexion en POST, avec jeton CSRF.
 *
 * Deux defauts corriges :
 *
 * 1. La deconnexion se faisait par un simple lien GET. N'importe quel site
 *    pouvait deconnecter un utilisateur a son insu par une balise
 *    <img src=".../logout.php">, la garde CSRF ne couvrant par construction
 *    que les requetes modifiantes.
 *
 * 2. La deconnexion ne deconnectait pas les utilisateurs ayant coche
 *    "se souvenir de moi" : la session etait detruite, mais pas le cookie
 *    remember_token, et checkRememberMe() les reconnectait des la requete
 *    suivante. Le cookie et sa trace en base sont desormais supprimes.
 *
 * Un GET (ancien lien, favori) affiche une page de confirmation plutot
 * qu'une erreur. Le POST est verifie par includes/csrf-guard.php avant
 * d'arriver ici.
 */

require_once __DIR__ . '/includes/functions.php';

/**
 * Destination apres deconnexion : la page precedente si elle appartient au
 * site, l'accueil sinon. Un Referer d'un autre site ne doit pas transformer
 * cette page en redirection ouverte.
 */
function destinationApresDeconnexion(): string
{
    $accueil = rtrim((string) SITE_URL, '/') . '/index.php';
    $precedente = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($precedente === '' || str_contains($precedente, 'logout.php')) {
        return $accueil;
    }
    $hoteSite = parse_url((string) SITE_URL, PHP_URL_HOST);
    $hoteRef  = parse_url($precedente, PHP_URL_HOST);
    return ($hoteSite !== null && $hoteRef === $hoteSite) ? $precedente : $accueil;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Deja deconnecte : rien a confirmer.
    if (!isLoggedIn()) {
        header('Location: ' . rtrim((string) SITE_URL, '/') . '/index.php');
        exit;
    }

    $pageTitle = 'Deconnexion';
    $pageDescription = 'Confirmer la deconnexion';
    include __DIR__ . '/includes/header-tailwind.php';
    ?>
    <main class="min-h-[60vh] pt-28 pb-16">
        <div class="mx-auto max-w-md px-4 sm:px-6">
            <div class="rounded-3xl border border-white/10 bg-surface/70 p-8 text-center shadow-elev-2">
                <h1 class="text-xl font-display font-semibold text-text">Se deconnecter ?</h1>
                <p class="mt-3 text-sm text-muted">Vous allez quitter votre session Tchadok sur cet appareil.</p>
                <form method="POST" action="<?php echo htmlspecialchars(rtrim((string) SITE_URL, '/') . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>" class="mt-6">
                    <?php echo csrfField(); ?>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white shadow-elev-1">
                        <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Se deconnecter
                    </button>
                </form>
                <a href="<?php echo htmlspecialchars(rtrim((string) SITE_URL, '/') . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" class="mt-4 inline-block text-sm text-muted hover:text-text">Annuler</a>
            </div>
        </div>
    </main>
    <?php
    include __DIR__ . '/includes/footer-tailwind.php';
    exit;
}

// ---------------------------------------------------------------------
// POST : deconnexion effective (jeton deja verifie par la garde CSRF)
// ---------------------------------------------------------------------
$destination = destinationApresDeconnexion();
$userId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;

$db = TchadokDatabase::getInstance()->getConnection();
if ($db) {
    try {
        if ($userId !== null) {
            // Sans cela, checkRememberMe() reconnectait l'utilisateur des la
            // requete suivante. La refonte complete du mecanisme releve de SEC-11.
            $db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$userId]);
        }
        if (!empty($_SESSION['session_id'])) {
            $db->prepare('DELETE FROM user_sessions WHERE id = ?')->execute([$_SESSION['session_id']]);
        }
    } catch (Throwable $e) {
        error_log('[Tchadok][logout] ' . $e->getMessage());
    }
}

// Cookie "se souvenir de moi"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => EnvLoader::bool('SESSION_SECURE', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . $destination);
exit;
