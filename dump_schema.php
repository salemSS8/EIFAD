<?php

$host = 'mainline.proxy.rlwy.net';
$port = '57589';
$db   = 'railway';
$user = 'root';
$pass = 'PZfXhHYchRfvdmYxKKhhdBAepxARvlIC';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Tables in database:\n";
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    echo "\n--- Schema for table: $table ---\n";
    $result = $conn->query("DESCRIBE `$table`");
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }

    echo "--- Foreign Keys for table: $table ---\n";
    // Get Foreign Keys
    $fkQuery = "
        SELECT 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            TABLE_SCHEMA = '$db' AND 
            TABLE_NAME = '$table' AND 
            REFERENCED_TABLE_NAME IS NOT NULL;
    ";
    $fkResult = $conn->query($fkQuery);
    while ($fkRow = $fkResult->fetch_assoc()) {
        echo json_encode($fkRow) . "\n";
    }
}

$conn->close();
