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
    up.first_name, up.last_name, up.rating, up.country
    FROM accounts a
    LEFT JOIN user_profiles up ON a.id = up.user_id
    WHERE a.id = ?";

$stmt = $conn->prepare($sql_user);
if (!$stmt) {
    die("Errore nella preparazione della query utente: " . $conn->error . "<br>SQL: " . $sql_user);
}
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
                  WHERE c.user_id = ?";
$stmt_collection = $conn->prepare($sql_collection);
if (!$stmt_collection) {
    die("Errore nella preparazione della query collection: " . $conn->error . "<br>SQL: " . $sql_collection);
}
$stmt_collection->bind_param("i", $user_id);
$stmt_collection->execute();
$collection_count = $stmt_collection->get_result()->fetch_assoc()['collection_count'];

// Active listings
$sql_listings = "SELECT COUNT(*) as listings_count FROM listings WHERE seller_id = ? AND is_active = TRUE";
$stmt_listings = $conn->prepare($sql_listings);
if (!$stmt_listings) {
    die("Errore nella preparazione della query listings: " . $conn->error . "<br>SQL: " . $sql_listings);
}
$stmt_listings->bind_param("i", $user_id);
$stmt_listings->execute();
$listings_count = $stmt_listings->get_result()->fetch_assoc()['listings_count'];

// Total sales
$sql_sales = "SELECT COUNT(*) as sales_count FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN listings l ON oi.listing_id = l.id
              WHERE l.seller_id = ?";
$stmt_sales = $conn->prepare($sql_sales);
if (!$stmt_sales) {
    die("Errore nella preparazione della query sales: " . $conn->error . "<br>SQL: " . $sql_sales);
}
$stmt_sales->bind_param("i", $user_id);
$stmt_sales->execute();
$sales_count = $stmt_sales->get_result()->fetch_assoc()['sales_count'];

// Total purchases
$sql_purchases = "SELECT COUNT(*) as purchases_count FROM orders WHERE buyer_id = ?";
$stmt_purchases = $conn->prepare($sql_purchases);
if (!$stmt_purchases) {
    die("Errore nella preparazione della query purchases: " . $conn->error . "<br>SQL: " . $sql_purchases);
}
$stmt_purchases->bind_param("i", $user_id);
$stmt_purchases->execute();
$purchases_count = $stmt_purchases->get_result()->fetch_assoc()['purchases_count'];

// Wishlist count
$sql_wishlist = "SELECT COUNT(*) as wishlist_count 
                FROM wishlist_items wi
                JOIN wishlists w ON wi.wishlist_id = w.id
                WHERE w.user_id = ?";
$stmt_wishlist = $conn->prepare($sql_wishlist);
if (!$stmt_wishlist) {
    die("Errore nella preparazione della query wishlist: " . $conn->error . "<br>SQL: " . $sql_wishlist);
}
$stmt_wishlist->bind_param("i", $user_id);
$stmt_wishlist->execute();
$wishlist_count = $stmt_wishlist->get_result()->fetch_assoc()['wishlist_count'];

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <h1>Il mio profilo</h1>
    </div>
    <div class="profile-content">
        <div class="profile-info" style="display: flex; align-items: center; gap: 30px;">
            <div class="profile-avatar" style="width: 80px; height: 80px; background: #e3e9f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; color: #4a6da7;">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="profile-details">
                <h2 style="margin-bottom: 5px;"><?php echo htmlspecialchars($user['username']); ?></h2>
                <p class="user-rating" style="margin-bottom: 5px;">
                    <?php echo str_repeat('★', round($user['rating'])) . str_repeat('☆', 5 - round($user['rating'])); ?>
                    <span><?php echo number_format($user['rating'], 1); ?>/5</span>
                </p>
                <p class="user-since" style="color: #666; margin-bottom: 0;">Membro da <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                <?php if (!empty($user['country'])): ?>
                    <p class="user-location" style="color: #666; margin-bottom: 0;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['country']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-stats" style="margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">Statistiche</h3>
            <div class="stats-grid" style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $collection_count; ?></div>
                    <div class="stat-label" style="color: #666;">Carte in collezione</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $listings_count; ?></div>
                    <div class="stat-label" style="color: #666;">Annunci attivi</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $sales_count; ?></div>
                    <div class="stat-label" style="color: #666;">Vendite</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $purchases_count; ?></div>
                    <div class="stat-label" style="color: #666;">Acquisti</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $wishlist_count; ?></div>
                    <div class="stat-label" style="color: #666;">Carte in wishlist</div>
                </div>
            </div>
        </div>
        <div class="profile-actions" style="margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
            <a href="edit_profile.php" class="btn btn-primary"><i class="fas fa-edit"></i> Modifica profilo</a>
            <a href="listings.php" class="btn"><i class="fas fa-list"></i> I miei annunci</a>
            <a href="orders.php" class="btn"><i class="fas fa-shopping-bag"></i> I miei ordini</a>
            <a href="wishlist.php" class="btn"><i class="fas fa-heart"></i> La mia wishlist</a>
        </div>

        <?php if (!empty($_SESSION['profile_success'])): ?>
            <div class="alert alert-success" style="margin: 20px auto; max-width: 800px;">
                <?php echo htmlspecialchars($_SESSION['profile_success']); ?>
            </div>
        <?php
            unset($_SESSION['profile_success']);
        endif;
        ?>
    </div>
</div>

<style>
   
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>