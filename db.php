<?php
// db.php

// New database connection details
$host = 'mysql-30f3d557-acemovers-dd24.b.aivencloud.com';
$port = 26656; // Your Aiven MySQL port
$dbUser = 'avnadmin';
$dbPass = 'AVNS_NU9ZIgbnh6Rrvc7ThrU'; // Change to your actual database password
$dbName = 'defaultdb';

// Path to your ca.pem file
$ssl_ca = './ca.pem';

// Create a new mysqli instance and enable SSL
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL); // Set SSL options
mysqli_real_connect($conn, $host, $dbUser, $dbPass, $dbName, $port, NULL, MYSQLI_CLIENT_SSL);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connection successful";
}

?>
