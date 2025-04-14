<?php
// Database configuration
$servername = "localhost";
$username = "root";  // Default XAMPP MySQL user
$password = "";  // Default XAMPP MySQL password (empty)
$dbname = "app_db";  // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the expansion ID from the URL
$expansion_id = $_GET['expansion_id'];

// Query to get the expansion details by ID
$sql_expansion = "SELECT name FROM expansions WHERE id = $expansion_id";
$result_expansion = $conn->query($sql_expansion);
$expansion = $result_expansion->fetch_assoc();

// Query to get the cards for the expansion
$sql_cards = "SELECT c.name_en, c.image_url FROM single_cards c WHERE c.expansion_id = $expansion_id";
$result_cards = $conn->query($sql_cards);

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cards in Expansion: <?php echo $expansion['name']; ?></title>
</head>
<body>
    <h1>Cards in Expansion: <?php echo $expansion['name']; ?></h1>

    <div>
        <?php while ($card = $result_cards->fetch_assoc()): ?>
            <div>
                <img src="http://www.cardtrader.com/<?php echo $card['image_url']; ?>" alt="<?php echo $card['name_en']; ?>">
                <p><?php echo $card['name_en']; ?></p>
            </div>
        <?php endwhile; ?>
    </div>
    
</body>
</html>
