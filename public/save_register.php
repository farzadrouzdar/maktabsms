<?php
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mobile = trim($_POST['mobile']);
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);

    // اعتبارسنجی
    if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $_SESSION['error'] = "شماره موبایل نامعتبر است.";
        header("Location: register.php");
        exit;
    }
    if (empty($name) || empty($type)) {
        $_SESSION['error'] = "نام و نوع مدرسه نمی‌توانند خالی باشند.";
        header("Location: register.php");
        exit;
    }

    // چک کردن وجود کاربر
    $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "این شماره موبایل قبلاً ثبت شده است.";
        header("Location: register.php");
        exit;
    }

    // ثبت مدرسه
    $stmt = $pdo->prepare("INSERT INTO schools (name, type, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$name, $type]);
    $school_id = $pdo->lastInsertId();

    // ثبت کاربر
    $stmt = $pdo->prepare("INSERT INTO users (mobile, school_id, role, balance, created_at) VALUES (?, ?, 'school_admin', 0, NOW())");
    $stmt->execute([$mobile, $school_id]);

    $_SESSION['message'] = "مدرسه با موفقیت ثبت شد. لطفاً وارد شوید.";
    header("Location: login.php");
    exit;
}
?>