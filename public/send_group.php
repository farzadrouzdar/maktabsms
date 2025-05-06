<?php
require_once 'includes/header.php';
$conn = db_connect();
$school_id = $_SESSION['school_id'];
$settings = getSettings();
$header = getSchoolHeader($school_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = $_POST['group_id'];
    $text = $_POST['text'];
    $draft_id = $_POST['draft_id'] ?: null;
    $send_time = $_POST['send_time'] ?: date('Y-m-d H:i:s');
    $cost = calculateSMSCost($text, $settings, $school_id);
    $status = $draft_id ? 'approved' : 'pending';

    $contacts = $conn->query("SELECT mobile FROM contacts WHERE school_id = $school_id AND group_id = $group_id");
    $mobiles = [];
    while ($contact = $contacts->fetch_assoc()) {
        $mobiles[] = $contact['mobile'];
    }

    $stmt = $conn->prepare("INSERT INTO sent_sms (school_id, type, contact_group_id, text, status, send_time, cost) VALUES (?, 'group', ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $school_id, $group_id, $text, $status, $send_time, $cost);
    $stmt->execute();
    $sms_id = $conn->insert_id;

    if ($status === 'approved' && $send_time <= date('Y-m-d H:i:s')) {
        $sms_api = new SabaNovinSMS();
        $response = $sms_api->sendGroup($mobiles, $text, $header, $settings['sms_footer']);
        if ($response['status']['code'] === 200) {
            $conn->query("UPDATE sent_sms SET status = 'sent', reference_id = '{$response['entries'][0]['reference_id']}' WHERE id = $sms_id");
        } else {
            $conn->query("UPDATE sent_sms SET status = 'failed' WHERE id = $sms_id");
        }
    }
    $stmt->close();
    header("Location: send_group.php");
    exit;
}
?>

<h1>ارسال پیامک گروهی</h1>
<form method="POST">
    <label>گروه:</label>
    <select name="group_id" required>
        <?php
        $groups = $conn->query("SELECT * FROM contact_groups WHERE school_id = $school_id");
        while ($group = $groups->fetch_assoc()): ?>
            <option value="<?= $group['id'] ?>"><?= $group['name'] ?></option>
        <?php endwhile; ?>
    </select><br>
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
        <th>گروه</th>
        <th>متن</th>
        <th>وضعیت</th>
        <th>هزینه</th>
        <th>زمان</th>
    </tr>
    <?php
    $result = $conn->query("SELECT s.*, g.name as group_name FROM sent_sms s LEFT JOIN contact_groups g ON s.contact_group_id = g.id WHERE s.school_id = $school_id AND s.type = 'group'");
    while ($sms = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $sms['group_name'] ?></td>
            <td><?= substr($sms['text'], 0, 20) . '...' ?></td>
            <td><?= $sms['status'] ?></td>
            <td><?= $sms['cost'] ?></td>
            <td><?= $sms['send_time'] ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<?php $conn->close(); require_once 'includes/footer.php'; ?>