<?php
require_once 'includes/header.php';
$conn = db_connect();
$school_id = $_SESSION['school_id'];
$settings = getSettings();
$header = getSchoolHeader($school_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'];
    $text = $_POST['text'];
    $draft_id = $_POST['draft_id'] ?: null;
    $send_time = $_POST['send_time'] ?: date('Y-m-d H:i:s');
    $cost = calculateSMSCost($text, $settings, $school_id);
    $status = $draft_id ? 'approved' : 'pending';

    $stmt = $conn->prepare("INSERT INTO sent_sms (school_id, type, recipient_mobile, text, status, send_time, cost) VALUES (?, 'single', ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssd", $school_id, $mobile, $text, $status, $send_time, $cost);
    $stmt->execute();
    $sms_id = $conn->insert_id;

    if ($status === 'approved' && $send_time <= date('Y-m-d H:i:s')) {
        $sms_api = new SabaNovinSMS();
        $response = $sms_api->sendSingle($mobile, $text, $header, $settings['sms_footer']);
        if ($response['status']['code'] === 200) {
            $conn->query("UPDATE sent_sms SET status = 'sent', reference_id = '{$response['entries'][0]['reference_id']}' WHERE id = $sms_id");
        } else {
            $conn->query("UPDATE sent_sms SET status = 'failed' WHERE id = $sms_id");
        }
    }
    $stmt->close();
    header("Location: send_single.php");
    exit;
}
?>

<h1>ارسال پیامک تکی</h1>
<form method="POST">
    <label>شماره موبایل:</label>
    <input type="text" name="mobile" required><br>
    <label>پیش‌نویس:</label>
    <select name="draft_id" onchange="if(this.value) document.getElementById('text').value = this.options[this.selectedIndex].dataset.text;">
        <option value="">انتخاب کنید</option>
        <?php
        $drafts = $conn->query("SELECT * FROM drafts WHERE school_id = $school_id AND type = 'simple' AND status = 'approved'");
        while ($draft = $drafts->fetch_assoc()): ?>
            <option value="<?= $draft['id'] ?>" data-text="<?= htmlspecialchars($draft['text']) ?>"><?= substr($draft['text'], 0, 20) . '...' ?></option>
        <?php endwhile; ?>
    </select><br>
    <label>متن پیامک:</label>
    <textarea id="text" name="text" oninput="previewSMS(this, document.getElementById('preview'), '<?= $header ?>', '<?= $settings['sms_footer'] ?>'); calculateChars(this, document.getElementById('char_count'), '<?= $header ?>', '<?= $settings['sms_footer'] ?>')" required></textarea><br>
    <span id="char_count"></span><br>
    <div id="preview" class="preview"></div>
    <label>زمان ارسال:</label>
    <input type="datetime-local" name="send_time"><br>
    <button type="submit">ارسال</button>
</form>

<h3>گزارش پیامک‌های ارسالی</h3>
<table>
    <tr>
        <th>گیرنده</th>
        <th>متن</th>
        <th>وضعیت</th>
        <th>هزینه</th>
        <th>زمان</th>
    </tr>
    <?php
    $result = $conn->query("SELECT * FROM sent_sms WHERE school_id = $school_id AND type = 'single'");
    while ($sms = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $sms['recipient_mobile'] ?></td>
            <td><?= substr($sms['text'], 0, 20) . '...' ?></td>
            <td><?= $sms['status'] ?></td>
            <td><?= $sms['cost'] ?></td>
            <td><?= $sms['send_time'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<?php $conn->close(); require_once 'includes/footer.php'; ?>