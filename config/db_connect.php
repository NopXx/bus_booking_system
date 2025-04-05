<?php
$host = 'localhost';
$dbname = 'bus_booking';
$username = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// Set character set to utf8
$conn->set_charset("utf8");
?>