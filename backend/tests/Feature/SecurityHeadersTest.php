<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_carry_security_headers_and_a_request_id(): void
    {
        $response = $this->actingWithToken(User::factory()->create())->getJson('/api/v1/me');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_not_found_responses_do_not_leak_internal_class_names(): void
    {
        $response = $this->actingWithToken(User::factory()->create())->getJson('/api/v1/posts/01jaaaaaaaaaaaaaaaaaaaaaaa');

        $response->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_malformed_identifiers_never_reach_the_database(): void
    {
        $this->actingWithToken(User::factory()->create())->getJson("/api/v1/posts/1' OR '1'='1")->assertNotFound();
    }

    public function test_unauthenticated_requests_get_json_401(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_cors_does_not_allow_arbitrary_browser_origins(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/v1/me');

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
