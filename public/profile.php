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

// Check if user is admin (usando account_type_id per ora, 1 = admin)
$sql_check_admin = "SELECT account_type_id FROM accounts WHERE id = ?";
$stmt_admin = $conn->prepare($sql_check_admin);
$stmt_admin->bind_param("i", $user_id);
$stmt_admin->execute();
$admin_result = $stmt_admin->get_result();
$account_type = $admin_result->fetch_assoc()['account_type_id'] ?? 0;
$is_admin = ($account_type == 1); // Assumendo che account_type_id = 1 sia admin

// Handle ban/unban actions
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ban_user']) && isset($_POST['target_user_id'])) {
        $target_user_id = (int)$_POST['target_user_id'];
        
        // Ban user by setting is_active to 0 and deactivate their listings
        $conn->begin_transaction();
        try {
            // Update user account to inactive (bannato)
            $sql_ban = "UPDATE accounts SET is_active = 0 WHERE id = ? AND id != ?";
            $stmt = $conn->prepare($sql_ban);
            $stmt->bind_param("ii", $target_user_id, $user_id);
            $stmt->execute();
            
            // Deactivate all user's listings
            $sql_deactivate = "UPDATE listings SET is_active = 0 WHERE seller_id = ?";
            $stmt = $conn->prepare($sql_deactivate);
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['admin_message'] = "Utente bannato con successo. Tutti i suoi annunci sono stati disattivati.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['admin_error'] = "Errore durante il ban dell'utente: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['unban_user']) && isset($_POST['target_user_id'])) {
        $target_user_id = (int)$_POST['target_user_id'];
        
        // Unban user by setting is_active to 1
        $sql_unban = "UPDATE accounts SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql_unban);
        $stmt->bind_param("i", $target_user_id);
        
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = "Utente sbannato con successo.";
        } else {
            $_SESSION['admin_error'] = "Errore durante lo sban dell'utente.";
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// If viewing another user's profile for admin actions
$viewing_user_id = $user_id;
$is_viewing_other = false;
if ($is_admin && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $viewing_user_id = (int)$_GET['user_id'];
    $is_viewing_other = true;
}

// Fetch user profile data
$sql_user = "SELECT 
    a.id, a.username, a.email, a.created_at, a.is_active, a.account_type_id,
    up.first_name, up.last_name, up.rating, up.country
    FROM accounts a
    LEFT JOIN user_profiles up ON a.id = up.user_id
    WHERE a.id = ?";

$stmt = $conn->prepare($sql_user);
if (!$stmt) {
    die("Errore nella preparazione della query utente: " . $conn->error . "<br>SQL: " . $sql_user);
}
$stmt->bind_param("i", $viewing_user_id);
$stmt->execute();
$result_user = $stmt->get_result();

if ($result_user->num_rows === 0) {
    die("Utente non trovato");
}

$user = $result_user->fetch_assoc();
$user['is_banned'] = ($user['is_active'] == 0); // is_active = 0 significa bannato

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
$stmt_collection->bind_param("i", $viewing_user_id);
$stmt_collection->execute();
$collection_count = $stmt_collection->get_result()->fetch_assoc()['collection_count'];

// Active listings
$sql_listings = "SELECT COUNT(*) as listings_count FROM listings WHERE seller_id = ? AND is_active = TRUE";
$stmt_listings = $conn->prepare($sql_listings);
if (!$stmt_listings) {
    die("Errore nella preparazione della query listings: " . $conn->error . "<br>SQL: " . $sql_listings);
}
$stmt_listings->bind_param("i", $viewing_user_id);
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
$stmt_sales->bind_param("i", $viewing_user_id);
$stmt_sales->execute();
$sales_count = $stmt_sales->get_result()->fetch_assoc()['sales_count'];

// Total purchases
$sql_purchases = "SELECT COUNT(*) as purchases_count FROM orders WHERE buyer_id = ?";
$stmt_purchases = $conn->prepare($sql_purchases);
if (!$stmt_purchases) {
    die("Errore nella preparazione della query purchases: " . $conn->error . "<br>SQL: " . $sql_purchases);
}
$stmt_purchases->bind_param("i", $viewing_user_id);
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
$stmt_wishlist->bind_param("i", $viewing_user_id);
$stmt_wishlist->execute();
$wishlist_count = $stmt_wishlist->get_result()->fetch_assoc()['wishlist_count'];

// Admin statistics
$banned_users_count = 0;
if ($is_admin && !$is_viewing_other) {
    $sql_banned = "SELECT COUNT(*) as banned_count FROM accounts WHERE is_active = 0";
    $result_banned = $conn->query($sql_banned);
    $banned_users_count = $result_banned->fetch_assoc()['banned_count'];
}

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <h1>
            <?php if ($is_viewing_other): ?>
                Profilo di <?php echo htmlspecialchars($user['username']); ?>
                <?php if ($user['is_banned']): ?>
                    <span class="banned-badge">BANNATO</span>
                <?php endif; ?>
            <?php else: ?>
                Il mio profilo
            <?php endif; ?>
        </h1>
    </div>
    
    <?php if (isset($_SESSION['admin_message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['admin_message']); ?>
        </div>
        <?php unset($_SESSION['admin_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['admin_error']); ?>
        </div>
        <?php unset($_SESSION['admin_error']); ?>
    <?php endif; ?>
    
    <div class="profile-content">
        <div class="profile-info" style="display: flex; align-items: center; gap: 30px;">
            <div class="profile-avatar" style="width: 80px; height: 80px; background: #e3e9f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold; color: #4a6da7;">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="profile-details">
                <h2 style="margin-bottom: 5px;">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <?php if ($user['account_type_id'] === 1): ?>
                        <span class="admin-badge">ADMIN</span>
                    <?php endif; ?>
                </h2>
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
                
                <?php if ($is_admin && !$is_viewing_other): ?>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;">
                        <a href="banned_users.php" style="text-decoration: none; color: inherit;">
                            <?php echo $banned_users_count; ?>
                        </a>
                    </div>
                    <div class="stat-label" style="color: #666;">Utenti bannati</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-actions" style="margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
            <?php if (!$is_viewing_other): ?>
                <a href="edit_profile.php" class="btn btn-primary"><i class="fas fa-edit"></i> Modifica profilo</a>
                <a href="listings.php" class="btn"><i class="fas fa-list"></i> I miei annunci</a>
                <a href="orders.php" class="btn"><i class="fas fa-shopping-bag"></i> I miei ordini</a>
                <a href="wishlist.php" class="btn"><i class="fas fa-heart"></i> La mia wishlist</a>
                
                <?php if ($is_admin): ?>
                    <a href="banned_users.php" class="btn btn-admin"><i class="fas fa-users-slash"></i> Gestisci utenti bannati</a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($is_admin && $is_viewing_other && $viewing_user_id != $user_id): ?>
                <div class="admin-actions">
                    <?php if ($user['is_banned']): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler sbannare questo utente?');">
                            <input type="hidden" name="target_user_id" value="<?php echo $viewing_user_id; ?>">
                            <button type="submit" name="unban_user" class="btn btn-success">
                                <i class="fas fa-user-check"></i> Sbanna utente
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler bannare questo utente? Tutti i suoi annunci verranno disattivati.');">
                            <input type="hidden" name="target_user_id" value="<?php echo $viewing_user_id; ?>">
                            <button type="submit" name="ban_user" class="btn btn-danger">
                                <i class="fas fa-user-slash"></i> Banna utente
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
.banned-badge {
    background-color: #dc3545;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7em;
    font-weight: bold;
    margin-left: 10px;
}

.admin-badge {
    background-color: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.7em;
    font-weight: bold;
    margin-left: 10px;
}

.btn-admin {
    background-color: #6f42c1;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.2s;
}

.btn-admin:hover {
    background-color: #5a32a3;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.2s;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-success {
    background-color: #28a745;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.2s;
}

.btn-success:hover {
    background-color: #218838;
}

.admin-actions {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #6f42c1;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>