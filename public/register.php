<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = db_connect();
    $education_type = $_POST['education_type'];
    $school_type = $_POST['school_type'];
    $name = $_POST['name'];
    $national_id = $_POST['national_id'];
    $province = $_POST['province'];
    $city = $_POST['city'];
    $section = $_POST['section'];
    $address = $_POST['address'];
    $postal_code = $_POST['postal_code'];
    $principal_name = $_POST['principal_name'];
    $principal_mobile = $_POST['principal_mobile'];
    $principal_national_code = $_POST['principal_national_code'];
    $principal_birth_date = $_POST['principal_birth_date'];
    $vice_name = $_POST['vice_name'];
    $vice_mobile = $_POST['vice_mobile'];
    $vice_national_code = $_POST['vice_national_code'];
    $vice_birth_date = $_POST['vice_birth_date'];
    $unique_code = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

    // آپلود نامه درخواست
    $request_letter = '';
    if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $request_letter = $upload_dir . basename($_FILES['request_letter']['name']);
        move_uploaded_file($_FILES['request_letter']['tmp_name'], $request_letter);
    }

    // ثبت مدرسه
    $stmt = $conn->prepare("INSERT INTO schools (education_type, school_type, name, national_id, province, city, section, address, postal_code, request_letter, unique_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $education_type, $school_type, $name, $national_id, $province, $city, $section, $address, $postal_code, $request_letter, $unique_code);
    $stmt->execute();
    $school_id = $conn->insert_id;

    // ثبت مدیر
    $stmt = $conn->prepare("INSERT INTO users (full_name, mobile, national_code, birth_date, role, school_id) VALUES (?, ?, ?, ?, 'admin', ?)");
    $stmt->bind_param("ssssi", $principal_name, $principal_mobile, $principal_national_code, $principal_birth_date, $school_id);
    $stmt->execute();

    // ثبت معاون
    $stmt->bind_param("ssssi", $vice_name, $vice_mobile, $vice_national_code, $vice_birth_date, $school_id);
    $stmt->execute();

    $stmt->close();
    $conn->close();
    header("Location: index.php?message=registration_success");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ثبت‌نام مدرسه</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h1>ثبت‌نام مدرسه</h1>
    <form method="POST" enctype="multipart/form-data">
        <h3>اطلاعات مدرسه</h3>
        <label>نوع مرکز آموزشی:</label>
        <select name="education_type" required>
            <option value="دبستان">دبستان</option>
            <option value="مدرسه">مدرسه</option>
            <option value="پیش دبستانی">پیش دبستانی</option>
            <option value="متوسطه">متوسطه</option>
        </select><br>
        <label>نوع مدرسه:</label>
        <select name="school_type" required>
            <option value="دخترانه">دخترانه</option>
            <option value="پسرانه">پسرانه</option>
            <option value="مختلط">مختلط</option>
        </select><br>
        <label>نام مرکز:</label>
        <input type="text" name="name" required><br>
        <label>شناسه ملی:</label>
        <input type="text" name="national_id" required><br>
        <label>استان:</label>
        <input type="text" name="province" required><br>
        <label>شهر:</label>
        <input type="text" name="city" required><br>
        <label>بخش:</label>
        <input type="text" name="section" required><br>
        <label>آدرس:</label>
        <textarea name="address" required></textarea><br>
        <label>کد پستی:</label>
        <input type="text" name="postal_code" required><br>
        <h3>اطلاعات مدیر</h3>
        <label>نام و نام خانوادگی:</label>
        <input type="text" name="principal_name" required><br>
        <label>موبایل:</label>
        <input type="text" name="principal_mobile" required><br>
        <label>کد ملی:</label>
        <input type="text" name="principal_national_code" required><br>
        <label>تاریخ تولد:</label>
        <input type="date" name="principal_birth_date" required><br>
        <h3>اطلاعات معاون</h3>
        <label>نام و نام خانوادگی:</label>
        <input type="text" name="vice_name" required><br>
        <label>موبایل:</label>
        <input type="text" name="vice_mobile" required><br>
        <label>کد ملی:</label>
        <input type="text" name="vice_national_code" required><br>
        <label>تاریخ تولد:</label>
        <input type="date" name="vice_birth_date" required><br>
        <label>نامه درخواست:</label>
        <input type="file" name="request_letter" accept=".pdf" required><br>
        <a href="templates/letter_template.pdf" download>دانلود قالب نامه</a><br>
        <button type="submit">ثبت</button>
    </form>
</body>
</html>