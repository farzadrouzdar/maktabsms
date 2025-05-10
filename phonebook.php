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

// Handle adding a new group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
    if (!empty($group_name)) {
        $stmt = $pdo->prepare("INSERT INTO contact_groups (school_id, group_name) VALUES (?, ?)");
        $stmt->execute([$school_id, $group_name]);
        $success = "گروه با موفقیت اضافه شد.";
        header('Location: phonebook.php');
        exit;
    } else {
        $error = "نام گروه نمی‌تواند خالی باشد.";
    }
}

// Handle adding a new contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $birth_date = filter_var($_POST['birth_date'], FILTER_SANITIZE_STRING);
    $field1 = filter_var($_POST['field1'], FILTER_SANITIZE_STRING);
    $field2 = filter_var($_POST['field2'], FILTER_SANITIZE_STRING);
    $field3 = filter_var($_POST['field3'], FILTER_SANITIZE_STRING);
    $field4 = filter_var($_POST['field4'], FILTER_SANITIZE_STRING);

    // اعتبارسنجی ساده شماره موبایل
    if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $error = "شماره موبایل باید با 09 شروع شود و 11 رقم باشد.";
    } elseif (!empty($group_id)) {
        $stmt = $pdo->prepare("INSERT INTO contacts (school_id, group_id, name, mobile, birth_date, field1, field2, field3, field4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$school_id, $group_id, $name, $mobile, $birth_date, $field1, $field2, $field3, $field4]);
        $success = "مخاطب با موفقیت اضافه شد.";
        header('Location: phonebook.php?group=' . $group_id);
        exit;
    } else {
        $error = "شماره موبایل و گروه نمی‌توانند خالی باشند.";
    }
}

// Handle editing a contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_contact'])) {
    $contact_id = filter_var($_POST['contact_id'], FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_var($_POST['group_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $birth_date = filter_var($_POST['birth_date'], FILTER_SANITIZE_STRING);
    $field1 = filter_var($_POST['field1'], FILTER_SANITIZE_STRING);
    $field2 = filter_var($_POST['field2'], FILTER_SANITIZE_STRING);
    $field3 = filter_var($_POST['field3'], FILTER_SANITIZE_STRING);
    $field4 = filter_var($_POST['field4'], FILTER_SANITIZE_STRING);

    // اعتبارسنجی ساده شماره موبایل
    if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
        $error = "شماره موبایل باید با 09 شروع شود و 11 رقم باشد.";
    } elseif (!empty($contact_id) && !empty($group_id)) {
        $stmt = $pdo->prepare("UPDATE contacts SET group_id = ?, name = ?, mobile = ?, birth_date = ?, field1 = ?, field2 = ?, field3 = ?, field4 = ? WHERE id = ? AND school_id = ?");
        $stmt->execute([$group_id, $name, $mobile, $birth_date, $field1, $field2, $field3, $field4, $contact_id, $school_id]);
        $success = "مخاطب با موفقیت ویرایش شد.";
        header('Location: phonebook.php?group=' . $group_id);
        exit;
    } else {
        $error = "شماره موبایل و گروه نمی‌توانند خالی باشند.";
    }
}

// Handle deleting a group or contact
if (isset($_GET['delete_group'])) {
    $group_id = filter_var($_GET['delete_group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM contact_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE group_id = ? AND school_id = ?");
    $stmt->execute([$group_id, $school_id]);
    $success = "گروه و مخاطبین آن با موفقیت حذف شدند.";
    header('Location: phonebook.php');
    exit;
}

if (isset($_GET['delete_contact'])) {
    $contact_id = filter_var($_GET['delete_contact'], FILTER_SANITIZE_NUMBER_INT);
    $group_id = filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND school_id = ?");
    $stmt->execute([$contact_id, $school_id]);
    $success = "مخاطب با موفقیت حذف شد.";
    header('Location: phonebook.php?group=' . $group_id);
    exit;
}

// Fetch all contact groups with contact counts
$stmt = $pdo->prepare("SELECT cg.*, COUNT(c.id) as contact_count FROM contact_groups cg LEFT JOIN contacts c ON cg.id = c.group_id AND cg.school_id = c.school_id WHERE cg.school_id = ? GROUP BY cg.id ORDER BY cg.created_at DESC");
$stmt->execute([$school_id]);
$groups = $stmt->fetchAll();

// Handle group selection or search
$selected_group = isset($_GET['group']) ? filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT) : null;
$search_query = isset($_GET['search']) ? filter_var($_GET['search'], FILTER_SANITIZE_STRING) : '';

$contacts = [];
if ($selected_group) {
    // چک کردن وجود گروه
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_groups WHERE id = ? AND school_id = ?");
    $stmt->execute([$selected_group, $school_id]);
    if ($stmt->fetchColumn() == 0) {
        header('Location: phonebook.php');
        exit;
    }

    $query = "SELECT c.*, cg.group_name FROM contacts c JOIN contact_groups cg ON c.group_id = cg.id WHERE c.school_id = ? AND c.group_id = ?";
    $params = [$school_id, $selected_group];
    if (!empty($search_query)) {
        $query .= " AND (c.name LIKE ? OR c.mobile LIKE ?)";
        $search_term = "%$search_query%";
        $params = array_merge($params, [$search_term, $search_term]);
    }
    $query .= " ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll();
}

// تنظیم عنوان صفحه
$page_title = "دفترچه تلفن - سامانه پیامک مدارس";

// لود فایل header.php
require_once 'header.php';
?>

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
                <h1>دفترچه تلفن</h1>
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
                <!-- Add Group Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">اضافه کردن گروه جدید</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="group_name">نام گروه</label>
                                <input type="text" class="form-control" name="group_name" required>
                            </div>
                            <button type="submit" name="add_group" class="btn btn-primary">اضافه کردن گروه</button>
                        </form>
                    </div>
                </div>
                <!-- Groups List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست گروه‌ها</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($groups)): ?>
                            <div class="alert alert-info">هیچ گروهی یافت نشد.</div>
                        <?php else: ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>نام گروه</th>
                                        <th>تعداد مخاطبین</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><a href="?group=<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></a></td>
                                            <td><?php echo $group['contact_count']; ?></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editGroupModal<?php echo $group['id']; ?>">ویرایش</button>
                                                <a href="phonebook.php?delete_group=<?php echo $group['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این گروه و مخاطبین آن را حذف کنید؟');">حذف</a>
                                            </td>
                                        </tr>
                                        <!-- Edit Group Modal -->
                                        <div class="modal fade" id="editGroupModal<?php echo $group['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editGroupModalLabel" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editGroupModalLabel">ویرایش گروه</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form method="POST">
                                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                            <div class="form-group">
                                                                <label for="group_name">نام گروه</label>
                                                                <input type="text" class="form-control" name="group_name" value="<?php echo htmlspecialchars($group['group_name']); ?>" required>
                                                            </div>
                                                            <button type="submit" name="edit_group" class="btn btn-primary">ذخیره تغییرات</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Contacts List (if group selected) -->
                <?php if ($selected_group): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">مخاطبین گروه <?php echo htmlspecialchars($groups[array_search($selected_group, array_column($groups, 'id'))]['group_name'] ?? ''); ?></h3>
                            <div class="card-tools">
                                <form method="GET">
                                    <input type="hidden" name="group" value="<?php echo $selected_group; ?>">
                                    <div class="input-group input-group-sm" style="width: 150px;">
                                        <input type="text" name="search" class="form-control float-right" placeholder="جستجو..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-default">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Add Contact Form -->
                            <form method="POST">
                                <input type="hidden" name="group_id" value="<?php echo $selected_group; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="name">نام</label>
                                            <input type="text" class="form-control" name="name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="mobile">شماره موبایل</label>
                                            <input type="text" class="form-control" name="mobile" required placeholder="09xxxxxxxxx">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="birth_date">تاریخ تولد</label>
                                            <input type="date" class="form-control" name="birth_date">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="field1">فیلد ۱</label>
                                            <input type="text" class="form-control" name="field1">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="field2">فیلد ۲</label>
                                            <input type="text" class="form-control" name="field2">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="field3">فیلد ۳</label>
                                            <input type="text" class="form-control" name="field3">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="field4">فیلد ۴</label>
                                            <input type="text" class="form-control" name="field4">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <button type="submit" name="add_contact" class="btn btn-primary">اضافه کردن مخاطب</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <?php if (empty($contacts)): ?>
                                <div class="alert alert-info mt-3">هیچ مخاطبی یافت نشد.</div>
                            <?php else: ?>
                                <table class="table table-bordered table-striped mt-3">
                                    <thead>
                                        <tr>
                                            <th>نام</th>
                                            <th>شماره موبایل</th>
                                            <th>تاریخ تولد</th>
                                            <th>فیلد ۱</th>
                                            <th>فیلد ۲</th>
                                            <th>فیلد ۳</th>
                                            <th>فیلد ۴</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($contact['name']) ?: '-'; ?></td>
                                                <td><?php echo htmlspecialchars($contact['mobile']); ?></td>
                                                <td><?php echo htmlspecialchars($contact['birth_date']) ?: '-'; ?></td>
                                                <td><?php echo htmlspecialchars($contact['field1']) ?: '-'; ?></td>
                                                <td><?php echo htmlspecialchars($contact['field2']) ?: '-'; ?></td>
                                                <td><?php echo htmlspecialchars($contact['field3']) ?: '-'; ?></td>
                                                <td><?php echo htmlspecialchars($contact['field4']) ?: '-'; ?></td>
                                                <td>
                                                    <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?php echo $contact['id']; ?>">ویرایش</button>
                                                    <a href="phonebook.php?delete_contact=<?php echo $contact['id']; ?>&group=<?php echo $selected_group; ?>" class="btn btn-danger btn-sm" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این مخاطب را حذف کنید؟');">حذف</a>
                                                </td>
                                            </tr>
                                            <!-- Edit Modal -->
                                            <div class="modal fade" id="editModal<?php echo $contact['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editModalLabel">ویرایش مخاطب</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="POST">
                                                                <input type="hidden" name="contact_id" value="<?php echo $contact['id']; ?>">
                                                                <input type="hidden" name="group_id" value="<?php echo $contact['group_id']; ?>">
                                                                <div class="form-group">
                                                                    <label for="name">نام</label>
                                                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($contact['name']); ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="mobile">شماره موبایل</label>
                                                                    <input type="text" class="form-control" name="mobile" value="<?php echo htmlspecialchars($contact['mobile']); ?>" required placeholder="09xxxxxxxxx">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="birth_date">تاریخ تولد</label>
                                                                    <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($contact['birth_date']); ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="field1">فیلد ۱</label>
                                                                    <input type="text" class="form-control" name="field1" value="<?php echo htmlspecialchars($contact['field1']); ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="field2">فیلد ۲</label>
                                                                    <input type="text" class="form-control" name="field2" value="<?php echo htmlspecialchars($contact['field2']); ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="field3">فیلد ۳</label>
                                                                    <input type="text" class="form-control" name="field3" value="<?php echo htmlspecialchars($contact['field3']); ?>">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="field4">فیلد ۴</label>
                                                                    <input type="text" class="form-control" name="field4" value="<?php echo htmlspecialchars($contact['field4']); ?>">
                                                                </div>
                                                                <button type="submit" name="edit_contact" class="btn btn-primary">ذخیره تغییرات</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <!-- Footer -->
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