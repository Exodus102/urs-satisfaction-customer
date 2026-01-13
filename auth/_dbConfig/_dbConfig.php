<?php
// _dbConfig.php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', '');     // Default XAMPP password
define('DB_NAME', 'db_css');   // I'll suggest this name

// Define the base URL path for the admin application's uploads.
// IMPORTANT: Adjust this path if your admin folder has a different name or is in a different location.
// This assumes the admin application is in a folder named 'urs-satisfaction'
// at the web server's root (e.g., htdocs), making it accessible via '/urs-satisfaction/'.
// If uploaded to the web, change this to the full URL (e.g., 'https://yourdomain.com/admin/') or the correct relative path.

//define('ADMIN_BASE_PATH', 'http://isocss-admin.urs.edu.ph/');
define('ADMIN_BASE_PATH', '/urs-satisfaction/');

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("ERROR: Could not connect. " . $conn->connect_error);
}
