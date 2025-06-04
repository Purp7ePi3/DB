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
$success_message = '';
$error_message = '';

// Fetch user profile data
$sql_user = "SELECT 
    a.id, a.username, a.email,
    up.first_name, up.last_name, up.country
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $country = trim($_POST['country']);
    
    // Check if user_profile exists
    $check_sql = "SELECT COUNT(*) AS profile_exists FROM user_profiles WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $profile_exists = $check_stmt->get_result()->fetch_assoc()['profile_exists'] > 0;
    
    if ($profile_exists) {
        // Update existing profile
        $sql = "UPDATE user_profiles SET 
                first_name = ?, last_name = ?, country = ? 
                WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Errore nella preparazione della query update: " . $conn->error . "<br>SQL: " . $sql);
        }
        $stmt->bind_param("sssi", $first_name, $last_name, $country, $user_id);
    } else {
        // Create new profile
        $sql = "INSERT INTO user_profiles (user_id, first_name, last_name, country, rating) 
                VALUES (?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Errore nella preparazione della query insert: " . $conn->error . "<br>SQL: " . $sql);
        }
        $stmt->bind_param("isss", $user_id, $first_name, $last_name, $country);
    }
    
    if ($stmt->execute()) {
        $_SESSION['profile_success'] = "Profilo aggiornato con successo!";
        header("Location: profile.php");
        exit;
    } else {
        $error_message = "Errore durante l'aggiornamento del profilo: " . $conn->error;
    }
}

// Include header
include __DIR__ . '/partials/header.php';
?>

<div class="edit-profile-container">
    <div class="profile-header">
        <h1>Modifica Profilo</h1>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-content">
        <form method="POST" action="" class="edit-profile-form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="form-control">
                <small class="form-text text-muted">L'username non può essere modificato.</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled class="form-control">
                <small class="form-text text-muted">Per modificare l'email, contatta l'assistenza.</small>
            </div>
            
            <div class="form-group">
                <label for="first_name">Nome:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="last_name">Cognome:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="country">Paese:</label>
                <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="bio">Bio:</label>
                <textarea id="bio" name="bio" rows="5" class="form-control"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                <small class="form-text text-muted">Racconta qualcosa su di te e sui tuoi interessi collezionistici.</small>
            </div>
            
            <div class="form-actions">
                <a href="profile.php" class="btn btn-secondary">Annulla</a>
                <button type="submit" class="btn btn-primary">Salva Modifiche</button>
            </div>
        </form>
    </div>
</div>

<style>
.edit-profile-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.profile-header {
    margin-bottom: 20px;
    text-align: center;
}

.edit-profile-form {
    background-color: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

textarea.form-control {
    resize: vertical;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 14px;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>