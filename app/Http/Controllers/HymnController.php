<?php

namespace App\Http\Controllers;

use App\Models\Hymn;
use Illuminate\Http\Request;

class HymnController extends Controller
{
    public function index(Request $request)
    {
        $season = $request->query('season');
        $query = Hymn::query();

        if (! empty($season)) {
            $query->where('season', $season);
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('coptic_arabic_text', 'LIKE', "%{$search}%")
                    ->orWhere('arabic_translation', 'LIKE', "%{$search}%");
            });
        }

        $hymns = $query->orderBy('id', 'desc')->get();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'hymns' => $hymns]);
        }

        return view('hymns.index', compact('hymns'));
    }

    public function show($id)
    {
        $hymn = Hymn::find($id);
        if (! $hymn) {
            // Demo fallback if database not seeded
            $hymn = (object) [
                'id' => $id,
                'title' => 'لحن إكإسماروؤوت (Ek-Smaro-out)',
                'season' => 'سنوي / أعياد',
                'coptic_text' => 'Ⲕⲥⲙⲁⲣⲱⲟⲩⲧ ⲁⲗⲏⲑⲱⲥ: ⲛⲉⲙ Ⲡⲉⲕⲓⲱⲧ ⲛ̀ⲁ̀ⲅⲁⲑⲟⲥ: ⲛⲉⲙ Ⲡⲓⲡ̀ⲛⲉⲩⲙⲁ ⲉⲑⲟⲩⲁⲃ: ϫⲉ ⲁⲕⲓ̀ ⲁⲕⲥⲱϯ ⲙ̀ⲙⲟⲛ.',
                'coptic_arabic_text' => 'إك إسماروؤوت أليثوس: نيم بيك يوت إن أغاثوس: نيم بي ابنفما إثؤواب: جي آك إي آك سوتي إممون.',
                'arabic_translation' => 'مبارك أنت بالحقيقة مع أبيك الصالح والروح القدس، لأنك أتيت وخلصتنا.',
                'audio_file' => 'https://www.copticchurch.net/hymns/mp3/eksmaroout.mp3',
            ];
        }

        return view('hymns.player', compact('hymn'));
    }
}
