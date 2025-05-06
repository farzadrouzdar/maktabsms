<?php
class SabaNovinSMS {
    private $api_key = 'sa2888726250:ocVL0dpy1VAYj1KF203EEDTP2E73QG6tuJCt';
    private $gateway = '50003000201';
    private $base_url = 'https://api.sabanovin.com/v1/';

    public function sendSingle($to, $text, $school_name, $footer) {
        $text = $school_name . "\n" . $text . "\n" . $footer;
        $url = $this->base_url . $this->api_key . '/sms/send.json';
        $data = [
            'gateway' => $this->gateway,
            'to' => [$to],
            'text' => $text
        ];
        return $this->sendRequest($url, $data);
    }

    public function sendGroup($to, $text, $school_name, $footer) {
        $text = $school_name . "\n" . $text . "\n" . $footer;
        $url = $this->base_url . $this->api_key . '/sms/send.json';
        $data = [
            'gateway' => $this->gateway,
            'to' => $to,
            'text' => $text
        ];
        return $this->sendRequest($url, $data);
    }

    public function sendSmart($to, $texts, $school_name, $footer) {
        $url = $this->base_url . $this->api_key . '/sms/send-array.json';
        $messages = [];
        foreach ($to as $index => $number) {
            $text = $school_name . "\n" . $texts[$index] . "\n" . $footer;
            $messages[] = [
                'to' => $number,
                'text' => $text
            ];
        }
        $data = [
            'gateway' => $this->gateway,
            'messages' => $messages
        ];
        return $this->sendRequest($url, $data);
    }

    public function receiveSMS() {
        $url = $this->base_url . $this->api_key . '/sms/receive.json?gateway=' . $this->gateway . '&is_read=0';
        return $this->sendRequest($url, [], 'GET');
    }

    private function sendRequest($url, $data = [], $method = 'POST') {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}
?>