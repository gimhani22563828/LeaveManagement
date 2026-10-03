<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireAdmin();

$message = "";

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

    $err = '';
    if ($emp_name === '' || $emp_username === '' || $emp_shift === '' || $raw_password === '') {
        $err = 'සියලුම fields (නම, username, shift, password) අනිවාර්යයි. Name saha password nathiwa account hadanna beri.';
    } elseif (mb_strlen($emp_name) < 2 || mb_strlen($emp_name) > 100) {
        $err = 'නම අකුරු 2-100 ක් විය යුතුයි.';
    } elseif (!validUsernameFormat($emp_username)) {
        $err = 'Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).';
    } elseif (!in_array($emp_shift, ['M', 'E', 'BOTH'], true)) {
        $err = 'වලංගු shift එකක් තෝරන්න (M / E / BOTH).';
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
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, shift_type) VALUES (:name, :username, :password, :shift)");
            $stmt->execute([':name' => $emp_name, ':username' => $emp_username, ':password' => $emp_password, ':shift' => $emp_shift]);
            $new_id = $conn->lastInsertId();
            writeLog('EMP_CREATED', 'Employee created: ' . $emp_username . ' (#' . $new_id . ')');
            $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Employee <b>' . htmlspecialchars($emp_name) . '</b> සාර්ථකව සෑදිණි! ID: <b>#' . $new_id . '</b> | Username: <b>' . htmlspecialchars($emp_username) . '</b></div>';
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

// Handle employee deletion
if (isset($_GET['delete_emp'])) {
    $del_id = (int)$_GET['delete_emp'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $del_id]);
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE user_id = :id");
    $stmt->execute([':id' => $del_id]);
    $message = '<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs"><i class="fa-solid fa-circle-check"></i> Employee account එක සාර්ථකව මකන ලදී.</div>';
    header("Location: setup_passwords.php");
    exit();
}

$employees = $conn->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
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
    </style>
</head>
<body class="bg-slate-950 min-h-screen p-4 md:p-8 antialiased">
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
            <a href="login.php" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Login
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
                        <div class="space-y-1 max-h-48 overflow-y-auto">
                            <?php foreach($employees as $e): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/40">
                                    <div class="w-7 h-7 rounded-full <?= !empty($e['password']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'; ?> flex items-center justify-center text-[10px] font-bold">
                                        <?= !empty($e['password']) ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-xmark"></i>'; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-white">#<?= $e['id']; ?> <?= htmlspecialchars($e['name']); ?></p>
                                        <p class="text-[10px] text-slate-400"><?= htmlspecialchars($e['username'] ?? 'N/A'); ?> &middot; <?= $e['shift_type']; ?> Shift <?= !empty($e['password']) ? '&middot; <span class="text-emerald-400">Ready</span>' : '&middot; <span class="text-amber-400">No Password</span>'; ?></p>
                                    </div>
                                    <a href="?delete_emp=<?= $e['id']; ?>" onclick="return confirm('මෙම Employee account එක මැකීමට තහවුරු කරන්න?')"
                                        class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
