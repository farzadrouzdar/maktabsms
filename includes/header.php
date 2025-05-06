<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <title>سامانه مکتب اس‌ام‌اس</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav>
        <a href="dashboard.php">داشبورد</a>
        <a href="contacts.php">مخاطبین</a>
        <a href="logout.php">خروج</a>
    </nav>