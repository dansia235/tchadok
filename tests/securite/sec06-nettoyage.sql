-- Nettoyage du jeu d'essai SEC-06.
-- La suppression de l'artiste 901 entraine celle des titres 9001-9007
-- (cle etrangere ON DELETE CASCADE). Les ecritures commerciales (DATA-05), en
-- revanche, refusent la cascade : une ecriture comptable ne disparait pas parce
-- qu'un compte est supprime. Elles sont donc retirees explicitement, et dans
-- l'ordre -- droits, lignes, commandes.
DELETE FROM entitlements WHERE user_id BETWEEN 901 AND 905;
DELETE FROM order_items  WHERE order_id BETWEEN 9001 AND 9009;
DELETE FROM orders       WHERE id BETWEEN 9001 AND 9009;
DELETE FROM streams      WHERE track_id BETWEEN 9001 AND 9007;
DELETE FROM tracks       WHERE id BETWEEN 9001 AND 9007;
DELETE FROM artists      WHERE id = 901;
DELETE FROM users        WHERE id BETWEEN 901 AND 905;
