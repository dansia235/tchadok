<?php
/**
 * Page hebergee de l'acquereur carte, simulee (PAY-07).
 *
 * En production, cette page est servie par l'acquereur VISA, sur son domaine :
 * le client y saisit sa carte, jamais chez Tchadok. Le simulateur reproduit ce
 * decoupage pour que l'application n'ait, des le local, AUCUN champ de carte.
 *
 * Meme ici, le numero de carte ne sert qu'a choisir le scenario : il n'est ni
 * conserve, ni journalise.
 */

declare(strict_types=1);

final class PageHebergee
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function traiter(string $id, string $etape, string $methode): never
    {
        $transaction = Magasin::lire($this->config['code'], $id);
        if (!$transaction) {
            $this->page('Session inconnue', '<p>Cette session de paiement n\'existe pas ou a expire.</p>', 404);
        }

        if ($transaction['status'] !== 'pending' && $etape !== '') {
            $this->retour($transaction);
        }

        match (true) {
            $etape === '' && $transaction['status'] !== 'pending' => $this->retour($transaction),
            $etape === ''                                         => $this->formulaireCarte($transaction),
            $etape === 'submit' && $methode === 'POST'             => $this->soumettre($transaction),
            $etape === '3ds' && $methode === 'POST'                => $this->validerDefi($transaction),
            $etape === '3ds'                                       => $this->formulaireDefi($transaction),
            $etape === 'cancel'                                    => $this->conclure($transaction, 'cancelled', null, 'Abandon par le client'),
            default                                                => $this->page('Requete invalide', '<p>Etape inconnue.</p>', 400),
        };
    }

    private function formulaireCarte(array $t, string $erreur = ''): never
    {
        $action = '/hosted/' . $t['id'] . '/submit';
        $html = $this->resume($t)
            . ($erreur !== '' ? '<p class="err">' . $this->e($erreur) . '</p>' : '')
            . '<form method="post" action="' . $this->e($action) . '" autocomplete="off">'
            . '<label>Numero de carte<input name="card" inputmode="numeric" required placeholder="4111 1111 1111 1111"></label>'
            . '<div class="row"><label>Expiration<input name="exp" required placeholder="12/30"></label>'
            . '<label>Cryptogramme<input name="cvc" required placeholder="123" maxlength="4"></label></div>'
            . '<label>Titulaire<input name="name" required placeholder="NOM Prenom"></label>'
            . '<button type="submit">Payer ' . $this->montant($t) . '</button>'
            . '</form>'
            . '<p class="muted"><a href="/hosted/' . $this->e($t['id']) . '/cancel">Annuler et revenir au marchand</a></p>'
            . '<details><summary>Cartes de test</summary><ul>' . $this->listeCartes() . '</ul></details>';

        $this->page('Paiement securise', $html);
    }

    private function soumettre(array $t): never
    {
        $scenario = Scenarios::pourCarte((string) ($_POST['card'] ?? ''));
        // Le numero n'est plus utilise au-dela de cette ligne.
        unset($_POST['card'], $_POST['cvc'], $_POST['exp']);

        if ($scenario === null) {
            $this->formulaireCarte($t, 'Numero de carte invalide.');
        }

        [$libelle, $statut, $echec, $defi, $contestation] = $scenario;
        Magasin::modifier($this->config['code'], $t['id'], function (array $x) use ($libelle, $statut, $echec, $defi, $contestation): array {
            $x['scenario'] = $libelle;
            $x['_prevu'] = ['statut' => $statut, 'echec' => $echec, 'contestation' => $contestation];
            $x['_3ds'] = $defi;
            return $x;
        });

        if ($defi) {
            header('Location: /hosted/' . $t['id'] . '/3ds', true, 303);
            exit;
        }

        $this->conclure(Magasin::lire($this->config['code'], $t['id']) ?? $t, $statut, $echec, $libelle, $contestation);
    }

    private function formulaireDefi(array $t, string $erreur = ''): never
    {
        $html = $this->resume($t)
            . '<p>Votre banque vous a envoye un code de confirmation par SMS.</p>'
            . ($erreur !== '' ? '<p class="err">' . $this->e($erreur) . '</p>' : '')
            . '<form method="post" action="/hosted/' . $this->e($t['id']) . '/3ds">'
            . '<label>Code 3-D Secure<input name="code" inputmode="numeric" required maxlength="6" placeholder="' . Scenarios::CODE_3DS . '"></label>'
            . '<button type="submit">Confirmer</button></form>';

        $this->page('Authentification 3-D Secure', $html);
    }

    private function validerDefi(array $t): never
    {
        $prevu = $t['_prevu'] ?? null;
        if (!is_array($prevu) || empty($t['_3ds'])) {
            $this->formulaireCarte($t, 'Session incoherente, recommencez.');
        }

        if (!hash_equals(Scenarios::CODE_3DS, (string) ($_POST['code'] ?? ''))) {
            $this->conclure($t, 'failed', 'authentication_failed', 'Echec de l\'authentification 3-D Secure');
        }

        $this->conclure($t, (string) $prevu['statut'], $prevu['echec'], (string) $t['scenario'], (bool) $prevu['contestation']);
    }

    private function conclure(array $t, string $statut, ?string $echec, string $libelle, bool $contestation = false): never
    {
        $t = Magasin::modifier($this->config['code'], $t['id'], function (array $x) use ($statut, $echec, $libelle): array {
            $x['status'] = $statut;
            $x['failure_code'] = $echec;
            $x['scenario'] = $libelle;
            unset($x['_prevu'], $x['_3ds']);
            return $x;
        }) ?? $t;

        $evenement = ['succeeded' => 'payment.succeeded', 'failed' => 'payment.failed', 'cancelled' => 'payment.cancelled'][$statut];
        Magasin::programmer(['passerelle' => $this->config['code'], 'id' => $t['id'], 'evenement' => $evenement, 'delai' => 1]);

        if ($contestation) {
            Magasin::programmer([
                'passerelle' => $this->config['code'], 'id' => $t['id'], 'evenement' => 'payment.disputed',
                'delai' => Scenarios::DELAI_CONTESTATION, 'statut' => 'disputed',
            ]);
        }

        $this->retour($t);
    }

    /** Renvoie le navigateur chez le marchand. Ce retour n'est PAS une preuve de paiement. */
    private function retour(array $t): never
    {
        $url = (string) ($t['return_url'] ?? '');
        if ($url === '') {
            $this->page('Paiement termine', '<p>Vous pouvez fermer cette page.</p>');
        }
        header('Location: ' . $url, true, 303);
        exit;
    }

    private function resume(array $t): string
    {
        return '<div class="box"><div class="muted">Marchand</div><strong>Tchadok</strong>'
            . '<div class="amount">' . $this->montant($t) . '</div>'
            . '<div class="muted">' . $this->e((string) $t['description']) . '</div></div>';
    }

    /** Montant lisible : les montants sont en unites mineures (cents pour l'USD). */
    private function montant(array $t): string
    {
        return ($t['currency'] ?? 'XAF') === 'USD'
            ? number_format((int) $t['amount'] / 100, 2, ',', ' ') . ' $ US'
            : number_format((int) $t['amount'], 0, ',', ' ') . ' FCFA';
    }

    private function listeCartes(): string
    {
        $html = '';
        foreach (Scenarios::CARTES as $numero => $s) {
            // PHP convertit en entier une cle de tableau purement numerique.
            $html .= '<li><code>' . trim(chunk_split((string) $numero, 4, ' ')) . '</code> — ' . $this->e($s[0]) . '</li>';
        }
        return $html;
    }

    private function page(string $titre, string $contenu, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $this->e($titre) . ' — Acquereur VISA (simulateur)</title><style>'
            . 'body{font-family:system-ui,sans-serif;background:#eef1f6;color:#1a1f36;margin:0;padding:24px 16px}'
            . 'main{max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 20px #0001}'
            . '.banner{background:#1a1f71;color:#fff;font-size:12px;padding:6px 10px;border-radius:6px;margin-bottom:16px}'
            . 'h1{font-size:20px;margin:0 0 16px}label{display:block;font-size:13px;margin:12px 0 0}'
            . 'input{display:block;width:100%;box-sizing:border-box;margin-top:4px;padding:10px;border:1px solid #c9cfdb;border-radius:8px;font-size:16px}'
            . '.row{display:flex;gap:12px}.row label{flex:1}'
            . 'button{margin-top:20px;width:100%;padding:12px;border:0;border-radius:8px;background:#1a1f71;color:#fff;font-size:16px;cursor:pointer}'
            . '.box{background:#f5f7fb;border-radius:8px;padding:12px;margin-bottom:8px}.amount{font-size:24px;font-weight:700;margin:6px 0}'
            . '.muted{color:#5b6478;font-size:13px}.err{color:#b42318;background:#fef3f2;padding:8px;border-radius:6px}'
            . 'details{margin-top:16px;font-size:13px}code{font-size:12px}'
            . '</style></head><body><main><div class="banner">SIMULATEUR LOCAL — aucune carte reelle n\'est debitee</div>'
            . '<h1>' . $this->e($titre) . '</h1>' . $contenu . '</main></body></html>';
        exit;
    }

    private function e(string $texte): string
    {
        return htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    }
}
