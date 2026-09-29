# Contrat générique des passerelles de paiement

**Statut :** contrat provisoire, en vigueur pour les quatre passerelles — Airtel Money, Moov Money, VISA et GIMAC — tant que la documentation officielle de chaque partenaire n'est pas obtenue (`PAY-11`).

**Pourquoi un contrat unique.** Aucune spécification partenaire n'est disponible. Plutôt que d'imiter des API supposées, l'application et les simulateurs partagent un contrat écrit ici, complet et testé. Le jour où un partenaire fournit sa documentation, **seul son adaptateur change** (`includes/paiement/passerelles/<Passerelle>.php`) ; la machine à états, les callbacks, le tunnel et les tests restent.

---

## 1. Deux parcours

| Parcours | Passerelles | Déroulé |
|---|---|---|
| **push** | Airtel Money, Moov Money, GIMAC | Tchadok demande le paiement ; l'opérateur envoie une demande de confirmation au téléphone du client (USSD, application) ; le client valide avec son code ; l'opérateur notifie Tchadok. |
| **hosted** | VISA | Tchadok ouvre une session ; le client est redirigé vers la **page de l'acquéreur**, y saisit sa carte, passe le 3-D Secure ; l'acquéreur notifie Tchadok et renvoie le client. |

**Aucune donnée de carte ne transite par Tchadok ni n'y est stockée**, pas même en local : c'est le seul modèle qui dispense la plateforme de la certification PCI DSS complète, et il est adopté dès le simulateur pour que la production n'ait pas à changer d'architecture.

GIMAC étant la plateforme d'interopérabilité monétique de la CEMAC, son parcours push vise un compte identifié par un numéro de téléphone, quel que soit l'émetteur du portefeuille.

---

## 2. Requêtes de Tchadok vers la passerelle

### 2.1. Authentification

Chaque requête porte :

| En-tête | Valeur |
|---|---|
| `X-Merchant-Id` | identifiant marchand (`<PASSERELLE>_MERCHANT_ID`) |
| `X-Timestamp` | horodatage Unix, en secondes |
| `X-Signature` | `hex(HMAC-SHA256(<PASSERELLE>_API_KEY, timestamp + "." + MÉTHODE + "." + chemin + "." + corps))` |
| `Idempotency-Key` | à l'initiation uniquement : 32 caractères hexadécimaux, propres à la tentative |

- La clé d'API **ne circule jamais** : elle sert seulement à signer.
- Écart d'horloge toléré : **300 secondes**. Au-delà, `401 timestamp_out_of_range` — protection contre le rejeu, et source classique d'incident si l'horloge du serveur dérive (NTP obligatoire en production).
- Corps vide pour un `GET` : la signature porte alors sur `timestamp.GET.chemin.`.

### 2.2. Points de terminaison

| Méthode | Chemin | Rôle |
|---|---|---|
| `POST` | `/v1/payments` | Initier un paiement |
| `GET` | `/v1/payments/{id}` | Consulter son statut |
| `POST` | `/v1/payments/{id}/refund` | Rembourser, totalement ou partiellement |
| `GET` | `/v1/statements?date=AAAA-MM-JJ` | Relevé d'une journée pour le rapprochement (`PAY-10`) : transactions initiées ce jour-là et encaissées, remboursées ou contestées, au format du statut |
| `POST` | `/v1/disbursements` | Versement sortant vers le portefeuille d'un artiste (`LOT 8`) — Airtel Money et Moov Money seulement |
| `GET` | `/v1/disbursements/{id}` | Consulter le statut d'un versement sortant |

La signature porte sur le **chemin sans la chaîne de requête** (`/v1/statements`).

**Versement sortant — corps JSON :**

```json
{
  "reference":    "VRS-42-1",
  "amount":       17000,
  "currency":     "XAF",
  "msisdn":       "66123456",
  "description":  "Tchadok - versement artiste",
  "callback_url": "https://tchadok.td/api/payments/callback.php?passerelle=airtel_money"
}
```

- `reference` = `VRS-<versement>-<essai>` ; chaque essai porte sa propre `Idempotency-Key`. Rejouer la même clé renvoie le **même** versement (`200`), sans second envoi.
- Seul le **XAF** est versé. Réponse `201` au format du statut (`status` ∈ `pending`, `succeeded`, `failed`). Numéro refusé d'emblée : `422 invalid_msisdn`.
- Une erreur réseau n'est **pas** un échec : Tchadok relance le même essai, avec la même clé.

**Initiation — corps JSON :**

```json
{
  "reference":    "TCHK-2026-A7F3B2C1-1",
  "amount":       1500,
  "currency":     "XAF",
  "flow":         "push",
  "msisdn":       "66000001",
  "description":  "Tchadok - commande TCHK-2026-A7F3B2C1",
  "callback_url": "https://tchadok.td/api/payments/callback.php?passerelle=airtel_money",
  "return_url":   null
}
```

- `amount` est un **entier en unités mineures** de la devise : francs pour le XAF (pas de subdivision), **cents** pour l'USD (2,50 $ → `250`). Jamais de nombre à virgule.
- `currency` : `XAF` pour tous ; `USD` en plus pour le parcours `hosted` (carte), réservé à la diaspora. Le montant USD est converti par Tchadok au taux en vigueur (`exchange_rates`), arrondi au cent supérieur, et **figé sur la tentative** ; la commande et la comptabilité restent en XAF.
- `flow` vaut `push` (avec `msisdn`) ou `hosted` (avec `return_url`).
- `reference` identifie la **tentative** (référence de commande + numéro d'essai) : une commande peut être retentée avec un autre moyen.

**Réponse `201` :**

```json
{ "id": "AIR-9F2C...", "status": "pending", "redirect_url": null }
```

Pour `hosted`, `redirect_url` est l'adresse de la page de l'acquéreur. Rejouer la même `Idempotency-Key` renvoie la **même** transaction (`200`), sans nouveau débit.

**Statut :**

```json
{ "id": "AIR-9F2C...", "reference": "TCHK-...-1", "amount": 1500, "currency": "XAF",
  "status": "pending", "failure_code": null, "updated_at": "2026-09-28T10:00:00+01:00" }
```

`status` ∈ `pending`, `succeeded`, `failed`, `cancelled`, `disputed`, `refunded`.

**Erreurs :** code HTTP `4xx`/`5xx` et corps `{"error": {"code": "...", "message": "..."}}`. Codes : `invalid_signature`, `timestamp_out_of_range`, `unknown_merchant`, `invalid_request`, `invalid_msisdn`, `not_found`, `not_refundable`, `internal_error`.

---

## 3. Notifications (callbacks) de la passerelle vers Tchadok

`POST` sur la `callback_url` de l'initiation, corps JSON :

```json
{
  "event":      "payment.succeeded",
  "event_id":   "evt_5d1c...",
  "created_at": "2026-09-28T10:00:05+01:00",
  "payment":    { "id": "AIR-9F2C...", "reference": "TCHK-...-1", "amount": 1500,
                  "currency": "XAF", "status": "succeeded", "failure_code": null }
}
```

| Événement | Effet côté Tchadok |
|---|---|
| `payment.succeeded` | Commande payée, facture, droits d'accès — **si** le montant et la devise correspondent exactement |
| `payment.failed` | Tentative échouée ; le client peut réessayer |
| `payment.cancelled` | Tentative annulée par le client |
| `payment.disputed` | Contestation carte : commande `disputed`, droits d'accès retirés |
| `refund.succeeded` | Confirme le remboursement (`SHOP-07`) |
| `disbursement.succeeded` | Versement artiste `paid` — **si** le montant correspond ; sinon le versement est suspendu pour vérification |
| `disbursement.failed` | Versement en échec, avec le code (`account_not_found`...) ; il revient en file, à reprendre ou refuser |

Un versement sortant est notifié sous la clé `disbursement` (au lieu de `payment`), avec les mêmes champs.

**Signature :** en-tête `X-Signature: t=<horodatage>,v1=<hex(HMAC-SHA256(<PASSERELLE>_WEBHOOK_SECRET, horodatage + "." + corps brut))>`.

Tchadok, dans cet ordre :

1. lit le **corps brut** — la signature porte sur les octets exacts ;
2. inscrit l'événement dans `payment_events` **avant tout traitement**, signature valide ou non : la preuve existe même si la suite échoue ;
3. vérifie la signature en temps constant, et l'écart d'horloge (300 s) ; sinon `401` ;
4. en production, vérifie que l'adresse source figure dans la liste déclarée de l'opérateur (`PAY-11`) ;
5. applique la transition sous verrou ;
6. répond `200` — **y compris pour un doublon ou une tentative inconnue**, pour que l'opérateur cesse de réessayer ; `500` si le traitement a échoué, pour qu'il réessaie.

Une redirection du navigateur après le paiement (`return_url`) **ne vaut jamais preuve** : seul un callback signé, ou une consultation de statut authentifiée, fait passer une commande en `paid`.

---

## 4. Points à confirmer auprès de chaque partenaire

À obtenir avant la bascule (`PAY-11`), pour Airtel Money, Moov Money, l'acquéreur VISA et GIMAC :

- [ ] mode d'authentification réel (OAuth2 *client credentials*, clé d'API, signature de requête) ;
- [ ] format exact de l'initiation, du statut et des erreurs ;
- [ ] mécanisme de notification : callback poussé, ou consultation périodique seulement ;
- [ ] méthode de signature des callbacks, et liste des adresses IP émettrices ;
- [ ] tolérance d'horloge et protection contre le rejeu ;
- [ ] idempotence de l'initiation (en-tête dédié ou référence unique) ;
- [ ] délais : durée de validité d'une demande push, délai maximal d'un callback ;
- [ ] remboursements : disponibles ou non, partiels ou non, délais ;
- [ ] **versements sortants** (Airtel, Moov) : API disponible pour un marchand, vérification du nom du titulaire, plafonds, frais, alimentation du compte de décaissement ;
- [ ] contestations (VISA) : délai, notification, pièces à fournir ;
- [ ] devises acceptées : l'acquéreur carte accepte-t-il le règlement en USD (décision du 28/09/2026), et qui porte la conversion — Tchadok (modèle actuel, taux administré) ou l'acquéreur ;
- [ ] plafonds par transaction et par jour ;
- [ ] frais et commissions de l'opérateur, et leur prélèvement (à la source ou facturés) ;
- [ ] environnement de test (bac à sable) et numéros ou cartes de test ;
- [ ] format du relevé quotidien pour la réconciliation (`PAY-10`) ;
- [ ] **GIMAC** : conditions d'adhésion (directe, ou via une banque membre) et liste des émetteurs couverts.

---

## 5. Correspondance avec le code

| Élément | Fichier |
|---|---|
| Interface commune | `includes/paiement/PasserellePaiement.php` |
| Contrat générique (requêtes, signatures) | `includes/paiement/PasserelleGenerique.php` |
| Adaptateurs | `includes/paiement/passerelles/` |
| Machine à états | `includes/paiement/Paiements.php` |
| Réception des callbacks | `api/payments/callback.php` |
| Versements aux artistes | `includes/paiement/Versements.php` |
| Simulateurs | `mock-gateways/` — voir `docs/paiement/jeux-de-test.md` |
