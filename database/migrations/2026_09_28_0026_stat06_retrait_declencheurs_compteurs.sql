-- STAT-06 : retrait des declencheurs de compteurs.
--
-- Trois declencheurs tenaient les compteurs publics depuis la donnee BRUTE :
--   update_stream_stats        +1 a chaque ligne de `streams`, fraudes et
--                              ecoutes de 0 s comprises ;
--   update_album_tracks_count  a l'ajout d'un titre seulement (jamais au
--                              retrait) ;
--   compter_vente_payee        au passage a « paid » seulement (jamais au
--                              remboursement), et artists.total_sales en
--                              FRANCS quand tracks/releases comptent des
--                              UNITES.
-- Ils sont remplaces par un recalcul explicite, depuis les agregats certifies
-- (includes/agregats.php : Compteurs::recalculer), reconstructible a tout
-- moment (php scripts/rebuild-counters.php).
--
-- Semantique corrigee : artists.total_sales compte des UNITES ;
-- artists.total_earnings porte le montant (part artiste nette, FCFA).
-- Les valeurs sont remises a zero ici puis recalculees par
-- scripts/rebuild-counters.php (a lancer apres la migration).
--
-- Les declencheurs d'IMMUTABILITE (journaux, preuves) restent : ils ne
-- calculent rien, ils interdisent.

-- UP

DROP TRIGGER IF EXISTS `update_stream_stats`;
DROP TRIGGER IF EXISTS `update_album_tracks_count`;
DROP TRIGGER IF EXISTS `compter_vente_payee`;

UPDATE `tracks` SET `total_streams` = 0, `total_sales` = 0, `total_downloads` = 0;
UPDATE `releases` SET `total_streams` = 0, `total_sales` = 0;
UPDATE `artists` SET `total_streams` = 0, `total_sales` = 0, `total_earnings` = 0;

-- DOWN

-- Les compteurs restent ceux du recalcul ; seuls les declencheurs reviennent.
DROP TRIGGER IF EXISTS `update_stream_stats`;
DELIMITER //
CREATE TRIGGER `update_stream_stats` AFTER INSERT ON `streams` FOR EACH ROW
BEGIN
    UPDATE tracks SET total_streams = total_streams + 1 WHERE id = NEW.track_id;
    UPDATE artists SET total_streams = total_streams + 1 WHERE id = NEW.artist_id;
    UPDATE releases r INNER JOIN tracks t ON r.id = t.album_id
       SET r.total_streams = r.total_streams + 1
     WHERE t.id = NEW.track_id AND t.album_id IS NOT NULL;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `update_album_tracks_count`;
DELIMITER //
CREATE TRIGGER `update_album_tracks_count` AFTER INSERT ON `tracks` FOR EACH ROW
BEGIN
    IF NEW.album_id IS NOT NULL THEN
        UPDATE releases SET total_tracks = (SELECT COUNT(*) FROM tracks WHERE album_id = NEW.album_id) WHERE id = NEW.album_id;
    END IF;
END//
DELIMITER ;
DROP TRIGGER IF EXISTS `compter_vente_payee`;
DELIMITER //
CREATE TRIGGER `compter_vente_payee` AFTER UPDATE ON `orders` FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
        UPDATE `tracks` t SET t.total_sales = t.total_sales + 1
         WHERE t.id IN (SELECT item_id FROM `order_items` WHERE order_id = NEW.id AND item_type = 'track');
        UPDATE `releases` r SET r.total_sales = r.total_sales + 1
         WHERE r.id IN (SELECT item_id FROM `order_items` WHERE order_id = NEW.id AND item_type = 'release');
        UPDATE `artists` a SET a.total_sales = a.total_sales + (
                   SELECT COALESCE(SUM(oi.artist_net), 0) FROM `order_items` oi WHERE oi.order_id = NEW.id AND oi.artist_id = a.id)
         WHERE a.id IN (SELECT artist_id FROM `order_items` WHERE order_id = NEW.id AND artist_id IS NOT NULL);
    END IF;
END//
DELIMITER ;
