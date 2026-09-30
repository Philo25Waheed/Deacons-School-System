<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$pageTitle = 'الصفحة الافتتاحية | مدرسة الشهيد إسطفانوس';
require_once __DIR__.'/config/config.php';
require_once __DIR__.'/config/session.php';
require_once __DIR__.'/includes/auth_check.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/coptic_date.php';

if (isLoggedIn()) {
    $role = $_SESSION['user']['role'];
    header('Location: '.BASE_URL."{$role}/index.php");
    exit;
}

$copticInfo = getCopticDateDetails();
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/navbar.php';
?>

<style>
/* Hero Section Styling */
.hero-section {
    position: relative;
    padding: 4.5rem 1.5rem 4rem;
    text-align: center;
    background: radial-gradient(circle at center, var(--royal-blue-glow) 0%, transparent 70%);
    overflow: hidden;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gold-glow);
    color: var(--gold);
    border: 1px solid rgba(217, 119, 6, 0.3);
    padding: 0.4rem 1.25rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
}

.hero-title {
    font-size: 2.8rem;
    font-weight: 900;
    color: var(--royal-blue);
    line-height: 1.25;
    margin-bottom: 1.25rem;
}

.hero-subtitle {
    font-size: 1.15rem;
    color: var(--text-secondary);
    max-width: 720px;
    margin: 0 auto 2.25rem;
    line-height: 1.8;
}

.hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.hero-btn-primary {
    padding: 0.9rem 2.2rem;
    font-size: 1.1rem;
    font-weight: 800;
    border-radius: var(--radius-sm);
    box-shadow: 0 4px 20px var(--royal-blue-glow);
}

.hero-btn-gold {
    padding: 0.9rem 2.2rem;
    font-size: 1.1rem;
    font-weight: 800;
    border-radius: var(--radius-sm);
    box-shadow: 0 4px 20px var(--gold-glow);
}

/* Section Containers */
.section-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3.5rem 1.5rem;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--royal-blue);
    margin-bottom: 0.75rem;
}

.section-desc {
    color: var(--text-muted);
    font-size: 1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Feature & Role Cards */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}

.feature-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    backdrop-filter: blur(12px);
    padding: 2rem 1.5rem;
    border-radius: var(--radius);
    text-align: center;
    transition: var(--transition);
}

.feature-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-md);
    border-color: var(--royal-blue);
}

.feature-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border-radius: 18px;
    background: var(--royal-blue-glow);
    color: var(--royal-blue);
}

.feature-card h3 {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    color: var(--text-primary);
}

.feature-card p {
    font-size: 0.92rem;
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Roles Overview Grid */
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}

.role-card {
    background: var(--bg-surface);
    border: 2px solid transparent;
    border-radius: var(--radius);
    padding: 1.75rem;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}

.role-card:hover {
    border-color: var(--gold);
    box-shadow: var(--shadow-md);
    transform: translateY(-4px);
}

.role-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.role-icon {
    font-size: 2.2rem;
}

.role-card h4 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--royal-blue);
}

.role-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.role-card li {
    font-size: 0.88rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.role-card li::before {
    content: "✓";
    color: var(--gold);
    font-weight: 900;
}

/* CTA Banner */
.cta-banner {
    background: linear-gradient(135deg, var(--royal-blue) 0%, #0f172a 100%);
    color: #ffffff;
    border-radius: var(--radius);
    padding: 3.5rem 2rem;
    text-align: center;
    position: relative;
    box-shadow: var(--shadow-md);
}

.cta-banner h2 {
    font-size: 2.2rem;
    font-weight: 900;
    color: var(--gold-light);
    margin-bottom: 1rem;
}

.cta-banner p {
    font-size: 1.1rem;
    color: #e2e8f0;
    max-width: 650px;
    margin: 0 auto 2rem;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 3rem 1rem 2.5rem;
    }
    .hero-title {
        font-size: clamp(1.6rem, 5vw, 2.2rem);
    }
    .hero-subtitle {
        font-size: clamp(0.92rem, 3vw, 1.1rem);
    }
    .hero-badge {
        font-size: 0.8rem;
        padding: 0.35rem 0.85rem;
        max-width: 100%;
        text-align: center;
        justify-content: center;
    }
    .hero-pill-motto {
        font-size: 0.9rem !important;
        padding: 0.35rem 1rem !important;
    }
}

@media (max-width: 480px) {
    .hero-section {
        padding: 2.25rem 0.75rem 2rem;
    }
    .hero-actions {
        flex-direction: column;
        width: 100%;
        gap: 0.75rem;
    }
    .hero-btn-primary, .hero-btn-gold {
        width: 100%;
        padding: 0.85rem 1rem;
        font-size: 1rem;
        text-align: center;
        justify-content: center;
    }
    .hero-pill-motto {
        font-size: 0.8rem !important;
        padding: 0.3rem 0.75rem !important;
        letter-spacing: 0px !important;
    }
}
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div style="margin-bottom:1.5rem; display:flex; justify-content:center;">
        <div style="width:clamp(80px, 18vw, 120px); height:clamp(80px, 18vw, 120px); border-radius:50%; border:2px solid var(--gold); background:rgba(255,255,255,0.05); box-shadow:0 8px 25px rgba(30,58,138,0.25), 0 0 20px var(--gold-glow); display:flex; align-items:center; justify-content:center; padding:5px; overflow:hidden;">
            <img src="<?= BASE_URL ?>assets/images/logo.png" alt="شعار مدرسة الشهيد إسطفانوس" style="width:100%; height:100%; object-fit:contain;">
        </div>
    </div>
    <div class="hero-badge">
        <span>☦️</span>
        <span>مدرسة الشهيد إسطفانوس الكنسية - خدمة وتعليم وألحان</span>
    </div>
    <h1 class="hero-title">
        نَحفظ التراث... <br>
        <span style="color:var(--gold);">ونبني جيلًا للخدمة</span>
    </h1>
    <p class="hero-subtitle" style="margin-bottom:1.25rem;">
        نُعلّم أبناءنا ألحان الكنيسة، الطقوس، والتعاليم الروحية، في بيئة تجمع بين محبة الكنيسة وروح الخدمة.
    </p>
    <div style="margin-bottom:2.25rem; display:flex; justify-content:center;">
        <span class="hero-pill-motto" style="display:inline-flex; align-items:center; justify-content:center; text-align:center; gap:0.6rem; background:var(--gold-glow); border:1px solid rgba(217, 119, 6, 0.35); padding:0.45rem 1.6rem; border-radius:50px; font-weight:800; color:var(--gold); font-size:1.05rem; letter-spacing:0.5px; max-width:100%;">
            تعليم • ألحان • طقوس • خدمة
        </span>
    </div>

    <div class="hero-actions">
        <a href="<?= BASE_URL ?>authentication/login.php" class="btn btn-primary hero-btn-primary">
            🔑 تسجيل الدخول
        </a>
        <a href="<?= BASE_URL ?>authentication/register.php" class="btn btn-gold hero-btn-gold">
            📝 إنشاء حساب جديد
        </a>
    </div>
</section>

<!-- About & Overview Section -->
<section class="section-container">
    <div class="section-header">
        <div class="badge badge-gold" style="margin-bottom:0.5rem;">نبذة عامة</div>
        <h2 class="section-title">رسالة مدرسة الشهيد إسطفانوس</h2>
        <p class="section-desc">
            تهدف مدرسة الشهيد إسطفانوس إلى غرس الروحانية الأرثوذكسية الأصيلة، وإتقان ألحان الكنيسة القبطية، وتأهيل جيل متمسك بالطقس الكنسي السليم.
        </p>
    </div>

    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">📚</div>
            <h3>المناهج والكتب الدراسية</h3>
            <p>تحميل وقراءة الكتب ومناهج كل مرحلة دراسية بصيغة PDF مباشرة من النظام.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">📱</div>
            <h3>بطاقة الشماس الرقمية (QR)</h3>
            <p>كارت ذكي لكل شماس يتيح مسح الحضور بالقداسات والتجمعات فورياً بكاميرا هاتف الخادم.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">🏆</div>
            <h3>بنك الطايو ولوحة الأوسمة</h3>
            <p>نظام تحفيزي يكافئ الشماس على الالتزام وحفظ الألحان واستبدال الطايو بهدايا وتشجيعات.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">📝</div>
            <h3>الاختبارات والتصحيح الآلي</h3>
            <p>امتحانات تفاعلية (اختيارات، صح وغلط، توصيل، ومقالي) لرصد المستوى الدراسي بدقة.</p>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="section-container">
    <div class="cta-banner">
        <h2>انضم الآن إلى مدرسة الشهيد إسطفانوس</h2>
        <p>سواء كنت شماساً ترغب في تعلّم الألحان، أو خادماً تسعى لمتابعة فصولك، أو ولي أمر يحرص على متابعة أبنائه روحياً؛ النظام يرحب بك.</p>
        <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>authentication/register.php" class="btn btn-gold" style="padding:0.85rem 2rem; font-size:1.1rem; font-weight:800;">
                📝 إنشاء حساب جديد الآن
            </a>
            <a href="<?= BASE_URL ?>authentication/login.php" class="btn btn-secondary" style="padding:0.85rem 2rem; font-size:1.1rem; font-weight:700;">
                🔑 تسجيل الدخول
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__.'/includes/footer.php'; ?>
