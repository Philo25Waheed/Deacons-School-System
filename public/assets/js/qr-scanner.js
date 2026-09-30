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

    const checkLesson = document.getElementById('scanCheckLesson')?.checked ?? true;
    const checkPamphlet = document.getElementById('scanCheckPamphlet')?.checked ?? false;
    const checkLiturgy = document.getElementById('scanCheckLiturgy')?.checked ?? false;

    fetch(baseUrl + 'api/scan_attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            qr_token: token,
            attended_lesson: checkLesson,
            attended_pamphlet: checkPamphlet,
            attended_liturgy: checkLiturgy
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success' || data.status === 'warning') {
            const stu = data.student;
            showScanResultModal(data.message, stu, data.scanned_at, data.status, data.points_awarded);
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

function showScanResultModal(message, student, timeStr, statusType, pointsAwarded) {
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
        resultModal.style.maxWidth = '420px';
        resultModal.style.width = '90%';
        resultModal.style.textAlign = 'center';
        resultModal.style.boxShadow = '0 20px 50px rgba(0,0,0,0.5)';
        resultModal.style.border = '2px solid var(--gold)';
        document.body.appendChild(resultModal);
    }

    const badgeClass = (statusType === 'success') ? 'badge-success' : 'badge-warning';
    const pointsBadge = (pointsAwarded !== undefined && pointsAwarded > 0)
        ? `<div style="margin:0.8rem 0;"><span class="badge badge-gold" style="font-size:1.1rem; padding:0.4rem 1rem;">🪙 +${pointsAwarded} طايو مكتسب!</span></div>`
        : '';

    resultModal.innerHTML = `
        <div style="padding: 1.5rem;">
            <span class="badge ${badgeClass}" style="margin-bottom: 0.8rem; font-size: 0.95rem; display:block; white-space:normal; line-height:1.5;">${message}</span>
            ${pointsBadge}
            <h3 style="margin-top:0.5rem; color:var(--royal-blue); font-weight:800;">${student.full_name}</h3>
            <p style="color:var(--text-secondary); margin: 0.5rem 0;">${student.stage_name || ''} - ${student.grade_name || ''} (${student.class_name || ''})</p>
            <p style="margin-top:0.5rem; font-size:0.85rem; color:var(--text-muted);">وقت التسجيل: ${timeStr}</p>
            <button class="btn btn-primary" style="margin-top:1.25rem; width:100%;" onclick="document.getElementById('scanResultModal').remove()">تم / إغلاق</button>
        </div>
    `;
}
