<?php
// Start session if not already started
session_start();

// Include database configuration
require_once 'config.php';

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'cartCount' => 0
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Per favore, accedi per aggiungere articoli al carrello';
    echo json_encode($response);
    exit;
}

// Check if listing_id is provided
if (!isset($_POST['listing_id']) || empty($_POST['listing_id'])) {
    $response['message'] = 'ID annuncio mancante';
    echo json_encode($response);
    exit;
}

$listing_id = (int)$_POST['listing_id'];
$user_id = $_SESSION['user_id'];

// Verify listing exists and is active
$sql_check = "SELECT l.id, l.price, l.quantity, sc.name_en 
              FROM listings l 
              JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
              WHERE l.id = ? AND l.is_active = TRUE";

$stmt = $conn->prepare($sql_check);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'L\'annuncio non esiste o non è più disponibile';
    echo json_encode($response);
    exit;
}

$listing = $result->fetch_assoc();

// Check if the listing belongs to the current user (can't buy your own listings)
$sql_check_owner = "SELECT id FROM listings WHERE id = ? AND seller_id = ?";
$stmt = $conn->prepare($sql_check_owner);
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result_owner = $stmt->get_result();

if ($result_owner->num_rows > 0) {
    $response['message'] = 'Non puoi acquistare le tue stesse carte';
    echo json_encode($response);
    exit;
}

// Check if the item is already in the cart
$sql_check_cart = "SELECT quantity FROM cart_items WHERE user_id = ? AND listing_id = ?";
$stmt = $conn->prepare($sql_check_cart);
$stmt->bind_param("ii", $user_id, $listing_id);
$stmt->execute();
$result_cart = $stmt->get_result();

if ($result_cart->num_rows > 0) {
    // Item exists in cart, update quantity
    $cart_item = $result_cart->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + 1;
    
    // Check if the requested quantity is available
    if ($new_quantity > $listing['quantity']) {
        $response['message'] = 'Quantità richiesta non disponibile';
        echo json_encode($response);
        exit;
    }
    
    $sql_update = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND listing_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("iii", $new_quantity, $user_id, $listing_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Quantità aggiornata nel carrello';
    } else {
        $response['message'] = 'Errore nell\'aggiornamento del carrello: ' . $conn->error;
    }
} else {
    // Add new item to cart
    $sql_insert = "INSERT INTO cart_items (user_id, listing_id, quantity, added_at) VALUES (?, ?, 1, NOW())";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("ii", $user_id, $listing_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Articolo aggiunto al carrello';
    } else {
        $response['message'] = 'Errore nell\'aggiunta al carrello: ' . $conn->error;
    }
}

// Get updated cart count
$sql_count = "SELECT SUM(quantity) as count FROM cart_items WHERE user_id = ?";
$stmt = $conn->prepare($sql_count);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_count = $stmt->get_result();
$row = $result_count->fetch_assoc();
$response['cartCount'] = (int)$row['count'];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);