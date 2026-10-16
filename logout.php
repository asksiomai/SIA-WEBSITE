<?php
// LOGOUT -> End.
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
session_start();
$_SESSION['flash'] = ['type' => 'success', 'msg' => 'You have been logged out.'];
header('Location: login.php');
exit;
