<?php
require_once 'includes/header.php';
$conn = db_connect();
$school_id = $_SESSION['school_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sms_api = new SabaNovinSMS();
    $response = $sms_api->receiveSMS();
    if ($response['status']['code'] === 200) {
        foreach ($response['entries'] as $sms) {
            $message = $sms['message'];
            $sender_number = $sms['sender_number'];
            $reference_id = $sms['reference_id'];
            $datetime = $sms['datetime'];

            // یافتن مدرسه بر اساس کد انحصاری در پیام
            $school = $conn->query("SELECT id FROM schools WHERE unique_code = SUBSTRING_INDEX('$message', ' ', 1)")->fetch_assoc();
            if ($school) {
                $stmt = $conn->prepare("INSERT INTO received_sms (school_id, reference_id, sender_number, message, datetime) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $school['id'], $reference_id, $sender_number, $message, $datetime);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
?>

<h1>دریافت پیامک</h1>
<form method="POST">
    <button type="submit">دریافت پیامک‌های جدید</button>
</form>
<table>
    <tr>
        <th>فرستنده</th>
        <th>متن</th>
        <th>زمان</th>
    </tr>
    <?php
    $result = $conn->query("SELECT * FROM received_sms WHERE school_id = $school_id");
    while ($sms = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $sms['sender_number'] ?></td>
            <td><?= $sms['message'] ?></td>
            <td><?= $sms['datetime'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<?php $conn->close(); require_once 'includes/footer.php'; ?>