<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('LOG_DIR', __DIR__ . '/logs/');
define('LOG_FILE', LOG_DIR . 'system.log');

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

/* Okkoma logs DATABASE EKE (system_logs table) save wenawa.
   DB write ekaka awul unoth witharak file ekakata fallback wenawa. */
function writeLog($action, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_id = $_SESSION['user_id'] ?? 'N/A';
    $user_name = $_SESSION['user_name'] ?? 'N/A';
    $user_role = $_SESSION['user_role'] ?? 'N/A';
    $username = $_SESSION['user_username'] ?? '';

    global $conn;
    if (isset($conn) && $conn instanceof PDO) {
        try {
            $stmt = $conn->prepare("INSERT INTO system_logs
                (log_time, user_id, user_name, user_role, ip, username, action, details)
                VALUES (:t, :uid, :un, :ur, :ip, :us, :a, :d)");
            $stmt->execute([
                ':t'   => $timestamp,
                ':uid' => (string)$user_id,
                ':un'  => (string)$user_name,
                ':ur'  => (string)$user_role,
                ':ip'  => (string)$ip,
                ':us'  => (string)$username,
                ':a'   => substr((string)$action, 0, 30),
                ':d'   => (string)$details,
            ]);
            return;
        } catch (PDOException $e) {
            /* fall through to file log */
        }
    }

    $log_line = sprintf(
        "[%s] | %-12s | ID:%-4s | %-15s | %-8s | IP:%-15s | @%-10s | %s\n",
        $timestamp,
        $action,
        $user_id,
        $user_name,
        $user_role,
        $ip,
        $username,
        $details
    );
    @file_put_contents(LOG_FILE, $log_line, FILE_APPEND | LOCK_EX);
}

function getLogsForUser($conn, $userId, $limit = 500) {
    try {
        $limit = (int)$limit;
        $stmt = $conn->prepare("SELECT * FROM system_logs WHERE user_id = :uid ORDER BY id DESC LIMIT " . $limit);
        $stmt->execute([':uid' => (string)$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllLogs($conn, $limit = 1000) {
    try {
        $limit = (int)$limit;
        $stmt = $conn->prepare("SELECT * FROM system_logs ORDER BY id DESC LIMIT " . $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLogFilePath() {
    return LOG_FILE;
}
?>
