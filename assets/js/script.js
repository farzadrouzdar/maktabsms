function previewSMS(textarea, previewDiv, header, footer) {
    const text = textarea.value;
    previewDiv.innerHTML = `${header}<br>${text}<br><span style="color: #888;">${footer}</span>`;
}

function calculateChars(textarea, charCountSpan, header, footer) {
    const text = header + "\n" + textarea.value + "\n" + footer;
    const charCount = text.length;
    const part1 = 70;
    const part2 = 67;
    let parts = charCount <= part1 ? 1 : 1 + Math.ceil((charCount - part1) / part2);
    charCountSpan.innerText = `کاراکتر: ${charCount} | تعداد پارت: ${parts}`;
}