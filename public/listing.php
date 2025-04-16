<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config/config.php';
$base_url = "/DataBase";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'active'; // Default to active listings tab

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Show 12 listings per page
$offset = ($page - 1) * $per_page;

// Get listings based on tab
if ($tab === 'active') {
    $status_condition = "is_active = TRUE";
} elseif ($tab === 'sold') {
    $status_condition = "is_active = FALSE AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
} else { // inactive
    $status_condition = "is_active = FALSE AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
}

$listings_sql = "SELECT l.id, l.price, l.quantity, l.description, l.created_at, l.is_active,
                sc.name_en, sc.image_url, sc.collector_number,
                e.name as expansion_name, e.code as expansion_code,
                g.display_name as game_name,
                cc.condition_name, cr.rarity_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.listing_id = l.id) as sold_count
                FROM listings l
                JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                JOIN expansions e ON sc.expansion_id = e.id
                JOIN games g ON e.game_id = g.id
                JOIN card_conditions cc ON l.condition_id = cc.id
                JOIN card_rarities cr ON sc.rarity_id = cr.id
                WHERE l.seller_id = ? AND $status_condition
                ORDER BY l.created_at DESC
                LIMIT ?, ?";

$stmt = $conn->prepare($listings_sql);
$stmt->bind_param("iii", $user_id, $offset, $per_page);
$stmt->execute();
$listings_result = $stmt->get_result();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM listings l WHERE l.seller_id = ? AND $status_condition";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();
$total_listings = $count_result['total'];
$total_pages = ceil($total_listings / $per_page);

// Get counts for tabs
$active_count_sql = "SELECT COUNT(*) as count FROM listings WHERE seller_id = ? AND is_active = TRUE";
$stmt = $conn->prepare($active_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_count = $stmt->get_result()->fetch_assoc()['count'];

$sold_count_sql = "SELECT COUNT(*) as count FROM listings l 
                  WHERE l.seller_id = ? AND l.is_active = FALSE 
                  AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
$stmt = $conn->prepare($sold_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sold_count = $stmt->get_result()->fetch_assoc()['count'];

$inactive_count_sql = "SELECT COUNT(*) as count FROM listings l 
                     WHERE l.seller_id = ? AND l.is_active = FALSE 
                     AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
$stmt = $conn->prepare($inactive_count_sql);
$stmt