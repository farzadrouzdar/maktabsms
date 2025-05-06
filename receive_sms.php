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
$school_id = $user['school_id'];

// ساخت لینک API با استفاده از مقادیر کانفیگ
$api_url = SABANOVIN_BASE_URL . '/sms/receive.json?gateway=' . SABANOVIN_GATEWAY . '&is_read=0';

// دریافت پیامک‌ها از API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // فقط برای تست، توی محیط واقعی باید SSL رو فعال کنی
$response = curl_exec($ch);
curl_close($ch);

$messages_from_api = [];
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['entries']) && $data['status']['code'] == 200) {
        $messages_from_api = $data['entries'];

        // ذخیره پیامک‌ها توی دیتابیس
        foreach ($messages_from_api as $msg) {
            $reference_id = $msg['reference_id'];
            $sender_mobile = $msg['number'];
            $content = $msg['message'];
            $received_at = $msg['datetime'];
            $created_at = date('Y-m-d H:i:s'); // زمان فعلی به‌عنوان created_at

            // چک کن اگه پیامک قبلاً ذخیره نشده، ذخیره کن
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM received_sms WHERE reference_id = ? AND school_id = ?");
            $stmt->execute([$reference_id, $school_id]);
            $exists = $stmt->fetchColumn();

            if (!$exists) {
                $stmt = $pdo->prepare("INSERT INTO received_sms (school_id, reference_id, sender_mobile, content, received_at, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$school_id, $reference_id, $sender_mobile, $content, $received_at, $created_at]);
            }
        }
    } else {
        $error = "خطا در دریافت پیامک‌ها: " . ($data['status']['message'] ?? 'پاسخ نامعتبر');
    }
}

// Handle search filter
$search_query = isset($_GET['search']) ? filter_var($_GET['search'], FILTER_SANITIZE_STRING) : '';
$date_filter = isset($_GET['date']) ? filter_var($_GET['date'], FILTER_SANITIZE_STRING) : '';

$query = "SELECT * FROM received_sms WHERE school_id = ?";
$params = [$school_id];

if (!empty($search_query)) {
    $query .= " AND sender_mobile LIKE ?";
    $search_term = "%$search_query%";
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(received_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY received_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دریافت پیامک - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css">
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
                        <a href="dashboard.php" class="nav-link">
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
                        <a href="receive_sms.php" class="nav-link active">
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
                <h1>دریافت پیامک</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <!-- Search Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">فیلتر پیامک‌های دریافتی</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="search">جستجو بر اساس شماره فرستنده</label>
                                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="شماره فرستنده">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date">فیلتر بر اساس تاریخ</label>
                                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary mt-4">فیلتر</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Messages List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست پیامک‌های دریافتی</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>شماره فرستنده</th>
                                    <th>متن پیام</th>
                                    <th>تاریخ دریافت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>شناسه مرجع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $message): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($message['sender_mobile']); ?></td>
                                        <td><?php echo htmlspecialchars($message['content']); ?></td>
                                        <td><?php echo htmlspecialchars($message['received_at']); ?></td>
                                        <td><?php echo htmlspecialchars($message['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($message['reference_id']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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