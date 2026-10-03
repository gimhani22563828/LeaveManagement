<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireEmployee();

$currentUser = getCurrentUser();

/* Logs dan DATABASE EKEN (system_logs table) enawa */
$logRows = getLogsForUser($conn, $currentUser['id']);

$actionIcons = [
    'LOGIN_SUCCESS'    => ['icon' => 'fa-right-to-bracket',  'color' => 'emerald'],
    'LOGIN_FAILED'     => ['icon' => 'fa-circle-xmark',      'color' => 'rose'],
    'LOGOUT'           => ['icon' => 'fa-right-from-bracket','color' => 'slate'],
    'LEAVE_APPROVED'   => ['icon' => 'fa-circle-check',      'color' => 'emerald'],
    'LEAVE_PENDING'    => ['icon' => 'fa-clock',             'color' => 'amber'],
    'LEAVE_REJECTED'   => ['icon' => 'fa-circle-xmark',      'color' => 'rose'],
    'LEAVE_CANCELLED'  => ['icon' => 'fa-trash-can',         'color' => 'rose'],
    'ADMIN_Approved'   => ['icon' => 'fa-user-shield',       'color' => 'indigo'],
    'ADMIN_Rejected'   => ['icon' => 'fa-user-shield',       'color' => 'indigo'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | My Activity Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased">

    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-emerald-600/5 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px]"></div>
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px); background-size: 32px 32px;"></div>
    </div>

    <nav class="fixed top-0 inset-x-0 z-50 bg-slate-950/70 backdrop-blur-xl border-b border-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 md:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <span class="text-sm font-black text-white">AK</span>
                </div>
                <span class="text-lg font-bold text-white tracking-tight">AKK <span class="text-indigo-400">SOLUTIONS</span></span>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center gap-2 bg-slate-800/50 border border-slate-700/50 rounded-xl px-3 py-2">
                    <div class="w-7 h-7 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-400 text-[10px] font-bold">
                        <?= strtoupper(substr($currentUser['name'], 0, 2)); ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-semibold text-white"><?= htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-[10px] text-slate-400">Employee Portal</p>
                    </div>
                </div>
                <a href="logout.php" class="text-xs font-semibold text-slate-400 hover:text-rose-400 transition bg-slate-800/50 border border-slate-700/50 px-3 py-2 rounded-xl flex items-center gap-1.5">
                    <i class="fa-solid fa-right-from-bracket"></i> <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="relative z-10 pt-24 pb-12 px-4 md:px-8">
        <div class="max-w-5xl mx-auto space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">My Activity Logs</h1>
                    <p class="text-sm text-slate-400 mt-1">ඔබගේ සියලුම ක්‍රියාකාරකම් මෙහි බලන්න</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="index.php" class="text-xs font-bold bg-slate-800/50 border border-slate-700/50 text-slate-300 hover:text-white px-4 py-2.5 rounded-xl transition flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                    <span class="text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/25 px-3 py-1.5 rounded-full">
                        <?= count($logRows); ?> entries
                    </span>
                </div>
            </div>

            <div class="card-glass border border-slate-700/40 rounded-2xl overflow-hidden shadow-xl">
                <?php if (count($logRows) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-700/40 bg-slate-900/30">
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Time</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Action</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">IP Address</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach ($logRows as $log):
                                    $action = $log['action'];
                                    $style = $actionIcons[$action] ?? ['icon' => 'fa-circle-info', 'color' => 'slate'];
                                ?>
                                    <tr class="hover:bg-slate-800/20 transition-colors">
                                        <td class="py-4 px-6 font-mono text-white text-xs whitespace-nowrap"><?= htmlspecialchars($log['log_time']); ?></td>
                                        <td class="py-4 px-6">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold
                                                bg-<?= $style['color']; ?>-500/15 text-<?= $style['color']; ?>-400 border border-<?= $style['color']; ?>-500/25">
                                                <i class="fa-solid <?= $style['icon']; ?>"></i>
                                                <?= htmlspecialchars($action); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 font-mono text-slate-400 text-xs"><?= htmlspecialchars($log['ip']); ?></td>
                                        <td class="py-4 px-6 text-slate-400 text-xs max-w-[250px] truncate"><?= htmlspecialchars($log['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-16 text-center">
                        <div class="w-20 h-20 rounded-2xl bg-slate-800/50 flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-inbox text-slate-600 text-3xl"></i>
                        </div>
                        <p class="text-slate-400 text-sm font-semibold mb-1">No activity logs yet</p>
                        <p class="text-slate-500 text-xs">Sign in වූ පසු logs මෙහි පේනු ඇත</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>
