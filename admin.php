<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireAdmin();

$currentUser = getCurrentUser();

$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

$message = "";
$messageOk = true;

function validUsernameFormat($u) {
    return is_string($u) && preg_match('/^[a-zA-Z0-9._]{3,50}$/', $u);
}

function usernameTaken($conn, $username, $excludeUserId = 0) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :u AND id != :eid");
    $stmt->execute([':u' => $username, ':eid' => $excludeUserId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) return true;
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function activeLabExists(PDO $conn, int $labId): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM labs WHERE id = :id AND active = 1");
    $stmt->execute([':id' => $labId]);
    return (int)$stmt->fetchColumn() > 0;
}

function adminMsg($text, $ok = true) {
    $cls = $ok ? 'bg-emerald-500/10 border-emerald-500/25 text-emerald-300'
               : 'bg-rose-500/10 border-rose-500/25 text-rose-300';
    $icon = $ok ? 'fa-circle-check' : 'fa-circle-xmark';
    return '<div class="p-3 rounded-xl border ' . $cls . ' text-xs mb-4"><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($text) . '</div>';
}

if (isset($_POST['create_lab'])) {
    $labName = trim($_POST['lab_name'] ?? '');
    $err = '';
    if ($labName === '' || mb_strlen($labName) > 100) {
        $err = 'Lab name eka akuru 1-100 athara venna one.';
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM labs WHERE LOWER(name) = LOWER(:name)");
        $stmt->execute([':name' => $labName]);
        if ((int)$stmt->fetchColumn() > 0) {
            $err = 'Me Lab eka danata thiyenawa. Vena nama danna.';
        }
    }

    if ($err !== '') {
        writeLog('LAB_CREATE_FAILED', $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO labs (name, active) VALUES (:name, 1)");
        $stmt->execute([':name' => $labName]);
        writeLog('LAB_CREATED', 'Lab created: ' . $labName);
        $message = adminMsg('Lab added: ' . $labName);
    }
}

if (isset($_POST['update_emp'])) {
    $empId       = (int)($_POST['emp_id'] ?? 0);
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $newShift    = $_POST['new_shift'] ?? '';
    $newPos      = trim($_POST['new_position'] ?? '');
    $newNum      = trim($_POST['new_number'] ?? '');
    $newLabId    = trim($_POST['new_lab_id'] ?? '');
    $newLabIdValue = $newLabId === '' ? 0 : filter_var($newLabId, FILTER_VALIDATE_INT);

    $err = '';
    if ($empId <= 0) {
        $err = 'Employee kenekwa select karanna.';
    } elseif ($newUsername !== '' && !validUsernameFormat($newUsername)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif ($newUsername !== '' && usernameTaken($conn, $newUsername, $empId)) {
        $err = 'මෙම username එක වෙනත් කෙනෙකුට දැනටමත් පවතී.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 4 || strlen($newPassword) > 72)) {
        $err = 'Password අකුරු 4-72 ක විය යුතුයි.';
    } elseif ($newShift !== '' && !in_array($newShift, ['M', 'E', 'BOTH'], true)) {
        $err = 'වලංගු shift එකක් තෝරන්න (M / E / BOTH).';
    } elseif ($newPos !== '' && mb_strlen($newPos) > 100) {
        $err = 'Position eka akuru 100 n ithi witharak venna one.';
    } elseif ($newNum !== '' && mb_strlen($newNum) > 50) {
        $err = 'Employee number eka akuru 50 n ithi witharak venna one.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $empId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) {
            $err = 'Employee #' . $empId . ' db eke nathi vela.';
        } elseif ($newLabId !== '' && ($emp['employee_type'] !== 'lab' || !is_int($newLabIdValue) || !activeLabExists($conn, $newLabIdValue))) {
            $err = 'Lab ekak assign karanna active Lab employee kenek select karanna.';
        }
    }

    if ($err !== '') {
        writeLog('ADMIN_EMP_UPDATE_FAILED', 'User#' . $empId . ': ' . $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        $sets = [];
        $params = [':id' => $empId];
        $changes = [];
        if ($newUsername !== '') {
            $sets[] = "username = :username";
            $params[':username'] = $newUsername;
            $changes[] = 'username:' . $newUsername;
        }
        if ($newPassword !== '') {
            $sets[] = "password = :password";
            $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $changes[] = 'password changed';
        }
        if ($newShift !== '') {
            $sets[] = "shift_type = :shift";
            $params[':shift'] = $newShift;
            $changes[] = 'shift:' . $newShift;
        }
        if ($newPos !== '') {
            $sets[] = "position = :position";
            $params[':position'] = $newPos;
            $changes[] = 'position:' . $newPos;
        }
        if ($newNum !== '') {
            $sets[] = "emp_number = :emp_number";
            $params[':emp_number'] = $newNum;
            $changes[] = 'emp_number:' . $newNum;
        }
        if ($newLabId !== '') {
            $sets[] = "lab_id = :lab_id";
            $params[':lab_id'] = $newLabIdValue;
            $changes[] = 'lab:' . $newLabId;
        }

        if (empty($sets)) {
            $message = adminMsg('Kisima deyak wenas kale naha. Username ho password ho shift ekak liyanna.');
            $messageOk = false;
        } else {
            $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id");
            $stmt->execute($params);
            writeLog('ADMIN_EMP_UPDATED', 'User#' . $empId . ' (' . $emp['name'] . ') -> ' . implode(', ', $changes));
            $message = adminMsg('Employee #' . $empId . ' (' . $emp['name'] . ') ge details update kala: ' . implode(', ', $changes));
        }
    }
}

if (isset($_POST['assign_leave'])) {
    $empId     = (int)($_POST['assign_emp_id'] ?? 0);
    $leaveDate = trim($_POST['assign_date'] ?? '');
    $shift     = $_POST['assign_shift'] ?? '';
    $status    = ($_POST['assign_status'] ?? '') === 'Pending' ? 'Pending' : 'Approved';
    $reason    = trim($_POST['assign_reason'] ?? '');

    $err = '';
    if ($empId <= 0) {
        $err = 'Employee kenekwa select karanna.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaveDate) || strtotime($leaveDate) === false) {
        $err = 'වලංගු දිනයක් තෝරන්න.';
    } elseif (!in_array($shift, ['M', 'E'], true)) {
        $err = 'Shift eka thooranna (Morning ho Evening).';
    } elseif ($reason === '') {
        $err = 'හේතුවක් ලියන්න.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = :id");
        $stmt->execute([':id' => $empId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) {
            $err = 'Employee #' . $empId . ' db eke nathi vela.';
        }
    }

    if ($err !== '') {
        writeLog('ADMIN_MANUAL_LEAVE_FAILED', 'User#' . $empId . ': ' . $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_date, shift_applied, is_emergency, reason, status)
                                VALUES (:uid, :date, :shift, 0, :reason, :status)");
        $stmt->execute([':uid' => $empId, ':date' => $leaveDate, ':shift' => $shift, ':reason' => $reason, ':status' => $status]);
        writeLog('ADMIN_MANUAL_LEAVE', 'User#' . $empId . ' (' . $emp['name'] . ') date:' . $leaveDate . ' shift:' . $shift . ' status:' . $status);
        $message = adminMsg('Leave eka assign kala: ' . $emp['name'] . ' | ' . $leaveDate . ' | ' . ($shift === 'M' ? 'Morning' : 'Evening') . ' | ' . $status);
    }
}

// --- Action Handlers (Approve, Reject / Cancel) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $req_id = (int)$_GET['id'];
    $act = $_GET['action'];
    
    if ($act === 'approve') {
        $status = 'Approved';
    } elseif ($act === 'cancel' || $act === 'reject') {
        $status = 'Cancelled';
    } else {
        $status = 'Rejected';
    }

    $stmt = $conn->prepare("UPDATE leave_requests SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $req_id]);
    writeLog("ADMIN_$status", "Request#$req_id $status");
    header("Location: admin.php");
    exit();
}

if (isset($_POST['save_codes'])) {
    $newAdminKey = trim($_POST['new_admin_key'] ?? '');

    if ($newAdminKey === '') {
        writeLog('SECURITY_CODES_FAILED', 'Empty admin key');
        $message = adminMsg('Alut Admin Key eka liyanna.', false);
        $messageOk = false;
    } elseif (mb_strlen($newAdminKey) < 6) {
        writeLog('SECURITY_CODES_FAILED', 'Short admin key');
        $message = adminMsg('Admin Key eka ayat akuru 6k wada diga venna one.', false);
        $messageOk = false;
    } else {
        setSetting($conn, 'admin_reg_key', $newAdminKey);
        writeLog('SECURITY_CODES_UPDATED', 'Admin reg key changed');
        $message = adminMsg('Update sadu! Admin Key eka aluten set kala.');
    }
}

if (isset($_POST['save_engine_settings'])) {
    $maxMonthly = (int)($_POST['max_monthly_leaves'] ?? 0);
    $minMorning = (int)($_POST['min_morning_staff'] ?? 0);
    $minEvening = (int)($_POST['min_evening_staff'] ?? 0);
    $useDep     = (isset($_POST['use_dependency_rules']) && $_POST['use_dependency_rules'] === '1') ? '1' : '0';

    $err = '';
    if ($maxMonthly < 0 || $maxMonthly > 90) { $err = 'Monthly leave limit 0-90 athara venna one (0 = no limit).'; }
    elseif ($minMorning < 0 || $minMorning > 500) { $err = 'Morning minimum 0-500 athara venna one (0 = off).'; }
    elseif ($minEvening < 0 || $minEvening > 500) { $err = 'Evening minimum 0-500 athara venna one (0 = off).'; }

    if ($err !== '') {
        writeLog('ENGINE_SETTINGS_FAILED', $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        setSetting($conn, 'max_monthly_leaves', (string)$maxMonthly);
        setSetting($conn, 'min_morning_staff', (string)$minMorning);
        setSetting($conn, 'min_evening_staff', (string)$minEvening);
        setSetting($conn, 'use_dependency_rules', $useDep);
        writeLog('ENGINE_SETTINGS_UPDATED', "maxMonthly:$maxMonthly minM:$minMorning minE:$minEvening dep:$useDep");
        $message = adminMsg('Leave rules settings update kala!');
    }
}

if (isset($_POST['change_my_password'])) {
    $curPass = $_POST['cur_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $conPass = $_POST['confirm_password'] ?? '';

    $err = '';
    if ($curPass === '' || $newPass === '' || $conPass === '') {
        $err = 'Okkoma fields anivaryayi.';
    } elseif (strlen($newPass) < 6 || strlen($newPass) > 72) {
        $err = 'Alut password eka akuru 6-72 athara venna one.';
    } elseif ($newPass !== $conPass) {
        $err = 'Alut password deka samana nahi.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $currentUser['id']]);
        $meRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$meRow) {
            $err = 'Account eka hambila naha.';
        } elseif (!password_verify($curPass, $meRow['password'])) {
            $err = 'Parana password eka veradi.';
        }
    }

    if ($err !== '') {
        writeLog('ADMIN_PASSWORD_FAILED', 'User#' . $currentUser['id'] . ': ' . $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        $stmt = $conn->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
        $stmt->execute([':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $currentUser['id']]);
        writeLog('ADMIN_PASSWORD_CHANGED', 'Admin#' . $currentUser['id'] . ' changed own password');
        $message = adminMsg('Oyage password eka sampurnayenma venas kala. Alut password eken dan Create Account gate eka unlock karanna puluwan.');
    }
}

// Change an Admin account's password directly (owner/managers)
if (isset($_POST['update_admin_pass'])) {
    $admId         = (int)($_POST['adm_id'] ?? 0);
    $admUsername   = trim($_POST['adm_username'] ?? '');
    $admNewPass    = $_POST['adm_new_password'] ?? '';
    $admConPass    = $_POST['adm_confirm_password'] ?? '';

    $err = '';
    if ($admId <= 0) {
        $err = 'Admin kenekwa select karanna.';
    } elseif ($admNewPass === '' || $admConPass === '') {
        $err = 'Alut password eka deka (new + confirm) anivaryayi.';
    } elseif (strlen($admNewPass) < 4 || strlen($admNewPass) > 72) {
        $err = 'Password eka akuru 4-72 athara venna one.';
    } elseif ($admNewPass !== $admConPass) {
        $err = 'Alut password deka samana nahi.';
    } elseif ($admUsername !== '' && !validUsernameFormat($admUsername)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif ($admUsername !== '' && usernameTaken($conn, $admUsername, $admId)) {
        $err = 'මෙම username එක වෙනත් කෙනෙකුට දැනටමත් පවතී.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $admId]);
        $adm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$adm) {
            $err = 'Admin #' . $admId . ' db eke nathi vela.';
        }
    }

    if ($err !== '') {
        writeLog('ADMIN_UPDATE_FAILED', 'Admin#' . $admId . ': ' . $err);
        $message = adminMsg($err, false);
        $messageOk = false;
    } else {
        if ($admUsername !== '') {
            $stmt = $conn->prepare("UPDATE admin_users SET username = :u, password = :p WHERE id = :id");
            $stmt->execute([':u' => $admUsername, ':p' => password_hash($admNewPass, PASSWORD_DEFAULT), ':id' => $admId]);
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
            $stmt->execute([':p' => password_hash($admNewPass, PASSWORD_DEFAULT), ':id' => $admId]);
        }
        writeLog('ADMIN_PASSWORD_CHANGED', 'Admin#'.$currentUser['id'].' changed Admin#'.$admId.' ('.$adm['username'].') password');
        $message = adminMsg('Admin #' . $admId . ' (' . $adm['name'] . ') ge password eka venas kala.');
    }
}

// Reset User Password via Token
if (isset($_POST['reset_user_password'])) {
    $resetToken = trim($_POST['reset_token'] ?? '');
    $newPass    = $_POST['reset_new_password'] ?? '';
    $conPass    = $_POST['reset_confirm_password'] ?? '';

    $err = '';
    if ($resetToken === '' || $newPass === '' || $conPass === '') {
        $err = 'Okkoma fields anivaryayi.';
    } elseif (strlen($newPass) < 4 || strlen($newPass) > 72) {
        $err = 'Password eka akuru 4-72 athara venna one.';
    } elseif ($newPass !== $conPass) {
        $err = 'Alut password deka samana nahi.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = :token AND used = 0 LIMIT 1");
        $stmt->execute([':token' => $resetToken]);
        $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$resetRow) {
            $err = 'Token eka veradi ho already used wela thiyenawa.';
            writeLog('RESET_FAILED', 'Invalid token attempt by admin#' . $currentUser['id']);
        } elseif (strtotime($resetRow['expires_at']) < time()) {
            $err = 'Token eka expire wela (30 min). User keneku new token ekak generate karanna kiyanna.';
            writeLog('RESET_FAILED', 'Expired token attempt by admin#' . $currentUser['id']);
        }
    }

    if ($err === '') {
        $tbl = $resetRow['user_type'] === 'admin' ? 'admin_users' : 'users';
        $stmt = $conn->prepare("UPDATE $tbl SET password = :p WHERE id = :id");
        $stmt->execute([':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $resetRow['user_id']]);
        $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = :id");
        $stmt->execute([':id' => $resetRow['id']]);
        writeLog('ADMIN_PASSWORD_RESET', "Admin#{$currentUser['id']} reset {$resetRow['user_type']}#{$resetRow['user_id']} password via token");
        $message = adminMsg("Password eka reset kala! {$resetRow['user_type']} #{$resetRow['user_id']}.");
    } else {
        $message = adminMsg($err, false);
        $messageOk = false;
    }
}

// --- Metrics ---
$stmt = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'Pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM leave_requests WHERE leave_date = :today AND status = 'Approved'");
$stmt->execute([':today' => $today]);
$today_leaves = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

$stmt = $conn->query("SELECT COUNT(*) as c FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'Approved' AND SUBSTR(leave_date, 6, 2) = :month AND SUBSTR(leave_date, 1, 4) = :year");
$stmt->execute([':month' => $current_month, ':year' => $current_year]);
$approved_month = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

$stmt = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'Approved'");
$total_approved = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

$stmt = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status IN ('Rejected', 'Cancelled')");
$total_rejected = $stmt->fetch(PDO::FETCH_ASSOC)['c'];

// --- Pending Requests ---
$stmt = $conn->query("SELECT lr.*, u.name 
                      FROM leave_requests lr 
                      JOIN users u ON lr.user_id = u.id 
                      WHERE lr.status = 'Pending' 
                      ORDER BY lr.created_at DESC");
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Approved Requests (Cancel කිරීමට) ---
$stmt = $conn->query("SELECT lr.*, u.name 
                      FROM leave_requests lr 
                      JOIN users u ON lr.user_id = u.id 
                      WHERE lr.status = 'Approved' AND lr.leave_date >= '$today'
                      ORDER BY lr.leave_date ASC");
$approved_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Employees List ---
$employees = $conn->query("SELECT u.*, l.name AS lab_name
                           FROM users u
                           LEFT JOIN labs l ON l.id = u.lab_id
                           ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
$labs = $conn->query("SELECT id, name FROM labs WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT * FROM admin_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$hasCustomRegCode = (getSetting($conn, 'register_access_code', '') !== '');
$hasCustomAdminKey = (getSetting($conn, 'admin_reg_key', '') !== '');

// --- Engine / Business Rule Settings ---
$cfgMaxMonthly  = (int)getSetting($conn, 'max_monthly_leaves', 4);
$cfgMinMorning  = (int)getSetting($conn, 'min_morning_staff', 5);
$cfgMinEvening  = (int)getSetting($conn, 'min_evening_staff', 3);
$cfgUseDepRules = (int)getSetting($conn, 'use_dependency_rules', 0) === 1;

// --- Calendar Events ---
$stmt = $conn->query("SELECT lr.id, lr.leave_date, lr.shift_applied, u.name, u.id AS user_id, u.lab_id
                      FROM leave_requests lr
                      JOIN users u ON lr.user_id = u.id
                      WHERE lr.status = 'Approved' AND u.employee_type = 'lab'");
$labLeaveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labCalendarEvents = [];
foreach ($labLeaveRows as $row) {
    $labCalendarEvents[] = [
        'id' => $row['id'],
        'title' => '#' . $row['user_id'] . ' ' . $row['name'] . ' (' . $row['shift_applied'] . ')',
        'start' => $row['leave_date'],
        'color' => ($row['shift_applied'] == 'M') ? '#3b82f6' : '#a855f7',
        'labId' => (int)$row['lab_id']
    ];
}
$labEventsJson = json_encode($labCalendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

$stmt = $conn->query("SELECT lr.id, lr.leave_date, lr.shift_applied, u.name, u.id AS user_id
                      FROM leave_requests lr
                      JOIN users u ON lr.user_id = u.id
                      WHERE lr.status = 'Approved' AND u.employee_type = 'rider'");
$riderLeaveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$riderCalendarEvents = [];
foreach ($riderLeaveRows as $row) {
    $riderCalendarEvents[] = [
        'id' => $row['id'],
        'title' => '#' . $row['user_id'] . ' ' . $row['name'] . ' (' . $row['shift_applied'] . ')',
        'start' => $row['leave_date'],
        'color' => ($row['shift_applied'] == 'M') ? '#3b82f6' : '#a855f7'
    ];
}
$riderEventsJson = json_encode($riderCalendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover { background: rgba(99, 102, 241, 0.08); }
        .sidebar-link.active { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(99, 102, 241, 0.2); color: #a5b4fc; }
        .fc { border-color: rgba(51, 65, 85, 0.5) !important; }
        .fc .fc-toolbar-title { font-size: 1rem !important; font-weight: 700; color: white; }
        .fc .fc-button-primary { background-color: #4f46e5 !important; border: none !important; border-radius: 10px !important; padding: 6px 14px !important; font-size: 0.75rem !important; }
        .fc .fc-button-primary:hover { background-color: #4338ca !important; }
        .fc .fc-button-primary:disabled { background-color: rgba(79, 70, 229, 0.4) !important; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(51, 65, 85, 0.35) !important; }
        .fc .fc-daygrid-day-number { color: #94a3b8; font-size: 0.8rem; text-decoration: none !important; padding: 6px !important; }
        .fc .fc-daygrid-day.fc-day-today { background: rgba(99, 102, 241, 0.06) !important; }
        .fc-event { border-radius: 8px !important; padding: 3px 7px !important; font-size: 0.7rem !important; border: none !important; font-weight: 500 !important; cursor: pointer; }
        .fc .fc-col-header-cell { padding: 10px 0 !important; font-size: 0.7rem !important; font-weight: 600 !important; color: #94a3b8 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; }
        .fc-scrollgrid { border-radius: 12px !important; overflow: hidden !important; }
        .fc-scrollgrid-sync-table { background: rgba(15, 23, 42, 0.4) !important; }
        .fc .fc-daygrid-day:hover { background: rgba(99, 102, 241, 0.04) !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-72 bg-slate-950/80 backdrop-blur-xl border-r border-slate-800/60 flex flex-col justify-between hidden lg:flex fixed inset-y-0 left-0 z-40">
        <div>
            <div class="h-16 flex items-center px-6 border-b border-slate-800/60 gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <span class="text-sm font-black text-white">AK</span>
                </div>
                <div>
                    <span class="text-lg font-bold tracking-tight text-white">AKK <span class="text-indigo-400">SOLUTIONS</span></span>
                    <p class="text-[10px] text-slate-500 font-medium">Admin Dashboard</p>
                </div>
            </div>

            <nav class="p-4 space-y-1.5">
                <a href="#dashboard" class="sidebar-link active flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-indigo-400"></i> Dashboard
                </a>
                <a href="#calendar-section" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-flask w-5 text-center"></i> Lab Leave Calendar
                </a>
                <a href="#rider-calendar-section" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-motorcycle w-5 text-center"></i> Rider Leave Calendar
                </a>
                <a href="#approved-section" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-calendar-xmark w-5 text-center text-rose-400"></i> Cancel Leaves
                </a>
                <a href="#employees-section" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-users-gear w-5 text-center"></i> Manage Employees
                </a>
                <a href="setup_passwords.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-user-plus w-5 text-center"></i> Add / Remove Staff
                </a>
                <a href="index.php" target="_blank" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-paper-plane w-5 text-center"></i> Apply Portal
                </a>
                <a href="my_leaves.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-list-check w-5 text-center"></i> All Requests
                </a>
                <a href="my_logs.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i> My Logs
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-800/60">
            <div class="flex items-center gap-3 px-2">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-slate-700/50 flex items-center justify-center text-indigo-400 text-xs font-bold">
                    <?= strtoupper(substr($currentUser['name'], 0, 2)); ?>
                </div>
                <div class="text-xs flex-1 min-w-0">
                    <p class="font-semibold text-white truncate"><?= htmlspecialchars($currentUser['name']); ?></p>
                    <p class="text-slate-500 truncate">@<?= htmlspecialchars($_SESSION['user_username'] ?? 'admin'); ?></p>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-rose-400 transition p-1.5 rounded-lg hover:bg-slate-800/50" title="Logout">
                    <i class="fa-solid fa-right-from-bracket text-sm"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 lg:ml-72 flex flex-col min-w-0 min-h-screen">

        <!-- Top Bar -->
        <header class="h-16 bg-slate-950/50 backdrop-blur-xl border-b border-slate-800/60 sticky top-0 z-30 flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <button onclick="document.querySelector('aside').classList.toggle('hidden')" class="lg:hidden text-slate-400 hover:text-white transition p-2 rounded-lg hover:bg-slate-800/50">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-sm font-bold text-white">Leave Management Control Center</h1>
            </div>
            <a href="index.php" target="_blank" class="text-xs font-bold bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white px-4 py-2.5 rounded-xl transition shadow-md shadow-indigo-600/20 flex items-center gap-2">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Apply Portal
            </a>
        </header>

        <div class="p-6 md:p-8 space-y-8 max-w-7xl mx-auto w-full">
            
            <!-- Metrics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="card-glass border border-slate-700/40 rounded-2xl p-5 relative overflow-hidden group hover:border-amber-500/30 transition-all">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-amber-500/5 rounded-full blur-2xl group-hover:bg-amber-500/10 transition"></div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-clock-rotate-left text-amber-400 text-xs"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase text-amber-400 tracking-wider">Pending</span>
                    </div>
                    <p class="text-3xl font-extrabold text-white"><?= $pending_count; ?></p>
                    <p class="text-[10px] text-slate-500 mt-1">awaiting review</p>
                </div>
                <div class="card-glass border border-slate-700/40 rounded-2xl p-5 relative overflow-hidden group hover:border-indigo-500/30 transition-all">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-indigo-500/5 rounded-full blur-2xl group-hover:bg-indigo-500/10 transition"></div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-calendar-xmark text-indigo-400 text-xs"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase text-indigo-400 tracking-wider">On Leave Today</span>
                    </div>
                    <p class="text-3xl font-extrabold text-white"><?= $today_leaves; ?></p>
                    <p class="text-[10px] text-slate-500 mt-1">absent today</p>
                </div>
                <div class="card-glass border border-slate-700/40 rounded-2xl p-5 relative overflow-hidden group hover:border-emerald-500/30 transition-all">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-emerald-500/5 rounded-full blur-2xl group-hover:bg-emerald-500/10 transition"></div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-users text-emerald-400 text-xs"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase text-emerald-400 tracking-wider">Employees</span>
                    </div>
                    <p class="text-3xl font-extrabold text-white"><?= $total_users; ?></p>
                    <p class="text-[10px] text-slate-500 mt-1">total staff</p>
                </div>
                <div class="card-glass border border-slate-700/40 rounded-2xl p-5 relative overflow-hidden group hover:border-purple-500/30 transition-all">
                    <div class="absolute top-0 right-0 w-20 h-20 bg-purple-500/5 rounded-full blur-2xl group-hover:bg-purple-500/10 transition"></div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-chart-line text-purple-400 text-xs"></i>
                        </div>
                        <span class="text-[10px] font-bold uppercase text-purple-400 tracking-wider">Monthly</span>
                    </div>
                    <p class="text-3xl font-extrabold text-white"><?= $approved_month; ?></p>
                    <p class="text-[10px] text-slate-500 mt-1">approved this month</p>
                </div>
            </div>

            <!-- Lab Calendar -->
            <div id="calendar-section" class="card-glass border border-slate-700/40 rounded-2xl p-6 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-flask text-indigo-400 text-sm"></i>
                        </div>
                        <h2 class="font-bold text-white text-sm">Lab Leave Calendar</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-[11px] text-slate-300">
                            <span class="font-semibold">Select Lab</span>
                            <select id="labCalendarSelect" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white">
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?= (int)$lab['id']; ?>" <?= (int)$lab['id'] === $mainLabId ? 'selected' : ''; ?>><?= htmlspecialchars($lab['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="flex items-center gap-4 text-[11px]">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Morning (M)</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Evening (E)</span>
                        </div>
                    </div>
                </div>
                <div id="calendar" class="min-h-[500px]"></div>
            </div>

            <!-- Rider Calendar -->
            <div id="rider-calendar-section" class="card-glass border border-slate-700/40 rounded-2xl p-6 overflow-hidden">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-motorcycle text-amber-400 text-sm"></i>
                        </div>
                        <h2 class="font-bold text-white text-sm">Rider Leave Calendar</h2>
                    </div>
                    <div class="flex items-center gap-4 text-[11px]">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Morning (M)</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Evening (E)</span>
                    </div>
                </div>
                <div id="riderCalendar" class="min-h-[500px]"></div>
            </div>

            <!-- Emergency Queue (With Cancel Option) -->
            <div class="card-glass border border-slate-700/40 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/40 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center">
                        <i class="fa-solid fa-triangle-exclamation text-amber-400 text-sm"></i>
                    </div>
                    <h2 class="font-bold text-white text-sm">Emergency Override Queue</h2>
                    <?php if ($pending_count > 0): ?>
                        <span class="ml-auto text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/25 px-2.5 py-1 rounded-full"><?= $pending_count; ?> pending</span>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto">
                    <?php if (count($pending_requests) > 0): ?>
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-700/40 bg-slate-900/30">
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Employee</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Leave Date</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Shift</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reason</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($pending_requests as $row): ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700/50 flex items-center justify-center text-[10px] font-bold text-slate-300">#<?= $row['user_id']; ?></div>
                                                <span class="font-semibold text-white text-xs"><?= htmlspecialchars($row['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 font-mono text-xs text-slate-300"><?= $row['leave_date']; ?></td>
                                        <td class="py-4 px-6">
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $row['shift_applied'] == 'M' ? 'bg-blue-500/15 text-blue-400 border border-blue-500/25' : 'bg-purple-500/15 text-purple-400 border border-purple-500/25'; ?>">
                                                <?= $row['shift_applied'] == 'M' ? 'Morning' : 'Evening'; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-slate-400 text-xs max-w-[200px] truncate"><?= htmlspecialchars($row['reason']); ?></td>
                                        <td class="py-4 px-6 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="admin.php?action=approve&id=<?= $row['id']; ?>" 
                                                    class="text-[11px] font-bold bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg transition shadow-sm shadow-emerald-600/20 flex items-center gap-1">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </a>
                                                <a href="admin.php?action=cancel&id=<?= $row['id']; ?>" 
                                                    onclick="return confirm('Mema leave request eka cancel karanna surety da?');"
                                                    class="text-[11px] font-bold bg-rose-600 hover:bg-rose-500 text-white px-3 py-1.5 rounded-lg transition shadow-sm shadow-rose-600/20 flex items-center gap-1">
                                                    <i class="fa-solid fa-ban"></i> Cancel Request
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-4">
                                <i class="fa-solid fa-inbox text-slate-600 text-2xl"></i>
                            </div>
                            <p class="text-slate-500 text-sm font-medium">No emergency requests pending</p>
                            <p class="text-slate-600 text-xs mt-1">All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approved Upcoming Leaves (Cancel Any Time Section) -->
            <div id="approved-section" class="card-glass border border-slate-700/40 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/40 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-rose-500/15 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-xmark text-rose-400 text-sm"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-white text-sm">Manage / Cancel Approved Leaves</h2>
                        <p class="text-[10px] text-slate-500">Approve කරපු ඕනෑම leave එකක් ඕනෑම වෙලාවක මෙතැනින් Cancel කළ හැක</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <?php if (count($approved_requests) > 0): ?>
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-700/40 bg-slate-900/30">
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Employee</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Leave Date</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Shift</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reason</th>
                                    <th class="py-3.5 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($approved_requests as $row): ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700/50 flex items-center justify-center text-[10px] font-bold text-slate-300">#<?= $row['user_id']; ?></div>
                                                <span class="font-semibold text-white text-xs"><?= htmlspecialchars($row['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 font-mono text-xs text-slate-300"><?= $row['leave_date']; ?></td>
                                        <td class="py-4 px-6">
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $row['shift_applied'] == 'M' ? 'bg-blue-500/15 text-blue-400 border border-blue-500/25' : 'bg-purple-500/15 text-purple-400 border border-purple-500/25'; ?>">
                                                <?= $row['shift_applied'] == 'M' ? 'Morning' : 'Evening'; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-slate-400 text-xs max-w-[200px] truncate"><?= htmlspecialchars($row['reason']); ?></td>
                                        <td class="py-4 px-6 text-right">
                                            <a href="admin.php?action=cancel&id=<?= $row['id']; ?>" 
                                                onclick="return confirm('Mema approved leave eka cancel karanna surety da?');"
                                                class="text-[11px] font-bold bg-rose-600/80 hover:bg-rose-600 text-white px-3 py-1.5 rounded-lg transition shadow-sm shadow-rose-600/20 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-rectangle-xmark"></i> Cancel Leave
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-500 text-xs">
                            No upcoming approved leaves to manage.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Employee Management -->
            <div id="employees-section" class="space-y-6">
                <?php if ($message !== ''): ?>
                    <?= $message; ?>
                <?php endif; ?>

                <div class="card-glass border border-indigo-500/20 rounded-2xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-flask text-indigo-400 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-white text-sm">Manage Labs</h2>
                            <p class="text-[10px] text-slate-500">Add Labs here; existing employee assignments and leaves are retained.</p>
                        </div>
                    </div>
                    <form method="POST" class="flex flex-col sm:flex-row gap-3 mb-4">
                        <input type="text" name="lab_name" required maxlength="100" placeholder="New Lab name"
                            class="flex-1 bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        <button type="submit" name="create_lab"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-5 py-3 rounded-xl transition">
                            <i class="fa-solid fa-plus"></i> Add Lab
                        </button>
                    </form>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($labs as $lab): ?>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/70 border border-slate-700 text-xs text-slate-200"><?= htmlspecialchars($lab['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Edit Employee -->
                    <div class="card-glass border border-slate-700/40 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                                <i class="fa-solid fa-user-pen text-indigo-400 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-white text-sm">Edit Employee</h2>
                                <p class="text-[10px] text-slate-500">Username / Password / Shift wenas karanna</p>
                            </div>
                        </div>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Employee</label>
                                <select name="emp_id" required
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="">-- Select Employee --</option>
                                    <?php foreach ($employees as $e): ?>
                                        <option value="<?= (int)$e['id']; ?>">#<?= htmlspecialchars((string)($e['emp_number'] ?? '')) ?: (int)$e['id']; ?> <?= htmlspecialchars($e['name']); ?> (<?= htmlspecialchars(ucfirst($e['employee_type'])); ?><?= $e['employee_type'] === 'lab' && !empty($e['lab_name']) ? ' - ' . htmlspecialchars($e['lab_name']) : ''; ?>; @<?= htmlspecialchars($e['username'] ?? ''); ?> - <?= $e['shift_type']; ?><?= !empty($e['position']) ? ' - ' . htmlspecialchars($e['position']) : ''; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">New Username</label>
                                    <input type="text" name="new_username" placeholder="Wenas karanne nam witharak"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">New Password</label>
                                    <input type="password" name="new_password" placeholder="Wenas karanne nam witharak"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Shift Kattiya</label>
                                <select name="new_shift"
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="">-- Wenas karanne nathnam meka thiyanna --</option>
                                    <option value="M">Morning Shift</option>
                                    <option value="E">Evening Shift</option>
                                    <option value="BOTH">Both Shifts</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Position</label>
                                <input type="text" name="new_position" placeholder="Wenas karanne nam witharak (ud ah: Manager, Supervisor)"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Employee Number</label>
                                <input type="text" name="new_number" placeholder="Wenas karanne nam witharak (ud ah: 014, EMP005)"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Assign Lab (Lab employees)</label>
                                <select name="new_lab_id"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="">-- Do not change Lab --</option>
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?= (int)$lab['id']; ?>"><?= htmlspecialchars($lab['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_emp"
                                class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2 text-sm">
                                <i class="fa-solid fa-floppy-disk"></i> Update Employee
                            </button>
                        </form>
                    </div>

                    <!-- Assign Leave Manually -->
                    <div class="card-glass border border-emerald-500/20 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                                <i class="fa-solid fa-calendar-plus text-emerald-400 text-sm"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-white text-sm">Assign Leave Manually</h2>
                                <p class="text-[10px] text-slate-500">Employee ta leave ekak admin idan direct daanna</p>
                            </div>
                        </div>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Employee</label>
                                <select name="assign_emp_id" required
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="">-- Select Employee --</option>
                                    <?php foreach ($employees as $e): ?>
                                        <option value="<?= (int)$e['id']; ?>">#<?= (int)$e['id']; ?> <?= htmlspecialchars($e['name']); ?> (<?= htmlspecialchars(ucfirst($e['employee_type'])); ?><?= $e['employee_type'] === 'lab' && !empty($e['lab_name']) ? ' - ' . htmlspecialchars($e['lab_name']) : ''; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Leave Date</label>
                                    <input type="date" name="assign_date" required min="<?= date('Y-m-d'); ?>"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Shift</label>
                                    <select name="assign_shift" required
                                        class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all [&>option]:bg-slate-900">
                                        <option value="">-- Shift --</option>
                                        <option value="M">Morning</option>
                                        <option value="E">Evening</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Status</label>
                                <select name="assign_status" required
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="Approved">Approved (direct confirm)</option>
                                    <option value="Pending">Pending (review queue ekata)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Reason</label>
                                <textarea name="assign_reason" rows="2" required placeholder="Leave eke hethuwa..."
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all resize-none"></textarea>
                            </div>
                            <button type="submit" name="assign_leave"
                                class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 text-sm">
                                <i class="fa-solid fa-calendar-check"></i> Assign Leave
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Codes + My Password -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="card-glass border border-amber-500/20 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center">
                                <i class="fa-solid fa-key text-amber-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h2 class="font-bold text-white text-sm">Admin Registration Key</h2>
                                <p class="text-[10px] text-slate-500">Admin account alut ekak hadanna one key eka</p>
                            </div>
                            <span class="text-[10px] <?= $hasCustomAdminKey ? 'text-emerald-400' : 'text-slate-500'; ?>"><?= $hasCustomAdminKey ? 'custom set kala' : 'default'; ?></span>
                        </div>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Alut Admin Key</label>
                                <input type="password" name="new_admin_key" required placeholder="Alut key eka (akuru 6+) liyanna"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
                            </div>
                            <button type="submit" name="save_codes"
                                class="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-amber-600/25 flex items-center justify-center gap-2 text-sm">
                                <i class="fa-solid fa-key"></i> Save Admin Key
                            </button>
                        </form>
                    </div>

                    <div class="card-glass border border-emerald-500/20 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                                <i class="fa-solid fa-rotate text-emerald-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h2 class="font-bold text-white text-sm">Reset User Password</h2>
                                <p class="text-[10px] text-slate-500">Login.php eken labena token eken reset karanna</p>
                            </div>
                        </div>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Reset Token</label>
                                <input type="text" name="reset_token" required placeholder="User eka gen karana token eka paste karanna"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">New Password</label>
                                    <input type="password" name="reset_new_password" required minlength="4" placeholder="Alut password"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Confirm</label>
                                    <input type="password" name="reset_confirm_password" required minlength="4" placeholder="Ayeth liyanna"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                                </div>
                            </div>
                            <button type="submit" name="reset_user_password"
                                class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 text-sm">
                                <i class="fa-solid fa-shield-halved"></i> Reset Password
                            </button>
                        </form>
                    </div>

                    <div class="card-glass border border-rose-500/20 rounded-2xl p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-9 h-9 rounded-xl bg-rose-500/15 flex items-center justify-center">
                                <i class="fa-solid fa-user-shield text-rose-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h2 class="font-bold text-white text-sm">Change My Password</h2>
                                <p class="text-[10px] text-slate-500">Oyage (@<?= htmlspecialchars($_SESSION['user_username'] ?? 'admin'); ?>) password eka venas karanna</p>
                            </div>
                        </div>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Parana Password</label>
                                <input type="password" name="cur_password" required placeholder="Dan thiyena password eka"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Alut Password</label>
                                    <input type="password" name="new_password" required minlength="6" placeholder="Akuru 6+"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Confirm</label>
                                    <input type="password" name="confirm_password" required minlength="6" placeholder="Ayeth liyanna"
                                        class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
                                </div>
                            </div>
                            <button type="submit" name="change_my_password"
                                class="w-full bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 text-sm">
                                <i class="fa-solid fa-shield-halved"></i> Change My Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Leave Rules / Auto-Approval Settings -->
                <div class="card-glass border border-sky-500/20 rounded-2xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-9 h-9 rounded-xl bg-sky-500/15 flex items-center justify-center">
                            <i class="fa-solid fa-scale-balanced text-sky-400 text-sm"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-white text-sm">Leave Auto-Approval Rules</h2>
                            <p class="text-[10px] text-slate-500">Monthly quota + shift minimums + optional dependency rules</p>
                        </div>
                    </div>
                    <form method="POST" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Max Monthly Leaves</label>
                                <input type="number" name="max_monthly_leaves" min="0" max="90" value="<?= $cfgMaxMonthly; ?>"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-sky-500 input-glow transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Min Morning Staff On Duty</label>
                                <input type="number" name="min_morning_staff" min="0" max="500" value="<?= $cfgMinMorning; ?>"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-sky-500 input-glow transition-all">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Min Evening Staff On Duty</label>
                                <input type="number" name="min_evening_staff" min="0" max="500" value="<?= $cfgMinEvening; ?>"
                                    class="w-full bg-slate-800/60 border border-slate-700/60 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-sky-500 input-glow transition-all">
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-3 cursor-pointer w-full bg-slate-800/50 border border-slate-700/60 rounded-xl px-4 py-3">
                                    <input type="checkbox" name="use_dependency_rules" value="1" <?= $cfgUseDepRules ? 'checked' : ''; ?>
                                        class="w-4 h-4 accent-sky-500">
                                    <span class="text-sm text-white font-semibold">Enable #1-12 Dependency Rules</span>
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="save_engine_settings"
                            class="w-full bg-gradient-to-r from-sky-600 to-cyan-600 hover:from-sky-500 hover:to-cyan-500 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-sky-600/25 flex items-center justify-center gap-2 text-sm">
                            <i class="fa-solid fa-floppy-disk"></i> Save Rules
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var labEvents = <?= $labEventsJson; ?>;
            var riderEvents = <?= $riderEventsJson; ?>;

            function createLeaveCalendar(elementId, events, leaveOwner) {
                var calendar = new FullCalendar.Calendar(document.getElementById(elementId), {
                    initialView: 'dayGridMonth',
                    height: 520,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek'
                    },
                    events: events,
                    eventClick: function(info) {
                        if (confirm(leaveOwner + ' Leave: ' + info.event.title + '\nDate: ' + info.event.startStr + '\n\nObata me leave eka cancel karanna oneda?')) {
                            window.location.href = 'admin.php?action=cancel&id=' + info.event.id;
                        }
                    },
                    dayMaxEvents: 3,
                    moreLinkText: function(n) {
                        return '+' + n + ' more';
                    }
                });
                calendar.render();
                return calendar;
            }

            var labSelect = document.getElementById('labCalendarSelect');
            var selectedLabId = labSelect.value;
            var labCalendar = createLeaveCalendar(
                'calendar',
                labEvents.filter(function(event) { return String(event.labId) === selectedLabId; }),
                'Lab'
            );
            labSelect.addEventListener('change', function() {
                labCalendar.removeAllEvents();
                labCalendar.addEventSource(
                    labEvents.filter(function(event) { return String(event.labId) === this.value; }, this)
                );
            });

            createLeaveCalendar('riderCalendar', riderEvents, 'Rider');
        });
    </script>
</body>
</html>