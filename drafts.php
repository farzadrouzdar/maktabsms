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

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
    if (!empty($group_name)) {
        $stmt = $pdo->prepare("INSERT INTO draft_groups (school_id, group_name) VALUES (?, ?)");
        $stmt->execute([$school_id, $group_name]);
        $success = "گروه با موفقیت ایجاد شد.";
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
    } else {
        $error = "نام گروه نمی‌تواند خالی باشد.";
    }
}

// Handle group deletion
if (isset($_GET['delete_group'])) {
    $group_id = filter_var($_GET['delete_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $success = "گروه با موفقیت حذف شد.";
    header('Location: drafts.php');
    exit;
}

// Fetch all groups with draft count
$stmt = $pdo->prepare("
    SELECT dg.*, COUNT(d.id) as draft_count 
    FROM draft_groups dg 
    LEFT JOIN drafts d ON dg.id = d.group_id 
    WHERE dg.school_id = ? 
    GROUP BY dg.id 
    ORDER BY dg.group_name
");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// Fetch group for editing
$edit_group = null;
if (isset($_GET['edit_group'])) {
    $group_id = filter_var($_GET['edit_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $edit_group = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نویس‌ها - سامانه پیامک مدارس</title>
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
                        <a href="drafts.php" class="nav-link active">
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
                        <a href="send_smart.php" class="nav-link">
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
                <h1>پیش‌نویس‌ها</h1>
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
                <!-- Group Management -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $edit_group ? 'ویرایش گروه' : 'ایجاد گروه جدید'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="group_name">نام گروه</label>
                                <input type="text" class="form-control" name="group_name" value="<?php echo $edit_group ? htmlspecialchars($edit_group['group_name']) : ''; ?>" required>
                            </div>
                            <?php if ($edit_group): ?>
                                <input type="hidden" name="group_id" value="<?php echo $edit_group['id']; ?>">
                                <button type="submit" name="update_group" class="btn btn-primary">به‌روزرسانی</button>
                                <a href="drafts.php" class="btn btn-secondary">لغو</a>
                            <?php else: ?>
                                <button type="submit" name="create_group" class="btn btn-primary">ایجاد گروه</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <!-- Groups List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست گروه‌ها</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
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
                                            <td><a href="draft_list.php?group_id=<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></a></td>
                                            <td><?php echo $group['draft_count']; ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($group['created_at'])); ?></td>
                                            <td>
                                                <a href="drafts.php?edit_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                                <a href="drafts.php?delete_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این گروه را حذف کنید؟');">حذف</a>
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
</body>
</html>