<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// چک کردن لاگین
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// شبیه‌سازی ذخیره در دیتابیس
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_contact = [
        'id' => rand(1000, 9999), // ID موقت
        'name' => $_POST['name'] ?? '',
        'mobile' => $_POST['mobile'],
        'birthdate' => $_POST['birthdate'] ?? '',
        'field1' => $_POST['field1'] ?? '',
        'field2' => $_POST['field2'] ?? '',
        'field3' => $_POST['field3'] ?? '',
        'field4' => $_POST['field4'] ?? '',
        'group_id' => $_POST['group_id'] ? (int)$_POST['group_id'] : null
    ];
    // TODO: ذخیره در دیتابیس واقعی
    $_SESSION['success'] = 'مخاطب با موفقیت اضافه شد!';
    header("Location: contacts.php");
    exit;
}
?>