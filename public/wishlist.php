<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Include database configuration
require_once '../config/config.php';
$base_url = "/DataBase";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: $base_url/public/login.php?redirect=wishlist.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$wishlist_message = '';

// Handle removing items from wishlist
if (isset($_POST['remove_item']) && isset($_POST['item_id']) && is_numeric($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];
    
    // Verify the item belongs to the user
    $sql_check = "SELECT wi.id FROM wishlist_items wi
                  JOIN wishlists w ON wi.wishlist_id = w.id
                  WHERE wi.id = ? AND w.user_id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $sql_remove = "DELETE FROM wishlist_items WHERE id = ?";
        $stmt = $conn->prepare($sql_remove);
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            $wishlist_message = "Elemento rimosso dalla wishlist con successo.";
        } else {
            $wishlist_message = "Errore durante la rimozione dell'elemento.";
        }
    } else {
        $wishlist_message = "Non hai i permessi per rimuovere questo elemento.";
    }
}

// Get user's wishlist
$sql_wishlist = "SELECT w.id as wishlist_id, w.name as wishlist_name
                FROM wishlists w
                WHERE w.user_id = ?";
$stmt = $conn->prepare($sql_wishlist);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_wishlist = $stmt->get_result();

// Check if user has a wishlist
if ($result_wishlist->num_rows === 0) {
    // Create a default wishlist for the user - QUERY CORRETTA senza created_at
    $default_name = "La mia Wishlist";
    $sql_create = "INSERT INTO wishlists (user_id, name) VALUES (?, ?)";
    $stmt = $conn->prepare($sql_create);
    $stmt->bind_param("is", $user_id, $default_name);
    
    if ($stmt->execute()) {
        $wishlist_id = $conn->insert_id;
        $wishlist_name = $default_name;
    } else {
        // Handle error
        $wishlist_id = 0;
        $wishlist_name = "";
    }
} else {
    $wishlist = $result_wishlist->fetch_assoc();
    $wishlist_id = $wishlist['wishlist_id'];
    $wishlist_name = $wishlist['wishlist_name'];
}

// Get wishlist items with detailed information
$sql_items = "SELECT 
                wi.id as item_id,
                sc.blueprint_id as card_id,
                sc.name_en as card_name,
                sc.image_url,
                e.name as expansion_name,
                g.display_name as game_name,
                r.rarity_name,
                cc.condition_name as desired_condition,
                wi.max_price
              FROM wishlist_items wi
              JOIN single_cards sc ON wi.single_card_id = sc.blueprint_id
              JOIN expansions e ON sc.expansion_id = e.id
              JOIN games g ON e.game_id = g.id
              LEFT JOIN card_rarities r ON sc.rarity_id = r.id
              LEFT JOIN card_conditions cc ON wi.desired_condition_id = cc.id
              WHERE wi.wishlist_id = ?
              ORDER BY sc.name_en ASC";

$stmt = $conn->prepare($sql_items);
$stmt->bind_param("i", $wishlist_id);
$stmt->execute();
$result_items = $stmt->get_result();

// Get the lowest price for each card in wishlist from active listings
function getLowestPrice($conn, $card_id) {
    $sql = "SELECT MIN(price) as lowest_price FROM listings 
            WHERE single_card_id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['lowest_price'];
    }
    
    return null;
}

// Include header
include __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>/css/cards.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>/css/wishlist.css">

<div class="wishlist-container">
    <h1><?php echo htmlspecialchars($wishlist_name); ?></h1>
    
    <?php if (!empty($wishlist_message)): ?>
    <div class="alert alert-info">
        <?php echo htmlspecialchars($wishlist_message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($result_items->num_rows > 0): ?>
        <div class="wishlist-items">
            <table>
                <thead>
                    <tr>
                        <th>Carta</th>
                        <th>Espansione</th>
                        <th>Gioco</th>
                        <th>Rarità</th>
                        <th>Condizione desiderata</th>
                        <th>Prezzo massimo</th>
                        <th>Prezzo più basso disponibile</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $result_items->fetch_assoc()): 
                        $lowest_price = getLowestPrice($conn, $item['card_id']); 
                    ?>
                        <tr>
                            <td class="card-info">
                                <div class="card-image-mini">
                                    <?php if ($item["image_url"]): ?>
                                        <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($item["image_url"]); ?>" alt="<?php echo htmlspecialchars($item["card_name"]); ?>">
                                    <?php else: ?>
                                        <div class="no-image-mini">No image</div>
                                    <?php endif; ?>
                                </div>
                                <a href="cards.php?id=<?php echo $item['card_id']; ?>"><?php echo htmlspecialchars($item['card_name']); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($item['expansion_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['game_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['rarity_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['desired_condition'] ?? 'Qualsiasi'); ?></td>
                            <td>
                                <?php echo $item['max_price'] ? number_format($item['max_price'], 2, ',', '.') . ' €' : 'Nessun limite'; ?>
                            </td>
                            <td>
                                <?php if ($lowest_price): ?>
                                    <span class="price"><?php echo number_format($lowest_price, 2, ',', '.'); ?> €</span>
                                    <a href="cards.php?id=<?php echo $item['card_id']; ?>" class="btn-view">Vedi annunci</a>
                                <?php else: ?>
                                    <span>Nessun annuncio</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <form method="POST" action="">
                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                    <button type="submit" name="remove_item" class="btn-remove" 
                                            onclick="return confirm('Sei sicuro di voler rimuovere questa carta dalla wishlist?');">
                                        <i class="fas fa-trash"></i> Rimuovi
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-wishlist">
            <p>La tua wishlist è vuota. Aggiungi carte dalla pagina dei dettagli della carta.</p>
            <a href="marketplace.php" class="btn-primary">Esplora il marketplace</a>
        </div>
    <?php endif; ?>
    <style>
        .wishlist-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.wishlist-container h1 {
    margin-bottom: 20px;
    color: #333;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-info {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.wishlist-items table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.wishlist-items th {
    background-color: #f5f5f5;
    padding: 12px;
    text-align: left;
    border-bottom: 2px solid #ddd;
    font-weight: bold;
}

.wishlist-items td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    vertical-align: middle;
}

.card-info {
    display: flex;
    align-items: center;
}

.card-image-mini {
    width: 50px;
    height: 70px;
    margin-right: 10px;
    overflow: hidden;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.card-image-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image-mini {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0;
    color: #999;
    font-size: 10px;
    text-align: center;
}

.price {
    font-weight: bold;
    color: #e53935;
}

.btn-view, .btn-remove {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9em;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-view {
    background-color: #4CAF50;
    color: white;
    border: none;
    margin-left: 10px;
}

.btn-view:hover {
    background-color: #388E3C;
}

.btn-remove {
    background-color: #f44336;
    color: white;
    border: none;
}

.btn-remove:hover {
    background-color: #d32f2f;
}

.btn-primary {
    display: inline-block;
    padding: 10px 20px;
    background-color: #2196F3;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: bold;
    transition: background-color 0.2s;
}

.btn-primary:hover {
    background-color: #1976D2;
}

.empty-wishlist {
    text-align: center;
    padding: 40px;
    border: 1px dashed #ddd;
    border-radius: 8px;
    background-color: #f9f9f9;
}

.empty-wishlist p {
    margin-bottom: 20px;
    color: #666;
    font-size: 1.1em;
}

.actions {
    text-align: center;
}
    </style>
</div>

<?php
// Include footer
include __DIR__ . '/partials/footer.php';
?>