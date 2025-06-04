<?php

require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$game_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$game_id) {
    echo "<p>Gioco non specificato.</p>";
    exit;
}

$sql_getGame = "SELECT 
                    sc.blueprint_id,
                    sc.name_en, 
                    sc.image_url,
                    e.name AS expansion_name,
                    sc.collector_number,
                    g.display_name AS game_name
                FROM single_cards sc
                JOIN expansions e ON sc.expansion_id = e.id
                JOIN games g ON e.game_id = g.id
                WHERE g.id = ?
                ORDER BY sc.name_en asc
                limit 30";

$stmt = $conn->prepare($sql_getGame);
$stmt->bind_param("i", $game_id);
$stmt->execute();
$result_latest = $stmt->get_result();

include __DIR__ . '/partials/header.php';
?>

<section class="latest-listings">
    <?php
    if ($result_latest->num_rows > 0) {
        $firstCard = $result_latest->fetch_assoc();
    ?>
        <h2><?php echo htmlspecialchars($firstCard["game_name"]); ?></h2>
        <div class="cards-grid">
            <?php
            $shown = [];
            $card = $firstCard;
            do {
                if (in_array($card["collector_number"], $shown)) continue;
                $shown[] = $card["collector_number"];
            ?>
                <div class="card-item">
                    <a href="cards.php?id=<?php echo $card["blueprint_id"]; ?>">
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
                        </div>
                    </a>
                </div>
            <?php
            } while ($card = $result_latest->fetch_assoc());
            ?>
        </div>
    <?php
    } else {
        echo "<p>Nessun annuncio disponibile al momento.</p>";
    }
    ?>
</section>


<?php include __DIR__ . '/partials/footer.php'; ?>