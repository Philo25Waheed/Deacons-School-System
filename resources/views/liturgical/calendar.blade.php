<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقويم والطقس الكنسي الذكي - مدرسة الشهيد إسطفانوس</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #0b1329; color: #f1f5f9; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .gold-text { color: #d4af37; }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="glass-card p-6 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <span class="text-xs text-amber-400 font-semibold">مدرسة الشهيد إسطفانوس - المحرك الكنسي</span>
                <h1 class="text-2xl font-black gold-text flex items-center gap-2 mt-1">
                    <i class="fa-solid fa-church"></i> التقويم والطقس الكنسي اليومي
                </h1>
            </div>
            <div class="text-left">
                <span class="px-4 py-1.5 rounded-full font-bold text-sm {{ $details['tune_badge'] }}">
                    {{ $details['tune'] }}
                </span>
            </div>
        </div>

        <!-- Date & Season Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="glass-card p-6 rounded-2xl space-y-2 border-r-4 border-amber-500">
                <span class="text-xs text-slate-400">التاريخ القبطي</span>
                <h2 class="text-xl font-bold text-amber-300">{{ $details['coptic_full'] }}</h2>
                <p class="text-sm text-slate-400">الموافق: {{ $details['gregorian_formatted'] }}</p>
            </div>
            <div class="glass-card p-6 rounded-2xl space-y-2 border-r-4 border-sky-500">
                <span class="text-xs text-slate-400">الموسم الطقسي الكنسي</span>
                <h2 class="text-xl font-bold text-sky-300">{{ $details['season'] }}</h2>
                <p class="text-xs text-slate-400">{{ $details['tune_desc'] }}</p>
            </div>
        </div>

        <!-- Synaxarium Story -->
        <div class="glass-card p-6 rounded-2xl space-y-4">
            <h3 class="text-lg font-bold gold-text flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fa-solid fa-book-bible"></i> سنكسار اليوم المقدس
            </h3>
            <div class="space-y-2">
                <h4 class="text-md font-bold text-amber-200">{{ $details['synaxarium']['title'] }}</h4>
                <p class="text-sm text-slate-300 leading-relaxed">{{ $details['synaxarium']['details'] }}</p>
            </div>
        </div>

        <!-- Katamaros Liturgy Readings Overview -->
        <div class="glass-card p-6 rounded-2xl space-y-4">
            <h3 class="text-lg font-bold gold-text flex items-center gap-2 border-b border-slate-700 pb-2">
                <i class="fa-solid fa-cross"></i> قراءات القداس الإلهي (القطمارس)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <span class="font-bold text-amber-400 block mb-1">📖 الإنجيل المقدس</span>
                    <p class="text-slate-300">{{ $details['katamaros']['gospel'] }}</p>
                </div>
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <span class="font-bold text-sky-400 block mb-1">📜 المزمور</span>
                    <p class="text-slate-300">{{ $details['katamaros']['psalm'] }}</p>
                </div>
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <span class="font-bold text-emerald-400 block mb-1">✉️ البولس</span>
                    <p class="text-slate-300">{{ $details['katamaros']['pauline'] }}</p>
                </div>
                <div class="bg-slate-900/60 p-4 rounded-xl border border-slate-800">
                    <span class="font-bold text-purple-400 block mb-1">🕊️ الإبركسيس</span>
                    <p class="text-slate-300">{{ $details['katamaros']['praxis'] }}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
