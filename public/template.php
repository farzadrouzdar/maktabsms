<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<nav class="bg-blue-500 p-4 mb-6">
    <ul class="flex flex-col md:flex-row gap-4 text-white">
        <li><a href="dashboard.php" class="hover:underline">داشبورد</a></li>
        <li><a href="send_sms.php" class="hover:underline">ارسال پیامک تکی</a></li>
        <?php if ($_SESSION['role'] === 'school_admin'): ?>
            <li><a href="manage_users.php" class="hover:underline">مدیریت کاربران</a></li>
            <li><a href="settings.php" class="hover:underline">تنظیمات</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="hover:underline">خروج</a></li>
    </ul>
</nav>