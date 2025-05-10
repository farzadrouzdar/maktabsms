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

// تابع برای محاسبه هزینه پیامک با هدر و فوتر
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

// گرفتن موجودی اولیه
$balance = get_balance($pdo, $school_id);

// Handle sending smart message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_smart_message'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $message_template = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

    if (!empty($group_id) && !empty($message_template)) {
        // اضافه کردن نام مدرسه به عنوان هدر
        $message_template = $school_name . "\n" . $message_template . "\n" . SMS_FOOTER_TEXT;

        // گرفتن تمام مخاطبین گروه با فیلدهای اضافی
        $stmt = $pdo->prepare("SELECT mobile, name, birth_date, field1, field2, field3, field4 FROM contacts WHERE school_id = ? AND group_id = ?");
        $stmt->execute([$school_id, $group_id]);
        $contacts = $stmt->fetchAll();

        if (empty($contacts)) {
            $error = "هیچ مخاطبی در این گروه یافت نشد.";
        } else {
            $to = [];
            $text = [];
            $debug_log = []; // برای دیباگ

            // محاسبه هزینه کل
            $total_cost = 0;
            foreach ($contacts as $contact) {
                $personalized_message = $message_template;
                $replacements = [
                    '{name}' => $contact['name'] ?? '',
                    '{mobile}' => $contact['mobile'] ?? '',
                    '{birth_date}' => $contact['birth_date'] ?? '',
                    '{field1}' => $contact['field1'] ?? '',
                    '{field_1}' => $contact['field1'] ?? '',
                    '{field2}' => $contact['field2'] ?? '',
                    '{field_2}' => $contact['field2'] ?? '',
                    '{field3}' => $contact['field3'] ?? '',
                    '{field_3}' => $contact['field3'] ?? '',
                    '{field4}' => $contact['field4'] ?? '',
                    '{field_4}' => $contact['field4'] ?? ''
                ];

                // جایگزینی تمام متغیرها
                $personalized_message = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $personalized_message
                );

                // حذف خطوط خالی اضافی
                $lines = explode("\r\n", $personalized_message);
                $cleaned_lines = [];
                foreach ($lines as $line) {
                    $trimmed_line = trim($line);
                    if (!empty($trimmed_line)) {
                        $cleaned_lines[] = $trimmed_line;
                    }
                }
                $personalized_message = implode("\r\n", $cleaned_lines);

                // چک کردن متغیرهای جایگزین‌نشده
                if (preg_match_all('/\{[^}]+\}/', $personalized_message, $matches)) {
                    $debug_log[] = [
                        'mobile' => $contact['mobile'],
                        'message' => $personalized_message,
                        'unreplaced_variables' => $matches[0],
                        'replacements' => $replacements
                    ];
                    $error = "برخی متغیرها جایگزین نشدند: " . implode(', ', $matches[0]);
                    file_put_contents('smart_sms_debug.log', json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                    break; // توقف پردازش برای نمایش خطا
                }

                // لاگ جایگزینی‌ها برای دیباگ
                $debug_log[] = [
                    'mobile' => $contact['mobile'],
                    'message' => $personalized_message,
                    'replacements' => $replacements
                ];

                $to[] = $contact['mobile'];
                $text[] = $personalized_message;

                // محاسبه هزینه برای هر پیامک
                $total_cost += calculate_sms_cost($personalized_message, $school_name);
            }

            // ذخیره لاگ دیباگ
            file_put_contents('smart_sms_debug.log', json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            // فقط اگه خطایی نبود ادامه بده
            if (!isset($error)) {
                // چک کردن موجودی
                if ($balance >= $total_cost) {
                    // آماده‌سازی داده‌های POST به صورت آرایه
                    $post_data = [
                        'gateway' => SABANOVIN_GATEWAY,
                        'to' => $to,
                        'text' => $text,
                    ];

                    // ارسال درخواست به API صبانوین با متد POST
                    $ch = curl_init(SABANOVIN_BASE_URL . '/sms/send_array.json');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true); // استفاده از متد POST
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data)); // ارسال داده‌ها به صورت JSON
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                    ]);

                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    // بررسی پاسخ API
                    if ($http_code == 200) {
                        $response_data = json_decode($response, true);
                        if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                            $success_count = count($to);

                            // ثبت تراکنش
                            $stmt = $pdo->prepare("
                                INSERT INTO transactions (school_id, amount, status, created_at, details, type, payment_id)
                                VALUES (?, ?, 'successful', NOW(), ?, 'debit', ?)
                            ");
                            $details = json_encode(['count' => $success_count, 'group_id' => $group_id]);
                            $stmt->execute([$school_id, $total_cost, $details, $response_data['batch_id']]); // حذف علامت منفی

                            $success = "پیامک به $success_count مخاطب با موفقیت ارسال شد (Batch ID: " . $response_data['batch_id'] . "). هزینه: " . number_format($total_cost) . " تومان.";
                        } else {
                            $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                            file_put_contents('sms_errors.log', "Failed smart batch: " . $response . "\n", FILE_APPEND);
                        }
                    } else {
                        $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                        file_put_contents('sms_errors.log', "HTTP Error $http_code for smart group $group_id\n", FILE_APPEND);
                    }
                } else {
                    $error = "موجودی کافی نیست. هزینه کل: " . number_format($total_cost) . " تومان، موجودی شما: " . number_format($balance) . " تومان.";
                }
            }
        }
    } else {
        $error = "گروه و متن پیام نمی‌توانند خالی باشند.";
    }
}

// گرفتن تمام گروه‌های مخاطبین
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// گرفتن پیش‌نویس‌های هوشمند تأییدشده
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' AND type = 'smart' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();

// تنظیم عنوان صفحه
$page_title = "ارسال پیامک هوشمند - سامانه پیامک مدارس";

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
                    <h1>ارسال پیامک هوشمند</h1>
                </div>
            </section>
            <section class="content">
                <div class="container-fluid">
                    <p>موجودی شما: <?php echo number_format($balance); ?> تومان</p>
                    <div id="message-container">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">ارسال پیامک هوشمند به گروهی از مخاطبین</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="smart-sms-form">
                                <div class
                                <label for="group_id">انتخاب گروه</label>
                                    <select class="form-control" name="group_id" id="group_id" required>
                                        <option value="">-- گروه را انتخاب کنید --</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>">
                                                <?php echo htmlspecialchars($group['group_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="draft_id">انتخاب پیش‌نویس هوشمند</label>
                                    <select class="form-control draft-select" name="draft_id" id="draft_id">
                                        <option value="">-- بدون پیش‌نویس --</option>
                                        <?php foreach ($approved_drafts as $draft): ?>
                                            <option value="<?php echo $draft['id']; ?>">
                                                <?php echo htmlspecialchars($draft['title']); ?> <span class="badge badge-success">هوشمند</span>
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
                                    <small class="form-text text-muted">می‌توانید از متغیرهایی مثل {name}، {mobile}، {birth_date}، {field1} تا {field4} استفاده کنید. هدر و فوتر به‌صورت خودکار اضافه می‌شوند.</small>
                                    <div class="mt-2">
                                        <span id="char_count" class="counter-animated">0</span> کاراکتر |
                                        <span id="sms_parts" class="counter-animated">1</span> پیامک |
                                        هزینه تخمینی: <span id="cost_estimate" class="counter-animated">0</span> تومان
                                    </div>
                                </div>
                                <button type="submit" name="send_smart_message" class="btn btn-primary" id="send-smart-btn">
                                    <span class="button-text">ارسال پیامک هوشمند</span>
                                    <span class="loading-text" style="display: none;">در حال ارسال...</span>
                                </button>
                            </form>
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
            // Function to update character count, SMS parts, and cost with animation
            function updateMessageStats(textarea, charCountElement, smsPartsElement, costElement) {
                try {
                    var userText = textarea.val() || '';
                    var header = textarea.data('header') || '';
                    var footer = textarea.data('footer') || '';
                    var fullMessage = header + "\n" + userText + "\n" . footer;
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
                    $('#message-container').html('<div class="alert alert-danger">خطا در شمارش: ' + e.message + '</div>');
                    charCountElement.text('0');
                    smsPartsElement.text('1');
                    costElement.text('0.00');
                }
            }

            // Handle draft selection
            $('#draft_id').on('change', function() {
                var draft_id = $(this).val();
                var textarea = $('#message');
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

            // Live character count and cost estimation
            $('#message').on('input', function() {
                updateMessageStats($(this), $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
            });

            // Initialize stats on page load
            updateMessageStats($('#message'), $('#char_count'), $('#sms_parts'), $('#cost_estimate'));

            // Handle form submission with loading state
            $('#smart-sms-form').on('submit', function(e) {
                var sendBtn = $('#send-smart-btn');
                sendBtn.prop('disabled', true);
                sendBtn.find('.button-text').hide();
                sendBtn.find('.loading-text').show();

                // Submit form via AJAX
                $.ajax({
                    url: 'send_smart.php',
                    type: 'POST',
                    data: $(this).serialize() + '&send_smart_message=1',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                            $('#smart-sms-form')[0].reset();
                            updateMessageStats($('#message'), $('#char_count'), $('#sms_parts'), $('#cost_estimate'));
                        } else {
                            $('#message-container').html('<div class="alert alert-danger">' + response.error + '</div>');
                        }
                        setTimeout(() => $('#message-container').empty(), 5000);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error sending smart SMS:', error);
                        $('#message-container').html('<div class="alert alert-danger">خطا در ارسال پیامک</div>');
                    },
                    complete: function() {
                        sendBtn.prop('disabled', false);
                        sendBtn.find('.button-text').show();
                        sendBtn.find('.loading-text').hide();
                    }
                });

                return false; // Prevent default form submission
            });
        });
    </script>
    <style>
        .lock-icon {
            color: #6c757d;
            margin-left: 5px;
        }
        .message-header, .message-footer {
            background-color: #f8f9fa;
            padding: 5px 10px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .message-footer {
            margin-top: 5px;
        }
        .counter-animated {
            transition: all 0.3s ease;
        }
        .counter-blink {
            color: #007bff;
            font-weight: bold;
        }
    </style>
    </body>
    </html>