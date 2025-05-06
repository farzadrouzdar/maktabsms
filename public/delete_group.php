<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// چک کردن لاگین
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// شبیه‌سازی حذف گروه
$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// TODO: حذف از دیتابیس واقعی
$_SESSION['success'] = 'گروه با موفقیت حذف شد!';
header("Location: contacts.php");
exit;
?>