<?php
require_once 'config.php';
session_start();

$provinces = ['تهران', 'اصفهان', 'فارس', 'خوزستان']; // Add more as needed
$school_types = ['دبستان', 'مدرسه', 'پیش‌دبستانی', 'متوسطه اول', 'متوسطه دوم'];
$gender_types = ['دخترانه', 'پسرانه', 'مختلط'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $school_type = filter_var($_POST['school_type'], FILTER_SANITIZE_STRING);
    $gender_type = filter_var($_POST['gender_type'], FILTER_SANITIZE_STRING);
    $school_name = filter_var($_POST['school_name'], FILTER_SANITIZE_STRING);
    $national_id = filter_var($_POST['national_id'], FILTER_SANITIZE_STRING);
    $province = filter_var($_POST['province'], FILTER_SANITIZE_STRING);
    $city = filter_var($_POST['city'], FILTER_SANITIZE_STRING);
    $district = filter_var($_POST['district'], FILTER_SANITIZE_STRING);
    $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
    $postal_code = filter_var($_POST['postal_code'], FILTER_SANITIZE_STRING);
    $manager_name = filter_var($_POST['manager_name'], FILTER_SANITIZE_STRING);
    $manager_mobile = filter_var($_POST['manager_mobile'], FILTER_SANITIZE_STRING);
    $manager_national_id = filter_var($_POST['manager_national_id'], FILTER_SANITIZE_STRING);
    $manager_birth_date = filter_var($_POST['manager_birth_date'], FILTER_SANITIZE_STRING);
    $deputy_name = filter_var($_POST['deputy_name'], FILTER_SANITIZE_STRING);
    $deputy_mobile = filter_var($_POST['deputy_mobile'], FILTER_SANITIZE_STRING);
    $deputy_national_id = filter_var($_POST['deputy_national_id'], FILTER_SANITIZE_STRING);
    $deputy_birth_date = filter_var($_POST['deputy_birth_date'], FILTER_SANITIZE_STRING);

    // Handle file upload
    $request_letter_path = null;
    if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $request_letter_path = $upload_dir . time() . '_' . basename($_FILES['request_letter']['name']);
        move_uploaded_file($_FILES['request_letter']['tmp_name'], $request_letter_path);
    }

    try {
        $pdo->beginTransaction();

        // Insert school
        $stmt = $pdo->prepare("
            INSERT INTO schools (school_type, gender_type, school_name, national_id, province, city, district, address, postal_code, request_letter_path, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$school_type, $gender_type, $school_name, $national_id, $province, $city, $district, $address, $postal_code, $request_letter_path]);
        $school_id = $pdo->lastInsertId();

        // Insert manager
        $stmt = $pdo->prepare("
            INSERT INTO users (name, mobile, national_id, birth_date, role, school_id)
            VALUES (?, ?, ?, ?, 'manager', ?)
        ");
        $stmt->execute([$manager_name, $manager_mobile, $manager_national_id, $manager_birth_date, $school_id]);

        // Insert deputy (if provided)
        if ($deputy_name && $deputy_mobile) {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, mobile, national_id, birth_date, role, school_id)
                VALUES (?, ?, ?, ?, 'deputy', ?)
            ");
            $stmt->execute([$deputy_name, $deputy_mobile, $deputy_national_id, $deputy_birth_date, $school_id]);
        }

        $pdo->commit();
        $success = "ثبت‌نام با موفقیت انجام شد. منتظر تأیید مدیریت باشید.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطا در ثبت‌نام: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام مرکز آموزشی - سامانه پیامک مدارس</title>
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="assets/css/custom.css"> <!-- Custom CSS -->
</head>
<body class="hold-transition register-page">
<div class="register-box">
    <div class="register-logo">
        <a href="register.php">
            <img src="https://behfarda.com/upload/image/BehFarda_FA_Horizontal.png" alt="Logo">
        </a>
    </div>
    <div class="card">
        <div class="card-body register-card-body">
            <p class="login-box-msg">ثبت‌نام مرکز آموزشی</p>
            <?php if (isset($error)): ?>
                <p class="text-danger"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <p class="text-success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <h5>اطلاعات مرکز آموزشی</h5>
                <div class="input-group mb-3">
                    <select class="form-control" name="school_type" required>
                        <?php foreach ($school_types as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-school"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <select class="form-control" name="gender_type" required>
                        <?php foreach ($gender_types as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-venus-mars"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="نام مرکز آموزشی" name="school_name" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-building"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="شناسه ملی مدرسه" name="national_id" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-id-card"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <select class="form-control" name="province" required>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?php echo $province; ?>"><?php echo $province; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-map"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="شهر" name="city" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-city"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="بخش" name="district" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-map-marker-alt"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <textarea class="form-control" placeholder="آدرس دقیق" name="address" required></textarea>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-home"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="کد پستی" name="postal_code" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-mail-bulk"></span>
                        </div>
                    </div>
                </div>
                <h5>اطلاعات مدیر</h5>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="نام و نام خانوادگی مدیر" name="manager_name" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="موبایل مدیر" name="manager_mobile" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-phone"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="کد ملی مدیر" name="manager_national_id" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-id-card"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="date" class="form-control" placeholder="تاریخ تولد مدیر" name="manager_birth_date" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-calendar"></span>
                        </div>
                    </div>
                </div>
                <h5>اطلاعات معاون (اختیاری)</h5>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="نام و نام خانوادگی معاون" name="deputy_name">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="موبایل معاون" name="deputy_mobile">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-phone"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="کد ملی معاون" name="deputy_national_id">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-id-card"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="date" class="form-control" placeholder="تاریخ تولد معاون" name="deputy_birth_date">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-calendar"></span>
                        </div>
                    </div>
                </div>
                <h5>نامه درخواست</h5>
                <p><a href="request_letter_template.pdf" download>دانلود قالب نامه درخواست</a></p>
                <div class="input-group mb-3">
                    <input type="file" class="form-control" name="request_letter" accept=".pdf" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-upload"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">ثبت‌نام</button>
                    </div>
                </div>
            </form>
            <p class="mt-3"><a href="index.php">بازگشت به ورود</a></p>
        </div>
    </div>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>