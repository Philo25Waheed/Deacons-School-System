<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $hymn->title ?? 'مشغل الألحان التفاعلي' }} - مدرسة الشهيد إسطفانوس</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #0b1329; color: #f1f5f9; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .gold-gradient { background: linear-gradient(135deg, #d4af37 0%, #aa7c11 100%); }
        .gold-text { color: #d4af37; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between glass-card p-4 rounded-2xl">
            <a href="javascript:history.back()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 rounded-xl text-sm font-semibold transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-right"></i> عودة
            </a>
            <div class="text-center">
                <h1 class="text-xl md:text-2xl font-bold gold-text flex items-center justify-center gap-2">
                    <i class="fa-solid fa-music"></i> {{ $hymn->title ?? 'لحن كنسي' }}
                </h1>
                <span class="text-xs px-3 py-1 bg-amber-500/20 text-amber-300 rounded-full border border-amber-500/30">
                    {{ $hymn->season ?? 'طقس سنوي' }}
                </span>
            </div>
            <div class="w-16"></div>
        </div>

        <!-- Audio Player & Controls -->
        <div class="glass-card p-6 rounded-2xl space-y-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <audio id="hymnAudio" class="w-full md:w-2/3" controls>
                    <source src="{{ $hymn->audio_file ?? '#' }}" type="audio/mpeg">
                    متصفحك لا يدعم مشغل الصوت.
                </audio>

                <!-- Speed Controller -->
                <div class="flex items-center gap-2 bg-slate-800/80 p-2 rounded-xl border border-slate-700">
                    <span class="text-xs text-slate-400 font-semibold">السرعة:</span>
                    <button onclick="setSpeed(0.5)" class="speed-btn px-2.5 py-1 text-xs rounded-lg hover:bg-slate-700">0.5x</button>
                    <button onclick="setSpeed(0.75)" class="speed-btn px-2.5 py-1 text-xs rounded-lg hover:bg-slate-700">0.75x</button>
                    <button onclick="setSpeed(1.0)" class="speed-btn px-2.5 py-1 text-xs rounded-lg bg-amber-500 text-slate-950 font-bold">1.0x</button>
                    <button onclick="setSpeed(1.25)" class="speed-btn px-2.5 py-1 text-xs rounded-lg hover:bg-slate-700">1.25x</button>
                </div>
            </div>
        </div>

        <!-- Lyrics Switcher Tabs -->
        <div class="glass-card p-6 rounded-2xl space-y-6">
            <div class="flex border-b border-slate-700 gap-4 pb-2">
                <button onclick="switchTab('coptic_ar')" id="tab_coptic_ar" class="tab-btn pb-2 font-bold gold-text border-b-2 border-amber-500">القبطي المعرب</button>
                <button onclick="switchTab('coptic')" id="tab_coptic" class="tab-btn pb-2 text-slate-400 hover:text-slate-200">الحروف القبطية</button>
                <button onclick="switchTab('arabic')" id="tab_arabic" class="tab-btn pb-2 text-slate-400 hover:text-slate-200">الترجمة العربية</button>
            </div>

            <!-- Content Area -->
            <div id="content_coptic_ar" class="text-xl md:text-2xl leading-loose font-medium text-amber-200 bg-slate-900/50 p-6 rounded-xl border border-slate-800">
                {{ $hymn->coptic_arabic_text ?? 'إك إسماروؤوت أليثوس: نيم بيك يوت إن أغاثوس: نيم بي ابنفما إثؤواب: جي آك إي آك سوتي إممون.' }}
            </div>
            <div id="content_coptic" class="hidden text-xl md:text-2xl leading-loose font-serif text-sky-300 bg-slate-900/50 p-6 rounded-xl border border-slate-800">
                {{ $hymn->coptic_text ?? 'Ⲕⲥⲙⲁⲣⲱⲟⲩⲧ ⲁⲗⲏⲑⲱⲥ: ⲛⲉⲙ Ⲡⲉⲕⲓⲱⲧ ⲛ̀ⲁ̀ⲅⲁⲑⲟⲥ: ⲛⲉⲙ Ⲡⲓⲡ̀ⲛⲉⲩⲙⲁ ⲉⲑⲟⲩⲁⲃ: ϫⲉ ⲁⲕⲓ̀ ⲁⲕⲥⲱϯ ⲙ̀ⲙⲟⲛ.' }}
            </div>
            <div id="content_arabic" class="hidden text-lg md:text-xl leading-relaxed text-slate-300 bg-slate-900/50 p-6 rounded-xl border border-slate-800">
                {{ $hymn->arabic_translation ?? 'مبارك أنت بالحقيقة مع أبيك الصالح والروح القدس، لأنك أتيت وخلصتنا.' }}
            </div>
        </div>

        <!-- Voice Recording Module for Students -->
        <div class="glass-card p-6 rounded-2xl space-y-4">
            <h2 class="text-lg font-bold flex items-center gap-2 gold-text">
                <i class="fa-solid fa-microphone"></i> تسجيل التسميع الصوتي للشماس
            </h2>
            <p class="text-sm text-slate-400">سجل صوتك وأنت تتلو اللحن لإرساله للخادم واستلام التقييم وطايو التشجيع.</p>

            <div class="flex flex-wrap items-center gap-4">
                <button id="recordBtn" onclick="toggleRecord()" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl flex items-center gap-2 shadow-lg transition">
                    <i class="fa-solid fa-circle"></i> <span>ابدأ التسجيل</span>
                </button>
                <audio id="recordedAudio" controls class="hidden"></audio>
                <button id="submitRecordBtn" class="hidden px-5 py-2.5 gold-gradient text-slate-950 font-bold rounded-xl shadow-lg hover:opacity-90 transition">
                    <i class="fa-solid fa-paper-plane"></i> إرسال للخادم للتصحيح
                </button>
            </div>
        </div>
    </div>

    <script>
        const audio = document.getElementById('hymnAudio');
        function setSpeed(speed) {
            if (audio) audio.playbackRate = speed;
            document.querySelectorAll('.speed-btn').forEach(btn => {
                btn.className = 'speed-btn px-2.5 py-1 text-xs rounded-lg hover:bg-slate-700';
            });
            event.target.className = 'speed-btn px-2.5 py-1 text-xs rounded-lg bg-amber-500 text-slate-950 font-bold';
        }

        function switchTab(type) {
            ['coptic_ar', 'coptic', 'arabic'].forEach(t => {
                document.getElementById('content_' + t).classList.add('hidden');
                document.getElementById('tab_' + t).className = 'tab-btn pb-2 text-slate-400 hover:text-slate-200';
            });
            document.getElementById('content_' + type).classList.remove('hidden');
            document.getElementById('tab_' + type).className = 'tab-btn pb-2 font-bold gold-text border-b-2 border-amber-500';
        }

        let mediaRecorder, audioChunks = [], isRecording = false;
        async function toggleRecord() {
            const btn = document.getElementById('recordBtn');
            if (!isRecording) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    audioChunks = [];
                    mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                    mediaRecorder.onstop = () => {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/mp3' });
                        const audioUrl = URL.createObjectURL(audioBlob);
                        const player = document.getElementById('recordedAudio');
                        player.src = audioUrl;
                        player.classList.remove('hidden');
                        document.getElementById('submitRecordBtn').classList.remove('hidden');
                    };
                    mediaRecorder.start();
                    isRecording = true;
                    btn.innerHTML = '<i class="fa-solid fa-square"></i> <span>إيقاف وحفظ</span>';
                    btn.className = 'px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl flex items-center gap-2 animate-pulse';
                } catch (e) {
                    alert('يرجى السماح بالوصول للميكروفون لتسجيل اللحن.');
                }
            } else {
                mediaRecorder.stop();
                isRecording = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> <span>إعادة التسجيل</span>';
                btn.className = 'px-5 py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-xl flex items-center gap-2';
            }
        }
    </script>
</body>
</html>
