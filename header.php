<!DOCTYPE html>
<html lang="<?php echo APP_LANGUAGE; ?>" dir="<?php echo APP_DIRECTION; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'سامانه پیامک مدارس'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo FONTAWESOME_PATH; ?>all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/adminlte/plugins/rtl/rtl.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/custom.css">
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('<?php echo BASE_URL; ?>assets/fonts/Vazirmatn-Regular.woff2') format('woff2'); /* عوض کن اگه اسم فایل فرق داره */
        }
        body {
            font-family: 'Vazir', sans-serif !important;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">