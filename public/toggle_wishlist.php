<?php
// Start session if not already started
session_start();

// Include database configuration
require_once '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json');

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

try {
    // Get or create user's wishlist
    $sql_get_wishlist = "SELECT id FROM wishlists WHERE user_id = ?";
    $stmt = $conn->prepare($sql_get_wishlist);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $wishlist_result = $stmt->get_result();
    
    if ($wishlist_result->num_rows === 0) {
        // Create a default wishlist for the user
        $sql_create_wishlist = "INSERT INTO wishlists (user_id, name, created_at) VALUES (?, 'My Wishlist', NOW())";
        $stmt = $conn->prepare($sql_create_wishlist);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $wishlist_id = $conn->insert_id;
    } else {
        $wishlist = $wishlist_result->fetch_assoc();
        $wishlist_id = $wishlist['id'];
    }

    // Check if the card is already in the wishlist
    $sql_check = "SELECT id FROM wishlist_items WHERE wishlist_id = ? AND single_card_id = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ii", $wishlist_id, $card_id);
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
        // Check if the card exists
        $sql_check_card = "SELECT blueprint_id FROM single_cards WHERE blueprint_id = ?";
        $stmt = $conn->prepare($sql_check_card);
        $stmt->bind_param("i", $card_id);
        $stmt->execute();
        $result_card = $stmt->get_result();
        
        if ($result_card->num_rows === 0) {
            $response['message'] = 'La carta non esiste';
            echo json_encode($response);
            exit;
        }
        
        // Add card to wishlist
        $sql_insert = "INSERT INTO wishlist_items (wishlist_id, single_card_id, desired_condition_id) 
                       VALUES (?, ?, NULL)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("ii", $wishlist_id, $card_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Carta aggiunta alla wishlist';
            $response['added'] = true;
        } else {
            $response['message'] = 'Errore nell\'aggiunta alla wishlist: ' . $conn->error;
        }
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Wishlist error: " . $e->getMessage());
    $response['message'] = 'Si è verificato un errore durante l\'operazione';
}

// Return JSON response
echo json_encode($response);
?>