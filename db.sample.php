<?php
// db.sample.php - Rename this to db.php and fill in your credentials
// DO NOT commit the actual db.php with passwords to GitHub!

$is_localhost = (php_sapi_name() === 'cli' || ($_SERVER['HTTP_HOST'] ?? '') == 'localhost' || ($_SERVER['HTTP_HOST'] ?? '') == '127.0.0.1');

if ($is_localhost) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "sales_db";
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    $servername = "your_live_host";
    $username = "your_username";
    $password = "your_password";
    $dbname = "your_database";
    error_reporting(0);
    ini_set('display_errors', 0);
}

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    if ($is_localhost) {
        die("Connection failed: " . mysqli_connect_error());
    } else {
        die("Service temporarily unavailable.");
    }
}
?>
