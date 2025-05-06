<?php
require_once 'includes/header.php';
checkSuperAdmin();
$conn = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['sms_part1_chars', 'sms_part2_chars', 'sms_cost', 'sms_footer', 'min_charge_amount'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE key = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: settings.php");
    exit;
}

$settings = getSettings();
?>

<h1>تنظیمات</h1>
<form method="POST">
    <label>تعداد کاراکتر پارت اول:</label>
    <input type="number" name="sms_part1_chars" value="<?= $settings['sms_part1_chars'] ?>" required><br>
    <label>تعداد کاراکتر پارت‌های بعدی:</label>
    <input type="number" name="sms_part2_chars" value="<?= $settings['sms_part2_chars'] ?>" required><br>
    <label>تعرفه پیامک (تومان):</label>
    <input type="number" name="sms_cost" value="<?= $settings['sms_cost'] ?>" required><br>
    <label>فوتر پیامک:</label>
    <input type="text" name="sms_footer" value="<?= $settings['sms_footer'] ?>" required><br>
    <label>حداقل مبلغ شارژ (تومان):</label>
    <input type="number" name="min_charge_amount" value="<?= $settings['min_charge_amount'] ?>" required><br>
    <button type="submit">ذخیره</button>
</form>

<?php $conn->close(); require_once 'includes/footer.php'; ?>