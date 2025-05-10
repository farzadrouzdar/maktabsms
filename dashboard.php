<?php
require_once 'config.php';
require_once 'menus.php'; // اضافه کردن فایل منوها
session_start();

// تنظیم عنوان صفحه
$page_title = "داشبورد - سامانه پیامک مدارس";

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get school_id for the user
$school_id = $user['school_id'];

// Get balance from transactions (calculate sum of credits minus sum of debits)
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) -
        SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as balance
    FROM transactions
    WHERE school_id = ? AND status = 'successful'
");
$stmt->execute([$school_id]);
$balance = $stmt->fetch()['balance'] ?? 0;

// Get total sent SMS
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM single_sms WHERE school_id = ? AND status = 'sent'");
$stmt->execute([$school_id]);
$total_sms = $stmt->fetch()['total'];

// Get total contacts
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contacts WHERE school_id = ?");
$stmt->execute([$school_id]);
$total_contacts = $stmt->fetch()['total'];

// لود فایل header.php
require_once 'header.php';
?>
<div class="wrapper">
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
                    <?php foreach ($menus as $key => $menu): ?>
                        <li class="nav-item">
                            <a href="<?php echo $menu['url']; ?>" class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == basename($menu['url'])) echo 'active'; ?>">
                                <i class="nav-icon <?php echo $menu['icon']; ?>"></i>
                                <p><?php echo $menu['title']; ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>داشبورد</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <p>خوش آمدید، <?php echo htmlspecialchars($user['name']); ?>!</p>
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
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>