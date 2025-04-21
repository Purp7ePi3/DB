<?
$host = 'localhost';
$user = 'root';
$pass = ''; 
$dbname = 'app_db'; 

// Connect to database
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch tables
$tables = [];
$result = $conn->query("SHOW TABLES");
if (!$result) {
    die("Error fetching tables: " . $conn->error);
}
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Fetch columns
$columns = [];
foreach ($tables as $table) {
    $result = $conn->query("DESCRIBE `$table`");
    if (!$result) {
        die("Error describing table $table: " . $conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $columns[$table][] = $row;
    }
}

// Fetch foreign keys
$fks = [];
$sql = "
    SELECT TABLE_NAME, COLUMN_NAME, 
           REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = '$dbname' AND REFERENCED_TABLE_NAME IS NOT NULL
";
$result = $conn->query($sql);
if (!$result) {
    die("Error fetching foreign keys: " . $conn->error);
}
while ($row = $result->fetch_assoc()) {
    $fks[] = $row;
}

// Start building the ER diagram
echo "erDiagram\n";

// Output tables and their columns with clean formatting
foreach ($columns as $table => $cols) {
    echo "    $table {\n";
    foreach ($cols as $col) {
        // Simplify type display by removing length specifications
        $type = preg_replace('/\(\d+\)/', '', $col['Type']);
        $type = preg_replace('/\(\d+,\d+\)/', '', $type);
        
        // Determine key type
        $key = $col['Key'] == 'PRI' ? 'PK' : ($col['Key'] == 'MUL' ? 'FK' : '');
        
        // Add proper spacing for clean alignment
        echo "        $type $col[Field] $key\n";
    }
    echo "    }\n";
}

// Output relationships with descriptive labels
foreach ($fks as $fk) {
    $from = $fk['TABLE_NAME'];
    $to = $fk['REFERENCED_TABLE_NAME'];
    $fromCol = $fk['COLUMN_NAME'];
    $toCol = $fk['REFERENCED_COLUMN_NAME'];
    
    // Add relationship with column names for clarity
    echo "    $from }|--|| $to : \"$fromCol -> $toCol\"\n";
}


$conn->close();
?>