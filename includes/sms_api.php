    <?php
class SabaNovinSMS {
    private $api_key = 'sa2888726250:ocVL0dpy1VAYj1KF203EEDTP2E73QG6tuJCt'; // کلید API صبانوین را اینجا وارد کنید
    private $gateway = '50003000201'; // شماره فرستنده (gateway) که توی تست مرورگر کار کرد
    private $api_url = 'https://api.sabanovin.com/v1/';

    private function normalizePhoneNumber($number) {
        $number = preg_replace('/\D/', '', $number); // حذف همه غیرعدد
        if (strlen($number) === 10 && substr($number, 0, 1) === '9') {
            $number = '0' . $number; // 9356194949 -> 09356194949
        } elseif (substr($number, 0, 2) === '98') {
            $number = '0' . substr($number, 2); // 989356194949 -> 09356194949
        } elseif (substr($number, 0, 3) === '+98') {
            $number = '0' . substr($number, 3); // +989356194949 -> 09356194949
        }
        return preg_match('/^09[0-9]{9}$/', $number) ? $number : false;
    }

    public function sendSingle($mobile, $text, $school_name, $footer) {
        // نرمال‌سازی شماره موبایل
        $mobile = $this->normalizePhoneNumber($mobile);
        if (!$mobile) {
            $error = "شماره موبایل نامعتبر: $mobile";
            file_put_contents('sms_error_log.txt', date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
            return ['status' => 'error', 'message' => $error];
        }

        // فرمت‌بندی پیامک
        $full_text = "مکتب اس ام اس\n" . $text . "\n" . $footer . "\nلغو11";
        $encoded_text = urlencode($full_text);

        // ساخت URL مشابه تست مرورگر
        $url = $this->api_url . $this->api_key . '/sms/send.json?gateway=' . $this->gateway . '&to=' . $mobile . '&text=' . $encoded_text;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // موقتاً برای تست
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // لاگ پاسخ
        $log_message = date('Y-m-d H:i:s') . " - Mobile: $mobile, Text: $full_text, HTTP Code: $http_code, Response: $response, Curl Error: $curl_error\n";
        file_put_contents('sms_log.txt', $log_message, FILE_APPEND);

        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']['code']) && $result['status']['code'] === 200) {
                // ثبت لاگ موفق
                $conn = db_connect();
                $stmt = $conn->prepare("INSERT INTO sms_logs (recipient, message, batch_id, reference_id, status, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$mobile, $full_text, $result['batch_id'] ?? null, $result['entries'][0]['reference_id'] ?? null, 'ENQUEUED']);
                $conn->close();
                return $result;
            } else {
                $error = "خطا از API: " . ($result['status']['message'] ?? 'پاسخ نامعتبر');
                file_put_contents('sms_error_log.txt', date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
                return ['status' => 'error', 'message' => $error];
            }
        } else {
            $error = "خطا در ارتباط با سرور صبانوین. کد HTTP: $http_code. خطا: $curl_error";
            file_put_contents('sms_error_log.txt', date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
            return ['status' => 'error', 'message' => $error];
        }
    }
}
?>