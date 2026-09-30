<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_env_file_is_blocked_by_router()
    {
        $response = $this->get('/.env');
        $response->assertStatus(404);
    }

    public function test_sensitive_database_files_are_blocked_by_router()
    {
        $response = $this->get('/database/database.sqlite');
        $response->assertStatus(404);
    }

    public function test_path_traversal_is_blocked_by_router()
    {
        $response = $this->get('/../../../Windows/win.ini');
        $response->assertStatus(404);
    }

    public function test_composer_and_package_files_are_blocked_by_router()
    {
        $response = $this->get('/composer.json');
        $response->assertStatus(404);

        $response2 = $this->get('/package.json');
        $response2->assertStatus(404);
    }

    public function test_valid_entry_points_are_accessible()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_security_headers_are_present_on_web_responses()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_static_assets_have_caching_and_security_headers()
    {
        $response = $this->get('/assets/css/style.css');
        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_sql_injection_on_login_is_safely_rejected()
    {
        $this->get('/authentication/login.php');
        $token = session()->token();

        // 1. Classic ' OR '1'='1 payload
        $response1 = $this->post('/authentication/login.php', [
            'login_input' => "' OR '1'='1",
            'password' => "' OR '1'='1",
            'csrf_token' => $token,
            '_token' => $token,
        ]);
        $response1->assertSee('بيانات الدخول غير صحيحة');
        $this->assertNull(session('user'));

        // 2. Comment injection payload
        $this->get('/authentication/login.php');
        $token = session()->token();
        $response2 = $this->post('/authentication/login.php', [
            'login_input' => "admin' --",
            'password' => 'secret',
            'csrf_token' => $token,
            '_token' => $token,
        ]);
        $response2->assertSee('بيانات الدخول غير صحيحة');
        $this->assertNull(session('user'));

        // 3. UNION SELECT payload
        $this->get('/authentication/login.php');
        $token = session()->token();
        $response3 = $this->post('/authentication/login.php', [
            'login_input' => "test' UNION SELECT 1,'hacker','hacker@test.com','01000000000','admin','hash',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL --",
            'password' => 'test',
            'csrf_token' => $token,
            '_token' => $token,
        ]);
        $response3->assertSee('بيانات الدخول غير صحيحة');
        $this->assertNull(session('user'));
    }

    public function test_rate_limiting_blocks_brute_force_login_attempts()
    {
        $ip = '192.168.1.155';

        // 5 consecutive failed attempts on the same target
        for ($i = 0; $i < 5; $i++) {
            $this->get('/authentication/login.php');
            $token = session()->token();
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/authentication/login.php', [
                    'login_input' => 'wrong_target_user',
                    'password' => 'wrong_pass_'.$i,
                    'csrf_token' => $token,
                    '_token' => $token,
                ]);
        }

        // 6th attempt on the same target must be throttled
        $this->get('/authentication/login.php');
        $token = session()->token();
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/authentication/login.php', [
                'login_input' => 'wrong_target_user',
                'password' => 'any_pass',
                'csrf_token' => $token,
                '_token' => $token,
            ]);

        $response->assertSee('تم حظر المحاولات مؤقتاً لتجاوز الحد الأقصى للمحاولات الخاطئة');
    }

    public function test_uploads_direct_php_execution_is_blocked()
    {
        $response = $this->get('/uploads/shell.php');
        $response->assertStatus(404);
    }

    public function test_responsive_viewport_and_css_media_queries_exist()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('name="viewport"', false);

        $cssResponse = $this->get('/assets/css/style.css');
        $cssResponse->assertStatus(200);

        $cssContent = file_get_contents(base_path('assets/css/style.css'));
        $this->assertStringContainsString('@media (max-width: 992px)', $cssContent);
        $this->assertStringContainsString('@media (max-width: 768px)', $cssContent);
        $this->assertStringContainsString('@media (max-width: 576px)', $cssContent);
        $this->assertStringContainsString('.sidebar-overlay', $cssContent);
        $this->assertStringContainsString('.sidebar-toggle-btn', $cssContent);
    }

    public function test_whatsapp_notification_api_requires_authorization()
    {
        $response = $this->postJson('/api/whatsapp/attendance', [
            'student_id' => 999,
            'status' => 'present',
        ]);
        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_csv_formula_injection_defense_neutralizes_formulas()
    {
        require_once base_path('includes/helpers.php');

        $malicious = '=cmd|"/C calc"!A0';
        $sanitized = sanitize_csv_cell($malicious);
        $this->assertEquals("'=cmd|\"/C calc\"!A0", $sanitized);

        $row = ['=1+1', '+2-2', '-SUM(A1:A5)', '@HYPERLINK()', 'safe_text'];
        $sanitizedRow = sanitize_csv_row($row);
        $this->assertEquals("'=1+1", $sanitizedRow[0]);
        $this->assertTrue(str_starts_with($sanitizedRow[0], "'"));
        $this->assertTrue(str_starts_with($sanitizedRow[1], "'"));
        $this->assertTrue(str_starts_with($sanitizedRow[2], "'"));
        $this->assertTrue(str_starts_with($sanitizedRow[3], "'"));
        $this->assertEquals('safe_text', $sanitizedRow[4]);
    }

    public function test_user_model_mass_assignment_guards_privileged_columns()
    {
        $user = new User;
        $fillable = $user->getFillable();

        $this->assertNotContains('role', $fillable, 'Privilege escalation: role must not be mass-assignable');
        $this->assertNotContains('status', $fillable, 'Privilege escalation: status must not be mass-assignable');
        $this->assertNotContains('points_balance', $fillable, 'Points tampering: points_balance must not be mass-assignable');
        $this->assertNotContains('qr_code_token', $fillable, 'QR token tampering: qr_code_token must not be mass-assignable');
    }

    public function test_csp_header_is_present_on_web_responses()
    {
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
    }
}
