<?php
require_once '../config/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['listing_id'])) {
    $listing_id = (int)$_POST['listing_id'];
    $user_id = $_SESSION['user_id'];

    // Solo il proprietario può disattivare
    $stmt = $conn->prepare("UPDATE listings SET is_active = 0 WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
}

header("Location: listings.php?tab=active");
exit;