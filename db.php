<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================================
   DATABASE CONFIG
   ---------------------------------------------------------------------
   ALUTH CLIENT kenekuta system eka denna kotasa:
   Meka 4 line witharak edit karanna. Hosting control panel eken
   (InfinityFree > MySQL Databases) labena details tika danna.

   - Local PC testing (localhost) -> SQLite auto mode (mekedi edit karanna epa)
   ===================================================================== */

$DB_HOST = 'sql105.infinityfree.com';      // MySQL Host Name
$DB_NAME = 'if0_43000401_leavesystem';     // Database Name
$DB_USER = 'if0_43000401';                 // Database Username
$DB_PASS = 'keshara2005';              // Database Password

/* Register page (Create Account) eken ADMIN account hadanna one nam
   me key eka danna one. Me key eka JAHA AYARU KARANNA EPAA.
   Client keneku ta denna kotasa meka alut password ekakata maru karanna! */
$ADMIN_REG_KEY = 'AKK-ADMIN-2026';

/* Create Account tab ekama ATHULATA YANNA one access koduwa
   (kauruhariyeku hama account hadanne nathi ve security nisa).
   Company staff atata witharak me koduwa denna. */
$REGISTER_ACCESS_CODE = 'AKK2026';

$host = strtolower($_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = ($host === ''
    || $host === 'localhost'
    || strpos($host, '127.') === 0
    || strpos($host, '192.168.') === 0
    || strpos($host, '10.') === 0);

if ($isLocal || PHP_SAPI === 'cli') {

    /* ---------- LOCAL : SQLite ---------- */
    $dbPath = __DIR__ . '/leave_system.db';
    $conn = new PDO('sqlite:' . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("PRAGMA journal_mode=WAL");
    $conn->exec("PRAGMA foreign_keys=ON");

    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT,
        password TEXT,
        username TEXT UNIQUE,
        shift_type TEXT NOT NULL DEFAULT 'M',
        position TEXT,
        emp_number TEXT
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        email TEXT,
        password TEXT NOT NULL,
        position TEXT,
        emp_number TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        leave_date DATE NOT NULL,
        shift_applied TEXT NOT NULL,
        is_emergency INTEGER DEFAULT 0,
        reason TEXT,
        status TEXT NOT NULL DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id TEXT,
        user_name TEXT,
        user_role TEXT,
        ip TEXT,
        username TEXT,
        action TEXT NOT NULL,
        details TEXT
    )");

    $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_type TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        token TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used INTEGER DEFAULT 0
    )");

} else {

    /* ---------- WEB SERVER : MySQL ---------- */
    try {
        $conn = new PDO(
            "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
            $DB_USER,
            $DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('<h3>Database Connection Failed</h3><p>MySQL details check karanna (db.php).<br>Error: '
            . htmlspecialchars($e->getMessage()) . '</p>');
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) DEFAULT NULL,
        username VARCHAR(50) DEFAULT NULL,
        shift_type VARCHAR(5) NOT NULL DEFAULT 'M',
        position VARCHAR(100) DEFAULT NULL,
        emp_number VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_users_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        password VARCHAR(255) NOT NULL,
        position VARCHAR(100) DEFAULT NULL,
        emp_number VARCHAR(50) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_admins_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        leave_date DATE NOT NULL,
        shift_applied VARCHAR(5) NOT NULL,
        is_emergency TINYINT(1) DEFAULT 0,
        reason TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* =====================================================================
   APP SETTINGS + SYSTEM LOGS
   ---------------------------------------------------------------------
   - app_settings : admin panel eken venas karana puluwan codes tika
                    (admin_reg_key - Admin account hadanna one key eka)
   - system_logs  : okkoma activity logs DATABASE EKE save wenawa
                    (file ekaka nemei - web saha exe dekemata okkoma)
   Meka empty nam db.php wala thiyena default values use wenawa.
   ===================================================================== */

if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $conn->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->exec("CREATE TABLE IF NOT EXISTS system_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id VARCHAR(20) DEFAULT NULL,
        user_name VARCHAR(100) DEFAULT NULL,
        user_role VARCHAR(20) DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        username VARCHAR(50) DEFAULT NULL,
        action VARCHAR(30) NOT NULL,
        details TEXT,
        PRIMARY KEY (id),
        KEY idx_logs_time (log_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_type VARCHAR(10) NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        token VARCHAR(64) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    $conn->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");
}

/* =====================================================================
   position (job title) column - purana DB walata migration.
   Column eka thawam thiyenam error eka ignore karanawa.
   ===================================================================== */
if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    try { $conn->exec("ALTER TABLE `users` ADD COLUMN position VARCHAR(100) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE `admin_users` ADD COLUMN position VARCHAR(100) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE `users` ADD COLUMN emp_number VARCHAR(50) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE `admin_users` ADD COLUMN emp_number VARCHAR(50) DEFAULT NULL"); } catch (PDOException $e) {}
} else {
    try { $conn->exec("ALTER TABLE users ADD COLUMN position TEXT"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE admin_users ADD COLUMN position TEXT"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE users ADD COLUMN emp_number TEXT"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE admin_users ADD COLUMN emp_number TEXT"); } catch (PDOException $e) {}
}

function getSetting($conn, $key, $default) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :k");
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return ($v === false || $v === null || $v === '') ? $default : $v;
    } catch (PDOException $e) {
        return $default;
    }
}

function setSetting($conn, $key, $value) {
    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
                                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    } else {
        $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
                                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
    }
    $stmt->execute([':k' => $key, ':v' => $value]);
}

function getAccessCodes($conn) {
    return [
        'register_code' => getSetting($conn, 'register_access_code', $GLOBALS['REGISTER_ACCESS_CODE']),
        'admin_key'     => getSetting($conn, 'admin_reg_key', $GLOBALS['ADMIN_REG_KEY']),
    ];
}

/* =====================================================================
   ENGINE / BUSINESS RULE DEFAULTS -> app_settings eke seed karanawa
   (meka admin panel eken aluthin aluthin change karanna puluwan)
   ===================================================================== */
$engineDefaults = [
    'max_monthly_leaves'  => '4',
    'min_morning_staff'   => '5',
    'min_evening_staff'   => '3',
    'use_dependency_rules'=> '0',
];
foreach ($engineDefaults as $k => $v) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key = :k");
    $stmt->execute([':k' => $k]);
    if ((int)$stmt->fetchColumn() === 0) {
        $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)");
        $stmt->execute([':k' => $k, ':v' => $v]);
    }
}

?>
