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

// Get school_id for the user
$school_id = $user['school_id'];

// Get balance
$stmt = $pdo->prepare("SELECT balance FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
$balance = $school['balance'];

// Get total sent SMS
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sms_logs WHERE school_id = ? AND status = 'sent'");
$stmt->execute([$school_id]);
$total_sms = $stmt->fetch()['total'];

// Get total contacts
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contacts WHERE school_id = ?");
$stmt->execute([$school_id]);
$total_contacts = $stmt->fetch()['total'];
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
            <li class="nav-item">
                <a class="navbar-brand" href="dashboard.php">
                    <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
                </a>
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
        <a href="dashboard.php" class="brand-link">
            <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
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
                        <a href="dashboard.php" class="nav-link active">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>داشبورد</p>
                        </a>
                    </li>
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
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>خروج</p>
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
                <!-- Statistic Widgets -->
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-wallet"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">مانده شارژ</span>
                                <span class="info-box-number"><?php echo number_format($balance, 2); ?> تومان</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-comment"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">پیامک‌های ارسال‌شده</span>
                                <span class="info-box-number"><?php echo number_format($total_sms); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-users"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">تعداد مخاطبین</span>
                                <span class="info-box-number"><?php echo number_format($total_contacts); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
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