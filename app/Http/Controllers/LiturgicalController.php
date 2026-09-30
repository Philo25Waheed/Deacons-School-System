<?php

namespace App\Http\Controllers;

use App\Services\CopticLiturgicalService;
use Illuminate\Http\Request;

class LiturgicalController extends Controller
{
    public function today(Request $request)
    {
        $date = $request->query('date', date('Y-m-d'));
        $details = CopticLiturgicalService::getDetails($date);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $details,
            ]);
        }

        return view('liturgical.calendar', compact('details'));
    }

    public function synaxarium(Request $request)
    {
        $month = (int) $request->query('month', 1);
        $day = (int) $request->query('day', 1);

        $events = CopticLiturgicalService::getSynaxariumEvents($month, $day);

        return response()->json([
            'success' => true,
            'coptic_month' => $month,
            'coptic_day' => $day,
            'events' => $events,
        ]);
    }
}
