<?php
declare(strict_types=1);
// Change only if you install the folder under a different htdocs name.
const BASE_URL = '/TourismWeb';
// Update these values only if your local XAMPP MySQL configuration differs.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'tourism_db';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection unavailable. Check config/database.php and import database/tourism_db.sql.');
    }
}
