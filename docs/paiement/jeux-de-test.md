# Jeux de test des paiements

Scénarios des simulateurs locaux (`mock-gateways/`), pilotés par le **numéro de téléphone** (Airtel Money, Moov Money, GIMAC) ou le **numéro de carte** (VISA). Ils reproduisent les situations que les opérateurs réels produisent, y compris les pires : callbacks en double, mal signés, au montant faux, ou jamais envoyés.

Démarrer les simulateurs : `scripts\mock-gateways.bat` — arrêter : `scripts\mock-gateways.bat stop`.

Créer une commande à payer, en attendant le panier (`SHOP-01`) :

```
php scripts/paiements.php commande-essai user@tchadok.td 1500
```

---

## 1. Mobile money : Airtel Money, Moov Money, GIMAC

Numéros sur 8 chiffres : **indicatif de l'opérateur**, puis `0000`, puis le **numéro de scénario**.

| Opérateur | Indicatif | Exemple (scénario 1) |
|---|---|---|
| Airtel Money | `66` | `66000001` |
| Moov Money | `65` | `65000001` |
| GIMAC | `62` | `62000001` — ou `+237 62000001` : GIMAC accepte les numéros de toute la CEMAC |

| N° | Comportement du simulateur | Résultat attendu dans Tchadok |
|---|---|---|
| `01` | Succès, callback après 2 s | Commande `paid`, facture, droits d'accès |
| `02` | Succès après 15 s (abonné lent) | Idem ; la page d'attente patiente |
| `03` | Échec : solde insuffisant | Tentative `failed`, commande `failed`, message « Solde insuffisant. » ; nouvel essai possible |
| `04` | Échec : code secret erroné | Tentative `failed` (`invalid_pin`) |
| `05` | **Aucun callback** | Tentative en attente, puis `expired` après `PAYMENT_INTENT_TTL`, **après** consultation de l'opérateur ; commande retentable |
| `06` | Annulé par l'abonné | Tentative et commande `cancelled` |
| `07` | Succès, **callback envoyé trois fois** | Une seule facture, un seul droit ; l'application répond `200` aux trois |
| `08` | Succès, **callback mal signé** | Callback rejeté (`401`) et tracé ; la commande est payée à la **consultation** suivante de l'opérateur (page d'attente, ou `paiements.php expirer`) |
| `09` | Succès, **montant altéré** dans le callback (+1 FCFA) | Tentative et commande en **revue** (`review`), sans facture ni droit ; montant annoncé conservé |
| `10` | Erreur `500` à l'initiation | Refus immédiat, « L'opérateur ne répond pas » ; commande retentable |
| autre | Succès après 5 s | Commande `paid` |

### Versements aux artistes (Airtel Money, Moov Money)

Le **numéro du compte de versement** de l'artiste choisit le scénario, sur le même modèle (`66000011`, `65000013`...). GIMAC et VISA ne font pas de versement sortant.

| N° | Comportement du simulateur | Résultat attendu dans Tchadok |
|---|---|---|
| `11` | Numéro invalide : refus immédiat (`422`) | Versement `failed` (`invalid_msisdn`) ; la finance le refuse avec un motif, le montant redevient disponible |
| `12` | Compte non enregistré : échec par callback | Versement `failed` (`account_not_found`), revient en file |
| `13` | Succès après 15 s | Versement `processing` ; relancer l'exécution est refusé (pas de second envoi) ; puis `paid` |
| `14` | Succès, **callback envoyé deux fois** | Un seul essai conclu, versement `paid` une fois |
| autre | Succès après 2 s | Versement `paid`, e-mail à l'artiste |

Parcours complet à la main : l'artiste déclare son compte dans « Revenus et versements » (`artiste-revenus.php`) ; un compte finance le vérifie et valide la demande dans `admin/versements.php` ; **un autre** compte l'exécute. Seules les ventes de plus de `PAYOUT_RETENTION_DAYS` jours sont disponibles : en local, mettre temporairement `PAYOUT_RETENTION_DAYS=0` dans `.env.local`. La panne d'opérateur simulée depuis la console vaut aussi pour les versements : l'envoi échoue, puis se reprend sans double paiement.

---

## 2. Carte : VISA (page hébergée de l'acquéreur)

Le client est redirigé vers la page du simulateur (`http://127.0.0.1:9103/hosted/...`), y saisit la carte, puis revient sur `paiement.php`, qui **attend le callback** : le retour du navigateur ne vaut pas preuve de paiement.

Date d'expiration et cryptogramme : quelconques.

| Numéro | Résultat |
|---|---|
| `4111 1111 1111 1111` | Succès sans 3-D Secure |
| `4000 0000 0000 3220` | Succès **avec** défi 3-D Secure — code `123456` (tout autre code : échec) |
| `4000 0000 0000 0002` | Refusée par l'émetteur (`card_declined`) |
| `4000 0000 0000 9995` | Refusée : provision insuffisante |
| `4000 0000 0000 0069` | Refusée : carte expirée |
| `4000 0000 0000 0127` | Refusée : cryptogramme incorrect |
| `4000 0000 0000 0119` | Erreur de traitement de l'acquéreur |
| `4000 0000 0000 0259` | Succès, puis **contestation** 30 s plus tard : commande `disputed`, droits d'accès révoqués |
| autre numéro valide (Luhn) | Succès |

« Annuler et revenir au marchand » sur la page : tentative `cancelled`.

**Dollar US (diaspora).** Sur `paiement.php`, choisir la carte puis « Dollar US ». Le taux est le **cours du jour**, lu dans une API gratuite (open.er-api.com, à défaut frankfurter.app × parité fixe 655,957) ; **sans connexion Internet, 600 FCFA** — 1 500 FCFA sont alors débités **2,50 $ US**. Arrondi au cent supérieur ; la page indique d'où vient le taux, et la tentative le fige avec sa provenance. Mêmes cartes de test. La commande et la facture restent en francs. Taux et provenance : `php scripts/devises.php liste` ; nouveau cours immédiat : `php scripts/devises.php actualiser`. Pour tester hors ligne, couper le réseau ou mettre `DEVISES_COURS=manuel` (dernier taux saisi, 600 au départ).

Ces numéros sont les numéros de test standards de l'industrie, rattachés à aucun compte réel. **Aucun numéro de carte n'est conservé**, ni par Tchadok ni par le simulateur.

---

## 3. Réglages et observation

**Console de pilotage : `http://127.0.0.1:9100`** (démarrée par le lanceur).

| Besoin | Moyen |
|---|---|
| Voir les transactions des quatre passerelles | Console, page d'accueil (filtre par passerelle, actualisation automatique) |
| Valider, refuser, annuler une transaction en attente | Console, détail de la transaction |
| Laisser une transaction sans réponse (tester l'expiration) | Console : « Abandonner » retire ses callbacks de la file |
| Rejouer un callback, l'envoyer mal signé ou au montant altéré | Console, sur une transaction terminée |
| Contester un paiement carte | Console : « Contester » sur une transaction VISA réussie |
| Voir la réponse de Tchadok à chaque callback | Console (détail, et « Derniers callbacks ») |
| Accélérer les délais, simuler des échecs aléatoires ou une panne d'opérateur | Console, « Réglages » |
| Voir une commande côté Tchadok | `php scripts/paiements.php statut <référence>` |
| Rapprocher les relevés | `php scripts/rapprochement.php --date=AAAA-MM-JJ`, ou l'écran `admin/rapprochement.php` |
| Tout réinitialiser | Console, « Tout effacer » |

**À savoir sur le rapprochement en local.** Les tests automatisés effacent leurs commandes à la fin, mais pas les transactions des simulateurs : le rapprochement du jour les signale donc comme « argent reçu sans tentative Tchadok ». C'est le comportement attendu ; « Tout effacer » dans la console remet les deux côtés d'accord.

---

## 4. Tests automatisés

```
C:\xampp\php\php.exe tests\paiement\pay-passerelles.php
```

Exerce le chemin réel (application → simulateur → callback via Apache) : les dix scénarios Airtel, Moov et GIMAC en nouvel essai, tous les cas VISA, les callbacks rejetés, simultanés, tardifs et inversés, le double encaissement, et l'absence de toute donnée de carte. Démarre les simulateurs s'ils ne tournent pas.

Les versements aux artistes (scénarios 11 à 14, panne, reprise) sont exercés par `tests\paiement\payout-versements.php`.
