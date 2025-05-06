<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// چک کردن لاگین
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// شبیه‌سازی ذخیره گروه
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_group = [
        'id' => rand(1000, 9999), // ID موقت
        'name' => $_POST['group_name']
    ];
    // TODO: ذخیره در دیتابیس واقعی
    $_SESSION['success'] = 'گروه با موفقیت اضافه شد!';
    header("Location: contacts.php");
    exit;
}
?>