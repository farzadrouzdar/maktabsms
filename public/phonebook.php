<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// چک کردن لاگین
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// داده‌های فرضی
$groups = isset($_SESSION['groups']) ? $_SESSION['groups'] : [
    1 => ["id" => 1, "name" => "دانش‌آموزان"],
    2 => ["id" => 2, "name" => "اولیا"],
    3 => ["id" => 3, "name" => "معلمان"]
];

$contacts = isset($_SESSION['contacts']) ? $_SESSION['contacts'] : [
    ["id" => 1, "name" => "علی", "family" => "احمدی", "mobile" => "09120000000", "birthdate" => "1370/01/01", "field1" => "کد ملی: 1234567890", "field2" => "", "field3" => "", "field4" => "", "group_id" => 1],
    ["id" => 2, "name" => "مریم", "family" => "حسینی", "mobile" => "09130000000", "birthdate" => "", "field1" => "", "field2" => "", "field3" => "", "field4" => "", "group_id" => 2],
    ["id" => 3, "name" => "رضا", "family" => "محمدی", "mobile" => "09140000000", "birthdate" => "1365/05/20", "field1" => "آدرس: تهران", "field2" => "", "field3" => "", "field4" => "", "group_id" => 3]
];

// اضافه کردن گروه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $new_id = max(array_keys($groups)) + 1;
    $groups[$new_id] = ["id" => $new_id, "name" => $_POST['group_name']];
    $_SESSION['groups'] = $groups;
    $_SESSION['success'] = 'گروه با موفقیت اضافه شد!';
    header("Location: phonebook.php");
    exit;
}

// حذف گروه
if (isset($_GET['delete_group']) && isset($groups[(int)$_GET['delete_group']])) {
    $group_id = (int)$_GET['delete_group'];
    unset($groups[$group_id]);
    $contacts = array_filter($contacts, fn($c) => $c['group_id'] != $group_id);
    $_SESSION['groups'] = $groups;
    $_SESSION['contacts'] = $contacts;
    $_SESSION['success'] = 'گروه با موفقیت حذف شد!';
    header("Location: phonebook.php");
    exit;
}

// ویرایش گروه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_group_id'])) {
    $group_id = (int)$_POST['edit_group_id'];
    if (isset($groups[$group_id])) {
        $groups[$group_id]['name'] = $_POST['edit_group_name'];
        $_SESSION['groups'] = $groups;
        $_SESSION['success'] = 'گروه با موفقیت ویرایش شد!';
        header("Location: phonebook.php");
        exit;
    }
}

// اضافه کردن مخاطب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact']) && isset($_POST['group_id'])) {
    $group_id = (int)$_POST['group_id'];
    $new_id = max(array_column($contacts, 'id')) + 1;
    $contacts[] = [
        'id' => $new_id,
        'name' => $_POST['contact_name'] ?? '',
        'family' => $_POST['contact_family'] ?? '',
        'mobile' => $_POST['contact_mobile'],
        'birthdate' => $_POST['contact_birthdate'] ?? '',
        'field1' => $_POST['contact_field1'] ?? '',
        'field2' => $_POST['contact_field2'] ?? '',
        'field3' => $_POST['contact_field3'] ?? '',
        'field4' => $_POST['contact_field4'] ?? '',
        'group_id' => $group_id
    ];
    $_SESSION['contacts'] = $contacts;
    $_SESSION['success'] = 'مخاطب با موفقیت اضافه شد!';
    header("Location: phonebook.php");
    exit;
}

// ویرایش مخاطب
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_contact_id'])) {
    $contact_id = (int)$_POST['edit_contact_id'];
    foreach ($contacts as &$contact) {
        if ($contact['id'] == $contact_id) {
            $contact['name'] = $_POST['contact_name'] ?? '';
            $contact['family'] = $_POST['contact_family'] ?? '';
            $contact['mobile'] = $_POST['contact_mobile'];
            $contact['birthdate'] = $_POST['contact_birthdate'] ?? '';
            $contact['field1'] = $_POST['contact_field1'] ?? '';
            $contact['field2'] = $_POST['contact_field2'] ?? '';
            $contact['field3'] = $_POST['contact_field3'] ?? '';
            $contact['field4'] = $_POST['contact_field4'] ?? '';
            break;
        }
    }
    $_SESSION['contacts'] = $contacts;
    $_SESSION['success'] = 'مخاطب با موفقیت ویرایش شد!';
    header("Location: phonebook.php");
    exit;
}

// حذف مخاطب
if (isset($_GET['delete_contact'])) {
    $contact_id = (int)$_GET['delete_contact'];
    $contacts = array_filter($contacts, fn($c) => $c['id'] != $contact_id);
    $_SESSION['contacts'] = $contacts;
    $_SESSION['success'] = 'مخاطب با موفقیت حذف شد!';
    header("Location: phonebook.php");
    exit;
}

// سرچ
$search_group = isset($_GET['search_group']) ? (int)$_GET['search_group'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_mobile = isset($_GET['search_mobile']) ? trim($_GET['search_mobile']) : '';

$filtered_contacts = $contacts;
if ($search_group) {
    $filtered_contacts = array_filter($filtered_contacts, fn($c) => $c['group_id'] == $search_group);
}
if ($search_name) {
    $filtered_contacts = array_filter($filtered_contacts, fn($c) => stripos($c['name'], $search_name) !== false || stripos($c['family'], $search_name) !== false);
}
if ($search_mobile) {
    $filtered_contacts = array_filter($filtered_contacts, fn($c) => stripos($c['mobile'], $search_mobile) !== false);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفترچه تلفن - مکتب اس‌ام‌اس</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/vazir@4.5.0/index.css" rel="stylesheet">
    <link rel="stylesheet" href="/maktabsms/public/assets/css/custom.css">
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
</head>
<body class="bg-gray-900 min-h-screen flex">
    <div id="particles-js" class="absolute inset-0 z-0"></div>
    
    <aside class="w-64 bg-gray-800 bg-opacity-90 h-screen sticky top-0 z-20 transform transition-transform duration-300 md:translate-x-0 translate-x-full" id="sidebar">
        <div class="p-4 flex items-center justify-center">
            <img src="/maktabsms/public/assets/dist/img/logo.png" alt="مکتب اس‌ام‌اس" class="w-16 animate__animated animate__pulse animate__infinite" style="filter: drop-shadow(0 0 10px #00f0ff);">
            <h1 class="text-xl font-bold text-white mr-2">مکتب اس‌ام‌اس</h1>
        </div>
        <nav class="mt-6">
            <ul>
                <li>
                    <a href="phonebook.php" class="flex items-center p-4 text-white neon-menu-item bg-gradient-to-l from-blue-500 to-pink-500">
                        <svg class="w-6 h-6 mr-2 animate__animated animate__pulse animate__infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a2 2 0 00-2-2h-3m-3 4h-5v-2a2 2 0 012-2h3m-3-4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                        دفترچه تلفن
                    </a>
                </li>
                <li>
                    <a href="send_sms.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-blue-500 hover:to-pink-500">
                        <svg class="w-6 h-6 mr-2 animate__animated animate__pulse animate__infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        ارسال پیامک
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-red-500 hover:to-red-700">
                        <svg class="w-6 h-6 mr-2 animate__animated animate__pulse animate__infinite" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        خروج
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <button class="md:hidden fixed top-4 right-4 z-30 text-white bg-gradient-to-r from-blue-500 to-pink-500 p-2 rounded-lg neon-button" onclick="toggleSidebar()">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
    </button>

    <div class="relative z-10 flex-1">
        <header class="bg-gray-800 bg-opacity-80 p-4 shadow-xl">
            <div class="container mx-auto flex items-center justify-between">
                <div class="flex items-center">
                    <img src="/maktabsms/public/assets/dist/img/logo.png" alt="مکتب اس‌ام‌اس" class="w-16 animate__animated animate__pulse animate__infinite" style="filter: drop-shadow(0 0 10px #00f0ff);">
                    <h1 class="text-2xl font-bold text-white mr-4">مکتب اس‌ام‌اس</h1>
                </div>
                <a href="logout.php" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-lg neon-button hidden md:block">خروج</a>
            </div>
        </header>

        <div class="container mx-auto p-6">
            <!-- فرم جستجو -->
            <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl mb-8 animate__animated animate__slideInDown">
                <h2 class="text-xl font-bold text-white mb-4">جستجو</h2>
                <form method="GET" action="">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="search_group" class="block text-white mb-2">گروه</label>
                            <select name="search_group" id="search_group" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                <option value="0">همه گروه‌ها</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo $search_group == $group['id'] ? 'selected' : ''; ?>><?php echo $group['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="search_name" class="block text-white mb-2">نام و نام خانوادگی</label>
                            <input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_name); ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                        </div>
                        <div>
                            <label for="search_mobile" class="block text-white mb-2">موبایل</label>
                            <input type="text" name="search_mobile" id="search_mobile" value="<?php echo htmlspecialchars($search_mobile); ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg">جستجو</button>
                </form>
            </div>

            <!-- لیست گروه‌ها -->
            <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl mb-8 animate__animated animate__slideInDown">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-white">دفترچه تلفن</h2>
                    <button type="button" class="bg-gradient-to-r from-blue-500 to-pink-500 text-white p-2 rounded-lg neon-button" data-bs-toggle="modal" data-bs-target="#addGroupModal">اضافه کردن گروه</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-white">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-500 to-pink-500">
                                <th class="p-3">نام گروه</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <tr class="border-b border-gray-700 hover:bg-gray-700 neon-row transition-all duration-300">
                                    <td class="p-3"><?php echo $group['name']; ?></td>
                                    <td class="p-3">
                                        <button type="button" class="text-blue-400 hover:text-blue-300 mr-2" data-bs-toggle="modal" data-bs-target="#editGroupModal<?php echo $group['id']; ?>">ویرایش</button>
                                        <a href="?delete_group=<?php echo $group['id']; ?>" class="text-red-400 hover:text-red-300 mr-2" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                                        <button type="button" class="text-green-400 hover:text-green-300" data-bs-toggle="modal" data-bs-target="#addContactModal<?php echo $group['id']; ?>">افزودن مخاطب</button>
                                    </td>
                                </tr>
                                <!-- مودال ویرایش گروه -->
                                <div class="modal fade" id="editGroupModal<?php echo $group['id']; ?>" tabindex="-1" aria-labelledby="editGroupModalLabel<?php echo $group['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-gray-800 text-white">
                                            <div class="modal-header bg-gradient-to-r from-blue-500 to-pink-500">
                                                <h5 class="modal-title" id="editGroupModalLabel<?php echo $group['id']; ?>">ویرایش گروه</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="edit_group_id" value="<?php echo $group['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="edit_group_name_<?php echo $group['id']; ?>" class="form-label">نام گروه</label>
                                                        <input type="text" name="edit_group_name" id="edit_group_name_<?php echo $group['id']; ?>" value="<?php echo $group['name']; ?>" required class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg">ذخیره تغییرات</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- مودال افزودن مخاطب -->
                                <div class="modal fade" id="addContactModal<?php echo $group['id']; ?>" tabindex="-1" aria-labelledby="addContactModalLabel<?php echo $group['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-gray-800 text-white">
                                            <div class="modal-header bg-gradient-to-r from-blue-500 to-pink-500">
                                                <h5 class="modal-title" id="addContactModalLabel<?php echo $group['id']; ?>">افزودن مخاطب</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="contact_name_<?php echo $group['id']; ?>" class="form-label">نام</label>
                                                        <input type="text" name="contact_name" id="contact_name_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_family_<?php echo $group['id']; ?>" class="form-label">نام خانوادگی</label>
                                                        <input type="text" name="contact_family" id="contact_family_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_mobile_<?php echo $group['id']; ?>" class="form-label">موبایل (الزامی)</label>
                                                        <input type="text" name="contact_mobile" id="contact_mobile_<?php echo $group['id']; ?>" required pattern="09[0-9]{9}" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_birthdate_<?php echo $group['id']; ?>" class="form-label">تاریخ تولد</label>
                                                        <input type="text" name="contact_birthdate" id="contact_birthdate_<?php echo $group['id']; ?>" placeholder="مثال: 1370/01/01" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field1_<?php echo $group['id']; ?>" class="form-label">فیلد 1</label>
                                                        <input type="text" name="contact_field1" id="contact_field1_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field2_<?php echo $group['id']; ?>" class="form-label">فیلد 2</label>
                                                        <input type="text" name="contact_field2" id="contact_field2_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field3_<?php echo $group['id']; ?>" class="form-label">فیلد 3</label>
                                                        <input type="text" name="contact_field3" id="contact_field3_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field4_<?php echo $group['id']; ?>" class="form-label">فیلد 4</label>
                                                        <input type="text" name="contact_field4" id="contact_field4_<?php echo $group['id']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <button type="submit" name="add_contact" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg">اضافه کردن</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- لیست مخاطبین -->
            <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl mb-8 animate__animated animate__slideInDown">
                <h2 class="text-xl font-bold text-white mb-4">مخاطبین</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-white">
                        <thead>
                            <tr class="bg-gradient-to-r from-blue-500 to-pink-500">
                                <th class="p-3">گروه</th>
                                <th class="p-3">نام</th>
                                <th class="p-3">نام خانوادگی</th>
                                <th class="p-3">موبایل</th>
                                <th class="p-3">تاریخ تولد</th>
                                <th class="p-3">فیلد 1</th>
                                <th class="p-3">فیلد 2</th>
                                <th class="p-3">فیلد 3</th>
                                <th class="p-3">فیلد 4</th>
                                <th class="p-3">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_contacts as $contact): ?>
                                <tr class="border-b border-gray-700 hover:bg-gray-700 neon-row transition-all duration-300">
                                    <td class="p-3"><?php echo $groups[$contact['group_id']]['name'] ?? '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['name'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['family'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['mobile']; ?></td>
                                    <td class="p-3"><?php echo $contact['birthdate'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['field1'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['field2'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['field3'] ?: '-'; ?></td>
                                    <td class="p-3"><?php echo $contact['field4'] ?: '-'; ?></td>
                                    <td class="p-3">
                                        <button type="button" class="text-blue-400 hover:text-blue-300 mr-2" data-bs-toggle="modal" data-bs-target="#editContactModal<?php echo $contact['id']; ?>">ویرایش</button>
                                        <a href="?delete_contact=<?php echo $contact['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                                    </td>
                                </tr>
                                <!-- مودال ویرایش مخاطب -->
                                <div class="modal fade" id="editContactModal<?php echo $contact['id']; ?>" tabindex="-1" aria-labelledby="editContactModalLabel<?php echo $contact['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-gray-800 text-white">
                                            <div class="modal-header bg-gradient-to-r from-blue-500 to-pink-500">
                                                <h5 class="modal-title" id="editContactModalLabel<?php echo $contact['id']; ?>">ویرایش مخاطب</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="edit_contact_id" value="<?php echo $contact['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="contact_name_<?php echo $contact['id']; ?>" class="form-label">نام</label>
                                                        <input type="text" name="contact_name" id="contact_name_<?php echo $contact['id']; ?>" value="<?php echo $contact['name']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_family_<?php echo $contact['id']; ?>" class="form-label">نام خانوادگی</label>
                                                        <input type="text" name="contact_family" id="contact_family_<?php echo $contact['id']; ?>" value="<?php echo $contact['family']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_mobile_<?php echo $contact['id']; ?>" class="form-label">موبایل (الزامی)</label>
                                                        <input type="text" name="contact_mobile" id="contact_mobile_<?php echo $contact['id']; ?>" value="<?php echo $contact['mobile']; ?>" required pattern="09[0-9]{9}" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_birthdate_<?php echo $contact['id']; ?>" class="form-label">تاریخ تولد</label>
                                                        <input type="text" name="contact_birthdate" id="contact_birthdate_<?php echo $contact['id']; ?>" value="<?php echo $contact['birthdate']; ?>" placeholder="مثال: 1370/01/01" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field1_<?php echo $contact['id']; ?>" class="form-label">فیلد 1</label>
                                                        <input type="text" name="contact_field1" id="contact_field1_<?php echo $contact['id']; ?>" value="<?php echo $contact['field1']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field2_<?php echo $contact['id']; ?>" class="form-label">فیلد 2</label>
                                                        <input type="text" name="contact_field2" id="contact_field2_<?php echo $contact['id']; ?>" value="<?php echo $contact['field2']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field3_<?php echo $contact['id']; ?>" class="form-label">فیلد 3</label>
                                                        <input type="text" name="contact_field3" id="contact_field3_<?php echo $contact['id']; ?>" value="<?php echo $contact['field3']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="contact_field4_<?php echo $contact['id']; ?>" class="form-label">فیلد 4</label>
                                                        <input type="text" name="contact_field4" id="contact_field4_<?php echo $contact['id']; ?>" value="<?php echo $contact['field4']; ?>" class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                                                    </div>
                                                    <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg">ذخیره تغییرات</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- مودال اضافه کردن گروه -->
    <div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-gray-800 text-white">
                <div class="modal-header bg-gradient-to-r from-blue-500 to-pink-500">
                    <h5 class="modal-title" id="addGroupModalLabel">اضافه کردن گروه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">نام گروه</label>
                            <input type="text" name="group_name" id="group_name" required class="form-control bg-gray-700 text-white border-2 border-transparent focus:border-blue-400">
                        </div>
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg">اضافه کردن</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.js"></script>
    <script>
        particlesJS("particles-js", {
            particles: { number: { value: 80, density: { enable: true, value_area: 800 } }, color: { value: ["#00f0ff", "#ff00cc"] }, shape: { type: "circle" }, opacity: { value: 0.5, random: true }, size: { value: 3, random: true }, line_linked: { enable: true, distance: 150, color: "#00f0ff", opacity: 0.4, width: 1 }, move: { enable: true, speed: 3, direction: "none", random: true, out_mode: "out" } },
            interactivity: { detect_on: "canvas", events: { onhover: { enable: true, mode: "repulse" }, onclick: { enable: true, mode: "push" }, resize: true }, modes: { repulse: { distance: 100, duration: 0.4 }, push: { particles_nb: 4 } } },
            retina_detect: true
        });

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('translate-x-full');
        }
    </script>
</body>
</html>