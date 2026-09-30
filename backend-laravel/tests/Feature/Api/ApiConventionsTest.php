<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\ApiException;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ApiConventionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
            Route::post('validate', fn (Request $request) => ApiResponse::success(
                $request->validate(['amount_paise' => ['required', 'integer', 'min:1']])
            ));
            Route::get('domain-error', fn () => throw new ApiException(
                'PICKUP_ALREADY_ACCEPTED',
                'Pickup already accepted by another collector.',
                409,
            ));
            Route::get('crash', fn () => throw new RuntimeException('secret internal detail'));
        });
    }

    public function test_meta_returns_the_standard_envelope_with_utc_server_time(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonStructure(['success', 'code', 'message', 'data', 'errors', 'meta' => ['server_time', 'request_id']]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $response->json('meta.server_time'));
    }

    public function test_a_valid_client_request_id_is_echoed_and_a_bad_one_is_replaced(): void
    {
        $id = 'c1f0e0a2-7d1b-4c1e-9a55-3f2d1b0a9e77';
        $this->getJson('/api/v1/meta', ['X-Request-Id' => $id])
            ->assertHeader('X-Request-Id', $id)
            ->assertJsonPath('meta.request_id', $id);

        $replaced = $this->getJson('/api/v1/meta', ['X-Request-Id' => 'bad id with spaces'])->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid((string) $replaced));
    }

    public function test_unknown_api_routes_answer_not_found_in_the_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertHeader('X-Request-Id');
    }

    public function test_validation_failures_list_field_rule_and_message(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.0.field', 'amount_paise')
            ->assertJsonPath('errors.0.code', 'REQUIRED');
    }

    public function test_business_errors_keep_their_code_and_status(): void
    {
        $this->getJson('/api/v1/_test/domain-error')
            ->assertStatus(409)
            ->assertJsonPath('code', 'PICKUP_ALREADY_ACCEPTED')
            ->assertJsonPath('message', 'Pickup already accepted by another collector.');
    }

    public function test_unexpected_errors_hide_internal_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/crash')->assertStatus(500)->assertJsonPath('code', 'SERVER_ERROR');

        $this->assertStringNotContainsString('secret internal detail', (string) $response->getContent());
    }

    public function test_wrong_method_answers_method_not_allowed(): void
    {
        $this->postJson('/api/v1/meta')->assertStatus(405)->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
