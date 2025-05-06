<?php
require_once 'includes/header.php';
$conn = db_connect();
$school_id = $_SESSION['school_id'];
$settings = getSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (int)$_POST['amount'];
    if ($amount >= $settings['min_charge_amount']) {
        // اتصال به زرین‌پال (نمونه)
        $merchant_id = 'YOUR-MERCHANT-ID';
        $data = [
            'merchant_id' => $merchant_id,
            'amount' => $amount,
            'description' => 'شارژ حساب maktabsms',
            'callback_url' => 'http://yourdomain.com/charge.php?verify=1'
        ];
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['data']['code'] === 100) {
            $stmt = $conn->prepare("INSERT INTO transactions (school_id, amount, status, transaction_id) VALUES (?, ?, 'pending', ?)");
            $stmt->bind_param("ids", $school_id, $amount, $response['data']['authority']);
            $stmt->execute();
            $stmt->close();
            header("Location: https://www.zarinpal.com/pg/StartPay/{$response['data']['authority']}");
            exit;
        }
    }
}

if (isset($_GET['verify'])) {
    $authority = $_GET['Authority'];
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND school_id = ?");
    $stmt->bind_param("si", $authority, $school_id);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    if ($transaction) {
        $data = [
            'merchant_id' => 'YOUR-MERCHANT-ID',
            'authority' => $authority,
            'amount' => $transaction['amount']
        ];
        $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['data']['code'] === 100) {
            $conn->query("UPDATE transactions SET status = 'successful', receipt_url = '{$response['data']['ref_id']}' WHERE transaction_id = '$authority'");
        } else {
            $conn->query("UPDATE transactions SET status = 'failed' WHERE transaction_id = '$authority'");
        }
    }
    $stmt->close();
    header("Location: charge.php");
    exit;
}
?>

<h1>شارژ حساب</h1>
<form method="POST">
    <label>مبلغ (حداقل <?= $settings['min_charge_amount'] ?> تومان):</label>
    <input type="number" name="amount" required><br>
    <button type="submit">پرداخت</button>
</form>

<?php $conn->close(); require_once 'includes/footer.php'; ?>