<?php
/* =====================================================================
   AKK Leave System - Create Account (OWNER GATE)
   ---------------------------------------------------------------------
   Create Account ekata yanna BLOCK.
   * Gate ekak: Owner Username + Owner Password illanawa (always ask,
     never remember - hama request ekakama).
   * Owner username/password eka hariyaman karanan witara panel eka
     open wenawa. Wrong nam -> Access Denied (lock).
   * Admin Registration Key eken Owner password reset karanna puluwan
     (Owner account ekak nathi nam alutin hadanawa).
   ===================================================================== */

include 'db.php';
include 'auth.php';
include 'logger.php';

function getOwnerId($conn) {
    $stmt = $conn->query("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : 0;
}

function validUsernameFormat($u) {
    return is_string($u) && preg_match('/^[a-zA-Z0-9._]{3,50}$/', $u);
}

function codeExists($conn, $code, $tbl, $excludeId = 0) {
    $s = $conn->prepare("SELECT COUNT(*) FROM $tbl WHERE emp_number = :c AND id != :id");
    $s->execute([':c' => $code, ':id' => $excludeId]);
    return (int)$s->fetchColumn() > 0;
}

function nextFreeId(PDO $conn, string $table): int {
    if (!in_array($table, ['users', 'admin_users'], true)) return 0;
    $ids  = $conn->query("SELECT id FROM $table ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $prev = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id > $prev + 1) return $prev + 1;
        $prev = $id;
    }
    return $prev + 1;
}

function usernameExistsAnywhere($conn, $username, $excludeUserId = 0, $tbl = '') {
    foreach (['admin_users', 'users'] as $t) {
        if ($tbl !== '' && $t !== $tbl) continue;
        $s = $conn->prepare("SELECT COUNT(*) FROM $t WHERE username = :u AND id != :eid");
        $s->execute([':u' => $username, ':eid' => $excludeUserId]);
        if ((int)$s->fetchColumn() > 0) return true;
    }
    return false;
}

function msg($text, $ok = true) {
    $cls = $ok ? 'bg-emerald-500/10 border-emerald-500/25 text-emerald-300'
               : 'bg-rose-500/10 border-rose-500/25 text-rose-300';
    $icon = $ok ? 'fa-circle-check' : 'fa-circle-xmark';
    return '<div class="p-3 rounded-xl border ' . $cls . ' text-xs mb-4"><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($text) . '</div>';
}

$ownerId = getOwnerId($conn);

$ownerRow = null;
if ($ownerId > 0) {
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :id");
    $stmt->execute([':id' => $ownerId]);
    $ownerRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$presetRole  = 'employee';
$presetCode  = '';
$presetShift = '';

/* ---------------- OWNER GATE (brute-force lockout eka samaga) ---------------- */
$ownerU = trim($_POST['owner_username'] ?? '');
$ownerP = $_POST['owner_password'] ?? '';
$headersMed = (isset($_POST['owner_login']) || isset($_POST['create'])
    || isset($_POST['add_employee']) || isset($_POST['create_admin'])
    || isset($_POST['set_emp_password']) || isset($_POST['change_admin_pass'])
    || isset($_POST['delete_emp_submit']) || isset($_POST['delete_admin'])
    || isset($_POST['set_emp_code']) || isset($_POST['set_admin_code'])
    || isset($_POST['replace']));

$gateError = '';
$ownerValid = false;
$locked = false;

if ($headersMed) {
    $fails     = (int)($_SESSION['owner_gate_fails']     ?? 0);
    $lockUntil = (int)($_SESSION['owner_gate_lock_until'] ?? 0);

    if (time() < $lockUntil) {
        $locked    = true;
        $minsLeft  = (int)ceil(($lockUntil - time()) / 60);
        $gateError = 'Ohu rata gana veradi attempts nisa thaama da gasa thiyenawa. ' . $minsLeft . ' min yatane pass karanna.';
        writeLog('OWNER_GATE_LOCKED', 'Owner gate locked out (IP throttled)');
    } elseif ($ownerRow && $ownerU !== '' && $ownerP !== ''
        && hash_equals(strtolower((string)$ownerRow['username']), strtolower($ownerU))
        && password_verify($ownerP, (string)$ownerRow['password'])) {
        $ownerValid = true;
        unset($_SESSION['owner_gate_fails'], $_SESSION['owner_gate_lock_until']);
    } elseif (isset($_POST['owner_login'])) {
        $fails++;
        $_SESSION['owner_gate_fails'] = $fails;
        if ($fails >= 5) {
            $_SESSION['owner_gate_lock_until'] = time() + 600;
        }
        $gateError = 'Owner username/password eka veradi. Denata sakkaranna!!';
        writeLog('OWNER_GATE_FAIL', 'Wrong owner credentials attempt for create_account');
    } else {
        $gateError = 'Owner Username & Password anivarya. Create Account ekata yanna owner kenekuta withakarai.';
    }
}

/* ---------------- OWNER RESET (admin key ekakin - guarantee entry) ---------------- */
$resetMsg  = '';
$resetErr  = '';
if (isset($_POST['reset_owner'])) {
    $codes        = getAccessCodes($conn);
    $keyTry       = $_POST['admin_reg_key'] ?? '';
    $newUname     = trim($_POST['owner_new_username'] ?? '');
    $newPass      = $_POST['owner_new_password'] ?? '';
    $newConf      = $_POST['owner_confirm_password'] ?? '';

    if (!hash_equals((string)$codes['admin_key'], $keyTry)) {
        $resetErr = 'Admin Registration Key eka veradi. Key eka db.php eke thiyenawa (ADMIN_REG_KEY).';
        writeLog('OWNER_RESET_BLOCKED', 'Wrong admin key for owner reset');
    } elseif ($newUname === '' || $newPass === '') {
        $resetErr = 'Alut Owner username saha password eka anivarya.';
    } elseif (!validUsernameFormat($newUname)) {
        $resetErr = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
    } elseif (strlen($newPass) < 6 || strlen($newPass) > 72) {
        $resetErr = 'Owner password eka akuru 6-72 athara venna one.';
    } elseif ($newPass !== $newConf) {
        $resetErr = 'Password deka samana nahi.';
    } elseif (usernameExistsAnywhere($conn, $newUname, $ownerId)) {
        $resetErr = 'Me username eka danata use karana. Vena ekak danna.';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        if ($ownerId > 0 && $ownerRow) {
            $stmt = $conn->prepare("UPDATE admin_users SET username = :u, password = :p WHERE id = :id");
            $stmt->execute([':u' => $newUname, ':p' => $hash, ':id' => $ownerId]);
            writeLog('OWNER_RESET', 'Owner (admin#' . $ownerId . ') username/password reset via admin key');
            $resetMsg = 'Owner account eka reset kala! Dan alut username: "' . htmlspecialchars($newUname) . '" ekakin gate eka atharata yanna.';
        } else {
            $stmt = $conn->prepare("INSERT INTO admin_users (name, username, password) VALUES (:n, :u, :p)");
            $stmt->execute([':n' => 'Owner', ':u' => $newUname, ':p' => $hash]);
            writeLog('OWNER_CREATED', 'Owner account created via admin key on create_account');
            $resetMsg = 'Owner account eka alutin haduwa! Dan username: "' . htmlspecialchars($newUname) . '" ekakin gate eka atharata yanna.';
        }
        $ownerId = getOwnerId($conn);
        if ($ownerId > 0) {
            $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $ownerId]);
            $ownerRow = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

/* =====================================================================
   OWNER VERIFIED -> handle actions
   ===================================================================== */
$error   = '';
$success = '';

if ($ownerValid) {
    $GLOBALS['HF_owner_u'] = $ownerU;
    $GLOBALS['HF_owner_p'] = $ownerP;

    function hf() {
        return '<input type="hidden" name="owner_username" value="' . htmlspecialchars($GLOBALS['HF_owner_u']) . '">'
             . '<input type="hidden" name="owner_password" value="' . htmlspecialchars($GLOBALS['HF_owner_p']) . '">';
    }

    /* --- Create account (role selector: employee / admin) --- */
    if (isset($_POST['create'])) {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $shift    = $_POST['shift'] ?? '';
        $position = trim($_POST['position'] ?? '');
        $code     = trim($_POST['code'] ?? '');
        $role     = (($_POST['role'] ?? 'employee') === 'admin') ? 'admin' : 'employee';

        if ($name === '' || $username === '' || $password === '') {
            $error = 'Name, Username saha Password okkoma anivaryayi.';
        } elseif ($confirm === '' || $password !== $confirm) {
            $error = 'Password saha Confirm Password deka samana nahi.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $error = 'Name eka akuru 2-100 athara venna one.';
        } elseif (!validUsernameFormat($username)) {
            $error = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
        } elseif (strlen($password) < 4 || strlen($password) > 72) {
            $error = 'Password eka akuru 4-72 athara venna one.';
        } elseif ($role === 'employee' && !in_array($shift, ['M', 'E', 'BOTH'], true)) {
            $error = 'Shift eka M / E / BOTH witharak venna one.';
        } elseif (mb_strlen($position) > 100) {
            $error = 'Position eka akuru 100 n ithi witharak venna one.';
        } elseif ($code === '') {
            $error = $role === 'admin' ? 'Admin Code eka anivarya.' : 'Employee Code eka anivarya.';
        } elseif (mb_strlen($code) > 50) {
            $error = 'Code eka akuru 50 n ithi witharak venna one.';
        } elseif (codeExists($conn, $code, $role === 'admin' ? 'admin_users' : 'users')) {
            $error = 'Me code eka danata use karana. Vena ekak danna.';
        } elseif (usernameExistsAnywhere($conn, $username)) {
            $error = 'Me username eka danata use karana. Vena ekak danna.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($role === 'admin') {
                $stmt = $conn->prepare("INSERT INTO admin_users (id, name, email, password, username, position, emp_number) VALUES (:id, :n, :e, :p, :u, :pos, :code)");
                $stmt->execute([':id' => nextFreeId($conn, 'admin_users'), ':n' => $name, ':e' => null, ':p' => $hash, ':u' => $username, ':pos' => $position, ':code' => $code]);
                writeLog('ACCOUNT_CREATED', 'ADMIN account created via create_account: ' . $username . ' (code ' . $code . ')');
                $success = 'Admin account eka sampurnayenma saduni! Code: ' . $code . ' | Username: ' . $username;
            } else {
                $stmt = $conn->prepare("INSERT INTO users (id, name, email, password, username, shift_type, position, emp_number) VALUES (:id, :n, :e, :p, :u, :s, :pos, :code)");
                $stmt->execute([':id' => nextFreeId($conn, 'users'), ':n' => $name, ':e' => null, ':p' => $hash, ':u' => $username, ':s' => $shift, ':pos' => $position, ':code' => $code]);
                writeLog('ACCOUNT_CREATED', 'Employee account created via create_account: ' . $username . ' (' . $shift . ') code ' . $code);
                $success = 'Employee account eka sampurnayenma saduni! Code: ' . $code . ' | Username: ' . $username;
            }
        }
    }

    /* --- Add employee --- */
    if (isset($_POST['add_employee'])) {
        $emp_name     = trim($_POST['emp_name'] ?? '');
        $emp_username = trim($_POST['emp_new_username'] ?? '');
        $emp_shift    = $_POST['emp_shift'] ?? '';
        $emp_position = trim($_POST['emp_position'] ?? '');
        $emp_pass     = $_POST['emp_new_password'] ?? '';
        $emp_code     = trim($_POST['emp_code'] ?? '');

        if ($emp_name === '' || $emp_username === '' || $emp_shift === '' || $emp_pass === '') {
            $error = 'Name, Username, Shift saha Password okkoma anivaryayi.';
        } elseif (mb_strlen($emp_name) < 2 || mb_strlen($emp_name) > 100) {
            $error = 'Name eka akuru 2-100 athara venna one.';
        } elseif (!validUsernameFormat($emp_username)) {
            $error = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
        } elseif (!in_array($emp_shift, ['M', 'E', 'BOTH'], true)) {
            $error = 'Shift eka M / E / BOTH witharak venna one.';
        } elseif (strlen($emp_pass) < 4 || strlen($emp_pass) > 72) {
            $error = 'Password eka akuru 4-72 athara venna one.';
        } elseif ($emp_code === '') {
            $error = 'Employee Code eka anivarya.';
        } elseif (mb_strlen($emp_code) > 50) {
            $error = 'Employee Code eka akuru 50 n ithi witharak venna one.';
        } elseif (codeExists($conn, $emp_code, 'users')) {
            $error = 'Me employee code eka danata use karana. Vena ekak danna.';
        } elseif (usernameExistsAnywhere($conn, $emp_username)) {
            $error = 'Me username eka danata use karana. Vena ekak danna.';
        } else {
            $newEmpId = nextFreeId($conn, 'users');
            $stmt = $conn->prepare("INSERT INTO users (id, name, username, password, shift_type, position, emp_number) VALUES (:id, :n, :u, :p, :s, :pos, :code)");
            $stmt->execute([':id' => $newEmpId, ':n' => $emp_name, ':u' => $emp_username, ':p' => password_hash($emp_pass, PASSWORD_DEFAULT), ':s' => $emp_shift, ':pos' => $emp_position !== '' ? $emp_position : null, ':code' => $emp_code]);
            writeLog('EMP_CREATED', 'Employee created: ' . $emp_username . ' (#' . $newEmpId . ') code ' . $emp_code);
            $success = 'Employee ' . htmlspecialchars($emp_name) . ' saduni! Code: ' . htmlspecialchars($emp_code) . ' | Username: ' . $emp_username;
        }
    }

    /* --- Create admin --- */
    if (isset($_POST['create_admin'])) {
        $adm_name     = trim($_POST['admin_name'] ?? '');
        $adm_username = trim($_POST['admin_username'] ?? '');
        $adm_email    = trim($_POST['admin_email'] ?? '');
        $adm_pass     = $_POST['admin_password'] ?? '';
        $adm_conf     = $_POST['admin_confirm'] ?? '';
        $adm_code     = trim($_POST['admin_code'] ?? '');

        if ($adm_name === '' || $adm_username === '' || $adm_pass === '') {
            $error = 'Name, Username saha Password okkoma anivaryayi.';
        } elseif ($adm_conf === '' || $adm_pass !== $adm_conf) {
            $error = 'Password saha Confirm Password deka samana nahi.';
        } elseif (mb_strlen($adm_name) < 2 || mb_strlen($adm_name) > 100) {
            $error = 'Name eka akuru 2-100 athara venna one.';
        } elseif (!validUsernameFormat($adm_username)) {
            $error = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
        } elseif (strlen($adm_pass) < 6 || strlen($adm_pass) > 72) {
            $error = 'Admin password eka akuru 6-72 athara venna one.';
        } elseif ($adm_email !== '' && !filter_var($adm_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Walangu email ekak danna.';
        } elseif ($adm_code === '') {
            $error = 'Admin Code eka anivarya.';
        } elseif (mb_strlen($adm_code) > 50) {
            $error = 'Admin Code eka akuru 50 n ithi witharak venna one.';
        } elseif (codeExists($conn, $adm_code, 'admin_users')) {
            $error = 'Me admin code eka danata use karana. Vena ekak danna.';
        } elseif (usernameExistsAnywhere($conn, $adm_username)) {
            $error = 'Me username eka danata use karana. Vena ekak danna.';
        } else {
            $stmt = $conn->prepare("INSERT INTO admin_users (id, name, username, email, password, emp_number) VALUES (:id, :n, :u, :e, :p, :code)");
            $stmt->execute([':id' => nextFreeId($conn, 'admin_users'), ':n' => $adm_name, ':u' => $adm_username, ':e' => $adm_email !== '' ? $adm_email : null, ':p' => password_hash($adm_pass, PASSWORD_DEFAULT), ':code' => $adm_code]);
            writeLog('ADMIN_CREATED', 'Admin created via create_account: ' . $adm_username . ' (code ' . $adm_code . ')');
            $success = 'Admin account eka saduni! Code: ' . $adm_code . ' | Username: ' . $adm_username;
        }
    }

    /* --- Set employee password / username --- */
    if (isset($_POST['set_emp_password'])) {
        $emp_id       = (int)($_POST['emp_id'] ?? 0);
        $emp_username = trim($_POST['emp_username'] ?? '');
        $emp_pass     = $_POST['emp_password'] ?? '';

        if ($emp_id <= 0) {
            $error = 'Employee kenekwa select karanna.';
        } elseif ($emp_pass === '') {
            $error = 'Password eka anivaryayi.';
        } elseif (strlen($emp_pass) < 4 || strlen($emp_pass) > 72) {
            $error = 'Password eka akuru 4-72 athara venna one.';
        } elseif ($emp_username !== '' && !validUsernameFormat($emp_username)) {
            $error = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
        } elseif ($emp_username !== '' && usernameExistsAnywhere($conn, $emp_username, $emp_id, 'users')) {
            $error = 'Me username eka danata use karana. Vena ekak danna.';
        } else {
            $stmt = $conn->prepare("SELECT name FROM users WHERE id = :id");
            $stmt->execute([':id' => $emp_id]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'Employee #' . $emp_id . ' db eke nathi.';
            } else {
                if ($emp_username !== '') {
                    $stmt = $conn->prepare("UPDATE users SET username = :u, password = :p WHERE id = :id");
                    $stmt->execute([':u' => $emp_username, ':p' => password_hash($emp_pass, PASSWORD_DEFAULT), ':id' => $emp_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET password = :p WHERE id = :id");
                    $stmt->execute([':p' => password_hash($emp_pass, PASSWORD_DEFAULT), ':id' => $emp_id]);
                }
                writeLog('EMP_PASSWORD_SET', 'Password set for User#' . $emp_id);
                $success = 'Employee #' . $emp_id . ' ge password eka set kala.';
            }
        }
    }

    /* --- Change admin username / password --- */
    if (isset($_POST['change_admin_pass'])) {
        $adm_id       = (int)($_POST['adm_id'] ?? 0);
        $adm_username = trim($_POST['adm_username'] ?? '');
        $adm_newpass  = $_POST['adm_new_password'] ?? '';
        $adm_conf     = $_POST['adm_confirm_password'] ?? '';

        if ($adm_id <= 0) {
            $error = 'Admin kenekwa select karanna.';
        } elseif ($adm_newpass === '' || $adm_conf === '') {
            $error = 'New password eka deka (new + confirm) anivaryayi.';
        } elseif ($adm_newpass !== $adm_conf) {
            $error = 'Password deka samana nahi.';
        } elseif (strlen($adm_newpass) < 6 || strlen($adm_newpass) > 72) {
            $error = 'Password eka akuru 6-72 athara venna one.';
        } elseif ($adm_username !== '' && !validUsernameFormat($adm_username)) {
            $error = 'Username akuru 3-50 (a-z, A-Z, 0-9, . _) witharak venna one.';
        } elseif ($adm_username !== '' && usernameExistsAnywhere($conn, $adm_username, $adm_id, 'admin_users')) {
            $error = 'Me username eka danata use karana. Vena ekak danna.';
        } else {
            $stmt = $conn->prepare("SELECT username FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $adm_id]);
            $adm = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$adm) {
                $error = 'Admin #' . $adm_id . ' db eke nathi.';
            } else {
                if ($adm_username !== '') {
                    $stmt = $conn->prepare("UPDATE admin_users SET username = :u, password = :p WHERE id = :id");
                    $stmt->execute([':u' => $adm_username, ':p' => password_hash($adm_newpass, PASSWORD_DEFAULT), ':id' => $adm_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
                    $stmt->execute([':p' => password_hash($adm_newpass, PASSWORD_DEFAULT), ':id' => $adm_id]);
                }
                writeLog('ADMIN_PASSWORD_CHANGED', 'Owner changed Admin#' . $adm_id . ' password');
                $success = 'Admin #' . $adm_id . ' (' . $adm['username'] . ') ge password eka venas kala.';
            }
        }
    }

    /* --- Set employee code (existing account - manual) --- */
    if (isset($_POST['set_emp_code'])) {
        $sid   = (int)($_POST['set_code_emp_id'] ?? 0);
        $scode = trim($_POST['new_emp_code'] ?? '');

        $stmt = $conn->prepare("SELECT name FROM users WHERE id = :id");
        $stmt->execute([':id' => $sid]);
        $srow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sid <= 0 || !$srow) {
            $error = 'Employee eka nathi.';
        } elseif ($scode === '') {
            $error = 'Employee Code eka anivarya.';
        } elseif (mb_strlen($scode) > 50) {
            $error = 'Code eka akuru 50 n ithi witharak venna one.';
        } elseif (codeExists($conn, $scode, 'users', $sid)) {
            $error = 'Me code eka danata use karana. Vena ekak danna.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET emp_number = :c WHERE id = :id");
            $stmt->execute([':c' => $scode, ':id' => $sid]);
            writeLog('EMP_CODE_CHANGED', 'Employee #' . $sid . ' code -> ' . $scode);
            $success = 'Employee #' . $sid . ' (' . $srow['name'] . ') ge code eka "' . $scode . '" kala.';
        }
    }

    /* --- Set admin code (existing account - manual) --- */
    if (isset($_POST['set_admin_code'])) {
        $aid   = (int)($_POST['set_code_admin_id'] ?? 0);
        $acode = trim($_POST['new_admin_code'] ?? '');

        $stmt = $conn->prepare("SELECT name FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $aid]);
        $arow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($aid <= 0 || !$arow) {
            $error = 'Admin eka nathi.';
        } elseif ($acode === '') {
            $error = 'Admin Code eka anivarya.';
        } elseif (mb_strlen($acode) > 50) {
            $error = 'Code eka akuru 50 n ithi witharak venna one.';
        } elseif (codeExists($conn, $acode, 'admin_users', $aid)) {
            $error = 'Me code eka danata use karana. Vena ekak danna.';
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET emp_number = :c WHERE id = :id");
            $stmt->execute([':c' => $acode, ':id' => $aid]);
            writeLog('ADMIN_CODE_CHANGED', 'Admin #' . $aid . ' code -> ' . $acode);
            $success = 'Admin #' . $aid . ' (' . $arow['name'] . ') ge code eka "' . $acode . '" kala.';
        }
    }

/* --- Replace: delete kala + eyage thanata aluth kenek danna (code eka nawa format) --- */
    if (isset($_POST['replace'])) {
        $repType = (($_POST['target_type'] ?? '') === 'admin') ? 'admin' : 'employee';
        $repId   = (int)($_POST['target_id'] ?? 0);
        $repName = '';

        if ($repType === 'admin') {
            if ($repId === $ownerId) {
                $error = 'Owner (oyage) account eka replace karanna beri.';
            } elseif ((int)$conn->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() <= 1) {
                $error = 'Pariththikaya thiyena ekama admin account eka. Replace karanna beri.';
            } else {
                $stmt = $conn->prepare("SELECT name, username, emp_number FROM admin_users WHERE id = :id");
                $stmt->execute([':id' => $repId]);
                $del = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($del) {
                    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = :id");
                    $stmt->execute([':id' => $repId]);
                    writeLog('ACCOUNT_REPLACED', 'Admin deleted for replacement: ' . $del['username'] . ' (#' . $repId . ')');
                    $presetRole  = 'admin';
                    $presetCode  = (string)($del['emp_number'] ?? '');
                    $repName     = $del['name'];
                } else {
                    $error = 'Admin #' . $repId . ' nathi.';
                }
            }
        } else {
            $stmt = $conn->prepare("SELECT name, username, emp_number, shift_type, position FROM users WHERE id = :id");
            $stmt->execute([':id' => $repId]);
            $del = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($del) {
                $stmt = $conn->prepare("DELETE FROM leave_requests WHERE user_id = :id");
                $stmt->execute([':id' => $repId]);
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $repId]);
                writeLog('ACCOUNT_REPLACED', 'Employee deleted for replacement: ' . $del['username'] . ' (#' . $repId . ')');
                $presetRole  = 'employee';
                $presetCode  = (string)($del['emp_number'] ?? '');
                $presetShift = $del['shift_type'] ?? '';
                $repName     = $del['name'];
            } else {
                $error = 'Employee eka nathi.';
            }
        }

        if (empty($error)) {
            $success = $repName . ' delete kala. Danata eyage thanata aluth kenek add karanna! Code eka: '
                . ($presetCode !== '' ? $presetCode . ' (hoi, venas karanna puluwan)' : ' (aluth ekak danna)');
        }
    }

    /* --- Delete admin (owner itself / last admin cannot be deleted) --- */
    if (isset($_POST['delete_admin'])) {
        $del_id = (int)($_POST['delete_admin'] ?? 0);
        if ($del_id === $ownerId) {
            writeLog('DELETE_BLOCKED', 'Tried to delete owner');
            $error = 'Owner (oyage) account eka delete karanna beri.';
        } elseif ((int)$conn->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() <= 1) {
            $error = 'Pariththikaya thiyena admin account eka. Delete karanna beri.';
        } else {
            $stmt = $conn->prepare("SELECT username FROM admin_users WHERE id = :id");
            $stmt->execute([':id' => $del_id]);
            $del = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($del) {
                $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = :id");
                $stmt->execute([':id' => $del_id]);
                writeLog('ACCOUNT_DELETED', 'Admin deleted: ' . $del['username'] . ' (#' . $del_id . ')');
                $success = 'Admin #' . $del_id . ' (' . $del['username'] . ') delete kala.';
            } else {
                $error = 'Admin #' . $del_id . ' nathi.';
            }
        }
    }

    /* --- Delete employee (owner password again required) --- */
    if (isset($_POST['delete_emp_submit'])) {
        $delEmpId = (int)($_POST['emp_del_id'] ?? 0);
        $delPass  = trim($_POST['owner_pass'] ?? '');

        $stmt = $conn->prepare("SELECT name, username FROM users WHERE id = :id");
        $stmt->execute([':id' => $delEmpId]);
        $del = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($delEmpId <= 0 || !$del) {
            $error = 'Employee eka nathi.';
        } elseif (!password_verify($delPass, (string)$ownerRow['password'])) {
            writeLog('DELETE_BLOCKED', 'Wrong owner password for employee delete');
            $error = 'Owner password eka veradi. Delete karanna beri.';
        } else {
            $stmt = $conn->prepare("DELETE FROM leave_requests WHERE user_id = :id");
            $stmt->execute([':id' => $delEmpId]);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $delEmpId]);
            writeLog('ACCOUNT_DELETED', 'Employee deleted: ' . $del['username'] . ' (#' . $delEmpId . ')');
            $success = 'Employee #' . $delEmpId . ' (' . $del['name'] . ') saha eyage okkoma nivadu delete kala.';
        }
    }
}

$employees = $conn->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$admins    = $conn->query("SELECT * FROM admin_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AKK Solutions | Create & Manage Accounts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        .card-glass { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .input-glow:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    </style>
</head>
<body class="bg-slate-950 min-h-screen p-4 md:p-8 antialiased">

<?php if ($ownerValid && !$locked && empty($gateError) || ($ownerValid && $locked)): ?>
<div class="max-w-6xl mx-auto space-y-6">
<?php else: ?>
<div class="max-w-lg mx-auto py-8 space-y-6">
<?php endif; ?>

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                <span class="text-lg font-black text-white">AK</span>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">Create Account</h1>
                <p class="text-xs text-slate-400">Owner Gate: username + password ekak illanawa</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="index.php" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700/50 transition">
                <i class="fa-solid fa-house"></i> Home
            </a>
            <a href="login.php" class="text-xs font-semibold text-rose-400 hover:text-rose-300 px-3 py-2 rounded-xl bg-slate-800/50 border border-slate-700/50 transition">
                <i class="fa-solid fa-right-to-bracket"></i> Login
            </a>
        </div>
    </div>

<?php if ($ownerValid && $locked): ?>
    <div class="bg-rose-500/10 border border-rose-500/25 text-rose-300 p-3 rounded-xl text-xs mb-4">
        <i class="fa-solid fa-lock"></i> <?= htmlspecialchars($gateError); ?>
    </div>
<?php endif; ?>

<?php if ($ownerValid): ?>
    <div class="flex items-center gap-2 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-xs text-emerald-300">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Block Active: Create Account ekata yanna beri - <b>Owner</b> Username + Password witharak. Session memory nathi (hama eka withakai ahannawa).</span>
    </div>

    <?php if ($error):   echo msg($error, false);   endif; ?>
    <?php if ($success): echo msg($success, true);  endif; ?>

    <!-- CREATE ACCOUNT -->
    <div class="card-glass border border-slate-700/40 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/15 flex items-center justify-center">
                <i class="fa-solid fa-user-plus text-emerald-400 text-sm"></i>
            </div>
            <div>
                <h2 class="font-bold text-white text-sm">Create Account</h2>
                <p class="text-[10px] text-slate-500">Alut Employee / Admin account ekak hadanna</p>
            </div>
        </div>
        <form method="POST" class="space-y-4">
            <?= hf(); ?>
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Account Type</label>
                <select name="role" id="createRole" onchange="toggleCreateRole()"
                    class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                    <option value="employee" <?= $presetRole !== 'admin' ? 'selected' : ''; ?>>Employee Account</option>
                    <option value="admin" <?= $presetRole === 'admin' ? 'selected' : ''; ?>>Admin Account</option>
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Full Name</label>
                    <input type="text" name="name" required placeholder="Enter full name"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Username</label>
                    <input type="text" name="username" required placeholder="Choose a username"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <div id="createCodeWrap">
                    <label id="createCodeLabel" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Employee Code</label>
                    <input type="text" name="code" id="createCode" required value="<?= htmlspecialchars($presetCode); ?>" placeholder="ud ah: EMP001"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
            </div>
            <div id="shiftWrap" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Shift</label>
                    <select name="shift" class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 input-glow transition-all [&>option]:bg-slate-900">
                        <option value="">-- Select Shift --</option>
                        <option value="M" <?= $presetShift === 'M' ? 'selected' : ''; ?>>Morning Shift</option>
                        <option value="E" <?= $presetShift === 'E' ? 'selected' : ''; ?>>Evening Shift</option>
                        <option value="BOTH" <?= $presetShift === 'BOTH' ? 'selected' : ''; ?>>Both Shifts</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Position</label>
                    <input type="text" name="position" placeholder="ud ah: Manager, Operator"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Password</label>
                    <input type="password" name="password" required placeholder="Min 4 characters"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="Ayeth liyanna"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
            </div>
            <button type="submit" name="create"
                class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 text-sm">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>
    </div>

    <!-- MANAGE ACCOUNTS -->
    <div id="manage-section" class="space-y-6">
        <div class="card-glass border border-indigo-500/20 rounded-2xl p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center">
                    <i class="fa-solid fa-users-gear text-indigo-400 text-sm"></i>
                </div>
                <div>
                    <h2 class="font-bold text-white text-sm">Manage Accounts</h2>
                    <p class="text-[10px] text-slate-500">Add / Password set / Edit / Delete (delete eke owner password again illanawa)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Add new admin -->
                <div class="border border-slate-700/40 rounded-2xl p-5 space-y-4">
                    <h3 class="text-xs font-bold text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-user-shield"></i> Add New Admin
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= hf(); ?>
                        <input type="text" name="admin_name" required placeholder="Admin Name"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        <input type="text" name="admin_username" required placeholder="Username (for login)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        <input type="text" name="admin_code" required value="<?= $presetRole === 'admin' ? htmlspecialchars($presetCode) : ''; ?>" placeholder="Admin Code (ud ah: A001)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        <input type="email" name="admin_email" placeholder="admin@company.com (optional)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="password" name="admin_password" required placeholder="Password (min 6)"
                                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                            <input type="password" name="admin_confirm" required placeholder="Confirm"
                                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                        </div>
                        <button type="submit" name="create_admin"
                            class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-indigo-600/20">
                            <i class="fa-solid fa-plus"></i> Add Admin
                        </button>
                    </form>
                </div>

                <!-- Add new employee -->
                <div class="border border-slate-700/40 rounded-2xl p-5 space-y-4">
                    <h3 class="text-xs font-bold text-emerald-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-user-plus"></i> Add New Employee
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= hf(); ?>
                        <input type="text" name="emp_name" required placeholder="Employee Name"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        <input type="text" name="emp_new_username" required placeholder="Username (for login)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        <input type="text" name="emp_code" required value="<?= $presetRole === 'employee' ? htmlspecialchars($presetCode) : ''; ?>" placeholder="Employee Code (ud ah: EMP001)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        <div class="grid grid-cols-2 gap-3">
                            <select name="emp_shift" required class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500 input-glow transition-all appearance-none cursor-pointer [&>option]:bg-slate-900">
                                <option value="">-- Shift --</option>
                                <option value="M">Morning</option>
                                <option value="E">Evening</option>
                                <option value="BOTH">Both</option>
                            </select>
                            <input type="text" name="emp_position" placeholder="Position"
                                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        </div>
                        <input type="password" name="emp_new_password" required placeholder="Password (min 4)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 input-glow transition-all">
                        <button type="submit" name="add_employee"
                            class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-emerald-600/20">
                            <i class="fa-solid fa-user-plus"></i> Add Employee
                        </button>
                    </form>
                </div>

                <!-- Set employee password -->
                <div class="border border-slate-700/40 rounded-2xl p-5 space-y-4">
                    <h3 class="text-xs font-bold text-purple-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-key"></i> Set / Change Employee Password
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= hf(); ?>
                        <select name="emp_id" required class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 input-glow transition-all appearance-none cursor-pointer [&>option]:bg-slate-900">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= (int)$e['id']; ?>"><?= htmlspecialchars((string)($e['emp_number'] ?? '')) ?: ('#' . (int)$e['id']); ?> &middot; <?= htmlspecialchars($e['name']); ?> (@<?= htmlspecialchars($e['username'] ?? 'no user'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="emp_username" placeholder="New username (hisa thiyenna one nam witharak)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 input-glow transition-all">
                        <input type="password" name="emp_password" required placeholder="New password"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 input-glow transition-all">
                        <button type="submit" name="set_emp_password"
                            class="w-full bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-purple-600/20">
                            <i class="fa-solid fa-check"></i> Set Password
                        </button>
                    </form>
                </div>

                <!-- Change admin password -->
                <div class="border border-slate-700/40 rounded-2xl p-5 space-y-4">
                    <h3 class="text-xs font-bold text-amber-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-user-lock"></i> Change Admin Password
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= hf(); ?>
                        <select name="adm_id" required class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-amber-500 input-glow transition-all appearance-none cursor-pointer [&>option]:bg-slate-900">
                            <option value="">-- Select Admin --</option>
                            <?php foreach ($admins as $a): ?>
                                <option value="<?= (int)$a['id']; ?>"><?= htmlspecialchars((string)($a['emp_number'] ?? '')) ?: ('#' . (int)$a['id']); ?> &middot; <?= htmlspecialchars($a['name']); ?> (@<?= htmlspecialchars($a['username'] ?? $a['email']); ?>)<?= (int)$a['id'] === $ownerId ? ' [OWNER]' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="adm_username" placeholder="New username (hisa thiyenna one nam witharak)"
                            class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="password" name="adm_new_password" required placeholder="New password (min 6)"
                                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
                            <input type="password" name="adm_confirm_password" required placeholder="Confirm"
                                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
                        </div>
                        <button type="submit" name="change_admin_pass"
                            class="w-full bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-amber-600/20">
                            <i class="fa-solid fa-floppy-disk"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account list -->
        <div class="card-glass border border-slate-700/40 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/40 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-700/20 flex items-center justify-center">
                    <i class="fa-solid fa-list text-slate-400 text-sm"></i>
                </div>
                <h2 class="font-bold text-white text-sm">All Accounts</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Admins -->
                <div>
                    <h3 class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-2">Admin Accounts</h3>
                    <?php if (count($admins) > 0): ?>
                        <?php foreach ($admins as $a): ?>
                            <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/40 mb-1.5">
                                <div class="w-7 h-7 rounded-full <?= (int)$a['id'] === $ownerId ? 'bg-amber-500/20 text-amber-400' : 'bg-indigo-500/20 text-indigo-400'; ?> flex items-center justify-center text-[10px] font-bold">
                                    <i class="fa-solid <?= (int)$a['id'] === $ownerId ? 'fa-crown' : 'fa-user-shield'; ?>"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-white"><?= htmlspecialchars($a['name']); ?> <?= (int)$a['id'] === $ownerId ? '<span class="text-amber-400 text-[9px] font-bold">OWNER</span>' : ''; ?></p>
                                    <p class="text-[10px] text-slate-400"><span class="text-indigo-300 font-semibold"><?= htmlspecialchars((string)($a['emp_number'] ?? '')) ?: '—'; ?></span> &middot; @<?= htmlspecialchars($a['username'] ?? $a['email']); ?></p>
                                </div>
                                <button type="button" data-set-code="adm" data-id="<?= (int)$a['id']; ?>" class="text-slate-400 hover:text-amber-300 hover:bg-amber-500/10 p-1.5 rounded-lg transition text-[10px]" title="Change Admin Code (manual)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if ((int)$a['id'] !== $ownerId): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Mema Admin eka delete kara eyage thanata aluth kenek danna?')">
                                        <?= hf(); ?>
                                        <input type="hidden" name="target_type" value="admin">
                                        <input type="hidden" name="target_id" value="<?= (int)$a['id']; ?>">
                                        <button type="submit" name="replace" class="text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete + aluth kenek add karanna (eyage tana)">
                                            <i class="fa-solid fa-arrows-rotate"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Mema Admin account eka maekeema tahawurankarada?')">
                                        <?= hf(); ?>
                                        <button type="submit" name="delete_admin" value="<?= (int)$a['id']; ?>"
                                            class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-500">No admin accounts yet.</p>
                    <?php endif; ?>
                </div>
                <!-- Employees -->
                <div>
                    <h3 class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mb-2">Employee Accounts</h3>
                    <div class="space-y-1 max-h-72 overflow-y-auto pr-1">
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $e): ?>
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/40">
                                    <div class="w-7 h-7 rounded-full <?= !empty($e['password']) ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-700 text-slate-400'; ?> flex items-center justify-center text-[10px] font-bold">
                                        <i class="fa-solid <?= !empty($e['password']) ? 'fa-check' : 'fa-xmark'; ?>"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-medium text-white"><span class="text-emerald-300 font-semibold"><?= htmlspecialchars((string)($e['emp_number'] ?? '')) ?: '—'; ?></span> <?= htmlspecialchars($e['name']); ?></p>
                                        <p class="text-[10px] text-slate-400">#<?= (int)$e['id']; ?> &middot; @<?= htmlspecialchars($e['username'] ?? 'N/A'); ?> &middot; <?= $e['shift_type']; ?> <?= !empty($e['position']) ? '&middot; ' . htmlspecialchars($e['position']) : ''; ?></p>
                                    </div>
                                    <button type="button" data-set-code="emp" data-id="<?= (int)$e['id']; ?>" class="text-slate-400 hover:text-amber-300 hover:bg-amber-500/10 p-1.5 rounded-lg transition text-[10px]" title="Change Employee Code (manual)">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Mema Employee eka delete kara eyage thanata aluth kenek danna?')">
                                        <?= hf(); ?>
                                        <input type="hidden" name="target_type" value="employee">
                                        <input type="hidden" name="target_id" value="<?= (int)$e['id']; ?>">
                                        <button type="submit" name="replace" class="text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete + aluth kenek add karanna (eyage tana)">
                                            <i class="fa-solid fa-arrows-rotate"></i>
                                        </button>
                                    </form>
                                    <button type="button" data-delete-emp="<?= (int)$e['id']; ?>" class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 p-1.5 rounded-lg transition text-[10px]" title="Delete (owner password need)">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-500">No employee accounts yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- hidden shared form for manual code change -->
    <form method="POST" id="codeSetForm" class="hidden">
        <?= hf(); ?>
        <input type="hidden" name="set_code_emp_id" id="codeSetEmpId" value="">
        <input type="hidden" name="new_emp_code" id="newEmpCode" value="">
        <input type="hidden" name="set_emp_code" id="setEmpCode" value="">
        <input type="hidden" name="set_code_admin_id" id="codeSetAdminId" value="">
        <input type="hidden" name="new_admin_code" id="newAdminCode" value="">
        <input type="hidden" name="set_admin_code" id="setAdminCode" value="">
    </form>

<?php else: ?>

    <!-- ============ OWNER GATE (LOCK) ============ -->
    <div class="card-glass border border-slate-700/40 rounded-3xl p-8 space-y-6 shadow-2xl">
        <div class="text-center space-y-3">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-br from-amber-500/20 to-rose-500/20 border border-amber-400/20 mx-auto flex items-center justify-center shadow-lg shadow-amber-500/10">
                <i class="fa-solid fa-lock text-amber-400 text-3xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-extrabold text-white">Create Account <span class="text-amber-400">Locked</span></h1>
                <p class="text-xs text-slate-400 mt-1">Owner Username + Password dala witharak create account ekata yanna puluwan</p>
            </div>
        </div>

        <?php if ($gateError): ?>
            <div class="p-3 rounded-xl border <?= $locked ? 'bg-amber-500/10 border-amber-500/25 text-amber-300' : 'bg-rose-500/10 border-rose-500/25 text-rose-300'; ?> text-xs flex items-center gap-2">
                <i class="fa-solid <?= $locked ? 'fa-hourglass-half' : 'fa-circle-xmark'; ?>"></i> <?= htmlspecialchars($gateError); ?>
            </div>
        <?php endif; ?>

        <?php if ($resetMsg): echo msg($resetMsg, true); endif; ?>
        <?php if ($resetErr): echo msg($resetErr, false); endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Owner Username</label>
                <input type="text" name="owner_username" required placeholder="Owner username"
                    class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Owner Password</label>
                <input type="password" name="owner_password" required placeholder="Owner password"
                    class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 input-glow transition-all">
            </div>
            <button type="submit" name="owner_login"
                class="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-semibold py-3.5 rounded-xl transition-all shadow-lg shadow-amber-600/25 flex items-center justify-center gap-2 text-sm">
                <i class="fa-solid fa-unlock-keyhole"></i> Unlock &amp; Open
            </button>
        </form>

        <!-- Owner reset (admin key) -->
        <details class="group border border-slate-700/40 rounded-2xl p-4">
            <summary class="flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer select-none list-none">
                <i class="fa-solid fa-key text-indigo-400"></i> Owner password miyakad? Admin Key ekin reset (Owner account na eheth hadanawa)
                <i class="fa-solid fa-chevron-down ml-auto text-slate-500 group-open:rotate-180 transition"></i>
            </summary>
            <form method="POST" class="mt-4 space-y-3">
                <input type="text" name="admin_reg_key" required placeholder="Admin Registration Key (db.php ADMIN_REG_KEY)"
                    class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <input type="text" name="owner_new_username" required placeholder="Alut Owner Username"
                    class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                <div class="grid grid-cols-2 gap-3">
                    <input type="password" name="owner_new_password" required placeholder="Alut Password (min 6)"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                    <input type="password" name="owner_confirm_password" required placeholder="Confirm"
                        class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 input-glow transition-all">
                </div>
                <button type="submit" name="reset_owner"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-indigo-600/20">
                    <i class="fa-solid fa-rotate"></i> Reset / Create Owner Account
                </button>
            </form>
        </details>

        <p class="text-[10px] text-slate-500 text-center">
            <i class="fa-solid fa-shield-halved"></i> Employee &amp; anith admins ta create account blocked. Owner witharak.
        </p>
    </div>

<?php endif; ?>
</div>

<!-- Delete employee: owner password prompt -->
<div id="deletePrompt" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-slate-900 border border-slate-700/70 rounded-2xl p-6 space-y-4 shadow-2xl">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-rose-500/15 flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation text-rose-400 text-sm"></i>
            </div>
            <div>
                <h3 class="font-bold text-white text-sm">Delete Employee</h3>
                <p class="text-[10px] text-slate-500">Confirm kirimata Owner password eka danna</p>
            </div>
        </div>
        <form method="POST" action="create_account.php" class="space-y-3">
            <?= isset($GLOBALS['HF_owner_u']) ? hf() : ''; ?>
            <input type="hidden" name="emp_del_id" id="deleteEmpId">
            <input type="password" name="owner_pass" required placeholder="Owner password"
                class="w-full bg-slate-800/70 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 input-glow transition-all">
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="document.getElementById('deletePrompt').classList.add('hidden')"
                    class="bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold px-4 py-3 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" name="delete_emp_submit"
                    class="bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold px-4 py-3 rounded-xl transition shadow-md shadow-rose-600/20">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCreateRole() {
    const role = document.getElementById('createRole').value;
    document.getElementById('shiftWrap').style.display = role === 'employee' ? '' : 'none';
    const lbl = document.getElementById('createCodeLabel');
    const ph  = document.getElementById('createCode');
    if (role === 'admin') {
        lbl.textContent = 'Admin Code';
        ph.placeholder  = 'ud ah: A001';
    } else {
        lbl.textContent = 'Employee Code';
        ph.placeholder  = 'ud ah: EMP001';
    }
}
function showDeletePrompt(id) {
    document.getElementById('deleteEmpId').value = id;
    document.getElementById('deletePrompt').classList.remove('hidden');
}
document.querySelectorAll('[data-delete-emp]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        showDeletePrompt(btn.getAttribute('data-delete-emp'));
    });
});
document.querySelectorAll('[data-set-code]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var kind = btn.getAttribute('data-set-code');
        var id   = btn.getAttribute('data-id');
        var code = prompt(kind === 'adm' ? 'Aluth Admin Code eka liyanna:' : 'Aluth Employee Code eka liyanna:');
        if (code === null) return;
        code = code.trim();
        if (code === '') { alert('Code eka hisa thiyanna beri.'); return; }
        if (kind === 'adm') {
            document.getElementById('codeSetAdminId').value = id;
            document.getElementById('newAdminCode').value   = code;
            document.getElementById('setAdminCode').value   = '1';
        } else {
            document.getElementById('codeSetEmpId').value = id;
            document.getElementById('newEmpCode').value   = code;
            document.getElementById('setEmpCode').value   = '1';
        }
        document.getElementById('codeSetForm').submit();
    });
});
if (document.getElementById('createRole')) toggleCreateRole();
</script>
</body>
</html>