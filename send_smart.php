<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$school_id = $user['school_id'];

// Handle sending smart message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_smart_message'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $message_template = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

    if (!empty($group_id) && !empty($message_template)) {
        // Add footer text to the template
        $message_template .= ' ' . SMS_FOOTER_TEXT;

        // Fetch all contacts in the selected group with additional fields
        $stmt = $pdo->prepare("SELECT mobile, name, birth_date, field1, field2, field3, field4 FROM contacts WHERE school_id = ? AND group_id = ?");
        $stmt->execute([$school_id, $group_id]);
        $contacts = $stmt->fetchAll();

        if (empty($contacts)) {
            $error = "هیچ مخاطبی در این گروه یافت نشد.";
        } else {
            $to = [];
            $text = [];
            $debug_log = []; // For debugging

            // Prepare personalized messages
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

                // Replace all variables
                $personalized_message = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $personalized_message
                );

                // Remove extra newlines if fields are empty
                $lines = explode("\r\n", $personalized_message);
                $cleaned_lines = [];
                foreach ($lines as $line) {
                    $trimmed_line = trim($line);
                    if (!empty($trimmed_line)) {
                        $cleaned_lines[] = $trimmed_line;
                    }
                }
                $personalized_message = implode("\r\n", $cleaned_lines);

                // Check for missing replacements
                if (preg_match_all('/\{[^}]+\}/', $personalized_message, $matches)) {
                    $debug_log[] = [
                        'mobile' => $contact['mobile'],
                        'message' => $personalized_message,
                        'unreplaced_variables' => $matches[0],
                        'replacements' => $replacements
                    ];
                    $error = "برخی متغیرها جایگزین نشدند: " . implode(', ', $matches[0]);
                    file_put_contents('smart_sms_debug.log', json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                    break; // Stop processing to show the error
                }

                // Log the replacements for debugging
                $debug_log[] = [
                    'mobile' => $contact['mobile'],
                    'message' => $personalized_message,
                    'replacements' => $replacements
                ];

                $to[] = $contact['mobile'];
                $text[] = $personalized_message;
            }

            // Save debug log
            file_put_contents('smart_sms_debug.log', json_encode($debug_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            // Only proceed if no error
            if (!isset($error)) {
                // Prepare URL with parameters for Sabanovin API
                $to_json = urlencode(json_encode($to));
                $text_json = urlencode(json_encode($text));
                $url = SABANOVIN_BASE_URL . '/sms/send_array.json?gateway=' . urlencode(SABANOVIN_GATEWAY) . '&to=' . $to_json . '&text=' . $text_json;

                // Send request to Sabanovin API
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPGET, true);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Check API response
                if ($http_code == 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['status']['code']) && $response_data['status']['code'] == 200) {
                        $success_count = count($to);
                        $success = "پیامک به $success_count مخاطب با موفقیت ارسال شد (Batch ID: " . $response_data['batch_id'] . ").";
                    } else {
                        $error = "خطا در ارسال پیامک: " . ($response_data['status']['message'] ?? 'پاسخ نامشخص از API');
                        file_put_contents('sms_errors.log', "Failed smart batch: " . $response . "\n", FILE_APPEND);
                    }
                } else {
                    $error = "خطا در اتصال به API صبانوین (کد HTTP: $http_code)";
                    file_put_contents('sms_errors.log', "HTTP Error $http_code for smart group $group_id\n", FILE_APPEND);
                }
            }
        }
    } else {
        $error = "گروه و متن پیام نمی‌توانند خالی باشند.";
    }
}

// Fetch all contact groups
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// Fetch approved smart drafts
$stmt = $pdo->prepare("SELECT * FROM drafts WHERE school_id = ? AND status = 'approved' AND type = 'smart' ORDER BY created_at DESC");
$stmt->execute([$school_id]);
$approved_drafts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال پیامک هوشمند - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <!-- Navbar -->
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
    <!-- Main Sidebar -->
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
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>داشبورد</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="phonebook.php" class="nav-link">
                            <i class="nav-icon fas fa-address-book"></i>
                            <p>دفترچه تلفن</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="drafts.php" class="nav-link">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>پیش‌نویس‌ها</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_single.php" class="nav-link">
                            <i class="nav-icon fas fa-comment"></i>
                            <p>ارسال پیامک تکی</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_group.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>ارسال پیامک گروهی</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="send_smart.php" class="nav-link active">
                            <i class="nav-icon fas fa-brain"></i>
                            <p>ارسال پیامک هوشمند</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="receive_sms.php" class="nav-link">
                            <i class="nav-icon fas fa-inbox"></i>
                            <p>دریافت پیامک</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="charge.php" class="nav-link">
                            <i class="nav-icon fas fa-wallet"></i>
                            <p>شارژ پیامک</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payments.php" class="nav-link">
                            <i class="nav-icon fas fa-list"></i>
                            <p>گزارش پرداخت‌ها</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>خروج</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>ارسال پیامک هوشمند</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <!-- Send Smart Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">ارسال پیامک هوشمند به گروهی از مخاطبین</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="group_id">انتخاب گروه</label>
                                <select class="form-control" name="group_id" required>
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
                                <label for="message">متن پیش‌نویس (با متغیرها مثل {name}, {mobile}, {birth_date}, {field1} تا {field4})</label>
                                <textarea class="form-control" name="message" id="message" rows="3" required></textarea>
                                <small class="form-text text-muted">متن پیش‌نویس رو وارد کن یا از پیش‌نویس انتخاب‌شده استفاده کن.</small>
                            </div>
                            <button type="submit" name="send_smart_message" class="btn btn-primary">ارسال پیامک هوشمند</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <!-- Footer -->
    <footer class="main-footer">
        <strong>maktabsms © <?php echo date('Y'); ?></strong>
    </footer>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
<script>
    $(document).ready(function() {
        $('#draft_id').change(function() {
            var draft_id = $(this).val();
            if (draft_id) {
                $.ajax({
                    url: 'get_draft.php',
                    type: 'POST',
                    data: { draft_id: draft_id },
                    success: function(response) {
                        $('#message').val(response);
                    }
                });
            } else {
                $('#message').val('');
            }
        });
    });
</script>
</body>
</html>