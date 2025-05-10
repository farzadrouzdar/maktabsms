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
    $success = "گروه با موفقیت حذف شد.";
    header('Location: drafts.php');
    exit;
}

// Fetch group for editing
$edit_group = null;
if (isset($_GET['edit_group'])) {
    $group_id = filter_var($_GET['edit_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM draft_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $edit_group = $stmt->fetch();
    if (!$edit_group) {
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
        header('Location: drafts.php');
        exit;
    }
}

// Handle draft deletion
if (isset($_GET['delete_draft'])) {
    $draft_id = filter_var($_GET['delete_draft'], FILTER_SANITIZE_NUMBER_INT);
    $stmt_delete_draft = $pdo->prepare("DELETE FROM drafts WHERE id = ? AND school_id = ?");
    $stmt_delete_draft->execute([$draft_id, $school_id]);
    $success = "پیش‌نویس با موفقیت حذف شد.";
    header('Location: drafts.php');
    exit;
}

// Handle draft update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_draft'])) {
    $draft_id_update = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);

    if (!empty($title) && !empty($message) && in_array($type, ['simple', 'smart'])) {
        $stmt_update_draft = $pdo->prepare("
            UPDATE drafts
            SET title = ?, message = ?, type = ?, updated_at = NOW()
            WHERE id = ? AND school_id = ?
        ");
        $stmt_update_draft->execute([$title, $message, $type, $draft_id_update, $school_id]);
        $success = "پیش‌نویس با موفقیت به‌روزرسانی شد.";
        header('Location: drafts.php');
        exit;
    } else {
        $error = "لطفاً تمام فیلدها را به درستی وارد کنید.";
    }
}

// Fetch all draft groups
$stmt_groups = $pdo->prepare("SELECT * FROM draft_groups WHERE school_id = ? ORDER BY created_at DESC");
$stmt_groups->execute([$school_id]);
$groups = $stmt_groups->fetchAll();

// تنظیم عنوان صفحه
$page_title = "پیش‌نویس‌ها - سامانه پیامک مدارس";

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
                <h1>پیش‌نویس‌ها</h1>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <div id="message-container">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                                <button type="submit" name="create_group" class="btn btn-primary">ایجاد گروه</button>
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
                                        <th>تاریخ ایجاد</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($groups)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">هیچ گروهی یافت نشد.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($groups as $index => $group): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($group['created_at'])); ?></td>
                                                <td>
                                                    <a href="?edit_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                                    <a href="?delete_group=<?php echo $group['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این گروه را حذف کنید؟');">حذف</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                                <button type="submit" name="update_group" class="btn btn-primary">به‌روزرسانی</button>
                                <a href="drafts.php" class="btn btn-secondary">لغو</a>
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
                            <form method="POST">
                                <div class="form-group">
                                    <label for="title">عنوان</label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($edit_draft['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="message">پیام</label>
                                    <textarea class="form-control" name="message" rows="5" required><?php echo htmlspecialchars($edit_draft['message']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="type">نوع پیش‌نویس</label>
                                    <select class="form-control" name="type" required>
                                        <option value="simple" <?php if ($edit_draft['type'] === 'simple') echo 'selected'; ?>>ساده</option>
                                        <option value="smart" <?php if ($edit_draft['type'] === 'smart') echo 'selected'; ?>>هوشمند</option>
                                    </select>
                                </div>
                                <input type="hidden" name="draft_id" value="<?php echo $edit_draft['id']; ?>">
                                <button type="submit" name="update_draft" class="btn btn-primary">ذخیره تغییرات</button>
                                <a href="drafts.php" class="btn btn-secondary">لغو</a>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست پیش‌نویس‌های تایید شده</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
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
                                <?php
                                $stmt_drafts = $pdo->prepare("
                                    SELECT
                                        id,
                                        title,
                                        message,
                                        created_at,
                                        CASE type
                                            WHEN 'simple' THEN 'ساده'
                                            WHEN 'smart' THEN 'هوشمند'
                                            ELSE '-'
                                        END AS type_fa
                                    FROM drafts
                                    WHERE school_id = ? AND status = 'approved'
                                    ORDER BY created_at DESC
                                ");
                                $stmt_drafts->execute([$school_id]);
                                $drafts = $stmt_drafts->fetchAll();

                                if (empty($drafts)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">هیچ پیش‌نویس تایید شده‌ای یافت نشد.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($drafts as $index => $draft): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($draft['title']); ?></td>
                                            <td><?php echo htmlspecialchars($draft['type_fa']); ?></td>
                                            <td><?php echo htmlspecialchars(mb_substr($draft['message'], 0, 50, 'UTF-8')) . '...'; ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($draft['created_at'])); ?></td>
                                            <td>
                                                <a href="?edit_draft=<?php echo $draft['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                                <a href="?delete_draft=<?php echo $draft['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این پیش‌نویس را حذف کنید؟');">حذف</a>
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
    });
</script>
</body>
</html>