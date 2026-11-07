<?php
require_once __DIR__ . '/../includes/auth.php';
$_SESSION = [];
session_destroy();
header('Location: ' . url('index.php'));
exit;