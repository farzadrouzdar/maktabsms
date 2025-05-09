<?php
require_once 'config.php';
require_once 'menus.php'; // اضافه کردن فایل منوها
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// گرفتن اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}
$school_id = $user['school_id'];

// گرفتن نام مرکز از ترکیب فیلدها
$stmt = $pdo->prepare("SELECT school_type, gender_type, school_name FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
$school_name = $school ? trim($school['school_type'] . ' ' . $school['gender_type'] . ' ' . $school['school_name']) : 'مدرسه نامشخص';

// فیلترها
$start_date = isset($_GET['start_date']) ? filter_var($_GET['start_date'], FILTER_SANITIZE_STRING) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? filter_var($_GET['end_date'], FILTER_SANITIZE_STRING) : date('Y-m-d');
$type = isset($_GET['type']) ? filter_var($_GET['type'], FILTER_SANITIZE_STRING) : 'all';

// جلوگیری از انتخاب تاریخ آینده برای end_date
if (strtotime($end_date) > strtotime(date('Y-m-d'))) {
    $end_date = date('Y-m-d');
}

// کوئری برای گرفتن تراکنش‌ها
$query = "SELECT * FROM transactions WHERE school_id = ? AND DATE(created_at) BETWEEN ? AND ?";
$params = [$school_id, $start_date, $end_date];
if ($type !== 'all') {
    $query .= " AND type = ?";
    $params[] = $type;
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// تولید توضیحات فارسی
function getTransactionDescription($transaction) {
    if ($transaction['type'] === 'credit') {
        if ($transaction['status'] === 'successful') {
            return "شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان (شماره تراکنش: " . ($transaction['payment_id'] ?? 'ندارد') . ")";
        } elseif ($transaction['status'] === 'failed') {
            return "تلاش برای شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان ناموفق بود";
        } else {
            return "شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان لغو شد";
        }
    } elseif ($transaction['type'] === 'debit') {
        if ($transaction['status'] === 'successful') {
            $details = json_decode($transaction['details'], true);
            $messageId = $details['message_id'] ?? 'نامشخص';
            $count = $details['count'] ?? '1';
            $batchId = $transaction['payment_id'] ?? 'ندارد';
            return "ارسال پیامک موفق به شناسه پیامک " . $messageId . " و تعداد " . $count . " با هزینه " . number_format(abs($transaction['amount'])) . " تومان (Batch ID: " . $batchId . ")";
        } else {
            return "تلاش برای ارسال پیامک با هزینه " . number_format(abs($transaction['amount'])) . " تومان ناموفق بود";
        }
    }
    return "تراکنش نامشخص با مبلغ " . number_format($transaction['amount']) . " تومان";
}

// تنظیم عنوان صفحه
$page_title = "تراکنش‌ها - سامانه پیامک مدارس";

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
                <h1>تراکنش‌ها</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">فیلتر تراکنش‌ها</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-3">
                                <label for="start_date">از تاریخ</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">تا تاریخ</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="type">نوع تراکنش</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>همه</option>
                                    <option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>شارژ</option>
                                    <option value="debit" <?php echo $type === 'debit' ? 'selected' : ''; ?>>ارسال پیامک</option>
                                </select>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <button type="submit" class="btn btn-primary">فیلتر</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">لیست تراکنش‌ها</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="alert alert-info">هیچ تراکنشی در بازه زمانی انتخاب‌شده یافت نشد.</div>
                        <?php else: ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>مبلغ</th>
                                        <th>نوع</th>
                                        <th>وضعیت</th>
                                        <th>شماره تراکنش</th>
                                        <th>توضیحات</th>
                                        <th>تاریخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo number_format($transaction['amount']); ?> <?php echo $transaction['type'] === 'credit' ? 'تومان' : ''; ?></td>
                                            <td><?php echo $transaction['type'] === 'credit' ? 'شارژ' : 'ارسال پیامک'; ?></td>
                                            <td>
                                                <span class="badge <?php echo $transaction['status'] === 'successful' ? 'badge-success' : ($transaction['status'] === 'failed' ? 'badge-danger' : 'badge-warning'); ?>">
                                                    <?php echo $transaction['status'] === 'successful' ? 'موفق' : ($transaction['status'] === 'failed' ? 'ناموفق' : 'لغو شده'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['payment_id'] ?? 'ندارد'); ?></td>
                                            <td><?php echo htmlspecialchars(getTransactionDescription($transaction)); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>

<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>