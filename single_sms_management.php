<?php
require_once 'config.php';
require_once 'menus.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT u.school_id FROM users u WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_school = $stmt->fetch();
$school_id = $user_school['school_id'];

// Function to get school balance from transactions table
function get_school_balance($school_id, $pdo) {
    $stmt = $pdo->prepare("SELECT SUM(amount) AS balance FROM transactions WHERE school_id = ? AND status = 'successful'");
    $stmt->execute([$school_id]);
    $result = $stmt->fetch();
    return $result['balance'] ?? 0;
}

$school_balance = get_school_balance($school_id, $pdo);

// Function to calculate SMS cost (remains the same)
function calculate_sms_cost($message) {
    $costPerPart = SMS_COST_PER_PART;
    $length = mb_strlen($message, 'UTF-8');
    $firstPartChars = SMS_MAX_CHARS_PART1;
    $otherPartsChars = SMS_MAX_CHARS_OTHER;

    if ($length <= $firstPartChars) {
        return $costPerPart;
    } else {
        $remainingChars = $length - $firstPartChars;
        $additionalParts = ceil($remainingChars / $otherPartsChars);
        return $costPerPart * (1 + $additionalParts);
    }
}

// Fetch all single SMS messages for the current school
$stmt = $pdo->prepare("SELECT * FROM single_sms WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$single_sms_list = $stmt->fetchAll();

// Handle actions (Send, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_sms']) && isset($_POST['sms_id_send'])) {
        $sms_id = filter_var($_POST['sms_id_send'], FILTER_SANITIZE_NUMBER_INT);
        $stmt_select = $pdo->prepare("SELECT mobile, message FROM single_sms WHERE id = ? AND school_id = ? AND status = 'ready'");
        $stmt_select->execute([$sms_id, $school_id]);
        $sms_to_send = $stmt_select->fetch();

        if ($sms_to_send) {
            $recipient = $sms_to_send['mobile'];
            $message_text = $sms_to_send['message'];
            $sms_cost = calculate_sms_cost($message_text);

            if ($school_balance >= $sms_cost) {
                $apiKey = SABANOVIN_API_KEY;
                $gateway = SABANOVIN_GATEWAY;
                $baseUrl = SABANOVIN_BASE_URL;

                $url = $baseUrl . '/' . $apiKey . '/sms/send.json?gateway=' . urlencode($gateway) . '&to=' . urlencode($recipient) . '&text=' . urlencode($message_text);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                        $batch_id = $response_data['batch_id'] ?? null;
                        $stmt_update = $pdo->prepare("UPDATE single_sms SET status = 'sent', sent_at = NOW(), batch_id = ? WHERE id = ?");
                        $stmt_update->execute([$batch_id, $sms_id]);
                        // Record the transaction
                        $stmt_transaction = $pdo->prepare("INSERT INTO transactions (school_id, type, amount, description, status) VALUES (?, ?, ?, ?, 'successful')");
                        $stmt_transaction->execute([$school_id, 'sms', -$sms_cost, 'ارسال پیامک تکی با شناسه ' . $sms_id]);

                        header("Location: single_sms_management.php?success=پیامک با موفقیت ارسال شد.");
                        exit;
                    } else {
                        $error_message = $response_data['status']['message'] ?? 'خطا در ارسال پیامک از API.';
                        $stmt_update = $pdo->prepare("UPDATE single_sms SET status = 'failed', error_message = ? WHERE id = ?");
                        $stmt_update->execute([$error_message, $sms_id]);
                        header("Location: single_sms_management.php?error=خطا در ارسال پیامک: " . urlencode($error_message));
                        exit;
                    }
                } else {
                    $error_message = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                    $stmt_update = $pdo->prepare("UPDATE single_sms SET status = 'failed', error_message = ? WHERE id = ?");
                    $stmt_update->execute([$error_message, $sms_id]);
                    header("Location: single_sms_management.php?error=خطا در ارسال پیامک: " . urlencode($error_message));
                    exit;
                }
            } else {
                header("Location: single_sms_management.php?error=موجودی حساب کافی نیست.");
                exit;
            }
        } elseif (isset($_POST['delete_sms']) && isset($_POST['sms_id_delete'])) {
            $sms_id = filter_var($_POST['sms_id_delete'], FILTER_SANITIZE_NUMBER_INT);
            $stmt_delete = $pdo->prepare("DELETE FROM single_sms WHERE id = ? AND school_id = ? AND (status = 'ready' OR status = 'pending')");
            $stmt_delete->execute([$sms_id, $school_id]);
            header("Location: single_sms_management.php?success=پیامک با موفقیت حذف شد.");
            exit;
        }
    }

// Fetch all single SMS messages for the current school (refetch after actions)
$stmt = $pdo->prepare("SELECT * FROM single_sms WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$single_sms_list = $stmt->fetchAll();

// Handle success/error messages
if (isset($_GET['success'])) {
    $success_message = filter_var($_GET['success'], FILTER_SANITIZE_STRING);
}
if (isset($_GET['error'])) {
    $error_message = filter_var($_GET['error'], FILTER_SANITIZE_STRING);
}

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پیامک‌های تکی - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <?php include 'top_navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>مدیریت پیامک‌های تکی</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست پیامک‌های تکی</h3>
                        <div class="card-tools">
                            <span class="badge badge-primary">موجودی: <?php echo number_format($school_balance); ?> تومان</span>
                            <a href="send-single.php" class="btn btn-success btn-sm ml-2">
                                <i class="fas fa-plus"></i> ارسال پیامک تکی جدید
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>گیرنده</th>
                                    <th>متن پیامک</th>
                                    <th>وضعیت</th>
                                    <th>هزینه</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>تاریخ ارسال</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($single_sms_list)): ?>
                                    <tr><td colspan="7" class="text-center">هیچ پیامک تکی ثبت شده است.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($single_sms_list as $sms): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sms['mobile']); ?></td>
                                            <td><?php echo htmlspecialchars(mb_substr($sms['message'], 0, 50)) . '...'; ?></td>
                                            <td>
                                                <?php
                                                switch ($sms['status']) {
                                                    case 'pending':
                                                        echo '<span class="badge badge-warning">در حال ایجاد</span>';
                                                        break;
                                                    case 'ready':
                                                        echo '<span class="badge badge-info">آماده ارسال</span>';
                                                        break;
                                                    case 'sent':
                                                        echo '<span class="badge badge-success">ارسال شده</span>';
                                                        break;
                                                    case 'failed':
                                                        echo '<span class="badge badge-danger">ناموفق</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge badge-secondary">نامشخص</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo number_format(calculate_sms_cost($sms['message'])); ?> تومان</td>
                                            <td><?php echo htmlspecialchars($sms['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($sms['sent_at'] ?? '-'); ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="sms_id_send" value="<?php echo $sms['id']; ?>">
                                                    <button type="submit" name="send_sms" class="btn btn-primary btn-sm" <?php if ($sms['status'] !== 'ready') echo 'disabled'; ?>>ارسال</button>
                                                </form>
                                                <a href="edit-single-sms.php?id=<?php echo $sms['id']; ?>" class="btn btn-warning btn-sm ml-1" <?php if ($sms['status'] !== 'ready' && $sms['status'] !== 'pending') echo 'disabled'; ?>>ویرایش</a>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="sms_id_delete" value="<?php echo $sms['id']; ?>">
                                                    <button type="submit" name="delete_sms" class="btn btn-danger btn-sm ml-1" onclick="return confirm('آیا مطمئنید؟')" <?php if ($sms['status'] !== 'ready' && $sms['status'] !== 'pending') echo 'disabled'; ?>>حذف</button>
                                                </form>
                                                <?php if ($sms['status'] === 'sent'): ?>
                                                    <button class="btn btn-info btn-sm ml-1">گزارش ارسال</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php include 'footer.php'; ?>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>