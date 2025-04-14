<?php
// Avvia la sessione per gestire login utente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '/DataBase');
define('BASE_PATH', dirname(__DIR__));


$base_url = isset($base_path) ? $base_path : '';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Collector Center</title>
    <link rel="stylesheet" href="/DataBase/public/assets/css/style.css">
                   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="top-bar">
            <div class="container">
                <div class="logo">
                    <a href="<?php echo BASE_URL; ?>/public/index.php">
                        <h1>Card Collector Center</h1>
                    </a>
                </div>
                <div class="search-bar">
                    <form action="search.php" method="GET">
                        <input type="text" name="query" placeholder="Cerca carte...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <div class="user-actions">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="cart.php" class="cart-icon"><i class="fas fa-shopping-cart"></i></a>
                        <div class="user-menu">
                            <span><?php echo htmlspecialchars($_SESSION['username']); ?> <i class="fas fa-chevron-down"></i></span>
                            <div class="dropdown-menu">
                                <a href="profile.php">Il mio profilo</a>
                                <a href="orders.php">I miei ordini</a>
                                <a href="listings.php">I miei annunci</a>
                                <a href="wishlist.php">Wishlist</a>
                                <a href="<?php echo BASE_URL; ?>/auth/logout.php">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/DataBase/auth/login.php" class="btn">Accedi</a>
                        <a href="/DataBase/auth/register.php" class="btn btn-primary">Registrati</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <nav class="main-nav">
            <div class="container">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Giochi <i class="fas fa-chevron-down"></i></a>
                        <div class="dropdown-content">
                            <?php
                            // Includi il file di configurazione del database
                            require_once __DIR__ . '/../../config/config.php';
                            
                            // Query per ottenere tutti i giochi
                            $sql = "SELECT id, display_name FROM games ORDER BY display_name";
                            $result = $conn->query($sql);
                            
                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo '<a href="game.php?id=' . $row["id"] . '">' . htmlspecialchars($row["display_name"]) . '</a>';
                                }
                            }
                            ?>
                        </div>
                    </li>
                    <li><a href="<?php echo BASE_URL; ?>/public/marketplace.php">Marketplace</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/public/seller_guide.php">Guida per venditori</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/public/faq.php">FAQ</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/public/contact.php">Contatti</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="container">