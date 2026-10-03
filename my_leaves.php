<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireEmployee();

$currentUser = getCurrentUser();
$msg = "";

// Leave Cancellation (own requests only)
if (isset($_GET['cancel_id'])) {
    $cancel_id = (int)$_GET['cancel_id'];
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $cancel_id, ':uid' => $currentUser['id']]);
    if ($stmt->rowCount() > 0) {
        writeLog('LEAVE_CANCELLED', "Request#$cancel_id cancelled");
        $msg = "ඔබගේ නිවාඩු ඉල්ලීම සාර්ථකව අවලංගු කරන ලදී.";
    } else {
        writeLog('CANCEL_DENIED', "User#" . $currentUser['id'] . " tried to cancel Request#$cancel_id");
        $msg = "<span class='text-rose-300'>මෙම ඉල්ලීම අවලංගු කළ නොහැක — එය ඔබේ ඉල්ලීමක් නොවේ.</span>";
    }
}

// Fetch leaves for current user
$stmt = $conn->prepare("SELECT lr.*, u.name 
                        FROM leave_requests lr 
                        JOIN users u ON lr.user_id = u.id 
                        WHERE lr.user_id = :uid
                        ORDER BY lr.leave_date DESC");
$stmt->execute([':uid' => $currentUser['id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | My Leave Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link:hover { background: rgba(99, 102, 241, 0.08); }
        .sidebar-link.active { background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(99, 102, 241, 0.2); color: #a5b4fc; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased">

    <!-- Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-amber-600/5 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-indigo-600/5 rounded-full blur-[120px]"></div>
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px); background-size: 32px 32px;"></div>
    </div>

    <!-- Top Navbar -->
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

    <!-- Main Content -->
    <div class="relative z-10 pt-24 pb-12 px-4 md:px-8">
        <div class="max-w-5xl mx-auto space-y-6">
            
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">My Leave Requests</h1>
                    <p class="text-sm text-slate-400 mt-1">ඔබගේ සියලුම නිවාඩු ඉල්ලීම් මෙහි බලන්න</p>
                </div>
                <div class="flex items-center gap-3">
                <a href="my_logs.php" class="text-xs font-bold bg-slate-800/50 border border-slate-700/50 text-emerald-400 hover:text-emerald-300 px-4 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left"></i> My Logs
                </a>
                <a href="index.php" class="text-xs font-bold bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white px-5 py-2.5 rounded-xl transition shadow-md shadow-indigo-600/20 flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Apply New Leave
                </a>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-xs flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                    </div>
                    <div>
                        <strong class="font-bold text-sm block mb-0.5">Success</strong>
                        <?= $msg; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="card-glass border border-slate-700/40 rounded-2xl overflow-hidden shadow-xl">
                <?php if (count($requests) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-700/40 bg-slate-900/30">
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Date</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Shift</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reason</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Emergency</th>
                                    <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach($requests as $row): ?>
                                    <tr class="hover:bg-slate-800/20 transition-colors">
                                        <td class="py-4 px-6 font-mono text-white text-xs"><?= $row['leave_date']; ?></td>
                                        <td class="py-4 px-6">
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold <?= $row['shift_applied'] == 'M' ? 'bg-blue-500/15 text-blue-400 border border-blue-500/25' : 'bg-purple-500/15 text-purple-400 border border-purple-500/25'; ?>">
                                                <?= $row['shift_applied'] == 'M' ? 'Morning' : 'Evening'; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-slate-400 max-w-[180px] truncate"><?= htmlspecialchars($row['reason']); ?></td>
                                        <td class="py-4 px-6">
                                            <span class="px-2.5 py-1 rounded-lg font-bold text-[10px]
                                                <?= $row['status'] == 'Approved' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/25' : ($row['status'] == 'Pending' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/25' : 'bg-rose-500/15 text-rose-400 border border-rose-500/25'); ?>">
                                                <i class="fa-solid <?= $row['status'] == 'Approved' ? 'fa-check-circle' : ($row['status'] == 'Pending' ? 'fa-clock' : 'fa-times-circle'); ?> mr-1"></i>
                                                <?= $row['status']; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?= $row['is_emergency'] ? '<span class="text-amber-400 font-bold text-[11px]"><i class="fa-solid fa-bolt mr-1"></i>Yes</span>' : '<span class="text-slate-500">No</span>'; ?>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <?php if ($row['status'] !== 'Rejected'): ?>
                                                <a href="my_leaves.php?cancel_id=<?= $row['id']; ?>" 
                                                    onclick="return confirm('මෙම නිවාඩුව අවලංගු කිරීමට තහවුරු කරන්න?');"
                                                    class="inline-flex items-center gap-1.5 text-rose-400 hover:text-rose-300 font-bold border border-rose-500/25 bg-rose-500/10 hover:bg-rose-500/20 px-3 py-1.5 rounded-lg transition text-[11px]">
                                                    <i class="fa-solid fa-trash-can text-[10px]"></i> Cancel
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-600 text-[11px]">--</span>
                                            <?php endif; ?>
                                        </td>
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
                        <p class="text-slate-400 text-sm font-semibold mb-1">No leave requests yet</p>
                        <p class="text-slate-500 text-xs mb-5">ඔබගේ ප්‍රථම නිවාඩු ඉල්ලීම සාදන්න</p>
                        <a href="index.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-md">
                            <i class="fa-solid fa-plus"></i> Apply Now
                </a>
                </div>
            </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
