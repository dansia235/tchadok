<?php
/**
 * Kit presse et exports d'une edition (CHART-04), et certifications Tchadok
 * (CHART-06).
 *
 * KIT PRESSE
 *   Produit sans intervention a l'arrete de chaque edition, dans
 *   storage/barometre/<edition>/ :
 *     top10.png        visuel 1080 x 1350 du Top 10 titres, pret a publier
 *                      (aussi l'image d'apercu WhatsApp / Facebook) ;
 *     classement.csv   tous les classements de l'edition ;
 *     communique.html  communique genere avec les chiffres cles.
 *   L'export PDF passe par la page imprimable de l'edition (« Imprimer ->
 *   Enregistrer en PDF ») en attendant une generation serveur (QA-01).
 *
 * CERTIFICATIONS
 *   Or, Platine, Diamant : paliers d'ecoutes certifiees et de ventes
 *   cumulees, publies (certification_levels). Decernees automatiquement
 *   chaque nuit, definitives, l'artiste est prevenu par e-mail.
 */

declare(strict_types=1);

final class KitPresse
{
    public static function dossier(string $slug): string
    {
        return dirname(__DIR__) . '/storage/barometre/' . preg_replace('/[^A-Za-z0-9-]/', '', $slug);
    }

    /** Genere le kit d'une edition. @return string[] fichiers produits */
    public static function generer(int $editionId): array
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT * FROM chart_editions WHERE id = ?');
        $stmt->execute([$editionId]);
        $edition = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edition) {
            throw new RuntimeException('Edition introuvable.');
        }
        $dossier = self::dossier((string) $edition['slug']);
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new RuntimeException('Dossier du kit impossible a creer.');
        }
        $fichiers = [];
        file_put_contents($dossier . '/classement.csv', self::csv($edition));
        $fichiers[] = 'classement.csv';
        file_put_contents($dossier . '/communique.html', self::communique($edition));
        $fichiers[] = 'communique.html';
        if (self::visuel($edition, $dossier . '/top10.png')) {
            $fichiers[] = 'top10.png';
        }
        $db->prepare('UPDATE chart_editions SET kit_generated_at = NOW() WHERE id = ?')->execute([$editionId]);
        return $fichiers;
    }

    /** Kits manquants (editions arretees sans kit). @return int kits generes */
    public static function genererManquants(): int
    {
        $ids = TchadokDatabase::getInstance()->getConnection()->query('SELECT id FROM chart_editions WHERE kit_generated_at IS NULL ORDER BY period_start')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::generer((int) $id);
        }
        return count($ids);
    }

    public static function csv(array $edition): string
    {
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM : ouverture correcte dans un tableur
        fputcsv($f, ['# Barometre Tchadok - ' . Barometre::libellePeriode($edition) . ' - arrete le ' . date('d/m/Y H:i', strtotime((string) $edition['arrete_at']))
            . ' - methodologie v' . $edition['methodology_version'] . ' : ' . SITE_URL . '/methodologie.php'], ';');
        fputcsv($f, ['classement', 'rang', 'rang_precedent', 'evolution', 'titre', 'artiste', 'valeur', 'mesure', 'meilleur_rang', 'periodes_classees'], ';');
        foreach (Barometre::CLASSEMENTS as $cle => $def) {
            foreach (Barometre::entrees((int) $edition['id'], $cle) as $e) {
                fputcsv($f, [$cle, $e['rank'], $e['previous_rank'] ?? '', Barometre::evolution($e), $e['titre'], $e['artiste'] ?? '',
                    $e['value'], $def['mesure'], $e['peak_rank'], $e['periods_on_chart']], ';');
            }
        }
        rewind($f);
        return (string) stream_get_contents($f);
    }

    public static function communique(array $edition): string
    {
        $top = Barometre::entrees((int) $edition['id'], 'titres');
        $artistes = Barometre::entrees((int) $edition['id'], 'artistes');
        $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nombre = static fn ($v): string => number_format((float) $v, 0, ',', ' ');
        $periode = Barometre::libellePeriode($edition);
        $premier = $top[0] ?? null;
        $nouveaux = count(array_filter($top, fn($x) => (int) $x['is_new'] === 1));
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Communique - Barometre Tchadok ' . $e($edition['slug']) . '</title>'
            . '<style>body{font:15px/1.6 system-ui,sans-serif;max-width:760px;margin:32px auto;padding:0 16px;color:#1a1f36}h1{font-size:22px}li{margin:4px 0}.doux{color:#5b6478;font-size:13px}</style></head><body>';
        $html .= '<p class="doux">COMMUNIQUE DE PRESSE - N\'Djamena, le ' . date('d/m/Y', strtotime((string) $edition['arrete_at'])) . '</p>';
        $html .= '<h1>Barometre Tchadok : ' . $e($periode) . '</h1>';
        if ($premier) {
            $html .= '<p><strong>' . $e($premier['titre']) . '</strong> de <strong>' . $e($premier['artiste']) . '</strong> prend la tete du classement des titres les plus ecoutes, avec '
                . $e($nombre($premier['value'])) . ' ecoutes certifiees.</p>';
        } else {
            $html .= '<p>Aucune ecoute certifiee sur la periode.</p>';
        }
        $html .= '<p>Chiffres cles : ' . $e($nombre($edition['streams_total'])) . ' ecoutes certifiees, ' . $e($nombre($edition['sales_total'])) . ' ventes, '
            . $nouveaux . ' nouvelle(s) entree(s) dans le Top titres.</p>';
        if ($top !== []) {
            $html .= '<h2>Top 10 titres</h2><ol>';
            foreach (array_slice($top, 0, 10) as $t) {
                $html .= '<li>' . $e($t['titre']) . ' - ' . $e($t['artiste']) . ' (' . $e(Barometre::evolution($t)) . ')</li>';
            }
            $html .= '</ol>';
        }
        if ($artistes !== []) {
            $html .= '<p>Artiste le plus ecoute : <strong>' . $e($artistes[0]['titre']) . '</strong>.</p>';
        }
        $html .= '<p class="doux">Source : Barometre Tchadok, classement arrete le ' . date('d/m/Y a H:i', strtotime((string) $edition['arrete_at']))
            . '. Seules les ecoutes certifiees (lecture effective d\'au moins 30 secondes, filtrage anti-fraude) sont comptees. Methodologie : '
            . $e(SITE_URL . '/methodologie.php') . '. Reproduction libre avec mention « Barometre Tchadok ».</p></body></html>';
        return $html;
    }

    /** Visuel du Top 10 (PNG). false si GD ou la police manque. */
    public static function visuel(array $edition, string $chemin): bool
    {
        $gras = dirname(__DIR__) . '/assets/fonts/Poppins-Bold.ttf';
        $normal = dirname(__DIR__) . '/assets/fonts/Poppins-Regular.ttf';
        if (!function_exists('imagettftext') || !is_file($gras) || !is_file($normal)) {
            error_log('[Tchadok][barometre] visuel non genere : GD/FreeType ou police absente');
            return false;
        }
        $l = 1080;
        $h = 1350;
        $img = imagecreatetruecolor($l, $h);
        $fond = imagecolorallocate($img, 11, 15, 23);
        $accent = imagecolorallocate($img, 47, 109, 224);
        $blanc = imagecolorallocate($img, 245, 247, 250);
        $gris = imagecolorallocate($img, 150, 160, 180);
        $or = imagecolorallocate($img, 240, 185, 60);
        imagefilledrectangle($img, 0, 0, $l, $h, $fond);
        imagefilledrectangle($img, 0, 0, $l, 12, $accent);
        $texte = static function (int $taille, int $x, int $y, int $couleur, string $police, string $s) use ($img): void {
            imagettftext($img, $taille, 0, $x, $y, $couleur, $police, $s);
        };
        $couper = static fn (string $s, int $n): string => mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
        $texte(30, 70, 110, $accent, $gras, 'BAROMETRE TCHADOK');
        $texte(44, 70, 185, $blanc, $gras, 'Top 10 titres');
        $texte(24, 70, 235, $gris, $normal, Barometre::libellePeriode($edition));
        $y = 330;
        $top = array_slice(Barometre::entrees((int) $edition['id'], 'titres'), 0, 10);
        if ($top === []) {
            $texte(28, 70, $y, $gris, $normal, 'Aucune ecoute certifiee sur la periode.');
        }
        foreach ($top as $t) {
            $texte(40, 70, $y + 10, (int) $t['rank'] <= 3 ? $or : $blanc, $gras, str_pad((string) $t['rank'], 2, '0', STR_PAD_LEFT));
            $texte(28, 190, $y - 8, $blanc, $gras, $couper((string) $t['titre'], 34));
            $texte(21, 190, $y + 28, $gris, $normal, $couper((string) $t['artiste'], 40));
            $evo = Barometre::evolution($t);
            $texte(22, 930, $y + 8, $evo === 'nouveau' ? $or : $gris, $gras, $evo === 'nouveau' ? 'NEW' : $evo);
            $y += 92;
        }
        $texte(18, 70, $h - 60, $gris, $normal, 'Ecoutes certifiees, arrete le ' . date('d/m/Y', strtotime((string) $edition['arrete_at'])) . ' - methodologie : ' . parse_url(SITE_URL, PHP_URL_HOST) . '/methodologie.php');
        $ok = imagepng($img, $chemin, 6);
        imagedestroy($img);
        return $ok;
    }
}

final class CertificationsTchadok
{
    public const NIVEAUX = ['or' => 'Or', 'platine' => 'Platine', 'diamant' => 'Diamant'];
    public const BASES = ['ecoutes' => 'ecoutes certifiees', 'ventes' => 'ventes'];

    /** Seuils en vigueur. @return array<string,array<string,int>> [base][niveau] => seuil */
    public static function seuils(): array
    {
        $seuils = [];
        $lignes = TchadokDatabase::getInstance()->getConnection()->query(
            'SELECT basis, level, threshold FROM certification_levels WHERE effective_from <= CURDATE() ORDER BY effective_from, id'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lignes as $l) {
            $seuils[$l['basis']][$l['level']] = (int) $l['threshold'];
        }
        return $seuils;
    }

    /**
     * Decerne les paliers atteints (compteurs publics, tenus depuis les
     * ecoutes certifiees et les ventes nettes) et previent les artistes.
     *
     * @return int certifications decernees
     */
    public static function decerner(): int
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $colonnes = ['ecoutes' => 'total_streams', 'ventes' => 'total_sales'];
        $n = 0;
        foreach (self::seuils() as $base => $niveaux) {
            foreach ($niveaux as $niveau => $seuil) {
                $colonne = $colonnes[$base];
                $stmt = $db->prepare(
                    "INSERT IGNORE INTO certifications (track_id, basis, level, threshold, value_at_award, awarded_at)
                     SELECT t.id, ?, ?, ?, t.{$colonne}, NOW() FROM tracks t
                      WHERE t.{$colonne} >= ? AND t.status = 'approved' AND t.deleted_at IS NULL"
                );
                $stmt->execute([$base, $niveau, $seuil, $seuil]);
                $n += $stmt->rowCount();
            }
        }
        self::prevenir($db);
        return $n;
    }

    /** @return array<int,array<string,mixed>> certifications d'un titre */
    public static function duTitre(int $trackId): array
    {
        $stmt = TchadokDatabase::getInstance()->getConnection()->prepare('SELECT * FROM certifications WHERE track_id = ? ORDER BY awarded_at');
        $stmt->execute([$trackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> dernieres certifications (annonce publique) */
    public static function recentes(int $limite = 10): array
    {
        return TchadokDatabase::getInstance()->getConnection()->query(
            'SELECT c.*, t.title, a.stage_name FROM certifications c JOIN tracks t ON t.id = c.track_id JOIN artists a ON a.id = t.artist_id
              WHERE t.deleted_at IS NULL ORDER BY c.awarded_at DESC, c.id DESC LIMIT ' . max(1, $limite)
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function libelle(array $c): string
    {
        return 'Certifie ' . (self::NIVEAUX[$c['level']] ?? $c['level']) . ' (' . (self::BASES[$c['basis']] ?? $c['basis']) . ')';
    }

    private static function prevenir(PDO $db): void
    {
        $lignes = $db->query(
            'SELECT c.*, t.title, u.email, u.first_name FROM certifications c JOIN tracks t ON t.id = c.track_id
               JOIN artists a ON a.id = t.artist_id JOIN users u ON u.id = a.user_id WHERE c.notified_at IS NULL'
        )->fetchAll(PDO::FETCH_ASSOC);
        $marquer = $db->prepare('UPDATE certifications SET notified_at = NOW() WHERE id = ?');
        foreach ($lignes as $c) {
            if (!empty($c['email'])) {
                sendEmail((string) $c['email'], 'Tchadok - certification ' . (self::NIVEAUX[$c['level']] ?? ''),
                    '<p>Bonjour ' . htmlspecialchars((string) $c['first_name'], ENT_QUOTES, 'UTF-8') . ',</p><p>Felicitations : votre titre « '
                    . htmlspecialchars((string) $c['title'], ENT_QUOTES, 'UTF-8') . ' » est ' . htmlspecialchars(self::libelle($c), ENT_QUOTES, 'UTF-8')
                    . ', avec ' . number_format((float) $c['value_at_award'], 0, ',', ' ') . ' ' . (self::BASES[$c['basis']] ?? '') . '.</p><p>Attestation : '
                    . SITE_URL . '/certification.php?id=' . (int) $c['id'] . '</p>');
            }
            $marquer->execute([$c['id']]);
        }
    }
}
