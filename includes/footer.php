<?php
?>
<footer style="margin-top:4rem; text-align:center; padding:1.5rem; color:var(--text-muted); font-size:0.85rem; border-top:1px solid var(--border-color);">
    <div>جميع الحقوق محفوظة © <?= date('Y') ?> - مدرسة الشمامسة الكنيسة القبطية الأرثوذكسية</div>
</footer>

<!-- Core Application Scripts -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>

<!-- Service Worker Registration for PWA Readiness -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= BASE_URL ?>sw.js')
                .then(reg => console.log('SW Registered successfully:', reg.scope))
                .catch(err => console.log('SW Registration failed:', err));
        });
    }
</script>
</body>
</html>
