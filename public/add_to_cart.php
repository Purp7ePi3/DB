<?php
// Sostituisci completamente il file public/add_to_cart.php con questo:

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$base_url = "/DataBase";

// Include configuration file
require_once '../config/config.php';

// Debug: check what data we're receiving
echo "<h3>Debug Info:</h3>";
echo "POST data: ";
var_dump($_POST);
echo "<br>GET data: ";
var_dump($_GET);
echo "<br>SESSION user_id: ";
var_dump($_SESSION['user_id'] ?? 'NOT SET');
echo "<br>REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'];
echo "<br><br>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Error: User not logged in<br>";
    echo '<a href="' . $base_url . '/auth/login.php">Go to login</a>';
    exit;
}

// Check if POST data exists
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Error: Not a POST request<br>";
    echo '<a href="' . $base_url . '/public/marketplace.php">Back to marketplace</a>';
    exit;
}

if (!isset($_POST['listing_id']) || empty($_POST['listing_id'])) {
    echo "Error: listing_id missing from POST data<br>";
    echo "POST data received: ";
    print_r($_POST);
    echo '<br><a href="' . $base_url . '/public/marketplace.php">Back to marketplace</a>';
    exit;
}

$user_id = $_SESSION['user_id'];
$listing_id = (int)$_POST['listing_id'];

echo "User ID: $user_id<br>";
echo "Listing ID: $listing_id<br>";

try {
    // Check if the listing exists and is active
    $check_listing = $conn->prepare("SELECT l.id, l.price, l.quantity, l.seller_id, l.single_card_id, sc.name_en 
                                     FROM listings l
                                     JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                                     WHERE l.id = ? AND l.is_active = TRUE");
    $check_listing->bind_param("i", $listing_id);
    $check_listing->execute();
    $result = $check_listing->get_result();
    
    if ($result->num_rows === 0) {
        echo "Error: Listing not found or not active<br>";
        echo '<a href="' . $base_url . '/public/marketplace.php">Back to marketplace</a>';
        exit;
    }
    
    $listing = $result->fetch_assoc();
    echo "Listing found: " . $listing['name_en'] . "<br>";
    
    // Prevent users from adding their own listings to cart
    if ($listing['seller_id'] == $user_id) {
        echo "Error: Cannot buy your own products<br>";
        echo '<a href="' . $base_url . '/public/cards.php?id=' . $listing['single_card_id'] . '">Back to card</a>';
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
        echo "Created new cart with ID: $cart_id<br>";
    } else {
        $cart = $cart_result->fetch_assoc();
        $cart_id = $cart['id'];
        echo "Using existing cart with ID: $cart_id<br>";
    }
    
    // Check if item already exists in cart
    $sql_check_existing = "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND listing_id = ?";
    $stmt = $conn->prepare($sql_check_existing);
    $stmt->bind_param("ii", $cart_id, $listing_id);
    $stmt->execute();
    $existing_result = $stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        // Update quantity if item already exists
        $existing_item = $existing_result->fetch_assoc();
        $new_quantity = $existing_item['quantity'] + 1;
        
        $sql_update = "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ii", $new_quantity, $existing_item['id']);
        
        if ($stmt->execute()) {
            echo "SUCCESS: Product quantity updated in cart!<br>";
        } else {
            echo "Error updating cart: " . $conn->error . "<br>";
        }
    } else {
        // Add new item to cart
        $sql_insert = "INSERT INTO cart_items (cart_id, listing_id, quantity, created_at, updated_at) 
                       VALUES (?, ?, 1, NOW(), NOW())";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("ii", $cart_id, $listing_id);
        
        if ($stmt->execute()) {
            echo "SUCCESS: Product added to cart!<br>";
        } else {
            echo "Error adding to cart: " . $conn->error . "<br>";
        }
    }
    
    echo '<a href="' . $base_url . '/public/cart.php">View Cart</a><br>';
    echo '<a href="' . $base_url . '/public/cards.php?id=' . $listing['single_card_id'] . '">Back to card</a>';
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
    echo '<a href="' . $base_url . '/public/marketplace.php">Back to marketplace</a>';
}
?>