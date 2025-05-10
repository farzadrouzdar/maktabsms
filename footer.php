<?php
require_once 'config.php';
require_once 'menus.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}
$school_id = $user['school_id'];

file_put_contents('debug.log', "School ID: $school_id\n", FILE_APPEND);

$stmt = $pdo->prepare("SELECT school_type, gender_type, school_name FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
$school_name = $school ? trim($school['school_type'] . ' ' . $school['gender_type'] . ' ' . $school['school_name']) : 'مدرسه نامشخص';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

if (strtotime($end_date) > strtotime(date('Y-m-d'))) {
    $end_date = date('Y-m-d');
}

$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

$query = "SELECT * FROM transactions WHERE school_id = ? AND created_at BETWEEN ? AND ?";
$params = [$school_id, $start_datetime, $end_datetime];
if ($type !== 'all') {
    $query .= " AND type = ?";
    $params[] = $type;
}
$query .= " ORDER BY created_at DESC";

file_put_contents('debug.log', "Query: $query\nParams: " . json_encode($params) . "\n", FILE_APPEND);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

file_put_contents('debug.log', "Number of transactions fetched: " . count($transactions) . "\n", FILE_APPEND);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE school_id = ?");
$stmt->execute([$school_id]);
$total_transactions = $stmt->fetch()['total'];
file_put_contents('debug.log', "Total transactions for school_id $school_id: $total_transactions\n", FILE_APPEND);

function getTransactionDescription($transaction) {
    $details = $transaction['details'];
    if ($transaction['type'] === 'credit') {
        if ($transaction['status'] === 'successful') {
            return "شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان (شماره تراکنش: " . ($transaction['payment_id'] ?? 'ندارد') . ")";
        } elseif ($transaction['status'] === 'failed') {
            return "تلاش برای شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان ناموفق بود";
        } else {
            return "شارژ آنلاین به مبلغ " . number_format($transaction['amount']) . " تومان لغو شد";
        }
    } elseif ($transaction['type'] === 'debit') {
        if ($transaction['status'] === 'successful') {
            $count = 1;
            $batchId = $transaction['payment_id'] ?? 'ندارد';
            if (is_string($details) && (strpos($details, '{') === 0 || strpos($details, '[') === 0)) {
                $decodedDetails = json_decode($details, true);
                $count = isset($decodedDetails['count']) ? $decodedDetails['count'] : 1;
                $batchId = isset($decodedDetails['batch_id']) ? $decodedDetails['batch_id'] : $batchId;
            } elseif (is_string($details)) {
                return $details . " با هزینه " . number_format(abs($transaction['amount'])) . " تومان";
            }
            if ($count > 1) {
                return "ارسال گروهی موفق به $count مخاطب با هزینه " . number_format(abs($transaction['amount'])) . " تومان (Batch ID: $batchId)";
            } else {
                return "ارسال پیامک موفق با هزینه " . number_format(abs($transaction['amount'])) . " تومان (Batch ID: $batchId)";
            }
        } else {
            return "تلاش برای ارسال پیامک با هزینه " . number_format(abs($transaction['amount'])) . " تومان ناموفق بود";
        }
    }
    return "تراکنش نامشخص با مبلغ " . number_format($transaction['amount']) . " تومان";
}

$page_title = "تراکنش‌ها - سامانه پیامک مدارس";
require_once 'header.php';
?>

<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item"><a class="nav-link" href="logout.php">خروج</a></li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="dashboard.php" class="brand-link">
            <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
        </a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="info">
                    <a href="#" class="d-block"><?php echo htmlspecialchars($user['name']); ?></a>
                </div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <?php foreach ($menus as $key => $menu): ?>
                        <li class="nav-item">
                            <a href="<?php echo $menu['url']; ?>" class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == basename($menu['url'])) echo 'active'; ?>">
                                <i class="nav-icon <?php echo $menu['icon']; ?>"></i>
                                <p><?php echo $menu['title']; ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid"><h1>تراکنش‌ها</h1></div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">فیلتر تراکنش‌ها</h3></div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-3">
                                <label for="start_date">از تاریخ</label>
                                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">تا تاریخ</label>
                                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="type">نوع تراکنش</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>همه</option>
                                    <option value="credit" <?php echo $type === 'credit' ? 'selected' : ''; ?>>شارژ</option>
                                    <option value="debit" <?php echo $type === 'debit' ? 'selected' : ''; ?>>ارسال پیامک</option>
                                </select>
                            </div>
                            <div class="col-md-3 align-self-end">
                                <button type="submit" class="btn btn-primary btn-block">فیلتر</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (count($transactions) === 0): ?>
                    <div class="alert alert-info">هیچ تراکنشی در بازه انتخابی یافت نشد.</div>
                <?php else: ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>مبلغ</th>
                                <th>نوع</th>
                                <th>وضعیت</th>
                                <th>توضیحات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($txn['created_at']); ?></td>
                                    <td><?php echo number_format($txn['amount']); ?> تومان</td>
                                    <td><?php echo $txn['type'] === 'credit' ? 'شارژ' : 'ارسال پیامک'; ?></td>
                                    <td><?php echo $txn['status'] === 'successful' ? 'موفق' : ($txn['status'] === 'failed' ? 'ناموفق' : 'لغو شده'); ?></td>
                                    <td><?php echo getTransactionDescription($txn); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'footer.php'; ?>
