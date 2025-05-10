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

// ساخت لینک API با استفاده از مقادیر کانفیگ
$api_url = SABANOVIN_BASE_URL . '/sms/receive.json?gateway=' . SABANOVIN_GATEWAY . '&is_read=0';

// دریافت پیامک‌ها از API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // فقط برای تست، توی محیط واقعی باید SSL رو فعال کنی
$response = curl_exec($ch);

if ($response === false) {
    $error = "خطا در اتصال به API: " . curl_error($ch);
    file_put_contents('sms_errors.log', "cURL Error: " . curl_error($ch) . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $messages_from_api = [];
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if (isset($data['entries']) && $data['status']['code'] == 200) {
            $messages_from_api = $data['entries'];

            // ذخیره پیامک‌ها توی دیتابیس
            foreach ($messages_from_api as $msg) {
                $reference_id = $msg['reference_id'] ?? '';
                $sender_mobile = $msg['number'] ?? '';
                $content = $msg['message'] ?? '';
                $received_at = $msg['datetime'] ?? date('Y-m-d H:i:s');
                $created_at = date('Y-m-d H:i:s');

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
            file_put_contents('sms_errors.log', "API Error: " . ($data['status']['message'] ?? 'No message') . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        }
    } else {
        $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
        file_put_contents('sms_errors.log', "HTTP Error $http_code at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
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

// تنظیم عنوان صفحه
$page_title = "دریافت پیامک - سامانه پیامک مدارس";

// لود فایل header.php
require_once 'header.php';
?>

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
                        <?php if (empty($messages)): ?>
                            <div class="alert alert-info">هیچ پیامکی یافت نشد.</div>
                        <?php else: ?>
                            <table class="table table-bordered table-striped">
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
                                            <td><?php echo date('Y-m-d H:i', strtotime($message['received_at'])); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($message['reference_id']); ?></td>
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
    <!-- Footer -->
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>