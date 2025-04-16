<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config/config.php';
$base_url = "/DataBase";

// Check if card ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: $base_url/public/marketplace.php");
    exit;
}

$card_id = (int)$_GET['id'];

// Fetch card details
$sql_card = "SELECT sc.blueprint_id, sc.name_en, sc.image_url, sc.collector_number, sc.rarity_id, 
             sc.expansion_id,
             e.id as expansion_id, e.name as expansion_name,
             g.id as game_id, g.display_name as game_name
             FROM single_cards sc
             JOIN expansions e ON sc.expansion_id = e.id
             JOIN games g ON e.game_id = g.id
             WHERE sc.blueprint_id = ?";

$stmt = $conn->prepare($sql_card);
$stmt->bind_param("i", $card_id);
$stmt->execute();
$result_card = $stmt->get_result();

if ($result_card->num_rows === 0) {
    header("Location: $base_url/public/marketplace.php");
    exit;
}

$card = $result_card->fetch_assoc();

// Fetch all listings for this card
$sql_listings = "SELECT l.id as listing_id, l.price, l.quantity, l.description, l.created_at,
                cc.id as condition_id, cc.condition_name,
                a.id as seller_id, a.username as seller_name,
                up.rating as seller_rating
                FROM listings l
                JOIN card_conditions cc ON l.condition_id = cc.id
                JOIN accounts a ON l.seller_id = a.id
                JOIN user_profiles up ON a.id = up.user_id
                WHERE l.single_card_id = ? AND l.is_active = TRUE
                ORDER BY l.price ASC";

$stmt = $conn->prepare($sql_listings);
$stmt->bind_param("i", $card_id);
$stmt->execute();
$result_listings = $stmt->get_result();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;

// Check if the user has this card in their wishlist
$in_wishlist = false;
if ($is_logged_in) {
    $sql_wishlist = "SELECT COUNT(*) as count FROM wishlist_items wi
                 JOIN wishlists w ON wi.wishlist_id = w.id
                 WHERE w.user_id = ? AND wi.single_card_id = ?";
    $stmt = $conn->prepare($sql_wishlist);
    $stmt->bind_param("ii", $user_id, $card_id);
    $stmt->execute();
    $wishlist_result = $stmt->get_result();
    $in_wishlist = ($wishlist_result->fetch_assoc()['count'] > 0);
}

// Handle adding card to wishlist
$wishlist_message = '';
if ($is_logged_in && isset($_POST['add_to_wishlist'])) {
    // First check if user already has a wishlist
    $sql_get_wishlist = "SELECT id FROM wishlists WHERE user_id = ?";
    $stmt = $conn->prepare($sql_get_wishlist);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wishlist_result = $stmt->get_result();
    
    if ($wishlist_result->num_rows === 0) {
        // Create a new wishlist for the user
        $sql_create_wishlist = "INSERT INTO wishlists (user_id, created_at) VALUES (?, NOW())";
        $stmt = $conn->prepare($sql_create_wishlist);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wishlist_id = $conn->insert_id;
    } else {
        $wishlist_id = $wishlist_result->fetch_assoc()['id'];
    }
    
    // Add card to wishlist if not already there
    if (!$in_wishlist) {
        $sql_add_to_wishlist = "INSERT INTO wishlist_items (wishlist_id, single_card_id, desired_condition_id) 
                        VALUES (?, ?, NULL)";
        $stmt = $conn->prepare($sql_add_to_wishlist);
        $stmt->bind_param("ii", $wishlist_id, $card_id);
        
        if ($stmt->execute()) {
            $wishlist_message = "La carta è stata aggiunta alla tua wishlist.";
            $in_wishlist = true;
        } else {
            $wishlist_message = "Errore durante l'aggiunta alla wishlist.";
        }
    }
}

// Handle removing card from wishlist
if ($is_logged_in && isset($_POST['remove_from_wishlist'])) {
    $sql_remove = "DELETE wi FROM wishlist_items wi
              JOIN wishlists w ON wi.wishlist_id = w.id
              WHERE w.user_id = ? AND wi.single_card_id = ?";
    $stmt = $conn->prepare($sql_remove);
    $stmt->bind_param("ii", $user_id, $card_id);
    
    if ($stmt->execute()) {
        $wishlist_message = "La carta è stata rimossa dalla tua wishlist.";
        $in_wishlist = false;
    } else {
        $wishlist_message = "Errore durante la rimozione dalla wishlist.";
    }
}

// Handle adding/removing user's own listings
$listing_message = '';

// Handle adding a new listing
if ($is_logged_in && isset($_POST['add_listing'])) {
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $condition_id = $_POST['condition'];
    $description = $_POST['description'];
    
    // Validate inputs
    if ($price <= 0 || $quantity <= 0 || !is_numeric($condition_id)) {
        $listing_message = "Errore: Verifica i dati inseriti.";
    } else {
        $sql_add_listing = "INSERT INTO listings (seller_id, single_card_id, price, quantity, condition_id, description, is_active, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())";
        $stmt->bind_param("iisiis", $user_id, $card_id, $price, $quantity, $condition_id, $description);      
        if ($stmt->execute()) {
            $listing_message = "Annuncio creato con successo.";
            // Refresh the listings
            $stmt = $conn->prepare($sql_listings);
            $stmt->bind_param("i", $card_id);
            $stmt->execute();
            $result_listings = $stmt->get_result();
        } else {
            $listing_message = "Errore durante la creazione dell'annuncio: " . $conn->error;
        }
    }
}

// Handle removing a listing
if ($is_logged_in && isset($_POST['remove_listing'])) {
    $listing_id = $_POST['listing_id'];
    
    // Verify the listing belongs to the user
    $sql_check = "SELECT id FROM listings WHERE id = ? AND seller_id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $sql_remove = "UPDATE listings SET is_active = FALSE WHERE id = ?";
        $stmt = $conn->prepare($sql_remove);
        $stmt->bind_param("i", $listing_id);
        
        if ($stmt->execute()) {
            $listing_message = "Annuncio rimosso con successo.";
            // Refresh the listings
            $stmt = $conn->prepare($sql_listings);
            $stmt->bind_param("i", $card_id);
            $stmt->execute();
            $result_listings = $stmt->get_result();
        } else {
            $listing_message = "Errore durante la rimozione dell'annuncio.";
        }
    } else {
        $listing_message = "Non hai i permessi per rimuovere questo annuncio.";
    }
}

// Get card conditions for the add listing form
$sql_conditions = "SELECT id, condition_name FROM card_conditions ORDER BY id";
$result_conditions = $conn->query($sql_conditions);
$conditions = [];
while ($condition = $result_conditions->fetch_assoc()) {
    $conditions[] = $condition;
}

// Include header
include __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>/css/cards.css">

<div class="card-details-container">
    <div class="card-details">
        <div class="card-image-container">
            <?php if ($card["image_url"]): ?>
                <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"]); ?>">
            <?php else: ?>
                <div class="no-image">Immagine non disponibile</div>
            <?php endif; ?>
            
            <?php if ($is_logged_in): ?>
                <div class="card-actions">
                    <form method="POST" action="">
                        <?php if ($in_wishlist): ?>
                            <button type="submit" name="remove_from_wishlist" class="btn-wishlist active">
                                <i class="fas fa-heart"></i> Rimuovi dalla wishlist
                            </button>
                        <?php else: ?>
                            <button type="submit" name="add_to_wishlist" class="btn-wishlist">
                                <i class="far fa-heart"></i> Aggiungi alla wishlist
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card-info">
            <h1><?php echo htmlspecialchars($card["name_en"]); ?></h1>
            <?php if (!empty($card["name_en"])): ?>
                <h2><?php echo htmlspecialchars($card["name_en"]); ?></h2>
            <?php endif; ?>
            
            <div class="card-meta">
                <div class="meta-row">
                    <span class="meta-label">Gioco:</span>
                    <span class="meta-value">
                        <a href="game.php?id=<?php echo $card["game_id"]; ?>"><?php echo htmlspecialchars($card["game_name"]); ?></a>
                    </span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">Espansione:</span>
                    <span class="meta-value">
                        <a href="expansion.php?id=<?php echo $card["expansion_id"]; ?>"><?php echo htmlspecialchars($card["expansion_name"]); ?></a>
                        <?php if (!empty($card["release_date"])): ?>
                            (<?php echo date('Y', strtotime($card["release_date"])); ?>)
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">Numero collezione:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["collector_number"]); ?></span>
                </div>
                
                <?php if (!empty($card["rarity"])): ?>
                <div class="meta-row">
                    <span class="meta-label">Rarità:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["rarity"]); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($card["card_type"])): ?>
                <div class="meta-row">
                    <span class="meta-label">Tipo:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["card_type"]); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($card["artist"])): ?>
                <div class="meta-row">
                    <span class="meta-label">Artista:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["artist"]); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($card["text_en"])): ?>
            <div class="card-text">
                <h3>Testo (EN)</h3>
                <p><?php echo nl2br(htmlspecialchars($card["text_en"])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($card["text_it"])): ?>
            <div class="card-text">
                <h3>Testo (IT)</h3>
                <p><?php echo nl2br(htmlspecialchars($card["text_it"])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($card["flavor_text"])): ?>
            <div class="flavor-text">
                <h3>Testo di colore</h3>
                <p><em><?php echo nl2br(htmlspecialchars($card["flavor_text"])); ?></em></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($wishlist_message)): ?>
    <div class="alert alert-info">
        <?php echo htmlspecialchars($wishlist_message); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($listing_message)): ?>
    <div class="alert alert-info">
        <?php echo htmlspecialchars($listing_message); ?>
    </div>
    <?php endif; ?>
    
    <div class="listings-section">
        <h2>Annunci disponibili</h2>
        
        <?php if ($result_listings->num_rows > 0): ?>
            <div class="listings-table">
                <table>
                    <thead>
                        <tr>
                            <th>Venditore</th>
                            <th>Condizione</th>
                            <th>Prezzo</th>
                            <th>Disponibilità</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($listing = $result_listings->fetch_assoc()): ?>
                            <tr>
                                <td class="seller-info">
                                    <a href="seller.php?id=<?php echo $listing['seller_id']; ?>">
                                        <?php echo htmlspecialchars($listing['seller_name']); ?>
                                    </a>
                                    <div class="seller-rating">
                                        <?php echo str_repeat('★', round($listing['seller_rating'])) . str_repeat('☆', 5 - round($listing['seller_rating'])); ?>
                                        <span><?php echo number_format($listing['seller_rating'], 1); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($listing['condition_name']); ?></td>
                                <td class="price"><?php echo number_format($listing['price'], 2, ',', '.'); ?> €</td>
                                <td><?php echo $listing['quantity']; ?></td>
                                <td class="actions">
                                    <?php if ($listing['seller_id'] == $user_id): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="listing_id" value="<?php echo $listing['listing_id']; ?>">
                                            <button type="submit" name="remove_listing" class="btn-remove" 
                                                   onclick="return confirm('Sei sicuro di voler rimuovere questo annuncio?');">
                                                <i class="fas fa-trash"></i> Rimuovi
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="add_to_cart.php?listing_id=<?php echo $listing['listing_id']; ?>" class="btn-add-cart">
                                            <i class="fas fa-cart-plus"></i> Aggiungi al carrello
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($listing['description'])): ?>
                                <tr class="description-row">
                                    <td colspan="6">
                                        <div class="listing-description">
                                            <strong>Descrizione:</strong> <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-listings">Non ci sono annunci disponibili per questa carta.</p>
        <?php endif; ?>
        
        <?php if ($is_logged_in): ?>
            <div class="add-listing-section">
                <h3>Vendi questa carta</h3>
                <form method="POST" action="" class="add-listing-form">
                    <div class="form-group">
                        <label for="price">Prezzo (€):</label>
                        <input type="number" id="price" name="price" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantità:</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="condition">Condizione:</label>
                        <select id="condition" name="condition" required>
                            <option value="">Seleziona una condizione</option>
                            <?php foreach ($conditions as $condition): ?>
                                <option value="<?php echo $condition['id']; ?>"><?php echo htmlspecialchars($condition['condition_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                        
                    <div class="form-group">
                        <label for="description">Descrizione (opzionale):</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_listing" class="btn-primary">Crea annuncio</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/partials/footer.php';

// Close database connection
$conn->close();
?>