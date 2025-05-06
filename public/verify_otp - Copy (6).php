<?php
include 'header.php';

if (!isset($_SESSION['mobile'])) {
    $_SESSION['error'] = "لطفاً ابتدا کد OTP را درخواست کنید.";
    header("Location: login.php");
    exit;
}

$mobile = $_SESSION['mobile'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp']);
    $otp = preg_replace('/\s+/', '', $otp);

    // اعتبارسنجی کد OTP
    $stmt = $pdo->prepare("SELECT * FROM otps WHERE mobile = ? AND otp = ? AND expires_at > NOW()");
    $stmt->execute([$mobile, $otp]);
    $otp_record = $stmt->fetch();

    // لاگ کردن
    $log_message = "Verify OTP - Mobile: $mobile, Entered OTP: $otp, Found: " . ($otp_record ? 'Yes' : 'No');
    if (!$otp_record) {
        $stmt = $pdo->prepare("SELECT otp, expires_at FROM otps WHERE mobile = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$mobile]);
        $last_otp = $stmt->fetch();
        $log_message .= $last_otp ? ", Stored OTP: {$last_otp['otp']}, Expires: {$last_otp['expires_at']}" : ", No OTP found";
    }
    file_put_contents('otp_log.txt', $log_message . "\n", FILE_APPEND);

    if ($otp_record) {
        // پیدا کردن یا ایجاد کاربر
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (mobile, balance, role, created_at) VALUES (?, 0, 'school_admin', NOW())");
            $stmt->execute([$mobile]);
            $user_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO schools (name, type, created_at) VALUES (?, ?, NOW())");
            $stmt->execute(['مدرسه پیش‌فرض', 'دبستان']);
            $school_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE users SET school_id = ? WHERE id = ?");
            $stmt->execute([$school_id, $user_id]);
        } else {
            $user_id = $user['id'];
            $school_id = $user['school_id'];
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['school_id'] = $school_id;
        $_SESSION['role'] = $user['role'];

        $stmt = $pdo->prepare("DELETE FROM otps WHERE mobile = ?");
        $stmt->execute([$mobile]);

        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "کد OTP اشتباه است یا منقضی شده است.";
        header("Location: verify_otp.php");
        exit;
    }
}
?>

<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-lg">
    <h2 class="text-2xl font-bold mb-6 text-center">تأیید کد OTP</h2>
    <form action="verify_otp.php" method="POST">
        <div class="mb-4">
            <label class="block text-gray-700 font-medium mb-2">کد OTP</label>
            <input type="text" name="otp" class="w-full p-2 border rounded" required pattern="[0-9]{6}" placeholder="مثال: 123456">
        </div>
        <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">تأیید کد</button>
    </form>
</div>
</div>
</body>
</html>