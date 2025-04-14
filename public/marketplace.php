<?php
// Includi il file di configurazione
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

// Build the WHERE clause for filtering
$where_clauses = [];

// For now, ensure we only get active listings
$where_clauses[] = "l.is_active = TRUE";

// Add other filters (you can uncomment these once the basic display works)
// if ($game_id > 0) $where_clauses[] = "e.game_id = $game_id";
// if ($expansion_id > 0) $where_clauses[] = "sc.expansion_id = $expansion_id";
// if ($condition_id > 0) $where_clauses[] = "l.condition_id = $condition_id";
// if ($rarity_id > 0) $where_clauses[] = "sc.rarity_id = $rarity_id";
// if ($min_price > 0) $where_clauses[] = "l.price >= $min_price";
// if ($max_price > 0) $where_clauses[] = "l.price <= $max_price";
// if (!empty($search)) $where_clauses[] = "sc.name_en LIKE '%" . $conn->real_escape_string($search) . "%'";

switch ($sort) {
    case 'price_asc': $order_by = "l.price ASC"; break;
    case 'price_desc': $order_by = "l.price DESC"; break;
    case 'name_asc': $order_by = "sc.name_en ASC"; break;
    case 'name_desc': $order_by = "sc.name_en DESC"; break;
    default: $order_by = "l.created_at DESC"; break;
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Simple query to test if we can get data
$sql = "SELECT 
            l.id, 
            l.price, 
            l.condition_id, 
            l.quantity, 
            sc.name_en, 
            sc.image_url, 
            e.name as expansion_name, 
            g.display_name as game_name, 
            cc.condition_name, 
            u.username as seller_name, 
            COALESCE(up.rating, 0) as seller_rating 
        FROM listings l
        LEFT JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        LEFT JOIN expansions e ON sc.expansion_id = e.id
        LEFT JOIN games g ON e.game_id = g.id
        LEFT JOIN card_conditions cc ON l.condition_id = cc.id
        LEFT JOIN accounts u ON l.seller_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        $where_clause
        ORDER BY $order_by
        LIMIT 50";

// Add debug output to see what's happening
$result = $conn->query($sql);
$error_message = null;

if (!$result) {
    $error_message = "Errore nella query: {$conn->error}";
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
        
        <!-- Debug output to check query results -->
        <div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
            <?php 
            if ($result) {
                echo "Query returned " . $result->num_rows . " rows<br>";
            } else {
                echo "Query failed: " . $conn->error . "<br>";
            }
            ?>
        </div>
        
        <div class="cards-grid">
            <?php
            // Make sure to reset the result pointer if you've used it earlier
            if ($result) {
                $result->data_seek(0);
            }
            
            // Explicitly check if we have results
            if ($result && $result->num_rows > 0) {
                // Loop through each row
                while ($card = $result->fetch_assoc()) {
                    // Debug output for first card
                    if (!isset($first_card_shown)) {
                        echo '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
                        echo '<strong>First card data:</strong><pre>';
                        print_r($card);
                        echo '</pre></div>';
                        $first_card_shown = true;
                    }
            ?>
                    <div class="card-item">
                        <a href="listing.php?id=<?php echo $card["id"]; ?>">
                            <div class="card-image">
                                <?php if (isset($card["image_url"]) && !empty($card["image_url"])): ?>
                                    <img src="https://www.cardtrader.com/images/games/<?php echo htmlspecialchars($card["sc.image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"] ?? 'Card'); ?>" loading="lazy">
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
                } // end while
            } else {
                // No results or query failed
                echo '<div class="no-results">Nessuna carta trovata con i filtri selezionati</div>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Add basic inline JS for cart functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add to cart functionality
    const cartButtons = document.querySelectorAll('.btn-cart');
    cartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Funzionalità in sviluppo');
        });
    });
    
    // Wishlist functionality
    const wishlistButtons = document.querySelectorAll('.btn-wishlist');
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Funzionalità in sviluppo');
        });
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>