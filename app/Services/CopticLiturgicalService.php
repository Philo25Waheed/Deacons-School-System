<?php

namespace App\Services;

class CopticLiturgicalService
{
    public const MONTHS = [
        1 => ['ar' => 'توت', 'en' => 'Tout'],
        2 => ['ar' => 'بابه', 'en' => 'Baba'],
        3 => ['ar' => 'هاتور', 'en' => 'Hathor'],
        4 => ['ar' => 'كيهك', 'en' => 'Kiahk'],
        5 => ['ar' => 'طوبة', 'en' => 'Toba'],
        6 => ['ar' => 'أمشير', 'en' => 'Amshir'],
        7 => ['ar' => 'برمهات', 'en' => 'Baramhat'],
        8 => ['ar' => 'برمودة', 'en' => 'Baramouda'],
        9 => ['ar' => 'بشنس', 'en' => 'Bashans'],
        10 => ['ar' => 'بؤونة', 'en' => 'Baona'],
        11 => ['ar' => 'أبيب', 'en' => 'Abib'],
        12 => ['ar' => 'مسرى', 'en' => 'Mesra'],
        13 => ['ar' => 'النسيء', 'en' => 'Nasie'],
    ];

    public const TUNE_ANNUAL = 'سنوي (Annual)';

    public const TUNE_KIAHK = 'كيهكي (Kiahk)';

    public const TUNE_FESTIVE = 'فرايحي (Festive)';

    public const TUNE_FASTING = 'صيامي (Fasting)';

    public const TUNE_PASSION = 'حزايني (Passion)';

    public static function getDetails(?string $gregorianDate = null): array
    {
        $timestamp = $gregorianDate ? strtotime($gregorianDate) : time();
        $gYear = (int) date('Y', $timestamp);
        $gMonth = (int) date('m', $timestamp);
        $gDay = (int) date('d', $timestamp);
        $dayOfWeek = (int) date('w', $timestamp);

        $copticDate = self::calculateCopticDate($gYear, $gMonth, $gDay);
        $cDay = $copticDate['day'];
        $cMonth = $copticDate['month'];
        $copticYear = $copticDate['year'];

        $tune = self::determineLiturgicalTune($cMonth, $cDay, $gYear, $gMonth, $gDay, $dayOfWeek);
        $season = self::determineSeason($cMonth, $cDay);
        $synaxarium = self::getSynaxariumEvents($cMonth, $cDay);
        $readings = self::getKatamarosOverview($cMonth, $cDay);

        $monthNameAr = self::MONTHS[$cMonth]['ar'] ?? 'توت';
        $monthNameEn = self::MONTHS[$cMonth]['en'] ?? 'Tout';

        return [
            'gregorian_date' => date('Y-m-d', $timestamp),
            'gregorian_formatted' => date('d F Y', $timestamp),
            'coptic_day' => $cDay,
            'coptic_month_num' => $cMonth,
            'coptic_month_ar' => $monthNameAr,
            'coptic_month_en' => $monthNameEn,
            'coptic_year' => $copticYear,
            'coptic_formatted' => "{$cDay} {$monthNameAr} {$copticYear} ش",
            'coptic_full' => "{$cDay} {$monthNameAr} {$copticYear} للشهداء الأبرار",
            'tune' => $tune['name'],
            'tune_code' => $tune['code'],
            'tune_badge' => $tune['badge'],
            'tune_desc' => $tune['description'],
            'season' => $season,
            'synaxarium' => $synaxarium,
            'katamaros' => $readings,
        ];
    }

    private static function calculateCopticDate(int $gy, int $gm, int $gd): array
    {
        // Convert Gregorian date to Julian Day Number
        $a = (int) floor((14 - $gm) / 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        $jd = $gd + (int) floor((153 * $m + 2) / 5) + 365 * $y + (int) floor($y / 4) - (int) floor($y / 100) + (int) floor($y / 400) - 32045;

        // Coptic epoch JD: 1 Tout 1 AM corresponds to August 29, 284 AD Julian (JDN = 1825030)
        $copticEpoch = 1825030;
        $days = $jd - $copticEpoch;

        // Coptic 4-year cycle is 1461 days: 3 years of 365 days + 1 leap year of 366 days
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

        return ['day' => $cDay, 'month' => $cMonth, 'year' => $copticYear];
    }

    private static function determineLiturgicalTune(int $cMonth, int $cDay, int $gYear, int $gMonth, int $gDay, int $dayOfWeek): array
    {
        if ($cMonth === 4 && $cDay <= 28) {
            return [
                'name' => self::TUNE_KIAHK,
                'code' => 'kiahk',
                'badge' => 'bg-purple-600 text-white',
                'description' => 'نغمة التسبيح والفرح والاستعداد لميلاد مخلص العالم',
            ];
        }

        $isMonthlyAnnunciationFeast = ($cDay === 29 && $cMonth !== 5 && $cMonth !== 6);
        $isNativityParamounOrFeast = ($cMonth === 4 && $cDay >= 29) || ($cMonth === 5 && $cDay <= 13);
        $isFeastOfCross1 = ($cMonth === 1 && $cDay >= 17 && $cDay <= 19);
        $isFeastOfCross2 = ($cMonth === 7 && $cDay === 10);
        $isNayrouz = ($cMonth === 1 && $cDay <= 16);

        if ($isMonthlyAnnunciationFeast || $isNativityParamounOrFeast || $isFeastOfCross1 || $isFeastOfCross2 || $isNayrouz) {
            return [
                'name' => self::TUNE_FESTIVE,
                'code' => 'festive',
                'badge' => 'bg-amber-500 text-white',
                'description' => 'النغمة الفريحية المفرحة لأعياد وتذكارات الخلاص',
            ];
        }

        if ($dayOfWeek === 3 || $dayOfWeek === 5) {
            return [
                'name' => self::TUNE_FASTING,
                'code' => 'fasting',
                'badge' => 'bg-blue-700 text-white',
                'description' => 'نغمة الصوم والخشوع والجهاد الروحي',
            ];
        }

        return [
            'name' => self::TUNE_ANNUAL,
            'code' => 'annual',
            'badge' => 'bg-emerald-600 text-white',
            'description' => 'النغمة السنوية المعتادة لطقس الكنيسة اليومي',
        ];
    }

    private static function determineSeason(int $cMonth, int $cDay): string
    {
        if ($cMonth === 1 && $cDay <= 16) {
            return 'أيام النيروز المباركة (Coptic New Year Season)';
        }
        if ($cMonth === 4) {
            return 'شهر كيهك المريمي (Blessed Kiahk Marian Season)';
        }
        if ($cMonth === 5 && $cDay <= 11) {
            return 'موسم عيد الميلاد المجيد والغطاس وقانا الجليل';
        }
        if ($cMonth === 11 && $cDay === 5) {
            return 'عيد استشهاد القديسين بطرس وبولس (عيد الرسل)';
        }
        if ($cMonth === 12 && $cDay >= 1 && $cDay <= 16) {
            return 'صوم وعيد صعود جسد السيدة العذراء مريم';
        }

        return 'الفترة السنوية للكنيسة القبطية الأرثوذكسية';
    }

    public static function getSynaxariumEvents(int $month, int $day): array
    {
        $database = [
            1 => [
                1 => ['title' => 'عيد النيروز رأس السنة القبطية وتذكار الشهداء', 'details' => 'بداية العام القبطي الجديد واستشهاد القديس يوحنا المعمدان.'],
                17 => ['title' => 'تذكار ظهور الصليب المجيد في عهد الملكة هيلانة', 'details' => 'احتفال الكنيسة بعيد الصليب المقدس وظفره على خشبة العار.'],
            ],
            4 => [
                3 => ['title' => 'دخول السيدة العذراء مريم إلى الهيكل بأورشليم', 'details' => 'تذكار تقديم يواقيم وحنة للعذراء الطاهرة في سن ثلاث سنوات لتخدم في الهيكل.'],
                29 => ['title' => 'عيد الميلاد المجيد لربنا ومخلصنا يسوع المسيح', 'details' => 'ميلاد الكلمة المتجسد في بيت لحم اليهودية وخلاص البشرية.'],
            ],
            5 => [
                11 => ['title' => 'عيد الغطاس المجيد (الظهور الإلهي - الثيؤفانيا)', 'details' => 'عماد السيد المسيح في نهر الأردن من يوحنا المعمدان وظهور الثالوث الأقدس.'],
                13 => ['title' => 'تذكار عرس قانا الجليل', 'details' => 'أولى معجزات السيد المسيح بتحويل الماء إلى خمر نقي وتكريس سر الزيجة.'],
            ],
            8 => [
                23 => ['title' => 'استشهاد أمير الشهداء القديس مارجرجس الروماني', 'details' => 'نوال القديس العظيم مارجرجس إكليل الشهادة بعد جهاد سبع سنوات.'],
            ],
            10 => [
                2 => ['title' => 'ظهور السيدة العذراء بكنيستها بالزيتون عام 1968', 'details' => 'تجلي أم النور فوق قباب كنيسة الزيتون بالقاهرة ورآها الملايين.'],
            ],
            11 => [
                5 => ['title' => 'عيد الرسل واستشهاد القديسين بطرس وبولس', 'details' => 'انتهاء صوم الرسل الأطهار ونوال هامتَي الرسل إكليل الاستشهاد بروما.'],
            ],
            12 => [
                16 => ['title' => 'عيد صعود جسد السيدة العذراء مريم إلى السماء', 'details' => 'إعلان صعود الجسد الطاهر لملكة السمائيين والأرضيين.'],
            ],
        ];

        if (isset($database[$month][$day])) {
            return $database[$month][$day];
        }

        $monthName = self::MONTHS[$month]['ar'] ?? 'توت';

        return [
            'title' => "تذكار قديسي وشهداء يوم {$day} من شهر {$monthName}",
            'details' => 'بركة صلوات وشفاعات القديسين والشهداء المعيد لهم في هذا اليوم المقدس تكون مع جميعنا أمين.',
        ];
    }

    public static function getKatamarosOverview(int $month, int $day): array
    {
        return [
            'pauline' => 'رسالة بولس الرسول (بولس): تعزيات وإرشادات السلوك الروحي والخدمة الشماسية.',
            'catholic' => 'الرسالة الجامعة (الكاثوليكون): الحث على المحبة وحفظ الإيمان المستقيم والقداسة.',
            'praxis' => 'سفر أعمال الرسل الأطهار (الإبركسيس): استمرار عمل الروح القدس وجهاد الرسل.',
            'psalm' => 'مزمور داود النبي: "هللويا، ذوقوا وانظروا ما أطيب الرب، طوبى للإنسان المتكل عليه."',
            'gospel' => 'الإنجيل المقدس بحسب البشير: "أنتم نور العالم، فليضئ نوركم هكذا قدام الناس ليروا أعمالكم الحسنة ويمجدوا أباكم."',
        ];
    }
}
