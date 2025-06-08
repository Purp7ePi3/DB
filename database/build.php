<?php
$host = 'localhost';
$db   = 'app_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connessione riuscita.<br>";
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Correct list of rarities matching the actual data in your table
$rarities = ['Common', 'Uncommon', 'Rare', 'Super Rare', 'Ultra Rare', 'Secret Rare', 'Promo'];

// Mappa nome rarità → id
$rarityMap = [];
try {
    // Corrected table name to match your actual database structure
    $stmt = $pdo->query("SELECT id, rarity_name FROM card_rarities");
    foreach ($stmt->fetchAll() as $row) {
        $rarityMap[$row['rarity_name']] = $row['id'];
    }
    
    // Display the mapping for debugging
    echo "Mapped rarities: " . count($rarityMap) . "<br>";
    
    // Seleziona tutte le carte con rarity_id NULL
    $stmt = $pdo->query("SELECT * FROM single_cards WHERE rarity_id IS NULL");
    $cards = $stmt->fetchAll();
    
    $updatedCount = 0;
    foreach ($cards as $card) {
        $randomName = $rarities[array_rand($rarities)];
        $rarityId = $rarityMap[$randomName] ?? null;
    
        if ($rarityId !== null) {
            $updateStmt = $pdo->prepare("UPDATE single_cards SET rarity_id = ? WHERE blueprint_id = ?");
            $updateStmt->execute([$rarityId, $card['blueprint_id']]);
            $updatedCount++;
        } else {
            echo "Warning: Rarity '$randomName' not found in database.<br>";
        }
    }
    
    echo "Rarità generate e aggiornate con successo: $updatedCount carte aggiornate.";
} catch (\PDOException $e) {
    echo "Errore: " . $e->getMessage();
}
?>