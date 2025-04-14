<?php
// Includi il file di configurazione
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force debug mode ON to see what's happening
$debug_mode = true;

$debug_db = $conn->query("SELECT COUNT(*) as count FROM listings");
if (!$debug_db) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb;'>";
    echo "Error checking listings count: " . $conn->error . "<br>";
    echo "</div>";
    $debug_cards = 0;
} else {
    $debug_cards = $debug_db->fetch_assoc()['count'];
    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "Numero totale di inserzioni nel database: $debug_cards<br>";
    echo "</div>";
}

// Imposta valori predefiniti per ordinamento e filtri
$sort = $_GET['sort'] ?? 'latest';
$game_id = (int)($_GET['game_id'] ?? 0);
$expansion_id = (int)($_GET['expansion_id'] ?? 0);
$condition_id = (int)($_GET['condition_id'] ?? 0);
$rarity_id = (int)($_GET['rarity_id'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$search = $_GET['search'] ?? '';

// Start with a simpler query without filters to see if we get any results
$basic_sql = "SELECT COUNT(*) as count FROM listings";
$basic_result = $conn->query($basic_sql);
if (!$basic_result) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb;'>";
    echo "Error in basic count query: " . $conn->error . "<br>";
    echo "</div>";
} else {
    $basic_count = $basic_result->fetch_assoc()['count'];
    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "Basic count of listings: $basic_count<br>";
    echo "</div>";
}

// Let's try a very simple query first to see if we can get any data at all
$simple_sql = "SELECT l.id, l.price, u.username as seller_name, l.quantity 
               FROM listings l
               JOIN accounts u ON l.seller_id = u.id
               LIMIT 10";

echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
echo "<strong>Simple Query:</strong> " . htmlspecialchars($simple_sql) . "<br>";
echo "</div>";

$simple_result = $conn->query($simple_sql);
if (!$simple_result) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb;'>";
    echo "Error in simple query: " . $conn->error . "<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "Simple query returned " . $simple_result->num_rows . " rows<br>";
    if ($simple_result->num_rows > 0) {
        echo "<pre>";
        while ($row = $simple_result->fetch_assoc()) {
            print_r($row);
        }
        echo "</pre>";
    }
    echo "</div>";
    
    // Reset the result pointer for later use if needed
    $simple_result->data_seek(0);
}

// Now proceed with the original code, but with some modifications for debugging
$where_clauses = [];

// Initially, don't apply any filters to see if that's the issue
// Comment out filters temporarily
// if ($game_id > 0) $where_clauses[] = "e.game_id = $game_id";
// if ($expansion_id > 0) $where_clauses[] = "sc.expansion_id = $expansion_id";
// if ($condition_id > 0) $where_clauses[] = "l.condition_id = $condition_id";
// if ($rarity_id > 0) $where_clauses[] = "sc.rarity_id = $rarity_id";
// if ($min_price > 0) $where_clauses[] = "l.price >= $min_price";
// if ($max_price > 0) $where_clauses[] = "l.price <= $max_price";

// For now, let's just ensure we get active listings
$where_clauses[] = "l.is_active = TRUE";

switch ($sort) {
    case 'price_asc': $order_by = "l.price ASC"; break;
    case 'price_desc': $order_by = "l.price DESC"; break;
    case 'name_asc': $order_by = "sc.name_en ASC"; break;
    case 'name_desc': $order_by = "sc.name_en DESC"; break;
    default: $order_by = "l.created_at DESC"; break;
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Build our query step by step to identify where it might be failing
$sql = "SELECT l.id, l.price, l.condition_id, l.quantity, sc.name_en, sc.image_url, 
        e.name as expansion_name, g.display_name as game_name, cc.condition_name, 
        u.username as seller_name, COALESCE(up.rating, 0) as seller_rating 
        FROM listings l
        LEFT JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        LEFT JOIN expansions e ON sc.expansion_id = e.id
        LEFT JOIN games g ON e.game_id = g.id
        LEFT JOIN card_conditions cc ON l.condition_id = cc.id
        LEFT JOIN accounts u ON l.seller_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        $where_clause
        ORDER BY $order_by
        LIMIT 50"; // Add a limit to avoid returning too many rows

echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
echo "<strong>Main Query:</strong> " . htmlspecialchars($sql) . "<br>";
echo "</div>";

// Check each table separately
$tables = ["listings", "single_cards", "expansions", "games", "card_conditions", "accounts", "user_profiles"];
echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
echo "<h4>Table Check:</h4><ul>";
foreach ($tables as $table) {
    $exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0 ? "Exists" : "Missing";
    $count = $exists === "Exists" ? $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'] : "N/A";
    echo "<li>$table: $exists - Count: $count</li>";
}
echo "</ul></div>";

$result = $conn->query($sql);
if (!$result) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb;'>";
    echo "Error in main query: " . $conn->error . "<br>";
    echo "</div>";
    $error_message = "Errore nella query: {$conn->error}";
} else {
    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "Main query returned " . $result->num_rows . " rows<br>";
    echo "</div>";
    
    if ($result->num_rows > 0) {
        echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
        echo "<h4>First Row Data:</h4>";
        echo "<pre>";
        print_r($result->fetch_array(MYSQLI_ASSOC));
        echo "</pre>";
        echo "</div>";
        
        // Reset the result pointer for later use
        $result->data_seek(0);
    }
}

// Queries for filters
$sql_games = "SELECT id, display_name FROM games ORDER BY display_name";
$result_games = $conn->query($sql_games);
$sql_conditions = "SELECT id, condition_name FROM card_conditions ORDER BY id";
$result_conditions = $conn->query($sql_conditions);
$sql_rarities = "SELECT id, rarity_name FROM card_rarities ORDER BY id";
$result_rarities = $conn->query($sql_rarities);
$result_expansions = null;
if ($game_id > 0) {
    $result_expansions = $conn->query("SELECT id, name FROM expansions WHERE game_id = $game_id ORDER BY name");
}

include __DIR__ . '/partials/header.php';
?>

<div class="marketplace-container">
    <div class="filters-sidebar">
        <h2>Filtri</h2>
        <form action="marketplace.php" method="GET" id="filter-form">
            <!-- Filter form fields remain the same -->
            <!-- ... -->
            <div class="filter-group">
                <label for="search">Cerca</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nome carta...">
            </div>
            
            <div class="filter-group">
                <label for="game_id">Gioco</label>
                <select id="game_id" name="game_id" onchange="this.form.submit()">
                    <option value="0">Tutti i giochi</option>
                    <?php
                    if ($result_games && $result_games->num_rows > 0) {
                        while($game = $result_games->fetch_assoc()) {
                            $selected = ($game_id == $game["id"]) ? "selected" : "";
                            echo '<option value="' . $game["id"] . '" ' . $selected . '>' . htmlspecialchars($game["display_name"]) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Applica filtri</button>
                <a href="marketplace.php" class="btn">Reimposta</a>
            </div>
        </form>
    </div>
    
    <div class="marketplace-content">
        <h1>Marketplace</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="cards-grid">
            <?php
            if ($result && $result->num_rows > 0) {
                while($card = $result->fetch_assoc()) {
                    ?>
                    <div class="card-item">
                        <a href="listing.php?id=<?php echo $card["id"]; ?>">
                            <div class="card-image">
                                <?php if (isset($card["image_url"]) && $card["image_url"]): ?>
                                    <img src="<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"] ?? 'Card'); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">Immagine non disponibile</div>
                                <?php endif; ?>
                            </div>
                            <div class="card-details">
                                <h3><?php echo htmlspecialchars($card["name_en"] ?? 'Unknown Card'); ?></h3>
                                <p class="expansion"><?php echo htmlspecialchars($card["expansion_name"] ?? 'Unknown'); ?> (<?php echo htmlspecialchars($card["game_name"] ?? 'Unknown Game'); ?>)</p>
                                <p class="condition">Condizione: <?php echo htmlspecialchars($card["condition_name"] ?? 'Unknown'); ?></p>
                                <p class="seller">Venditore: <?php echo htmlspecialchars($card["seller_name"] ?? 'Unknown'); ?> 
                                    <span class="rating"><?php $rating = min(5, max(0, round($card["seller_rating"] ?? 0))); echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?></span>
                                </p>
                                <div class="price-container">
                                    <span class="price"><?php echo number_format($card["price"] ?? 0, 2, ',', '.'); ?> €</span>
                                    <span class="quantity">Disp: <?php echo $card["quantity"] ?? 0; ?></span>
                                </div>
                            </div>
                        </a>
                        <div class="card-actions">
                            <button class="btn-cart" data-listing-id="<?php echo $card["id"]; ?>">
                                <i class="fas fa-cart-plus"></i> Aggiungi
                            </button>
                            <button class="btn-wishlist" data-card-id="<?php echo $card["id"]; ?>">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="no-results">Nessuna carta trovata con i filtri selezionati</div>';
                
                // Additional debug output for empty results
                echo '<div style="background: #f8d7da; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb;">';
                echo 'No cards found. Possible reasons:<br>';
                echo '1. The database may be empty<br>';
                echo '2. Filters are too restrictive<br>';
                echo '3. There\'s a JOIN condition issue in the query<br>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>