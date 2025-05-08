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

// Get group info
$group_id = filter_var($_GET['group_id'], FILTER_SANITIZE_NUMBER_INT);
$stmt = $pdo->prepare("SELECT * FROM draft_groups WHERE id = ? AND school_id = ?");
$stmt->execute([$group_id, $school_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: drafts.php');
    exit;
}

// Handle draft creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_draft'])) {
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
    if (empty($type)) {
        $error = "لطفاً نوع پیش‌نویس را انتخاب کنید.";
    } elseif (!empty($title) && !empty($message) && in_array($type, ['simple', 'smart'])) {
        $stmt = $pdo->prepare("INSERT INTO drafts (school_id, group_id, title, message, type, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$school_id, $group_id, $title, $message, $type]);
        $success = "پیش‌نویس با موفقیت ذخیره شد و منتظر تأیید مدیریت است.";
    } else {
        $error = "لطفاً اطلاعات معتبر وارد کنید.";
    }
}

// Handle draft update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draft'])) {
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
    if (empty($type)) {
        $error = "لطفاً نوع پیش‌نویس را انتخاب کنید.";
    } elseif (!empty($title) && !empty($message) && in_array($type, ['simple', 'smart'])) {
        $stmt = $pdo->prepare("UPDATE drafts SET title = ?, message = ?, type = ?, status = 'pending' WHERE id = ? AND school_id = ? AND group_id = ?");
        $stmt->execute([$title, $message, $type, $draft_id, $school_id, $group_id]);
        $success = "پیش‌نویس با موفقیت به‌روزرسانی شد و منتظر تأیید مدیریت است.";
    } else {
        $error = "لطفاً اطلاعات معتبر وارد کنید.";
    }
}

// Handle draft deletion
if (isset($_GET['delete'])) {
    $draft_id = filter_var($_GET['delete'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM drafts WHERE id = ? AND school_id = ? AND group_id = ?");
    $stmt->execute([$draft_id, $school_id, $group_id]);
    $success = "پیش‌نویس با موفقیت حذف شد.";
    header("Location: draft_list.php?group_id=$group_id");
    exit;
}

// Fetch all drafts with search functionality
$search_title = isset($_GET['search_title']) ? filter_var($_GET['search_title'], FILTER_SANITIZE_STRING) : '';
$search_message = isset($_GET['search_message']) ? filter_var($_GET['search_message'], FILTER_SANITIZE_STRING) : '';

$query = "SELECT * FROM drafts WHERE school_id = ? AND group_id = ?";
$params = [$school_id, $group_id];

if (!empty($search_title)) {
    $query .= " AND title LIKE ?";
    $params[] = "%$search_title%";
}
if (!empty($search_message)) {
    $query .= " AND message LIKE ?";
    $params[] = "%$search_message%";
}
$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$drafts = $stmt->fetchAll();

// Fetch draft for editing
$edit_draft = null;
if (isset($_GET['edit'])) {
    $draft_id = filter_var($_GET['edit'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM drafts WHERE id = ? AND school_id = ? AND group_id = ?");
    $stmt->execute([$draft_id, $school_id, $group_id]);
    $edit_draft = $stmt->fetch();
}

// Handle inserting variables into message
if (isset($_POST['insert_variable'])) {
    $variable = filter_var($_POST['variable'], FILTER_SANITIZE_STRING);
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $message .= $variable;
    $edit_draft = ['title' => $edit_draft['title'], 'message' => $message, 'type' => $edit_draft['type'], 'id' => $edit_draft['id']];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نویس‌های گروه <?php echo htmlspecialchars($group['group_name']); ?> - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        .variable-btn {
            margin: 5px;
            cursor: pointer;
        }
        .hidden {
            display: none;
        }
    </style>
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
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>پیش‌نویس‌های گروه <?php echo htmlspecialchars($group['group_name']); ?></h1>
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
                <!-- Draft Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $edit_draft ? 'ویرایش پیش‌نویس' : 'ایجاد پیش‌نویس جدید'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="title">عنوان پیش‌نویس</label>
                                <input type="text" class="form-control" name="title" value="<?php echo $edit_draft ? htmlspecialchars($edit_draft['title']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="type">نوع پیش‌نویس</label>
                                <select class="form-control" name="type" id="draft-type" required>
                                    <option value="" <?php echo !$edit_draft || !$edit_draft['type'] ? 'selected' : ''; ?>>-- انتخاب کنید --</option>
                                    <option value="simple" <?php echo $edit_draft && $edit_draft['type'] == 'simple' ? 'selected' : ''; ?>>ساده</option>
                                    <option value="smart" <?php echo $edit_draft && $edit_draft['type'] == 'smart' ? 'selected' : ''; ?>>هوشمند</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="message">متن پیام</label>
                                <textarea class="form-control" name="message" id="message" rows="3" required><?php echo $edit_draft ? htmlspecialchars($edit_draft['message']) : ''; ?></textarea>
                            </div>
                            <div id="variable-section" class="mt-2 <?php echo $edit_draft && $edit_draft['type'] == 'simple' ? 'hidden' : ''; ?>">
                                <label>فیلدهای اختصاصی:</label>
                                <button type="submit" name="insert_variable" value="{name}" class="btn btn-sm btn-primary variable-btn">نام</button>
                                <button type="submit" name="insert_variable" value="{mobile}" class="btn btn-sm btn-primary variable-btn">موبایل</button>
                                <button type="submit" name="insert_variable" value="{birth_date}" class="btn btn-sm btn-primary variable-btn">تاریخ تولد</button>
                                <button type="submit" name="insert_variable" value="{field_1}" class="btn btn-sm btn-primary variable-btn">فیلد ۱</button>
                                <button type="submit" name="insert_variable" value="{field_2}" class="btn btn-sm btn-primary variable-btn">فیلد ۲</button>
                                <button type="submit" name="insert_variable" value="{field_3}" class="btn btn-sm btn-primary variable-btn">فیلد ۳</button>
                                <button type="submit" name="insert_variable" value="{field_4}" class="btn btn-sm btn-primary variable-btn">فیلد ۴</button>
                            </div>
                            <?php if ($edit_draft): ?>
                                <input type="hidden" name="draft_id" value="<?php echo $edit_draft['id']; ?>">
                                <button type="submit" name="update_draft" class="btn btn-primary">به‌روزرسانی</button>
                                <a href="draft_list.php?group_id=<?php echo $group_id; ?>" class="btn btn-secondary">لغو</a>
                            <?php else: ?>
                                <button type="submit" name="create_draft" class="btn btn-primary">ذخیره پیش‌نویس</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <!-- Drafts List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست پیش‌نویس‌ها</h3>
                        <div class="card-tools">
                            <form method="GET" class="form-inline">
                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                <div class="input-group input-group-sm mr-2">
                                    <input type="text" name="search_title" class="form-control" placeholder="جستجو بر اساس عنوان..." value="<?php echo htmlspecialchars($search_title); ?>">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search_message" class="form-control" placeholder="جستجو بر اساس متن..." value="<?php echo htmlspecialchars($search_message); ?>">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>شناسه</th>
                                    <th>عنوان</th>
                                    <th>متن پیام</th>
                                    <th>نوع</th>
                                    <th>وضعیت</th>
                                    <th>تاریخ ایجاد</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($drafts)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">هیچ پیش‌نویسی یافت نشد.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($drafts as $index => $draft): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo $draft['id']; ?></td>
                                            <td><?php echo htmlspecialchars($draft['title']); ?></td>
                                            <td><?php echo htmlspecialchars($draft['message']); ?></td>
                                            <td><?php echo $draft['type'] == 'simple' ? 'ساده' : 'هوشمند'; ?></td>
                                            <td>
                                                <?php
                                                if ($draft['status'] == 'pending') echo 'در انتظار تأیید';
                                                elseif ($draft['status'] == 'approved') echo 'تأیید شده';
                                                else echo 'رد شده';
                                                ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($draft['created_at'])); ?></td>
                                            <td>
                                                <a href="draft_list.php?group_id=<?php echo $group_id; ?>&edit=<?php echo $draft['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                                <a href="draft_list.php?group_id=<?php echo $group_id; ?>&delete=<?php echo $draft['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این پیش‌نویس را حذف کنید؟');">حذف</a>
                                                <?php if ($draft['status'] == 'approved'): ?>
                                                    <a href="send_single.php?draft_id=<?php echo $draft['id']; ?>" class="btn btn-sm btn-info">ارسال</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
        $('#draft-type').change(function() {
            var type = $(this).val();
            if (type === 'simple') {
                $('#variable-section').addClass('hidden');
            } else {
                $('#variable-section').removeClass('hidden');
            }
        });

        $('.variable-btn').click(function(e) {
            e.preventDefault();
            var variable = $(this).val();
            $('#message').val($('#message').val() + variable);
        });
    });
</script>
</body>
</html>