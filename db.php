<?php
$host   = 'localhost';
$dbname = 'IsraelStateBank';
$user   = 'root';
$pass   = '';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;background:#0d1117;color:#f85149;padding:40px;text-align:center;">
        <h2>Database Connection Failed</h2>
        <p>' . htmlspecialchars($conn->connect_error) . '</p>
        <p>Make sure XAMPP MySQL is running and you have run <strong>setup.sql</strong></p>
    </div>');
}

$conn->set_charset('utf8mb4');
