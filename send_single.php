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

// Function to get current balance
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

// Get initial balance
$balance = get_balance($pdo, $school_id);

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($mobile) && !empty($message)) {
        // Calculate SMS cost based on message length
        $message_length = mb_strlen($message, 'UTF-8');
        $sms_parts = 1;
        if ($message_length > SMS_MAX_CHARS_PART1) {
            $remaining_chars = $message_length - SMS_MAX_CHARS_PART1;
            $sms_parts += ceil($remaining_chars / SMS_MAX_CHARS_OTHER);
        }
        $sms_cost = $sms_parts * SMS_COST_PER_PART;

        // Get current balance before sending
        $current_balance = get_balance($pdo, $school_id);

        // Check if balance is sufficient
        if ($current_balance >= $sms_cost) {
            // Add footer text to the message
            $message .= ' ' . SMS_FOOTER_TEXT;

            // Prepare URL with parameters for Sabanovin API
            $url = SABANOVIN_BASE_URL . '/sms/send.json?gateway=' . urlencode(SABANOVIN_GATEWAY) . '&to=' . urlencode($mobile) . '&text=' . urlencode($message);

            // Send request to Sabanovin API
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Check API response
            if ($http_code == 200) {
                $response_data = json_decode($response, true);
                if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                    // Register transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (school_id, amount, status, created_at, details, type)
                        VALUES (?, ?, 'successful', NOW(), 'ارسال پیامک', 'debit')
                    ");
                    $stmt->execute([$school_id, $sms_cost]);

                    $success = "پیامک به شماره $mobile با موفقیت ارسال شد (Batch ID: " . $response_data['batch_id'] . "). هزینه: " . number_format($sms_cost) . " تومان.";

                    // Update balance after successful send
                    $balance = get_balance($pdo, $school_id);
                } else {
                    $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                }
            } else {
                $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
            }
        } else {
            $error = "مانده شارژ کافی نیست. لطفاً حساب خود را شارژ کنید. هزینه این پیامک: " . number_format($sms_cost) . " تومان.";
        }
    } else {
        $error = "شماره موبایل و متن پیام نمی‌توانند خالی باشند.";
    }
}

// Fetch approved drafts
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال پیامک تکی - سامانه پیامک مدارس</title>
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
                        <a href="send_single.php" class="nav-link active">
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
                        <a href="transactions.php" class="nav-link">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>تراکنش‌ها</p>
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
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>ارسال پیامک تکی</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <p>مانده شارژ: <?php echo number_format(get_balance($pdo, $school_id), 2); ?> تومان</p>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">ارسال پیامک به شماره دلخواه</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="mobile">شماره موبایل</label>
                                <input type="text" class="form-control" name="mobile" placeholder="مثال: 09123456789" required>
                            </div>
                            <div class="form-group">
                                <label for="draft_id">انتخاب پیش‌نویس</label>
                                <select class="form-control" name="draft_id" id="draft_id">
                                    <option value="">-- بدون پیش‌نویس --</option>
                                    <?php foreach ($approved_drafts as $draft): ?>
                                        <option value="<?php echo $draft['id']; ?>">
                                            <?php echo htmlspecialchars($draft['title']); ?> (<?php echo $draft['type'] == 'simple' ? 'ساده' : 'هوشمند'; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="message">متن پیام</label>
                                <textarea class="form-control" name="message" id="message" rows="3" required></textarea>
                                <small class="form-text text-muted">اگه پیش‌نویس انتخاب کنی، متن به‌صورت خودکار پر می‌شه.</small>
                            </div>
                            <button type="submit" name="send_message" class="btn btn-primary">ارسال پیامک</button>
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
<script>
    $(document).ready(function() {
        $('#draft_id').change(function() {
            var draft_id = $(this).val();
            if (draft_id) {
                $.ajax({
                    url: 'get_draft.php',
                    type: 'POST',
                    data: { draft_id: draft_id },
                    success: function(response) {
                        $('#message').val(response);
                    }
                });
            } else {
                $('#message').val('');
            }
        });
    });
</script>
</body>
</html>