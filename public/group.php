<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// چک کردن لاگین
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// داده‌های فرضی
$groups = [
    1 => ["id" => 1, "name" => "دانش‌آموزان"],
    2 => ["id" => 2, "name" => "اولیا"],
    3 => ["id" => 3, "name" => "معلمان"]
];

// اضافه کردن گروه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    $new_id = max(array_keys($groups)) + 1;
    $groups[$new_id] = ["id" => $new_id, "name" => $_POST['group_name']];
    $_SESSION['success'] = 'گروه با موفقیت اضافه شد!';
    header("Location: groups.php");
    exit;
}

// حذف گروه
if (isset($_GET['delete']) && isset($groups[(int)$_GET['delete']])) {
    unset($groups[(int)$_GET['delete']]);
    $_SESSION['success'] = 'گروه با موفقیت حذف شد!';
    header("Location: groups.php");
    exit;
}

// ویرایش گروه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_group_id'])) {
    $group_id = (int)$_POST['edit_group_id'];
    if (isset($groups[$group_id])) {
        $groups[$group_id]['name'] = $_POST['edit_group_name'];
        $_SESSION['success'] = 'گروه با موفقیت ویرایش شد!';
        header("Location: groups.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گروه‌ها - مکتب اس‌ام‌اس</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/vazir@4.5.0/index.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
</head>
<body class="bg-gray-900 min-h-screen flex">
    <div id="particles-js" class="absolute inset-0 z-0"></div>
    
    <aside class="w-64 bg-gray-800 bg-opacity-90 h-screen sticky top-0 z-20 transform transition-transform duration-300 md:translate-x-0 translate-x-full" id="sidebar">
        <div class="p-4 flex items-center justify-center">
            <img src="assets/dist/img/logo.png" alt="مکتب اس‌ام‌اس" class="w-16 animate__animated animate__pulse animate__infinite" style="filter: drop-shadow(0 0 10px #00f0ff);">
            <h1 class="text-xl font-bold text-white mr-2">مکتب اس‌ام‌اس</h1>
        </div>
        <nav class="mt-6">
            <ul>
                <li><a href="dashboard.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-blue-500 hover:to-pink-500">داشبورد</a></li>
                <li><a href="contacts.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-blue-500 hover:to-pink-500">مخاطبین</a></li>
                <li><a href="send_sms.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-blue-500 hover:to-pink-500">ارسال پیامک</a></li>
                <li><a href="groups.php" class="flex items-center p-4 text-white neon-menu-item bg-gradient-to-l from-blue-500 to-pink-500">گروه‌ها</a></li>
                <li><a href="logout.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-red-500 hover:to-red-700">خروج</a></li>
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
                    <img src="assets/dist/img/logo.png" alt="مکتب اس‌ام‌اس" class="w-16 animate__animated animate__pulse animate__infinite" style="filter: drop-shadow(0 0 10px #00f0ff);">
                    <h1 class="text-2xl font-bold text-white mr-4">مکتب اس‌ام‌اس</h1>
                </div>
                <a href="logout.php" class="bg-gradient-to-r from-red-500 to-red-700 text-white px-4 py-2 rounded-lg neon-button hidden md:block">خروج</a>
            </div>
        </header>

        <div class="container mx-auto p-6">
            <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl mb-8 animate__animated animate__slideInDown">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-white">لیست گروه‌ها</h2>
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
                                    <td class="p-3"><a href="contacts.php?group_id=<?php echo $group['id']; ?>" class="text-blue-400 hover:text-blue-300"><?php echo $group['name']; ?></a></td>
                                    <td class="p-3">
                                        <button type="button" class="text-blue-400 hover:text-blue-300 mr-2" data-bs-toggle="modal" data-bs-target="#editGroupModal<?php echo $group['id']; ?>">ویرایش</button>
                                        <a href="?delete=<?php echo $group['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
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