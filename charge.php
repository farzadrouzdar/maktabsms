<?php
require_once 'config.php';
require_once 'menus.php'; // اضافه کردن فایل منوها
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$school_id = $user['school_id'];

// Function to get current balance from transactions
function get_balance($pdo, $school_id) {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) -
            SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as balance
        FROM transactions
        WHERE school_id = ? AND status = 'successful'
    ");
    $stmt->execute([$school_id]);
    return $stmt->fetch()['balance'] ?? 0;
}

// Get current balance
$balance = get_balance($pdo, $school_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['charge'])) {
    $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_INT);
    if ($amount >= 1000) {
        // ذخیره مبلغ توی سشن برای استفاده در callback
        $_SESSION['charge_amount'] = $amount;

        // تنظیمات زرین‌پال از کانفیگ
        $merchant_id = ZARINPAL_MERCHANT_ID;
        $callback_url = ZARINPAL_CALLBACK_URL;
        $description = "شارژ حساب کاربری - " . $user['name'];

        // درخواست پرداخت به زرین‌پال (بدون metadata)
        $data = array(
            "merchant_id" => $merchant_id,
            "amount" => $amount * 10, // زرین‌پال مبلغ رو به ریال می‌خواد (تومان * 10)
            "description" => $description,
            "callback_url" => $callback_url
        );

        $jsonData = json_encode($data);
        $ch = curl_init(ZARINPAL_REQUEST_URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);
        if (isset($result['data']['code']) && $result['data']['code'] == 100) {
            $authority = $result['data']['authority'];
            header("Location: " . ZARINPAL_STARTPAY_URL . $authority);
            exit;
        } else {
            $error = "خطا در اتصال به درگاه پرداخت: " . ($result['errors']['message'] ?? 'خطای ناشناخته');
        }
    } else {
        $error = "مبلغ باید حداقل 1000 تومان باشد.";
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شارژ پیامک - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
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
                <h1>شارژ پیامک</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">موجودی فعلی: <?php echo number_format(get_balance($pdo, $school_id)); ?> تومان</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="amount">مبلغ شارژ (تومان)</label>
                                <select class="form-control" name="amount" id="amount" required>
                                    <option value="1000">1,000 تومان</option>
                                    <option value="5000">5,000 تومان</option>
                                    <option value="10000">10,000 تومان</option>
                                    <option value="20000">20,000 تومان</option>
                                    <option value="50000">50,000 تومان</option>
                                </select>
                            </div>
                            <button type="submit" name="charge" class="btn btn-primary">پرداخت و شارژ</button>
                        </form>
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