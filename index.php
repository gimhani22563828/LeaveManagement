<?php
include 'db.php';
include 'auth.php';
include 'logger.php';
requireEmployee();

$currentUser = getCurrentUser();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $currentUser['id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header("Location: logout.php");
    exit();
}

$message = "";

if (isset($_POST['apply_leave'])) {
    $user_id = (int)$me['id'];
    $leave_date = $_POST['leave_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // දින 5 පරීක්ෂාව - දින 5ට අඩු නම් automatically is_emergency = 1 වේ
    $today = new DateTime(date('Y-m-d'));
    $targetDate = new DateTime($leave_date);
    $diff = $today->diff($targetDate);
    
    $is_notice_short = ($diff->invert == 0 && $diff->days < 5);
    $is_emergency = (isset($_POST['is_emergency']) || $is_notice_short) ? 1 : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leave_date) || strtotime($leave_date) === false) {
        writeLog('LEAVE_REJECTED', "User#$user_id invalid date");
        $message = '
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/25 text-rose-300 flex items-start gap-3 shadow-lg shadow-rose-500/5">
            <div class="w-10 h-10 rounded-xl bg-rose-500/15 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-xmark text-rose-400"></i>
            </div>
            <div class="text-xs leading-relaxed">
                <strong class="font-bold text-sm block mb-0.5 text-rose-300">Invalid Date</strong>
                වලංගු දිනයක් තෝරන්න.
            </div>
        </div>';
    } elseif ($reason === '') {
        writeLog('LEAVE_REJECTED', "User#$user_id empty reason");
        $message = '
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/25 text-rose-300 flex items-start gap-3 shadow-lg shadow-rose-500/5">
            <div class="w-10 h-10 rounded-xl bg-rose-500/15 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-xmark text-rose-400"></i>
            </div>
            <div class="text-xs leading-relaxed">
                <strong class="font-bold text-sm block mb-0.5 text-rose-300">Reason Required</strong>
                නිවාඩුවට හේතුවක් ලියන්න.
            </div>
        </div>';
    } else {

        $stmt = $conn->prepare("SELECT shift_type FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $shift_type = $user['shift_type'] ?? 'M';

        $applied_shift = ($shift_type == 'BOTH') ? ($_POST['applied_shift'] ?? 'M') : $shift_type;

        include_once 'leaveManagementEngine.php';
        $engine = new LeaveManagementEngine($conn);
        $result = $engine->validateLeaveRequest((int)$user_id, $leave_date, $applied_shift, (bool)$is_emergency);

        if ($result['status'] === 'APPROVED' && !$is_notice_short) {
            $status = 'Approved';
            $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_date, shift_applied, is_emergency, reason, status)
                                   VALUES (:uid, :date, :shift, :emergency, :reason, :status)");
            if ($stmt->execute([':uid' => $user_id, ':date' => $leave_date, ':shift' => $applied_shift, ':emergency' => $is_emergency, ':reason' => $reason, ':status' => $status])) {
                writeLog('LEAVE_APPROVED', "User#$user_id date:$leave_date shift:$applied_shift");
                $message = '
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 flex items-start gap-3 shadow-lg shadow-emerald-500/5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-circle-check text-emerald-400"></i>
                    </div>
                    <div class="text-xs leading-relaxed">
                        <strong class="font-bold text-sm block mb-0.5 text-emerald-300">Auto-Approved Successfully!</strong>
                        '.htmlspecialchars($result['reason']).'
                    </div>
                </div>';
            }
        } else {
            // දින 5ට අඩු නම් හෝ අනිත් Rules කැඩුණොත් Emergency ලෙස Admin අනුමැතියට යොමු වේ
            $reject_reason = $is_notice_short ? "දින 5ක පූර්ව දැනුම්දීමක් නොමැති නිසා (Emergency Review)" : $result['reason'];
            if ($is_emergency) {
                $status = 'Pending';
                $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_date, shift_applied, is_emergency, reason, status)
                                       VALUES (:uid, :date, :shift, :emergency, :reason, :status)");
                if ($stmt->execute([':uid' => $user_id, ':date' => $leave_date, ':shift' => $applied_shift, ':emergency' => 1, ':reason' => $reason, ':status' => $status])) {
                    writeLog('LEAVE_PENDING', "User#$user_id date:$leave_date shift:$applied_shift emergency reason:$reject_reason");
                    $message = '
                    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-300 flex items-start gap-3 shadow-lg shadow-amber-500/5">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-triangle-exclamation text-amber-400"></i>
                        </div>
                        <div class="text-xs leading-relaxed">
                            <strong class="font-bold text-sm block mb-0.5 text-amber-300">Submitted as Emergency Request (Pending Admin Approval)</strong>
                            <b>'.htmlspecialchars($reject_reason).'</b> — ඔබගේ ඉල්ලීම Admin Review සඳහා යොමු කරන ලදී.
                        </div>
                    </div>';
                }
            } else {
                writeLog('LEAVE_REJECTED', "User#$user_id date:$leave_date reason:$reject_reason");
                $message = '
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/25 text-rose-300 flex items-start gap-3 shadow-lg shadow-rose-500/5">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/15 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-circle-xmark text-rose-400"></i>
                    </div>
                    <div class="text-xs leading-relaxed">
                        <strong class="font-bold text-sm block mb-0.5 text-rose-300">Auto-Rejected by Engine</strong>
                        '.htmlspecialchars($reject_reason).'
                    </div>
                </div>';
            }
        }
    }
}

// Fetch all approved/pending leaves for Calendar view
$calStmt = $conn->prepare("
    SELECT lr.leave_date, lr.shift_applied, lr.status, u.id as user_id, u.name as user_name 
    FROM leave_requests lr 
    JOIN users u ON lr.user_id = u.id 
    WHERE lr.leave_date >= :today AND lr.status IN ('Approved')
    ORDER BY lr.leave_date ASC, u.id ASC
");
$calStmt->execute([':today' => date('Y-m-d')]);
$allLeaves = $calStmt->fetchAll(PDO::FETCH_ASSOC);

// Group leaves by date for calendar display
$leavesByDate = [];
foreach ($allLeaves as $l) {
    $leavesByDate[$l['leave_date']][] = $l;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK SOLUTIONS | Employee Leave Application</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .input-glow:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .card-glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .btn-shine { position: relative; overflow: hidden; }
        .btn-shine::after { content: ''; position: absolute; top: -50%; left: -60%; width: 200%; height: 200%; background: linear-gradient(60deg, transparent 40%, rgba(255,255,255,0.06) 50%, transparent 60%); animation: btnShine 3s ease-in-out infinite; }
        @keyframes btnShine { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased">

    <!-- Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-purple-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute inset-0" style="background-image: radial-gradient(rgba(99, 102, 241, 0.04) 1px, transparent 1px); background-size: 32px 32px;"></div>
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
        <div class="max-w-6xl mx-auto space-y-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Info Panel -->
                <div class="lg:col-span-4 space-y-5">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Submit Your Leave Request</h1>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            ඔබගේ නිවාඩු ඉල්ලීම ස්වයංක්‍රීයව සත්‍යාපනය කරනු ලැබේ.
                        </p>
                    </div>

                    <!-- Guidelines -->
                    <div class="space-y-2.5">
                        <div class="flex items-start gap-3 p-3.5 rounded-2xl card-glass border border-slate-700/40">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-clock text-amber-400 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-white">5-Day Advance Notice</p>
                                <p class="text-[11px] text-slate-400">Apply at least 5 days in advance, or it goes as Emergency for Admin approval.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3.5 rounded-2xl card-glass border border-slate-700/40">
                            <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-sun text-indigo-400 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-white">Morning Shift Limit</p>
                                <p class="text-[11px] text-slate-400">Max 2 leaves per day for Users #1 to #7</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 p-3.5 rounded-2xl card-glass border border-slate-700/40">
                            <div class="w-9 h-9 rounded-xl bg-purple-500/15 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid fa-moon text-purple-400 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-white">Evening Shift Limit</p>
                                <p class="text-[11px] text-slate-400">Max 1 leave per day for Users #8, #9, #10</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="space-y-2 pt-2">
                        <a href="my_leaves.php" class="flex items-center gap-2.5 p-3 rounded-xl bg-slate-800/40 border border-slate-700/40 text-xs font-medium text-amber-400 hover:text-amber-300 hover:border-amber-500/30 transition group">
                            <i class="fa-solid fa-list-check text-sm"></i>
                            <span>View / Cancel Submitted Leaves</span>
                            <i class="fa-solid fa-arrow-right text-[10px] ml-auto opacity-0 group-hover:opacity-100 transition"></i>
                        </a>
                        <a href="my_logs.php" class="flex items-center gap-2.5 p-3 rounded-xl bg-slate-800/40 border border-slate-700/40 text-xs font-medium text-emerald-400 hover:text-emerald-300 hover:border-emerald-500/30 transition group">
                            <i class="fa-solid fa-clock-rotate-left text-sm"></i>
                            <span>View My Activity Logs</span>
                            <i class="fa-solid fa-arrow-right text-[10px] ml-auto opacity-0 group-hover:opacity-100 transition"></i>
                        </a>
                    </div>
                </div>

                <!-- Right: Form Card -->
                <div class="lg:col-span-8">
                    <div class="card-glass border border-slate-700/50 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-indigo-600 via-purple-500 to-indigo-600"></div>

                        <?= $message; ?>

                        <form method="POST" class="space-y-5">
                            
                            <!-- Employee Profile -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest mb-2">
                                    Employee Profile
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-user-check text-indigo-400 text-sm"></i>
                                    </div>
                                    <div class="w-full bg-slate-900/60 border border-slate-700/60 rounded-xl pl-11 pr-4 py-3.5 text-sm text-slate-200 select-none">
                                        #<?= (int)$me['id']; ?> <?= htmlspecialchars($me['name']); ?> (<?= htmlspecialchars($me['shift_type']); ?> Shift)
                                    </div>
                                </div>
                            </div>

                            <!-- Leave Date -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest mb-2">
                                    Leave Date <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="fa-solid fa-calendar-day text-slate-500 text-sm"></i>
                                    </div>
                                    <input type="date" name="leave_date" required min="<?= date('Y-m-d'); ?>"
                                        class="w-full bg-slate-900/60 border border-slate-700/60 rounded-xl pl-11 pr-4 py-3.5 text-sm text-slate-200 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                                </div>
                            </div>

                            <!-- Shift Selection -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest mb-2">
                                    Select Shift <span class="text-slate-500 text-[10px] font-normal normal-case">(For BOTH shift employees only)</span>
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center justify-center gap-2.5 p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/60 cursor-pointer hover:border-indigo-500/40 transition-all text-xs font-medium group">
                                        <input type="radio" name="applied_shift" value="M" checked class="accent-indigo-500 w-4 h-4">
                                        <span class="text-slate-300 group-hover:text-white transition">Morning Shift</span>
                                    </label>
                                    <label class="flex items-center justify-center gap-2.5 p-3.5 rounded-xl bg-slate-900/60 border border-slate-700/60 cursor-pointer hover:border-purple-500/40 transition-all text-xs font-medium group">
                                        <input type="radio" name="applied_shift" value="E" class="accent-purple-500 w-4 h-4">
                                        <span class="text-slate-300 group-hover:text-white transition">Evening Shift</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Emergency Toggle -->
                            <div class="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/15 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-shield-halved text-amber-400 text-sm"></i>
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-white block">Emergency Request</span>
                                        <span class="text-[11px] text-slate-400">Mark if urgent / applied within 5 days</span>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_emergency" value="1" class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>

                            <!-- Reason -->
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest mb-2">
                                    Reason for Leave <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute top-3.5 left-4 pointer-events-none">
                                        <i class="fa-solid fa-comment text-slate-500 text-sm"></i>
                                    </div>
                                    <textarea name="reason" rows="3" required placeholder="Write a brief note explaining your leave request..."
                                        class="w-full bg-slate-900/60 border border-slate-700/60 rounded-xl pl-11 pr-4 py-3.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all resize-none"></textarea>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" name="apply_leave"
                                class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg shadow-indigo-600/25 hover:shadow-indigo-500/40 flex items-center justify-center gap-2.5 text-sm btn-shine">
                                <i class="fa-solid fa-paper-plane"></i> Submit Leave Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Calendar / Staff Leave Schedule Section -->
            <div class="card-glass border border-slate-700/50 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/15 flex items-center justify-center text-indigo-400">
                            <i class="fa-solid fa-calendar-days text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white">Staff Leave Calendar / Schedule</h2>
                            <p class="text-xs text-slate-400">Upcoming leaves of all team members</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5 text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-lg border border-emerald-500/20">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Approved
                        </span>
                        <span class="flex items-center gap-1.5 text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span> Pending
                        </span>
                    </div>
                </div>

                <?php if (empty($leavesByDate)): ?>
                    <div class="text-center py-8 text-slate-500 text-xs">
                        <i class="fa-regular fa-calendar-xmark text-3xl mb-2 block"></i>
                        No upcoming leaves scheduled.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($leavesByDate as $date => $leaves): ?>
                            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4">
                                <div class="text-xs font-bold text-indigo-400 mb-3 pb-2 border-b border-slate-800 flex items-center justify-between">
                                    <span><i class="fa-regular fa-calendar mr-1.5"></i> <?= date('D, M d, Y', strtotime($date)); ?></span>
                                    <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded-full text-slate-400"><?= count($leaves); ?> Leave(s)</span>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach ($leaves as $l): ?>
                                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/50 border border-slate-800/80">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-lg bg-indigo-500/20 flex items-center justify-center text-[10px] font-bold text-indigo-300">
                                                    #<?= $l['user_id']; ?>
                                                </div>
                                                <span class="text-xs font-medium text-slate-200"><?= htmlspecialchars($l['user_name']); ?></span>
                                                <span class="text-[10px] text-slate-500 uppercase">(<?= $l['shift_applied']; ?>)</span>
                                            </div>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md <?= $l['status'] === 'Approved' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/15 text-amber-400 border border-amber-500/20'; ?>">
                                                <?= $l['status']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>