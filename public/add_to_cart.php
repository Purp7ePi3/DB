<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$root_path = $_SERVER['DOCUMENT_ROOT'];
$base_url = "/DataBase";

// Include configuration file
require_once '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Devi effettuare il login per aggiungere prodotti al carrello',
        'redirect' => 'login.php'
    ]);
    exit;
}

// Check if POST data exists
if (!isset($_POST['listing_id']) || empty($_POST['listing_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Dati mancanti'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$listing_id = (int)$_POST['listing_id'];
$quantity = 1; // Default quantity to add

try {
    // Check if the listing exists and is active
    $check_listing = $conn->prepare("SELECT l.id, l.price, l.quantity, l.seller_id, sc.name_en 
                                     FROM listings l
                                     JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                                     WHERE l.id = ? AND l.is_active = TRUE");
    $check_listing->bind_param("i", $listing_id);
    $check_listing->execute();
    $result = $check_listing->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Questo prodotto non è più disponibile'
        ]);
        exit;
    }
    
    $listing = $result->fetch_assoc();
    
    // Prevent users from adding their own listings to cart
    if ($listing['seller_id'] == $user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Non puoi acquistare i tuoi stessi prodotti'
        ]);
        exit;
    }
    
    // Check if there's enough quantity available
    if ($listing['quantity'] < $quantity) {
        echo json_encode([
            'success' => false,
            'message' => 'Quantità richiesta non disponibile'
        ]);
        exit;
    }
    
    // Get or create user's cart
    $sql_get_cart = "SELECT id FROM carts WHERE user_id = ?";
    $stmt = $conn->prepare($sql_get_cart);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        // Create a new cart for the user
        $sql_create_cart = "INSERT INTO carts (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $stmt = $conn->prepare($sql_create_cart);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cart_id = $conn->insert_id;
    } else {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['id'];
    }
    
    // Check if the item is already in cart
    $sql_check_item = "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND listing_id = ?";
    $stmt = $conn->prepare($sql_check_item);
    $stmt->bind_param("ii", $cart_id, $listing_id);
    $stmt->execute();
    $item_result = $stmt->get_result();
    
    if ($item_result->num_rows > 0) {
        // Item exists, update quantity
        $item = $item_result->fetch_assoc();
        $new_quantity = $item['quantity'] + $quantity;
        
        // Check if adding would exceed available quantity
        if ($new_quantity > $listing['quantity']) {
            echo json_encode([
                'success' => false,
                'message' => 'Hai raggiunto la quantità massima disponibile per questo prodotto'
            ]);
            exit;
        }
        
        $sql_update = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ii", $new_quantity, $item['id']);
        $stmt->execute();
    } else {
        // Add new item to cart
        $sql_insert = "INSERT INTO cart_items (cart_id, listing_id, quantity, created_at, updated_at) 
                       VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("iii", $cart_id, $listing_id, $quantity);
        $stmt->execute();
    }
    
    // Update cart timestamp
    $sql_update_cart = "UPDATE carts SET updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql_update_cart);
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    
    // Calculate total items in cart
    $sql_count = "SELECT SUM(quantity) as total_items FROM cart_items WHERE cart_id = ?";
    $stmt = $conn->prepare($sql_count);
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $cart_count = $count_result->fetch_assoc()['total_items'] ?? 0;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Prodotto aggiunto al carrello',
        'cartCount' => $cart_count
    ]);
    
} catch (Exception $e) {
    // Log error (in a production environment)
    error_log("Cart error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Si è verificato un errore durante l\'aggiunta al carrello'
    ]);
}
?>