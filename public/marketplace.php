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
$rarity_id = (int)($_GET['rarity_id'] ?? 0);
$search = $_GET['search'] ?? '';

// Build the WHERE clause for filtering
$where_clauses = [];

// Add filters
if ($game_id > 0) $where_clauses[] = "e.game_id = $game_id";
if ($expansion_id > 0) $where_clauses[] = "sc.expansion_id = $expansion_id";
if ($rarity_id > 0) $where_clauses[] = "sc.rarity_id = $rarity_id";
if ($listing_id > 0) $where_clauses[] = "sc."
if (!empty($search)) $where_clauses[] = "sc.name_en LIKE '%" . $conn->real_escape_string($search) . "%'";

switch ($sort) {
    case 'name_asc': $order_by = "sc.name_en ASC"; break;
    case 'name_desc': $order_by = "sc.name_en DESC"; break;
    default: $order_by = "sc.blueprint_id DESC"; break;
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Query to get all single cards with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 30; // Number of cards per page
$offset = ($page - 1) * $per_page;

// Query for the cards
$cards_sql = "SELECT 
            sc.blueprint_id as id, 
            sc.name_en, 
            sc.image_url, 
            sc.collector_number,
            e.name as expansion_name, 
            g.display_name as game_name,
            r.rarity_name
        FROM single_cards sc
        LEFT JOIN expansions e ON sc.expansion_id = e.id
        LEFT JOIN games g ON e.game_id = g.id
        LEFT JOIN card_rarities r ON sc.rarity_id = r.id
        $where_clause and sc.image_url is not null
        ORDER BY $order_by
        LIMIT $offset, $per_page";

$cards_result = $conn->query($cards_sql);
$error_message = null;

if (!$cards_result) {
    $error_message = "Errore nella query: {$conn->error}";
}

// Count total cards for pagination
$count_sql = "SELECT COUNT(*) as total FROM single_cards sc 
              LEFT JOIN expansions e ON sc.expansion_id = e.id
              LEFT JOIN games g ON e.game_id = g.id
              $where_clause";
$count_result = $conn->query($count_sql);
$total_cards = 0;
if ($count_result) {
    $count_data = $count_result->fetch_assoc();
    $total_cards = $count_data['total'];
}
$total_pages = ceil($total_cards / $per_page);

// Queries for filters
$sql_games = "SELECT id, display_name FROM games ORDER BY display_name";
$result_games = $conn->query($sql_games);
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
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Applica filtri</button>
                <a href="marketplace.php" class="btn">Reimposta</a>
            </div>
        </form>
    </div>
    
    <div class="marketplace-content">
        <h1>Le mie carte</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="cards-info">
            <p>Mostrando <?php echo $cards_result->num_rows; ?> carte su <?php echo $total_cards; ?> totali - Pagina <?php echo $page; ?> di <?php echo $total_pages; ?></p>
        </div>
        
        <div class="cards-grid">
            <?php
            // Explicitly check if we have results
            if ($cards_result && $cards_result->num_rows > 0) {
                // Loop through each row
                while ($card = $cards_result->fetch_assoc()) {
            ?>
                    <div class="card-item">
                        <a href="card.php?id=<?php echo $card["id"]; ?>">
                            <div class="card-image">
                                <?php if (isset($card["image_url"]) && !empty($card["image_url"])): ?>
                                    <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"] ?? 'Card'); ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">Immagine non disponibile</div>
                                <?php endif; ?>
                            </div>
                            <div class="card-details">
                                <h3><?php echo htmlspecialchars($card["name_en"] ?? 'Unknown Card'); ?></h3>
                                <p class="expansion"><?php echo htmlspecialchars($card["expansion_name"] ?? 'Unknown'); ?> (<?php echo htmlspecialchars($card["game_name"] ?? 'Unknown Game'); ?>)</p>
                                <p class="collector">N°: <?php echo htmlspecialchars($card["collector_number"] ?? 'N/A'); ?></p>
                                <p class="rarity">Rarità: <?php echo htmlspecialchars($card["rarity_name"] ?? 'Unknown'); ?></p>
                            </div>
                        </a>
                        <!-- <div class="card-actions">
                            <button class="btn-add" data-card-id="<?php echo $card["id"]; ?>">
                                <i class="fas fa-plus"></i> Aggiungi alla collezione
                            </button>
                        </div> -->
                    </div>
            <?php
                } // end while
            } else {
                // No results or query failed
                echo '<div class="no-results">Nessuna carta trovata con i filtri selezionati</div>';
            }
            ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_id > 0 ? '&game_id=' . $game_id : ''; ?><?php echo $expansion_id > 0 ? '&expansion_id=' . $expansion_id : ''; ?><?php echo $rarity_id > 0 ? '&rarity_id=' . $rarity_id : ''; ?>" class="page-link">&laquo; Precedente</a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_id > 0 ? '&game_id=' . $game_id : ''; ?><?php echo $expansion_id > 0 ? '&expansion_id=' . $expansion_id : ''; ?><?php echo $rarity_id > 0 ? '&rarity_id=' . $rarity_id : ''; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $game_id > 0 ? '&game_id=' . $game_id : ''; ?><?php echo $expansion_id > 0 ? '&expansion_id=' . $expansion_id : ''; ?><?php echo $rarity_id > 0 ? '&rarity_id=' . $rarity_id : ''; ?>" class="page-link">Successivo &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add basic inline JS for functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add to collection functionality
    const addButtons = document.querySelectorAll('.btn-add');
    addButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const cardId = this.getAttribute('data-card-id');
            // Here you would implement the logic to add the card to the user's collection
            alert('Carta aggiunta alla collezione!');
        });
    });
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>