<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$otp_for_test = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mobile'])) {
        $mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING);
        if (preg_match('/^09[0-9]{9}$/', $mobile)) {
            try {
                $otp = generateOTP($mobile);
                $_SESSION['otp_mobile'] = $mobile;
                $message = "کد تأیید تولید شد.";
                $otp_for_test = $otp;
            } catch (Exception $e) {
                $message = "خطا در تولید کد تأیید: " . $e->getMessage();
            }
        } else {
            $message = "شماره موبایل معتبر نیست.";
        }
    } elseif (isset($_POST['otp'])) {
        $otp = trim(filter_input(INPUT_POST, 'otp', FILTER_SANITIZE_NUMBER_INT));
        $mobile = $_SESSION['otp_mobile'] ?? '';
        if ($mobile && validateOTP($mobile, $otp)) {
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT id, school_id, first_login FROM users WHERE mobile = ?");
            $stmt->bind_param("s", $mobile);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['school_id'] = $user['school_id'];

                if ($user['first_login']) {
                    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'welcome_message'");
                    $stmt->execute();
                    $welcome_message = $stmt->get_result()->fetch_assoc()['setting_value'];
                    $stmt->close();
                    sendSMS($mobile, $welcome_message);

                    $stmt = $conn->prepare("UPDATE users SET first_login = 0 WHERE id = ?");
                    $stmt->bind_param("i", $user['id']);
                    $stmt->execute();
                    $stmt->close();
                }

                header("Location: dashboard.php");
                exit;
            } else {
                $message = "کاربری با این شماره یافت نشد.";
            }
            $stmt->close();
            $conn->close();
        } else {
            $message = "کد تأیید نادرست است. لطفاً کد ۶ رقمی را دقیق وارد کنید.";
        }
    } elseif (isset($_POST['reset_mobile'])) {
        unset($_SESSION['otp_mobile']);
        $message = "شماره موبایل پاک شد. لطفاً شماره جدید وارد کنید.";
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه مکتب اس‌ام‌اس</title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Vazir -->
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/vazir@4.5.0/index.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center">
    <div id="particles-js" class="absolute inset-0 z-0"></div>
    <div class="relative z-10 bg-gray-800 bg-opacity-80 p-8 rounded-xl shadow-2xl w-full max-w-md animate__animated animate__slideInDown">
        <div class="text-center mb-6">
            <img src="assets/dist/img/logo-placeholder.png" alt="مکتب اس‌ام‌اس" class="mx-auto mb-4 w-24 animate__animated animate__pulse animate__infinite" style="filter: drop-shadow(0 0 10px #00f0ff);">
            <h1 class="text-3xl font-bold text-white">مکتب اس‌ام‌اس</h1>
        </div>

        <?php if ($message): ?>
            <p class="text-center p-3 rounded <?php echo strpos($message, 'خطا') !== false || strpos($message, 'نادرست') !== false ? 'bg-red-500 text-white' : 'bg-green-500 text-white'; ?> animate__animated animate__bounceIn mb-4">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <?php if ($otp_for_test): ?>
            <p class="text-center p-3 bg-blue-500 text-white rounded animate__animated animate__bounceIn mb-4">
                کد OTP شما: <strong><?php echo $otp_for_test; ?></strong>
            </p>
        <?php endif; ?>

        <?php if (!isset($_SESSION['otp_mobile'])): ?>
            <form method="POST" class="space-y-6">
                <div class="relative">
                    <input type="text" id="mobile" name="mobile" required pattern="09[0-9]{9}" 
                           placeholder="شماره موبایل" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                    <span class="absolute left-3 top-3 text-blue-400">
                        <svg class="w-6 h-6 animate__animated animate__pulse animate__infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg hover:from-blue-600 hover:to-pink-600 neon-button transition-all duration-300 animate__animated animate__vibrate animate__infinite animate__slow">
                    ارسال کد تأیید
                </button>
            </form>
        <?php else: ?>
            <form method="POST" class="space-y-6">
                <div class="relative">
                    <input type="text" id="otp" name="otp" required pattern="[0-9]{6}" 
                           placeholder="کد ۶ رقمی" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                    <span class="absolute left-3 top-3 text-blue-400">
                        <svg class="w-6 h-6 animate__animated animate__pulse animate__infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </span>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg hover:from-blue-600 hover:to-pink-600 neon-button transition-all duration-300 animate__animated animate__vibrate animate__infinite animate__slow">
                    تأیید
                </button>
            </form>
            <form method="POST" class="mt-4">
                <button type="submit" name="reset_mobile" 
                        class="w-full bg-gradient-to-r from-gray-500 to-gray-700 text-white p-3 rounded-lg hover:from-gray-600 hover:to-gray-800 neon-button transition-all duration-300">
                    اصلاح شماره موبایل
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        particlesJS("particles-js", {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: ["#00f0ff", "#ff00cc"] },
                shape: { type: "circle" },
                opacity: { value: 0.5, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: "#00f0ff", opacity: 0.4, width: 1 },
                move: { enable: true, speed: 3, direction: "none", random: true, out_mode: "out" }
            },
            interactivity: {
                detect_on: "canvas",
                events: { onhover: { enable: true, mode: "repulse" }, onclick: { enable: true, mode: "push" }, resize: true },
                modes: { repulse: { distance: 100, duration: 0.4 }, push: { particles_nb: 4 } }
            },
            retina_detect: true
        });
    </script>
</body>
</html>