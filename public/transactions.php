<?php
require_once 'includes/header.php';
$conn = db_connect();
$school_id = $_SESSION['school_id'];
?>

<h1>گزارش پرداخت‌ها</h1>
<table>
    <tr>
        <th>مبلغ</th>
        <th>وضعیت</th>
        <th>زمان</th>
        <th>رسید</th>
    </tr>
    <?php
    $result = $conn->query("SELECT * FROM transactions WHERE school_id = $school_id");
    while ($transaction = $result->fetch_assoc()): ?>
        <tr>
            <td><?= number_format($transaction['amount']) ?> تومان</td>
            <td><?= $transaction['status'] ?></td>
            <td><?= $transaction['created_at'] ?></td>
            <td>
                <?php if ($transaction['status'] === 'successful' && $transaction['receipt_url']): ?>
                    <a href="https://www.zarinpal.com/pg/ShowPayment/<?= $transaction['receipt_url'] ?>" target="_blank">مشاهده رسید</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
</table>

<?php $conn->close(); require_once 'includes/footer.php'; ?>