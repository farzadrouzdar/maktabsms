<?php
require_once 'config.php';
session_start();

// تنظیم عنوان صفحه
$page_title = "ورود به سامانه پیامک مدارس";

// ایجاد زمان انقضای OTP (به ثانیه)
define('OTP_EXPIRY_SECONDS', 90);

// تابع برای تولید کد OTP
function generateOTP() {
    return rand(100000, 999999);
}

// تابع برای ارسال پیامک
function sendSMS($mobile, $message) {
    $url = SABANOVIN_BASE_URL . "/sms/send.json";
    $data = [
        'gateway' => SABANOVIN_GATEWAY,
        'to'      => $mobile,
        'text'    => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    // لاگ پاسخ سرویس پیامک
    error_log("[SMS RESPONSE] " . date('Y-m-d H:i:s') . " To: {$mobile} Response: {$response} Error: {$error}\n", 3, 'sms_log.txt');

    return ($response !== false);
}

// بررسی پارامتر step برای بازگشت به مرحله ورود شماره
if (isset($_GET['step']) && $_GET['step'] === 'initial') {
    $_SESSION['step'] = 'initial';
    unset($_SESSION['otp'], $_SESSION['mobile'], $_SESSION['otp_sent_at'], $_SESSION['resend_success'], $_SESSION['resend_wait_until']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // مرحلهٔ ارسال موبایل و تولید OTP
    if (isset($_POST['mobile'])) {
        $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
        if (preg_match('/^09[0-9]{9}$/', $mobile)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
            $stmt->execute([$mobile]);
            $userExists = $stmt->fetchColumn();

            if ($userExists) {
                $otp = generateOTP();
                $_SESSION['otp'] = $otp;
                $_SESSION['mobile'] = $mobile;
                $_SESSION['otp_sent_at'] = time();
                $_SESSION['step'] = 'verify';
                unset($_SESSION['resend_success'], $_SESSION['resend_wait_until']);

                $message = "کد ورود شما: $otp\n" . SMS_FOOTER_TEXT;
                if (sendSMS($mobile, $message)) {
                    header('Location: index.php');
                    exit;
                } else {
                    $error = "خطا در ارسال پیامک. لطفاً دوباره تلاش کنید.";
                    // در صورت عدم ارسال، اطلاعات OTP و زمان ارسال را پاک کنید
                    unset($_SESSION['otp'], $_SESSION['otp_sent_at']);
                }
            } else {
                $error = "کاربری با این شماره وجود ندارد.";
            }
        } else {
            $error = "شماره موبایل نامعتبر است.";
        }

    // مرحلهٔ وارد کردن OTP و ورود به داشبورد
    } elseif (isset($_POST['otp'])) {
        $otp = filter_var($_POST['otp'], FILTER_SANITIZE_STRING);
        if (isset($_SESSION['otp']) && $otp == $_SESSION['otp']) {
            $mobile = $_SESSION['mobile'];
            // انجام JOIN بین جدول users و schools برای دریافت نام مدرسه
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.role,
                    s.school_name
                FROM
                    users u
                INNER JOIN
                    schools s ON u.school_id = s.id
                WHERE
                    u.mobile = ?
            ");
            $stmt->execute([$mobile]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['school_name'] = $user['school_name'];
                unset($_SESSION['otp'], $_SESSION['mobile'], $_SESSION['otp_sent_at'], $_SESSION['step'], $_SESSION['resend_success'], $_SESSION['resend_wait_until']);
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "خطا در بازیابی اطلاعات کاربر یا مدرسه.";
            }
        } else {
            $error = "کد واردشده اشتباه است.";
        }

    // مرحلهٔ ارسال مجدد OTP (درخواست AJAX)
    } elseif (isset($_POST['resend']) && isset($_SESSION['mobile']) && isset($_SESSION['otp_sent_at'])) {
        $wait_until = $_SESSION['resend_wait_until'] ?? 0;
        $remaining_wait = $wait_until - time();

        if ($remaining_wait > 0) {
            http_response_code(429); // Too Many Requests
            echo json_encode(['error' => "لطفاً " . $remaining_wait . " ثانیه صبر کنید."]);
            exit;
        } else {
            $new_otp = generateOTP();
            $_SESSION['otp'] = $new_otp;
            $_SESSION['otp_sent_at'] = time();
            // تنظیم زمان انتظار برای ارسال مجدد بعدی (مثلاً 60 ثانیه)
            $_SESSION['resend_wait_until'] = time() + 60;

            $message = "کد ورود جدید: {$new_otp}\n" . SMS_FOOTER_TEXT;
            if (sendSMS($_SESSION['mobile'], $message)) {
                echo json_encode(['success' => true]);
                exit;
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(['error' => "خطا در ارسال مجدد کد. لطفاً دوباره تلاش کنید."]);
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
                $resend_available_in = ($_SESSION['resend_wait_until'] ?? 0) - time();
                if ($remaining < 0) $remaining = 0;
                if ($resend_available_in < 0) $resend_available_in = 0;
                ?>
                <p class="login-box-msg">کد ارسال‌شده به شماره <?= $mobile_masked ?> را وارد کنید</p>
                <form method="POST" id="verify-form">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="کد یک‌بارمصرف" name="otp" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="row">
                        <div class="col-6"><button type="submit" class="btn btn-primary btn-block">تأیید</button></div>
                        <div class="col-6">
                            <button type="button" class="btn btn-secondary btn-block" id="resend-btn" <?php if ($resend_available_in > 0 || !isset($_SESSION['otp_sent_at']) || (time() - $_SESSION['otp_sent_at'] < OTP_EXPIRY_SECONDS)) echo 'disabled'; ?>>ارسال مجدد</button>
                        </div>
                    </div>
                    <p class="text-center mt-3">
                        <?php if (isset($_SESSION['otp_sent_at']) && (time() - $_SESSION['otp_sent_at'] < OTP_EXPIRY_SECONDS)): ?>
                            امکان ارسال مجدد در <span id="countdown"><?= $remaining ?></span> ثانیه دیگر
                        <?php elseif (isset($_SESSION['resend_wait_until']) && ($_SESSION['resend_wait_until'] > time())): ?>
                            امکان ارسال مجدد در <span id="resend-countdown"><?= $resend_available_in ?></span> ثانیه دیگر
                        <?php else: ?>
                            اگر کد را دریافت نکرده‌اید، روی دکمه "ارسال مجدد" کلیک کنید.
                        <?php endif; ?>
                        <br><a href="?step=initial">اصلاح شماره</a>
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
let resendSeconds = parseInt(document.getElementById("resend-countdown")?.innerText || 0);
const resendBtn = document.getElementById("resend-btn");
const verifyForm = document.getElementById("verify-form"); // گرفتن آیدی فرم اصلی

if (resendBtn && seconds > 0) {
    const interval = setInterval(() => {
        seconds--;
        document.getElementById("countdown").innerText = seconds;
        resendBtn.disabled = seconds > 0;
        if (seconds <= 0) {
            clearInterval(interval);
            resendBtn.innerText = "ارسال مجدد";
            resendBtn.disabled = resendSeconds > 0;
        }
    }, 1000);
} else if (resendBtn && resendSeconds > 0) {
    const resendInterval = setInterval(() => {
        resendSeconds--;
        document.getElementById("resend-countdown").innerText = resendSeconds;
        resendBtn.disabled = resendSeconds > 0;
        if (resendSeconds <= 0) {
            clearInterval(resendInterval);
            resendBtn.innerText = "ارسال مجدد";
            resendBtn.disabled = false;
        }
    }, 1000);
} else if (resendBtn) {
    resendBtn.disabled = false;
}

// اضافه کردن رویداد کلیک به دکمه ارسال مجدد
resendBtn?.addEventListener('click', function() {
    // غیرفعال کردن دکمه برای جلوگیری از درخواست‌های مکرر
    resendBtn.disabled = true;
    resendBtn.innerText = "در حال ارسال...";

    // ایجاد یک فرم دیتا برای ارسال درخواست POST
    const formData = new FormData();
    formData.append('resend', '1');

    // ارسال درخواست AJAX به همین صفحه
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest' // برای تشخیص درخواست AJAX در سرور (اختیاری)
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload(); // ساده‌ترین راه برای به‌روزرسانی صفحه و نمایش پیغام موفقیت
        } else if (data.error) {
            alert(data.error);
            resendBtn.disabled = false;
            resendBtn.innerText = "ارسال مجدد";
        } else {
            alert('خطا در ارسال مجدد. لطفاً دوباره تلاش کنید.');
            resendBtn.disabled = false;
            resendBtn.innerText = "ارسال مجدد";
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در برقراری ارتباط با سرور.');
        resendBtn.disabled = false;
        resendBtn.innerText = "ارسال مجدد";
    });
});
</script>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>