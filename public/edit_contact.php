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

$contacts = [
    1 => ["id" => 1, "name" => "علی احمدی", "mobile" => "09120000000", "birthdate" => "1370/01/01", "field1" => "کد ملی: 1234567890", "field2" => "", "field3" => "", "field4" => "", "group_id" => 1],
    2 => ["id" => 2, "name" => "مریم حسینی", "mobile" => "09130000000", "birthdate" => "", "field1" => "", "field2" => "ایمیل: maryam@example.com", "field3" => "", "field4" => "", "group_id" => 2],
    3 => ["id" => 3, "name" => "رضا محمدی", "mobile" => "09140000000", "birthdate" => "1365/05/20", "field1" => "", "field2" => "", "field3" => "آدرس: تهران", "field4" => "", "group_id" => 3]
];

$contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$contact = isset($contacts[$contact_id]) ? $contacts[$contact_id] : null;

if (!$contact) {
    $_SESSION['error'] = 'مخاطب یافت نشد!';
    header("Location: contacts.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_contact = [
        'id' => $contact_id,
        'name' => $_POST['name'] ?? '',
        'mobile' => $_POST['mobile'],
        'birthdate' => $_POST['birthdate'] ?? '',
        'field1' => $_POST['field1'] ?? '',
        'field2' => $_POST['field2'] ?? '',
        'field3' => $_POST['field3'] ?? '',
        'field4' => $_POST['field4'] ?? '',
        'group_id' => $_POST['group_id'] ? (int)$_POST['group_id'] : null
    ];
    // TODO: آپدیت در دیتابیس واقعی
    $_SESSION['success'] = 'مخاطب با موفقیت ویرایش شد!';
    header("Location: contacts.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش مخاطب - مکتب اس‌ام‌اس</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/vazir@4.5.0/index.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-gray-900 min-h-screen">
    <div class="container mx-auto p-6">
        <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl">
            <h2 class="text-xl font-bold text-white mb-4">ویرایش مخاطب</h2>
            <form method="POST" class="space-y-6 grid md:grid-cols-2 gap-4">
                <div class="relative">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($contact['name']); ?>" placeholder="نام و نام‌خانوادگی" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="mobile" required pattern="09[0-9]{9}" value="<?php echo htmlspecialchars($contact['mobile']); ?>" 
                           placeholder="شماره موبایل" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="birthdate" value="<?php echo htmlspecialchars($contact['birthdate']); ?>" placeholder="تاریخ تولد (مثال: 1370/01/01)" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="field1" value="<?php echo htmlspecialchars($contact['field1']); ?>" placeholder="فیلد 1" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="field2" value="<?php echo htmlspecialchars($contact['field2']); ?>" placeholder="فیلد 2" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="field3" value="<?php echo htmlspecialchars($contact['field3']); ?>" placeholder="فیلد 3" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <input type="text" name="field4" value="<?php echo htmlspecialchars($contact['field4']); ?>" placeholder="فیلد 4" 
                           class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                </div>
                <div class="relative">
                    <select name="group_id" class="w-full p-3 bg-gray-700 text-white rounded-lg border-2 border-transparent focus:border-blue-400 focus:ring-4 focus:ring-blue-400 focus:ring-opacity-50 neon-input transition-all duration-300">
                        <option value="">انتخاب گروه</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>" <?php echo $contact['group_id'] == $group['id'] ? 'selected' : ''; ?>><?php echo $group['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-pink-500 text-white p-3 rounded-lg neon-button animate__animated animate__vibrate animate__infinite animate__slow">
                        ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>