<?php
$pageTitle = 'فلاش كاردز الحروف واللغة القبطية';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/helpers.php';

require_role('student', 'servant', 'admin', 'parent');

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';
?>

<style>
.flashcards-container {
    max-width: 1200px;
    margin: 0 auto;
}

.flashcard-tabs {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.flashcard-tab-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    border: 2px solid var(--border-color);
    background: var(--bg-surface);
    color: var(--text-primary);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}

.flashcard-tab-btn:hover {
    border-color: var(--royal-blue);
}

.flashcard-tab-btn.active {
    background: var(--royal-blue);
    color: #ffffff;
    border-color: var(--royal-blue);
    box-shadow: 0 4px 15px var(--royal-blue-glow);
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

/* 3D Flip Card Animation */
.coptic-card {
    background-color: transparent;
    height: 290px;
    perspective: 1000px;
    cursor: pointer;
}

.coptic-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    text-align: center;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
}

.coptic-card.flipped .coptic-card-inner {
    transform: rotateY(180deg);
}

.card-front, .card-back {
    position: absolute;
    width: 100%;
    height: 100%;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    border-radius: var(--radius);
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-sm);
    border: 2px solid var(--border-color);
}

.card-front {
    background: var(--bg-card);
    backdrop-filter: blur(12px);
}

.card-front:hover {
    border-color: var(--gold);
    box-shadow: 0 8px 25px var(--gold-glow);
}

.card-back {
    background: linear-gradient(145deg, var(--bg-surface) 0%, var(--royal-blue-glow) 100%);
    transform: rotateY(180deg);
    border-color: var(--royal-blue);
}

.coptic-char-large {
    font-size: 3.5rem;
    font-weight: 900;
    color: var(--royal-blue);
    font-family: 'Times New Roman', serif;
    margin: 0.5rem 0;
    text-shadow: 0 2px 10px rgba(30, 58, 138, 0.2);
}

.coptic-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--text-primary);
}

.coptic-val-badge {
    background: var(--gold-glow);
    color: var(--gold);
    padding: 0.2rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.flip-hint {
    font-size: 0.78rem;
    color: var(--text-muted);
}

/* Quiz Mode Modal/Card */
.quiz-box {
    display: none;
    background: var(--bg-card);
    border: 2px solid var(--gold);
    border-radius: var(--radius);
    padding: 2.5rem 2rem;
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
    box-shadow: var(--shadow-md);
}

.quiz-option-btn {
    width: 100%;
    padding: 0.85rem;
    margin: 0.5rem 0;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: var(--radius-sm);
    border: 2px solid var(--border-color);
    background: var(--bg-surface);
    cursor: pointer;
    transition: var(--transition);
}

.quiz-option-btn:hover {
    border-color: var(--royal-blue);
    background: var(--royal-blue-glow);
}
</style>

<div class="app-container">
    <?php require_once __DIR__.'/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="flashcards-container">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <h1 style="color:var(--royal-blue); font-weight:800; margin-bottom:0.35rem;">
                        🔤 فلاش كاردز الحروف واللغة القبطية
                    </h1>
                    <p style="color:var(--text-muted); font-size:0.95rem;">
                        بطاقات تفاعلية ذكية لحفظ حروف اللغة القبطية وقواعد النطق وأهم مصطلحات القداس الإلهي. اضغط على أي بطاقة لقلبها واكتشاف طريقة النطق!
                    </p>
                </div>

                <button onclick="startQuizMode()" class="btn btn-gold" style="font-weight:800; padding:0.75rem 1.5rem;">
                    🧠 وضع الاختبار السريع
                </button>
            </div>

            <!-- Categories Tabs -->
            <div class="flashcard-tabs">
                <button class="flashcard-tab-btn active" onclick="filterCards('all', this)">كل الحروف (32 حرف)</button>
                <button class="flashcard-tab-btn" onclick="filterCards('vowels', this)">حروف الحركة (Vowels)</button>
                <button class="flashcard-tab-btn" onclick="filterCards('coptic_only', this)">الحروف ذات الأصل القبطي (7 حروف)</button>
                <button class="flashcard-tab-btn" onclick="filterCards('liturgical', this)">مصطلحات القداس والتسبحة</button>
                <button class="flashcard-tab-btn" onclick="filterCards('numbers', this)">الأرقام القبطية</button>
            </div>

            <!-- Quiz Box -->
            <div id="quizBox" class="quiz-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <span class="badge badge-gold" id="quizScore">الطايو: 0</span>
                    <button onclick="exitQuizMode()" class="btn btn-secondary btn-sm">✕ خروج من الاختبار</button>
                </div>
                <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:0.5rem;">ما هو اسم أو نطق هذا الحرف القبطي؟</div>
                <div class="coptic-char-large" id="quizQuestionChar" style="font-size:5rem; margin:1rem 0;">Ⲁⲁ</div>
                <div id="quizOptionsGrid" style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-top:1.5rem;"></div>
            </div>

            <!-- Flashcards Grid -->
            <div class="cards-grid" id="cardsGrid">
                <!-- Dynamically rendered via JavaScript below -->
            </div>
        </div>
    </main>
</div>

<script>
const copticData = [
    // Alphabet
    { char: "Ⲁ ⲁ", name: "ألفا (Alfa)", cat: "vowels", val: "1", sound: "يُنطق دائماً: (أَ / ألف مفتوحة)", ex: "Ⲁⲅⲓⲟⲥ (آجيوس)", exAr: "قدوس" },
    { char: "Ⲃ ⲃ", name: "فيتا (Vita)", cat: "alphabet", val: "2", sound: "يُنطق: (ف) إذا جاء بعده حرف متحرك، و(ب) فيما عدا ذلك", ex: "Ⲃⲏⲑⲗⲉⲉⲙ (بيت لحم)", exAr: "بيت لحم" },
    { char: "Ⲅ ⲅ", name: "غما (Gamma)", cat: "alphabet", val: "3", sound: "يُنطق: (ج) بعده متحرك كسر، (ن) بعده حلقي، و(غ) غالباً", ex: "Ⲁⲅⲅⲉⲗⲟⲥ (أنجيلوس)", exAr: "ملاك" },
    { char: "Ⲇ ⲇ", name: "دلتا (Delta)", cat: "alphabet", val: "4", sound: "يُنطق: (د) في أسماء الأعلام، و(ذ) في باقي الكلمات اليونانية", ex: "Ⲇⲁⲩⲓⲇ (دافيد)", exAr: "داود" },
    { char: "Ⲉ ⲉ", name: "إي (Ei)", cat: "vowels", val: "5", sound: "حرف متحرك للفتح الخفيف يُنطق: (إي قصيرة مثل الفتحة)", ex: "Ⲉⲕⲕⲗⲏⲥⲓⲁ (إكليسيا)", exAr: "كنيسة" },
    { char: "Ⲋ ⲋ", name: "سو (Soou)", cat: "numbers", val: "6", sound: "رقم 6 قبطي ويستعمل كحرف عددي", ex: "Ⲡⲓⲥⲟⲟⲩ (بي سوو)", exAr: "الرقم ستة" },
    { char: "Ⲍ ⲍ", name: "زيتا (Zita)", cat: "alphabet", val: "7", sound: "يُنطق دائماً: (ز)", ex: "Ⲍⲏⲛⲱⲛ (زينون)", exAr: "زينون" },
    { char: "Ⲏ ⲏ", name: "إيتا (Hita)", cat: "vowels", val: "8", sound: "حرف متحرك للكسر الطويل يُنطق: (ياء طويلة)", ex: "Ⲓⲏⲥⲟⲩⲥ (إيسوس)", exAr: "يسوع" },
    { char: "Ⲑ ⲑ", name: "ثيتا (Thita)", cat: "alphabet", val: "9", sound: "يُنطق: (ت) إذا سبقه (س، ش، ت)، و(ث) فيما عدا ذلك", ex: "Ⲑⲉⲟⲥ (ثيئوس)", exAr: "الله" },
    { char: "Ⲓ ⲓ", name: "يوطا (Iota)", cat: "vowels", val: "10", sound: "حرف متحرك للكسر القصير يُنطق: (ياء قصيرة / كسرة)", ex: "Ⲓⲱⲁⲛⲛⲏⲥ (يؤانس)", exAr: "يوحنا" },
    { char: "Ⲕ ⲕ", name: "كابا (Kapa)", cat: "alphabet", val: "20", sound: "يُنطق دائماً: (ك)", ex: "Ⲕⲩⲣⲓⲟⲥ (كيريوس)", exAr: "يا رب" },
    { char: "Ⲗ ⲗ", name: "لافلا (Laula)", cat: "alphabet", val: "30", sound: "يُنطق دائماً: (ل)", ex: "Ⲗⲁⲟⲥ (لاؤس)", exAr: "شعب" },
    { char: "Ⲙ ⲙ", name: "مي (Mey)", cat: "alphabet", val: "40", sound: "يُنطق دائماً: (م)", ex: "Ⲙⲁⲣⲓⲁ (ماريا)", exAr: "مريم" },
    { char: "Ⲛ ⲛ", name: "ني (Ney)", cat: "alphabet", val: "50", sound: "يُنطق دائماً: (ن)", ex: "Ⲛⲟⲩϯ (نوتي)", exAr: "الله" },
    { char: "Ⲝ ⲝ", name: "إكسي (Eksy)", cat: "alphabet", val: "60", sound: "حرف مركب يُنطق: (ك + س)", ex: "Ⲇⲟⲝⲁ (دوكصا)", exAr: "مجد" },
    { char: "Ⲟ ⲟ", name: "أو (O قصيرة)", cat: "vowels", val: "70", sound: "حرف متحرك للضم القصير يُنطق: (واو قصيرة)", ex: "Ⲟⲩⲣⲟ (أورو)", exAr: "ملك" },
    { char: "Ⲡ ⲡ", name: "بي (Pi)", cat: "alphabet", val: "80", sound: "يُنطق: (ب ثقيلة P)", ex: "Ⲡⲉⲧⲣⲟⲥ (بيتروس)", exAr: "بطرس" },
    { char: "Ⲣ ⲣ", name: "رو (Ro)", cat: "alphabet", val: "100", sound: "يُنطق دائماً: (ر)", ex: "Ⲣⲁⲛ (ران)", exAr: "اسم" },
    { char: "Ⲥ ⲥ", name: "سيما (Sima)", cat: "alphabet", val: "200", sound: "يُنطق: (س) غالباً، و(ز) في بعض الكلمات اليونانية", ex: "Ⲥⲱⲧⲏⲣ (سوتير)", exAr: "مخلّص" },
    { char: "Ⲧ ⲧ", name: "تاف (Tav)", cat: "alphabet", val: "300", sound: "يُنطق: (ت) غالباً، و(د) بعد حرف الني في الكلمات اليونانية", ex: "Ⲧⲁϫⲣⲟ (تاجرو)", exAr: "ثبّت / قوّي" },
    { char: "Ⲩ ⲩ", name: "إبسلون (Epsilon)", cat: "vowels", val: "400", sound: "يُنطق: (ف) بعد (أو، إي)، (و) بعد (أو)، و(ي) فيما عدا ذلك", ex: "Ⲩⲓⲟⲥ (إيوس)", exAr: "ابن" },
    { char: "Ⲫ ⲫ", name: "في (Fy)", cat: "alphabet", val: "500", sound: "يُنطق: (ف)", ex: "Ⲫⲛⲟⲩϯ (إفنوتي)", exAr: "الله" },
    { char: "Ⲭ ⲭ", name: "كي (Khy)", cat: "alphabet", val: "600", sound: "يُنطق: (ك) في القبطي، (ش/خ) في اليوناني حسب الحرف التالي", ex: "Ⲭⲣⲓⲥⲧⲟⲥ (خرستوس)", exAr: "المسيح" },
    { char: "Ⲯ ⲯ", name: "إبسي (Psy)", cat: "alphabet", val: "700", sound: "حرف مركب يُنطق: (ب + س)", ex: "Ⲯⲁⲗⲙⲟⲥ (بصالموس)", exAr: "مزمور" },
    { char: "Ⲱ ⲱ", name: "أوميجا (Oou طويلة)", cat: "vowels", val: "800", sound: "حرف متحرك للضم الطويل يُنطق: (واو ممدودة O)", ex: "Ⲱⲥⲁⲛⲛⲁ (أوصنا)", exAr: "خلّصنا" },
    
    // 7 Coptic-only Letters
    { char: "Ϣ ϣ", name: "شاي (Shai)", cat: "coptic_only", val: "-", sound: "مأخوذ من الديموطيقية ويُنطق دائماً: (ش)", ex: "Ϣⲗⲏⲗ (شليل)", exAr: "صلاة / صلّوا" },
    { char: "Ϥ ϥ", name: "فاي (Fai)", cat: "coptic_only", val: "-", sound: "مأخوذ من الديموطيقية ويُنطق دائماً: (ف)", ex: "Ϥⲁⲓ (فاي)", exAr: "حامل / رافع" },
    { char: "Ϧ ϧ", name: "خاي (Khei)", cat: "coptic_only", val: "-", sound: "مأخوذ من الديموطيقية ويُنطق دائماً: (خ)", ex: "Ϧⲉⲛ (خين)", exAr: "في / بواسطة" },
    { char: "Ϩ ϩ", name: "هوري (Hori)", cat: "coptic_only", val: "-", sound: "مأخوذ من الديموطيقية ويُنطق دائماً: (هـ)", ex: "Ϩⲱⲥ (هوس)", exAr: "تسبيح" },
    { char: "Ϫ ϫ", name: "جانجا (Janja)", cat: "coptic_only", val: "-", sound: "يُنطق: (ج معطشة) إذا جاء بعده حرف متحرك للكسر، و(ج مصرية) فيما عدا ذلك", ex: "Ϫⲱ (جو)", exAr: "قل / رتّل" },
    { char: "Ϭ ϭ", name: "تشيما (Tshima)", cat: "coptic_only", val: "-", sound: "يُنطق: (ت + ش / Ch)", ex: "Ϭⲟⲓⲥ (تشويس)", exAr: "رب" },
    { char: "Ϯ ϯ", name: "تي (Ti)", cat: "coptic_only", val: "-", sound: "مأخوذ من الديموطيقية وهو مقطع يُنطق: (ت + ي)", ex: "Ϯⲁⲅⲓⲁ (تي آجيا)", exAr: "القديسة" },

    // Liturgical Words
    { char: "Ⲁⲗⲗⲏⲗⲟⲩⲓⲁ", name: "الليلويا (Alleluia)", cat: "liturgical", val: "تسبحة", sound: "النطق: أليلويا", ex: "Ⲁⲗⲗⲏⲗⲟⲩⲓⲁ", exAr: "هللويا (سبحوا الله)" },
    { char: "Ⲕⲩⲣⲓⲉ ⲉⲗⲉⲏⲥⲟⲛ", name: "كيرياليصون", cat: "liturgical", val: "مرد قداس", sound: "النطق: كيريي إليسون", ex: "Ⲕⲩⲣⲓⲉ ⲉⲗⲉⲏⲥⲟⲛ", exAr: "يا رب ارحم" },
    { char: "Ⲭⲉⲣⲉ ⲛⲉ Ⲙⲁⲣⲓⲁ", name: "شيري ني ماريا", cat: "liturgical", val: "لحن للثيؤطوكية", sound: "النطق: شيري ني ماريا", ex: "Ⲭⲉⲣⲉ ⲛⲉ Ⲙⲁⲣⲓⲁ", exAr: "السلام لكِ يا مريم" },
    { char: "Ⲁⲝⲓⲟⲥ", name: "آكسيوس (Axios)", cat: "liturgical", val: "رسامة شماس", sound: "النطق: آكسيوس", ex: "Ⲁⲝⲓⲟⲥ ⲡⲓⲇⲓⲁⲕⲱⲛ", exAr: "مستحق الشماس" },
    { char: "Ⲁⲙⲏⲛ", name: "آمين (Amen)", cat: "liturgical", val: "ختام الصلوات", sound: "النطق: آمين", ex: "Ⲁⲙⲏⲛ Ⲁⲗⲗⲏⲗⲟⲩⲓⲁ", exAr: "حقاً / استجب يا رب" }
];

function renderCards(filter = 'all') {
    const grid = document.getElementById('cardsGrid');
    grid.innerHTML = '';

    const filtered = (filter === 'all') 
        ? copticData 
        : copticData.filter(d => d.cat === filter || (filter === 'vowels' && d.cat === 'vowels') || (filter === 'numbers' && d.val !== '-'));

    filtered.forEach((item, idx) => {
        const card = document.createElement('div');
        card.className = 'coptic-card';
        card.onclick = () => card.classList.toggle('flipped');

        card.innerHTML = `
            <div class="coptic-card-inner">
                <div class="card-front">
                    <span class="coptic-val-badge">القيمة: ${item.val}</span>
                    <div class="coptic-char-large">${item.char}</div>
                    <div class="coptic-name">${item.name}</div>
                    <span class="flip-hint">👆 اضغط لمعرفة النطق والقواعد</span>
                </div>
                <div class="card-back">
                    <span class="badge badge-info" style="font-size:0.75rem;">قاعدة النطق</span>
                    <p style="font-size:0.92rem; font-weight:700; color:var(--text-primary); margin:0.75rem 0; line-height:1.6;">${item.sound}</p>
                    <div style="background:var(--bg-surface); padding:0.6rem 0.85rem; border-radius:var(--radius-sm); width:100%; border:1px solid var(--border-color);">
                        <strong style="color:var(--royal-blue); font-size:1.1rem; font-family:'Times New Roman', serif;">${item.ex}</strong>
                        <div style="font-size:0.85rem; color:var(--gold); font-weight:800; margin-top:0.2rem;">المعنى: ${item.exAr}</div>
                    </div>
                    <span class="flip-hint" style="color:var(--royal-blue);">🔄 اضغط للعودة</span>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}

function filterCards(filter, btn) {
    document.querySelectorAll('.flashcard-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderCards(filter);
}

// Quiz Mode Logic
let currentScore = 0;
let quizPool = [];
let currentQ = null;

function startQuizMode() {
    document.getElementById('cardsGrid').style.display = 'none';
    document.querySelector('.flashcard-tabs').style.display = 'none';
    document.getElementById('quizBox').style.display = 'block';
    currentScore = 0;
    document.getElementById('quizScore').innerText = `الطايو: ${currentScore}`;
    quizPool = [...copticData.filter(d => d.val !== 'تسبحة' && d.val !== 'مرد قداس')];
    nextQuestion();
}

function exitQuizMode() {
    document.getElementById('quizBox').style.display = 'none';
    document.getElementById('cardsGrid').style.display = 'grid';
    document.querySelector('.flashcard-tabs').style.display = 'flex';
}

function nextQuestion() {
    if (quizPool.length === 0) {
        quizPool = [...copticData.filter(d => d.val !== 'تسبحة' && d.val !== 'مرد قداس')];
    }
    const randIdx = Math.floor(Math.random() * quizPool.length);
    currentQ = quizPool[randIdx];

    document.getElementById('quizQuestionChar').innerText = currentQ.char;

    // Generate 3 random wrong choices
    const wrongChoices = copticData
        .filter(d => d.name !== currentQ.name)
        .sort(() => 0.5 - Math.random())
        .slice(0, 3)
        .map(d => d.name);

    const choices = [currentQ.name, ...wrongChoices].sort(() => 0.5 - Math.random());

    const optionsGrid = document.getElementById('quizOptionsGrid');
    optionsGrid.innerHTML = '';
    choices.forEach(ch => {
        const b = document.createElement('button');
        b.className = 'quiz-option-btn';
        b.innerText = ch;
        b.onclick = () => checkAnswer(ch, b);
        optionsGrid.appendChild(b);
    });
}

function checkAnswer(chosen, btn) {
    if (chosen === currentQ.name) {
        btn.style.background = '#22c55e';
        btn.style.color = '#fff';
        currentScore += 10;
        document.getElementById('quizScore').innerText = `الطايو: ${currentScore} 🌟 أحسنت!`;
        setTimeout(() => nextQuestion(), 800);
    } else {
        btn.style.background = '#ef4444';
        btn.style.color = '#fff';
        alert(`إجابة غير صحيحة! الحرف هو: ${currentQ.name}`);
        setTimeout(() => nextQuestion(), 600);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderCards('all');
});
</script>

<?php require_once __DIR__.'/../includes/footer.php'; ?>
