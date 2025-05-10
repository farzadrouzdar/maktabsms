<?php
require_once 'config.php';
date_default_timezone_set('Asia/Tehran'); // تنظیم منطقه زمانی به ایران
require_once 'composer/vendor/autoload.php'; // لود خودکار پکیج‌ها
use Morilog\Jalali\Jalalian;
require_once 'menus.php'; // اضافه کردن فایل منوها
session_start();

// تابع برای لاگ کردن
function logDebug($message) {
    $logFile = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

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

// تابع برای نمایش جدول پیش‌نویس‌ها
function displayDraftsTable($drafts, $selected_group) {
    if (empty($drafts)) {
        echo '<div class="alert alert-info mt-4">هیچ پیش‌نویسی یافت نشد.</div>';
        return;
    }
    ?>
    <table class="table table-bordered table-striped mt-4">
        <thead>
            <tr>
                <th>#</th>
                <th>عنوان</th>
                <th>نوع پیش‌نویس</th>
                <th>پیام</th>
                <th>تاریخ ایجاد</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($drafts as $index => $draft): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($draft['title']); ?></td>
                    <td><?php echo htmlspecialchars($draft['type_fa'] ?? $draft['type'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars(mb_substr($draft['message'], 0, 50, 'UTF-8')) . '...'; ?></td>
                    <td><?php echo Jalalian::forge($draft['created_at'])->format('Y/m/d H:i'); ?></td>
                    <td>
                        <a href="?edit_draft=<?php echo $draft['id']; ?>&group=<?php echo $selected_group; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> ویرایش</a>
                        <a href="?delete_draft=<?php echo $draft['id']; ?>&group=<?php echo $selected_group; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این پیش‌نویس را حذف کنید؟');"><i class="fas fa-trash"></i> حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
    if (!empty($group_name)) {
        $stmt = $pdo->prepare("INSERT INTO draft_groups (school_id, group_name) VALUES (?, ?)");
        $stmt->execute([$school_id, $group_name]);
        $success = "گروه با موفقیت ایجاد شد.";
        header('Location: drafts.php');
        exit;
    } else {
        $error = "نام گروه نمی‌تواند خالی باشد.";
    }
}

// Handle group update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
    if (!empty($group_name)) {
        $stmt = $pdo->prepare("UPDATE draft_groups SET group_name = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$group_name, $group_id, $school_id]);
        $success = "گروه با موفقیت به‌روزرسانی شد.";
        header('Location: drafts.php');
        exit;
    } else {
        $error = "نام گروه نمی‌تواند خالی باشد.";
    }
}

// Handle group deletion
if (isset($_GET['delete_group'])) {
    $group_id = filter_var($_GET['delete_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $stmt = $pdo->prepare("DELETE FROM drafts WHERE group_id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $success = "گروه و پیش‌نویس‌های آن با موفقیت حذف شدند.";
    header('Location: drafts.php');
    exit;
}

// Handle draft creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_draft'])) {
    logDebug("Starting create_draft with POST data: " . json_encode($_POST));
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);

    logDebug("Processed data - group_id: $group_id, title: $title, message: $message, type: $type");

    if (empty($title) || empty($message)) {
        $error = "عنوان و پیام نمی‌توانند خالی باشند.";
        logDebug("Validation failed: Title or message is empty.");
    } elseif (empty($type) || !in_array($type, ['simple', 'named'])) {
        $error = "نوع پیش‌نویس نامعتبر است. مقدار دریافت‌شده: $type";
        logDebug("Validation failed: Invalid type - $type");
    } elseif (empty($group_id)) {
        $error = "گروه انتخاب‌شده نامعتبر است.";
        logDebug("Validation failed: Invalid group_id - $group_id");
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO drafts (school_id, group_id, title, message, type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'approved', NOW(), NOW())
            ");
            $stmt->execute([$school_id, $group_id, $title, $message, $type]);
            $lastInsertId = $pdo->lastInsertId();
            logDebug("Inserted data into drafts table: school_id=$school_id, group_id=$group_id, title=$title, message=$message, type=$type, lastInsertId=$lastInsertId");

            // دیباگ مقدار ذخیره‌شده
            $stmt_verify = $pdo->prepare("SELECT type FROM drafts WHERE id = ?");
            $stmt_verify->execute([$lastInsertId]);
            $saved_type = $stmt_verify->fetchColumn();
            logDebug("Verified saved type for draft id $lastInsertId: $saved_type");

            $success = "پیش‌نویس با موفقیت ایجاد شد.";
            header('Location: drafts.php?group=' . $group_id);
            exit;
        } catch (PDOException $e) {
            $error = "خطا در ایجاد پیش‌نویس: " . $e->getMessage();
            logDebug("Error in creating draft: " . $e->getMessage());
        }
    }
}

// Fetch group for editing
$edit_group = null;
if (isset($_GET['edit_group'])) {
    $group_id = filter_var($_GET['edit_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $edit_group = $stmt->fetch();
    if (!$edit_group) {
        $error = "گروه موردنظر یافت نشد.";
        header('Location: drafts.php');
        exit;
    }
}

// Fetch draft for editing
$edit_draft = null;
if (isset($_GET['edit_draft'])) {
    $draft_id = filter_var($_GET['edit_draft'], FILTER_SANITIZE_NUMBER_INT);
    $stmt_edit_draft = $pdo->prepare("SELECT * FROM drafts WHERE id = ? AND school_id = ?");
    $stmt_edit_draft->execute([$draft_id, $school_id]);
    $edit_draft = $stmt_edit_draft->fetch();

    if (!$edit_draft) {
        $error = "پیش‌نویس موردنظر یافت نشد.";
        header('Location: drafts.php');
        exit;
    }
}

// Handle draft deletion
if (isset($_GET['delete_draft'])) {
    $draft_id = filter_var($_GET['delete_draft'], FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt_delete_draft = $pdo->prepare("DELETE FROM drafts WHERE id = ? AND school_id = ?");
    $stmt_delete_draft->execute([$draft_id, $school_id]);
    $success = "پیش‌نویس با موفقیت حذف شد.";
    header('Location: drafts.php?group=' . $group_id);
    exit;
}

// Handle draft update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draft'])) {
    logDebug("Starting update_draft with POST data: " . json_encode($_POST));
    $draft_id_update = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);

    logDebug("Processed data - draft_id: $draft_id_update, group_id: $group_id, title: $title, message: $message, type: $type");

    if (empty($title) || empty($message)) {
        $error = "عنوان و پیام نمی‌توانند خالی باشند.";
        logDebug("Validation failed: Title or message is empty.");
    } elseif (empty($type) || !in_array($type, ['simple', 'named'])) {
        $error = "نوع پیش‌نویس نامعتبر است. مقدار دریافت‌شده: $type";
        logDebug("Validation failed: Invalid type - $type");
    } else {
        try {
            $stmt_update_draft = $pdo->prepare("
                UPDATE drafts
                SET title = ?, message = ?, type = ?, updated_at = NOW()
                WHERE id = ? AND school_id = ?
            ");
            $stmt_update_draft->execute([$title, $message, $type, $draft_id_update, $school_id]);
            $success = "پیش‌نویس با موفقیت به‌روزرسانی شد.";
            logDebug("Draft updated successfully for draft_id: $draft_id_update with type: $type");
            header('Location: drafts.php?group=' . $group_id);
            exit;
        } catch (PDOException $e) {
            $error = "خطا در به‌روزرسانی پیش‌نویس: " . $e->getMessage();
            logDebug("Error in updating draft: " . $e->getMessage());
        }
    }
}

// Fetch all draft groups with draft count
$stmt_groups = $pdo->prepare("
    SELECT dg.*, COUNT(d.id) as draft_count
    FROM draft_groups dg
    LEFT JOIN drafts d ON d.group_id = dg.id AND d.school_id = dg.school_id AND d.status = 'approved'
    WHERE dg.school_id = ?
    GROUP BY dg.id, dg.group_name, dg.created_at
    ORDER BY dg.created_at DESC
");
$stmt_groups->execute([$school_id]);
$groups = $stmt_groups->fetchAll();

// Handle group selection
$selected_group = isset($_GET['group']) ? filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT) : null;
$selected_group_name = '';
$drafts = [];
if ($selected_group) {
    $stmt = $pdo->prepare("SELECT group_name FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$selected_group, $school_id]);
    $group = $stmt->fetch();
    if (!$group) {
        $error = "گروه انتخاب‌شده یافت نشد.";
        header('Location: drafts.php');
        exit;
    }
    $selected_group_name = $group['group_name'];

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            message,
            type,
            created_at,
            CASE type
                WHEN 'simple' THEN 'ساده'
                WHEN 'named' THEN 'ارسال با نام'
                ELSE '-'
            END AS type_fa
        FROM drafts
        WHERE school_id = ? AND group_id = ? AND status = 'approved'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$school_id, $selected_group]);
    $drafts = $stmt->fetchAll();
    logDebug("Fetched drafts: " . json_encode($drafts)); // دیباگ برای چک کردن دیتای برگشتی
}

// تنظیم عنوان صفحه
$page_title = "پیش‌نویس‌ها - سامانه پیامک مدارس";

// لود فایل header.php
require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- لود jQuery از فایل محلی -->
    <script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/jquery/jquery.min.js"></script>
    <script>
        // تست اولیه لود شدن jQuery
        if (typeof jQuery === 'undefined') {
            document.write('<div class="alert alert-danger mt-4">خطا: jQuery از فایل محلی لود نشده است! لطفاً مسیر را بررسی کنید.</div>');
            console.error('jQuery failed to load from local file!');
        } else {
            console.log('jQuery loaded successfully from local file');
        }
    </script>
</head>
<body>
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
                <h1>پیش‌نویس‌ها</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div id="message-container">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$edit_group && !$edit_draft): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">ایجاد گروه جدید</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="group_name">نام گروه</label>
                                    <input type="text" class="form-control" name="group_name" required>
                                </div>
                                <button type="submit" name="create_group" class="btn btn-primary"><i class="fas fa-plus"></i> ایجاد گروه</button>
                            </form>
                        </div>
                    </div>

                    <!-- نمایش لیست گروه‌ها -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">لیست گروه‌های پیش‌نویس</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نام گروه</th>
                                        <th>تعداد پیش‌نویس‌ها</th>
                                        <th>تاریخ ایجاد</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($groups)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">هیچ گروهی یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($groups as $index => $group): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><a href="?group=<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></a></td>
                                                <td><?php echo $group['draft_count']; ?></td>
                                                <td><?php echo Jalalian::forge($group['created_at'])->format('Y/m/d H:i'); ?></td>
                                                <td>
                                                    <a href="?edit_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> ویرایش</a>
                                                    <a href="?delete_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این گروه را حذف کنید؟');"><i class="fas fa-trash"></i> حذف</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- نمایش پیش‌نویس‌های گروه انتخاب‌شده -->
                    <?php if ($selected_group): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">پیش‌نویس‌های گروه <?php echo htmlspecialchars($selected_group_name); ?></h3>
                            </div>
                            <div class="card-body">
                                <!-- فرم اضافه کردن پیش‌نویس جدید -->
                                <form method="POST" id="create-draft-form">
                                    <input type="hidden" name="group_id" value="<?php echo $selected_group; ?>">
                                    <div class="form-group">
                                        <label for="type">نوع پیش‌نویس <span class="text-danger">*</span></label>
                                        <select class="form-control" name="type" id="type" required>
                                            <option value="">لطفاً انتخاب کنید</option>
                                            <option value="simple">ساده</option>
                                            <option value="named">ارسال با نام</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="title">عنوان <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="message">پیام <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="message" rows="5" required></textarea>
                                        <div id="variables" class="form-text text-muted" style="display: none;">
                                            برای پیش‌نویس ارسال با نام می‌توانید از متغیرها استفاده کنید:
                                            <code>[student_name]</code> (نام دانش‌آموز)،
                                            <code>[parent_name]</code> (نام ولی دانش‌آموز)،
                                            <code>[birth_date]</code> (تاریخ تولد)
                                        </div>
                                    </div>
                                    <button type="submit" name="create_draft" class="btn btn-primary" id="submit-btn" disabled><i class="fas fa-plus"></i> اضافه کردن پیش‌نویس</button>
                                </form>

                                <script>
                                    // چک کردن لود شدن jQuery
                                    if (typeof jQuery === 'undefined') {
                                        document.write('<div class="alert alert-danger mt-4">خطا: jQuery لود نشده است! لطفاً لاگ‌های کنسول را بررسی کنید.</div>');
                                        console.error('jQuery is not loaded!');
                                    } else {
                                        console.log('jQuery is available for use');
                                        $(document).ready(function() {
                                            // تابع برای مدیریت دکمه و متغیرها
                                            function updateFormState() {
                                                const type = $('#type').val();
                                                const variables = $('#variables');
                                                const submitBtn = $('#submit-btn');
                                                console.log('Type changed to: ' + type); // لاگ برای دیباگ
                                                if (type === 'named') {
                                                    variables.show();
                                                    console.log('Variables shown');
                                                } else {
                                                    variables.hide();
                                                    console.log('Variables hidden');
                                                }
                                                submitBtn.prop('disabled', !type);
                                                console.log('Submit button disabled: ' + submitBtn.prop('disabled'));
                                            }

                                            // هنگام تغییر type
                                            $('#type').on('change', updateFormState);

                                            // هنگام لود صفحه (برای اطمینان از وضعیت اولیه)
                                            updateFormState();
                                        });
                                    }
                                </script>

                                <?php displayDraftsTable($drafts, $selected_group); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ($edit_group): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">ویرایش گروه</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="group_name">نام گروه</label>
                                    <input type="text" class="form-control" name="group_name" value="<?php echo htmlspecialchars($edit_group['group_name']); ?>" required>
                                </div>
                                <input type="hidden" name="group_id" value="<?php echo $edit_group['id']; ?>">
                                <button type="submit" name="update_group" class="btn btn-primary"><i class="fas fa-save"></i> به‌روزرسانی</button>
                                <a href="drafts.php" class="btn btn-secondary"><i class="fas fa-times"></i> لغو</a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($edit_draft): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">ویرایش پیش‌نویس</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="update-draft-form">
                                <div class="form-group">
                                    <label for="type">نوع پیش‌نویس <span class="text-danger">*</span></label>
                                    <select class="form-control" name="type" id="type" required>
                                        <option value="">لطفاً انتخاب کنید</option>
                                        <option value="simple" <?php if ($edit_draft['type'] === 'simple') echo 'selected'; ?>>ساده</option>
                                        <option value="named" <?php if ($edit_draft['type'] === 'named') echo 'selected'; ?>>ارسال با نام</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="title">عنوان <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($edit_draft['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="message">پیام <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="message" rows="5" required><?php echo htmlspecialchars($edit_draft['message']); ?></textarea>
                                    <div id="variables" class="form-text text-muted" style="display: <?php echo ($edit_draft['type'] === 'named') ? 'block' : 'none'; ?>;">
                                        متغیرهای قابل استفاده:
                                        <code>[student_name]</code> (نام دانش‌آموز)،
                                        <code>[parent_name]</code> (نام ولی دانش‌آموز)،
                                        <code>[birth_date]</code> (تاریخ تولد)
                                    </div>
                                </div>
                                <input type="hidden" name="draft_id" value="<?php echo $edit_draft['id']; ?>">
                                <input type="hidden" name="group_id" value="<?php echo $selected_group; ?>">
                                <button type="submit" name="update_draft" class="btn btn-primary" id="submit-btn-update"><i class="fas fa-save"></i> ذخیره تغییرات</button>
                                <a href="drafts.php?group=<?php echo $selected_group; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> لغو</a>
                            </form>

                            <script>
                                // چک کردن لود شدن jQuery
                                if (typeof jQuery === 'undefined') {
                                    document.write('<div class="alert alert-danger mt-4">خطا: jQuery لود نشده است! لطفاً لاگ‌های کنسول را بررسی کنید.</div>');
                                    console.error('jQuery is not loaded!');
                                } else {
                                    console.log('jQuery is available for use');
                                    $(document).ready(function() {
                                        // تابع برای مدیریت متغیرها
                                        function updateFormState() {
                                            const type = $('#type').val();
                                            const variables = $('#variables');
                                            console.log('Type changed to: ' + type); // لاگ برای دیباگ
                                            if (type === 'named') {
                                                variables.show();
                                                console.log('Variables shown');
                                            } else {
                                                variables.hide();
                                                console.log('Variables hidden');
                                            }
                                        }

                                        // هنگام تغییر type
                                        $('#type').on('change', updateFormState);

                                        // هنگام لود صفحه (برای اطمینان از وضعیت اولیه)
                                        updateFormState();
                                    });
                                }
                            </script>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <footer class="main-footer">
        <strong>maktabsms © <?php echo Jalalian::now()->format('Y'); ?></strong>
    </footer>
</div>
<script src="<?php echo BASE_URL; ?>assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/adminlte/dist/js/adminlte.min.js"></script>
<script>
    // محو شدن پیام‌های موفقیت و خطا بعد از 5 ثانیه
    if (typeof jQuery !== 'undefined') {
        $(document).ready(function() {
            setTimeout(function() {
                $('#message-container .alert').fadeOut('slow');
            }, 5000);
        });
    }
</script>
</body>
</html>