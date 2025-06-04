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
if (!in_array($tab, ['active', 'sold', 'inactive'])) {
    $tab = 'active';
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Show 12 listings per page
$offset = ($page - 1) * $per_page;

// Get listings based on tab
if ($tab === 'active') {
    $status_condition = "l.is_active = TRUE";
} elseif ($tab === 'sold') {
    $status_condition = "l.is_active = FALSE AND EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
} else { // inactive
    $status_condition = "l.is_active = FALSE AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.listing_id = l.id)";
}

$listings_sql = "SELECT l.id, l.price, l.quantity, l.description, l.created_at, l.is_active,
                sc.name_en, sc.image_url, sc.collector_number,
                e.name as expansion_name, e.code as expansion_code,
                g.display_name as game_name,
                cc.condition_name, cr.rarity_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.listing_id = l.id) as sold_count
                FROM listings l
                LEFT JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                LEFT JOIN expansions e ON sc.expansion_id = e.id
                LEFT JOIN games g ON e.game_id = g.id
                LEFT JOIN card_conditions cc ON l.condition_id = cc.id
                LEFT JOIN card_rarities cr ON sc.rarity_id = cr.id
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
$stmt->bind_param("i", $user_id); 
$stmt->execute(); 
$inactive_count = $stmt->get_result()->fetch_assoc()['count']; 
include __DIR__ . '/partials/header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings</title>
    <?php 
        $page = basename($_SERVER['PHP_SELF']); // Ottiene il nome del file corrente
        // Carica il CSS per listings solo se siamo nella pagina di listings
        if ($page == 'listings.php'): ?>
            <link rel="stylesheet" href="<?= $base_url ?>/public/assets/css/listing.css">
        <?php endif; ?>
    </head>
<body>
    <div class="container">
        <h1>My Listings</h1>
        
        <!-- Tabs Navigation -->
        <div class="tabs">
            <a href="?tab=active" class="tab <?php echo ($tab === 'active') ? 'active' : ''; ?>">
                Active (<?php echo $active_count; ?>)
            </a>
            <a href="?tab=sold" class="tab <?php echo ($tab === 'sold') ? 'active' : ''; ?>">
                Sold (<?php echo $sold_count; ?>)
            </a>
            <a href="?tab=inactive" class="tab <?php echo ($tab === 'inactive') ? 'active' : ''; ?>">
                Inactive (<?php echo $inactive_count; ?>)
            </a>
        </div>
        
        <!-- Listings Grid -->
        <div class="listings-grid">
            <?php if ($listings_result->num_rows > 0): ?>
                <?php while ($listing = $listings_result->fetch_assoc()): ?>
                    <div class="listing-card">
                        <div class="card-image">
                            <?php if (!empty($listing['image_url'])): ?>
                                <img src="https://www.cardtrader.com<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['name_en'] ?? 'Card'); ?>">
                            <?php else: ?>
                                <div class="no-image">No image</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-info">
                            <h3>
                                <?php
                                    if (!empty($listing['name_en'])) {
                                        echo htmlspecialchars($listing['name_en']);
                                    } else {
                                        echo 'Card #' . htmlspecialchars($listing['single_card_id']);
                                    }
                                ?>
                            </h3>
                            <p class="expansion"><?php echo htmlspecialchars($listing['expansion_name'] ?? ''); ?> (<?php echo htmlspecialchars($listing['expansion_code'] ?? ''); ?>)</p>
                            <p class="game"><?php echo htmlspecialchars($listing['game_name'] ?? ''); ?></p>
                            <p class="details">
                                <?php echo htmlspecialchars($listing['condition_name']); ?> | 
                                <?php echo htmlspecialchars($listing['rarity_name']); ?> | 
                                #<?php echo htmlspecialchars($listing['collector_number']); ?>
                            </p>
                            <p class="price">$<?php echo number_format($listing['price'], 2); ?></p>
                            <p class="quantity">
                                <?php if ($tab === 'active'): ?>
                                    Available: <?php echo htmlspecialchars($listing['quantity']); ?>
                                <?php elseif ($tab === 'sold'): ?>
                                    Sold: <?php echo htmlspecialchars($listing['sold_count']); ?>
                                <?php endif; ?>
                            </p>
                            
                            <!-- Actions based on tab -->
                            <div class="actions">
                                <?php if ($tab === 'active'): ?>
                                    <a href="edit_listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-edit">Edit</a>
                                    <form method="post" action="deactivate_listing.php">
                                        <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                        <button type="submit" class="btn btn-deactivate">Deactivate</button>
                                    </form>
                                <?php elseif ($tab === 'inactive'): ?>
                                    <a href="edit_listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-edit">Edit</a>
                                    <form method="post" action="activate_listing.php">
                                        <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                                        <button type="submit" class="btn btn-activate">Activate</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-listings">
                    <p>No listings found in this category.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?tab=<?php echo $tab; ?>&page=<?php echo ($page - 1); ?>" class="page-link">&laquo; Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?tab=<?php echo $tab; ?>&page=<?php echo $i; ?>" class="page-link <?php echo ($page === $i) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?tab=<?php echo $tab; ?>&page=<?php echo ($page + 1); ?>" class="page-link">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo $base_url; ?>/assets/js/scripts.js"></script>
</body>
<?php include __DIR__ . '/partials/footer.php'; ?>
<style>
    /* Tabs Navigation */
    .tabs {
    display: flex;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--medium-gray);
    }
    
    .tab {
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    color: var(--dark-gray);
    font-weight: 500;
    border-radius: 4px 4px 0 0;
    margin-right: 0.5rem;
    transition: all 0.3s ease;
    }
    
    .tab:hover {
    background-color: var(--medium-gray);
    color: var(--primary-color);
    }
    
    .tab.active {
    background-color: var(--primary-color);
    color: var(--white);
    border-bottom: 3px solid var(--accent-color);
    }
    
    /* Listings Grid */
    .listings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    }
    
    .listing-card {
    background-color: var(--white);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    }
    
    .listing-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      }
      
      .card-image {
        height: 200px;
        overflow: hidden;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
      }
      
      .card-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        transition: transform 0.5s ease;
      }
      
    
    .listing-card:hover .card-image img {
    transform: scale(1.05);
    }
    
    .card-info {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    }
    
    .card-info h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    }
    
    .expansion {
    font-size: 0.9rem;
    color: var(--dark-gray);
    margin-bottom: 0.25rem;
    }
    
    .game {
    font-size: 0.85rem;
    color: var(--dark-gray);
    margin-bottom: 0.5rem;
    font-style: italic;
    }
    
    .details {
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px dashed var(--medium-gray);
    }
    
    .price {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--accent-color);
    margin: 0.5rem 0;
    }
    
    .quantity {
    font-size: 0.9rem;
    margin-bottom: 1rem;
    }
    
    .actions {
    display: flex;
    justify-content: space-between;
    margin-top: auto;
    }
    
    .btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    font-size: 0.9rem;
    transition: background-color 0.3s ease;
    }
    
    .btn-edit {
    background-color: var(--primary-color);
    color: var(--white);
    flex: 1;
    margin-right: 0.5rem;
    }
    
    .btn-edit:hover {
    background-color: #3a5a8d;
    }
    
    .btn-deactivate, .btn-activate {
    flex: 1;
    display: inline-block;
    }
    
    .btn-deactivate {
    background-color: var(--warning-color);
    color: #212529;
    }
    
    .btn-deactivate:hover {
    background-color: #e0a800;
    }
    
    .btn-activate {
    background-color: var(--success-color);
    color: var(--white);
    }
    
    .btn-activate:hover {
    background-color: #218838;
    }
    
    form {
    flex: 1;
    }
    
    /* No listings message */
    .no-listings {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: var(--shadow);
    }
    
    .no-listings p {
    font-size: 1.1rem;
    color: var(--dark-gray);
    }
    
    /* Pagination */
    .pagination {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
    }
    
    .page-link {
    padding: 0.5rem 1rem;
    margin: 0 0.25rem;
    background-color: var(--white);
    color: var(--primary-color);
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
    }
    
    .page-link:hover {
    background-color: var(--medium-gray);
    }
    
    .page-link.active {
    background-color: var(--primary-color);
    color: var(--white);
    }
    
    /* Responsive Adjustments */
    @media (max-width: 768px) {
    .listings-grid {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .tabs {
    flex-wrap: wrap;
    }
    
    .tab {
    margin-bottom: 0.5rem;
    }
    
    .actions {
    flex-direction: column;
    }
    
    .btn-edit, form {
    flex: none;
    width: 100%;
    margin-right: 0;
    margin-bottom: 0.5rem;
    }
    }
    
    @media (max-width: 480px) {
    .listings-grid {
    grid-template-columns: 1fr;
    }
    
    .container {
    width: 100%;
    padding: 0.5rem;
    }
    
    h1 {
    font-size: 1.5rem;
    }
    }
    
</style>
</html>