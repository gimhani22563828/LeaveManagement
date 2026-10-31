<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireAdmin();

$currentUser = getCurrentUser();
$message = "";
$showAllStaff = false;

$isMysql = ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');

function columnExists($conn, $table, $column) {
    if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE :col");
        $stmt->execute([':col' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
    $stmt = $conn->prepare("PRAGMA table_info($table)");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['name'] === $column) return true;
    }
    return false;
}

if (!columnExists($conn, 'users', 'email')) {
    if ($isMysql) {
        $conn->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255)");
        $conn->exec("UPDATE users SET email = CONCAT('user', id, '@akksolutions.local') WHERE email IS NULL OR email = ''");
    } else {
        $conn->exec("ALTER TABLE users ADD COLUMN email TEXT");
        $conn->exec("UPDATE users SET email = 'user' || id || '@akksolutions.local' WHERE email IS NULL OR email = ''");
    }
}

if (!columnExists($conn, 'users', 'password')) {
    if ($isMysql) {
        $conn->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255)");
    } else {
        $conn->exec("ALTER TABLE users ADD COLUMN password TEXT");
    }
}

if (!columnExists($conn, 'users', 'username')) {
    if ($isMysql) {
        $conn->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50)");
        $conn->exec("UPDATE users SET username = CONCAT('emp', id) WHERE username IS NULL OR username = ''");
    } else {
        $conn->exec("ALTER TABLE users ADD COLUMN username TEXT");
        $conn->exec("UPDATE users SET username = 'emp' || id WHERE username IS NULL OR username = ''");
    }
}

function validUsernameFormat($u) {
    return is_string($u) && preg_match('/^[a-zA-Z0-9._]{3,50}$/', $u);
}

function errorMessageBox($text) {
    return '<div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/25 text-rose-300 text-xs"><i class="fa-solid fa-circle-xmark"></i> ' . htmlspecialchars($text) . '</div>';
}

function usernameExistsAnywhere($conn, $username, $excludeUserId = 0) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :u AND id != :eid");
    $stmt->execute([':u' => $username, ':eid' => $excludeUserId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) return true;
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle admin creation
if (isset($_POST['create_admin'])) {
    $name = trim($_POST['admin_name'] ?? '');
    $admin_username = trim($_POST['admin_username'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $raw_password = $_POST['admin_password'] ?? '';

    $err = '';
    if ($name === '' || $admin_username === '' || $email === '' || $raw_password === '') {
        $err = 'සියලුම fields (නම, username, email, password) අනිවාර්යයි. හිස්තැන් තැබීම තහනම්.';
    } elseif (!validUsernameFormat($admin_username)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'වලංගු email ලිපිනයක් ලබා දෙන්න.';
    } elseif (strlen($raw_password) < 4) {
        $err = 'Password අකුරු 4 ට වඩා අඩු විය නොහැක.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100 || strlen($raw_password) > 72) {
        $err = 'නම අකුරු 2-100 ක් විය යුතුයි.';
    } elseif (usernameExistsAnywhere($conn, $admin_username)) {
        $err = 'මෙම username එක දැනටමත් භාවිතයේ ඇත (employees + admins දෙකම පරීක්ෂා කළා).';
    }

    if ($err !== '') {
        writeLog('ADMIN_CREATE_FAILED', 'Validation: ' . $err);
        $message = errorMessageBox($err);
    } else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (name, username, email, password) VALUES (:name, :username, :email, :password)");
        $stmt->execute([':name' => $name, ':username' => $admin_username, ':email' => $email, ':password' => $password]);
        writeLog('ADMIN_CREATED', 'Admin account created: ' . $admin_username);
        $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Admin account එක සාර්ථකව සෑදිණි! Login: <b>' . htmlspecialchars($admin_username) . '</b></div>';
    }
}

// Handle employee password setup
if (isset($_POST['set_emp_password'])) {
    $emp_id = (int)($_POST['emp_id'] ?? 0);
    $new_username = trim($_POST['emp_username'] ?? '');
    $raw_password = $_POST['emp_password'] ?? '';

    $err = '';
    if ($emp_id <= 0) {
        $err = 'Employee kenekwa select karanna.';
    } elseif ($raw_password === '') {
        $err = 'Password එකක් අනිවාර්යයි. Password nathiwa save karanna beri.';
    } elseif (strlen($raw_password) < 4 || strlen($raw_password) > 72) {
        $err = 'Password අකුරු 4-72 ක විය යුතුයි.';
    } elseif ($new_username !== '' && !validUsernameFormat($new_username)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif ($new_username !== '' && usernameExistsAnywhere($conn, $new_username, $emp_id)) {
        $err = 'මෙම username එක වෙනත් කෙනෙකුට දැනටමත් පවතී.';
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $emp_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $err = 'Employee #' . $emp_id . ' db eke nathi vela.';
        }
    }

    if ($err !== '') {
        writeLog('EMP_PASSWORD_FAILED', 'Validation: ' . $err);
        $message = errorMessageBox($err);
    } else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);
        if ($new_username !== '') {
            $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password WHERE id = :id");
            $stmt->execute([':username' => $new_username, ':password' => $password, ':id' => $emp_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute([':password' => $password, ':id' => $emp_id]);
        }
        writeLog('EMP_PASSWORD_SET', 'Credentials set for User#' . $emp_id);
        $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Employee #' . $emp_id . ' ගේ details සාර්ථකව සැකසිණි.</div>';
    }
}

if (isset($_POST['save_employee'])) {
    $showAllStaff = true;
    $empId = (int)($_POST['employee_id'] ?? 0);
    $name = trim($_POST['employee_name'] ?? '');
    $username = trim($_POST['employee_username'] ?? '');
    $email = trim($_POST['employee_email'] ?? '');
    $empNumber = trim($_POST['employee_number'] ?? '');
    $position = trim($_POST['employee_position'] ?? '');
    $shift = $_POST['employee_shift'] ?? '';
    $employeeType = $_POST['employee_type'] ?? '';
    $labIdInput = trim($_POST['employee_lab_id'] ?? '');
    $newPassword = $_POST['employee_password'] ?? '';

    $err = '';
    if ($empId <= 0) {
        $err = 'Employee kenekwa select karanna.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $err = 'Employee name eka akuru 2-100 athara venna one.';
    } elseif (!validUsernameFormat($username)) {
        $err = 'Username eka akuru 3-50 athara (a-z, A-Z, 0-9, . _) venna one.';
    } elseif (usernameExistsAnywhere($conn, $username, $empId)) {
        $err = 'මෙම username එක දැනටමත් භාවිතයේ ඇත.';
    } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
        $err = 'වලංගු email ලිපිනයක් ලබා දෙන්න.';
    } elseif (!in_array($shift, ['M', 'E', 'BOTH'], true)) {
        $err = 'වලංගු shift එකක් තෝරන්න (M / E / BOTH).';
    } elseif (!in_array($employeeType, ['lab', 'rider'], true)) {
        $err = 'Employee type eka Lab Employee ho Rider venna one.';
    } elseif (mb_strlen($empNumber) > 50) {
        $err = 'Employee number eka akuru 50 n ithi venna one.';
    } elseif (mb_strlen($position) > 100) {
        $err = 'Position eka akuru 100 n ithi venna one.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 4 || strlen($newPassword) > 72)) {
        $err = 'Password eka akuru 4-72 athara venna one.';
    } elseif ($employeeType === 'lab' && filter_var($labIdInput, FILTER_VALIDATE_INT) === false) {
        $err = 'Lab employee kenekuta active Lab ekak select karanna.';
    }

    $labId = null;
    if ($err === '' && $employeeType === 'lab') {
        $labId = (int)$labIdInput;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM labs WHERE id = :id AND active = 1");
        $stmt->execute([':id' => $labId]);
        if ((int)$stmt->fetchColumn() === 0) {
            $err = 'Lab employee kenekuta active Lab ekak select karanna.';
        }
    }

    if ($err === '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $empId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $err = 'Employee #' . $empId . ' db eke nathi vela.';
        }
    }

    if ($err !== '') {
        writeLog('EMP_UPDATE_FAILED', 'User#' . $empId . ': ' . $err);
        $message = errorMessageBox($err);
    } else {
        try {
            $sql = "UPDATE users SET name = :name, username = :username, email = :email,
                    emp_number = :emp_number, position = :position, shift_type = :shift,
                    employee_type = :employee_type, lab_id = :lab_id";
            $params = [
                ':name' => $name,
                ':username' => $username,
                ':email' => $email === '' ? null : $email,
                ':emp_number' => $empNumber === '' ? null : $empNumber,
                ':position' => $position === '' ? null : $position,
                ':shift' => $shift,
                ':employee_type' => $employeeType,
                ':lab_id' => $employeeType === 'lab' ? $labId : null,
                ':id' => $empId,
            ];
            if ($newPassword !== '') {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            writeLog('EMP_UPDATED', 'User#' . $empId . ' (' . $username . ') details updated');
            $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Employee #' . $empId . ' details update kala.</div>';
        } catch (PDOException $e) {
            writeLog('EMP_UPDATE_FAILED', 'Database update failed for User#' . $empId);
            $message = errorMessageBox('Employee details save karanna bari una. Submitted details check karala ayeth try karanna.');
        }
    }
}

// Handle bulk setup
if (isset($_POST['setup_all'])) {
    $default_pass = password_hash('leave123', PASSWORD_DEFAULT);
    $conn->exec("UPDATE users SET password = '$default_pass' WHERE password IS NULL OR password = ''");
    if ($isMysql) {
        $conn->exec("UPDATE users SET username = CONCAT('emp', id) WHERE username IS NULL OR username = ''");
    } else {
        $conn->exec("UPDATE users SET username = 'emp' || id WHERE username IS NULL OR username = ''");
    }
    $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> සියලුම employees ට default password: <b>leave123</b> සහ usernames: <b>emp1, emp2...</b> ලබා දෙන ලදී.</div>';
}

// Handle new employee creation
if (isset($_POST['add_employee'])) {
    $emp_name = trim($_POST['emp_name'] ?? '');
    $emp_username = trim($_POST['emp_new_username'] ?? '');
    $emp_shift = $_POST['emp_shift'] ?? '';
    $raw_password = $_POST['emp_new_password'] ?? '';
    $employee_type = $_POST['employee_type'] ?? 'lab';
    $lab_id = (int)filter_var($_POST['lab_id'] ?? '', FILTER_VALIDATE_INT);
    $lab_is_valid = false;
    if ($employee_type === 'lab') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM labs WHERE id = :id AND active = 1");
        $stmt->execute([':id' => $lab_id]);
        $lab_is_valid = (int)$stmt->fetchColumn() > 0;
    }

    $err = '';
    if ($emp_name === '' || $emp_username === '' || $emp_shift === '' || $raw_password === '') {
        $err = 'සියලුම fields (නම, username, shift, password) අනිවාර්යයි. Name saha password nathiwa account hadanna beri.';
    } elseif (!in_array($employee_type, ['lab', 'rider'], true)) {
        $err = 'Employee type eka Lab Employee ho Rider venna one.';
    } elseif (mb_strlen($emp_name) < 2 || mb_strlen($emp_name) > 100) {
        $err = 'නම අකුරු 2-100 ක් විය යුතුයි.';
    } elseif (!validUsernameFormat($emp_username)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif (!in_array($emp_shift, ['M', 'E', 'BOTH'], true)) {
        $err = 'වලංගු shift එකක් තෝරන්න (M / E / BOTH).';
    } elseif ($employee_type === 'lab' && !$lab_is_valid) {
        $err = 'Lab employee kenekuta active Lab ekak select karanna.';
    } elseif (strlen($raw_password) < 4 || strlen($raw_password) > 72) {
        $err = 'Password අකුරු 4-72 ක විය යුතුයි.';
    } elseif (usernameExistsAnywhere($conn, $emp_username)) {
        $err = 'මෙම username එක දැනටමත් භාවිතයේ ඇත (employees + admins දෙකම පරීක්ෂා කළා).';
    }

    if ($err !== '') {
        writeLog('EMP_CREATE_FAILED', 'Validation: ' . $err);
        $message = errorMessageBox($err);
    } else {
        $emp_password = password_hash($raw_password, PASSWORD_DEFAULT);
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, shift_type, employee_type, lab_id) VALUES (:name, :username, :password, :shift, :employee_type, :lab_id)");
            $stmt->execute([
                ':name' => $emp_name,
                ':username' => $emp_username,
                ':password' => $emp_password,
                ':shift' => $emp_shift,
                ':employee_type' => $employee_type,
                ':lab_id' => $employee_type === 'lab' ? $lab_id : null
            ]);
            $new_id = $conn->lastInsertId();
            writeLog('EMP_CREATED', ucfirst($employee_type) . ' created: ' . $emp_username . ' (#' . $new_id . ')');
            $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> ' . ucfirst($employee_type) . ' <b>' . htmlspecialchars($emp_name) . '</b> සාර්ථකව සෑදිණි! ID: <b>#' . $new_id . '</b> | Username: <b>' . htmlspecialchars($emp_username) . '</b></div>';
        } catch (PDOException $e) {
            writeLog('EMP_CREATE_FAILED', 'Insert failed: ' . $emp_username);
            $message = errorMessageBox('ගිණුම සෑදීම අසාර්ථක විය. Username eka duplicate vela vune venna one.');
        }
    }
}

// Handle admin deletion
if (isset($_GET['delete_admin'])) {
    $del_id = (int)$_GET['delete_admin'];
    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $del_id]);
    $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Admin account එක සාර්ථකව මකන ලදී.</div>';
    header("Location: setup_passwords.php");
    exit();
}

if (isset($_POST['delete_employee'])) {
    $showAllStaff = true;
    $delId = (int)($_POST['employee_id'] ?? 0);
    $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE id = :id");
    $stmt->execute([':id' => $delId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($delId <= 0 || !$employee) {
        writeLog('EMP_DELETE_FAILED', 'Invalid employee ID: ' . $delId);
        $message = errorMessageBox('Employee account eka hambune naha.');
    } else {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("DELETE FROM leave_requests WHERE user_id = :id");
            $stmt->execute([':id' => $delId]);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $delId]);
            $conn->commit();
            writeLog('EMP_DELETED', 'Employee deleted: ' . $employee['username'] . ' (#' . $delId . ')');
            $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Employee account එක සාර්ථකව මකන ලදී.</div>';
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            writeLog('EMP_DELETE_FAILED', 'Database delete failed for User#' . $delId);
            $message = errorMessageBox('Employee account eka delete karanna bari una. Ayeth try karanna.');
        }
    }
}

$employees = $conn->query("SELECT users.*, labs.name AS lab_name FROM users LEFT JOIN labs ON labs.id = users.lab_id ORDER BY users.id")->fetchAll(PDO::FETCH_ASSOC);
$labs = $conn->query("SELECT id, name FROM labs WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT * FROM admin_users")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | Setup Passwords</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(24px); }
        .input-glow:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover { background: rgba(99, 102, 241, 0.08); }
        .sidebar-link.active { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(99, 102, 241, 0.2); color: #a5b4fc; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased flex">
    <aside id="adminSidebar" class="w-72 max-w-[85vw] bg-slate-950/95 lg:bg-slate-950/80 backdrop-blur-xl border-r border-slate-800/60 flex flex-col justify-between hidden lg:flex fixed inset-y-0 left-0 z-40 overflow-y-auto">
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
                <a href="admin.php?page=dashboard" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-chart-pie w-5 text-center text-indigo-400"></i> Dashboard
                </a>
                <a href="admin.php?page=lab-calendar" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-flask w-5 text-center"></i> Lab Leave Calendar
                </a>
                <a href="admin.php?page=rider-calendar" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-motorcycle w-5 text-center"></i> Rider Leave Calendar
                </a>
                <a href="admin.php?page=cancel-leaves" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-calendar-xmark w-5 text-center text-rose-400"></i> Cancel Leaves
                </a>
                <a href="admin.php?page=employees" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
                    <i class="fa-solid fa-users-gear w-5 text-center"></i> Manage Employees
                </a>
                <a href="setup_passwords.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl" aria-current="page">
                    <i class="fa-solid fa-user-plus w-5 text-center"></i> Add / Remove Staff
                </a>
                <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-xl text-slate-400">
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

    <main class="flex-1 lg:ml-72 flex flex-col min-w-0 min-h-screen">
        <header class="h-16 bg-slate-950/80 backdrop-blur-xl border-b border-slate-800/60 sticky top-0 z-30 flex items-center justify-between px-4 md:px-6">
            <div class="flex items-center gap-3">
                <button id="sidebarToggle" type="button" aria-controls="adminSidebar" aria-expanded="false" onclick="toggleAdminSidebar()" class="lg:hidden text-slate-400 hover:text-white transition p-2 rounded-lg hover:bg-slate-800/50">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="text-sm font-bold text-white">Leave Management / Add / Remove Staff</h1>
            </div>
            <a href="admin.php?page=dashboard" class="text-xs font-semibold text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-arrow-left"></i> Dashboard
            </a>
        </header>
        <div class="flex-1 p-4 md:p-8">
    <div class="max-w-3xl mx-auto space-y-8">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                    <span class="text-lg font-black text-white">AK</span>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">Password Setup</h1>
                    <p class="text-xs text-slate-400">Initialize login credentials</p>
                </div>
            </div>
            <a href="admin.php?page=dashboard" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <?= $message; ?>
        <?php endif; ?>

        <!-- Quick Setup -->
        <div class="glass-card border border-amber-500/20 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center">
                    <i class="fa-solid fa-bolt text-amber-400 text-sm"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white">Quick Setup</h2>
                    <p class="text-[11px] text-slate-400">Set default password <code class="bg-slate-800 px-1 rounded text-amber-300">leave123</code> for all employees</p>
                </div>
            </div>
            <form method="POST" class="flex justify-end">
                <button type="submit" name="setup_all" onclick="return confirm('සියලුම employees ට leave123 password ලබා දෙන්නේද?')"
                    class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition shadow-md">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Setup All Employees
                </button>
            </form>
        </div>

        <!-- Create Admin -->
        <div class="glass-card border border-slate-700/60 rounded-2xl p-6">
            <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-indigo-400"></i> Create Admin Account
            </h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="admin_name" required placeholder="Admin Name"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <input type="text" name="admin_username" required placeholder="Username (for login)"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <input type="email" name="admin_email" required placeholder="admin@akksolutions.local"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <input type="password" name="admin_password" required placeholder="Password"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <button type="submit" name="create_admin"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-indigo-600/20 md:col-span-2">
                    <i class="fa-solid fa-plus"></i> Create Admin
                </button>
            </form>
        </div>

        <!-- Set Individual Passwords -->
        <div class="glass-card border border-slate-700/60 rounded-2xl p-6">
            <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-key text-purple-400"></i> Set Employee Password
            </h2>
            <form method="POST" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Employee</label>
                    <select name="emp_id" required class="w-full bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all appearance-none cursor-pointer">
                        <option value="">-- Select --</option>
                        <?php foreach($employees as $emp): ?>
                            <option value="<?= $emp['id']; ?>">#<?= $emp['id']; ?> <?= htmlspecialchars($emp['name']); ?> (<?= htmlspecialchars($emp['username'] ?? 'no user') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">New Username</label>
                    <input type="text" name="emp_username" placeholder="Leave empty to keep current"
                        class="w-full bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">New Password</label>
                    <input type="password" name="emp_password" required placeholder="Enter password"
                        class="w-full bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <button type="submit" name="set_emp_password"
                    class="bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-5 py-3 rounded-xl transition shadow-md shadow-purple-600/20 flex-shrink-0">
                    <i class="fa-solid fa-check"></i> Set
                </button>
            </form>
        </div>

        <!-- Add New Employee -->
        <div class="glass-card border border-emerald-500/20 rounded-2xl p-6">
            <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-400"></i> Add New Employee
            </h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="emp_name" required placeholder="Employee Name"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                <input type="text" name="emp_new_username" required placeholder="Username (for login)"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                <select name="emp_shift" required class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all appearance-none cursor-pointer">
                    <option value="">-- Select Shift --</option>
                    <option value="M">Morning Shift</option>
                    <option value="E">Evening Shift</option>
                    <option value="BOTH">Both Shifts</option>
                </select>
                <select name="employee_type" id="setupEmployeeType" onchange="toggleSetupEmployeeType()"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all">
                    <option value="lab">Lab Employee</option>
                    <option value="rider">Rider</option>
                </select>
                <div id="setupLabWrap">
                    <select name="lab_id" id="setupLabId"
                        class="w-full bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        <option value="">-- Select Lab --</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?= (int)$lab['id']; ?>"><?= htmlspecialchars($lab['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="password" name="emp_new_password" required placeholder="Password"
                    class="bg-slate-800/60 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                <button type="submit" name="add_employee"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-emerald-600/20 md:col-span-2">
                    <i class="fa-solid fa-user-plus"></i> Add Employee
                </button>
            </form>
        </div>

        <!-- Current Status -->
        <div class="glass-card border border-slate-700/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/60">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-list text-emerald-400"></i> Current Status
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Admins -->
                    <div>
                        <h3 class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2">Admin Accounts</h3>
                        <?php if (count($admins) > 0): ?>
                            <?php foreach($admins as $a): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/40 mb-1.5">
                                    <div class="w-7 h-7 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 text-[10px] font-bold">AD</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-white"><?= htmlspecialchars($a['name']); ?></p>
                                        <p class="text-[10px] text-slate-400">@<?= htmlspecialchars($a['username'] ?? $a['email']); ?></p>
                                    </div>
                                    <a href="?delete_admin=<?= $a['id']; ?>" onclick="return confirm('මෙම Admin account එක මැකීමට තහවුරු කරන්න?')"
                                        class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-500">No admin accounts yet.</p>
                        <?php endif; ?>
                    </div>
                    <!-- Employees -->
                    <div>
                        <h3 class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mb-2">Employee Accounts</h3>
                                    <p class="text-xs text-slate-400 mb-3"><?= count($employees); ?> staff members</p>
                                    <button id="viewAllStaffButton" type="button" aria-controls="allStaffList" aria-expanded="<?= $showAllStaff ? 'true' : 'false'; ?>" onclick="toggleAllStaff()"
                                        class="bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl transition">
                                        <i class="fa-solid fa-users"></i> <span id="viewAllStaffLabel"><?= $showAllStaff ? 'Hide All Staff' : 'View All Staff'; ?></span>
                                    </button>
                                </div>
                            </div>

                            <div id="allStaffList" class="<?= $showAllStaff ? '' : 'hidden'; ?> mt-6 space-y-4">
                                <?php if (count($employees) > 0): ?>
                                    <?php foreach ($employees as $e): ?>
                                        <?php $employeeId = (int)$e['id']; ?>
                                        <article class="rounded-2xl border border-slate-700/60 bg-slate-900/50 p-4 md:p-5">
                                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="font-bold text-white">#<?= $employeeId; ?> <?= htmlspecialchars($e['name']); ?></h3>
                                                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-2 text-xs">
                                                        <div><dt class="text-slate-500">Username</dt><dd class="text-slate-200">@<?= htmlspecialchars($e['username'] ?? 'N/A'); ?></dd></div>
                                                        <div><dt class="text-slate-500">Email</dt><dd class="text-slate-200"><?= htmlspecialchars($e['email'] ?? '') ?: '-'; ?></dd></div>
                                                        <div><dt class="text-slate-500">Employee Number</dt><dd class="text-slate-200"><?= htmlspecialchars($e['emp_number'] ?? '') ?: '-'; ?></dd></div>
                                                        <div><dt class="text-slate-500">Position</dt><dd class="text-slate-200"><?= htmlspecialchars($e['position'] ?? '') ?: '-'; ?></dd></div>
                                                        <div><dt class="text-slate-500">Employee Type</dt><dd class="text-slate-200"><?= htmlspecialchars(ucfirst($e['employee_type'] ?? 'lab')); ?></dd></div>
                                                        <div><dt class="text-slate-500">Shift</dt><dd class="text-slate-200"><?= htmlspecialchars($e['shift_type'] ?? ''); ?></dd></div>
                                                        <div><dt class="text-slate-500">Lab</dt><dd class="text-slate-200"><?= htmlspecialchars($e['lab_name'] ?? '') ?: '-'; ?></dd></div>
                                                        <div><dt class="text-slate-500">Password</dt><dd class="<?= !empty($e['password']) ? 'text-emerald-400' : 'text-amber-400'; ?>"><?= !empty($e['password']) ? 'Set' : 'Not set'; ?></dd></div>
                                                    </dl>
                                                </div>
                                                <div class="flex items-center gap-2 sm:flex-shrink-0">
                                                    <button type="button" aria-controls="editEmployeeForm<?= $employeeId; ?>" aria-expanded="false" onclick="toggleEmployeeEditor(<?= $employeeId; ?>)"
                                                        class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                                                        <i class="fa-solid fa-pen"></i> Edit
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Delete this staff account and its leave requests?');">
                                                        <input type="hidden" name="employee_id" value="<?= $employeeId; ?>">
                                                        <button type="submit" name="delete_employee" class="bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-semibold px-3 py-2 rounded-lg transition">
                                                            <i class="fa-solid fa-trash-can"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <form id="editEmployeeForm<?= $employeeId; ?>" method="POST" class="hidden mt-5 border-t border-slate-700/60 pt-5">
                                                <input type="hidden" name="employee_id" value="<?= $employeeId; ?>">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Name
                                                        <input type="text" name="employee_name" required minlength="2" maxlength="100" value="<?= htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Username
                                                        <input type="text" name="employee_username" required minlength="3" maxlength="50" value="<?= htmlspecialchars($e['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Email
                                                        <input type="email" name="employee_email" maxlength="255" value="<?= htmlspecialchars($e['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Employee Number
                                                        <input type="text" name="employee_number" maxlength="50" value="<?= htmlspecialchars($e['emp_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Position
                                                        <input type="text" name="employee_position" maxlength="100" value="<?= htmlspecialchars($e['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Shift
                                                        <select name="employee_shift" required class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                            <option value="M" <?= $e['shift_type'] === 'M' ? 'selected' : ''; ?>>Morning</option>
                                                            <option value="E" <?= $e['shift_type'] === 'E' ? 'selected' : ''; ?>>Evening</option>
                                                            <option value="BOTH" <?= $e['shift_type'] === 'BOTH' ? 'selected' : ''; ?>>Both shifts</option>
                                                        </select>
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase">Employee Type
                                                        <select name="employee_type" id="employeeType<?= $employeeId; ?>" required onchange="toggleEmployeeLab(<?= $employeeId; ?>)"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                            <option value="lab" <?= ($e['employee_type'] ?? 'lab') === 'lab' ? 'selected' : ''; ?>>Lab employee</option>
                                                            <option value="rider" <?= ($e['employee_type'] ?? 'lab') === 'rider' ? 'selected' : ''; ?>>Rider</option>
                                                        </select>
                                                    </label>
                                                    <label id="employeeLabWrap<?= $employeeId; ?>" class="text-[10px] font-semibold text-slate-400 uppercase">
                                                        Lab
                                                        <select name="employee_lab_id" id="employeeLab<?= $employeeId; ?>"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                            <option value="">-- Select Lab --</option>
                                                            <?php foreach ($labs as $lab): ?>
                                                                <option value="<?= (int)$lab['id']; ?>" <?= (int)($e['lab_id'] ?? 0) === (int)$lab['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($lab['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="text-[10px] font-semibold text-slate-400 uppercase sm:col-span-2">New Password <span class="normal-case text-slate-500">(optional; leave blank to keep current)</span>
                                                        <input type="password" name="employee_password" minlength="4" maxlength="72" autocomplete="new-password"
                                                            class="mt-1 w-full bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white">
                                                    </label>
                                                </div>
                                                <div class="mt-4 flex justify-end gap-2">
                                                    <button type="button" onclick="toggleEmployeeEditor(<?= $employeeId; ?>)" class="text-slate-300 hover:text-white text-xs font-semibold px-4 py-2 rounded-lg border border-slate-700">Cancel</button>
                                                    <button type="submit" name="save_employee" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                                                        <i class="fa-solid fa-floppy-disk"></i> Save Details
                                                    </button>
                                                </div>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-slate-400">No staff accounts found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </main>
    <script>
    function toggleAdminSidebar() {
        var sidebar = document.getElementById('adminSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var isOpening = sidebar.classList.contains('hidden');
        sidebar.classList.toggle('hidden', !isOpening);
        toggle.setAttribute('aria-expanded', String(isOpening));
    }

    document.querySelectorAll('#adminSidebar .sidebar-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                document.getElementById('adminSidebar').classList.add('hidden');
                document.getElementById('sidebarToggle').setAttribute('aria-expanded', 'false');
            }
        });
    });

    function toggleAllStaff() {
        var list = document.getElementById('allStaffList');
        var button = document.getElementById('viewAllStaffButton');
        var label = document.getElementById('viewAllStaffLabel');
        var isOpening = list.classList.contains('hidden');
        list.classList.toggle('hidden', !isOpening);
        button.setAttribute('aria-expanded', String(isOpening));
        label.textContent = isOpening ? 'Hide All Staff' : 'View All Staff';
    }

    function toggleEmployeeEditor(employeeId) {
        var form = document.getElementById('editEmployeeForm' + employeeId);
        var button = document.querySelector('[aria-controls="editEmployeeForm' + employeeId + '"]');
        var isOpening = form.classList.contains('hidden');
        form.classList.toggle('hidden', !isOpening);
        button.setAttribute('aria-expanded', String(isOpening));
    }

    function toggleEmployeeLab(employeeId) {
        var isLabEmployee = document.getElementById('employeeType' + employeeId).value === 'lab';
        var labWrap = document.getElementById('employeeLabWrap' + employeeId);
        var labSelect = document.getElementById('employeeLab' + employeeId);
        labWrap.classList.toggle('hidden', !isLabEmployee);
        labSelect.required = isLabEmployee;
    }

    document.querySelectorAll('#allStaffList select[id^="employeeType"]').forEach(function(select) {
        toggleEmployeeLab(select.id.replace('employeeType', ''));
    });

    function toggleSetupEmployeeType() {
        const isLabEmployee = document.getElementById('setupEmployeeType').value === 'lab';
        document.getElementById('setupLabWrap').style.display = isLabEmployee ? '' : 'none';
        document.getElementById('setupLabId').required = isLabEmployee;
    }
    toggleSetupEmployeeType();
    </script>
</body>
</html>
