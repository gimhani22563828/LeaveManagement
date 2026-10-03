<?php
session_start();
include 'db.php';
include 'logger.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_role'] === 'admin' ? 'admin.php' : 'index.php'));
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$error = "";
$success = "";
$activeTab = 'login';

// =====================================================================
// ACCOUNT BLOCK: Create Account / Manage Account dan login.php eken
// wada karanne Naha. Okkoma create_account.php (OWNER GATE) ekata yanna.
// Username + password (owner) dala witharak eka atharata yanna puluwan.
// =====================================================================
if (isset($_POST['register']) || isset($_POST['delete_account']) || isset($_POST['edit_account'])) {
    $blockedAction = isset($_POST['register']) ? 'register'
        : (isset($_POST['delete_account']) ? 'delete_account' : 'edit_account');
    writeLog('ACCOUNT_TAB_BLOCKED', 'Blocked via login.php POST: ' . $blockedAction);
    header('Location: create_account.php');
    exit();
}

$stmt = $conn->query("SELECT COUNT(*) FROM admin_users");
$noAdmins = ((int)$stmt->fetchColumn() === 0);
$justInstalled = isset($_GET['installed']);

// =====================================================================
// Create Account - direct access (no admin gate).
// Hama request ekama Create Account panel eka open. Security nisa
// admin account hadanne nam Admin Key eka illanawa - edit/delete karanna
// admin password eka illanawa (create_account security).
// =====================================================================
$codes = getAccessCodes($conn);

$regUnlocked = true;

// Edit/Delete wala admin password verify kirimata (no-op function ekak)
function regRelock() {}

// Create Account / Manage Account direct tab links dan create_account.php
// (owner gate) ekata redirect wenawa.
if (isset($_GET['tab']) && ($_GET['tab'] === 'register' || $_GET['tab'] === 'manage')) {
    header('Location: create_account.php');
    exit();
}

if (isset($_POST['register'])) {
    $regName     = trim($_POST['reg_name'] ?? '');
    $regUsername = trim($_POST['reg_username'] ?? '');
    $regPassword = $_POST['reg_password'] ?? '';
    $regShift    = trim($_POST['reg_shift'] ?? '');
    $regPosition = trim($_POST['reg_position'] ?? '');
    $regNumber   = trim($_POST['reg_number'] ?? '');
    $regRole     = ($_POST['reg_role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';
    $regAdminKey = trim($_POST['reg_admin_key'] ?? '');
    $activeTab = 'register';

    if ($regName === '' || $regUsername === '' || $regPassword === '') {
        $error = "Name, Username saha Password okkoma anivaryayi. Eewa nathiwa account hadanna beri.";
    } elseif ($regRole === 'employee' && $regShift === '') {
        $error = "Shift ekak thooranna.";
    } elseif (mb_strlen($regName) < 2 || mb_strlen($regName) > 100) {
        $error = "නම අකුරු 2-100 ක් විය යුතුයි.";
    } elseif (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $regUsername)) {
        $error = "Username අකුරු 3-50 ක විය යුතුයි (a-z, 0-9, . _ පමණයි).";
    } elseif ($regRole === 'employee' && !in_array($regShift, ['M', 'E', 'BOTH'], true)) {
        $error = "වලංගු shift එකක් තෝරන්න (M / E / BOTH).";
    } elseif ($regRole === 'employee' && $regPosition === '') {
        $error = "Position eka danna (me hama employee kenekutama anivariya - ud ah: Manager, Supervisor, Operator).";
    } elseif (mb_strlen($regPosition) > 100) {
        $error = "Position eka akuru 100 n ithi witharak venna one.";
    } elseif ($regNumber === '') {
        $error = $regRole === 'admin' ? "Admin number eka danna (me hama admin account ekata anivariya)." : "Employee number eka danna (me hama employee kenekutama anivariya).";
    } elseif (mb_strlen($regNumber) > 50) {
        $error = "Number eka akuru 50 n ithi witharak venna one.";
    } elseif (strlen($regPassword) < 4 || strlen($regPassword) > 72) {
        $error = "Password අකුරු 4-72 අතර විය යුතුයි.";
    } elseif ($regRole === 'admin' && !hash_equals($codes['admin_key'], $regAdminKey)) {
        writeLog('REGISTER_FAILED', 'Invalid admin key: ' . $regUsername);
        $error = "Admin Key eka veradi. Admin account hadanna beri.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
        $stmt->execute([':u' => $regUsername]);
        $exists = (int)$stmt->fetchColumn();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :u");
        $stmt->execute([':u' => $regUsername]);
        $exists += (int)$stmt->fetchColumn();

        if ($exists > 0) {
            writeLog('REGISTER_FAILED', 'Duplicate username: ' . $regUsername);
            $error = "මෙම username එක දැනටමත් භාවිතයේ ඇත. වෙනත් එකක් උත්සාහ කරන්න.";
        } else {
            try {
                if ($regRole === 'admin') {
                    $stmt = $conn->prepare("INSERT INTO admin_users (name, email, password, username, position, emp_number)
                                            VALUES (:n, :e, :p, :u, :pos, :num)");
                    $stmt->execute([
                        ':n' => $regName,
                        ':e' => null,
                        ':p' => password_hash($regPassword, PASSWORD_DEFAULT),
                        ':u' => $regUsername,
                        ':pos' => $regPosition,
                        ':num' => $regNumber
                    ]);
                    writeLog('ACCOUNT_CREATED', 'ADMIN account created via register: ' . $regUsername);
                    $success = "Admin account eka sampurnayenma saduni! Dan Sign In karanna.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, username, shift_type, position, emp_number)
                                            VALUES (:n, :e, :p, :u, :s, :pos, :num)");
                    $stmt->execute([
                        ':n' => $regName,
                        ':e' => null,
                        ':p' => password_hash($regPassword, PASSWORD_DEFAULT),
                        ':u' => $regUsername,
                        ':s' => $regShift,
                        ':pos' => $regPosition,
                        ':num' => $regNumber
                    ]);
                    writeLog('ACCOUNT_CREATED', 'Employee account created: ' . $regUsername . ' (' . $regShift . ')');
                    $success = "Employee account eka sampurnayenma saduni! Dan Sign In karanna.";
                }
                regRelock();
                $activeTab = 'login';
            } catch (PDOException $e) {
                writeLog('REGISTER_FAILED', 'Insert failed: ' . $regUsername);
                $error = "ගිණුම සෑදීම අසාර්ථක විය. නැවත උත්සාහ කරන්න.";
            }
        }
    }
}

if (isset($_POST['delete_account'])) {
    $activeTab = 'manage';
        // Admin password eka dala witharak delete karanna (REAL verify - JS witharak nemei).
        // Gate eka athi kalin EKA admin kenekge password eka balat. Dan hama admin kenekge
        // password eka hari nam witharak delete wenawa.
        $delPass = trim($_POST['del_pass'] ?? '');
        $verified = false;
        if ($delPass !== '') {
            $admins = $conn->query("SELECT password FROM admin_users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $ad) {
                if (password_verify($delPass, (string)$ad['password'])) { $verified = true; break; }
            }
        }
        if (!$verified) {
            writeLog('DELETE_BLOCKED', 'Wrong admin password for delete');
            $error = "Admin password eka veradi nethnam liyala na. Mulin admin password eka liyana Delete button eka press karanna.";
        } else {
        $delTarget = trim($_POST['del_target'] ?? '');
        $target = $delTarget;
        $parts = explode(':', $target);
        $type = ($parts[0] ?? '') === 'adm' ? 'adm' : (($parts[0] ?? '') === 'emp' ? 'emp' : '');
        $delId = (int)($parts[1] ?? 0);

        if ($type === '' || $delId <= 0) {
            writeLog('DELETE_FAILED', 'Invalid target selected');
            $error = "Meka list eken account ekak thooranna.";
        } elseif ($type === 'adm') {
            if ((int)$conn->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() <= 1) {
                writeLog('DELETE_BLOCKED', 'Attempt to delete last remaining admin');
                $error = "Meeka pariththikaya thiyena admin account eka. Eeka delete karanna beri.";
            } else {
            $stmt = $conn->prepare("SELECT name, username FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $delId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                writeLog('DELETE_FAILED', 'Admin #' . $delId . ' not found');
                $error = "Meya account eka hambila nahe (kalin delete wela venna one).";
            } else {
                $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = :id");
                $stmt->execute([':id' => $delId]);
                writeLog('ACCOUNT_DELETED', 'Admin deleted via register page: ' . $row['username'] . ' (#' . $delId . ')');
                $success = "Admin account eka (" . htmlspecialchars($row['name']) . ") sampurnayenma delete kala.";
                regRelock();
            }
            }
        } else {
            $stmt = $conn->prepare("SELECT name, username FROM users WHERE id = :id");
            $stmt->execute([':id' => $delId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                writeLog('DELETE_FAILED', 'Employee #' . $delId . ' not found');
                $error = "Meya account eka hambila nahe (kalin delete wela venna one).";
            } else {
                $stmt = $conn->prepare("DELETE FROM leave_requests WHERE user_id = :id");
                $stmt->execute([':id' => $delId]);
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $delId]);
                writeLog('ACCOUNT_DELETED', 'Employee deleted via register page: ' . $row['username'] . ' (#' . $delId . ')');
                $success = "Employee account eka (" . htmlspecialchars($row['name']) . ") saha eyage okkoma nivadu tika sampurnayenma delete kala.";
                regRelock();
            }
        }
        }
    }

if (isset($_POST['edit_account'])) {
    $activeTab = 'manage';
        // Admin password eka dala witharak edit/save karanna (REAL verify).
        // Gate eka athi kalin EKA admin kenekge password eka balat. Dan hama admin
        // kenekge password eka hari nam witharak Save wenawa.
        $editPass = trim($_POST['edit_pass'] ?? '');
        $verified = false;
        if ($editPass !== '') {
            $admins = $conn->query("SELECT password FROM admin_users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $ad) {
                if (password_verify($editPass, (string)$ad['password'])) { $verified = true; break; }
            }
        }
        if (!$verified) {
            writeLog('EDIT_BLOCKED', 'Wrong admin password for edit');
            $error = "Admin password eka veradi nethnam liyala na. Mulin admin password eka liyanna - eka hari nam witharak Save wenawa.";
        } else {
        $target = trim($_POST['edit_target'] ?? '');
        $parts  = explode(':', $target);
        $etype  = ($parts[0] ?? '') === 'adm' ? 'adm' : (($parts[0] ?? '') === 'emp' ? 'emp' : '');
        $eid    = (int)($parts[1] ?? 0);
        $newName  = trim($_POST['edit_name'] ?? '');
        $newUser  = trim($_POST['edit_username'] ?? '');
        $newPass  = $_POST['edit_password'] ?? '';
        $newCon   = $_POST['edit_confirm'] ?? '';
        $newShift = $_POST['edit_shift'] ?? '';
        $newPos   = trim($_POST['edit_position'] ?? '');
        $newNum   = trim($_POST['edit_number'] ?? '');

        $err = '';
        if ($etype === '' || $eid <= 0) {
            $err = "Account eka hambila nahe.";
        } elseif ($newName === '' || mb_strlen($newName) < 2 || mb_strlen($newName) > 100) {
            $err = "Name eka akuru 2-100 athara venna one.";
        } elseif (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $newUser)) {
            $err = "Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.";
        } elseif ($newPass !== '' && (strlen($newPass) < 4 || strlen($newPass) > 72)) {
            $err = "Alut password eka akuru 4-72 athara venna one.";
        } elseif ($newPass !== '' && $newPass !== $newCon) {
            $err = "Alut password deka samana nahi.";
        } elseif ($etype === 'emp' && !in_array($newShift, ['M', 'E', 'BOTH'], true)) {
            $err = "Shift eka M / E / BOTH witharak venna one.";
        } elseif ($etype === 'emp' && $newPos === '') {
            $err = "Position eka danna.";
        } elseif (mb_strlen($newPos) > 100) {
            $err = "Position eka akuru 100 n ithi witharak venna one.";
        } elseif ($newNum === '') {
            $err = $etype === 'adm' ? "Admin number eka danna." : "Employee number eka danna.";
        } elseif (mb_strlen($newNum) > 50) {
            $err = "Number eka akuru 50 n ithi witharak venna one.";
        }

        if ($err === '') {
            try {
                $tbl = $etype === 'adm' ? 'admin_users' : 'users';
                $otherTbl = $etype === 'adm' ? 'users' : 'admin_users';
                $s1 = $conn->prepare("SELECT COUNT(*) FROM $tbl WHERE username = :u AND id != :id");
                $s1->execute([':u' => $newUser, ':id' => $eid]);
                $dup = (int)$s1->fetchColumn();
                $s2 = $conn->prepare("SELECT COUNT(*) FROM $otherTbl WHERE username = :u");
                $s2->execute([':u' => $newUser]);
                $dup += (int)$s2->fetchColumn();
                if ($dup > 0) {
                    $err = "Username eka danath kawuruhari use karanawa. Vena ekak danna.";
                }
            } catch (PDOException $e) {
                $err = "Check karadi awul unaha. Ayeth try karanna.";
            }
        }

        if ($err !== '') {
            writeLog('EDIT_FAILED', ucfirst($etype) . " #$eid: $err");
            $error = $err;
        } else {
            try {
                if ($etype === 'adm') {
                    if ($newPass !== '') {
                        $stmt = $conn->prepare("UPDATE admin_users SET name = :n, username = :u, password = :p, position = :pos, emp_number = :num WHERE id = :id");
                        $stmt->execute([':n' => $newName, ':u' => $newUser, ':p' => password_hash($newPass, PASSWORD_DEFAULT), ':pos' => $newPos, ':num' => $newNum, ':id' => $eid]);
                    } else {
                        $stmt = $conn->prepare("UPDATE admin_users SET name = :n, username = :u, position = :pos, emp_number = :num WHERE id = :id");
                        $stmt->execute([':n' => $newName, ':u' => $newUser, ':pos' => $newPos, ':num' => $newNum, ':id' => $eid]);
                    }
                } else {
                    if ($newPass !== '') {
                        $stmt = $conn->prepare("UPDATE users SET name = :n, username = :u, shift_type = :s, password = :p, position = :pos, emp_number = :num WHERE id = :id");
                        $stmt->execute([':n' => $newName, ':u' => $newUser, ':s' => $newShift, ':p' => password_hash($newPass, PASSWORD_DEFAULT), ':pos' => $newPos, ':num' => $newNum, ':id' => $eid]);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET name = :n, username = :u, shift_type = :s, position = :pos, emp_number = :num WHERE id = :id");
                        $stmt->execute([':n' => $newName, ':u' => $newUser, ':s' => $newShift, ':pos' => $newPos, ':num' => $newNum, ':id' => $eid]);
                    }
                }
                writeLog('ACCOUNT_UPDATED', ucfirst($etype) . " #$eid updated: $newUser" . ($newPass !== '' ? ' (password changed)' : ''));
                $success = ($etype === 'adm' ? 'Admin' : 'Employee') . " account eka update kala (#$eid - " . htmlspecialchars($newUser) . ").";
                regRelock();
            } catch (PDOException $e) {
                writeLog('EDIT_FAILED', 'Update failed for #' . $eid);
                $error = "Update kirima asaru una. Ayeth try karanna.";
            }
        }
        }
    }

// Forgot Password handler
if (isset($_POST['forgot_password'])) {
    $fpUser = trim($_POST['fp_username'] ?? '');
    $activeTab = 'forgot';
    if ($fpUser === '') {
        $error = "Username eka liyanna.";
    } else {
        // Check both tables
        $stmt = $conn->prepare("SELECT id, name, username FROM admin_users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $fpUser]);
        $fpRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $fpType = 'admin';
        if (!$fpRow) {
            $stmt = $conn->prepare("SELECT id, name, username FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $fpUser]);
            $fpRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $fpType = 'employee';
        }
        if (!$fpRow) {
            writeLog('PASSWORD_RESET_FAILED', 'Username not found: ' . $fpUser);
            $error = "මෙම username එක සඳහා ගිණුමක් හමු නොවීය.";
        } else {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $conn->prepare("INSERT INTO password_resets (user_type, user_id, token, expires_at) VALUES (:type, :uid, :token, :expires)");
            $stmt->execute([':type' => $fpType, ':uid' => $fpRow['id'], ':token' => $token, ':expires' => $expires]);
            writeLog('PASSWORD_RESET_REQUESTED', "User: $fpUser (#{$fpRow['id']} $fpType)");
            $success = "Reset token eka hambila! Me token eka Admin keneku ta danna - admin eken oyage password eka reset kara givvima. Token: <b class='font-mono text-lg'>$token</b>";
        }
    }
}

if (isset($_GET['tab']) && $_GET['tab'] === 'forgot') {
    $activeTab = 'forgot';
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username saha password dekama anivaryayi. Histan tida nathuwa login karanna beri.";
        writeLog('LOGIN_FAILED', 'Empty credentials');
    }

    // Check admin login
    if (empty($error)) {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_name'] = $admin['name'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['user_email'] = $admin['email'] ?? '';
                $_SESSION['user_username'] = $admin['username'];
                writeLog('LOGIN_SUCCESS', 'Admin logged in');
                header("Location: admin.php");
                exit();
            } else {
                writeLog('LOGIN_FAILED', 'Wrong password for admin: ' . $username);
                $error = "මුරපදය වැරදියි. නැවත උත්සාහ කරන්න.";
            }
        }
    }

    if (empty($error)) {
        // Check employee login
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($emp) {
            if (password_verify($password, $emp['password'])) {
                $_SESSION['user_id'] = $emp['id'];
                $_SESSION['user_name'] = $emp['name'];
                $_SESSION['user_role'] = 'employee';
                $_SESSION['user_email'] = $emp['email'] ?? '';
                $_SESSION['user_username'] = $emp['username'];
                $_SESSION['user_shift'] = $emp['shift_type'];
                writeLog('LOGIN_SUCCESS', 'Employee logged in');
                header("Location: index.php");
                exit();
            } else {
                writeLog('LOGIN_FAILED', 'Wrong password for employee: ' . $username);
                $error = "මුරපදය වැරදියි. නැවත උත්සාහ කරන්න.";
            }
        }
    }

    if (empty($error)) {
        writeLog('LOGIN_FAILED', 'Account not found: ' . $username);
        $error = "මෙම username එක සඳහා ගිණුමක් හමු නොවීය.";
    }
}

$empList = $regUnlocked ? $conn->query("SELECT id, name, username, shift_type, position, emp_number FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) : [];
$admList = $regUnlocked ? $conn->query("SELECT id, name, username, position, emp_number FROM admin_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) : [];

$editAcc = null;
if ($regUnlocked) {
    $eparts = explode(':', trim($_GET['edit'] ?? ''));
    $etype = ($eparts[0] ?? '') === 'adm' ? 'adm' : (($eparts[0] ?? '') === 'emp' ? 'emp' : '');
    $eid = (int)($eparts[1] ?? 0);
    if ($etype !== '' && $eid > 0) {
        $stmt = $conn->prepare("SELECT * FROM " . ($etype === 'adm' ? 'admin_users' : 'users') . " WHERE id = :id");
        $stmt->execute([':id' => $eid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $editAcc = $row;
            $editAcc['atype'] = $etype;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .gradient-border {
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #a855f7, #6366f1);
            background-size: 300% 300%;
            animation: gradientShift 4s ease infinite;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .float-orb {
            animation: floatOrb 8s ease-in-out infinite;
        }
        .float-orb-delay {
            animation: floatOrb 8s ease-in-out infinite 3s;
        }
        @keyframes floatOrb {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        .btn-shine {
            position: relative;
            overflow: hidden;
        }
        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 200%;
            height: 200%;
            background: linear-gradient(60deg, transparent 40%, rgba(255,255,255,0.08) 50%, transparent 60%);
            animation: btnShine 3s ease-in-out infinite;
        }
        @keyframes btnShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body class="bg-slate-950 min-h-screen p-4 py-8 relative overflow-y-auto block">

    <!-- Animated Background -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-indigo-600/8 rounded-full blur-[120px] float-orb"></div>
        <div class="absolute bottom-1/4 right-1/4 w-[400px] h-[400px] bg-purple-600/8 rounded-full blur-[100px] float-orb-delay"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-600/5 rounded-full blur-[150px]"></div>
        <!-- Grid Pattern -->
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(99, 102, 241, 0.06) 1px, transparent 1px); background-size: 40px 40px;"></div>
    </div>

    <div class="w-full max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-0 z-10 relative">

        <!-- Left Panel - Branding -->
        <div class="hidden lg:flex flex-col justify-center items-center p-12 relative">
            <div class="absolute inset-0 rounded-l-3xl overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/20 via-purple-600/10 to-slate-900/80"></div>
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 24px 24px;"></div>
            </div>
            <div class="relative z-10 text-center space-y-8">
                <!-- Logo -->
                <div class="flex justify-center">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-2xl shadow-indigo-500/30 relative">
                        <span class="text-3xl font-black text-white">AK</span>
                        <div class="absolute -inset-1 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 opacity-20 blur-lg"></div>
                    </div>
                </div>
                <div>
                    <h1 class="text-4xl font-extrabold text-white tracking-tight">
                        AKK <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">SOLUTIONS</span>
                    </h1>
                        <p class="text-slate-400 mt-2 text-sm tracking-wide">Automated Management System</p>
                </div>
                <!-- Feature Cards -->
                <div class="space-y-3 max-w-xs mx-auto">
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="w-9 h-9 rounded-lg bg-indigo-500/15 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-bolt text-indigo-400 text-sm"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-semibold text-white">Smart Auto-Approval</p>
                            <p class="text-[10px] text-slate-400">Real-time rule validation</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="w-9 h-9 rounded-lg bg-purple-500/15 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-shield-halved text-purple-400 text-sm"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-semibold text-white">Shift Coverage Protection</p>
                            <p class="text-[10px] text-slate-400">Always maintain minimum staff</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/5">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-chart-line text-emerald-400 text-sm"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-xs font-semibold text-white">Admin Dashboard</p>
                            <p class="text-[10px] text-slate-400">Calendar, metrics & approvals</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="flex items-start justify-center p-8 lg:p-12">
            <div class="w-full max-w-md space-y-8">

                <!-- Mobile Logo -->
                <div class="flex items-center gap-3 lg:hidden">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <span class="text-lg font-black text-white">AK</span>
                    </div>
                    <span class="text-xl font-bold tracking-wide text-white">AKK <span class="text-indigo-400">SOLUTIONS</span></span>
                </div>

                <!-- Tab Switcher -->
                <div class="grid grid-cols-1 gap-1 p-1 rounded-xl bg-slate-800/60 border border-slate-700/80">
                    <button type="button" id="tabLoginBtn" onclick="showPanel('login')"
                        class="py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all">Sign In</button>
                </div>

                <!-- Owner-only account entries (create_account.php owner gate) -->
                <div class="grid grid-cols-2 gap-2">
                    <a href="create_account.php" class="text-center py-2.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all bg-slate-800/40 border border-slate-700/60 text-amber-300 hover:text-amber-200 hover:border-amber-500/40">
                        <i class="fa-solid fa-user-plus mr-1"></i> Create Account
                    </a>
                    <a href="create_account.php" class="text-center py-2.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition-all bg-slate-800/40 border border-slate-700/60 text-amber-300 hover:text-amber-200 hover:border-amber-500/40">
                        <i class="fa-solid fa-users-gear mr-1"></i> Manage Accounts
                    </a>
                    <p class="col-span-2 text-[10px] text-slate-500 text-center -mt-1">
                        <i class="fa-solid fa-lock mr-1 text-amber-500"></i>Owner username + password dala witharak
                    </p>
                </div>

                <!-- Messages (both tabs) -->
                <?php if (!empty($error)): ?>
                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/25 flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-rose-400 text-sm mt-0.5"></i>
                    <p class="text-xs text-rose-300 font-medium"><?= $error; ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/25 flex items-start gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-sm mt-0.5"></i>
                    <p class="text-xs text-emerald-300 font-medium"><?= $success; ?></p>
                </div>
                <?php endif; ?>

                <!-- Login Panel -->
                <div id="panelLogin" class="space-y-5">
                    <?php if ($noAdmins): ?>
                    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-400 text-sm mt-0.5"></i>
                        <div class="text-xs text-amber-200">
                            <b>First-Time Setup:</b> Mulma admin account eka thawam hadala nathi nisa,
                            <a href="install.php" class="underline font-bold text-amber-300 hover:text-amber-200">install.php</a>
                            eken Owner account eka hadanna.
                        </div>
                    </div>
                    <?php elseif ($justInstalled): ?>
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3">
                        <i class="fa-solid fa-circle-check text-emerald-400 text-sm mt-0.5"></i>
                        <div class="text-xs text-emerald-200">
                            <b>Setup sampurnayi!</b> Dan oyage admin account eken Sign In wenna.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <h2 class="text-2xl font-bold text-white">Welcome back</h2>
                        <p class="text-slate-400 text-sm mt-1">Sign in to your account to continue</p>
                    </div>

                <!-- Login Form -->
                <form method="POST" class="space-y-5">
                    <!-- Username -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-user text-slate-500 text-sm"></i>
                            </div>
                            <input type="text" name="username" required placeholder="Enter your username"
                                class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-lock text-slate-500 text-sm"></i>
                            </div>
                            <input type="password" name="password" id="password" required placeholder="Enter your password"
                                class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-12 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-slate-300 transition">
                                <i class="fa-solid fa-eye text-sm" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-0 cursor-pointer">
                            <span class="text-xs text-slate-400">Remember me</span>
                        </label>
                        <button type="button" onclick="showPanel('forgot')" class="text-xs text-rose-400 hover:text-rose-300 font-medium transition">Forgot Password?</button>
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="login"
                        class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-semibold py-3.5 px-6 rounded-xl transition-all shadow-lg shadow-indigo-600/25 hover:shadow-indigo-500/40 flex items-center justify-center gap-2 text-sm btn-shine">
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In
                    </button>
                </form>
                </div>

                <!-- Register + Manage panels dan akriya (owner gate create_account.php) -->
                <?php if (false): ?>
                <!-- Register Panel -->
                <div id="panelRegister" class="space-y-5 hidden">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Create account</h2>
                        <p class="text-slate-400 text-sm mt-1">අලුත් ගිණුමක් සාදා ගන්න</p>
                    </div>

                    <form method="POST" class="space-y-4">
                        <!-- Account Type -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Account Type</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-user-tag text-slate-500 text-sm"></i>
                                </div>
                                <select name="reg_role" id="regRole" onchange="toggleRegRole()" required
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="employee">Employee Account</option>
                                    <option value="admin">Admin Account (Key eka one)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Full Name</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-id-card text-slate-500 text-sm"></i>
                                </div>
                                <input type="text" name="reg_name" required placeholder="Enter your full name"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                        </div>

                        <!-- Username -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-user text-slate-500 text-sm"></i>
                                </div>
                                <input type="text" name="reg_username" required placeholder="Choose a username"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                        </div>

                        <!-- Shift (employees only) -->
                        <div id="regShiftWrap" class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Shift</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-clock text-slate-500 text-sm"></i>
                                </div>
                                <select name="reg_shift" id="regShift" required
                                    class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                                    <option value="">-- Select Shift --</option>
                                    <option value="M">Morning Shift</option>
                                    <option value="E">Evening Shift</option>
                                    <option value="BOTH">Both Shifts</option>
                                </select>
                            </div>
                        </div>

                        <!-- Position -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Position</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-briefcase text-slate-500 text-sm"></i>
                                </div>
                                <input type="text" name="reg_position" id="regPosition" placeholder="Job position eka danna (ud ah: Manager, Supervisor, Operator)"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                        </div>

                        <!-- Employee/Admin Number -->
                        <div class="space-y-1.5">
                            <label id="regNumberLabel" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Employee Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-hashtag text-slate-500 text-sm"></i>
                                </div>
                                <input type="text" name="reg_number" id="regNumber" required placeholder="Company employee number eka danna (ud ah: 014, EMP005)"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            </div>
                        </div>

                        <!-- Admin Key (admins only) -->
                        <div id="regKeyWrap" class="space-y-1.5 hidden">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Admin Key</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-key text-amber-400 text-sm"></i>
                                </div>
                                <input type="password" name="reg_admin_key" id="regAdminKey" placeholder="Company admin key eka danna"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
                            </div>
                            <p class="text-[10px] text-slate-500 ml-1"><i class="fa-solid fa-shield-halved mr-1 text-amber-500"></i>Admin account hadanna company ge Admin Key eka one (security nisa)</p>
                        </div>

                        <!-- Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-lock text-slate-500 text-sm"></i>
                                </div>
                                <input type="password" name="reg_password" id="regPassword" required placeholder="Choose a password (min 4 characters)"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-12 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                                <button type="button" onclick="toggleRegPassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-500 hover:text-slate-300 transition">
                                    <i class="fa-solid fa-eye text-sm" id="regEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" name="register"
                            class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold py-3.5 px-6 rounded-xl transition-all shadow-lg shadow-emerald-600/25 hover:shadow-emerald-500/40 flex items-center justify-center gap-2 text-sm btn-shine">
                            <i class="fa-solid fa-user-plus"></i> Create Account
                        </button>
                    </form>
                </div>

                <!-- Manage Accounts Panel -->
                <div id="panelManage" class="space-y-5 hidden">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Manage Accounts</h2>
                        <p class="text-slate-400 text-sm mt-1">Okkoma system accounts - edit/delete karanna</p>
                    </div>
                    <div class="pt-0 border-t border-slate-800">
                        <p class="text-[11px] text-slate-400 mt-3 mb-4">Okkoma accounts methana pennanawa. Edit/Delete karanna mulin <b class="text-rose-300">admin password eka</b> danna (udama thiyena box eka) - eka hari nam witharak wada wenne. Admin account ekak hadaddi Admin Key eka illana wage mekath danna one.</p>

                        <?php if ($editAcc): ?>
                        <div class="bg-indigo-500/5 border border-indigo-500/30 rounded-xl p-4 mb-4">
                            <h4 class="text-xs font-bold text-indigo-300 flex items-center gap-2 mb-3">
                                <i class="fa-solid fa-pen-to-square"></i>
                                Edit <?= $editAcc['atype'] === 'adm' ? 'Admin' : 'Employee'; ?> Account #<?= (int)$editAcc['id']; ?>
                            </h4>
                            <form method="POST" class="space-y-3" onsubmit="return editSave(this);">
                                <input type="hidden" name="edit_target" value="<?= $editAcc['atype']; ?>:<?= (int)$editAcc['id']; ?>">
                                <input type="hidden" name="edit_pass" value="">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Name</label>
                                        <input type="text" name="edit_name" required value="<?= htmlspecialchars($editAcc['name']); ?>"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Username</label>
                                        <input type="text" name="edit_username" required value="<?= htmlspecialchars($editAcc['username'] ?? ''); ?>"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                    <?php if ($editAcc['atype'] === 'emp'): ?>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Shift</label>
                                        <select name="edit_shift" required
                                            class="w-full appearance-none cursor-pointer bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500 transition-all [&>option]:bg-slate-900">
                                            <?php foreach ([['M', 'Morning Shift'], ['E', 'Evening Shift'], ['BOTH', 'Both Shifts']] as $so): ?>
                                                <option value="<?= $so[0]; ?>" <?= ($editAcc['shift_type'] ?? '') === $so[0] ? 'selected' : ''; ?>><?= $so[1]; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Position</label>
                                        <input type="text" name="edit_position" value="<?= htmlspecialchars($editAcc['position'] ?? ''); ?>" placeholder="Job position eka (ud ah: Manager, Supervisor)"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1"><?= $editAcc['atype'] === 'adm' ? 'Admin Number' : 'Employee Number'; ?></label>
                                        <input type="text" name="edit_number" required value="<?= htmlspecialchars($editAcc['emp_number'] ?? ''); ?>" placeholder="Company number eka (ud ah: 014, EMP005)"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Alut Password (optional)</label>
                                        <input type="password" name="edit_password" placeholder="Wenas karanne nam witharak"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Confirm Password</label>
                                        <input type="password" name="edit_confirm" placeholder="Ayeth liyanna (password danne nam)"
                                            class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-3 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition-all">
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="submit" name="edit_account"
                                        class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-semibold py-2.5 px-5 rounded-xl transition-all shadow-lg shadow-indigo-600/25 flex items-center gap-2 text-xs">
                                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                                    </button>
                                    <a href="login.php?tab=manage" class="text-xs font-semibold text-slate-400 hover:text-white px-4 py-2.5 rounded-xl bg-slate-800/50 border border-slate-700/50 transition">Cancel</a>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <div class="relative mb-3">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-shield-halved text-rose-400 text-sm"></i>
                            </div>
                            <input type="password" id="delPass" autocomplete="new-password" placeholder="Edit/Delete karanna mulin admin password eka danna"
                                class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
                            <p class="text-[10px] text-rose-400/70 mt-1 ml-1"><i class="fa-solid fa-circle-info mr-1"></i>Admin account eka hadaddi Admin Key eka illana wage - Edit/Delete karanna me admin password eka one (REAL verify).</p>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-700/50">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-700/50 bg-slate-900/40">
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">#</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">Name</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">Role</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">Username</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">Shift</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase">Position</th>
                                        <th class="py-3 px-4 text-[10px] font-bold text-slate-400 uppercase text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <?php foreach ($admList as $a): ?>
                                    <tr class="hover:bg-slate-800/20 transition-colors">
                                        <td class="py-3 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)($a['emp_number'] ?? '')) ?: (int)$a['id']; ?></td>
                                        <td class="py-3 px-4 font-semibold text-white"><?= htmlspecialchars($a['name']); ?></td>
                                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/25">Admin</span></td>
                                        <td class="py-3 px-4 font-mono text-slate-300">@<?= htmlspecialchars($a['username'] ?? ''); ?></td>
                                        <td class="py-3 px-4 text-slate-500">-</td>
                                        <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars((string)($a['position'] ?? '')) ?: '-'; ?></td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="login.php?tab=manage&edit=adm:<?= (int)$a['id']; ?>" title="Edit"
                                                    class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 hover:bg-indigo-500/30 hover:text-indigo-300 flex items-center justify-center transition">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </a>
                                                <form method="POST" class="inline" onsubmit="return rowDel(this);">
                                                    <input type="hidden" name="del_target" value="adm:<?= (int)$a['id']; ?>">
                                                    <input type="hidden" name="del_pass" value="">
                                                    <button type="submit" name="delete_account" title="Delete" class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-400 hover:bg-rose-500/30 hover:text-rose-300 flex items-center justify-center transition">
                                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($empList as $e): ?>
                                    <tr class="hover:bg-slate-800/20 transition-colors">
                                        <td class="py-3 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)($e['emp_number'] ?? '')) ?: (int)$e['id']; ?></td>
                                        <td class="py-3 px-4 font-semibold text-white"><?= htmlspecialchars($e['name']); ?></td>
                                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/25">Employee</span></td>
                                        <td class="py-3 px-4 font-mono text-slate-300">@<?= htmlspecialchars($e['username'] ?? ''); ?></td>
                                        <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars(['M' => 'Morning', 'E' => 'Evening', 'BOTH' => 'Both'][$e['shift_type']] ?? $e['shift_type']); ?></td>
                                        <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars((string)($e['position'] ?? '')) ?: '-'; ?></td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="login.php?tab=manage&edit=emp:<?= (int)$e['id']; ?>" title="Edit"
                                                    class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 hover:bg-indigo-500/30 hover:text-indigo-300 flex items-center justify-center transition">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </a>
                                                <form method="POST" class="inline" onsubmit="return rowDel(this);">
                                                    <input type="hidden" name="del_target" value="emp:<?= (int)$e['id']; ?>">
                                                    <input type="hidden" name="del_pass" value="">
                                                    <button type="submit" name="delete_account" title="Delete" class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-400 hover:bg-rose-500/30 hover:text-rose-300 flex items-center justify-center transition">
                                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($empList) && empty($admList)): ?>
                                    <tr><td colspan="7" class="py-8 text-center text-slate-500 text-xs">Thawamada account hadala nahe.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Forgot Password Panel -->
                <div id="panelForgot" class="space-y-5 hidden">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Forgot Password?</h2>
                        <p class="text-slate-400 text-sm mt-1">Oyage username eka liyan reset token eka labanna</p>
                    </div>

                    <form method="POST" class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-user text-slate-500 text-sm"></i>
                                </div>
                                <input type="text" name="fp_username" required placeholder="Oyage username eka liyanna"
                                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl pl-11 pr-4 py-3.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
                            </div>
                        </div>

                        <button type="submit" name="forgot_password"
                            class="w-full bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-semibold py-3.5 px-6 rounded-xl transition-all shadow-lg shadow-rose-600/25 flex items-center justify-center gap-2 text-sm btn-shine">
                            <i class="fa-solid fa-key"></i> Generate Reset Token
                        </button>
                    </form>

                    <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-700/50 space-y-2">
                        <p class="text-[11px] text-slate-400"><i class="fa-solid fa-circle-info mr-1 text-indigo-400"></i>Eenam meka weda karanne:</p>
                        <ol class="text-[11px] text-slate-400 space-y-1 ml-4 list-decimal">
                            <li>Oyage <b class="text-white">username</b> eka liyan token eka Generate karanna.</li>
                            <li>Token eka <b class="text-white">Admin keneku ta</b> danna (phone/message ekken).</li>
                            <li>Admin eken <b class="text-white">admin.php</b> eke "Reset Password" section eke token eka dala new password ekak set karanna.</li>
                        </ol>
                    </div>

                    <div class="text-center">
                        <button type="button" onclick="showPanel('login')" class="text-xs font-semibold text-slate-400 hover:text-white transition">
                            <i class="fa-solid fa-arrow-left mr-1"></i> Back to Sign In
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center pt-4 border-t border-slate-800">
                    <p class="text-[11px] text-slate-500">
                        Account hadanna / manage karanna? <a href="create_account.php" class="text-amber-400 font-medium hover:text-amber-300 underline">Create / Manage Accounts</a> (Owner username + password illanawa)
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const p = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (p.type === 'password') {
                p.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                p.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function toggleRegPassword() {
            const p = document.getElementById('regPassword');
            const icon = document.getElementById('regEyeIcon');
            if (p.type === 'password') {
                p.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                p.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function toggleRegRole() {
            const role = document.getElementById('regRole').value;
            const isAdmin = role === 'admin';
            const shiftWrap = document.getElementById('regShiftWrap');
            const keyWrap = document.getElementById('regKeyWrap');
            const shiftSel = document.getElementById('regShift');
            const keyInput = document.getElementById('regAdminKey');
            const regPos = document.getElementById('regPosition');
            const regNum = document.getElementById('regNumber');
            const regNumLabel = document.getElementById('regNumberLabel');
            shiftWrap.classList.toggle('hidden', isAdmin);
            keyWrap.classList.toggle('hidden', !isAdmin);
            if (isAdmin) {
                shiftSel.removeAttribute('required');
                keyInput.setAttribute('required', 'required');
                regPos.removeAttribute('required');
                regNumLabel.textContent = 'Admin Number';
                regNum.placeholder = 'Company admin number eka danna (ud ah: A001, ADM-04)';
            } else {
                shiftSel.setAttribute('required', 'required');
                keyInput.removeAttribute('required');
                regPos.setAttribute('required', 'required');
                regNumLabel.textContent = 'Employee Number';
                regNum.placeholder = 'Company employee number eka danna (ud ah: 014, EMP005)';
            }
            regNum.setAttribute('required', 'required');
        }

        function showPanel(tab) {
            const panels = {
                login: document.getElementById('panelLogin'),
                register: document.getElementById('panelRegister'),
                manage: document.getElementById('panelManage'),
                forgot: document.getElementById('panelForgot')
            };
            const btns = {
                login: document.getElementById('tabLoginBtn'),
                register: document.getElementById('tabRegisterBtn'),
                manage: document.getElementById('tabManageBtn')
            };
            const activeCls = 'py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg shadow-indigo-600/25';
            const inactiveCls = 'py-2.5 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all text-slate-400 hover:text-white';
            for (const key of ['login', 'register', 'manage']) {
                const isActive = (key === tab && tab !== 'forgot' && tab !== 'accounts');
                if (panels[key]) panels[key].classList.toggle('hidden', !isActive);
                if (btns[key]) btns[key].className = isActive ? activeCls : inactiveCls;
            }
            if (panels.forgot) panels.forgot.classList.toggle('hidden', tab !== 'forgot');
            if (btns.login && tab === 'forgot') btns.login.className = inactiveCls;
        }

        function rowDel(f) {
            const p = document.getElementById('delPass');
            if (!p || !p.value) {
                alert('Mulin admin password eka danna (udama thiyena box eka). Eka hari nam witharak delete wenawa.');
                return false;
            }
            f.querySelector('[name=del_pass]').value = p.value;
            return confirm('MEKA PERMANENT! Thoorapu account eka sampurnayenma delete wenne. Hariyatada?');
        }

        function editSave(f) {
            const p = document.getElementById('delPass');
            if (!p || !p.value) {
                alert('Mulin admin password eka danna (udama thiyena box eka). Eka hari nam witharak Save wenawa.');
                return false;
            }
            f.querySelector('[name=edit_pass]').value = p.value;
            return true;
        }

        showPanel('<?= $activeTab; ?>');
    </script>
</body>
</html>
