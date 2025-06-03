<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$base_url = "/DataBase";

// Include configuration file
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'add_to_cart.php';
    header("Location: $base_url/auth/login.php");
    exit;
}

// Check if POST data exists
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $base_url/public/marketplace.php");
    exit;
}

if (!isset($_POST['listing_id']) || empty($_POST['listing_id'])) {
    header("Location: $base_url/public/marketplace.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$listing_id = (int)$_POST['listing_id'];

try {
    // Check if the listing exists and is active
    $check_listing = $conn->prepare("SELECT l.id, l.price, l.quantity, l.seller_id, l.single_card_id, sc.name_en 
                                     FROM listings l
                                     JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                                     WHERE l.id = ? AND l.is_active = TRUE");
    
    if (!$check_listing) {
        throw new Exception("Error preparing listing check: " . $conn->error);
    }
    
    $check_listing->bind_param("i", $listing_id);
    $check_listing->execute();
    $result = $check_listing->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = 'Annuncio non trovato o non attivo.';
        header("Location: $base_url/public/marketplace.php");
        exit;
    }
    
    $listing = $result->fetch_assoc();
    
    // Prevent users from adding their own listings to cart
    if ($listing['seller_id'] == $user_id) {
        $_SESSION['error_message'] = 'Non puoi acquistare i tuoi stessi prodotti.';
        header("Location: $base_url/public/cards.php?id=" . $listing['single_card_id']);
        exit;
    }
    
    // Get or create user's cart
    $sql_get_cart = "SELECT id FROM carts WHERE user_id = ?";
    $stmt = $conn->prepare($sql_get_cart);
    
    if (!$stmt) {
        throw new Exception("Error preparing cart query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        // Create a new cart for the user - QUERY CORRETTA senza created_at/updated_at
        $sql_create_cart = "INSERT INTO carts (user_id) VALUES (?)";
        $stmt = $conn->prepare($sql_create_cart);
        
        if (!$stmt) {
            throw new Exception("Error preparing cart creation: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $cart_id = $conn->insert_id;
        } else {
            throw new Exception("Error creating cart: " . $conn->error);
        }
    } else {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['id'];
    }
    
    // Check if item already exists in cart
    $sql_check_existing = "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND listing_id = ?";
    $stmt = $conn->prepare($sql_check_existing);
    
    if (!$stmt) {
        throw new Exception("Error preparing existing item check: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $cart_id, $listing_id);
    $stmt->execute();
    $existing_result = $stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        // Update quantity if item already exists
        $existing_item = $existing_result->fetch_assoc();
        $new_quantity = $existing_item['quantity'] + 1;
        
        $sql_update = "UPDATE cart_items SET quantity = ? WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        
        if (!$stmt) {
            throw new Exception("Error preparing cart update: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $new_quantity, $existing_item['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Quantità aggiornata nel carrello!';
        } else {
            throw new Exception("Error updating cart: " . $conn->error);
        }
    } else {
        // Add new item to cart - QUERY CORRETTA senza created_at/updated_at
        $sql_insert = "INSERT INTO cart_items (cart_id, listing_id, quantity) VALUES (?, ?, 1)";
        $stmt = $conn->prepare($sql_insert);
        
        if (!$stmt) {
            throw new Exception("Error preparing cart insert: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $cart_id, $listing_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Prodotto aggiunto al carrello con successo!';
        } else {
            throw new Exception("Error adding to cart: " . $conn->error);
        }
    }
    
    // Redirect back to the card page with success message
    header("Location: $base_url/public/cards.php?id=" . $listing['single_card_id']);
    exit;
    
} catch (Exception $e) {
    // Log error and set error message
    error_log("Add to cart error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Errore durante l\'aggiunta al carrello. Riprova.';
    
    // Try to redirect back to the card page if we have the card ID
    if (isset($listing['single_card_id'])) {
        header("Location: $base_url/public/cards.php?id=" . $listing['single_card_id']);
    } else {
        header("Location: $base_url/public/marketplace.php");
    }
    exit;
}
?>