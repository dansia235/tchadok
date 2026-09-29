# Procédure de revendication de droits et de contre-notification

Référence : `MOD-03`. Mise en œuvre : `includes/signalements.php`, pages `signaler.php`, `artiste-signalements.php`, `admin/signalements.php`.

## 1. Revendication

Toute personne connectée peut signaler un titre ou une sortie pour atteinte au droit d'auteur, en indiquant le **nom du titulaire des droits**, une **adresse e-mail de contact** et une description précise (quelle œuvre, quels droits, quels éléments).

Dès l'envoi, le contenu est **retiré provisoirement** du catalogue public (statut « hors ligne ») : c'est immédiat, sans attendre un modérateur. L'artiste est prévenu par e-mail.

## 2. Contre-notification

L'artiste dispose de **10 jours** pour répondre depuis `artiste-signalements.php`, avec ses justificatifs : contrat avec les ayants droit, preuve d'antériorité (date de création, dépôt auprès d'un organisme de gestion collective), autorisation écrite. Aucune décision n'est prise sans examen de sa réponse si elle arrive dans le délai.

## 3. Décision

L'équipe (permission `signalement.traiter`) tranche, avec un **motif obligatoire** transmis aux deux parties :

| Décision | Effet |
|---|---|
| Retirer | Le contenu reste hors ligne. |
| Maintenir | La revendication n'est pas fondée : le contenu est remis en ligne. |
| Rejeter | Signalement abusif ou incomplet : le contenu est remis en ligne. |

Chaque décision est inscrite au journal d'audit et reste consultable (journal des décisions de `admin/signalements.php`).

## 4. Points à valider par la direction et son conseil

- Le délai de réponse (10 jours proposés).
- Les suites données aux revendications abusives répétées.
- L'articulation avec l'organisme de gestion collective compétent (BUTDRA).
