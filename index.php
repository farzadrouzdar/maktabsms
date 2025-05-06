<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mobile'])) {
        // Step 1: Send OTP
        $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
        if (preg_match('/^09[0-9]{9}$/', $mobile)) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['mobile'] = $mobile;

            // Send OTP via Sabanovin
            $message = "کد ورود شما: $otp\n" . SMS_FOOTER_TEXT;
            $url = "https://api.sabanovin.com/v1/" . SABANOVIN_API_KEY . "/sms/send.json";
            $data = [
                'gateway' => SABANOVIN_GATEWAY,
                'to' => $mobile,
                'text' => $message
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
            $response = curl_exec($ch);
            
            if ($response === false) {
                $error = "خطا در اتصال به سرویس پیامک: " . curl_error($ch);
                file_put_contents('sms_error.log', date('Y-m-d H:i:s') . " - cURL Error: " . curl_error($ch) . "\n", FILE_APPEND);
            } else {
                $result = json_decode($response, true);
                if (isset($result['status']['code']) && $result['status']['code'] == 200) {
                    $_SESSION['step'] = 'verify';
                    header('Location: index.php');
                    exit;
                } else {
                    $error = "خطا در ارسال پیامک: " . ($result['status']['message'] ?? 'پاسخ نامعتبر');
                    file_put_contents('sms_error.log', date('Y-m-d H:i:s') . " - API Response: " . $response . "\n", FILE_APPEND);
                }
            }
            curl_close($ch);
        } else {
            $error = "شماره موبایل نامعتبر است.";
        }
    } elseif (isset($_POST['otp'])) {
        // Step 2: Verify OTP
        $otp = filter_var($_POST['otp'], FILTER_SANITIZE_STRING);
        if ($otp == $_SESSION['otp']) {
            $mobile = $_SESSION['mobile'];
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
            $stmt->execute([$mobile]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "کاربری با این شماره یافت نشد.";
            }
        } else {
            $error = "کد واردشده اشتباه است.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css"> <!-- Custom CSS -->
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="index.php">
            <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
        </a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <?php if (isset($error)): ?>
                <p class="text-danger"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['step']) && $_SESSION['step'] === 'verify'): ?>
                <p class="login-box-msg">کد یک‌بارمصرف ارسال‌شده را وارد کنید</p>
                <form method="POST">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="کد یک‌بارمصرف" name="otp" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">تأیید کد</button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <p class="login-box-msg">برای ورود شماره موبایل خود را وارد کنید</p>
                <form method="POST">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="شماره موبایل" name="mobile" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-phone"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">ارسال کد یک‌بارمصرف</button>
                        </div>
                    </div>
                </form>
                <p class="mt-3"><a href="register.php">ثبت‌نام مرکز آموزشی</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>