<?php
require_once 'config.php';
require_once 'menus.php'; // اضافه کردن فایل منوها
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// گرفتن اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}
$school_id = $user['school_id'];

// گرفتن نام مرکز از ترکیب فیلدها
$stmt = $pdo->prepare("SELECT school_type, gender_type, school_name FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();
$school_name = $school ? trim($school['school_type'] . ' ' . $school['gender_type'] . ' ' . $school['school_name']) : 'مدرسه نامشخص';

// تابع برای محاسبه موجودی
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

// تابع برای محاسبه هزینه پیامک (هماهنگ با ارسال تکی)
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

// تابع برای محاسبه هزینه گروهی
function calculate_group_sms_cost($message, $school_name, $recipient_count) {
    $cost_per_sms = calculate_sms_cost($message, $school_name);
    return $cost_per_sms * $recipient_count;
}

// مقداردهی اولیه متغیرها
$error = '';
$success = '';
$balance = get_balance($pdo, $school_id);

// دیباگ: لاگ موجودی اولیه
file_put_contents('debug.log', "Initial Balance for school_id $school_id: $balance\n", FILE_APPEND);

// Handle sending group message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_group_message'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

    if (!empty($group_id) && !empty($message)) {
        // اضافه کردن هدر و فوتر به پیام
        $full_message = $school_name . "\n" . $message . "\n" . SMS_FOOTER_TEXT;

        // گرفتن تمام مخاطبین گروه
        $stmt = $pdo->prepare("SELECT mobile FROM contacts WHERE school_id = ? AND group_id = ?");
        $stmt->execute([$school_id, $group_id]);
        $contacts = $stmt->fetchAll();

        if (empty($contacts)) {
            $error = "هیچ مخاطبی در این گروه یافت نشد.";
        } else {
            $recipient_count = count($contacts);
            $sms_cost = calculate_group_sms_cost($message, $school_name, $recipient_count);
            $current_balance = get_balance($pdo, $school_id);

            // دیباگ: لاگ هزینه و موجودی
            file_put_contents('debug.log', "SMS Cost: $sms_cost, Current Balance: $current_balance\n", FILE_APPEND);

            if ($current_balance >= $sms_cost) {
                $mobiles = [];
                $invalid_mobiles = [];
                foreach ($contacts as $contact) {
                    $mobile = $contact['mobile'];
                    // اعتبارسنجی شماره موبایل
                    if (preg_match('/^09[0-9]{9}$/', $mobile)) {
                        $mobiles[] = $mobile;
                    } else {
                        $invalid_mobiles[] = $mobile;
                    }
                }

                if (empty($mobiles)) {
                    $error = "هیچ شماره موبایل معتبری در این گروه یافت نشد.";
                } else {
                    $to_list = implode(',', $mobiles);

                    // ثبت ارسال گروهی در جدول group_sms
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO group_sms (school_id, group_id, draft_id, message, mobile_list, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$school_id, $group_id, $draft_id ?: null, $full_message, json_encode($mobiles)]);
                        $group_sms_id = $pdo->lastInsertId();
                    } catch (PDOException $e) {
                        $error = "خطا در ثبت ارسال گروهی: " . $e->getMessage();
                        file_put_contents('debug.log', "Error inserting into group_sms: " . $e->getMessage() . "\n", FILE_APPEND);
                    }

                    if (empty($error)) {
                        // ارسال درخواست به API صبانوین
                        $url = SABANOVIN_BASE_URL . '/sms/send.json?gateway=' . urlencode(SABANOVIN_GATEWAY) . '&to=' . urlencode($to_list) . '&text=' . urlencode($full_message);

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPGET, true);
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        // دیباگ: لاگ پاسخ API
                        file_put_contents('debug.log', "API Response: $response, HTTP Code: $http_code\n", FILE_APPEND);

                        if ($http_code == 200) {
                            $response_data = json_decode($response, true);
                            if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                                $batch_id = $response_data['batch_id'] ?? null;
                                $success_count = count($mobiles);
                                $success = "پیامک به $success_count مخاطب با موفقیت ارسال شد (Batch ID: " . ($batch_id ?? 'ندارد') . ").";

                                // ثبت تراکنش (هماهنگ با ارسال تکی)
                                $details = "ارسال گروهی به گروه $group_id (تعداد: $success_count, Batch ID: " . ($batch_id ?? 'ندارد') . ")";
                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO transactions (school_id, amount, status, created_at, details, type)
                                        VALUES (?, ?, 'successful', NOW(), ?, 'debit')
                                    ");
                                    $stmt->execute([$school_id, $sms_cost, $details]);
                                    file_put_contents('debug.log', "Transaction inserted successfully: $details\n", FILE_APPEND);
                                } catch (PDOException $e) {
                                    $error = "خطا در ثبت تراکنش: " . $e->getMessage();
                                    file_put_contents('debug.log', "Error inserting transaction: " . $e->getMessage() . "\n", FILE_APPEND);
                                }

                                // به‌روزرسانی وضعیت در جدول group_sms
                                try {
                                    $stmt = $pdo->prepare("
                                        UPDATE group_sms
                                        SET status = 'sent', batch_id = ?, sent_at = NOW(), updated_at = NOW()
                                        WHERE id = ?
                                    ");
                                    $stmt->execute([$batch_id, $group_sms_id]);
                                } catch (PDOException $e) {
                                    $error = "خطا در به‌روزرسانی وضعیت ارسال گروهی: " . $e->getMessage();
                                    file_put_contents('debug.log', "Error updating group_sms: " . $e->getMessage() . "\n", FILE_APPEND);
                                }
                            } else {
                                $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                                file_put_contents('sms_errors.log', "Failed batch: " . $response . "\n", FILE_APPEND);

                                $stmt = $pdo->prepare("UPDATE group_sms SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$error, $group_sms_id]);
                            }
                        } else {
                            $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                            file_put_contents('sms_errors.log', "HTTP Error $http_code for group $group_id\n", FILE_APPEND);

                            $stmt = $pdo->prepare("UPDATE group_sms SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$error, $group_sms_id]);
                        }

                        // گزارش شماره‌های نامعتبر
                        if (!empty($invalid_mobiles)) {
                            $error .= " شماره‌های نامعتبر (ارسال نشدند): " . implode(', ', $invalid_mobiles);
                        }
                    }
                }
            } else {
                $error = "موجودی حساب کافی نیست. هزینه ارسال: " . number_format($sms_cost) . " تومان، موجودی فعلی: " . number_format($current_balance) . " تومان.";
            }
        }
    } else {
        $error = "گروه و متن پیام نمی‌توانند خالی باشند.";
    }
}

// دیباگ: لاگ موجودی نهایی
$final_balance = get_balance($pdo, $school_id);
file_put_contents('debug.log', "Final Balance for school_id $school_id: $final_balance\n", FILE_APPEND);

// گرفتن تمام گروه‌های مخاطبین
$stmt = $pdo->prepare("SELECT cg.*, COUNT(c.id) as contact_count FROM contact_groups cg LEFT JOIN contacts c ON cg.id = c.group_id WHERE cg.school_id = ? GROUP BY cg.id ORDER BY cg.created_at DESC");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// گرفتن پیش‌نویس‌های ساده تأییدشده
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' AND type = 'simple' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();

// تنظیم عنوان صفحه
$page_title = "ارسال پیامک گروهی - سامانه پیامک مدارس";

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
                <h1>ارسال پیامک گروهی</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <p>موجودی فعلی: <?php echo number_format($balance); ?> تومان</p>
                <div id="message-container">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">ارسال پیامک به گروهی از مخاطبین</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="alert alert-info">هیچ گروهی یافت نشد. لطفاً ابتدا یک گروه ایجاد کنید.</div>
                        <?php else: ?>
                            <form method="POST" id="group-sms-form">
                                <div class="form-group">
                                    <label for="group_id">انتخاب گروه</label>
                                    <select class="form-control" name="group_id" id="group_id" required>
                                        <option value="">-- گروه را انتخاب کنید --</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>" data-contact-count="<?php echo $group['contact_count']; ?>">
                                                <?php echo htmlspecialchars($group['group_name']); ?> (<?php echo $group['contact_count']; ?> مخاطب)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">تعداد مخاطبین گروه انتخاب‌شده نمایش داده می‌شود.</small>
                                </div>
                                <div class="form-group">
                                    <label for="draft_id">انتخاب پیش‌نویس (فقط ساده)</label>
                                    <select class="form-control" name="draft_id" id="draft_id">
                                        <option value="">-- بدون پیش‌نویس --</option>
                                        <?php foreach ($approved_drafts as $draft): ?>
                                            <option value="<?php echo $draft['id']; ?>">
                                                <?php echo htmlspecialchars($draft['title']); ?>
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
                                        هزینه تخمینی برای هر پیامک: <span id="cost_estimate" class="counter-animated">0</span> تومان | 
                                        کل هزینه (برای <span id="recipient_count">0</span> مخاطب): <span id="total_cost" class="counter-animated">0</span> تومان
                                    </div>
                                </div>
                                <button type="submit" name="send_group_message" class="btn btn-primary">ارسال پیامک گروهی</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/dist/js/adminlte.min.js"></script>
<script>
    $(document).ready(function() {
        // محو شدن پیام‌های موفقیت و خطا بعد از 5 ثانیه
        setTimeout(function() {
            $('#message-container .alert').fadeOut('slow');
        }, 5000);

        // تابع برای به‌روزرسانی تعداد کاراکترها، بخش‌ها و هزینه
        function updateMessageStats() {
            var textarea = $('#message');
            var userText = textarea.val() || '';
            var header = textarea.data('header') || '';
            var footer = textarea.data('footer') || '';
            var fullMessage = header + "\n" + userText + "\n" + footer;
            var char_count = fullMessage.length;
            var sms_parts = 1;
            if (char_count > <?php echo SMS_MAX_CHARS_PART1; ?>) {
                var remaining_chars = char_count - <?php echo SMS_MAX_CHARS_PART1; ?>;
                sms_parts += Math.ceil(remaining_chars / <?php echo SMS_MAX_CHARS_OTHER; ?>);
            }
            var cost_per_sms = sms_parts * <?php echo SMS_COST_PER_PART; ?>;
            var recipient_count = parseInt($('#group_id option:selected').data('contact-count') || 0);
            var total_cost = cost_per_sms * recipient_count;

            $('#char_count').text(char_count);
            $('#sms_parts').text(sms_parts);
            $('#cost_estimate').text(cost_per_sms.toFixed(2));
            $('#recipient_count').text(recipient_count);
            $('#total_cost').text(total_cost.toFixed(2));

            // انیمیشن برای تغییرات
            $('#char_count, #sms_parts, #cost_estimate, #recipient_count, #total_cost').addClass('counter-blink');
        }

        // به‌روزرسانی تعداد کاراکترها و هزینه هنگام تایپ
        $('#message').on('input', updateMessageStats);

        // به‌روزرسانی تعداد مخاطبین و هزینه هنگام تغییر گروه
        $('#group_id').on('change', function() {
            updateMessageStats();
        });

        // بارگذاری پیش‌نویس
        $('#draft_id').on('change', function() {
            var draft_id = $(this).val();
            if (draft_id) {
                $.ajax({
                    url: 'get_draft.php',
                    type: 'POST',
                    data: { draft_id: draft_id },
                    success: function(response) {
                        $('#message').val(response);
                        updateMessageStats();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching draft:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در بارگذاری پیش‌نویس</div>');
                    }
                });
            } else {
                $('#message').val('');
                updateMessageStats();
            }
        });

        // مقدار اولیه هنگام لود صفحه
        updateMessageStats();
    });
</script>
<style>
    .counter-blink {
        animation: blink 0.5s ease-in-out;
    }
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    .message-header, .message-footer {
        background-color: #f8f9fa;
        padding: 5px 10px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 5px;
    }
    .lock-icon {
        color: #6c757d;
        margin-left: 5px;
    }
</style>
</body>
</html>