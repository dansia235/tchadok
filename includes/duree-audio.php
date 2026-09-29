<?php
/**
 * Caracteristiques d'un fichier audio, lues dans le fichier (STAT-02, MOD-04).
 *
 * La duree etait saisie a la main par l'artiste : declarer 1 seconde faisait
 * compter chaque ouverture comme une ecoute complete. Elle est desormais lue
 * au depot, avec le debit, la frequence d'echantillonnage et les canaux
 * (controles de qualite de MOD-04).
 *
 * ffprobe s'il est configure (FFPROBE_PATH), sinon lecture native des quatre
 * formats acceptes (ALLOWED_AUDIO_TYPES) :
 *   MP3   en-tete de trame (debit, frequence, canaux), Xing/Info ou VBRI pour
 *         le debit variable, sinon debit constant ;
 *   WAV   bloc « fmt » et bloc « data » ;
 *   FLAC  bloc STREAMINFO (frequence, canaux, nombre d'echantillons) ;
 *   M4A   atomes « mvhd » (duree) et « mdhd » (echelle = frequence).
 * Le debit d'un format sans debit declare se deduit de la taille et de la
 * duree. null si le fichier est illisible : le depot est alors refuse.
 */

declare(strict_types=1);

/**
 * Depot refuse. Le message est ECRIT POUR L'ARTISTE (format, taille, duree
 * illisible) : il s'affiche tel quel, par messagePourArtiste(). Toute autre
 * exception passe par GestionErreurs::messagePublic() (SEC-15).
 */
final class DepotRefuse extends RuntimeException
{
    public function messagePourArtiste(): string
    {
        return $this->getMessage();
    }
}

final class DureeAudio
{
    /**
     * Duree d'un fichier qui vient d'etre depose. S'il est illisible, il est
     * supprime et le depot refuse : un titre sans duree fiable fausserait le
     * seuil d'ecoute.
     */
    public static function duDepot(string $cheminAbsolu): int
    {
        $duree = self::secondes($cheminAbsolu);
        if ($duree === null) {
            @unlink($cheminAbsolu);
            throw new DepotRefuse('Fichier audio illisible : sa duree ne peut pas etre determinee. Deposez un MP3, WAV, FLAC ou M4A valide.');
        }
        return $duree;
    }

    /** Duree en secondes (arrondie), ou null si elle ne peut pas etre lue. */
    public static function secondes(string $chemin): ?int
    {
        $d = self::details($chemin);
        return $d === null ? null : $d['duree'];
    }

    /**
     * @return array{duree:int, debit:?int, frequence:?int, canaux:?int}|null
     *         debit en kbit/s, frequence en Hz
     */
    public static function details(string $chemin): ?array
    {
        if (!is_file($chemin) || !is_readable($chemin) || filesize($chemin) < 32) {
            return null;
        }
        $taille = (int) filesize($chemin);
        $d = self::parFfprobe($chemin);
        if ($d === null) {
            $f = fopen($chemin, 'rb');
            if ($f === false) {
                return null;
            }
            $debut = (string) fread($f, 12);
            rewind($f);
            try {
                $d = match (true) {
                    str_starts_with($debut, 'RIFF') && substr($debut, 8, 4) === 'WAVE' => self::wav($f),
                    str_starts_with($debut, 'fLaC') => self::flac($f),
                    substr($debut, 4, 4) === 'ftyp' => self::mp4($f, $taille),
                    default => self::mp3($f, $taille),
                };
            } finally {
                fclose($f);
            }
        }
        if ($d === null || !isset($d['duree']) || $d['duree'] <= 0.5 || $d['duree'] >= 6 * 3600) {
            return null;
        }
        return [
            'duree'     => (int) round($d['duree']),
            'debit'     => isset($d['debit']) && $d['debit'] > 0 ? (int) round($d['debit']) : (int) round($taille * 8 / 1000 / $d['duree']),
            'frequence' => isset($d['frequence']) && $d['frequence'] > 0 ? (int) $d['frequence'] : null,
            'canaux'    => isset($d['canaux']) && $d['canaux'] > 0 ? (int) $d['canaux'] : null,
        ];
    }

    private static function parFfprobe(string $chemin): ?array
    {
        $ffprobe = (string) EnvLoader::get('FFPROBE_PATH', '');
        if ($ffprobe === '' || !is_file($ffprobe)) {
            return null;
        }
        $sortie = @shell_exec(escapeshellarg($ffprobe) . ' -v error -select_streams a:0 -show_entries format=duration,bit_rate:stream=sample_rate,channels -of json ' . escapeshellarg($chemin));
        $j = json_decode((string) $sortie, true);
        if (!is_array($j) || !is_numeric($j['format']['duration'] ?? null)) {
            return null;
        }
        return [
            'duree' => (float) $j['format']['duration'],
            'debit' => is_numeric($j['format']['bit_rate'] ?? null) ? (int) $j['format']['bit_rate'] / 1000 : null,
            'frequence' => (int) ($j['streams'][0]['sample_rate'] ?? 0), 'canaux' => (int) ($j['streams'][0]['channels'] ?? 0),
        ];
    }

    /** @param resource $f */
    private static function wav($f): ?array
    {
        fseek($f, 12);
        $fmt = null;
        while (!feof($f)) {
            $entete = fread($f, 8);
            if ($entete === false || strlen($entete) < 8) {
                break;
            }
            $id = substr($entete, 0, 4);
            $taille = unpack('V', substr($entete, 4, 4))[1];
            if ($id === 'fmt ') {
                $bloc = (string) fread($f, $taille);
                $fmt = unpack('vformat/vcanaux/Vfrequence/Voctets', substr($bloc, 0, 12));
                if ($taille % 2) {
                    fseek($f, 1, SEEK_CUR);
                }
                continue;
            }
            if ($id === 'data') {
                if (!$fmt || $fmt['octets'] <= 0) {
                    return null;
                }
                return ['duree' => $taille / $fmt['octets'], 'debit' => $fmt['octets'] * 8 / 1000, 'frequence' => $fmt['frequence'], 'canaux' => $fmt['canaux']];
            }
            fseek($f, $taille + ($taille % 2), SEEK_CUR);
        }
        return null;
    }

    /** @param resource $f */
    private static function flac($f): ?array
    {
        fseek($f, 4);
        $bloc = (string) fread($f, 4 + 34);
        if (strlen($bloc) < 38 || (ord($bloc[0]) & 0x7F) !== 0) {
            return null; // STREAMINFO doit etre le premier bloc
        }
        $info = substr($bloc, 4);
        // Octets 10 a 17 : frequence (20 bits), canaux (3), bits (5), echantillons (36).
        $frequence = (ord($info[10]) << 12) | (ord($info[11]) << 4) | (ord($info[12]) >> 4);
        $canaux = ((ord($info[12]) >> 1) & 0x07) + 1;
        $echantillons = ((ord($info[13]) & 0x0F) << 32) | (ord($info[14]) << 24) | (ord($info[15]) << 16) | (ord($info[16]) << 8) | ord($info[17]);
        return $frequence > 0 && $echantillons > 0 ? ['duree' => $echantillons / $frequence, 'frequence' => $frequence, 'canaux' => $canaux] : null;
    }

    /** @param resource $f */
    private static function mp4($f, int $taille): ?array
    {
        $resultat = [];
        self::parcourirMp4($f, 0, $taille, $resultat);
        return isset($resultat['duree']) ? $resultat : null;
    }

    /** @param resource $f */
    private static function parcourirMp4($f, int $debut, int $fin, array &$resultat): void
    {
        $position = $debut;
        while ($position + 8 <= $fin) {
            fseek($f, $position);
            $entete = (string) fread($f, 8);
            if (strlen($entete) < 8) {
                return;
            }
            $longueur = unpack('N', substr($entete, 0, 4))[1];
            $type = substr($entete, 4, 4);
            $tailleEntete = 8;
            if ($longueur === 1) {
                $longueur = (int) unpack('J', (string) fread($f, 8))[1];
                $tailleEntete = 16;
            } elseif ($longueur === 0) {
                $longueur = $fin - $position;
            }
            if ($longueur < 8) {
                return;
            }
            if (in_array($type, ['moov', 'trak', 'mdia'], true)) {
                self::parcourirMp4($f, $position + $tailleEntete, $position + $longueur, $resultat);
            } elseif ($type === 'mvhd' || $type === 'mdhd') {
                $corps = (string) fread($f, 32);
                $version = ord($corps[0] ?? "\0");
                [$echelle, $duree] = $version === 1
                    ? [unpack('N', substr($corps, 20, 4))[1], unpack('J', substr($corps, 24, 8))[1]]
                    : [unpack('N', substr($corps, 12, 4))[1], unpack('N', substr($corps, 16, 4))[1]];
                if ($type === 'mvhd' && $echelle > 0) {
                    $resultat['duree'] = $duree / $echelle;
                } elseif ($type === 'mdhd' && !isset($resultat['frequence'])) {
                    // Piste audio : l'echelle de temps est la frequence d'echantillonnage.
                    $resultat['frequence'] = $echelle;
                }
            }
            $position += $longueur;
        }
    }

    /** @param resource $f */
    private static function mp3($f, int $taille): ?array
    {
        // Etiquette ID3v2 en tete : sa taille est codee sur 4 x 7 bits.
        $debut = 0;
        $id3 = (string) fread($f, 10);
        if (str_starts_with($id3, 'ID3') && strlen($id3) === 10) {
            $debut = 10 + ((ord($id3[6]) & 0x7F) << 21 | (ord($id3[7]) & 0x7F) << 14 | (ord($id3[8]) & 0x7F) << 7 | (ord($id3[9]) & 0x7F));
        }
        fseek($f, $debut);
        $tampon = (string) fread($f, 65536);
        $debitsV1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0];
        $debitsV2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160, 0];
        $frequences = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];

        for ($i = 0; $i + 4 <= strlen($tampon); $i++) {
            if (ord($tampon[$i]) !== 0xFF || (ord($tampon[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }
            $b1 = ord($tampon[$i + 1]);
            $b2 = ord($tampon[$i + 2]);
            $b3 = ord($tampon[$i + 3]);
            $version = ($b1 >> 3) & 0x03;   // 3 = MPEG1, 2 = MPEG2, 0 = MPEG2.5
            $couche = ($b1 >> 1) & 0x03;    // 1 = Layer III
            $indiceDebit = $b2 >> 4;
            $indiceFrequence = ($b2 >> 2) & 0x03;
            if ($version === 1 || $couche !== 1 || $indiceDebit === 0 || $indiceDebit === 15 || $indiceFrequence === 3) {
                continue;
            }
            $frequence = $frequences[$version][$indiceFrequence];
            $debit = ($version === 3 ? $debitsV1 : $debitsV2)[$indiceDebit];
            $echantillonsParTrame = $version === 3 ? 1152 : 576;
            $mono = (($b3 >> 6) & 0x03) === 3;
            $base = ['frequence' => $frequence, 'canaux' => $mono ? 1 : 2];

            // Xing / Info : nombre total de trames (debit variable).
            $decalage = $version === 3 ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
            $xing = substr($tampon, $i + 4 + $decalage, 12);
            if (in_array(substr($xing, 0, 4), ['Xing', 'Info'], true) && (unpack('N', substr($xing, 4, 4))[1] & 0x01)) {
                $trames = unpack('N', substr($xing, 8, 4))[1];
                return $base + ['duree' => $trames * $echantillonsParTrame / $frequence];
            }
            // VBRI (Fraunhofer) : 32 octets apres l'en-tete de trame.
            $vbri = substr($tampon, $i + 4 + 32, 18);
            if (str_starts_with($vbri, 'VBRI')) {
                $trames = unpack('N', substr($vbri, 14, 4))[1];
                return $base + ['duree' => $trames * $echantillonsParTrame / $frequence];
            }
            // Debit constant : taille audio / debit.
            $audio = $taille - $debut - $i;
            fseek($f, -128, SEEK_END);
            if ((string) fread($f, 3) === 'TAG') {
                $audio -= 128; // etiquette ID3v1 en fin de fichier
            }
            return $debit > 0 ? $base + ['duree' => $audio * 8 / ($debit * 1000), 'debit' => $debit] : null;
        }
        return null;
    }
}
