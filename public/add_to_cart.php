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
    header("Location: $base_url/public/index.php");  
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
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if the item is already in cart
    $item_exists = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['listing_id'] == $listing_id) {
            // Check if adding would exceed available quantity
            if ($item['quantity'] + $quantity > $listing['quantity']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Hai raggiunto la quantità massima disponibile per questo prodotto'
                ]);
                exit;
            }
            
            // Increment quantity if already in cart
            $item['quantity'] += $quantity;
            $item_exists = true;
            break;
        }
    }
    
    // Add new item to cart if not exists
    if (!$item_exists) {
        $_SESSION['cart'][] = [
            'listing_id' => $listing_id,
            'name' => $listing['name_en'],
            'price' => $listing['price'],
            'quantity' => $quantity,
            'max_quantity' => $listing['quantity']
        ];
    }
    
    // Calculate total items in cart
    $cart_count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
    
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