<?php
/**
 * Stockage des simulateurs : fichiers JSON sous mock-gateways/storage/.
 *
 * Pas de base de donnees : les simulateurs demarrent en une commande et se
 * reinitialisent en supprimant le repertoire. Les transactions survivent au
 * redemarrage. Chaque ecriture est faite sous verrou de fichier : le serveur
 * HTTP et le distributeur de callbacks modifient les memes transactions.
 *
 *   storage/<passerelle>/<id>.json      transactions
 *   storage/<passerelle>/cles/<cle>     idempotence de l'initiation
 *   storage/file/<echeance>-<x>.json    callbacks programmes
 *   storage/journal-callbacks.jsonl     callbacks envoyes et reponse de l'application
 *   storage/reglages.json               reglages globaux (facteur de delai...)
 */

declare(strict_types=1);

final class Magasin
{
    public static function racine(): string
    {
        $racine = dirname(__DIR__) . '/storage';
        if (!is_dir($racine)) {
            mkdir($racine, 0775, true);
        }
        return $racine;
    }

    private static function dossier(string $sousDossier): string
    {
        $chemin = self::racine() . '/' . $sousDossier;
        if (!is_dir($chemin)) {
            mkdir($chemin, 0775, true);
        }
        return $chemin;
    }

    private static function cheminTransaction(string $passerelle, string $id): string
    {
        if (!preg_match('/^[A-Z]{3}-[A-F0-9]{16}$/', $id)) {
            throw new InvalidArgumentException('Identifiant de transaction invalide.');
        }
        return self::dossier($passerelle) . '/' . $id . '.json';
    }

    /** @return array<string,mixed>|null */
    public static function lire(string $passerelle, string $id): ?array
    {
        try {
            $chemin = self::cheminTransaction($passerelle, $id);
        } catch (InvalidArgumentException $e) {
            return null;
        }
        if (!is_file($chemin)) {
            return null;
        }
        $donnees = json_decode((string) file_get_contents($chemin), true);
        return is_array($donnees) ? $donnees : null;
    }

    public static function creer(string $passerelle, array $transaction): void
    {
        file_put_contents(
            self::cheminTransaction($passerelle, (string) $transaction['id']),
            json_encode($transaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Modifie une transaction sous verrou exclusif.
     *
     * @param callable(array):array $modification
     * @return array<string,mixed>|null
     */
    public static function modifier(string $passerelle, string $id, callable $modification): ?array
    {
        try {
            $chemin = self::cheminTransaction($passerelle, $id);
        } catch (InvalidArgumentException $e) {
            return null;
        }
        if (!is_file($chemin)) {
            return null;
        }

        $f = fopen($chemin, 'c+');
        flock($f, LOCK_EX);
        $transaction = json_decode((string) stream_get_contents($f), true);
        if (!is_array($transaction)) {
            flock($f, LOCK_UN);
            fclose($f);
            return null;
        }

        $transaction = $modification($transaction);
        $transaction['updated_at'] = date('c');

        ftruncate($f, 0);
        rewind($f);
        fwrite($f, (string) json_encode($transaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);

        return $transaction;
    }

    public static function idPourCle(string $passerelle, string $cle): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $cle)) {
            return null;
        }
        $chemin = self::dossier($passerelle . '/cles') . '/' . $cle;
        return is_file($chemin) ? trim((string) file_get_contents($chemin)) : null;
    }

    public static function retenirCle(string $passerelle, string $cle, string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $cle)) {
            file_put_contents(self::dossier($passerelle . '/cles') . '/' . $cle, $id, LOCK_EX);
        }
    }

    /**
     * Programme l'envoi d'un callback.
     *
     * @param array{passerelle:string, id:string, evenement:string, delai:float, statut?:?string, echec?:?string, alteration?:?string} $envoi
     */
    public static function programmer(array $envoi): void
    {
        $echeance = microtime(true) + max(0.0, $envoi['delai'] * self::facteurDelai());
        $nom = sprintf('%017.6f-%s.json', $echeance, bin2hex(random_bytes(4)));
        file_put_contents(self::dossier('file') . '/' . $nom, json_encode($envoi + ['echeance' => $echeance]), LOCK_EX);
    }

    /**
     * Callbacks dont l'echeance est passee, du plus ancien au plus recent.
     *
     * @return array<string,array<string,mixed>> chemin => envoi
     */
    public static function echus(): array
    {
        $dus = [];
        $maintenant = microtime(true);
        $fichiers = glob(self::dossier('file') . '/*.json') ?: [];
        sort($fichiers, SORT_STRING);
        foreach ($fichiers as $chemin) {
            if ((float) basename($chemin) > $maintenant) {
                break;
            }
            $envoi = json_decode((string) @file_get_contents($chemin), true);
            if (is_array($envoi)) {
                $dus[$chemin] = $envoi;
            }
        }
        return $dus;
    }

    public static function journaliser(array $ligne): void
    {
        file_put_contents(
            self::racine() . '/journal-callbacks.jsonl',
            json_encode($ligne + ['a' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /** @return array<string,mixed> */
    public static function reglages(): array
    {
        $chemin = self::racine() . '/reglages.json';
        $reglages = is_file($chemin) ? json_decode((string) file_get_contents($chemin), true) : null;
        return (is_array($reglages) ? $reglages : []) + ['facteur_delai' => 1.0];
    }

    public static function facteurDelai(): float
    {
        return max(0.0, (float) self::reglages()['facteur_delai']);
    }

    // -----------------------------------------------------------------
    // Console de pilotage (PAY-09)
    // -----------------------------------------------------------------

    /**
     * @param array<string,mixed> $valeurs
     */
    public static function enregistrerReglages(array $valeurs): void
    {
        file_put_contents(
            self::racine() . '/reglages.json',
            json_encode(array_merge(self::reglages(), $valeurs), JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /** Passerelle declaree indisponible depuis la console ? */
    public static function indisponible(string $passerelle): bool
    {
        return in_array($passerelle, (array) (self::reglages()['indisponibles'] ?? []), true);
    }

    /** Taux d'echec aleatoire (0 a 100) applique aux numeros hors scenarios. */
    public static function tauxEchec(): int
    {
        return max(0, min(100, (int) (self::reglages()['taux_echec'] ?? 0)));
    }

    /**
     * Transactions, les plus recentes d'abord.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function lister(?string $passerelle = null, int $limite = 100): array
    {
        $fichiers = [];
        foreach ($passerelle !== null ? [$passerelle] : array_keys(SIMULATEURS) as $code) {
            foreach (glob(self::racine() . '/' . $code . '/*.json') ?: [] as $chemin) {
                $fichiers[$chemin] = filemtime($chemin);
            }
        }
        arsort($fichiers);

        $liste = [];
        foreach (array_slice(array_keys($fichiers), 0, $limite) as $chemin) {
            $t = json_decode((string) file_get_contents($chemin), true);
            if (is_array($t)) {
                $t['_passerelle'] = basename(dirname($chemin));
                $liste[] = $t;
            }
        }
        return $liste;
    }

    /**
     * Retire de la file les callbacks programmes pour une transaction.
     */
    public static function annulerProgrammes(string $passerelle, string $id): int
    {
        $retires = 0;
        foreach (glob(self::dossier('file') . '/*.json') ?: [] as $chemin) {
            $envoi = json_decode((string) @file_get_contents($chemin), true);
            if (is_array($envoi) && ($envoi['passerelle'] ?? '') === $passerelle && ($envoi['id'] ?? '') === $id && @unlink($chemin)) {
                $retires++;
            }
        }
        return $retires;
    }

    /** @return array<int,array<string,mixed>> derniers callbacks envoyes, plus recents d'abord */
    public static function journalRecent(int $nombre = 50, ?string $id = null): array
    {
        $chemin = self::racine() . '/journal-callbacks.jsonl';
        if (!is_file($chemin)) {
            return [];
        }
        $lignes = array_reverse(file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        $resultat = [];
        foreach ($lignes as $ligne) {
            $l = json_decode($ligne, true);
            if (is_array($l) && ($id === null || ($l['id'] ?? '') === $id)) {
                $resultat[] = $l;
                if (count($resultat) >= $nombre) {
                    break;
                }
            }
        }
        return $resultat;
    }

    /** Efface l'etat des simulateurs (transactions, file, journal), reglages compris. */
    public static function reinitialiser(): void
    {
        $iterateur = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::racine(), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterateur as $element) {
            if ($element->getFilename() === 'console-jeton') {
                continue;
            }
            $element->isDir() ? @rmdir($element->getPathname()) : @unlink($element->getPathname());
        }
    }
}
