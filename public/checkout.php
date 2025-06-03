<?php
// Crea questo file come: public/checkout.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = "/DataBase";

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php';
    header("Location: $base_url/public/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$order_id = null;
$payment_method = null;

// Get cart items
$sql = "SELECT ci.id as cart_item_id, ci.quantity, l.id as listing_id, l.price, l.quantity as available_quantity,
        sc.name_en, sc.image_url, e.name as expansion_name, g.display_name as game_name,
        cc.condition_name, u.username as seller_name, l.seller_id
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        JOIN listings l ON ci.listing_id = l.id
        JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        JOIN expansions e ON sc.expansion_id = e.id
        JOIN games g ON e.game_id = g.id
        JOIN card_conditions cc ON l.condition_id = cc.id
        JOIN accounts u ON l.seller_id = u.id
        WHERE c.user_id = ? AND l.is_active = TRUE
        ORDER BY c.updated_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Errore nella prepare() della SELECT carrello: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();


// Calculate totals
$total = 0;
$items_count = 0;
$cart_items = [];

if ($result->num_rows > 0) {
    while ($item = $result->fetch_assoc()) {
        // Check if requested quantity is still available
        if ($item['quantity'] > $item['available_quantity']) {
            $error_message = "Alcuni articoli non sono più disponibili nella quantità richiesta.";
            break;
        }
        
        $cart_items[] = $item;
        $item_total = $item['price'] * $item['quantity'];
        $total += $item_total;
        $items_count += $item['quantity'];
    }
}

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    if (empty($cart_items)) {
        $error_message = "Il carrello è vuoto.";
    } else {
        // Get form data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $postal_code = trim($_POST['postal_code']);
        $country = trim($_POST['country']);
        $phone = trim($_POST['phone']);
        $payment_method_id = isset($_POST['payment_method']) ? intval($_POST['payment_method']) : 0;
        
        // Validate required fields
        if (
            empty($first_name) || empty($last_name) || empty($address) ||
            empty($city) || empty($postal_code) || empty($payment_method_id)
        ) {
            $error_message = "Tutti i campi obbligatori devono essere compilati, incluso il metodo di pagamento.";
        } else {
            try {
                $conn->autocommit(false);
                
                // Create order
                $sql_order = "INSERT INTO orders (buyer_id, payment_id, total_price, status, order_date) 
                              VALUES (?, ?, ?, 'PENDING', NOW())";
                $stmt = $conn->prepare($sql_order);
                if (!$stmt) {
                    die("Errore nella preparazione della query: " . $conn->error);
                }
                $stmt->bind_param("iid", $user_id, $payment_method_id, $total);
                if (!$stmt->execute()) {
                    throw new Exception("Errore nella creazione dell'ordine");
                }
                $order_id = $conn->insert_id;
                
                // Create order items
                foreach ($cart_items as $item) {
                    $sql_item = "INSERT INTO order_items (order_id, listing_id, seller_id, quantity, unit_price) 
                                 VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql_item);
                    if (!$stmt) {
                        die("Errore nella preparazione della query order_items: " . $conn->error . "<br>SQL: " . $sql_item);
                    }
                    $stmt->bind_param(
                        "iiiid",
                        $order_id,
                        $item['listing_id'],
                        $item['seller_id'],
                        $item['quantity'],
                        $item['price']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Errore nell'aggiunta degli articoli all'ordine");
                    }
                    
                    // Update listing quantity
                    $new_quantity = $item['available_quantity'] - $item['quantity'];
                    $sql_update = "UPDATE listings SET quantity = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql_update);
                    $stmt->bind_param("ii", $new_quantity, $item['listing_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Errore nell'aggiornamento della disponibilità");
                    }
                    
                    // Deactivate listing if quantity reaches 0
                    if ($new_quantity <= 0) {
                        $sql_deactivate = "UPDATE listings SET is_active = FALSE WHERE id = ?";
                        $stmt = $conn->prepare($sql_deactivate);
                        $stmt->bind_param("i", $item['listing_id']);
                        $stmt->execute();
                    }
                }
                
                // Clear cart
                $sql_clear = "DELETE ci FROM cart_items ci 
                             JOIN carts c ON ci.cart_id = c.id 
                             WHERE c.user_id = ?";
                $stmt = $conn->prepare($sql_clear);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $conn->commit();
                
                // Redirect to order confirmation
                header("Location: order_confirmation.php?order_id=" . $order_id);
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Errore durante il completamento dell'ordine: " . $e->getMessage();
            } finally {
                $conn->autocommit(true);
            }
        }
    }
}

// Get payment methods
$payment_methods = [];
$sql_payments = "SELECT id, method_name FROM payment_methods";
$result_payments = $conn->query($sql_payments);
if ($result_payments && $result_payments->num_rows > 0) {
    while ($row = $result_payments->fetch_assoc()) {
        $payment_methods[] = $row;
    }
}

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="checkout-container">
    <h1>Checkout</h1>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <p>Il tuo carrello è vuoto</p>
            <a href="<?php echo $base_url; ?>/public/marketplace.php" class="btn-primary">Continua lo shopping</a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <div class="checkout-content">
                <!-- Order Summary -->
                <div class="order-summary">
                    <h2>Riepilogo ordine</h2>
                    <div class="order-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <div class="item-info">
                                    <strong><?php echo htmlspecialchars($item['name_en']); ?></strong><br>
                                    <?php echo htmlspecialchars($item['expansion_name']); ?> - <?php echo htmlspecialchars($item['condition_name']); ?><br>
                                    Venditore: <?php echo htmlspecialchars($item['seller_name']); ?>
                                </div>
                                <div class="item-quantity">
                                    Quantità: <?php echo $item['quantity']; ?>
                                </div>
                                <div class="item-price">
                                    <?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?> €
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-total">
                        <strong>Totale: <?php echo number_format($total, 2, ',', '.'); ?> €</strong>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="shipping-info">
                    <h2>Informazioni di spedizione</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Nome *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Cognome *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Indirizzo *</label>
                        <input type="text" id="address" name="address" required 
                               value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Città *</label>
                            <input type="text" id="city" name="city" required 
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="postal_code">CAP *</label>
                            <input type="text" id="postal_code" name="postal_code" required 
                                   value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Paese *</label>
                            <select id="country" name="country" required>
                                <option value="">Seleziona paese</option>
                                <option value="Italia" <?php echo (($_POST['country'] ?? '') === 'Italia') ? 'selected' : ''; ?>>Italia</option>
                                <option value="Francia" <?php echo (($_POST['country'] ?? '') === 'Francia') ? 'selected' : ''; ?>>Francia</option>
                                <option value="Germania" <?php echo (($_POST['country'] ?? '') === 'Germania') ? 'selected' : ''; ?>>Germania</option>
                                <option value="Spagna" <?php echo (($_POST['country'] ?? '') === 'Spagna') ? 'selected' : ''; ?>>Spagna</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="phone">Telefono</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Metodo di pagamento -->
                <div class="form-group">
                    <label for="payment_method">Metodo di pagamento *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Seleziona metodo</option>
                        <?php foreach ($payment_methods as $pm): ?>
                            <option value="<?php echo $pm['id']; ?>" <?php echo (($_POST['payment_method'] ?? '') == $pm['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pm['method_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Campi finti per la carta di credito -->
                <div id="credit-card-fields" style="display:none;">
                    <div class="form-group">
                        <label for="cc_number">Numero carta</label>
                        <input type="text" id="cc_number" name="cc_number" maxlength="19" placeholder="1234 5678 9012 3456">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cc_expiry">Scadenza</label>
                            <input type="text" id="cc_expiry" name="cc_expiry" maxlength="5" placeholder="MM/AA">
                        </div>
                        <div class="form-group">
                            <label for="cc_cvc">CVC</label>
                            <input type="text" id="cc_cvc" name="cc_cvc" maxlength="4" placeholder="123">
                        </div>
                    </div>
                </div>
                
                <div id="bank-transfer-fields" style="display:none;">
                    <div class="form-group">
                        <label for="iban">IBAN</label>
                        <input type="text" id="iban" name="iban" maxlength="34" placeholder="IT60X0542811101000000123456">
                    </div>
                </div>
            </div>
            
            <div class="checkout-actions">
                <a href="<?php echo $base_url; ?>/public/cart.php" class="btn-secondary">Torna al carrello</a>
                <button type="submit" name="complete_order" class="btn-primary">Completa ordine</button>
            </div>
        </form>
    <?php endif; ?>
    
    <?php if ($order_id): ?>
        <p>Il tuo numero d'ordine è <strong>#<?php echo htmlspecialchars($order_id); ?></strong>.</p>
        <?php if ($payment_method): ?>
            <p>Metodo di pagamento scelto: <strong><?php echo htmlspecialchars($payment_method); ?></strong></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.checkout-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.checkout-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.order-summary, .shipping-info {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.order-summary h2, .shipping-info h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.order-item:last-child {
    border-bottom: none;
}

.item-info {
    flex: 1;
    font-size: 14px;
    line-height: 1.4;
}

.item-quantity {
    margin: 0 15px;
    font-size: 14px;
}

.item-price {
    font-weight: bold;
    color: #28a745;
}

.order-total {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #ddd;
    text-align: right;
    font-size: 18px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #555;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.checkout-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.btn-primary, .btn-secondary {
    padding: 12px 24px;
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

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

@media (max-width: 768px) {
    .checkout-content {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .checkout-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-primary, .btn-secondary {
        width: 100%;
    }
}
</style>

<script>
function togglePaymentFields() {
    var ccFields = document.getElementById('credit-card-fields');
    var bankFields = document.getElementById('bank-transfer-fields');
    var paymentSelect = document.getElementById('payment_method');
    var selected = paymentSelect.options[paymentSelect.selectedIndex].text.toLowerCase();
    if (selected.includes('carta') || selected.includes('credit')) {
        ccFields.style.display = '';
        if (bankFields) bankFields.style.display = 'none';
    } else if (selected.includes('bank') || selected.includes('bonifico')) {
        ccFields.style.display = 'none';
        if (bankFields) bankFields.style.display = '';
    } else {
        ccFields.style.display = 'none';
        if (bankFields) bankFields.style.display = 'none';
    }
}

// Mostra/nasconde i campi al cambio select
document.getElementById('payment_method').addEventListener('change', togglePaymentFields);
// Mostra/nasconde i campi al caricamento pagina
window.addEventListener('DOMContentLoaded', togglePaymentFields);
</script>

<?php
// Include footer
include __DIR__ . '/partials/footer.php';
?>