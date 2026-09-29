# Définition de l'écoute comptabilisée

**Version 1 — proposée le 28/09/2026, à valider par la direction.**
Référence : `STAT-01`. Mise en œuvre : `includes/ecoutes.php` (enregistrement, `STAT-02`), puis certification (`STAT-03`).

Cette définition fonde les classements, le baromètre et la **rémunération des artistes** sur les abonnements. Elle doit figurer au contrat de distribution (`PAYOUT-05`). Toute modification ouvre une nouvelle version, datée ; elle ne s'applique jamais rétroactivement à une période déjà arrêtée.

## 1. Ce qui compte comme une écoute

| Règle | Valeur |
|---|---|
| Durée minimale | **30 secondes de lecture effective** du titre complet |
| Titre de moins de 30 s | Lecture **complète** |
| Extrait (30 s de prévisualisation) | **Ne compte pas** : seule la lecture du titre complet est une écoute |
| Déduplication | **Une écoute par auditeur et par titre, par fenêtre de 60 minutes** |
| Auditeur non connecté | Compté, identifié par une **empreinte anonyme** (adresse IP tronquée + navigateur, hachées avec un sel renouvelé chaque jour) — jamais d'identifiant persistant sans consentement |
| Écoutes radio | Comptées **à part**, jamais mêlées aux écoutes à la demande |
| Écoutes d'un artiste sur ses propres titres | **Exclues** des classements et de la rémunération |
| Période d'arrêté | Semaine du **lundi 00:00 au dimanche 23:59**, heure de N'Djamena (UTC+1) |
| Délai de consolidation | **48 heures** après la fin de période avant publication |

## 2. Comment elle est mesurée (garanties techniques)

- **Le serveur décide, pas le navigateur.** À l'ouverture d'un titre, le serveur remet un **jeton d'écoute** à usage unique, lié au titre et à l'auditeur. Une écoute n'est enregistrée qu'avec ce jeton, **une seule fois**.
- **Le seuil de 30 s est vérifié côté serveur**, à partir de l'heure d'émission du jeton : une durée annoncée par le navigateur ne suffit jamais.
- **La durée d'un titre est lue dans le fichier audio** au dépôt ; l'artiste ne la saisit pas et ne peut pas la modifier.
- **Le pays et la ville ne sont jamais fournis par le navigateur.** Ils seront déduits de l'adresse IP côté serveur (base de géolocalisation locale, à installer) ; en attendant, ils restent vides.
- **Le journal brut n'est pas le compte officiel.** Chaque écoute enregistrée passe ensuite un filtrage anti-fraude (`STAT-03`) ; seules les écoutes **certifiées** alimentent classements et rémunération. Les écoutes suspectes sont mises en quarantaine, ni supprimées ni comptées, jusqu'à une décision humaine tracée.

## 3. Ce que cela change pour un artiste

- Faire tourner son titre en boucle depuis le même appareil ne compte qu'une fois par heure.
- Écouter ses propres titres ne rapporte rien.
- Un script qui envoie des écoutes sans lecture réelle est refusé : pas de jeton, jeton déjà utilisé, ou écoute trop courte.

## 4. Points à valider par la direction

1. Les valeurs du tableau du §1 (reprises de la proposition du plan).
2. L'inscription de cette définition au contrat de distribution, avant la première répartition des abonnements.
3. Le choix d'une base de géolocalisation (par exemple GeoLite2, gratuite sous licence, à télécharger).
