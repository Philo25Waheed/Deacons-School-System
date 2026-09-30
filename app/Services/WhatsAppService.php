<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public static function generateClickToChatUrl(string $phone, string $message): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '20'.substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '20') === false && strlen($cleanPhone) === 10) {
            $cleanPhone = '20'.$cleanPhone;
        }

        return 'https://api.whatsapp.com/send?phone='.$cleanPhone.'&text='.urlencode($message);
    }

    public static function formatAttendanceMessage(string $studentName, string $status, string $date, string $notes = ''): string
    {
        $statusText = match ($status) {
            'present' => 'حاضر ✅ (بركة كبيرة لمشاركته معنا)',
            'absent' => 'غائب ⚠️ (افتدقناه بشدة في كنيستنا)',
            'late' => 'متأخر ⏰',
            'excused' => 'معتذر بعذر مسبق 📝',
            default => $status
        };

        $msg = "⛪ *مدرسة الشهيد إسطفانوس*\n";
        $msg .= "سلام ونعمة من ربنا يسوع المسيح،\n\n";
        $msg .= "نحيط سيادتكم علماً بتسجيل حضور الابن المبارك/ *{$studentName}*:\n";
        $msg .= "📅 *التاريخ:* {$date}\n";
        $msg .= "📌 *الحالة:* {$statusText}\n";
        if (! empty($notes)) {
            $msg .= "💬 *ملاحظة الخادم:* {$notes}\n";
        }
        $msg .= "\n_نصلي أن يبارك الرب في نموه الروحي والكنسي دائماً._ 🙏";

        return $msg;
    }

    public static function formatExamResultMessage(string $studentName, string $examTitle, float $score, float $totalScore, string $notes = ''): string
    {
        $percentage = ($totalScore > 0) ? round(($score / $totalScore) * 100, 1) : 0;
        $appreciation = ($percentage >= 85) ? 'ممتاز جداً 🌟🥇' : (($percentage >= 75) ? 'جيد جداً 🥈' : (($percentage >= 60) ? 'جيد 👍' : 'يحتاج لمزيد من المراجعة والاهتمام 📖'));

        $msg = "⛪ *مدرسة الشهيد إسطفانوس - نتائج الاختبارات*\n\n";
        $msg .= "مبروك للشماس الحبيب/ *{$studentName}*\n";
        $msg .= "📝 *الاختبار:* {$examTitle}\n";
        $msg .= "🎯 *الدرجة المحصلة:* {$score} من {$totalScore} ({$percentage}%)\n";
        $msg .= "🏆 *التقدير:* {$appreciation}\n";
        if (! empty($notes)) {
            $msg .= "💬 *ملاحظات المصحح:* {$notes}\n";
        }
        $msg .= "\n_مع تمنياتنا له بمزيد من التفوق وحفظ ألحان وطقوس كنيستنا المقدسة._ ✝️";

        return $msg;
    }

    public static function formatRosterReminderMessage(string $studentName, string $liturgyDate, string $liturgyName, string $altarName = 'المذبح الرئيسي'): string
    {
        $msg = "⛪ *مدرسة الشهيد إسطفانوس - تذكير خدمة القداس الإلهي*\n\n";
        $msg .= "سلام ونعمة يا شماسنا الحبيب/ *{$studentName}*،\n";
        $msg .= "نذكرك بموعد خدمتك المباركة في سر الإفخارستيا المقدس:\n";
        $msg .= "📅 *يوم:* {$liturgyDate}\n";
        $msg .= "🕊️ *القداس:* {$liturgyName}\n";
        $msg .= "🕯️ *المذبح:* {$altarName}\n\n";
        $msg .= 'نرجو الحضور مبكراً لارتداء التونية وأخذ البركة مع إخوتك الشمامسة. ربنا يعوض تعب محبتكم! ✝️';

        return $msg;
    }

    public static function sendCloudApiMessage(string $phone, string $message): array
    {
        $token = env('WHATSAPP_CLOUD_API_TOKEN');
        $phoneId = env('WHATSAPP_PHONE_NUMBER_ID');

        if (empty($token) || empty($phoneId)) {
            return [
                'success' => true,
                'mode' => 'click_to_chat',
                'url' => self::generateClickToChatUrl($phone, $message),
            ];
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '01')) {
            $cleanPhone = '20'.substr($cleanPhone, 1);
        }

        try {
            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $cleanPhone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return [
                'success' => $response->successful(),
                'mode' => 'cloud_api',
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => self::generateClickToChatUrl($phone, $message),
            ];
        }
    }
}
