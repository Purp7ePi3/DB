<?php
// Start session if not already started
session_start();

// Include database configuration
require_once 'config.php';

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'added' => false
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Per favore, accedi per gestire la tua wishlist';
    echo json_encode($response);
    exit;
}

// Check if card_id is provided
if (!isset($_POST['card_id']) || empty($_POST['card_id'])) {
    $response['message'] = 'ID carta mancante';
    echo json_encode($response);
    exit;
}

$card_id = (int)$_POST['card_id'];
$user_id = $_SESSION['user_id'];

// Check if the card is already in the wishlist
$sql_check = "SELECT id FROM wishlist_items WHERE user_id = ? AND listing_id = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("ii", $user_id, $card_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Card is in wishlist, remove it
    $wishlist_item = $result->fetch_assoc();
    
    $sql_delete = "DELETE FROM wishlist_items WHERE id = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $wishlist_item['id']);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Carta rimossa dalla wishlist';
        $response['added'] = false;
    } else {
        $response['message'] = 'Errore nella rimozione dalla wishlist: ' . $conn->error;
    }
} else {
    // Check if the listing exists and is active
    $sql_check_listing = "SELECT id FROM listings WHERE id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql_check_listing);
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    $result_listing = $stmt->get_result();
    
    if ($result_listing->num_rows === 0) {
        $response['message'] = 'L\'annuncio non esiste o non è più disponibile';
        echo json_encode($response);
        exit;
    }
    
    // Add card to wishlist
    $sql_insert = "INSERT INTO wishlist_items (user_id, listing_id, added_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("ii", $user_id, $card_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Carta aggiunta alla wishlist';
        $response['added'] = true;
    } else {
        $response['message'] = 'Errore nell\'aggiunta alla wishlist: ' . $conn->error;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);