<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config/config.php';

// Check if listing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: marketplace.php");
    exit;
}

$listing_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'] ?? 0;

// Get listing details
$sql = "SELECT l.id, l.price, l.quantity, l.description, l.condition_id, l.seller_id, l.created_at,
        sc.name_en, sc.name_it, sc.image_url, sc.collector_number, sc.rarity_id, 
        e.name as expansion_name, e.code as expansion_code, e.release_date as expansion_release,
        g.display_name as game_name, g.id as game_id,
        cc.condition_name, cr.rarity_name,
        u.username as seller_name, up.rating as seller_rating, up.total_sales
        FROM listings l
        JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
        JOIN expansions e ON sc.expansion_id = e.id
        JOIN games g ON e.game_id = g.id
        JOIN card_conditions cc ON l.condition_id = cc.id
        JOIN card_rarities cr ON sc.rarity_id = cr.id
        JOIN accounts u ON l.seller_id = u.id
        JOIN user_profiles up ON u.id = up.user_id
        WHERE l.id = ? AND l.is_active = TRUE";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

// If listing not found, redirect to marketplace
if ($result->num_rows === 0) {
    header("Location: marketplace.php");
    exit;
}

$listing = $result->fetch_assoc();

// Check if in wishlist (if user is logged in)
$in_wishlist = false;
if ($user_id > 0) {
    $sql_wishlist = "SELECT id FROM wishlist_items WHERE user_id = ? AND listing_id = ?";
    $stmt = $conn->prepare($sql_wishlist);
    $stmt->bind_param("ii", $user_id, $listing_id);
    $stmt->execute();
    $result_wishlist = $stmt->get_result();
    $in_wishlist = ($result_wishlist->num_rows > 0);
}

// Get similar cards (same card, different sellers/conditions)
$sql_similar = "SELECT l.id, l.price, l.condition_id, cc.condition_name, u.username as seller_name, up.rating as seller_rating
                FROM listings l
                JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                JOIN accounts u ON l.seller_id = u.id
                JOIN user_profiles up ON u.id = up.user_id
                JOIN card_conditions cc ON l.condition_id = cc.id
                WHERE sc.name_en = ? AND l.id != ? AND l.is_active = TRUE
                ORDER BY l.price ASC
                LIMIT 5";

$stmt = $conn->prepare($sql_similar);
$stmt->bind_param("si", $listing['name_en'], $listing_id);
$stmt->execute();
$result_similar = $stmt->get_result();

// Get other cards from same seller
$sql_seller = "SELECT l.id, l.price, sc.name_en, sc.image_url, e.name as expansion_name
               FROM listings l
               JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
               JOIN expansions e ON sc.expansion_id = e.id
               WHERE l.seller_id = ? AND l.id != ? AND l.is_active = TRUE
               ORDER BY l.created_at DESC
               LIMIT 4";

$stmt = $conn->prepare($sql_seller);
$stmt->bind_param("ii", $listing['seller_id'], $listing_id);
$stmt->execute();
$result_seller = $stmt->get_result();

// Include header
include 'header.php';
?>

<div class="listing-container">
    <div class="breadcrumb">
        <a href="index.php">Home</a> &gt; 
        <a href="marketplace.php">Marketplace</a> &gt; 
        <a href="marketplace.php?game_id=<?php echo $listing['game_id']; ?>"><?php echo htmlspecialchars($listing['game_name']); ?></a> &gt; 
        <span><?php echo htmlspecialchars($listing['name_en']); ?></span>
    </div>
    
    <div class="listing-content">
        <div class="card-image-container">
            <?php if ($listing["image_url"]): ?>
                <img src="<?php echo htmlspecialchars($listing["image_url"]); ?>" alt="<?php echo htmlspecialchars($listing["name_en"]); ?>" class="card-full-image">
            <?php else: ?>
                <div class="no-image-large">Immagine non disponibile</div>
            <?php endif; ?>
        </div>
        
        <div class="card-details-container">
            <h1><?php echo htmlspecialchars($listing["name_en"]); ?></h1>
            <?php if (!empty($listing["name_it"])): ?>
                <h2 class="alternate-name"><?php echo htmlspecialchars($listing["name_it"]); ?></h2>
            <?php endif; ?>
            
            <div class="card-meta">
                <p class="expansion"><strong>Espansione:</strong> <?php echo htmlspecialchars($listing["expansion_name"]); ?> (<?php echo htmlspecialchars($listing["expansion_code"]); ?>)</p>
                <p class="game"><strong>Gioco:</strong> <?php echo htmlspecialchars($listing["game_name"]); ?></p>
                <p class="rarity"><strong>Rarità:</strong> <?php echo htmlspecialchars($listing["rarity_name"]); ?></p>
                <p class="condition"><strong>Condizione:</strong> <?php echo htmlspecialchars($listing["condition_name"]); ?></p>
                <p class="collector-number"><strong>Numero carta:</strong> <?php echo htmlspecialchars($listing["collector_number"]); ?></p>
            </div>
            
            <div class="listing-meta">
                <div class="price-container">
                    <span class="price"><?php echo number_format($listing["price"], 2, ',', '.'); ?> €</span>
                    <span class="quantity">Disponibilità: <?php echo $listing["quantity"]; ?></span>
                </div>
                
                <div class="seller-info">
                    <p><strong>Venditore:</strong> <a href="seller_profile.php?id=<?php echo $listing["seller_id"]; ?>"><?php echo htmlspecialchars($listing["seller_name"]); ?></a></p>
                    <p class="seller-rating">
                        <?php echo str_repeat('★', round($listing["seller_rating"])) . str_repeat('☆', 5 - round($listing["seller_rating"])); ?>
                        (<?php echo $listing["total_sales"]; ?> vendite)
                    </p>
                    <p class="listing-date">Annuncio pubblicato il <?php echo date('d/m/Y', strtotime($listing["created_at"])); ?></p>
                </div>
            </div>
            
            <?php if (!empty($listing["description"])): ?>
                <div class="listing-description">
                    <h3>Descrizione del venditore</h3>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($listing["description"])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="listing-actions">
                <?php if ($user_id > 0 && $listing["seller_id"] != $user_id): ?>
                    <button class="btn-primary btn-cart" data-listing-id="<?php echo $listing["id"]; ?>">
                        <i class="fas fa-shopping-cart"></i> Aggiungi al carrello
                    </button>
                    <button class="btn-wishlist <?php echo $in_wishlist ? 'active' : ''; ?>" data-card-id="<?php echo $listing["id"]; ?>">
                        <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i> 
                        <?php echo $in_wishlist ? 'Nella wishlist' : 'Aggiungi a wishlist'; ?>
                    </button>
                <?php elseif ($user_id == 0): ?>
                    <a href="login.php" class="btn-primary">Accedi per acquistare</a>
                <?php else: ?>
                    <p class="your-listing">Questo è il tuo annuncio</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($result_similar->num_rows > 0): ?>
    <div class="similar-listings">
        <h2>Altre offerte per questa carta</h2>
        <div class="similar-list">
            <table class="similar-table">
                <thead>
                    <tr>
                        <th>Venditore</th>
                        <th>Condizione</th>
                        <th>Prezzo</th>
                        <th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($similar = $result_similar->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($similar["seller_name"]); ?>
                            <span class="rating"><?php echo str_repeat('★', round($similar["seller_rating"])) . str_repeat('☆', 5 - round($similar["seller_rating"])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($similar["condition_name"]); ?></td>
                        <td class="price"><?php echo number_format($similar["price"], 2, ',', '.'); ?> €</td>
                        <td>
                            <a href="listing.php?id=<?php echo $similar["id"]; ?>" class="btn btn-sm">Visualizza</a>
                            <?php if ($user_id > 0): ?>
                            <button class="btn-cart btn-sm" data-listing-id="<?php echo $similar["id"]; ?>">Carrello</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($result_seller->num_rows > 0): ?>
    <div class="seller-other-cards">
        <h2>Altre carte di <?php echo htmlspecialchars($listing["seller_name"]); ?></h2>
        <div class="cards-grid">
            <?php while($seller_card = $result_seller->fetch_assoc()): ?>
            <div class="card-item">
                <a href="listing.php?id=<?php echo $seller_card["id"]; ?>">
                    <div class="card-image">
                        <?php if ($seller_card["image_url"]): ?>
                            <img src="<?php echo htmlspecialchars($seller_card["image_url"]); ?>" alt="<?php echo htmlspecialchars($seller_card["name_en"]); ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image">Immagine non disponibile</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-info">
                        <h3><?php echo htmlspecialchars($seller_card["name_en"]); ?></h3>
                        <p class="expansion"><?php echo htmlspecialchars($seller_card["expansion_name"]); ?></p>
                        <p class="price"><?php echo number_format($seller_card["price"], 2, ',', '.'); ?> €</p>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
        
        <div class="view-all">
            <a href="seller_cards.php?id=<?php echo $listing["seller_id"]; ?>" class="btn">Visualizza tutte le carte di questo venditore</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add to cart button
        const addToCartButtons = document.querySelectorAll('.btn-cart');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const listingId = this.getAttribute('data-listing-id');
                if (listingId) {
                    // Add animation
                    this.classList.add('adding');
                    
                    fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'listing_id=' + listingId
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Remove animation
                        this.classList.remove('adding');
                        
                        if (data.success) {
                            // Update cart count in header
                            const cartCount = document.querySelector('.cart-count');
                            if (cartCount) {
                                cartCount.textContent = data.cartCount;
                                cartCount.classList.add('update-animation');
                                setTimeout(() => {
                                    cartCount.classList.remove('update-animation');
                                }, 500);
                            }
                            
                            // Show success message
                            showNotification('Carta aggiunta al carrello', 'success');
                            
                            // Change button text temporarily
                            const originalText = this.innerHTML;
                            this.innerHTML = '<i class="fas fa-check"></i> Aggiunto';
                            setTimeout(() => {
                                this.innerHTML = originalText;
                            }, 1500);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        this.classList.remove('adding');
                        showNotification('Errore di connessione', 'error');
                    });
                }
            });
        });
        
        // Wishlist toggle
        const wishlistButton = document.querySelector('.btn-wishlist');
        if (wishlistButton) {
            wishlistButton.addEventListener('click', function() {
                const cardId = this.getAttribute('data-card-id');
                if (cardId) {
                    fetch('toggle_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'card_id=' + cardId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Toggle active class
                            this.classList.toggle('active');
                            
                            // Update icon and text
                            const icon = this.querySelector('i');
                            if (data.added) {
                                icon.className = 'fas fa-heart';
                                this.innerHTML = icon.outerHTML + ' Nella wishlist';
                                showNotification('Carta aggiunta alla wishlist', 'success');
                            } else {
                                icon.className = 'far fa-heart';
                                this.innerHTML = icon.outerHTML + ' Aggiungi a wishlist';
                                showNotification('Carta rimossa dalla wishlist', 'info');
                            }
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showNotification('Errore di connessione', 'error');
                    });
                }
            });
        }
        
        // Function to show notifications
        function showNotification(message, type = 'info') {
            // Create notification element if it doesn't exist
            let notification = document.getElementById('notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'notification';
                document.body.appendChild(notification);
            }
            
            // Set message and type
            notification.textContent = message;
            notification.className = 'notification ' + type;
            
            // Show notification
            notification.classList.add('show');
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
    });
</script>

<?php include 'footer.php'; ?>