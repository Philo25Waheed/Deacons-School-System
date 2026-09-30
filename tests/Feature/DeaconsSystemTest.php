<?php

namespace Tests\Feature;

use App\Models\Hymn;
use App\Services\CopticLiturgicalService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeaconsSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_coptic_liturgical_service_returns_valid_data()
    {
        $details = CopticLiturgicalService::getDetails();
        $this->assertIsArray($details);
        $this->assertArrayHasKey('coptic_day', $details);
        $this->assertArrayHasKey('coptic_month_ar', $details);
        $this->assertArrayHasKey('tune', $details);
        $this->assertArrayHasKey('synaxarium', $details);
        $this->assertArrayHasKey('katamaros', $details);
    }

    public function test_liturgical_api_endpoint_returns_json()
    {
        $response = $this->getJson('/api/liturgical/today');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'coptic_day',
                    'coptic_month_ar',
                    'tune',
                    'season',
                    'synaxarium',
                ],
            ]);
    }

    public function test_whatsapp_notification_url_generation()
    {
        $url = WhatsAppService::generateClickToChatUrl('01222222222', 'Test Message');
        $this->assertStringContainsString('https://api.whatsapp.com/send', $url);
        $this->assertStringContainsString('phone=20122222222', $url);
    }

    public function test_whatsapp_attendance_api_endpoint()
    {
        $response = $this->withSession(['user' => ['id' => 1, 'role' => 'servant']])
            ->postJson('/api/whatsapp/attendance', [
                'student_name' => 'الشماس يوسف مينا',
                'phone' => '01222222222',
                'status' => 'present',
                'notes' => 'حضور متميز في القداس',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message_preview',
                'whatsapp_url',
            ]);
    }

    public function test_hymns_api_endpoint()
    {
        Hymn::create([
            'title' => 'لحن إكإسماروؤوت',
            'coptic_arabic_text' => 'إك إسماروؤوت أليثوس',
            'arabic_translation' => 'مبارك أنت بالحقيقة',
            'season' => 'سنوي',
        ]);

        $response = $this->getJson('/api/hymns');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'hymns',
            ]);
    }
}
