<?php
/**
 * TCHADOK PLATFORM - INSTALLATION SCRIPT
 * Crée les tables et génère les données de test
 */

// Configuration directe de la base de données
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'tchadok';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   TCHADOK - SCRIPT D'INSTALLATION                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "Configuration:\n";
echo "  • Hôte: $host\n";
echo "  • Base de données: $dbname\n";
echo "  • Utilisateur: $username\n\n";

try {
    // Connexion à la base de données
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $db = new PDO($dsn, $username, $password, $options);
    echo "✓ Connexion à la base de données réussie\n\n";

    // Lire et exécuter le schéma SQL
    echo "━━━ CRÉATION DES TABLES ━━━\n";
    $sql = file_get_contents(__DIR__ . '/schema.sql');

    // Séparer les instructions SQL
    $statements = array_filter(array_map('trim', preg_split('/;[\s]*$/m', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $db->exec($statement);
                // Extraire le nom de la table
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?\s*\(/i', $statement, $matches)) {
                    echo "  • Table '{$matches[1]}' créée\n";
                }
            } catch (PDOException $e) {
                // Ignorer les erreurs "table already exists"
                if (strpos($e->getMessage(), '1050') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "\n✓ Toutes les tables ont été créées\n\n";

    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✓ INSTALLATION TERMINÉE AVEC SUCCÈS !                   ║\n";
    echo "║                                                            ║\n";
    echo "║   Prochaine étape:                                         ║\n";
    echo "║   php database/generate-test-data.php                      ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERREUR DE BASE DE DONNÉES: " . $e->getMessage() . "\n\n";
    echo "Vérifiez que:\n";
    echo "  1. MySQL est démarré\n";
    echo "  2. La base de données '$dbname' existe\n";
    echo "  3. Les identifiants sont corrects dans le fichier .env\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ ERREUR: " . $e->getMessage() . "\n\n";
    exit(1);
}
