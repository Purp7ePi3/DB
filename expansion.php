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

// Get the game ID from the URL
$game_id = $_GET['game_id'];

// Query to get the game details by ID
$sql_game = "SELECT display_name FROM games WHERE id = $game_id";
$result_game = $conn->query($sql_game);
$game = $result_game->fetch_assoc();

// Query to get expansions for the specific game
$sql_expansions = "SELECT e.id, e.name FROM expansions e WHERE e.game_id = $game_id";
$result_expansions = $conn->query($sql_expansions);

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expansions for <?php echo $game['display_name']; ?></title>
</head>
<body>
    <h1>Expansions for <?php echo $game['display_name']; ?></h1>

    <div>
        <?php while ($expansion = $result_expansions->fetch_assoc()): ?>
            <div>
                <h3><a href="expansion_details.php?expansion_id=<?php echo $expansion['id']; ?>"><?php echo $expansion['name']; ?></a></h3>
            </div>
        <?php endwhile; ?>
    </div>
    
</body>
</html>
