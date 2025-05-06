<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css"> <!-- Custom CSS -->
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="logout.php">خروج</a>
            </li>
        </ul>
    </nav>
    <!-- Main Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text font-weight-light">maktabsms</span>
        </a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="info">
                    <a href="#" class="d-block"><?php echo htmlspecialchars($user['name']); ?></a>
                </div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="phonebook.php" class="nav-link">
                            <i class="nav-icon fas fa-address-book"></i>
                            <p>دفترچه تلفن</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="drafts.php" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>پیش‌نویس‌ها</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_single.php" class="nav-link">
                            <i class="nav-icon fas fa-comment"></i>
                            <p>ارسال پیامک تکی</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_group.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>ارسال پیامک گروهی</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_smart.php" class="nav-link">
                            <i class="nav-icon fas fa-brain"></i>
                            <p>ارسال پیامک هوشمند</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="receive_sms.php" class="nav-link">
                            <i class="nav-icon fas fa-inbox"></i>
                            <p>دریافت پیامک</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="charge.php" class="nav-link">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>شارژ پیامک</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payments.php" class="nav-link">
                            <i class="nav-icon fas fa-list"></i>
                            <p>گزارش پرداخت‌ها</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>داشبورد</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <p>خوش آمدید، <?php echo htmlspecialchars($user['name']); ?>!</p>
            </div>
        </section>
    </div>
    <!-- Footer -->
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>