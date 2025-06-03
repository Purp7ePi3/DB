<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
include __DIR__ . '/partials/header.php';
?>

<div class="order-confirmation-container">
    <h1>Ordine completato!</h1>
    <p>Grazie per il tuo acquisto.</p>
    <?php if ($order_id): ?>
        <p>Il tuo numero d'ordine è <strong>#<?php echo htmlspecialchars($order_id); ?></strong>.</p>
    <?php endif; ?>
    <a href="orders.php" class="btn btn-primary">Vai ai miei ordini</a>
    <a href="marketplace.php" class="btn btn-secondary">Torna al marketplace</a>
</div>

<style>
.order-confirmation-container {
    max-width: 600px;
    margin: 40px auto;
    background: #fff;
    padding: 40px 30px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.order-confirmation-container h1 {
    color: #28a745;
    margin-bottom: 20px;
}
.order-confirmation-container .btn {
    margin: 15px 10px 0 10px;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>