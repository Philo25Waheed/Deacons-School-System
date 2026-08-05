// Camera QR Scanner Component using HTML5 MediaDevices
document.addEventListener('DOMContentLoaded', () => {
    const video = document.getElementById('qrVideo');
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const scanStatus = document.getElementById('scanStatus');
    const manualInput = document.getElementById('manualTokenInput');
    const manualBtn = document.getElementById('manualSubmitBtn');

    let stream = null;
    let scanning = false;

    if (startBtn && video) {
        startBtn.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                video.srcObject = stream;
                video.setAttribute("playsinline", true);
                video.play();
                scanning = true;
                if (scanStatus) scanStatus.innerText = "جاري الكاميرا... وجه الكود أمام الكاميرا";
                startBtn.style.display = "none";
                if (stopBtn) stopBtn.style.display = "inline-flex";
            } catch (err) {
                alert("لم يتم التمكن من الوصول لكاميرا الجهاز. يرجى التأكد من إعطاء الصلاحية أو استخدام الإدخال اليدوي.");
            }
        });
    }

    if (stopBtn && video) {
        stopBtn.addEventListener('click', () => {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            scanning = false;
            if (scanStatus) scanStatus.innerText = "متوقف";
            if (startBtn) startBtn.style.display = "inline-flex";
            stopBtn.style.display = "none";
        });
    }

    if (manualBtn && manualInput) {
        manualBtn.addEventListener('click', () => {
            const token = manualInput.value.trim();
            if (token) {
                processScannedToken(token);
            }
        });
    }
});

function processScannedToken(token) {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '../';
    const scanStatus = document.getElementById('scanStatus');
    if (scanStatus) scanStatus.innerText = "جاري التحقق من كود الشماس...";

    fetch(baseUrl + 'api/scan_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_token: token })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success' || data.status === 'warning') {
            const stu = data.student;
            showScanResultModal(data.message, stu, data.scanned_at, data.status);
            if (scanStatus) scanStatus.innerText = data.message;
        } else {
            alert(data.message || 'كود QR غير صحيح');
            if (scanStatus) scanStatus.innerText = "فشل التعرف على الكود";
        }
    })
    .catch(err => {
        console.error('Scan process error:', err);
        alert('حدث خطأ في الاتصال بالسيرفر');
    });
}

function showScanResultModal(message, student, timeStr, statusType) {
    let resultModal = document.getElementById('scanResultModal');
    if (!resultModal) {
        resultModal = document.createElement('div');
        resultModal.id = 'scanResultModal';
        resultModal.className = 'glass-card';
        resultModal.style.position = 'fixed';
        resultModal.style.top = '50%';
        resultModal.style.left = '50%';
        resultModal.style.transform = 'translate(-50%, -50%)';
        resultModal.style.zIndex = '9999';
        resultModal.style.maxWidth = '400px';
        resultModal.style.width = '90%';
        resultModal.style.textAlign = 'center';
        document.body.appendChild(resultModal);
    }

    const badgeClass = (statusType === 'success') ? 'badge-success' : 'badge-warning';

    resultModal.innerHTML = `
        <div style="padding: 1.5rem;">
            <span class="badge ${badgeClass}" style="margin-bottom: 1rem; font-size: 1rem;">${message}</span>
            <h3 style="margin-top:0.5rem;">${student.full_name}</h3>
            <p style="color:var(--text-secondary); margin: 0.5rem 0;">${student.stage_name || ''} - ${student.grade_name || ''} (${student.class_name || ''})</p>
            <p><strong>الكنيسة:</strong> ${student.church_name}</p>
            <p style="margin-top:0.5rem; font-size:0.85rem; color:var(--text-muted);">وقت التسجيل: ${timeStr}</p>
            <button class="btn btn-primary" style="margin-top:1.5rem; width:100%;" onclick="document.getElementById('scanResultModal').remove()">تم / موافق</button>
        </div>
    `;
}
