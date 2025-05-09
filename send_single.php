<?php
require_once 'config.php';
require_once 'menus.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$school_id = $user['school_id'];

// Ensure school_id is stored in session
$_SESSION['school_id'] = $school_id;

// Get school details and construct school name from school_type, gender_type, and school_name
$stmt = $pdo->prepare("SELECT school_type, gender_type, school_name FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
if ($school) {
    $school_name = trim($school['school_type'] . ' ' . $school['gender_type'] . ' ' . $school['school_name']);
} else {
    $school_name = 'مدرسه نامشخص';
}

// Function to get current balance
function get_balance($pdo, $school_id) {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) -
            SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as balance
        FROM transactions
        WHERE school_id = ? AND status = 'successful'
    ");
    $stmt->execute([$school_id]);
    return $stmt->fetch()['balance'] ?? 0;
}

// Function to calculate SMS cost with header and footer
function calculate_sms_cost($message, $school_name) {
    $full_message = $school_name . "\n" . $message . "\n" . SMS_FOOTER_TEXT;
    $message_length = mb_strlen($full_message, 'UTF-8');
    $sms_parts = 1;
    if ($message_length > SMS_MAX_CHARS_PART1) {
        $remaining_chars = $message_length - SMS_MAX_CHARS_PART1;
        $sms_parts += ceil($remaining_chars / SMS_MAX_CHARS_OTHER);
    }
    return $sms_parts * SMS_COST_PER_PART;
}

// Get initial balance
$balance = get_balance($pdo, $school_id);

// Pagination settings
$records_per_page = 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter settings
$search = isset($_GET['search']) ? filter_var($_GET['search'], FILTER_SANITIZE_STRING) : '';
$status_filter = isset($_GET['status']) ? filter_var($_GET['status'], FILTER_SANITIZE_STRING) : '';
$where_conditions = ["school_id = :school_id"];
$params = [':school_id' => (int)$school_id];

if (!empty($search)) {
    $where_conditions[] = "(mobile LIKE :search OR message LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($status_filter) && in_array($status_filter, ['ready_to_send', 'sent', 'failed'])) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}
$where_clause = implode(' AND ', $where_conditions);

// Fetch total records for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM single_sms WHERE $where_clause");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch SMS records for current page
$stmt = $pdo->prepare("
    SELECT * FROM single_sms
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$sms_records = $stmt->fetchAll();

// Handle form submission for new SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_message'])) {
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $message = $school_name . "\n" . filter_var($_POST['message'], FILTER_SANITIZE_STRING) . "\n" . SMS_FOOTER_TEXT;
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($mobile) && !empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO single_sms (user_id, school_id, mobile, message, draft_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'ready_to_send', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $school_id, $mobile, $message, $draft_id ?: null]);
        echo json_encode(['success' => true, 'message' => 'پیامک با موفقیت ذخیره شد.']);
        exit;
    } else {
        echo json_encode(['error' => 'شماره موبایل و متن پیام نمی‌توانند خالی باشند.']);
        exit;
    }
}

// Handle send SMS action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_sms'])) {
    $sms_id = filter_var($_POST['sms_id'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM single_sms WHERE id = ? AND school_id = ?");
    $stmt->execute([$sms_id, $school_id]);
    $sms = $stmt->fetch();

    if ($sms) {
        $mobile = $sms['mobile'];
        $message = $sms['message'];
        $sms_cost = calculate_sms_cost($message, $school_name);
        $current_balance = get_balance($pdo, $school_id);

        if ($current_balance >= $sms_cost) {
            $url = SABANOVIN_BASE_URL . '/sms/send.json?gateway=' . urlencode(SABANOVIN_GATEWAY) . '&to=' . urlencode($mobile) . '&text=' . urlencode($message);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200) {
                $response_data = json_decode($response, true);
                if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (school_id, amount, status, created_at, details, type)
                        VALUES (?, ?, 'successful', NOW(), ?, 'debit')
                    ");
                    $stmt->execute([$school_id, $sms_cost, "ارسال پیامک تکی به شماره $mobile (Batch ID: {$response_data['batch_id']})"]);

                    $stmt = $pdo->prepare("
                        UPDATE single_sms
                        SET status = 'sent', batch_id = ?, sent_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$response_data['batch_id'], $sms_id]);

                    echo json_encode(['success' => true, 'message' => "پیامک با موفقیت ارسال شد (Batch ID: {$response_data['batch_id']}).", 'balance' => get_balance($pdo, $school_id)]);
                } else {
                    $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                    $stmt = $pdo->prepare("UPDATE single_sms SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$error, $sms_id]);
                    echo json_encode(['error' => $error]);
                }
            } else {
                $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                $stmt = $pdo->prepare("UPDATE single_sms SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$error, $sms_id]);
                echo json_encode(['error' => $error]);
            }
        } else {
            $error = "مانده شارژ کافی نیست. هزینه این پیامک: " . number_format($sms_cost) . " تومان.";
            $stmt = $pdo->prepare("UPDATE single_sms SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$error, $sms_id]);
            echo json_encode(['error' => $error]);
        }
        exit;
    }
}

// Handle edit SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sms'])) {
    $sms_id = filter_var($_POST['sms_id'], FILTER_SANITIZE_NUMBER_INT);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $message = $school_name . "\n" . filter_var($_POST['message'], FILTER_SANITIZE_STRING) . "\n" . SMS_FOOTER_TEXT;
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($mobile) && !empty($message)) {
        $stmt = $pdo->prepare("
            UPDATE single_sms
            SET mobile = ?, message = ?, draft_id = ?, updated_at = NOW()
            WHERE id = ? AND school_id = ? AND status = 'ready_to_send'
        ");
        $stmt->execute([$mobile, $message, $draft_id ?: null, $sms_id, $school_id]);
        echo json_encode(['success' => true, 'message' => 'پیامک با موفقیت ویرایش شد.']);
        exit;
    } else {
        echo json_encode(['error' => 'شماره موبایل و متن پیام نمی‌توانند خالی باشند.']);
        exit;
    }
}

// Handle delete SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sms'])) {
    $sms_id = filter_var($_POST['sms_id'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM single_sms WHERE id = ? AND school_id = ? AND status = 'ready_to_send'");
    $stmt->execute([$sms_id, $school_id]);
    echo json_encode(['success' => true, 'message' => 'پیامک با موفقیت حذف شد.']);
    exit;
}

// Fetch approved drafts (only simple type)
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' AND type = 'simple' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();

// تنظیم عنوان صفحه
$page_title = "ارسال پیامک تکی - سامانه پیامک مدارس";

// لود فایل header.php
require_once 'header.php';
?>
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="logout.php">خروج</a>
            </li>
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
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
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
            <div class="container-fluid">
                <h1>ارسال پیامک تکی</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <p>مانده شارژ: <?php echo number_format($balance, 2); ?> تومان</p>
                <div id="message-container"></div>
                <div id="debug-container" class="error-message"></div>

                <!-- Search and Filter Form -->
                <div class="mb-3">
                    <form method="GET" class="form-inline">
                        <div class="form-group mr-2">
                            <input type="text" name="search" class="form-control" placeholder="جستجو (شماره یا متن)" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group mr-2">
                            <select name="status" class="form-control">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="ready_to_send" <?php if ($status_filter == 'ready_to_send') echo 'selected'; ?>>آماده ارسال</option>
                                <option value="sent" <?php if ($status_filter == 'sent') echo 'selected'; ?>>ارسال‌شده</option>
                                <option value="failed" <?php if ($status_filter == 'failed') echo 'selected'; ?>>ناموفق</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info"><i class="fas fa-search"></i> جستجو</button>
                    </form>
                </div>

                <!-- New SMS Button -->
                <div class="mb-3 text-right">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newSmsModal">
                        <i class="fas fa-plus"></i> ارسال پیامک جدید
                    </button>
                </div>

                <!-- SMS List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست پیامک‌ها</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped" id="sms-table">
                            <thead>
                                <tr>
                                    <th>شماره موبایل</th>
                                    <th>متن پیام</th>
                                    <th>هزینه (تومان)</th>
                                    <th>وضعیت</th>
                                    <th>زمان ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="sms-body">
                                <?php foreach ($sms_records as $sms): ?>
                                    <tr data-id="<?php echo $sms['id']; ?>">
                                        <td><?php echo htmlspecialchars($sms['mobile']); ?></td>
                                        <td class="message-preview" data-full-message="<?php echo htmlspecialchars($sms['message']); ?>">
                                            <?php echo mb_substr(htmlspecialchars($sms['message']), 0, 50) . (mb_strlen($sms['message'], 'UTF-8') > 50 ? '...' : ''); ?>
                                        </td>
                                        <td><?php echo number_format(calculate_sms_cost($sms['message'], $school_name), 2); ?></td>
                                        <td>
                                            <?php
                                            $status_text = [
                                                'ready_to_send' => 'آماده ارسال',
                                                'sent' => 'ارسال‌شده',
                                                'failed' => 'ناموفق'
                                            ];
                                            echo $status_text[$sms['status']];
                                            ?>
                                        </td>
                                        <td><?php echo $sms['created_at']; ?></td>
                                        <td>
                                            <?php if ($sms['status'] === 'ready_to_send'): ?>
                                                <form id="send-form-<?php echo $sms['id']; ?>" style="display:inline;">
                                                    <input type="hidden" name="sms_id" value="<?php echo $sms['id']; ?>">
                                                    <button type="button" class="btn btn-success btn-sm send-btn" data-id="<?php echo $sms['id']; ?>"><i class="fas fa-paper-plane"></i> ارسال</button>
                                                </form>
                                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editSmsModal<?php echo $sms['id']; ?>"><i class="fas fa-edit"></i> ویرایش</button>
                                                <form id="delete-form-<?php echo $sms['id']; ?>" style="display:inline;" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید این پیامک را حذف کنید؟');">
                                                    <input type="hidden" name="sms_id" value="<?php echo $sms['id']; ?>">
                                                    <button type="button" class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $sms['id']; ?>"><i class="fas fa-trash"></i> حذف</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#reportModal<?php echo $sms['id']; ?>"><i class="fas fa-info-circle"></i> گزارش</button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-info btn-sm preview-btn" data-toggle="modal" data-target="#fullMessageModal<?php echo $sms['id']; ?>" data-full-message="<?php echo htmlspecialchars($sms['message']); ?>"><i class="fas fa-eye"></i> پیش‌نمایش</button>
                                        </td>
                                    </tr>

                                    <!-- Full Message Modal -->
                                    <div class="modal fade full-message-modal" id="fullMessageModal<?php echo $sms['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">متن کامل پیامک</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">×</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre><?php echo htmlspecialchars($sms['message']); ?></pre>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit SMS Modal -->
                                    <div class="modal fade" id="editSmsModal<?php echo $sms['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ویرایش پیامک</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">×</span>
                                                    </button>
                                                </div>
                                                <form id="edit-form-<?php echo $sms['id']; ?>">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="sms_id" value="<?php echo $sms['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="mobile">شماره موبایل</label>
                                                            <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($sms['mobile']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="draft_id">انتخاب پیش‌نویس</label>
                                                            <select class="form-control draft-select" name="draft_id">
                                                                <option value="">-- بدون پیش‌نویس --</option>
                                                                <?php foreach ($approved_drafts as $draft): ?>
                                                                    <option value="<?php echo $draft['id']; ?>" <?php if ($sms['draft_id'] == $draft['id']) echo 'selected'; ?>>
                                                                        <?php echo htmlspecialchars($draft['title']); ?> <span class="badge badge-success">ساده</span>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="message">متن پیام</label>
                                                            <div class="message-header">
                                                                <i class="fas fa-lock lock-icon"></i>
                                                                <span class="text"><?php echo htmlspecialchars($school_name); ?></span>
                                                            </div>
                                                            <textarea class="form-control message-input" name="message" rows="5" required data-header="<?php echo htmlspecialchars($school_name); ?>" data-footer="<?php echo htmlspecialchars(SMS_FOOTER_TEXT); ?>"><?php echo str_replace($school_name . "\n", '', str_replace("\n" . SMS_FOOTER_TEXT, '', $sms['message'])); ?></textarea>
                                                            <div class="message-footer">
                                                                <i class="fas fa-lock lock-icon"></i>
                                                                <span class="text"><?php echo htmlspecialchars(SMS_FOOTER_TEXT); ?></span>
                                                            </div>
                                                            <small class="form-text text-muted">هدر و فوتر به‌صورت خودکار به متن شما اضافه می‌شوند و بخشی از متن نهایی خواهند بود.</small>
                                                            <div class="mt-2">
                                                                <span id="char_count_<?php echo $sms['id']; ?>" class="counter-animated">0</span> کاراکتر | 
                                                                <span id="sms_parts_<?php echo $sms['id']; ?>" class="counter-animated">1</span> پیامک | 
                                                                هزینه تخمینی: <span id="cost_estimate_<?php echo $sms['id']; ?>" class="counter-animated">0</span> تومان
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                                        <button type="button" class="btn btn-primary save-edit" data-id="<?php echo $sms['id']; ?>" id="save-edit-<?php echo $sms['id']; ?>">ذخیره تغییرات</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Report Modal -->
                                    <div class="modal fade" id="reportModal<?php echo $sms['id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">گزارش وضعیت پیامک</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">×</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <?php if ($sms['batch_id']): ?>
                                                        <?php
                                                        $url = SABANOVIN_BASE_URL . "/sms/status.json?batch_id=" . urlencode($sms['batch_id']);
                                                        $ch = curl_init($url);
                                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                        curl_setopt($ch, CURLOPT_HTTPGET, true);
                                                        $response = curl_exec($ch);
                                                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                                        curl_close($ch);

                                                        if ($http_code == 200) {
                                                            $report_data = json_decode($response, true);
                                                            if (isset($report_data['entries'])) {
                                                                foreach ($report_data['entries'] as $entry) {
                                                                    echo "<p>شماره: {$entry['number']}</p>";
                                                                    echo "<p>وضعیت: {$entry['status']}</p>";
                                                                    echo "<p>زمان: {$entry['datetime']}</p>";
                                                                    echo "<hr>";
                                                                }
                                                            } else {
                                                                echo "<p>خطا در دریافت گزارش: " . ($report_data['status']['message'] ?? 'پاسخ نامشخص') . "</p>";
                                                            }
                                                        } else {
                                                            echo "<p>خطا در اتصال به API صبانوین (کد HTTP: $http_code)</p>";
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <p>گزارش برای این پیامک در دسترس نیست.</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">قبلی</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">بعدی</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- New SMS Modal -->
    <div class="modal fade" id="newSmsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ارسال پیامک جدید</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form id="new-sms-form">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="mobile">شماره موبایل</label>
                            <input type="text" class="form-control" name="mobile" placeholder="مثال: 09123456789" required>
                        </div>
                        <div class="form-group">
                            <label for="draft_id">انتخاب پیش‌نویس</label>
                            <select class="form-control draft-select" name="draft_id" id="draft_id">
                                <option value="">-- بدون پیش‌نویس --</option>
                                <?php foreach ($approved_drafts as $draft): ?>
                                    <option value="<?php echo $draft['id']; ?>">
                                        <?php echo htmlspecialchars($draft['title']); ?> <span class="badge badge-success">ساده</span>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">متن پیام</label>
                            <div class="message-header">
                                <i class="fas fa-lock lock-icon"></i>
                                <span class="text"><?php echo htmlspecialchars($school_name); ?></span>
                            </div>
                            <textarea class="form-control message-input" name="message" id="message" rows="5" required data-header="<?php echo htmlspecialchars($school_name); ?>" data-footer="<?php echo htmlspecialchars(SMS_FOOTER_TEXT); ?>"></textarea>
                            <div class="message-footer">
                                <i class="fas fa-lock lock-icon"></i>
                                <span class="text"><?php echo htmlspecialchars(SMS_FOOTER_TEXT); ?></span>
                            </div>
                            <small class="form-text text-muted">هدر و فوتر به‌صورت خودکار به متن شما اضافه می‌شوند و بخشی از متن نهایی خواهند بود.</small>
                            <div class="mt-2">
                                <span id="char_count" class="counter-animated">0</span> کاراکتر | 
                                <span id="sms_parts" class="counter-animated">1</span> پیامک | 
                                هزینه تخمینی: <span id="cost_estimate" class="counter-animated">0</span> تومان
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                        <button type="button" class="btn btn-primary" id="save-new">ذخیره پیامک</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
<script>
    $(document).ready(function() {
        // Function to update character count, SMS parts, and cost with animation
        function updateMessageStats(textarea, charCountElement, smsPartsElement, costElement) {
            try {
                var userText = textarea.val() || '';
                var header = textarea.data('header') || '';
                var footer = textarea.data('footer') || '';
                var fullMessage = header + "\n" + userText + "\n" + footer;
                var char_count = fullMessage.length; // Simple length for now
                var sms_parts = 1;
                if (char_count > <?php echo SMS_MAX_CHARS_PART1; ?>) {
                    var remaining_chars = char_count - <?php echo SMS_MAX_CHARS_PART1; ?>;
                    sms_parts += Math.ceil(remaining_chars / <?php echo SMS_MAX_CHARS_OTHER; ?>);
                }
                var cost = sms_parts * <?php echo SMS_COST_PER_PART; ?>;

                charCountElement.text(char_count);
                smsPartsElement.text(sms_parts);
                costElement.text(cost.toFixed(2));

                // Add animation
                [charCountElement, smsPartsElement, costElement].forEach(function(el) {
                    el.addClass('counter-blink');
                });
            } catch (e) {
                console.error('Error in updateMessageStats:', e);
                $('#debug-container').text('خطا در شمارش: ' + e.message);
                charCountElement.text('0');
                smsPartsElement.text('1');
                costElement.text('0.00');
            }
        }

        // Handle draft selection for new SMS modal
        $('#newSmsModal').on('shown.bs.modal', function () {
            var textarea = $('#message');
            textarea.val('');

            $('#draft_id').off('change').on('change', function() {
                var draft_id = $(this).val();
                if (draft_id) {
                    $.ajax({
                        url: 'get_draft.php',
                        type: 'POST',
                        data: { draft_id: draft_id },
                        success: function(response) {
                            textarea.val(response);
                            updateMessageStats(textarea, $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching draft:', error);
                            $('#message-container').html('<div class="alert alert-danger">خطا در بارگذاری پیش‌نویس</div>');
                        }
                    });
                } else {
                    textarea.val('');
                    updateMessageStats(textarea, $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
                }
            });

            textarea.off('input').on('input', function() {
                updateMessageStats($(this), $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
            });

            updateMessageStats(textarea, $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
        });

        // Handle draft selection for edit SMS modal
        $('.draft-select').on('change', function() {
            var draft_id = $(this).val();
            var textarea = $(this).closest('.modal-body').find('.message-input');
            if (draft_id) {
                $.ajax({
                    url: 'get_draft.php',
                    type: 'POST',
                    data: { draft_id: draft_id },
                    success: function(response) {
                        textarea.val(response);
                        var id = textarea.closest('.modal').attr('id').replace('editSmsModal', '');
                        updateMessageStats(textarea, $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching draft:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در بارگذاری پیش‌نویس</div>');
                    }
                });
            } else {
                textarea.val('');
                var id = textarea.closest('.modal').attr('id').replace('editSmsModal', '');
                updateMessageStats(textarea, $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
            }
        });

        // Live stats for edit SMS modal
        $('.message-input').on('input', function() {
            var id = $(this).closest('.modal').attr('id').replace('editSmsModal', '');
            updateMessageStats($(this), $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
        });

        // Initialize stats for edit modals on open
        $('[id^="editSmsModal"]').on('shown.bs.modal', function() {
            var id = $(this).attr('id').replace('editSmsModal', '');
            var textarea = $(this).find('.message-input');
            updateMessageStats(textarea, $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
        });

        // Save new SMS
        $('#save-new').click(function() {
            var formData = $('#new-sms-form').serialize() + '&save_message=1';
            var message = $('#message').val();
            $.ajax({
                url: 'send_single.php',
                type: 'POST',
                data: formData + '&message=' + encodeURIComponent(message),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#newSmsModal').modal('hide');
                        loadSmsList();
                        $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                        setTimeout(() => $('#message-container').empty(), 3000);
                    } else {
                        $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error saving SMS:', error);
                    $('#message-container').html('<div class="alert alert-danger">خطا در ذخیره پیامک</div>');
                }
            });
        });

        // Send SMS
        $('.send-btn').click(function() {
            var id = $(this).data('id');
            $.ajax({
                url: 'send_single.php',
                type: 'POST',
                data: $('#send-form-' + id).serialize() + '&send_sms=1',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadSmsList();
                        $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                        setTimeout(() => $('#message-container').empty(), 3000);
                    } else {
                        $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error sending SMS:', error);
                    $('#message-container').html('<div class="alert alert-danger">خطا در ارسال پیامک</div>');
                }
            });
        });

        // Edit SMS
        $('.save-edit').click(function() {
            var id = $(this).data('id');
            var formData = $('#edit-form-' + id).serialize() + '&edit_sms=1';
            var message = $('#edit-form-' + id + ' .message-input').val();
            $.ajax({
                url: 'send_single.php',
                type: 'POST',
                data: formData + '&message=' + encodeURIComponent(message),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editSmsModal' + id).modal('hide');
                        loadSmsList();
                        $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                        setTimeout(() => $('#message-container').empty(), 3000);
                    } else {
                        $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error editing SMS:', error);
                    $('#message-container').html('<div class="alert alert-danger">خطا در ویرایش پیامک</div>');
                }
            });
        });

        // Delete SMS
        $('.delete-btn').click(function() {
            var id = $(this).data('id');
            if (confirm('آیا مطمئن هستید که می‌خواهید این پیامک را حذف کنید؟')) {
                $.ajax({
                    url: 'send_single.php',
                    type: 'POST',
                    data: $('#delete-form-' + id).serialize() + '&delete_sms=1',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadSmsList();
                            $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                            setTimeout(() => $('#message-container').empty(), 3000);
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error deleting SMS:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در حذف پیامک</div>');
                    }
                });
            }
        });

        // Preview Button
        $('.preview-btn').click(function() {
            var fullMessage = $(this).data('full-message');
            $('#fullMessageModal' + $(this).data('target').replace('#fullMessageModal', '') + ' .modal-body pre').text(fullMessage);
        });

        // Load SMS list
        function loadSmsList() {
            $.ajax({
                url: 'send_single.php',
                type: 'GET',
                data: {
                    page: <?php echo $page; ?>,
                    search: '<?php echo $search; ?>',
                    status: '<?php echo $status_filter; ?>'
                },
                success: function(response) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(response, 'text/html');
                    var newBody = doc.getElementById('sms-body').innerHTML;
                    var newPagination = doc.querySelector('.pagination').innerHTML;
                    $('#sms-body').html(newBody);
                    $('.pagination').html(newPagination);
                    attachEventListeners();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading SMS list:', error);
                    $('#message-container').html('<div class="alert alert-danger">خطا در بارگذاری لیست پیامک‌ها</div>');
                }
            });
        }

        // Auto-update status
        setInterval(loadSmsList, 10000);

        // Attach event listeners after loading new content
        function attachEventListeners() {
            $('.send-btn').off().click(function() {
                var id = $(this).data('id');
                $.ajax({
                    url: 'send_single.php',
                    type: 'POST',
                    data: $('#send-form-' + id).serialize() + '&send_sms=1',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadSmsList();
                            $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                            setTimeout(() => $('#message-container').empty(), 3000);
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error sending SMS:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در ارسال پیامک</div>');
                    }
                });
            });

            $('.delete-btn').off().click(function() {
                var id = $(this).data('id');
                if (confirm('آیا مطمئن هستید که می‌خواهید این پیامک را حذف کنید؟')) {
                    $.ajax({
                        url: 'send_single.php',
                        type: 'POST',
                        data: $('#delete-form-' + id).serialize() + '&delete_sms=1',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                loadSmsList();
                                $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                                setTimeout(() => $('#message-container').empty(), 3000);
                            } else {
                                $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error deleting SMS:', error);
                            $('#message-container').html('<div class="alert alert-danger">خطا در حذف پیامک</div>');
                        }
                    });
                }
            });

            $('.save-edit').off().click(function() {
                var id = $(this).data('id');
                var formData = $('#edit-form-' + id).serialize() + '&edit_sms=1';
                var message = $('#edit-form-' + id + ' .message-input').val();
                $.ajax({
                    url: 'send_single.php',
                    type: 'POST',
                    data: formData + '&message=' + encodeURIComponent(message),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#editSmsModal' + id).modal('hide');
                            loadSmsList();
                            $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                            setTimeout(() => $('#message-container').empty(), 3000);
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error editing SMS:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در ویرایش پیامک</div>');
                    }
                });
            });

            $('.draft-select').off('change').on('change', function() {
                var draft_id = $(this).val();
                var textarea = $(this).closest('.modal-body').find('.message-input');
                if (draft_id) {
                    $.ajax({
                        url: 'get_draft.php',
                        type: 'POST',
                        data: { draft_id: draft_id },
                        success: function(response) {
                            textarea.val(response);
                            var id = textarea.closest('.modal').attr('id').replace('editSmsModal', '');
                            updateMessageStats(textarea, $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching draft:', error);
                            $('#message-container').html('<div class="alert alert-danger">خطا در بارگذاری پیش‌نویس</div>');
                        }
                    });
                } else {
                    textarea.val('');
                    var id = textarea.closest('.modal').attr('id').replace('editSmsModal', '');
                    updateMessageStats(textarea, $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
                }
            });

            $('.message-input').off('input').on('input', function() {
                var id = $(this).closest('.modal').attr('id').replace('editSmsModal', '');
                updateMessageStats($(this), $('#char_count_' + id), $('#sms_parts_' + id), $('#cost_estimate_' + id));
            });

            $('.preview-btn').off().click(function() {
                var fullMessage = $(this).data('full-message');
                $('#fullMessageModal' + $(this).data('target').replace('#fullMessageModal', '') + ' .modal-body pre').text(fullMessage);
            });
        }
    });
</script>
</body>
</html>