<?php
// Configurazione del database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "app_db";

// Creazione della connessione
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica della connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Impostazione charset
$conn->set_charset("utf8mb4");
?>