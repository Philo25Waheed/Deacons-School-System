<?php

// Coptic Calendar & Synaxarium Helper

function getCopticDateDetails(?string $gregorianDateStr = null): array
{
    $time = $gregorianDateStr ? strtotime($gregorianDateStr) : time();
    $year = (int) date('Y', $time);
    $month = (int) date('m', $time);
    $day = (int) date('d', $time);

    // Approximate Coptic Year calculation (e.g. 2026 -> 1742/1743 AM)
    $copticMonths = [
        1 => 'توت (Tout)', 2 => 'بابه (Baba)', 3 => 'هاتور (Hathor)',
        4 => 'كيهك (Kiahk)', 5 => 'طوبة (Toba)', 6 => 'أمشير (Amshir)',
        7 => 'برمهات (Baramhat)', 8 => 'برمودة (Baramouda)', 9 => 'بشنس (Bashans)',
        10 => 'بؤونة (Baona)', 11 => 'أبيب (Abib)', 12 => 'مسرى (Mesra)', 13 => 'النسيء (Nasi)',
    ];

    // Coptic New Year (1 Tout) is Sept 11/12
    $isLeap = ($year % 4 === 3);
    $copticYear = $year - 284;
    if ($month < 9 || ($month === 9 && $day < ($isLeap ? 12 : 11))) {
        $copticYear--;
    }

    // Determine Coptic Month
    if ($month === 9 && $day >= 11) {
        $cMonth = 1;
        $cDay = $day - 10;
    } elseif ($month === 10) {
        $cMonth = ($day <= 10) ? 1 : 2;
        $cDay = ($day <= 10) ? $day + 20 : $day - 10;
    } elseif ($month === 11) {
        $cMonth = ($day <= 9) ? 2 : 3;
        $cDay = ($day <= 9) ? $day + 21 : $day - 9;
    } elseif ($month === 12) {
        $cMonth = ($day <= 9) ? 3 : 4;
        $cDay = ($day <= 9) ? $day + 21 : $day - 9;
    } elseif ($month === 1) {
        $cMonth = ($day <= 8) ? 4 : 5;
        $cDay = ($day <= 8) ? $day + 22 : $day - 8;
    } elseif ($month === 2) {
        $cMonth = ($day <= 7) ? 5 : 6;
        $cDay = ($day <= 7) ? $day + 21 : $day - 7;
    } elseif ($month === 3) {
        $cMonth = ($day <= 9) ? 6 : 7;
        $cDay = ($day <= 9) ? $day + 21 : $day - 9;
    } elseif ($month === 4) {
        $cMonth = ($day <= 8) ? 7 : 8;
        $cDay = ($day <= 8) ? $day + 22 : $day - 8;
    } elseif ($month === 5) {
        $cMonth = ($day <= 8) ? 8 : 9;
        $cDay = ($day <= 8) ? $day + 22 : $day - 8;
    } elseif ($month === 6) {
        $cMonth = ($day <= 7) ? 9 : 10;
        $cDay = ($day <= 7) ? $day + 23 : $day - 7;
    } elseif ($month === 7) {
        $cMonth = ($day <= 7) ? 10 : 11;
        $cDay = ($day <= 7) ? $day + 23 : $day - 7;
    } elseif ($month === 8) {
        $cMonth = ($day <= 6) ? 11 : 12;
        $cDay = ($day <= 6) ? $day + 24 : $day - 6;
    } else {
        $cMonth = 1;
        $cDay = 1;
    }

    // Liturgical Tone (فرايحي، صيامي، سنوي)
    $tone = 'سنوي (Annual)';
    if ($cMonth === 4) {
        $tone = 'كيهكي (Kiahk Tune)';
    } elseif (date('w', $time) == 5 || date('w', $time) == 3) {
        $tone = 'صيامي (Fasting)';
    }

    return [
        'day' => $cDay,
        'month_num' => $cMonth,
        'month_name' => $copticMonths[$cMonth] ?? 'توت',
        'year' => $copticYear,
        'full_str' => "{$cDay} {$copticMonths[$cMonth]} {$copticYear} للشهداء",
        'tone' => $tone,
    ];
}
