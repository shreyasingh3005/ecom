<?php
// includes/db.php
// Secure PDO Database Connection Manager

require_once __DIR__ . '/env.php';

if (!function_exists('getDB')) {
    function getDB() {
        static $pdo = null;

        if ($pdo === null) {
            $host = env('DB_HOST', 'localhost');
            $port = env('DB_PORT', '3306');
            $database = env('DB_DATABASE', 'u424679052_jelly');
            $username = env('DB_USERNAME', 'u424679052_jelly12');
            $password = env('DB_PASSWORD', '3!sY!3;Sy?k~');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                $pdo = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // If database doesn't exist on server, try creating it
                if ($e->getCode() == 1049) {
                    try {
                        $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                        $serverPdo = new PDO($serverDsn, $username, $password, $options);
                        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo = new PDO($dsn, $username, $password, $options);
                    } catch (PDOException $createEx) {
                        // pass to fallback below
                    }
                }

                // If still not connected and host is localhost, try local dev defaults (root without password on ecom_db)
                if ($pdo === null && ($host === 'localhost' || $host === '127.0.0.1')) {
                    try {
                        $localDsn = "mysql:host={$host};port={$port};dbname=ecom_db;charset=utf8mb4";
                        $pdo = new PDO($localDsn, 'root', '', $options);
                    } catch (PDOException $localEx) {
                        try {
                            // Try connecting to default database on localhost
                            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", 'root', '', $options);
                        } catch (PDOException $localEx2) {
                            // Handled below
                        }
                    }
                }

                if ($pdo === null) {
                    if (env('APP_DEBUG', false)) {
                        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
                    } else {
                        die("Database connection failed. Please contact administrator.");
                    }
                }
            }
        }

        return $pdo;
    }
}

if (!class_exists('Database')) {
    class Database {
        public static function getInstance(): PDO {
            return getDB();
        }
    }
}
