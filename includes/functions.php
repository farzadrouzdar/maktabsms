<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

function sendSMS($mobile, $message) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_append_cancel'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $auto_append_cancel = $result['setting_value'] == '1';
    $stmt->close();
    
    $original_message = $message;
    if ($auto_append_cancel) {
        $message .= ' لغو11';
    }

    $stmt = $conn->prepare("INSERT INTO sms_logs (school_id, recipient, message, status, sent_at, created_at) VALUES (?, ?, ?, 'sent', NOW(), NOW())");
    $school_id = 1;
    $status = 'sent';
    $stmt->bind_param("iss", $school_id, $mobile, $message);
    $stmt->execute();
    $stmt->close();

    $api_key = "sa2888726250:ocVL0dpy1VAYj1KF203EEDTP2E73QG6tuJCt";
    $gateway = "50003000201";
    if ($api_key !== "YOUR_API_KEY" && $gateway !== "YOUR_GATEWAY") {
        try {
            $api = new \SabaNovin\SabaNovinApi($api_key);
            $to = [$mobile];
            $result = $api->Send($gateway, $to, $message);

            $reference_id = '';
            if ($result->entries) {
                $entry = $result->entries[0];
                $reference_id = $entry->reference_id;
                $status = $entry->status;
            }

            $stmt = $conn->prepare("UPDATE sms_logs SET reference_id = ?, status = ? WHERE recipient = ? AND message = ? AND created_at = (SELECT MAX(created_at) FROM sms_logs WHERE recipient = ?)");
            $stmt->bind_param("sssss", $reference_id, $status, $mobile, $message, $mobile);
            $stmt->execute();
            $stmt->close();
        } catch (\SabaNovin\Exceptions\ApiException $e) {
            error_log("خطای صبانوین: " . $e->errorMessage());
            $status = 'failed';
            if ($auto_append_cancel) {
                try {
                    $result = $api->Send($gateway, $to, $original_message);
                    $reference_id = $result->entries[0]->reference_id;
                    $status = $result->entries[0]->status;
                    $stmt = $conn->prepare("UPDATE sms_logs SET reference_id = ?, status = ?, message = ? WHERE recipient = ? AND message = ? AND created_at = (SELECT MAX(created_at) FROM sms_logs WHERE recipient = ?)");
                    $stmt->bind_param("ssssss", $reference_id, $status, $original_message, $mobile, $message, $mobile);
                    $stmt->execute();
                    $stmt->close();
                } catch (\SabaNovin\Exceptions\ApiException $e2) {
                    error_log("خطای صبانوین بدون لغو11: " . $e2->errorMessage());
                    $status = 'failed';
                }
            }
        }
    }

    $conn->close();
    return $status === 'sent';
}

function generateOTP($mobile) {
    $conn = db_connect();
    $otp_code = sprintf("%06d", rand(100000, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+60 minutes'));

    try {
        $stmt = $conn->prepare("DELETE FROM otps WHERE mobile = ?");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO otps (mobile, otp_code, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
        $stmt->bind_param("sss", $mobile, $otp_code, $expires_at);
        $stmt->execute();
        $stmt->close();

        $message = "کد تأیید شما: $otp_code";
        sendSMS($mobile, $message);

        $conn->close();
        return $otp_code;
    } catch (Exception $e) {
        $conn->close();
        throw new Exception("خطا در تولید OTP: " . $e->getMessage());
    }
}

function validateOTP($mobile, $otp_code) {
    $conn = db_connect();
    $otp_code = trim($otp_code);
    
    // کوئری ساده برای تست
    $stmt = $conn->prepare("SELECT otp_code, expires_at FROM otps WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    $is_valid = $row && $row['otp_code'] === $otp_code;
    error_log("validateOTP: mobile=$mobile, input_otp=$otp_code, db_otp=" . ($row ? $row['otp_code'] : 'none') . ", expires_at=" . ($row ? $row['expires_at'] : 'none') . ", valid=$is_valid");

    return $is_valid;
}

function getBalance($school_id) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT SUM(amount) as balance FROM transactions WHERE school_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result['balance'] ?? 0;
}

function getContactsCount($school_id) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result['count'];
}

function getSMSCount($school_id) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sms_logs WHERE school_id = ? AND status = 'sent'");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $result['count'];
}
?>