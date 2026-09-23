<?php
/**
 * Question de verification pour les formulaires publics (SEC-12, point 4).
 *
 * Volontairement modeste : une addition a resoudre, generee et verifiee sur le
 * serveur. Pas de service tiers -- la reponse ne sort pas du site, aucune
 * donnee n'est transmise a un prestataire, et la page reste lisible sans
 * JavaScript. Elle n'apparait qu'au-dela d'un certain nombre d'envois depuis
 * la meme adresse : un visiteur ordinaire ne la voit jamais.
 */

declare(strict_types=1);

require_once __DIR__ . '/rate-limit.php';

final class Captcha
{
    /** Envois toleres depuis une adresse avant que la question apparaisse. */
    private const SEUIL = [
        'inscription' => 2,
        'contact'     => 2,
    ];

    public static function requis(string $action): bool
    {
        if (!LimiteDebit::active() || PHP_SAPI === 'cli') {
            return false;
        }

        return LimiteDebit::compte($action) >= (self::SEUIL[$action] ?? 3);
    }

    /**
     * Question a afficher. La reponse attendue est gardee en session : elle ne
     * transite jamais par la page.
     */
    public static function question(string $action): string
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['captcha'][$action] = $a + $b;
        }

        return "Combien font {$a} + {$b} ?";
    }

    /**
     * Verifie la reponse envoyee, puis l'oublie : une reponse ne sert qu'une
     * fois, sinon elle se rejouerait indefiniment.
     */
    public static function verifier(string $action, string $reponse): bool
    {
        $attendue = $_SESSION['captcha'][$action] ?? null;
        unset($_SESSION['captcha'][$action]);

        if ($attendue === null || trim($reponse) === '' || !is_numeric(trim($reponse))) {
            return false;
        }

        return (int) trim($reponse) === (int) $attendue;
    }

    /**
     * Champ pret a inserer dans un formulaire.
     */
    public static function champ(string $action): string
    {
        $question = htmlspecialchars(self::question($action), ENT_QUOTES, 'UTF-8');
        $id = 'captcha_' . preg_replace('/[^a-z]/', '', $action);

        return '<div class="mt-4">'
            . '<label for="' . $id . '" class="text-sm font-semibold text-text">' . $question . '</label>'
            . '<p class="mt-1 text-xs text-muted">Une verification simple, demandee apres plusieurs envois.</p>'
            . '<input type="text" inputmode="numeric" autocomplete="off" required'
            . ' class="mt-2 w-full rounded-2xl border border-white/10 bg-bg px-4 py-3 text-sm text-text focus:outline-none focus:ring-2 focus:ring-accent/60"'
            . ' id="' . $id . '" name="captcha">'
            . '</div>';
    }
}
