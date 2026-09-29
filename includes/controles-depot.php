<?php
/**
 * Controles automatiques au depot (MOD-04), avant la revue humaine : les
 * moderateurs ne recoivent que des dossiers valides.
 *
 *   CONTROLE                    BLOQUANT
 *   type reel du fichier        oui  (uploadFile, SEC-17)
 *   duree lisible               oui  (source de verite, STAT-02)
 *   debit >= 128 kbit/s         oui
 *   frequence >= 44,1 kHz       oui
 *   silence ou fichier corrompu oui  si ffmpeg est configure (FFMPEG_PATH),
 *                                    sinon « non verifie » trace
 *   niveau sonore (cible -14 LUFS) signale seulement (ffmpeg)
 *   doublon exact (empreinte SHA-256) signale au moderateur, avec le titre
 *   pochette >= 1400 x 1400     oui, re-encodee en JPEG (metadonnees retirees)
 *
 * L'empreinte acoustique (Chromaprint), qui reconnait un meme enregistrement
 * reencode, demande un outil externe : a brancher avec ffmpeg.
 */

declare(strict_types=1);

final class ControlesDepot
{
    public const DEBIT_MIN = 128;
    public const FREQUENCE_MIN = 44100;
    public const POCHETTE_MIN = 1400;

    /**
     * Controle un fichier audio depose. Refuse (DepotRefuse, fichier supprime)
     * s'il est bloque ; sinon renvoie ses caracteristiques et les resultats a
     * tracer.
     *
     * @return array{duree:int, debit:?int, frequence:?int, canaux:?int, sha256:string, resultats:array<int,array{0:string,1:string,2:string}>}
     */
    public static function audio(string $chemin, ?int $exclureTitre = null): array
    {
        $d = DureeAudio::details($chemin);
        if ($d === null) {
            @unlink($chemin);
            throw new DepotRefuse('Fichier audio illisible : sa duree ne peut pas etre determinee. Deposez un MP3, WAV, FLAC ou M4A valide.');
        }
        $resultats = [['duree', 'ok', $d['duree'] . ' s']];
        if ($d['debit'] !== null && $d['debit'] < self::DEBIT_MIN) {
            @unlink($chemin);
            throw new DepotRefuse(sprintf('Qualite insuffisante : %d kbit/s. Le minimum est de %d kbit/s ; exportez votre titre en meilleure qualite.', $d['debit'], self::DEBIT_MIN));
        }
        $resultats[] = ['debit', $d['debit'] === null ? 'non_verifie' : 'ok', $d['debit'] === null ? 'debit inconnu' : $d['debit'] . ' kbit/s'];
        if ($d['frequence'] !== null && $d['frequence'] < self::FREQUENCE_MIN) {
            @unlink($chemin);
            throw new DepotRefuse(sprintf('Frequence d\'echantillonnage de %s Hz : le minimum est de 44 100 Hz.', number_format($d['frequence'], 0, ',', ' ')));
        }
        $resultats[] = ['frequence', $d['frequence'] === null ? 'non_verifie' : 'ok', $d['frequence'] === null ? 'frequence inconnue' : $d['frequence'] . ' Hz'];

        $niveaux = self::niveaux($chemin);
        if ($niveaux === null) {
            $resultats[] = ['silence', 'non_verifie', 'ffmpeg non configure'];
            $resultats[] = ['niveau_sonore', 'non_verifie', 'ffmpeg non configure'];
        } else {
            if ($niveaux['max'] !== null && $niveaux['max'] < -60) {
                @unlink($chemin);
                throw new DepotRefuse('Le fichier semble silencieux ou corrompu (aucun signal audible).');
            }
            $resultats[] = ['silence', 'ok', 'crete ' . $niveaux['max'] . ' dB'];
            $ecart = $niveaux['lufs'] !== null ? abs($niveaux['lufs'] + 14) : null;
            $resultats[] = ['niveau_sonore', $ecart !== null && $ecart > 3 ? 'signal' : 'ok', $niveaux['lufs'] !== null ? $niveaux['lufs'] . ' LUFS (cible -14)' : 'non mesure'];
        }

        $empreinte = (string) hash_file('sha256', $chemin);
        $stmt = TchadokDatabase::getInstance()->getConnection()->prepare(
            'SELECT t.id, t.title, a.stage_name FROM tracks t JOIN artists a ON a.id = t.artist_id WHERE t.audio_sha256 = ? AND t.deleted_at IS NULL' . ($exclureTitre ? ' AND t.id <> ' . (int) $exclureTitre : '') . ' LIMIT 1'
        );
        $stmt->execute([$empreinte]);
        $doublon = $stmt->fetch(PDO::FETCH_ASSOC);
        $resultats[] = $doublon
            ? ['doublon', 'signal', sprintf('Fichier identique au titre n°%d « %s » de %s', $doublon['id'], $doublon['title'], $doublon['stage_name'])]
            : ['doublon', 'ok', 'aucun fichier identique'];

        return $d + ['sha256' => $empreinte, 'resultats' => $resultats];
    }

    /** Trace les resultats d'un titre (visibles du moderateur). */
    public static function enregistrer(int $trackId, array $resultats): void
    {
        $db = TchadokDatabase::getInstance()->getConnection();
        $db->prepare('DELETE FROM content_checks WHERE track_id = ?')->execute([$trackId]);
        $stmt = $db->prepare('INSERT INTO content_checks (track_id, check_code, result, detail) VALUES (?, ?, ?, ?)');
        foreach ($resultats as [$code, $resultat, $detail]) {
            $stmt->execute([$trackId, $code, $resultat, mb_substr($detail, 0, 500)]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function resultats(int $trackId): array
    {
        $stmt = TchadokDatabase::getInstance()->getConnection()->prepare('SELECT * FROM content_checks WHERE track_id = ? ORDER BY id');
        $stmt->execute([$trackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pochette : 1400 x 1400 au moins, re-encodee en JPEG (retire les
     * metadonnees et tout contenu cache). Renvoie le nouveau chemin.
     */
    public static function pochette(string $chemin): string
    {
        $info = @getimagesize($chemin);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            @unlink($chemin);
            throw new DepotRefuse('Pochette illisible : JPEG, PNG ou WebP attendu.');
        }
        if ($info[0] < self::POCHETTE_MIN || $info[1] < self::POCHETTE_MIN) {
            @unlink($chemin);
            throw new DepotRefuse(sprintf('Pochette de %d x %d pixels : le minimum est de %d x %d.', $info[0], $info[1], self::POCHETTE_MIN, self::POCHETTE_MIN));
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG  => @imagecreatefrompng($chemin),
            default        => @imagecreatefromwebp($chemin),
        };
        if (!$image) {
            @unlink($chemin);
            throw new DepotRefuse('Pochette illisible.');
        }
        $destination = preg_replace('/\.[a-z0-9]+$/i', '', $chemin) . '.jpg';
        $ok = imagejpeg($image, $destination, 90);
        if ($destination !== $chemin) {
            @unlink($chemin);
        }
        if (!$ok) {
            throw new DepotRefuse('Pochette : enregistrement impossible.');
        }
        return $destination;
    }

    /** Crete (dB) et niveau integre (LUFS) par ffmpeg, ou null s'il manque. */
    private static function niveaux(string $chemin): ?array
    {
        $ffmpeg = (string) EnvLoader::get('FFMPEG_PATH', '');
        if ($ffmpeg === '' || !is_file($ffmpeg)) {
            return null;
        }
        $sortie = (string) @shell_exec(escapeshellarg($ffmpeg) . ' -hide_banner -nostats -i ' . escapeshellarg($chemin) . ' -af volumedetect,ebur128 -f null - 2>&1');
        preg_match('/max_volume:\s*(-?[\d.]+|-inf) dB/', $sortie, $max);
        preg_match_all('/I:\s*(-?[\d.]+) LUFS/', $sortie, $lufs);
        return [
            'max'  => isset($max[1]) ? ($max[1] === '-inf' ? -1000.0 : (float) $max[1]) : null,
            'lufs' => ($lufs[1] ?? []) !== [] ? (float) end($lufs[1]) : null,
        ];
    }
}
