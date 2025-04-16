<?php
$host = "localhost";
$user = "root";
$password = ""; 
$database = "app_db";

$mysqli = new mysqli($host, $user, $password, $database);

// Controllo connessione
if ($mysqli->connect_error) {
    die("Connessione fallita: " . $mysqli->connect_error);
}

// Ottieni tutte le tabelle
$tables_result = $mysqli->query("SHOW TABLES");

if ($tables_result) {
    while ($table_row = $tables_result->fetch_array()) {
        $table_name = $table_row[0];
        echo "<h2>Tabella: $table_name</h2>";

        // Ottieni descrizione della tabella
        $describe_result = $mysqli->query("DESCRIBE $table_name");

        if ($describe_result) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chiave</th><th>Default</th><th>Extra</th></tr>";

            while ($column = $describe_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }

            echo "</table><br>";
        } else {
            echo "Errore nel descrivere la tabella $table_name: " . $mysqli->error . "<br>";
        }
    }
} else {
    echo "Errore nel recuperare le tabelle: " . $mysqli->error;
}

$mysqli->close();
?>
