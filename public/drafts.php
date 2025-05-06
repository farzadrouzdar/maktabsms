<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = db_connect();
$school_id = $_SESSION['school_id'];

// دریافت لیست پیش‌نویس‌ها
$stmt = $conn->prepare("SELECT id, title, message FROM drafts WHERE school_id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$drafts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<h1>مدیریت پیش‌نویس‌ها</h1>
<div>
    <a href="add_draft.php" class="btn">افزودن پیش‌نویس جدید</a>
    <table border="1">
        <thead>
            <tr>
                <th>عنوان</th>
                <th>متن</th>
                <th>عملیات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($drafts)): ?>
                <tr>
                    <td colspan="3">هیچ پیش‌نویسی یافت نشد.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($drafts as $draft): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($draft['title']); ?></td>
                        <td><?php echo htmlspecialchars($draft['message']); ?></td>
                        <td>
                            <a href="edit_draft.php?id=<?php echo $draft['id']; ?>">ویرایش</a> |
                            <a href="delete_draft.php?id=<?php echo $draft['id']; ?>" onclick="return confirm('آیا مطمئن هستید؟');">حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>