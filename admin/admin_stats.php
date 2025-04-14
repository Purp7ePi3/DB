<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$root_path = $_SERVER['DOCUMENT_ROOT'];
$base_url = "/DataBase";

// Include database configuration
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: $base_url/public/index.php");
    exit;
}

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Include header
include 'header.php';

// Get total users
$sql_users = "SELECT COUNT(*) as total FROM accounts";
$result_users = $conn->query($sql_users);
$total_users = $result_users->fetch_assoc()['total'];

// Get new users in date range
$sql_new_users = "SELECT COUNT(*) as total FROM accounts WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($sql_new_users);
$end_date_adj = date('Y-m-d', strtotime($end_date . ' +1 day')); // Include end date fully
$stmt->bind_param("ss", $start_date, $end_date_adj);
$stmt->execute();
$result_new_users = $stmt->get_result();
$new_users = $result_new_users->fetch_assoc()['total'];

// Get total listings
$sql_listings = "SELECT COUNT(*) as total FROM listings WHERE is_active = TRUE";
$result_listings = $conn->query($sql_listings);
$total_listings = $result_listings->fetch_assoc()['total'];

// Get total completed orders
$sql_orders = "SELECT COUNT(*) as total FROM orders WHERE status = 'completed'";
$result_orders = $conn->query($sql_orders);
$total_orders = $result_orders->fetch_assoc()['total'];

// Get recent orders
$sql_recent_orders = "SELECT o.id, o.total_price as total_amount, o.order_date as created_at, o.status, 
                      a.username FROM orders o 
                      JOIN accounts a ON o.buyer_id = a.id 
                      ORDER BY o.order_date DESC LIMIT 10";
$result_recent_orders = $conn->query($sql_recent_orders);

// Get sales by game
$sql_sales_by_game = "SELECT g.display_name, COUNT(oi.id) as items_sold, 
                      SUM(oi.price * oi.quantity) as total_sales 
                      FROM order_items oi
                      JOIN listings l ON oi.listing_id = l.id
                      JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                      JOIN expansions e ON sc.expansion_id = e.id
                      JOIN games g ON e.game_id = g.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?
                      GROUP BY g.id
                      ORDER BY total_sales DESC";
$stmt = $conn->prepare($sql_sales_by_game);
$stmt->bind_param("ss", $start_date, $end_date_adj);
$stmt->execute();
$result_sales_by_game = $stmt->get_result();

// Get top selling cards
$sql_top_cards = "SELECT sc.name_en, e.name as expansion, g.display_name as game,
                  COUNT(oi.id) as times_sold, SUM(oi.quantity) as total_quantity
                  FROM order_items oi
                  JOIN listings l ON oi.listing_id = l.id
                  JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                  JOIN expansions e ON sc.expansion_id = e.id
                  JOIN games g ON e.game_id = g.id
                  JOIN orders o ON oi.order_id = o.id
                  WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?
                  GROUP BY sc.blueprint_id
                  ORDER BY total_quantity DESC
                  LIMIT 10";
$stmt = $conn->prepare($sql_top_cards);
$stmt->bind_param("ss", $start_date, $end_date_adj);
$stmt->execute();
$result_top_cards = $stmt->get_result();
?>

<div class="admin-container">
    <h1>Dashboard Amministratore</h1>
    
    <div class="date-filter">
        <form action="admin_stats.php" method="GET">
            <div class="filter-inputs">
                <div class="input-group">
                    <label for="start_date">Data inizio:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="input-group">
                    <label for="end_date">Data fine:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn-primary">Applica filtro</button>
            </div>
        </form>
    </div>
    
    <div class="admin-overview">
        <div class="stat-card">
            <h3>Utenti totali</h3>
            <div class="stat-value"><?php echo $total_users; ?></div>
            <div class="stat-detail">Nuovi: <span><?php echo $new_users; ?></span> nel periodo selezionato</div>
        </div>
        
        <div class="stat-card">
            <h3>Annunci attivi</h3>
            <div class="stat-value"><?php echo $total_listings; ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Ordini completati</h3>
            <div class="stat-value"><?php echo $total_orders; ?></div>
        </div>
    </div>
    
    <div class="admin-data-container">
        <div class="admin-data-section">
            <h2>Vendite per Gioco</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Gioco</th>
                        <th>Articoli venduti</th>
                        <th>Totale vendite</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_sales_by_game && $result_sales_by_game->num_rows > 0) {
                        while ($row = $result_sales_by_game->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['display_name']) . '</td>';
                            echo '<td>' . $row['items_sold'] . '</td>';
                            echo '<td>' . number_format($row['total_sales'], 2, ',', '.') . ' €</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">Nessun dato disponibile</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="admin-data-section">
            <h2>Carte più vendute</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nome carta</th>
                        <th>Espansione</th>
                        <th>Gioco</th>
                        <th>Quantità venduta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_top_cards && $result_top_cards->num_rows > 0) {
                        while ($row = $result_top_cards->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['name_en']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['expansion']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['game']) . '</td>';
                            echo '<td>' . $row['total_quantity'] . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">Nessun dato disponibile</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="admin-data-section">
            <h2>Ordini recenti</h2>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID Ordine</th>
                        <th>Utente</th>
                        <th>Data</th>
                        <th>Importo</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_recent_orders && $result_recent_orders->num_rows > 0) {
                        while ($row = $result_recent_orders->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . $row['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                            echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
                            echo '<td>' . number_format($row['total_amount'], 2, ',', '.') . ' €</td>';
                            echo '<td><span class="status-' . strtolower($row['status']) . '">' . ucfirst($row['status']) . '</span></td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="5">Nessun ordine recente</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include footer
include 'footer.php';

// Close database connection
$conn->close();
?>