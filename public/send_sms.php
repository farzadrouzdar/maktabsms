<?php
include 'header.php';

// چک کردن ورود کاربر
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "لطفاً ابتدا وارد شوید.";
    header("Location: login.php");
    exit;
}

include 'template.php';

// دریافت اطلاعات مدرسه
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$_SESSION['school_id']]);
$school = $stmt->fetch(PDO::FETCH_ASSOC);
$header_text = $school['type'] . ' ' . $school['name'];
?>

<div class="flex flex-col md:flex-row gap-4">
    <!-- فرم ارسال -->
    <div class="w-full md:w-1/2 bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">ارسال پیامک تکی</h2>
        <form action="process_sms.php" method="POST" id="sms-form">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">شماره موبایل</label>
                <input type="text" name="mobile" class="w-full p-2 border rounded" required pattern="09[0-9]{9}" placeholder="مثال: 09123456789">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">متن پیامک</label>
                <div class="message-container">
                    <div class="header-text"><?php echo htmlspecialchars($header_text); ?></div>
                    <textarea name="message" id="message" class="w-full p-2" rows="6" required placeholder="متن پیامک را اینجا بنویسید"></textarea>
                    <div class="footer-text"><?php echo htmlspecialchars($sms_footer_text); ?></div>
                </div>
                <p class="text-sm text-gray-600 mt-2">
                    کاراکترها: <span id="char-count">0</span> |
                    پارت‌ها: <span id="part-count">1</span> |
                    هزینه: <span id="cost">0</span> تومان
                </p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">زمان ارسال</label>
                <input type="datetime-local" name="scheduled_at" class="w-full p-2 border rounded" min="<?php echo date('Y-m-d\TH:i'); ?>">
                <p class="text-sm text-gray-600">خالی بگذارید برای ارسال فوری</p>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">ارسال پیامک</button>
        </form>
    </div>
    <!-- پیش‌نمایش -->
    <div class="w-full md:w-1/2 bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">پیش‌نمایش پیامک</h2>
        <div id="preview-screen">
            <div id="preview-text"></div>
        </div>
    </div>
</div>
</div>
</body>
</html>

<style>
    #preview-screen {
        background: url('https://via.placeholder.com/200x400/ffffff/000000?text=موبایل') no-repeat center;
        background-size: contain;
        height: 300px;
        padding: 20px;
        overflow-y: auto;
        border: 1px solid #ccc;
        border-radius: 10px;
    }
    #preview-text {
        font-size: 14px;
        white-space: pre-wrap;
    }
    .message-container {
        position: relative;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .header-text {
        padding: 8px;
        background: #f9f9f9;
        border-bottom: 1px solid #ccc;
        color: #555;
    }
    .footer-text {
        padding: 8px;
        background: #f9f9f9;
        border-top: 1px solid #ccc;
        color: #555;
        text-align: right;
    }
    #message {
        border: none;
        resize: vertical;
    }
</style>

<script>
    const textarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    const partCount = document.getElementById('part-count');
    const costDisplay = document.getElementById('cost');
    const previewText = document.getElementById('preview-text');
    const headerText = <?php echo json_encode($header_text); ?>;
    const footerText = <?php echo json_encode($sms_footer_text); ?>;
    const firstPartChars = <?php echo $sms_first_part_chars; ?>;
    const nextPartChars = <?php echo $sms_next_part_chars; ?>;
    const maxParts = <?php echo $sms_max_parts; ?>;
    const smsCost = <?php echo $sms_cost; ?>;

    function calculateParts(text) {
        const headerLen = headerText.length;
        const footerLen = footerText.length;
        const totalLen = headerLen + text.length + footerLen + 4; // 4 برای دو خط فاصله
        if (totalLen <= firstPartChars) return 1;
        const extraChars = totalLen - firstPartChars;
        return 1 + Math.ceil(extraChars / nextPartChars);
    }

    function cleanText(text) {
        return text.replace(/[\u200B-\u200D\uFEFF\s\r\n]+/g, ' ').trim();
    }

    function updatePreview() {
        const text = cleanText(textarea.value);
        previewText.textContent = headerText + '\n\n' + text + '\n\n' + footerText;
    }

    textarea.addEventListener('input', function() {
        let text = this.value;
        let cleanedText = cleanText(text);
        const charLen = headerText.length + cleanedText.length + footerText.length + 4;
        const parts = calculateParts(cleanedText);

        charCount.textContent = charLen;
        partCount.textContent = parts;
        costDisplay.textContent = parts * smsCost;

        if (!cleanedText.length) {
            this.setCustomValidity('متن پیامک نمی‌تواند خالی باشد.');
            console.log('Empty text detected:', JSON.stringify(text));
        } else if (!(/^[\u0600-\u06FF]|^[\s]*[\u0600-\u06FF]/.test(cleanedText))) {
            this.setCustomValidity('متن پیامک باید با کاراکتر فارسی شروع شود.');
            console.log('Invalid Persian start:', JSON.stringify(cleanedText));
        } else {
            this.setCustomValidity('');
        }

        if (parts > maxParts) {
            this.setCustomValidity(`حداکثر ${maxParts} پارت مجاز است.`);
        } else {
            this.setCustomValidity('');
        }

        updatePreview();
    });

    updatePreview();
    textarea.dispatchEvent(new Event('input'));

    document.getElementById('sms-form').addEventListener('submit', function(e) {
        let text = cleanText(textarea.value);
        if (!text.length) {
            e.preventDefault();
            console.log('Form submission prevented: Empty text');
        }
    });
</script>