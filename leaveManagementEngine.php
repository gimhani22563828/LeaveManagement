<?php

class LeaveManagementEngine {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function validateLeaveRequest($userId, $leaveDate, $shift, $isEmergency) {
        
        // --- RULE 8: දින 5ක Advance Notice පරීක්ෂාව ---
        $today = new DateTime(date('Y-m-d'));
        $targetDate = new DateTime($leaveDate);
        $diff = $today->diff($targetDate);
        
        // අද සිට අදාළ දිනයට දින 5කට අඩු නම් Emergency එකක් ලෙස සලකනු ලැබේ
        if ($diff->invert == 0 && $diff->days < 5 && !$isEmergency) {
            return [
                'status' => 'REJECTED',
                'reason' => "නිවාඩුවක් ලබා ගැනීමට අවම වශයෙන් දින 5කට පෙර ඉල්ලුම් කළ යුතුය. ($leaveDate දිනයට දින 5ක් නොමැති බැවින් මෙය Emergency Request එකක් ලෙස යොමු කරන්න)."
            ];
        }

        // --- RULE 9: මාසයකට එක් අයෙකුට ලබාගත හැකි උපරිම නිවාඩු ගණන 4 සීමාව ---
        $yearMonth = date('Y-m', strtotime($leaveDate));
        $monthStart = $yearMonth . '-01';
        $nextMonthStart = (new DateTime($monthStart))->modify('+1 month')->format('Y-m-d');
        $stmtMonthly = $this->conn->prepare("
            SELECT COUNT(*) 
            FROM leave_requests 
            WHERE user_id = :userId 
              AND status = 'Approved' 
              AND leave_date >= :monthStart
              AND leave_date < :nextMonthStart
        ");
        $stmtMonthly->execute([
            ':userId'         => $userId,
            ':monthStart'     => $monthStart,
            ':nextMonthStart' => $nextMonthStart
        ]);
        $monthlyApprovedCount = $stmtMonthly->fetchColumn();

        if ($monthlyApprovedCount >= 4) {
            return [
                'status' => 'REJECTED',
                'reason' => "ඔබ මේ වන විටත් මෙම මාසය ($yearMonth) සඳහා උපරිම නිවාඩු 4 ලබාගෙන ඇත."
            ];
        }

        // අදාළ දිනයේ දැනට Approved වී ඇති සියලුම නිවාඩු ලබාගැනීම
        $stmt = $this->conn->prepare("SELECT user_id FROM leave_requests WHERE leave_date = :ldate AND status = 'Approved'");
        $stmt->execute([':ldate' => $leaveDate]);
        $approvedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $morningGroup = [1, 2, 3, 4, 5, 6, 7];
        $eveningGroup = [8, 9, 10];
        $group123 = [1, 2, 3];

        // --- RULE 6: Morning Shift (#1 - #7) උපරිම 2 දෙදෙනෙකුට පමණයි නිවාඩු ගත හැක්කේ ---
        if (in_array($userId, $morningGroup)) {
            $morningApprovedCount = 0;
            foreach ($approvedUserIds as $approvedId) {
                if (in_array($approvedId, $morningGroup) && $approvedId != $userId) {
                    $morningApprovedCount++;
                }
            }
            if ($morningApprovedCount >= 2) {
                return [
                    'status' => 'REJECTED',
                    'reason' => "Morning Shift (#1-#7) සඳහා එකම දිනයේ ($leaveDate) අනුමත කළ හැක්කේ උපරිම නිවාඩු 2ක් පමණි."
                ];
            }
        }

        // --- RULE 7: Evening Shift (#8, #9, #10) එක් අයෙකුට පමණයි නිවාඩු ගත හැක්කේ ---
        if (in_array($userId, $eveningGroup)) {
            foreach ($approvedUserIds as $approvedId) {
                if (in_array($approvedId, $eveningGroup) && $approvedId != $userId) {
                    return [
                        'status' => 'REJECTED',
                        'reason' => "Evening Shift (#8, #9, #10) සඳහා එකම දිනයේ ($leaveDate) ලබාගත හැක්කේ එක් නිවාඩුවක් පමණි."
                    ];
                }
            }
        }

        // --- RULE 1: #1, #2, #3 යන තිදෙනාගෙන් එක් අයෙකුට පමණයි නිවාඩු ගත හැක්කේ ---
        if (in_array($userId, $group123)) {
            foreach ($approvedUserIds as $approvedId) {
                if (in_array($approvedId, $group123) && $approvedId != $userId) {
                    return [
                        'status' => 'REJECTED',
                        'reason' => "User #1, #2, සහ #3 යන අයගෙන් එක් අයෙකුට පමණක් $leaveDate දින නිවාඩු ලබාගත හැක."
                    ];
                }
            }
        }

        // --- RULE 2 & 5: #1, #2, #3 හෝ #8 නිවාඩු අරන් ඇත්නම් #4 ට නිවාඩු ගත නොහැක ---
        if ($userId == 4) {
            foreach ($approvedUserIds as $approvedId) {
                if (in_array($approvedId, $group123)) {
                    return [
                        'status' => 'REJECTED',
                        'reason' => "User #1, #2, හෝ #3 $leaveDate දින නිවාඩු ලබාගෙන ඇති බැවින් User #4 ට නිවාඩු ලබාගත නොහැක."
                    ];
                }
                if ($approvedId == 8) {
                    return [
                        'status' => 'REJECTED',
                        'reason' => "User #8 $leaveDate දින නිවාඩු ලබාගෙන ඇති බැවින් User #4 ට නිවාඩු ලබාගත නොහැක."
                    ];
                }
            }
        }

        // --- RULE 3: #6 සහ #7 එකම දිනයේ නිවාඩු ගත නොහැක ---
        if ($userId == 6 || $userId == 7) {
            $otherUser = ($userId == 6) ? 7 : 6;
            if (in_array($otherUser, $approvedUserIds)) {
                return [
                    'status' => 'REJECTED',
                    'reason' => "User #6 සහ User #7 දෙදෙනාටම එකම දිනයේ ($leaveDate) නිවාඩු ලබාගත නොහැක."
                ];
            }
        }

        // --- RULE 4: #8 සහ #9 දෙදෙනාගෙන් එක් අයෙකුට පමණයි නිවාඩු ගත හැක්කේ ---
        if ($userId == 8 || $userId == 9) {
            $otherUser = ($userId == 8) ? 9 : 8;
            if (in_array($otherUser, $approvedUserIds)) {
                return [
                    'status' => 'REJECTED',
                    'reason' => "User #8 සහ User #9 දෙදෙනාගෙන් එක් අයෙකුට පමණක් $leaveDate දින නිවාඩු ලබාගත හැක."
                ];
            }
        }

        // සියලුම Rules සමත් නම් Auto-Approve වේ
        return [
            'status' => 'APPROVED',
            'reason' => 'සියලුම කොන්දේසි සපිරී ඇත. නිවාඩුව අනුමත විය.'
        ];
    }
}