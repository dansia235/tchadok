<?php
/**
 * Partage de ressources entre origines (SEC-18).
 *
 * AVANT
 *   Le .htaccess posait Access-Control-Allow-Origin "*" sur TOUTES les
 *   reponses, pages HTML comprises, et six API le reposaient chacune de leur
 *   cote. N'importe quel site pouvait donc lire les reponses de l'API depuis
 *   le navigateur de ses visiteurs.
 *
 * APRES
 *   Aucune API n'ouvre par defaut. Celles qui servent du contenu public en
 *   lecture declarent explicitement une ouverture, limitee aux origines de
 *   CORS_ALLOWED_ORIGINS. Liste vide -- le cas normal, y compris en
 *   production -- signifie : meme origine uniquement.
 *
 *   Jamais de credentials avec une origine ouverte : le navigateur interdit
 *   de toute facon la combinaison "*" + cookies, et repondre une origine
 *   precise AVEC cookies reviendrait a exposer les sessions.
 *
 * L'APPLICATION ANDROID N'A PAS BESOIN DE CORS : c'est une regle appliquee
 * par les navigateurs, pas par un client natif.
 */

declare(strict_types=1);

final class Cors
{
    /**
     * Ouvre l'endpoint aux origines declarees, pour de la lecture publique.
     *
     * @param string[] $methodes methodes autorisees
     */
    public static function ouvrirEnLecture(array $methodes = ['GET']): void
    {
        $origine = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));

        // La reponse depend de l'origine : sans Vary, un cache partage
        // servirait la reponse d'une origine a une autre.
        if (!headers_sent()) {
            header('Vary: Origin', false);
        }

        if ($origine === '' || !in_array($origine, self::originesAutorisees(), true)) {
            // Aucune ouverture : le navigateur appliquera la meme origine.
            self::terminerPrevol(403);
            return;
        }

        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: ' . $origine);
            header('Access-Control-Allow-Methods: ' . implode(', ', array_map('strtoupper', $methodes)));
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 600');
        }

        self::terminerPrevol(204);
    }

    /**
     * Origines declarees dans CORS_ALLOWED_ORIGINS (separees par des virgules).
     */
    public static function originesAutorisees(): array
    {
        if (!class_exists('EnvLoader')) {
            return [];
        }

        $brut = trim((string) EnvLoader::get('CORS_ALLOWED_ORIGINS', ''));
        if ($brut === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v) => rtrim(trim($v), '/'), explode(',', $brut)),
            static fn ($v) => $v !== ''
        ));
    }

    /**
     * Repond a une requete de prevol et arrete le script ; ne fait rien sur
     * une requete ordinaire.
     */
    private static function terminerPrevol(int $code): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'OPTIONS') {
            return;
        }

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Length: 0');
        }
        exit;
    }
}
