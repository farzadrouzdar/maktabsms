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
$stmt = $pdo->prepare("SELECT * FROM contact_groups WHERE id = ? AND school_id = ?");
$stmt->execute([$group_id, $school_id]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: phonebook.php');
    exit;
}

// Handle contact creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_contact'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $field_1 = !empty($_POST['field_1']) ? filter_var($_POST['field_1'], FILTER_SANITIZE_STRING) : null;
    $field_2 = !empty($_POST['field_2']) ? filter_var($_POST['field_2'], FILTER_SANITIZE_STRING) : null;
    $field_3 = !empty($_POST['field_3']) ? filter_var($_POST['field_3'], FILTER_SANITIZE_STRING) : null;
    $field_4 = !empty($_POST['field_4']) ? filter_var($_POST['field_4'], FILTER_SANITIZE_STRING) : null;

    if (preg_match('/^09[0-9]{9}$/', $mobile)) {
        $stmt = $pdo->prepare("INSERT INTO contacts (school_id, group_id, name, mobile, birth_date, field_1, field_2, field_3, field_4) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$school_id, $group_id, $name, $mobile, $birth_date, $field_1, $field_2, $field_3, $field_4]);
        $success = "مخاطب با موفقیت اضافه شد.";
    } else {
        $error = "شماره موبایل نامعتبر است.";
    }
}

// Handle contact update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    $contact_id = filter_var($_POST['contact_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $field_1 = !empty($_POST['field_1']) ? filter_var($_POST['field_1'], FILTER_SANITIZE_STRING) : null;
    $field_2 = !empty($_POST['field_2']) ? filter_var($_POST['field_2'], FILTER_SANITIZE_STRING) : null;
    $field_3 = !empty($_POST['field_3']) ? filter_var($_POST['field_3'], FILTER_SANITIZE_STRING) : null;
    $field_4 = !empty($_POST['field_4']) ? filter_var($_POST['field_4'], FILTER_SANITIZE_STRING) : null;

    if (preg_match('/^09[0-9]{9}$/', $mobile)) {
        $stmt = $pdo->prepare("UPDATE contacts SET name = ?, mobile = ?, birth_date = ?, field_1 = ?, field_2 = ?, field_3 = ?, field_4 = ? WHERE id = ? AND school_id = ? AND group_id = ?");
        $stmt->execute([$name, $mobile, $birth_date, $field_1, $field_2, $field_3, $field_4, $contact_id, $school_id, $group_id]);
        $success = "مخاطب با موفقیت به‌روزرسانی شد.";
    } else {
        $error = "شماره موبایل نامعتبر است.";
    }
}

// Handle contact deletion
if (isset($_GET['delete_contact'])) {
    $contact_id = filter_var($_GET['delete_contact'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND school_id = ? AND group_id = ?");
    $stmt->execute([$contact_id, $school_id, $group_id]);
    $success = "مخاطب با موفقیت حذف شد.";
    header("Location: contacts.php?group_id=$group_id");
    exit;
}

// Fetch all contacts with search functionality
$search_name = isset($_GET['search_name']) ? filter_var($_GET['search_name'], FILTER_SANITIZE_STRING) : '';
$search_mobile = isset($_GET['search_mobile']) ? filter_var($_GET['search_mobile'], FILTER_SANITIZE_STRING) : '';

$query = "SELECT * FROM contacts WHERE school_id = ? AND group_id = ?";
$params = [$school_id, $group_id];

if (!empty($search_name)) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search_name%";
}
if (!empty($search_mobile)) {
    $query .= " AND mobile LIKE ?";
    $params[] = "%$search_mobile%";
}
$query .= " ORDER BY name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$contacts = $stmt->fetchAll();

// Fetch contact for editing
$edit_contact = null;
if (isset($_GET['edit_contact'])) {
    $contact_id = filter_var($_GET['edit_contact'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ? AND school_id = ? AND group_id = ?");
    $stmt->execute([$contact_id, $school_id, $group_id]);
    $edit_contact = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مخاطبین گروه <?php echo htmlspecialchars($group['group_name']); ?> - سامانه پیامک مدارس</title>
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
                        <a href="phonebook.php" class="nav-link active">
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
                <h1>مخاطبین گروه <?php echo htmlspecialchars($group['group_name']); ?></h1>
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
                <!-- Contact Management -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $edit_contact ? 'ویرایش مخاطب' : 'اضافه کردن مخاطب جدید'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label for="name">نام و نام خانوادگی</label>
                                <input type="text" class="form-control" name="name" value="<?php echo $edit_contact ? htmlspecialchars($edit_contact['name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="mobile">شماره موبایل (الزامی)</label>
                                <input type="text" class="form-control" name="mobile" value="<?php echo $edit_contact ? htmlspecialchars($edit_contact['mobile']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="birth_date">تاریخ تولد</label>
                                <input type="date" class="form-control" name="birth_date" value="<?php echo $edit_contact && $edit_contact['birth_date'] ? $edit_contact['birth_date'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="field_1">فیلد ۱</label>
                                <input type="text" class="form-control" name="field_1" value="<?php echo $edit_contact && $edit_contact['field_1'] ? htmlspecialchars($edit_contact['field_1']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="field_2">فیلد ۲</label>
                                <input type="text" class="form-control" name="field_2" value="<?php echo $edit_contact && $edit_contact['field_2'] ? htmlspecialchars($edit_contact['field_2']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="field_3">فیلد ۳</label>
                                <input type="text" class="form-control" name="field_3" value="<?php echo $edit_contact && $edit_contact['field_3'] ? htmlspecialchars($edit_contact['field_3']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="field_4">فیلد ۴</label>
                                <input type="text" class="form-control" name="field_4" value="<?php echo $edit_contact && $edit_contact['field_4'] ? htmlspecialchars($edit_contact['field_4']) : ''; ?>">
                            </div>
                            <?php if ($edit_contact): ?>
                                <input type="hidden" name="contact_id" value="<?php echo $edit_contact['id']; ?>">
                                <button type="submit" name="update_contact" class="btn btn-primary">به‌روزرسانی</button>
                                <a href="contacts.php?group_id=<?php echo $group_id; ?>" class="btn btn-secondary">لغو</a>
                            <?php else: ?>
                                <button type="submit" name="create_contact" class="btn btn-primary">اضافه کردن</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <!-- Contacts List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">لیست مخاطبین</h3>
                        <div class="card-tools">
                            <form method="GET" class="form-inline">
                                <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                                <div class="input-group input-group-sm mr-2">
                                    <input type="text" name="search_name" class="form-control" placeholder="جستجو بر اساس نام..." value="<?php echo htmlspecialchars($search_name); ?>">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="search_mobile" class="form-control" placeholder="جستجو بر اساس موبایل..." value="<?php echo htmlspecialchars($search_mobile); ?>">
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
                                    <th>نام و نام خانوادگی</th>
                                    <th>موبایل</th>
                                    <th>تاریخ تولد</th>
                                    <th>فیلد ۱</th>
                                    <th>فیلد ۲</th>
                                    <th>فیلد ۳</th>
                                    <th>فیلد ۴</th>
                                    <th>تاریخ اضافه شدن</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contacts)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">هیچ مخاطبی یافت نشد.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $index => $contact): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                            <td><?php echo htmlspecialchars($contact['mobile']); ?></td>
                                            <td><?php echo $contact['birth_date'] ? $contact['birth_date'] : '-'; ?></td>
                                            <td><?php echo $contact['field_1'] ? htmlspecialchars($contact['field_1']) : '-'; ?></td>
                                            <td><?php echo $contact['field_2'] ? htmlspecialchars($contact['field_2']) : '-'; ?></td>
                                            <td><?php echo $contact['field_3'] ? htmlspecialchars($contact['field_3']) : '-'; ?></td>
                                            <td><?php echo $contact['field_4'] ? htmlspecialchars($contact['field_4']) : '-'; ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($contact['created_at'])); ?></td>
                                            <td>
                                                <a href="contacts.php?group_id=<?php echo $group_id; ?>&edit_contact=<?php echo $contact['id']; ?>" class="btn btn-sm btn-warning">ویرایش</a>
                                                <a href="contacts.php?group_id=<?php echo $group_id; ?>&delete_contact=<?php echo $contact['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این مخاطب را حذف کنید؟');">حذف</a>
                                                <a href="send_single.php?mobile=<?php echo $contact['mobile']; ?>" class="btn btn-sm btn-info">ارسال پیام</a>
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