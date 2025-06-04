<?php
require_once '../config/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p>Utente non trovato.</p>";
    exit;
}

$user_id = (int)$_GET['id'];

// Prendi dati utente
$sql = "SELECT a.id, a.username, a.email, up.rating, up.country
        FROM accounts a
        LEFT JOIN user_profiles up ON a.id = up.user_id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p>Utente non trovato.</p>";
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Conta annunci attivi
$sql_listings = "SELECT COUNT(*) as count FROM listings WHERE seller_id = ? AND is_active = TRUE";
$stmt = $conn->prepare($sql_listings);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Conta vendite totali
$sql_sales = "SELECT COUNT(*) as sales_count FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              JOIN listings l ON oi.listing_id = l.id
              WHERE l.seller_id = ?";
$stmt = $conn->prepare($sql_sales);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_count = $stmt->get_result()->fetch_assoc()['sales_count'];
$stmt->close();

include __DIR__ . '/partials/header.php';
?>

<div class="profile-container">
    <div class="profile-header">
        <h1>Profilo di <?php echo htmlspecialchars($user['username']); ?></h1>
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
                <?php if (!empty($user['country'])): ?>
                    <p class="user-location" style="color: #666; margin-bottom: 0;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['country']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-stats" style="margin-top: 30px;">
            <h3 style="margin-bottom: 15px;">Statistiche</h3>
            <div class="stats-grid" style="display: flex; gap: 30px; flex-wrap: wrap;">
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;">
                        <a href="listings.php?seller_id=<?php echo $user_id; ?>&tab=active" style="text-decoration: none; color: inherit;">
                            <?php echo $listings_count; ?>
                        </a>
                    </div>
                    <div class="stat-label" style="color: #666;">Annunci attivi</div>
                </div>
                <div class="stat-item" style="text-align: center;">
                    <div class="stat-value" style="font-size: 22px; font-weight: bold;"><?php echo $sales_count; ?></div>
                    <div class="stat-label" style="color: #666;">Vendite</div>
                </div>
            </div>
        </div>
        <div class="profile-actions" style="margin-top: 30px;">
            <a href="listings.php?seller_id=<?php echo $user_id; ?>" class="btn btn-primary">Vedi annunci</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>