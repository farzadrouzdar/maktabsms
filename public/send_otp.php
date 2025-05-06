<?php
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mobile = trim($_POST['mobile']);

    // اعتبارسنجی شماره موبایل
    if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $_SESSION['error'] = "شماره موبایل نامعتبر است.";
        header("Location: login.php");
        exit;
    }

    // تولید کد OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // ذخیره کد OTP
    try {
        $stmt = $pdo->prepare("INSERT INTO otps (mobile, otp, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$mobile, $otp, $expires_at]);
        file_put_contents('otp_log.txt', "Send OTP - Mobile: $mobile, OTP: $otp, Expires: $expires_at\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents('otp_log.txt', "Send OTP Error - Mobile: $mobile, Error: " . $e->getMessage() . "\n", FILE_APPEND);
        $_SESSION['error'] = "خطا در ذخیره کد OTP.";
        header("Location: login.php");
        exit;
    }

    // ارسال پیامک OTP
    $message = "کد ورود شما: $otp\nلغو11";
    $url = "https://api.sabanovin.com/v1/{$sabanovin_api_key}/sms/send.json";
    $data = [
        'gateway' => $sabanovin_gateway,
        'to' => $mobile,
        'text' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    file_put_contents('sabanovin_log.txt', "Send OTP - Mobile: $mobile, HTTP Code: $http_code, Response: $response, Error: $curl_error\n", FILE_APPEND);

    $result = json_decode($response, true);
    if ($http_code == 200 && isset($result['status']['code']) && $result['status']['code'] == 200) {
        $_SESSION['mobile'] = $mobile;
        header("Location: verify_otp.php");
        exit;
    } else {
        $error_message = isset($result['status']['message']) ? $result['status']['message'] : 'خطا در ارسال پیامک';
        file_put_contents('otp_log.txt', "Send OTP SMS Error - Mobile: $mobile, Error: $error_message\n", FILE_APPEND);
        $_SESSION['error'] = $error_message;
        header("Location: login.php");
        exit;
    }
}
?>