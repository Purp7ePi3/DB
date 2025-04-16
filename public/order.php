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
$tab = $_GET['tab'] ?? 'purchases'; // Default to purchases tab

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get purchased orders
$purchases_sql = "SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at, 
                 COUNT(oi.id) as item_count
                 FROM orders o
                 JOIN order_items oi ON o.id = oi.order_id
                 WHERE o.buyer_id = ?
                 GROUP BY o.id
                 ORDER BY o.created_at DESC
                 LIMIT ?, ?";

$stmt = $conn->prepare($purchases_sql);
$stmt->bind_param("iii", $user_id, $offset, $per_page);
$stmt->execute();
$purchases_result = $stmt->get_result();

// Get total purchases count
$purchases_count_sql = "SELECT COUNT(DISTINCT o.id) as total FROM orders o WHERE o.buyer_id = ?";
$stmt = $conn->prepare($purchases_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$purchases_count = $stmt->get_result()->fetch_assoc()['total'];
$purchases_total_pages = ceil($purchases_count / $per_page);

// Get sold items
$sales_sql = "SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
             a.username as buyer_name,
             COUNT(oi.id) as item_count
             FROM orders o
             JOIN order_items oi ON o.id = oi.order_id
             JOIN listings l ON oi.listing_id = l.id
             JOIN accounts a ON o.buyer_id = a.id
             WHERE l.seller_id = ?
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT ?, ?";

$stmt = $conn->prepare($sales_sql);
$stmt->bind_param("iii", $user_id, $offset, $per_page);
$stmt->execute();
$sales_result = $stmt->get_result();

// Get total sales count
$sales_count_sql = "SELECT COUNT(DISTINCT o.id) as total 
                   FROM orders o
                   JOIN order_items oi ON o.id = oi.order_id
                   JOIN listings l ON oi.listing_id = l.id
                   WHERE l.seller_id = ?";
$stmt = $conn->prepare($sales_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_count = $stmt->get_result()->fetch_assoc()['total'];
$sales_total_pages = ceil($sales_count / $per_page);

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="orders-container">
    <div class="orders-header">
        <h1>I Miei Ordini</h1>
    </div>
    
    <div class="tabs">
        <a href="?tab=purchases" class="tab <?php echo $tab === 'purchases' ? 'active' : ''; ?>">
            Acquisti (<?php echo $purchases_count; ?>)
        </a>
        <a href="?tab=sales" class="tab <?php echo $tab === 'sales' ? 'active' : ''; ?>">
            Vendite (<?php echo $sales_count; ?>)
        </a>
    </div>
    
    <div class="orders-content">
        <?php if ($tab === 'purchases'): ?>
            <!-- Purchases tab -->
            <div class="tab-content active">
                <?php if ($purchases_result->num_rows > 0): ?>
                    <div class="orders-list">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Ordine</th>
                                    <th>Data</th>
                                    <th>Importo</th>
                                    <th>Stato</th>
                                    <th>Articoli</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $purchases_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                        <td class="price"><?php echo number_format($order['total_amount'], 2, ',', '.'); ?> €</td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php 
                                                    $status_labels = [
                                                        'PENDING' => 'In attesa',
                                                        'PROCESSING' => 'In lavorazione',
                                                        'SHIPPED' => 'Spedito',
                                                        'DELIVERED' => 'Consegnato',
                                                        'CANCELLED' => 'Annullato'
                                                    ];
                                                    echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $order['item_count']; ?></td>
                                        <td>
                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm">Dettaglio</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($purchases_total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?tab=purchases&page=<?php echo ($page - 1); ?>" class="page-link">&laquo; Precedente</a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($purchases_total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?tab=purchases&page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $purchases_total_pages): ?>
                                <a href="?tab=purchases&page=<?php echo ($page + 1); ?>" class="page-link">Successivo &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-orders">
                        <p>Non hai ancora effettuato acquisti.</p>
                        <a href="marketplace.php" class="btn btn-primary">Esplora il marketplace</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Sales tab -->
            <div class="tab-content active">
                <?php if ($sales_result->num_rows > 0): ?>
                    <div class="orders-list">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Ordine</th>
                                    <th>Data</th>
                                    <th>Acquirente</th>
                                    <th>Importo</th>
                                    <th>Stato</th>
                                    <th>Articoli</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $sales_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                        <td class="price"><?php echo number_format($order['total_amount'], 2, ',', '.'); ?> €</td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php 
                                                    $status_labels = [
                                                        'PENDING' => 'In attesa',
                                                        'PROCESSING' => 'In lavorazione',
                                                        'SHIPPED' => 'Spedito',
                                                        'DELIVERED' => 'Consegnato',
                                                        'CANCELLED' => 'Annullato'
                                                    ];
                                                    echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $order['item_count']; ?></td>
                                        <td>
                                            <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm">Dettaglio</a>
                                            <?php if ($order['status'] === 'PENDING'): ?>
                                                <a href="update_order_status.php?id=<?php echo $order['id']; ?>&status=PROCESSING" class="btn btn-sm btn-primary">Elabora</a>
                                            <?php elseif ($order['status'] === 'PROCESSING'): ?>
                                                <a href="update_order_status.php?id=<?php echo $order['id']; ?>&status=SHIPPED" class="btn btn-sm btn-primary">Spedisci</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($sales_total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?tab=sales&page=<?php echo ($page - 1); ?>" class="page-link">&laquo; Precedente</a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($sales_total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?tab=sales&page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $sales_total_pages): ?>
                                <a href="?tab=sales&page=<?php echo ($page + 1); ?>" class="page-link">Successivo &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-orders">
                        <p>Non hai ancora venduto nessuna carta.</p>
                        <a href="create_listing.php" class="btn btn-primary">Crea un annuncio</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .orders-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .orders-header {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .tabs {
        display: flex;
        border-bottom: 1px solid #eee;
        margin-bottom: 20px;
    }
    
    .tab {
        padding: 10px 20px;
        margin-right: 10px;
        border-bottom: 3px solid transparent;
        color: #666;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .tab:hover {
        color: #3498db;
    }
    
    .tab.active {
        border-bottom-color: #3498db;
        color: #3498db;
        font-weight: bold;
    }
    
    .orders-list {
        overflow-x: auto;
    }
    
    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .orders-table th,
    .orders-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .orders-table th {
        background-color: #f9f9f9;
        font-weight: bold;
    }
    
    .orders-table tr:hover {
        background-color: #f5f5f5;
    }
    
    .price {
        font-weight: bold;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
    }
    
    .status-pending {
        background-color: #FFF3CD;
        color: #856404;
    }
    
    .status-processing {
        background-color: #D1ECF1;
        color: #0C5460;
    }
    
    .status-shipped {
        background-color: #D4EDDA;
        color: #155724;
    }
    
    .status-delivered {
        background-color: #D4EDDA;
        color: #155724;
    }
    
    .status-cancelled {
        background-color: #F8D7DA;
        color: #721C24;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 0.8em;
    }
    
    .no-orders {
        text-align: center;
        padding: 50px;
        background-color: #f9f9f9;
        border-radius: 8px;
    }
    
    .no-orders p {
        margin-bottom: 20px;
        color: #666;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    
    .page-link {
        display: inline-block;
        padding: 5px 10px;
        margin: 0 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
        color: #333;
        text-decoration: none;
    }
    
    .page-link.active {
        background-color: #3498db;
        color: white;
        border-color: #3498db;
    }
    
    .page-link:hover:not(.active) {
        background-color: #f5f5f5;
    }
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>