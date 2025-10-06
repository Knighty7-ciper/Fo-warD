<?php
session_start();

require_once __DIR__ . '/../config/auth.php';

Auth::logout();

header('Location: /frontend/index.php');
exit();
?>
