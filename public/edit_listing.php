<?php
require_once '../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Recupera i dati dell'annuncio
$stmt = $conn->prepare("SELECT l.*, sc.name_en, sc.image_url, cc.id as condition_id, cr.id as rarity_id
                        FROM listings l
                        LEFT JOIN single_cards sc ON l.single_card_id = sc.blueprint_id
                        LEFT JOIN card_conditions cc ON l.condition_id = cc.id
                        LEFT JOIN card_rarities cr ON sc.rarity_id = cr.id
                        WHERE l.id = ? AND l.seller_id = ?");
$stmt->bind_param("ii", $listing_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<p>Annuncio non trovato o non autorizzato.</p>";
    exit;
}
$listing = $result->fetch_assoc();

// Aggiorna annuncio se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = floatval(str_replace(',', '.', $_POST['price']));
    $quantity = intval($_POST['quantity']);
    $description = trim($_POST['description']);
    $condition_id = intval($_POST['condition_id']);
    $rarity_id = intval($_POST['rarity_id']);

    $stmt = $conn->prepare("UPDATE listings SET price = ?, quantity = ?, description = ?, condition_id = ? WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("disiii", $price, $quantity, $description, $condition_id, $listing_id, $user_id);
    $stmt->execute();

    // Aggiorna anche la rarità della carta se cambiata (opzionale)
    $stmt = $conn->prepare("UPDATE single_cards SET rarity_id = ? WHERE blueprint_id = ?");
    $stmt->bind_param("ii", $rarity_id, $listing['single_card_id']);
    $stmt->execute();

    header("Location: listings.php?tab=active");
    exit;
}

// Recupera condizioni e rarità disponibili
$conditions = $conn->query("SELECT id, condition_name FROM card_conditions ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$rarities = $conn->query("SELECT id, rarity_name FROM card_rarities ORDER BY rarity_name ASC")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/partials/header.php';
?>

<div class="edit-listing-container" style="max-width:500px;margin:2rem auto;">
    <h2>Modifica Annuncio</h2>
    <form method="post">
        <div style="margin-bottom:1rem;">
            <strong><?php echo htmlspecialchars($listing['name_en']); ?></strong><br>
            <?php if ($listing['image_url']): ?>
                <img src="https://www.cardtrader.com<?php echo htmlspecialchars($listing['image_url']); ?>" alt="" style="max-width:120px;">
            <?php endif; ?>
        </div>
        <label>Prezzo (€):</label>
        <input type="number" step="0.01" min="0" name="price" value="<?php echo htmlspecialchars($listing['price']); ?>" required class="form-control"><br>

        <label>Quantità:</label>
        <input type="number" min="1" name="quantity" value="<?php echo htmlspecialchars($listing['quantity']); ?>" required class="form-control"><br>

        <label>Condizione:</label>
        <select name="condition_id" class="form-control" required>
            <?php foreach ($conditions as $cond): ?>
                <option value="<?php echo $cond['id']; ?>" <?php if ($cond['id'] == $listing['condition_id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($cond['condition_name']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>Rarità:</label>
        <select name="rarity_id" class="form-control" required>
            <?php foreach ($rarities as $rar): ?>
                <option value="<?php echo $rar['id']; ?>" <?php if ($rar['id'] == $listing['rarity_id']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($rar['rarity_name']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label>Descrizione:</label>
        <textarea name="description" rows="3" class="form-control"><?php echo htmlspecialchars($listing['description']); ?></textarea><br>

        <button type="submit" class="btn btn-primary">Salva modifiche</button>
        <a href="listings.php?tab=active" class="btn btn-secondary">Annulla</a>
    </form>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>