-- Bootstrap radio_live pour moteur de stream (Icecast/Liquidsoap)
-- Adapter l'URL du flux avant execution.

SET @public_stream_url = 'https://radio.tchadok.td/tchadok.mp3';

INSERT INTO radio_live (stream_url, listeners_count, is_live, updated_at)
SELECT @public_stream_url, 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM radio_live);

UPDATE radio_live
SET stream_url = @public_stream_url, is_live = 1, updated_at = NOW()
WHERE id = (SELECT id FROM (SELECT id FROM radio_live ORDER BY id ASC LIMIT 1) t);

SELECT id, stream_url, listeners_count, is_live, updated_at
FROM radio_live
ORDER BY id ASC
LIMIT 1;

