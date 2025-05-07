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

// بررسی پارامترهای بازگشتی از زرین‌پال
if (isset($_GET['Authority']) && isset($_GET['Status'])) {
    $authority = filter_var($_GET['Authority'], FILTER_SANITIZE_STRING);
    $status = filter_var($_GET['Status'], FILTER_SANITIZE_STRING);
    $amount = isset($_SESSION['charge_amount']) ? $_SESSION['charge_amount'] : 0;

    // لاگ برای دیباگ
    file_put_contents('debug.log', "User ID: " . $_SESSION['user_id'] . ", School ID: " . $school_id . ", Amount: " . $amount . ", Status: " . $status . "\n", FILE_APPEND);

    if ($status === 'OK') {
        // تأیید پرداخت با زرین‌پال
        $merchant_id = ZARINPAL_MERCHANT_ID;
        $data = array(
            "merchant_id" => $merchant_id,
            "authority" => $authority,
            "amount" => $amount * 10 // زرین‌پال مبلغ رو به ریال می‌خواد
        );

        $jsonData = json_encode($data);
        $ch = curl_init(ZARINPAL_VERIFY_URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);
        if (isset($result['data']['code']) && $result['data']['code'] == 100) {
            // پرداخت موفق
            $ref_id = $result['data']['ref_id'];

            // آپدیت موجودی مدرسه (جدول schools)
            $stmt = $pdo->prepare("UPDATE schools SET balance = balance + ? WHERE id = ?");
            $success = $stmt->execute([$amount, $school_id]);

            // لاگ برای چک کردن نتیجه آپدیت
            file_put_contents('debug.log', "Balance Update Success: " . ($success ? 'Yes' : 'No') . "\n", FILE_APPEND);

            // ثبت تراکنش در جدول payments
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, school_id, amount, authority, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $school_id, $amount, $authority, 'SUCCESS', date('Y-m-d H:i:s')]);

            // پاک کردن مبلغ از سشن
            unset($_SESSION['charge_amount']);

            $success = "پرداخت با موفقیت انجام شد. موجودی مدرسه شما شارژ شد.";
            header('Location: dashboard.php?success=' . urlencode($success));
            exit;
        } else {
            // ثبت تراکنش ناموفق
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, school_id, amount, authority, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $school_id, $amount, $authority, 'FAILED', date('Y-m-d H:i:s')]);

            $error = "خطا در تأیید پرداخت: " . ($result['errors']['message'] ?? 'خطای ناشناخته');
            header('Location: charge.php?error=' . urlencode($error));
            exit;
        }
    } else {
        // پرداخت لغو شده
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, school_id, amount, authority, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $school_id, $amount, $authority, 'CANCELED', date('Y-m-d H:i:s')]);

        $error = "پرداخت لغو شد.";
        header('Location: charge.php?error=' . urlencode($error));
        exit;
    }
} else {
    $error = "پارامترهای بازگشتی نامعتبر.";
    header('Location: charge.php?error=' . urlencode($error));
    exit;
}
?>