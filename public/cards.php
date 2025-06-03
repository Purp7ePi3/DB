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

// Check if card ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: $base_url/public/marketplace.php");
    exit;
}

$card_id = (int)$_GET['id'];

// Fetch card details with rarity name
$sql_card = "SELECT sc.blueprint_id, sc.name_en, sc.image_url, sc.collector_number, 
             r.id as rarity_id, r.rarity_name, r.description as rarity_description,
             sc.expansion_id,
             e.id as expansion_id, e.name as expansion_name,
             g.id as game_id, g.display_name as game_name
             FROM single_cards sc
             JOIN expansions e ON sc.expansion_id = e.id
             JOIN games g ON e.game_id = g.id
             LEFT JOIN card_rarities r ON sc.rarity_id = r.id
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
                up.rating as seller_rating,
                sc.name_en as card_name

                FROM listings l
                JOIN card_conditions cc ON l.condition_id = cc.id
                JOIN accounts a ON l.seller_id = a.id
                LEFT JOIN user_profiles up ON a.id = up.user_id
                JOIN single_cards sc ON l.single_card_id = sc.blueprint_id

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
        $sql_create_wishlist = "INSERT INTO wishlists (user_id, name, created_at) VALUES (?, 'My Wishlist', NOW())";
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
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $condition_id = (int)$_POST['condition'];
    $description = trim($_POST['description']);
    
    // Validate inputs
    if ($price <= 0 || $quantity <= 0 || $condition_id <= 0) {
        $listing_message = "Errore: Verifica i dati inseriti.";
    } else {
        $sql_add_listing = "INSERT INTO listings (seller_id, single_card_id, condition_id, price, quantity, description, is_active, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())";
        $stmt = $conn->prepare($sql_add_listing);
        $stmt->bind_param("iiidis", $user_id, $card_id, $condition_id, $price, $quantity, $description);
        
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
    $listing_id = (int)$_POST['listing_id'];
    
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
                    </span>
                </div>
                
                <?php if (!empty($card["collector_number"])): ?>
                <div class="meta-row">
                    <span class="meta-label">Numero collezione:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["collector_number"]); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($card["rarity_name"])): ?>
                <div class="meta-row">
                    <span class="meta-label">Rarità:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($card["rarity_name"]); ?></span>
                </div>
                <?php endif; ?>
            </div>
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
                            <th>Nome Carta</th>
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
                                <td><?php echo htmlspecialchars($listing['card_name']); ?></td>
                                <td><?php echo htmlspecialchars($listing['condition_name']); ?></td>
                                <td class="price"><?php echo number_format($listing['price'], 2, ',', '.'); ?> €</td>
                                <td><?php echo $listing['quantity']; ?></td>
                                <td class="action">
                                    <?php if ($listing['seller_id'] == $user_id): ?>
    <form method="POST" action="">
        <input type="hidden" name="listing_id" value="<?php echo $listing['listing_id']; ?>">
        <button type="submit" name="remove_listing" class="btn-remove" 
            onclick="return confirm('Sei sicuro di voler rimuovere questo annuncio?');">
            <i class="fas fa-trash"></i> Rimuovi
        </button>
    </form>
    <?php
echo "<!-- DEBUG: Listing ID = " . $listing['listing_id'] . " -->";
?>
<?php else: ?>
    <!-- Form per aggiungere al carrello -->
    <form method="POST" action="<?php echo $base_url; ?>/public/add_to_cart.php" style="display: inline;">
        <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($listing['listing_id']); ?>">
        <button type="submit" class="btn-add-cart" onclick="console.log('Form submitted with listing_id:', <?php echo $listing['listing_id']; ?>);">
            <i class="fas fa-cart-plus"></i> Aggiungi al carrello
        </button>
    </form>
<?php endif; ?>

<?php
// ALTERNATIVE: Se il form sopra non funziona, prova questo con AJAX:
?>

<!-- METODO ALTERNATIVO CON AJAX -->
<script>
function addToCart(listingId) {
    console.log('Adding to cart, listing ID:', listingId);
    
    // Crea un form nascosto e invialo
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo $base_url; ?>/public/add_to_cart.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'listing_id';
    input.value = listingId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>

<!-- E poi usa questo bottone invece del form: -->
<button type="button" class="btn-add-cart" onclick="addToCart(<?php echo $listing['listing_id']; ?>)">
    <i class="fas fa-cart-plus"></i> Aggiungi al carrello
</button>
                                </td>
                            </tr>
                            <?php if (!empty($listing['description'])): ?>
                                <tr class="description-row">
                                    <td colspan="5">
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
                
                <?php
                // Process form submission
                if (isset($_POST['add_listing'])) {
                    // Validate and sanitize input
                    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
                    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
                    $condition = filter_var($_POST['condition'], FILTER_VALIDATE_INT);
                    $description = trim($_POST['description'] ?? '');
                    
                    // Basic validation
                    if ($price === false || $price <= 0) {
                        $error_msg = "Prezzo non valido.";
                    } elseif ($quantity === false || $quantity <= 0) {
                        $error_msg = "Quantità non valida.";
                    } elseif ($condition === false || $condition <= 0) {
                        $error_msg = "Condizione non valida.";
                    } else {
                        try {
                            $conn->autocommit(false);
                            
                            $sql_insert = "INSERT INTO listing (seller_id, single_card_id, condition_id, price, quantity, description, is_active, created_at, updated_at)
                                          VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW(), NOW())";
                            
                            $stmt = $conn->prepare($sql_insert);
                            
                            $stmt->bind_param("iiidis", 
                                $_SESSION['user_id'], 
                                $card_id,
                                $condition, 
                                $price, 
                                $quantity, 
                                $description
                            );
                            
                            if ($stmt->execute()) {
                                $conn->commit();
                                $success_msg = "Annuncio creato con successo!";
                                header("Location: " . $_SERVER['PHP_SELF'] . "?card_id=" . $card_id);
                                exit();
                            } else {
                                throw new Exception("Errore nell'esecuzione della query");
                            }
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error_msg = "Errore nell'aggiunta dell'annuncio: " . $e->getMessage();
                        } finally {
                            $conn->autocommit(true);
                        }
                    }
                }
                ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>
                
                <?php if (isset($success_msg)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>
                
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
<style>
    /* Card Details Page Styles */
.card-details-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 15px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.card-details {
    display: flex;
    flex-wrap: wrap;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

/* Card Image */
.card-image-container {
    flex: 0 0 300px;
    padding: 20px;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.card-image-container img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
}

.no-image {
    width: 100%;
    height: 400px;
    background-color: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #999;
    font-style: italic;
}

.card-actions {
    margin-top: 20px;
    width: 100%;
}

.btn-wishlist {
    width: 100%;
    padding: 10px;
    background-color: #f8f9fa;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.btn-wishlist i {
    margin-right: 5px;
}

.btn-wishlist:hover {
    background-color: #e9ecef;
}

.btn-wishlist.active {
    background-color: #dc3545;
    color: white;
    border-color: #dc3545;
}

.btn-wishlist.active:hover {
    background-color: #c82333;
}

/* Card Information */
.card-info {
    flex: 1;
    padding: 25px;
    min-width: 300px;
}

.card-info h1 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 24px;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.card-info h2 {
    font-size: 18px;
    color: #555;
    margin-bottom: 20px;
}

.card-meta {
    margin-bottom: 20px;
}

.meta-row {
    display: flex;
    margin-bottom: 10px;
    line-height: 1.5;
}

.meta-label {
    flex: 0 0 150px;
    font-weight: bold;
    color: #666;
}

.meta-value {
    flex: 1;
}

.meta-value a {
    color: #0275d8;
    text-decoration: none;
}

.meta-value a:hover {
    text-decoration: underline;
}

.card-text, .flavor-text {
    margin-top: 20px;
}

.card-text h3, .flavor-text h3 {
    font-size: 16px;
    color: #555;
    margin-bottom: 10px;
}

.card-text p, .flavor-text p {
    line-height: 1.6;
}

.flavor-text p {
    font-style: italic;
    color: #777;
}

/* Alerts */
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

/* Listings Section */
.listings-section {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 25px;
    margin-bottom: 30px;
}

.listings-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 20px;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.listings-table {
    overflow-x: auto;
}

.listings-table table {
    width: 100%;
    border-collapse: collapse;
}

.listings-table th, .listings-table td {
    padding: 10px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.listings-table th {
    background-color: #f8f9fa;
    color: #555;
    font-weight: 600;
}

.seller-info {
    display: flex;
    flex-direction: column;
}

.seller-info a {
    color: #0275d8;
    text-decoration: none;
    font-weight: 600;
}

.seller-rating {
    margin-top: 5px;
    color: #f8bb00;
    font-size: 14px;
}

.seller-rating span {
    color: #555;
    margin-left: 5px;
}

.price {
    font-weight: 600;
    color: #28a745;
}

.actions {
    display: flex;
    justify-content: flex-start;
}

.btn-add-cart, .btn-remove {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.btn-add-cart {
    background-color: #007bff;
    color: white;
    border: none;
}

.btn-add-cart:hover {
    background-color: #0069d9;
}

.btn-remove {
    background-color: #dc3545;
    color: white;
    border: none;
}

.btn-remove:hover {
    background-color: #c82333;
}

.btn-add-cart i, .btn-remove i {
    margin-right: 5px;
}

.description-row {
    background-color: #fafafa;
}

.listing-description {
    padding: 10px 15px;
    font-size: 14px;
    color: #666;
}

.no-listings {
    text-align: center;
    padding: 20px;
    color: #777;
    font-style: italic;
}

/* Add Listing Form */
.add-listing-section {
    margin-top: 30px;
    border-top: 1px solid #eee;
    padding-top: 20px;
}

.add-listing-section h3 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
    color: #333;
}

.add-listing-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #555;
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-actions {
    grid-column: 1 / -1;
    margin-top: 10px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.btn-primary:hover {
    background-color: #0069d9;
}

/* Responsive Design */
@media (max-width: 768px) {
    .card-image-container {
        flex: 0 0 100%;
    }
    
    .card-info {
        padding-top: 0;
    }
    
    .meta-row {
        flex-direction: column;
    }
    
    .meta-label {
        margin-bottom: 5px;
    }
    
    .add-listing-form {
        grid-template-columns: 1fr;
    }
}
</style>
<?php
// Include footer
include __DIR__ . '/partials/footer.php';

// Close database connection
$conn->close();

?>