<?php
// Database connection (existing database: volunteer_management)
// XAMPP / Laragon default: user "root", empty password. Change here if yours differs.
date_default_timezone_set('Asia/Manila');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'volunteer_management';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Unable to connect to the database. Please check config/db.php and make sure MySQL is running.');
}
