<?php
// Includi il file di configurazione
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$debug_db = $conn->query("SELECT COUNT(*) as count FROM listings");
$debug_cards = $debug_db->fetch_assoc()['count'];
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "Numero totale di inserzioni nel database: $debug_cards<br>";
    echo "</div>";
    $debug_mode = true;
} else {
    $debug_mode = false;
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

$where_clauses = ["l.is_active = TRUE"];

if ($game_id > 0) $where_clauses[] = "e.game_id = $game_id";
if ($expansion_id > 0) $where_clauses[] = "sc.expansion_id = $expansion_id";
if ($condition_id > 0) $where_clauses[] = "l.condition_id = $condition_id";
if ($rarity_id > 0) $where_clauses[] = "sc.rarity_id = $rarity_id";
if ($min_price > 0) $where_clauses[] = "l.price >= $min_price";
if ($max_price > 0) $where_clauses[] = "l.price <= $max_price";

switch ($sort) {
    case 'price_asc': $order_by = "l.price ASC"; break;
    case 'price_desc': $order_by = "l.price DESC"; break;
    case 'name_asc': $order_by = "sc.name_en ASC"; break;
    case 'name_desc': $order_by = "sc.name_en DESC"; break;
    default: $order_by = "l.created_at DESC"; break;
}

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where_clauses[] = "(sc.name_en LIKE '%$search%' OR e.name LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? implode(" AND ", $where_clauses) : "1=1";



$sql = "SELECT l.id, l.price, l.condition_id, l.quantity, sc.name_en, sc.image_url, sc.collector_number, e.name as expansion_name, g.display_name as game_name, cc.condition_name, u.username as seller_name, COALESCE(up.rating, 0) as seller_rating 
        FROM listings l
        JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        LEFT JOIN expansions e ON sc.expansion_id = e.id
        LEFT JOIN games g ON e.game_id = g.id
        LEFT JOIN card_conditions cc ON l.condition_id = cc.id
        JOIN accounts u ON l.seller_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE l.is_active = TRUE
        ORDER BY l.created_at DESC";

if ($debug_mode) {
    echo "<div class='debug-info' style='background-color:#f8f9fa;padding:15px;margin-bottom:20px;border:1px solid #ddd;'>";
    echo "<h3>Debug Information</h3>";
    echo "<p><strong>SQL Query:</strong> " . htmlspecialchars($sql) . "</p>";
    $tables = ["listings", "single_cards", "expansions", "games", "card_conditions", "accounts", "user_profiles"];
    echo "<h4>Table Check:</h4><ul>";
    foreach ($tables as $table) {
        $exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0 ? "Exists" : "Missing";
        echo "<li>$table: $exists</li>";
    }
    echo "</ul>";
    $count_res = $conn->query("SELECT COUNT(*) as count FROM listings");
    echo $count_res ? "<p><strong>Total listings:</strong> " . $count_res->fetch_assoc()['count'] . "</p>" : "<p><strong>Error:</strong> {$conn->error}</p>";
}

$result = $conn->query($sql);
if ($debug_mode) {
    echo "<p><strong>Rows returned:</strong> " . ($result ? $result->num_rows : 'Query failed') . "</p>";
    if (!$result) echo "<p><strong>Error:</strong> {$conn->error}</p>";
    echo "</div>";
}

if (!$result) $error_message = "Errore nella query: {$conn->error}";

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
            
            <?php if ($result_expansions && $result_expansions->num_rows > 0): ?>
            <div class="filter-group">
                <label for="expansion_id">Espansione</label>
                <select id="expansion_id" name="expansion_id">
                    <option value="0">Tutte le espansioni</option>
                    <?php
                    while($expansion = $result_expansions->fetch_assoc()) {
                        $selected = ($expansion_id == $expansion["id"]) ? "selected" : "";
                        echo '<option value="' . $expansion["id"] . '" ' . $selected . '>' . htmlspecialchars($expansion["name"]) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label for="condition_id">Condizione</label>
                <select id="condition_id" name="condition_id">
                    <option value="0">Tutte le condizioni</option>
                    <?php
                    if ($result_conditions && $result_conditions->num_rows > 0) {
                        while($condition = $result_conditions->fetch_assoc()) {
                            $selected = ($condition_id == $condition["id"]) ? "selected" : "";
                            echo '<option value="' . $condition["id"] . '" ' . $selected . '>' . htmlspecialchars($condition["condition_name"]) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="rarity_id">Rarità</label>
                <select id="rarity_id" name="rarity_id">
                    <option value="0">Tutte le rarità</option>
                    <?php
                    if ($result_rarities && $result_rarities->num_rows > 0) {
                        while($rarity = $result_rarities->fetch_assoc()) {
                            $selected = ($rarity_id == $rarity["id"]) ? "selected" : "";
                            echo '<option value="' . $rarity["id"] . '" ' . $selected . '>' . htmlspecialchars($rarity["rarity_name"]) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="filter-group price-range">
                <label>Prezzo</label>
                <div class="price-inputs">
                    <input type="number" id="min_price" name="min_price" value="<?php echo $min_price; ?>" placeholder="Min" min="0" step="0.01">
                    <span>-</span>
                    <input type="number" id="max_price" name="max_price" value="<?php echo $max_price; ?>" placeholder="Max" min="0" step="0.01">
                </div>
            </div>
            
            <div class="filter-group">
                <label for="sort">Ordina per</label>
                <select id="sort" name="sort">
                    <option value="latest" <?php echo ($sort == 'latest') ? 'selected' : ''; ?>>Più recenti</option>
                    <option value="price_asc" <?php echo ($sort == 'price_asc') ? 'selected' : ''; ?>>Prezzo crescente</option>
                    <option value="price_desc" <?php echo ($sort == 'price_desc') ? 'selected' : ''; ?>>Prezzo decrescente</option>
                    <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Nome A-Z</option>
                    <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Nome Z-A</option>
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
        
        <?php 
        // Mostra i filtri attivi
        $active_filters = [];
        if (!empty($search)) $active_filters[] = "Ricerca: " . htmlspecialchars($search);
        if ($game_id > 0 && $result_games) {
            $result_games->data_seek(0);
            while($game = $result_games->fetch_assoc()) {
                if ($game["id"] == $game_id) {
                    $active_filters[] = "Gioco: " . htmlspecialchars($game["display_name"]);
                    break;
                }
            }
        }
        if ($expansion_id > 0 && $result_expansions) {
            $result_expansions->data_seek(0);
            while($expansion = $result_expansions->fetch_assoc()) {
                if ($expansion["id"] == $expansion_id) {
                    $active_filters[] = "Espansione: " . htmlspecialchars($expansion["name"]);
                    break;
                }
            }
        }
        if ($condition_id > 0 && $result_conditions) {
            $result_conditions->data_seek(0);
            while($condition = $result_conditions->fetch_assoc()) {
                if ($condition["id"] == $condition_id) {
                    $active_filters[] = "Condizione: " . htmlspecialchars($condition["condition_name"]);
                    break;
                }
            }
        }
        if ($rarity_id > 0 && $result_rarities) {
            $result_rarities->data_seek(0);
            while($rarity = $result_rarities->fetch_assoc()) {
                if ($rarity["id"] == $rarity_id) {
                    $active_filters[] = "Rarità: " . htmlspecialchars($rarity["rarity_name"]);
                    break;
                }
            }
        }
        if ($min_price > 0) $active_filters[] = "Prezzo min: " . number_format($min_price, 2, ',', '.') . " €";
        if ($max_price > 0) $active_filters[] = "Prezzo max: " . number_format($max_price, 2, ',', '.') . " €";
        
        if (!empty($active_filters)) {
            echo '<div class="active-filters">';
            echo '<span>Filtri attivi:</span>';
            foreach ($active_filters as $filter) {
                echo '<span class="filter-badge">' . $filter . '</span>';
            }
            echo '</div>';
        }
        ?>
        
        <div class="sort-mobile">
            <label for="sort-mobile">Ordina per:</label>
            <select id="sort-mobile" onchange="document.getElementById('sort').value=this.value; document.getElementById('filter-form').submit();">
                <option value="latest" <?php echo ($sort == 'latest') ? 'selected' : ''; ?>>Più recenti</option>
                <option value="price_asc" <?php echo ($sort == 'price_asc') ? 'selected' : ''; ?>>Prezzo crescente</option>
                <option value="price_desc" <?php echo ($sort == 'price_desc') ? 'selected' : ''; ?>>Prezzo decrescente</option>
                <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Nome A-Z</option>
                <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Nome Z-A</option>
            </select>
        </div>
        
        <div class="cards-grid">
            <?php
            if ($result && $result->num_rows > 0) {
                var_dump($result); 
                while($card = $result->fetch_assoc()) {
                    var_dump($card);
                    ?>
                    <div class="card-item">
                        <a href="listing.php?id=<?php echo $card["id"]; ?>">
                            <div class="card-image">
                                <?php if ($card["image_url"]): ?>
                                    <img src="<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"]); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">Immagine non disponibile</div>
                                <?php endif; ?>
                            </div>
                            <div class="card-details">
                                <h3><?php echo htmlspecialchars($card["name_en"]); ?></h3>
                                <p class="expansion"><?php echo htmlspecialchars($card["expansion_name"]); ?> (<?php echo htmlspecialchars($card["game_name"]); ?>)</p>
                                <p class="condition">Condizione: <?php echo htmlspecialchars($card["condition_name"]); ?></p>
                                <p class="seller">Venditore: <?php echo htmlspecialchars($card["seller_name"]); ?> 
                                    <span class="rating"><?php $rating = min(5, max(0, round($card["seller_rating"]))); echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?></span>
                                </p>
                                <div class="price-container">
                                    <span class="price"><?php echo number_format($card["price"], 2, ',', '.'); ?> €</span>
                                    <span class="quantity">Disp: <?php echo $card["quantity"]; ?></span>
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
            }
            ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>