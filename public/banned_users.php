<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../config/config.php';
$base_url = "/DataBase";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin (usando account_type_id, assumendo 1 = admin)
$sql_check_admin = "SELECT account_type_id FROM accounts WHERE id = ?";
$stmt_admin = $conn->prepare($sql_check_admin);
$stmt_admin->bind_param("i", $user_id);
$stmt_admin->execute();
$admin_result = $stmt_admin->get_result();
$account_type = $admin_result->fetch_assoc()['account_type_id'] ?? 0;
$is_admin = ($account_type == 1); // Assumendo che account_type_id = 1 sia admin

if (!$is_admin) {
    header("Location: profile.php");
    exit;
}

// Handle ban/unban actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = "AND (a.username LIKE ? OR a.email LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ?)";
    $search_param = "%$search%";
}

// Get banned users (is_active = 0) with pagination
$sql_banned = "SELECT 
    a.id, a.username, a.email, a.created_at, a.is_active,
    up.first_name, up.last_name, up.rating,
    COUNT(DISTINCT l.id) as active_listings,
    COUNT(DISTINCT oi.id) as total_sales
    FROM accounts a
    LEFT JOIN user_profiles up ON a.id = up.user_id
    LEFT JOIN listings l ON a.id = l.seller_id AND l.is_active = TRUE
    LEFT JOIN listings l2 ON a.id = l2.seller_id
    LEFT JOIN order_items oi ON l2.id = oi.listing_id
    WHERE a.is_active = 0 AND a.account_type_id != 1 $search_condition
    GROUP BY a.id, a.username, a.email, a.created_at, a.is_active, up.first_name, up.last_name, up.rating
    ORDER BY a.created_at DESC
    LIMIT ?, ?";

$stmt = $conn->prepare($sql_banned);

if (!empty($search)) {
    $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $offset, $per_page);
} else {
    $stmt->bind_param("ii", $offset, $per_page);
}

$stmt->execute();
$result_banned = $stmt->get_result();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM accounts a 
              LEFT JOIN user_profiles up ON a.id = up.user_id 
              WHERE a.is_active = 0 AND a.account_type_id != 1 $search_condition";

$stmt_count = $conn->prepare($count_sql);

if (!empty($search)) {
    $stmt_count->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
} else {
    // No parameters needed for count without search
}

$stmt_count->execute();
$total_banned = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_banned / $per_page);

// Get all active users (not banned) for quick ban access
$sql_all_users = "SELECT 
    a.id, a.username, a.email, a.created_at,
    up.first_name, up.last_name, up.rating
    FROM accounts a
    LEFT JOIN user_profiles up ON a.id = up.user_id
    WHERE a.is_active = 1 AND a.id != ? AND a.account_type_id != 1
    ORDER BY a.username ASC
    LIMIT 100";

$stmt_all = $conn->prepare($sql_all_users);
$stmt_all->bind_param("i", $user_id);
$stmt_all->execute();
$result_all_users = $stmt_all->get_result();

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="banned-users-container">
    <div class="page-header">
        <h1><i class="fas fa-users-slash"></i> Gestione Utenti Bannati</h1>
        <p>Pannello amministrativo per la gestione degli utenti bannati</p>
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
    
    <div class="admin-sections">
        <!-- Search and Actions Section -->
        <div class="actions-section">
            <div class="search-form">
                <form method="GET" action="">
                    <div class="search-input-group">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Cerca per username, email o nome..." class="search-input">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Cerca
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Quick Ban Section -->
            <div class="quick-ban-section">
                <h3><i class="fas fa-user-slash"></i> Banna Utente</h3>
                <form method="POST" onsubmit="return confirm('Sei sicuro di voler bannare questo utente? Tutti i suoi annunci verranno disattivati.');">
                    <div class="quick-ban-form">
                        <select name="target_user_id" required class="user-select">
                            <option value="">Seleziona un utente da bannare...</option>
                            <?php while ($user_option = $result_all_users->fetch_assoc()): ?>
                                <option value="<?php echo $user_option['id']; ?>">
                                    <?php echo htmlspecialchars($user_option['username']); ?> 
                                    (<?php echo htmlspecialchars($user_option['email']); ?>)
                                    <?php if (!empty($user_option['first_name']) || !empty($user_option['last_name'])): ?>
                                        - <?php echo htmlspecialchars(trim($user_option['first_name'] . ' ' . $user_option['last_name'])); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="ban_user" class="btn btn-danger">
                            <i class="fas fa-user-slash"></i> Banna
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Banned Users List -->
        <div class="banned-users-section">
            <h2>
                <i class="fas fa-list"></i> Utenti Bannati 
                <span class="count">(<?php echo $total_banned; ?> totali)</span>
            </h2>
            
            <?php if ($result_banned->num_rows > 0): ?>
                <div class="users-table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Utente</th>
                                <th>Email</th>
                                <th>Nome Completo</th>
                                <th>Rating</th>
                                <th>Data Registrazione</th>
                                <th>Annunci Attivi</th>
                                <th>Vendite Totali</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($banned_user = $result_banned->fetch_assoc()): ?>
                                <tr>
                                    <td class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($banned_user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo htmlspecialchars($banned_user['username']); ?></strong>
                                            <span class="banned-badge">BANNATO</span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($banned_user['email']); ?></td>
                                    <td>
                                        <?php 
                                        $full_name = trim($banned_user['first_name'] . ' ' . $banned_user['last_name']);
                                        echo !empty($full_name) ? htmlspecialchars($full_name) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="rating">
                                            <?php 
                                            $rating = (float)$banned_user['rating'];
                                            echo str_repeat('★', round($rating)) . str_repeat('☆', 5 - round($rating)); 
                                            ?>
                                            <span><?php echo number_format($rating, 1); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($banned_user['created_at'])); ?></td>
                                    <td>
                                        <span class="count-badge <?php echo $banned_user['active_listings'] > 0 ? 'warning' : ''; ?>">
                                            <?php echo $banned_user['active_listings']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="count-badge">
                                            <?php echo $banned_user['total_sales']; ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Sei sicuro di voler sbannare questo utente?');">
                                            <input type="hidden" name="target_user_id" value="<?php echo $banned_user['id']; ?>">
                                            <button type="submit" name="unban_user" class="btn btn-success btn-sm">
                                                <i class="fas fa-user-check"></i> Sbanna
                                            </button>
                                        </form>
                                        <a href="profile.php?user_id=<?php echo $banned_user['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-user"></i> Profilo
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">&laquo; Precedente</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="page-link">Successivo &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-banned-users">
                    <div class="empty-state">
                        <i class="fas fa-users-check"></i>
                        <h3>Nessun utente bannato</h3>
                        <?php if (!empty($search)): ?>
                            <p>Nessun utente bannato trovato per la ricerca "<?php echo htmlspecialchars($search); ?>"</p>
                            <a href="?" class="btn btn-primary">Visualizza tutti gli utenti bannati</a>
                        <?php else: ?>
                            <p>Non ci sono utenti attualmente bannati nel sistema.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.banned-users-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.page-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.page-header h1 {
    margin: 0 0 10px 0;
    font-size: 2em;
}

.page-header p {
    margin: 0;
    opacity: 0.9;
}

.admin-sections {
    display: grid;
    gap: 30px;
}

.actions-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.search-form h3, .quick-ban-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
}

.search-input-group {
    display: flex;
    gap: 10px;
}

.search-input {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
}

.quick-ban-form {
    display: flex;
    gap: 10px;
    align-items: stretch;
}

.user-select {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    background: white;
}

.banned-users-section {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.banned-users-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #eee;
    padding-bottom: 15px;
}

.count {
    color: #666;
    font-weight: normal;
    font-size: 0.8em;
}

.users-table-container {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.users-table th,
.users-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.users-table th {
    background-color: #f8f9fa;
    font-weight: bold;
    color: #555;
    border-bottom: 2px solid #dee2e6;
}

.users-table tr:hover {
    background-color: #f5f5f5;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.banned-badge {
    background-color: #dc3545;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7em;
    font-weight: bold;
}

.rating {
    display: flex;
    align-items: center;
    gap: 5px;
}

.rating span {
    color: #666;
    font-size: 0.9em;
}

.count-badge {
    display: inline-block;
    padding: 4px 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.9em;
}

.count-badge.warning {
    background-color: #fff3cd;
    color: #856404;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.pagination {
    display: flex;
    justify-content: center;
    margin-top: 30px;
    gap: 5px;
}

.page-link {
    display: inline-block;
    padding: 8px 16px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    color: #007bff;
    text-decoration: none;
    transition: all 0.3s;
}

.page-link:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.page-link.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.no-banned-users {
    padding: 40px;
    text-align: center;
}

.empty-state {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state i {
    font-size: 4em;
    color: #28a745;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 15px;
    color: #333;
}

.empty-state p {
    color: #666;
    margin-bottom: 20px;
}

.alert {
    padding: 15px 20px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-weight: 500;
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

/* Responsive Design */
@media (max-width: 1200px) {
    .actions-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .banned-users-container {
        padding: 15px;
    }
    
    .actions-section {
        padding: 20px;
    }
    
    .search-input-group, .quick-ban-form {
        flex-direction: column;
    }
    
    .users-table {
        font-size: 14px;
    }
    
    .users-table th,
    .users-table td {
        padding: 8px 10px;
    }
    
    .user-info {
        flex-direction: column;
        text-align: center;
    }
    
    .actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.5em;
    }
    
    .users-table-container {
        font-size: 12px;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>