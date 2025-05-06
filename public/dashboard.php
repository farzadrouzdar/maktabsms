<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - مکتب اس‌ام‌اس</title>
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
                    <a href="phonebook.php" class="flex items-center p-4 text-white neon-menu-item hover:bg-gradient-to-l hover:from-blue-500 hover:to-pink-500">
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
            <div class="bg-gray-800 bg-opacity-80 p-6 rounded-xl shadow-2xl mb-8 animate__animated animate__slideInDown">
                <h2 class="text-xl font-bold text-white mb-4">خوش آمدید</h2>
                <p class="text-gray-300">به داشبورد مکتب اس‌ام‌اس خوش آمدید! از منوی سمت راست می‌توانید به دفترچه تلفن و ارسال پیامک دسترسی داشته باشید.</p>
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