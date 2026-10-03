<?php
session_start();
include 'logger.php';
writeLog('LOGOUT', 'User logged out');
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
