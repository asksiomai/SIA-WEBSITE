
<?php
date_default_timezone_set('Asia/Manila');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'volufjvu_cvms_db';
const DB_USER = 'volufjvu_cvmsuser';
const DB_PASS = 'Bombaclat123.';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('CVMS database error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the cPanel error log.');
}
