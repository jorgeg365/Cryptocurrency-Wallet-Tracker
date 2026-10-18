
<?php

// Example database connection
// Copy this file to db.php and enter
// your own local database credentials.

$host = "127.0.0.1";
$port = 3306;
$username = "YOUR_DB_USERNAME";
$password = "YOUR_DB_PASSWORD";
$database = "crypto_wallet_tracker";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

$conn->set_charset("utf8mb4");
