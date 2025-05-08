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

// Function to calculate SMS cost based on recipient count (assuming SMS_COST_PER_PART is in Tomans)
function calculate_sms_cost($recipient_count) {
    $cost_per_sms = SMS_COST_PER_PART; // Cost per SMS in Tomans
    return $recipient_count * $cost_per_sms;
}

// Handle sending group message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_group_message'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

    if (!empty($group_id) && !empty($message)) {
        // Add footer text to the message
        $message .= ' ' . SMS_FOOTER_TEXT;

        // Fetch all contacts in the selected group
        $stmt = $pdo->prepare("SELECT mobile FROM contacts WHERE school_id = ? AND group_id = ?");
        $stmt->execute([$school_id, $group_id]);
        $contacts = $stmt->fetchAll();

        if (empty($contacts)) {
            $error = "هیچ مخاطبی در این گروه یافت نشد.";
        } else {
            $recipient_count = count($contacts);
            $sms_cost = calculate_sms_cost($recipient_count);
            $current_balance = get_balance($pdo, $school_id);

            if ($current_balance >= $sms_cost) {
                $mobiles = [];
                foreach ($contacts as $contact) {
                    $mobiles[] = $contact['mobile'];
                }
                $to_list = implode(',', $mobiles);

                // Prepare URL with parameters for Sabanovin API
                $url = SABANOVIN_BASE_URL . '/sms/send.json?gateway=' . urlencode(SABANOVIN_GATEWAY) . '&to=' . urlencode($to_list) . '&text=' . urlencode($message);

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
                        $success_count = $recipient_count;
                        $success = "پیامک به $success_count مخاطب با موفقیت ارسال شد (Batch ID: " . $response_data['batch_id'] . ").";

                        // Record transaction
                        $details = json_encode(['batch_id' => $response_data['batch_id'] ?? null, 'count' => $recipient_count]);
                        $stmt = $pdo->prepare("
                            INSERT INTO transactions (school_id, amount, status, payment_id, created_at, details, type)
                            VALUES (?, ?, 'successful', ?, NOW(), ?, 'debit')
                        ");
                        $stmt->execute([$school_id, $sms_cost, $response_data['batch_id'] ?? null, $details]);

                    } else {
                        $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                        file_put_contents('sms_errors.log', "Failed batch: " . $response . "\n", FILE_APPEND);
                    }
                } else {
                    $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                    file_put_contents('sms_errors.log', "HTTP Error $http_code for group $group_id\n", FILE_APPEND);
                }
            } else {
                $error = "موجودی حساب کافی نیست. هزینه ارسال: " . number_format($sms_cost) . " تومان، موجودی فعلی: " . number_format($current_balance) . " تومان.";
            }
        }
    } else {
        $error = "گروه و متن پیام نمی‌توانند خالی باشند.";
    }
}

// Fetch all contact groups
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// Fetch approved simple drafts
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' AND type = 'simple' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال پیامک گروهی - سامانه پیامک مدارس</title>
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
                <h1>ارسال پیامک گروهی</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">ارسال پیامک به گروهی از مخاطبین</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="group_id">انتخاب گروه</label>
                                <select class="form-control" name="group_id" required>
                                    <option value="">-- گروه را انتخاب کنید --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>">
                                            <?php echo htmlspecialchars($group['group_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="draft_id">انتخاب پیش‌نویس (فقط ساده)</label>
                                <select class="form-control" name="draft_id" id="draft_id">
                                    <option value="">-- بدون پیش‌نویس --</option>
                                    <?php foreach ($approved_drafts as $draft): ?>
                                        <option value="<?php echo $draft['id']; ?>">
                                            <?php echo htmlspecialchars($draft['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="message">متن پیام</label>
                                <textarea class="form-control" name="message" id="message" rows="3" required></textarea>
                                <small class="form-text text-muted">اگه پیش‌نویس انتخاب کنی، متن به‌صورت خودکار پر می‌شه.</small>
                            </div>
                            <button type="submit" name="send_group_message" class="btn btn-primary">ارسال پیامک گروهی</button>
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