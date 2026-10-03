<?php
include 'db.php';
include 'logger.php';

// Self-lock: admin kenek hariyatama innawa nam meka weda na
$stmt = $conn->query("SELECT COUNT(*) FROM admin_users");
$adminCount = (int)$stmt->fetchColumn();

if ($adminCount > 0) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if (isset($_POST['create_owner'])) {
    $name     = trim($_POST['owner_name'] ?? '');
    $username = trim($_POST['owner_username'] ?? '');
    $email    = trim($_POST['owner_email'] ?? '');
    $password = $_POST['owner_password'] ?? '';
    $confirm  = $_POST['owner_confirm'] ?? '';

    if ($name === '' || $username === '' || $email === '' || $password === '') {
        $error = "සියලුම fields අනිවාර්යයි.";
    } elseif (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $username)) {
        $error = "Username අකුරු 3-50 ක විය යුතුයි (a-z, A-Z, 0-9, . _ පමණයි).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "වලංගු email ලිපිනයක් ලබා දෙන්න.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = "නම අකුරු 2-100 ක් විය යුතුයි.";
    } elseif (strlen($password) < 6 || strlen($password) > 72) {
        $error = "Admin password එක අකුරු 6-72 අතර විය යුතුයි.";
    } elseif ($password !== $confirm) {
        $error = "Passwords deka samana nahi.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO admin_users (name, username, email, password) VALUES (:n, :u, :e, :p)");
            $stmt->execute([
                ':n' => $name,
                ':u' => $username,
                ':e' => $email,
                ':p' => password_hash($password, PASSWORD_DEFAULT)
            ]);
            writeLog('INSTALL_COMPLETE', 'Owner admin created: ' . $username);
            header("Location: login.php?installed=1");
            exit();
        } catch (PDOException $e) {
            $error = "Account eka hadanna beri. Username eka duplicate vela vena one.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | First-Time Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }
        .input-glow:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md glass-card border border-slate-700/60 rounded-3xl p-8 space-y-6 shadow-2xl">
        <div class="text-center space-y-3">
            <div class="flex justify-center">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-2xl shadow-indigo-500/30">
                    <span class="text-2xl font-black text-white">AK</span>
                </div>
            </div>
            <h1 class="text-2xl font-extrabold text-white">First-Time Setup</h1>
            <p class="text-slate-400 text-xs leading-relaxed">
                System eka sampurnayenma set karanna <b class="text-indigo-400">mulma Admin (Owner) account</b> eka hadanna.
                Meka pass wela idan passe me page eka weda nathi ve.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/25 flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation text-rose-400 text-sm mt-0.5"></i>
                <p class="text-xs text-rose-300 font-medium"><?= $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Your Name</label>
                <input type="text" name="owner_name" required placeholder="e.g. Pramod Kawya"
                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Admin Username</label>
                <input type="text" name="owner_username" required placeholder="e.g. admin"
                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Email</label>
                <input type="email" name="owner_email" required placeholder="you@company.com"
                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Password <span class="text-slate-500 normal-case">(min 6)</span></label>
                <input type="password" name="owner_password" required minlength="6" placeholder="Strong password ekak danna"
                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Confirm Password</label>
                <input type="password" name="owner_confirm" required minlength="6" placeholder="Password eka ayeth type karanna"
                    class="w-full bg-slate-800/60 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
            </div>

            <button type="submit" name="create_owner"
                class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold py-3.5 px-6 rounded-xl transition-all shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 text-sm">
                <i class="fa-solid fa-shield-halved"></i> Create Owner Account
            </button>
        </form>

        <p class="text-center text-[11px] text-slate-500 pt-2 border-t border-slate-800">
            <i class="fa-solid fa-lock mr-1"></i> Meka ekawaraikama pennanne mulma setup ekedi witharai
        </p>
    </div>

</body>
</html>
