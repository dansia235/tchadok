-- Nettoyage du jeu d'essai SEC-06.
-- La suppression de l'artiste 901 entraine celle des titres 9001-9007
-- (cle etrangere ON DELETE CASCADE). Les achats sont supprimes
-- explicitement, purchases n'ayant pas de cascade sur users.
DELETE FROM purchases WHERE user_id BETWEEN 901 AND 905;
DELETE FROM streams   WHERE track_id BETWEEN 9001 AND 9007;
DELETE FROM tracks    WHERE id BETWEEN 9001 AND 9007;
DELETE FROM artists   WHERE id = 901;
DELETE FROM users     WHERE id BETWEEN 901 AND 905;
