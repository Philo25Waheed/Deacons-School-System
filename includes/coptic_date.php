<?php

use App\Services\CopticLiturgicalService;

// Coptic Calendar & Synaxarium Helper (Bridged with CopticLiturgicalService)

function getCopticDateDetails(?string $gregorianDateStr = null): array
{
    if (class_exists('App\Services\CopticLiturgicalService')) {
        $details = CopticLiturgicalService::getDetails($gregorianDateStr);

        return [
            'day' => $details['coptic_day'],
            'month_num' => $details['coptic_month_num'],
            'month_name' => $details['coptic_month_ar'],
            'year' => $details['coptic_year'],
            'full_str' => $details['coptic_full'],
            'tone' => $details['tune'],
            'tone_badge' => $details['tune_badge'],
            'season' => $details['season'],
            'synaxarium' => $details['synaxarium'],
            'katamaros' => $details['katamaros'],
        ];
    }

    $time = $gregorianDateStr ? strtotime($gregorianDateStr) : time();
    $gy = (int) date('Y', $time);
    $gm = (int) date('m', $time);
    $gd = (int) date('d', $time);

    // Precise Julian Day Number calculation
    $a = (int) floor((14 - $gm) / 12);
    $y = $gy + 4800 - $a;
    $m = $gm + 12 * $a - 3;
    $jd = $gd + (int) floor((153 * $m + 2) / 5) + 365 * $y + (int) floor($y / 4) - (int) floor($y / 100) + (int) floor($y / 400) - 32045;

    $copticEpoch = 1825030;
    $days = $jd - $copticEpoch;
    $cycle = (int) floor($days / 1461);
    $rem = $days % 1461;
    if ($rem < 0) {
        $rem += 1461;
        $cycle--;
    }

    if ($rem < 365) {
        $yearInCycle = 0;
        $dayOfYear = $rem;
    } elseif ($rem < 730) {
        $yearInCycle = 1;
        $dayOfYear = $rem - 365;
    } elseif ($rem < 1096) {
        $yearInCycle = 2;
        $dayOfYear = $rem - 730;
    } else {
        $yearInCycle = 3;
        $dayOfYear = $rem - 1096;
    }

    $copticYear = 4 * $cycle + $yearInCycle + 1;
    $cMonth = (int) floor($dayOfYear / 30) + 1;
    $cDay = ($dayOfYear % 30) + 1;
    if ($cMonth > 13) {
        $cMonth = 13;
    }

    $copticMonths = [
        1 => 'توت', 2 => 'بابه', 3 => 'هاتور', 4 => 'كيهك', 5 => 'طوبة', 6 => 'أمشير',
        7 => 'برمهات', 8 => 'برمودة', 9 => 'بشنس', 10 => 'بؤونة', 11 => 'أبيب', 12 => 'مسرى', 13 => 'النسيء',
    ];

    $monthName = $copticMonths[$cMonth] ?? 'توت';

    return [
        'day' => $cDay,
        'month_num' => $cMonth,
        'month_name' => $monthName,
        'year' => $copticYear,
        'full_str' => "{$cDay} {$monthName} {$copticYear} للشهداء الأبرار",
        'tone' => 'سنوي (Annual)',
        'tone_badge' => 'bg-emerald-600 text-white',
        'season' => 'الفترة السنوية للكنيسة القبطية الأرثوذكسية',
    ];
}
