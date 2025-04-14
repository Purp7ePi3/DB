<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// If user is already logged in, redirect to home page
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Include database configuration
require_once '../config/config.php';

$error_message = '';
$success_message = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted = isset($_POST['terms']) ? true : false;
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Per favore, completa tutti i campi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Per favore, inserisci un indirizzo email valido.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Le password non corrispondono.";
    } elseif (strlen($password) < 8) {
        $error_message = "La password deve essere di almeno 8 caratteri.";
    } elseif (!$terms_accepted) {
        $error_message = "Devi accettare i termini e le condizioni per registrarti.";
    } else {
        // Check if email already exists
        $sql_check = "SELECT id FROM accounts WHERE email = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Esiste già un account con questa email.";
        } else {
            // Check if username is already taken
            $sql_check = "SELECT id FROM accounts WHERE username = ?";
            $stmt = $conn->prepare($sql_check);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Il nome utente è già in uso. Per favore scegline un altro.";
            } else {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert user account
                    $sql_insert = "INSERT INTO accounts (username, email, password_hash, created_at, is_active) 
                                   VALUES (?, ?, ?, NOW(), TRUE)";
                    $stmt = $conn->prepare($sql_insert);
                    $stmt->bind_param("sss", $username, $email, $password_hash);
                    $stmt->execute();
                    
                    $user_id = $conn->insert_id;
                    
                    // Create user profile
                    $sql_profile = "INSERT INTO user_profiles (user_id, rating, bio, location, 
                                   registered_date, last_login) VALUES (?, 0, '', '', NOW(), NOW())";
                    $stmt = $conn->prepare($sql_profile);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message and redirect to login
                    $success_message = "Registrazione completata con successo! Ora puoi accedere.";
                    
                    // Optional: Auto-login after registration
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    
                    // Redirect to home page after short delay (to show success message)
                    header("refresh:2;url=index.php");
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error_message = "Errore durante la registrazione: " . $e->getMessage();
                }
            }
        }
    }
}

// Include header
include '../public/partials/header.php';
?>

<section class="auth-container">
    <div class="auth-box">
        <h1>Crea il tuo account</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <form action="register.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">Nome utente</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="30">
                <small>Il nome sarà visibile agli altri utenti</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
                <small>Utilizzeremo questa email per le comunicazioni</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
                <small>Minimo 8 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Conferma password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <div class="form-options">
                <div class="terms-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">Ho letto e accetto i <a href="terms.php" target="_blank">Termini e Condizioni</a> e la <a href="privacy.php" target="_blank">Privacy Policy</a></label>
                </div>
            </div>
            
            <button type="submit" class="btn-primary btn-full">Registrati</button>
        </form>
        
        <!-- <div class="auth-separator">
            <span>oppure</span>
        </div>
        
        <div class="social-login">
            <a href="oauth/google.php" class="btn-social btn-google">
                <i class="fab fa-google"></i> Registrati con Google
            </a>
            <a href="oauth/facebook.php" class="btn-social btn-facebook">
                <i class="fab fa-facebook-f"></i> Registrati con Facebook
            </a>
        </div> -->
        
        <div class="auth-link">
            Hai già un account? <a href="login.php">Accedi</a>
        </div>
    </div>
</section>

<?php
// Include footer
include '../public/partials/footer.php';
?>