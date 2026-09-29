<?php
/**
 * API Transactions - NEUTRALISEE (SEC-01)
 *
 * Cette API exposait, SANS AUCUN CONTROLE D'ACCES :
 *   - action=create  : creation d'une transaction arbitraire (user_id,
 *                      amount et status tous fournis par le client)
 *   - action=approve : passage d'une transaction en "completed"
 *   - action=reject  : passage en "failed"
 *   - action=delete  : suppression definitive d'une transaction
 *   - action=stats   : agregats financiers
 *
 * Le chiffre d'affaires affiche sur le dashboard admin
 * (SUM(amount) WHERE status = 'completed') etait donc entierement
 * pilotable depuis l'exterieur, et la piste d'audit destructible.
 *
 * Regle a respecter lors de la reecriture : le passage d'une
 * transaction en "completed" ne doit JAMAIS etre declenche par un
 * client. Seul un callback signe de l'operateur (Airtel, Moov, VISA,
 * GIMAC) peut le faire - voir PAY-02 et PAY-04. Les transactions
 * doivent devenir immuables : pas de DELETE, pas de modification du
 * montant, uniquement des ecritures d'annulation.
 *
 * Aucun appelant atteignable n'utilisait cette API : les 3 appels
 * existaient dans admin/dashboard-tabs/payments.php, repertoire retire
 * fichier mort (supprime en CLEAN-02).
 *
 * Le code d'origine reste consultable dans l'historique Git,
 * commit 401eaa5 et anterieurs.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(503);

echo json_encode([
    'success' => false,
    'error' => [
        'code' => 503,
        'message' => "API desactivee. La validation des transactions passera par les callbacks operateurs signes (SEC-01, PAY-02)."
    ]
], JSON_UNESCAPED_UNICODE);
