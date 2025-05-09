<?php
require_once 'config.php';
session_start();

// تنظیم عنوان صفحه
$page_title = "ورود به سامانه پیامک مدارس";

// ایجاد زمان انقضای OTP (مثلاً 90 ثانیه)
define('OTP_EXPIRY_SECONDS', 90);

// بررسی پارامتر step برای بازگشت به مرحله ورود شماره
if (isset($_GET['step']) && $_GET['step'] === 'initial') {
    $_SESSION['step'] = 'initial';
    unset($_SESSION['otp'], $_SESSION['otp_sent_at'], $_SESSION['resend_success']); // پاک کردن فلگ موفقیت ارسال مجدد
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mobile'])) {
        $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
        if (preg_match('/^09[0-9]{9}$/', $mobile)) {
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['mobile'] = $mobile;
            $_SESSION['otp_sent_at'] = time();
            $_SESSION['step'] = 'verify';
            unset($_SESSION['resend_success']); // پاک کردن فلگ موفقیت ارسال مجدد

            $message = "کد ورود شما: $otp\n" . SMS_FOOTER_TEXT;
            $url = SABANOVIN_BASE_URL . "/sms/send.json";
            $data = [
                'gateway' => SABANOVIN_GATEWAY,
                'to' => $mobile,
                'text' => $message
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) {
                $error = "خطا در اتصال به سرویس پیامک: " . curl_error($ch);
            }
            curl_close($ch);
            header('Location: index.php');
            exit;
        } else {
            $error = "شماره موبایل نامعتبر است.";
        }
    } elseif (isset($_POST['otp'])) {
        $otp = filter_var($_POST['otp'], FILTER_SANITIZE_STRING);
        if ($otp == $_SESSION['otp']) {
            $mobile = $_SESSION['mobile'];
            $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
            $stmt->execute([$mobile]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['school_name'] = $user['school_name'];
                unset($_SESSION['otp'], $_SESSION['otp_sent_at'], $_SESSION['step'], $_SESSION['resend_success']);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "کاربری با این شماره یافت نشد.";
            }
        } else {
            $error = "کد واردشده اشتباه است.";
        }
    } elseif (isset($_POST['resend'])) {
        if (isset($_SESSION['mobile'])) {
            $last_sent = $_SESSION['otp_sent_at'] ?? 0;
            if (time() - $last_sent < OTP_EXPIRY_SECONDS) {
                $error = "لطفاً تا پایان شمارش صبر کنید.";
            } else {
                $_SESSION['otp'] = rand(100000, 999999);
                $_SESSION['otp_sent_at'] = time();
                $message = "کد ورود جدید: {$_SESSION['otp']}\n" . SMS_FOOTER_TEXT;

                // لاگ اطلاعات قبل از ارسال مجدد
                error_log("[RESEND OTP] Time: " . date('Y-m-d H:i:s') . ", Mobile: " . $_SESSION['mobile'] . ", New OTP: " . $_SESSION['otp'] . "\n", 3, 'otp_log.txt');

                $url = SABANOVIN_BASE_URL . "/sms/send.json";
                $data = [
                    'gateway' => SABANOVIN_GATEWAY,
                    'to' => $_SESSION['mobile'],
                    'text' => $message
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                curl_close($ch);

                // لاگ پاسخ API بعد از ارسال مجدد
                error_log("[RESEND OTP RESPONSE] Time: " . date('Y-m-d H:i:s') . ", Response: " . $response . "\n", 3, 'otp_log.txt');

                $_SESSION['resend_success'] = true; // تنظیم فلگ موفقیت
                header('Location: index.php'); // ریدایرکت برای نمایش پیغام
                exit;
            }
        }
    }
}

require_once 'header.php';
?>
<div class="login-box">
    <div class="login-logo">
        <a href="index.php">
            <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
        </a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <?php if (isset($error)): ?>
                <p class="text-danger"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['resend_success'])): ?>
                <p class="text-success">کد جدید ارسال شد.</p>
                <?php unset($_SESSION['resend_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['step']) && $_SESSION['step'] === 'verify'): ?>
                <?php
                $mobile_masked = substr($_SESSION['mobile'], 0, 4) . '****' . substr($_SESSION['mobile'], -3);
                $remaining = OTP_EXPIRY_SECONDS - (time() - ($_SESSION['otp_sent_at'] ?? 0));
                if ($remaining < 0) $remaining = 0;
                ?>
                <p class="login-box-msg">کد ارسال‌شده به شماره <?= $mobile_masked ?> را وارد کنید</p>
                <form method="POST">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="کد یک‌بارمصرف" name="otp" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="row">
                        <div class="col-6"><button type="submit" class="btn btn-primary btn-block">تأیید</button></div>
                        <div class="col-6">
                            <form method="POST">
                                <input type="hidden" name="resend" value="1">
                                <button type="submit" class="btn btn-secondary btn-block" id="resend-btn" <?php if ($remaining > 0) echo 'disabled'; ?>>ارسال مجدد</button>
                            </form>
                        </div>
                    </div>
                    <p class="text-center mt-3">
                        امکان ارسال مجدد در <span id="countdown"><?= $remaining ?></span> ثانیه
                        <br>
                        <a href="?step=initial">اصلاح شماره</a>
                    </p>
                </form>
            <?php else: ?>
                <p class="login-box-msg">برای ورود، شماره موبایل خود را وارد کنید</p>
                <form method="POST">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="mobile" placeholder="شماره موبایل" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-phone"></span></div></div>
                    </div>
                    <div class="row">
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-block">ارسال کد</button></div>
                    </div>
                </form>
                <p class="mt-3"><a href="register.php">ثبت‌نام مرکز آموزشی</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let seconds = parseInt(document.getElementById("countdown")?.innerText || 0);
const resendBtn = document.getElementById("resend-btn");
if (resendBtn) {
    const interval = setInterval(() => {
        seconds--;
        document.getElementById("countdown").innerText = seconds;
        resendBtn.disabled = seconds > 0;
        resendBtn.innerText = seconds > 0 ? `ارسال مجدد` : `ارسال مجدد`;
        if (seconds <= 0) {
            clearInterval(interval);
            resendBtn.innerText = "ارسال مجدد";
        }
    }, 1000);
}
</script>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>