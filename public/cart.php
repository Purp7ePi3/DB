<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
$base_url = "/DataBase";

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store current URL to redirect back after login
    $_SESSION['redirect_url'] = 'cart.php';
    header("Location: $base_url/auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $cart_item_id => $quantity) {
        if ($quantity <= 0) {
            // Remove item if quantity is zero or negative
            $sql_delete = "DELETE FROM cart_items WHERE id = ? AND cart_id IN (SELECT id FROM carts WHERE user_id = ?)";
            $stmt = $conn->prepare($sql_delete);
            $stmt->bind_param("ii", $cart_item_id, $user_id);
            $stmt->execute();
        } else {
            // Update quantity
            $sql_update = "UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id IN (SELECT id FROM carts WHERE user_id = ?)";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("iii", $quantity, $cart_item_id, $user_id);
            $stmt->execute();
        }
    }
    $success_message = 'Carrello aggiornato con successo';
}

// Handle remove item
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $cart_item_id = (int)$_GET['remove'];
    $sql_delete = "DELETE FROM cart_items WHERE id = ? AND cart_id IN (SELECT id FROM carts WHERE user_id = ?)";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("ii", $cart_item_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = 'Articolo rimosso dal carrello';
    } else {
        $error_message = 'Errore nella rimozione dell\'articolo: ' . $conn->error;
    }
}

// Get cart items - QUERY CORRETTA con single_card_id
$sql = "SELECT ci.id as cart_item_id, ci.quantity, l.id as listing_id, l.price, l.quantity as available_quantity,
        l.single_card_id, sc.name_en, sc.image_url, e.name as expansion_name, g.display_name as game_name,
        cc.condition_name, u.username as seller_name, COALESCE(up.rating, 0) as seller_rating
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        JOIN listings l ON ci.listing_id = l.id
        JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        JOIN expansions e ON sc.expansion_id = e.id
        JOIN games g ON e.game_id = g.id
        JOIN card_conditions cc ON l.condition_id = cc.id
        JOIN accounts u ON l.seller_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE c.user_id = ? AND l.is_active = TRUE
        ORDER BY c.updated_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate totals and check for unavailable items
$total = 0;
$items_count = 0;
$unavailable_items = [];
$cart_items = [];

if ($result->num_rows > 0) {
    while ($item = $result->fetch_assoc()) {
        // Check if requested quantity is still available
        if ($item['quantity'] > $item['available_quantity']) {
            $unavailable_items[] = $item['name_en'];
            $item['quantity'] = $item['available_quantity']; // Adjust quantity to what's available
            
            // Update cart quantity in database
            $sql_update = "UPDATE cart_items SET quantity = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ii", $item['available_quantity'], $item['cart_item_id']);
            $stmt_update->execute();
        }
        
        // Add to cart items array
        $cart_items[] = $item;
        
        // Add to totals
        $item_total = $item['price'] * $item['quantity'];
        $total += $item_total;
        $items_count += $item['quantity'];
    }
}

// Include header
include_once $root_path . $base_url . '/public/partials/header.php';

?>
<link rel="stylesheet" href="<?php echo $base_url; ?>/css/cart.css">

<div class="cart-container">
    <h1>Il tuo carrello</h1>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($unavailable_items)): ?>
        <div class="alert alert-warning">
            Alcuni articoli non sono più disponibili nella quantità richiesta. Il carrello è stato aggiornato.
        </div>
    <?php endif; ?>
    
    <?php if (count($cart_items) > 0): ?>
        <form action="cart.php" method="POST">
            <div class="cart-content">
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <!-- LINK CORRETTO con single_card_id -->
                                <a href="cards.php?id=<?php echo $item['single_card_id']; ?>">
                                    <?php if ($item['image_url']): ?>
                                        <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name_en']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">Immagine non disponibile</div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <div class="item-details">
                                <!-- LINK CORRETTO con single_card_id -->
                                <h3><a href="cards.php?id=<?php echo $item['single_card_id']; ?>"><?php echo htmlspecialchars($item['name_en']); ?></a></h3>
                                <p class="item-meta">
                                    <?php echo htmlspecialchars($item['expansion_name']); ?> (<?php echo htmlspecialchars($item['game_name']); ?>)<br>
                                    Condizione: <?php echo htmlspecialchars($item['condition_name']); ?><br>
                                    Venditore: <?php echo htmlspecialchars($item['seller_name']); ?>
                                    <span class="rating">
                                        <?php 
                                        $rating = (float)$item['seller_rating'];
                                        echo str_repeat('★', round($rating)) . str_repeat('☆', 5 - round($rating)); 
                                        ?>
                                    </span>
                                </p>
                            </div>
                            
                            <div class="item-quantity">
                                <label for="quantity-<?php echo $item['cart_item_id']; ?>">Quantità:</label>
                                <input type="number" id="quantity-<?php echo $item['cart_item_id']; ?>" 
                                       name="quantity[<?php echo $item['cart_item_id']; ?>]" 
                                       value="<?php echo $item['quantity']; ?>" 
                                       min="1" max="<?php echo $item['available_quantity']; ?>">
                                <div class="available">
                                    Disponibili: <?php echo $item['available_quantity']; ?>
                                </div>
                            </div>
                            
                            <div class="item-price">
                                <div class="price"><?php echo number_format($item['price'], 2, ',', '.'); ?> €</div>
                                <div class="total">Totale: <?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?> €</div>
                            </div>
                            
                            <div class="item-actions">
                                <a href="cart.php?remove=<?php echo $item['cart_item_id']; ?>" class="btn-remove" 
                                   onclick="return confirm('Sei sicuro di voler rimuovere questo articolo dal carrello?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <h2>Riepilogo ordine</h2>
                    <div class="summary-item">
                        <span>Articoli (<?php echo $items_count; ?>):</span>
                        <span><?php echo number_format($total, 2, ',', '.'); ?> €</span>
                    </div>
                    <div class="summary-item">
                        <span>Spedizione:</span>
                        <span>Calcolata al checkout</span>
                    </div>
                    <div class="summary-total">
                        <span>Totale:</span>
                        <span><?php echo number_format($total, 2, ',', '.'); ?> €</span>
                    </div>
                    
                    <div class="cart-actions">
                        <button type="submit" name="update_cart" class="btn-secondary">Aggiorna carrello</button>
                        <a href="checkout.php" class="btn-primary">Procedi al checkout</a>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="empty-cart">
            <p>Il tuo carrello è vuoto</p>
            <a href="marketplace.php" class="btn-primary">Continua lo shopping</a>
        </div>
    <?php endif; ?>
</div>

<style>
.cart-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}

.cart-container h1 {
    margin-bottom: 30px;
    color: #333;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-warning {
    color: #856404;
    background-color: #fff3cd;
    border-color: #ffeaa7;
}

.cart-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 30px;
}

.cart-items {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr 150px 120px 40px;
    gap: 15px;
    align-items: center;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.item-image {
    width: 100px;
    height: 140px;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.no-image {
    width: 100%;
    height: 100%;
    background-color: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #999;
    font-size: 12px;
    text-align: center;
}

.item-details h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
}

.item-details h3 a {
    color: #333;
    text-decoration: none;
}

.item-details h3 a:hover {
    color: #007bff;
}

.item-meta {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
}

.rating {
    color: #f8bb00;
    margin-left: 5px;
}

.item-quantity label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 14px;
}

.item-quantity input {
    width: 60px;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.available {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.item-price {
    text-align: right;
}

.price {
    font-size: 18px;
    font-weight: 600;
    color: #28a745;
    margin-bottom: 5px;
}

.total {
    font-size: 14px;
    color: #666;
}

.btn-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background-color: #dc3545;
    color: white;
    border-radius: 4px;
    text-decoration: none;
    transition: background-color 0.2s;
}

.btn-remove:hover {
    background-color: #c82333;
}

.cart-summary {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    height: fit-content;
}

.cart-summary h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 20px;
    color: #333;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 16px;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    margin-top: 15px;
    font-size: 18px;
    font-weight: 600;
}

.cart-actions {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-primary, .btn-secondary {
    padding: 12px 20px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0069d9;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.empty-cart p {
    font-size: 18px;
    color: #666;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .cart-content {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr;
        grid-template-rows: auto auto auto auto;
        gap: 10px;
    }
    
    .item-image {
        grid-row: 1 / 3;
        width: 80px;
        height: 110px;
    }
    
    .item-details {
        grid-column: 2;
    }
    
    .item-quantity, .item-price, .item-actions {
        grid-column: 1 / -1;
        text-align: left;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aggiorna il totale quando cambia la quantità
    const quantityInputs = document.querySelectorAll('.item-quantity input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Verifica che la quantità non superi il massimo disponibile
            const max = parseInt(this.getAttribute('max'));
            if (parseInt(this.value) > max) {
                this.value = max;
                alert('La quantità richiesta non è disponibile');
            }
            // Impedisce quantità negative
            if (parseInt(this.value) < 1) {
                this.value = 1;
            }
        });
    });
});
</script>

<?php
// Include footer
include '../public/partials/footer.php';

// Close database connection
$conn->close();
?>