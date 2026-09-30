<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppNotificationController extends Controller
{
    /**
     * Authorize that the requester is an active servant, admin, or provides a valid API secret.
     */
    private function authorizeNotification(Request $request): bool
    {
        $sessionUser = session('user') ?? ($_SESSION['user'] ?? null);
        if ($sessionUser && in_array($sessionUser['role'] ?? '', ['admin', 'servant'], true)) {
            return true;
        }

        $expectedToken = env('WHATSAPP_API_KEY') ?: env('APP_KEY');
        $bearer = $request->bearerToken() ?: $request->header('X-API-KEY');
        if (! empty($expectedToken) && ! empty($bearer) && hash_equals($expectedToken, $bearer)) {
            return true;
        }

        return false;
    }

    public function notifyAttendance(Request $request)
    {
        if (! $this->authorizeNotification($request)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإرسال إشعارات الواتساب.',
            ], 401);
        }

        $validated = $request->validate([
            'student_name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'regex:/^(\+?20|0)?1[0125][0-9]{8}$/'],
            'status' => 'required|string|in:present,absent,late,excused',
            'date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $date = $validated['date'] ?? date('Y-m-d');
        $notes = $validated['notes'] ?? '';
        $message = WhatsAppService::formatAttendanceMessage($validated['student_name'], $validated['status'], $date, $notes);
        $result = WhatsAppService::sendCloudApiMessage($validated['phone'], $message);

        return response()->json([
            'success' => true,
            'result' => $result,
            'message_preview' => $message,
            'whatsapp_url' => WhatsAppService::generateClickToChatUrl($validated['phone'], $message),
        ]);
    }

    public function notifyExamResult(Request $request)
    {
        if (! $this->authorizeNotification($request)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإرسال إشعارات الواتساب.',
            ], 401);
        }

        $validated = $request->validate([
            'student_name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'regex:/^(\+?20|0)?1[0125][0-9]{8}$/'],
            'exam_title' => 'required|string|max:150',
            'score' => 'required|numeric|min:0',
            'total_score' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $message = WhatsAppService::formatExamResultMessage(
            $validated['student_name'],
            $validated['exam_title'],
            $validated['score'],
            $validated['total_score'],
            $validated['notes'] ?? ''
        );

        $result = WhatsAppService::sendCloudApiMessage($validated['phone'], $message);

        return response()->json([
            'success' => true,
            'result' => $result,
            'message_preview' => $message,
            'whatsapp_url' => WhatsAppService::generateClickToChatUrl($validated['phone'], $message),
        ]);
    }

    public function notifyRoster(Request $request)
    {
        if (! $this->authorizeNotification($request)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإرسال إشعارات الواتساب.',
            ], 401);
        }

        $validated = $request->validate([
            'student_name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'regex:/^(\+?20|0)?1[0125][0-9]{8}$/'],
            'liturgy_date' => 'required|string|max:50',
            'liturgy_name' => 'required|string|max:150',
            'altar_name' => 'nullable|string|max:100',
        ]);

        $message = WhatsAppService::formatRosterReminderMessage(
            $validated['student_name'],
            $validated['liturgy_date'],
            $validated['liturgy_name'],
            $validated['altar_name'] ?? 'المذبح الرئيسي'
        );

        $result = WhatsAppService::sendCloudApiMessage($validated['phone'], $message);

        return response()->json([
            'success' => true,
            'result' => $result,
            'message_preview' => $message,
            'whatsapp_url' => WhatsAppService::generateClickToChatUrl($validated['phone'], $message),
        ]);
    }
}
