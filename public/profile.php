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

// Fetch user profile data
$sql_user = "SELECT 
    a.id, a.username, a.email, a.created_at,
    up.first_name, up.last_name, up.bio, up.rating, up.country
    FROM accounts a
    LEFT JOIN user_profiles up ON a.id = up.user_id
    WHERE a.id = ?";

$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_user = $stmt->get_result();

if ($result_user->num_rows === 0) {
    die("Utente non trovato");
}

$user = $result_user->fetch_assoc();

// Fetch statistics
// Cards in collection
$sql_collection = "SELECT COUNT(*) as collection_count 
                  FROM cart_items ci
                  JOIN carts c ON ci.cart_id = c.id
                  WHERE c.user_id = ?";$stmt = $conn->prepare($sql_collection);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$collection_count = $stmt->get_result()->fetch_assoc()['collection_count'];

// Active listings
$sql_listings = "SELECT COUNT(*) as listings_count FROM listings WHERE seller_id = ? AND is_active = TRUE";
$stmt = $conn->prepare($sql_listings);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings_count = $stmt->get_result()->fetch_assoc()['listings_count'];

// Total sales
$sql_sales = "SELECT COUNT(*) as sales_count FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN listings l ON oi.listing_id = l.id
              WHERE l.seller_id = ?";
$stmt = $conn->prepare($sql_sales);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_count = $stmt->get_result()->fetch_assoc()['sales_count'];

// Total purchases
$sql_purchases = "SELECT COUNT(*) as purchases_count FROM orders WHERE buyer_id = ?";
$stmt = $conn->prepare($sql_purchases);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$purchases_count = $stmt->get_result()->fetch_assoc()['purchases_count'];

// Wishlist count
$sql_wishlist = "SELECT COUNT(*) as wishlist_count 
                FROM wishlist_items wi
                JOIN wishlists w ON wi.wishlist_id = w.id
                WHERE w.user_id = ?";$stmt = $conn->prepare($sql_wishlist);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist_count = $stmt->get_result()->fetch_assoc()['wishlist_count'];

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <h1>Il mio profilo</h1>
    </div>
    
    <div class="profile-content">
        <div class="profile-info">
            <div class="profile-avatar">
                <div class="avatar-placeholder">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
            </div>
            
            <div class="profile-details">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p class="user-rating">
                    <?php echo str_repeat('★', round($user['rating'])) . str_repeat('☆', 5 - round($user['rating'])); ?>
                    <span><?php echo number_format($user['rating'], 1); ?>/5</span>
                </p>
                <p class="user-since">Membro da <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                <?php if (!empty($user['location'])): ?>
                    <p class="user-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['location']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-bio">
            <h3>Bio</h3>
            <?php if (!empty($user['bio'])): ?>
                <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            <?php else: ?>
                <p>Nessuna bio disponibile. <a href="edit_profile.php">Aggiungi una bio</a></p>
            <?php endif; ?>
        </div>
        
        <div class="profile-stats">
            <h3>Statistiche</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $collection_count; ?></div>
                    <div class="stat-label">Carte in collezione</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $listings_count; ?></div>
                    <div class="stat-label">Annunci attivi</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $sales_count; ?></div>
                    <div class="stat-label">Vendite</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $purchases_count; ?></div>
                    <div class="stat-label">Acquisti</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $wishlist_count; ?></div>
                    <div class="stat-label">Carte in wishlist</div>
                </div>
            </div>
        </div>
        
        <div class="profile-actions">
            <a href="edit_profile.php" class="btn btn-primary"><i class="fas fa-edit"></i> Modifica profilo</a>
            <a href="listings.php" class="btn"><i class="fas fa-list"></i> I miei annunci</a>
            <a href="orders.php" class="btn"><i class="fas fa-shopping-bag"></i> I miei ordini</a>
            <a href="wishlist.php" class="btn"><i class="fas fa-heart"></i> La mia wishlist</a>
        </div>
    </div>
</div>

<style>
   
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>