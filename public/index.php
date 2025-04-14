<?php
// Includi il file di configurazione
require_once '../config/config.php';

// Query per ottenere le carte più recenti (ultimi annunci)
$sql_latest = "SELECT sc.name_en, sc.image_url, sc.collector_number,
                e.name AS expansion_name,
                g.display_name AS game_name
                FROM single_cards sc
                JOIN expansions e ON sc.expansion_id = e.id
                JOIN games g ON e.game_id = g.id
                ORDER BY RAND()
                LIMIT 12";
$result_latest = $conn->query($sql_latest);

// Query per ottenere le carte più costose
$sql_expensive = "SELECT l.id, l.price, l.condition_id, sc.name_en, sc.image_url, sc.collector_number, 
                  e.name as expansion_name, g.display_name as game_name, cc.condition_name
                  FROM listings l
                  JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                  JOIN expansions e ON sc.expansion_id = e.id
                  JOIN games g ON e.game_id = g.id
                  JOIN card_conditions cc ON l.condition_id = cc.id
                  WHERE l.is_active = TRUE
                  ORDER BY l.price DESC
                  LIMIT 8";
$result_expensive = $conn->query($sql_expensive);

// Includi l'header
include 'partials/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <h1>Il mercato italiano per le carte collezionabili</h1>
        <p>Compra e vendi carte di Magic: The Gathering, Pokémon, Yu-Gi-Oh! e molti altri giochi!</p>
        <div class="hero-buttons">
            <a href="marketplace.php" class="btn btn-primary">Esplora il marketplace</a>
            <a href="register.php" class="btn">Registrati ora</a>
        </div>
    </div>
</section>

<section class="featured-games">
    <h2>Giochi disponibili</h2>
    <div class="games-grid">
        <?php
        // Query per ottenere tutti i giochi
        $sql_games = "SELECT id, display_name, name FROM games ORDER BY display_name";
        $result_games = $conn->query($sql_games);
        
        if ($result_games->num_rows > 0) {
            while($game = $result_games->fetch_assoc()) {
                echo '<a href="game.php?id=' . $game["id"] . '" class="game-card">';
                echo '<div class="game-logo">';
                echo '<img src="https://www.cardtrader.com/images/games/' . $game["name"] . '-logo.png" alt="' . htmlspecialchars($game["display_name"]) . '">';
                echo '</div>';
                echo '<h3>' . htmlspecialchars($game["display_name"]) . '</h3>';
                echo '</a>';
            }
        }
        ?>
    </div>
</section>

<section class="latest-listings">
    <h2>Random Cards</h2>
    <div class="cards-grid">
        <?php
        if ($result_latest->num_rows > 0) {
            while($card = $result_latest->fetch_assoc()) {
                ?>
                <div class="card-item">
                    <!-- <a href="listing.php?id=<?php echo $card["id"]; ?>"> -->
                        <div class="card-image">
                            <?php if ($card["image_url"]): ?>
                                <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"]); ?>">
                            <?php else: ?>
                                <div class="no-image">Immagine non disponibile</div>
                            <?php endif; ?>
                        </div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($card["name_en"]); ?></h3>
                            <p class="card-expansion"><?php echo htmlspecialchars($card["expansion_name"]); ?> (#<?php echo htmlspecialchars($card["collector_number"]); ?>)</p>
                            <p class="card-game"><?php echo htmlspecialchars($card["game_name"]); ?></p>
                            <div class="card-details">
                                <!-- <span class="card-condition"><?php echo htmlspecialchars($card["condition_name"]); ?></span> -->
                                <!-- <span class="card-price"><?php echo number_format($card["price"], 2, ',', '.'); ?> €</span> -->
                            </div>
                        </div>
                    </a>
                    <!-- <div class="card-actions">
                        <a href="add_to_cart.php?listing_id=<?php echo $card["id"]; ?>" class="btn-cart">
                            <i class="fas fa-cart-plus"></i> Aggiungi al carrello
                        </a> 
                         <a href="add_to_wishlist.php?card_id=<?php echo $card["id"]; ?>" class="btn-wishlist">
                            <i class="fas fa-heart"></i>
                        </a>
                    </div> -->
                </div>
                <?php
            }
        } else {
            echo "<p>Nessun annuncio disponibile al momento.</p>";
        }
        ?>
    </div>
    <!-- <div class="view-more">
        <a href="marketplace.php" class="btn">Visualizza tutti gli annunci</a>
    </div> -->
</section>

<section class="featured-cards">
    <h2>Carte in evidenza</h2>
    <div class="featured-grid">
        <?php
        if ($result_expensive->num_rows > 0) {
            while($card = $result_expensive->fetch_assoc()) {
                ?>
                <div class="featured-card">
                    <a href="listing.php?id=<?php echo $card["id"]; ?>">
                        <div class="featured-image">
                            <?php if ($card["image_url"]): ?>
                                <img src="https://www.cardtrader.com/<?php echo htmlspecialchars($card["image_url"]); ?>" alt="<?php echo htmlspecialchars($card["name_en"]); ?>">
                            <?php else: ?>
                                <div class="no-image">Immagine non disponibile</div>
                            <?php endif; ?>
                            <div class="featured-price"><?php echo number_format($card["price"], 2, ',', '.'); ?> €</div>
                        </div>
                        <div class="featured-info">
                            <h3><?php echo htmlspecialchars($card["name_en"]); ?></h3>
                            <p><?php echo htmlspecialchars($card["game_name"]); ?> - <?php echo htmlspecialchars($card["expansion_name"]); ?></p>
                            <p class="featured-condition"><?php echo htmlspecialchars($card["condition_name"]); ?></p>
                        </div>
                    </a>
                </div>
                <?php
            }
        }
        ?>
    </div>
</section>

<section class="how-it-works">
    <h2>Come funziona Card Collector Center</h2>
    <div class="steps">
        <div class="step">
            <div class="step-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3>Registrati</h3>
            <p>Crea un account gratuito per iniziare a comprare e vendere carte collezionabili.</p>
        </div>
        <div class="step">
            <div class="step-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3>Cerca</h3>
            <p>Trova le carte che desideri con il nostro potente motore di ricerca e filtri avanzati.</p>
        </div>
        <div class="step">
            <div class="step-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h3>Compra</h3>
            <p>Acquista carte dai venditori verificati con pagamenti sicuri e garantiti.</p>
        </div>
        <div class="step">
            <div class="step-icon">
                <i class="fas fa-tag"></i>
            </div>
            <h3>Vendi</h3>
            <p>Pubblica annunci per le tue carte e raggiungi migliaia di collezionisti.</p>
        </div>
    </div>
</section>

<?php


// Includi il footer
include 'partials/footer.php';
// Chiudi la connessione al database
$conn->close();
?>