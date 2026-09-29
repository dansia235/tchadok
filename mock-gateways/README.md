# Simulateurs de paiement — LOCAL UNIQUEMENT

Quatre faux opérateurs — **Airtel Money, Moov Money, VISA, GIMAC** — qui implémentent le contrat générique (`docs/paiement/contrat-generique.md`) pour que l'application exerce, en local, **exactement le même code** qu'en production.

> **Ce répertoire ne doit jamais être déployé.** `scripts/env-switch.php production` refuse la bascule tant qu'il est présent, `includes/environment-guard.php` le signale, et les deux `.htaccess` en interdisent l'accès web.

## Démarrer

```
scripts\mock-gateways.bat         (arrêt : scripts\mock-gateways.bat stop)
```

| Simulateur | Adresse | Parcours |
|---|---|---|
| Airtel Money | `http://127.0.0.1:9101` | push |
| Moov Money | `http://127.0.0.1:9102` | push |
| VISA | `http://127.0.0.1:9103` | page hébergée + 3-D Secure |
| GIMAC | `http://127.0.0.1:9104` | push (CEMAC) |
| **Console** | `http://127.0.0.1:9100` | pilotage : valider, refuser, rejouer, pannes, réglages |
| Distributeur | fenêtre « callbacks » | envoie les callbacks différés |

Ils écoutent sur `127.0.0.1` uniquement, et refusent de démarrer si l'environnement n'est pas `local`. Ils lisent les identifiants marchands et les secrets de `.env.local` : l'application et les simulateurs partagent les mêmes valeurs, comme avec un vrai opérateur.

## Ce qu'ils imitent fidèlement

- **Authentification du marchand** : requête signée HMAC, horodatage à ±300 s. Mauvaise clé, marchand inconnu ou horodatage périmé : `401`.
- **Idempotence** de l'initiation par l'en-tête `Idempotency-Key`.
- **Callbacks différés** et **signés**, envoyés par un processus distinct après le délai du scénario — comme un abonné qui valide sur son téléphone.
- **Pannes** : callback en triple, mal signé, au montant altéré, jamais envoyé ; erreur `500` ; contestation de carte.
- **Persistance** : les transactions survivent au redémarrage.

Scénarios : `docs/paiement/jeux-de-test.md`.

## Organisation

```
mock-gateways/
├── lib/
│   ├── amorce.php        refus hors local, configuration depuis .env.local
│   ├── Magasin.php       stockage JSON sous verrou, file des callbacks
│   ├── Scenarios.php     numéros et cartes de test
│   ├── Simulateur.php    API du contrat générique
│   └── PageHebergee.php  page de l'acquéreur VISA (carte, 3-D Secure)
├── airtel/ moov/ visa/ gimac/   routeurs du serveur intégré de PHP
├── distributeur.php      envoi des callbacks à l'échéance
└── storage/              état (ignoré par Git) — le supprimer réinitialise tout
```

Les simulateurs **ne réutilisent pas** le code de signature de l'application : un bogue identique des deux côtés passerait sinon inaperçu. Seul le contrat est partagé.
