<?php
/* =====================================================================
   AKK Leave System - Quick Account Setup (ONE TIME)
   ---------------------------------------------------------------------
   Web (InfinityFree) eken account eka create karanna me script eka
   upload karala me URL eka open karanna:

       https://akksolutions.wuaze.com/setup_account_AKK.php

   NOTE: Script eka walatyath create wela thiyenawa nam meka execute
   wena ekai. Account create wunama script eka AUTO-DELETE wenawa
   (self-delete) - security nisa.
   ===================================================================== */

session_start();

$AKK_ADMIN_KEY = 'AKK-ADMIN-2026';

if (PHP_SAPI === 'cli' || (defined('STDIN'))) {
    $inputKey = $argv[1] ?? '';
} else {
    $inputKey = trim($_POST['key'] ?? '');
}

if ($inputKey === '') {
    if (PHP_SAPI === 'cli' || (defined('STDIN'))) {
        fwrite(STDOUT, "Usage: php setup_account_AKK.php <ADMIN_KEY>\n");
        exit(1);
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Account Setup</title></head>
<body style="font-family:Segoe UI,sans-serif;background:#0f172a;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;margin:0">
<form method="POST" style="background:#1e293b;padding:32px;border-radius:16px;text-align:center">
<h2 style="margin:0 0 16px">AKK Account Setup</h2>
<input type="password" name="key" placeholder="Admin Key eka danna" required
  style="padding:12px 16px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#fff;width:100%;box-sizing:border-box">
<br><br>
<button style="background:#6366f1;color:#fff;border:none;padding:12px 28px;border-radius:8px;cursor:pointer;font-weight:600">Run</button>
</form>
</body></html>
HTML;
    exit(0);
}

if (!hash_equals($AKK_ADMIN_KEY, $inputKey)) {
    echo '<pre style="color:#f87171;background:#0f172a;padding:20px">Authentication FAILED - veradi key eka.</pre>';
    exit(1);
}

$NAME      = 'AKK Solutions';
$USERNAME  = 'admin';
$PASSWORD  = 'Admin@2026';
$SHIFT     = 'BOTH';

include __DIR__ . '/db.php';

$hash = password_hash($PASSWORD, PASSWORD_DEFAULT);

$dup = 0;
try {
    $s = $conn->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :u");
    $s->execute([':u' => $USERNAME]);
    $dup = (int)$s->fetchColumn();
} catch (PDOException $e) { $dup = 0; }

if ($dup === 0) {
    $stmt = $conn->prepare("INSERT INTO admin_users (name, username, email, password) VALUES (:n, :u, :e, :p)");
    $stmt->execute([':n' => $NAME, ':u' => $USERNAME, ':e' => 'admin@akksolutions.com', ':p' => $hash]);
    echo '<pre style="color:#4ade80;background:#0f172a;padding:20px">SUCCESS: Admin account eka create kala!' . "\n\n"
       . "Username: " . $USERNAME . "\n"
       . "Password: " . $PASSWORD . "\n\n"
       . 'Dan ' . (PHP_SAPI === 'cli' ? 'localhost' : 'web') . ' eken Sign In karanna.' . '</pre>';
} else {
    echo '<pre style="color:#fbbf24;background:#0f172a;padding:20px">NOTE: "' . $USERNAME . '" username ekata admin account ekak DENEITHIMA innawa. Change na (exist).</pre>';
}

if (file_exists(__FILE__)) {
    @unlink(__FILE__);
    echo '<pre style="color:#94a3b8;background:#0f172a;padding:8px">[Security] Setup script eka auto-delete kala.</pre>';
}