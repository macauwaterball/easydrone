<?php
require_once __DIR__ . '/session_check.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /auth/login.php');
    exit;
}