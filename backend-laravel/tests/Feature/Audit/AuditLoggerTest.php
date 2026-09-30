<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_actor_entity_request_id_and_ip_of_an_api_request(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->post('/api/v1/_test/audit', function (AuditLogger $audit) {
            $user = request()->user();
            $audit->record('TEST.ACTION', $user, ['status' => 'OLD'], ['status' => 'NEW']);

            return ApiResponse::success();
        });
        $user = User::factory()->retailer()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/_test/audit', [], ['X-Request-Id' => 'req-12345678'])->assertOk();

        $log = AuditLog::query()->where('action', 'TEST.ACTION')->sole();
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame('retailer', $log->actor_role);
        $this->assertSame('USER', $log->actor_type);
        $this->assertSame('User', $log->entity_type);
        $this->assertSame($user->public_id, $log->entity_id);
        $this->assertSame('req-12345678', $log->request_id);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertSame(['status' => 'OLD'], $log->before);
        $this->assertSame(['status' => 'NEW'], $log->after);
    }

    public function test_secrets_are_redacted_at_any_depth(): void
    {
        $log = app(AuditLogger::class)->record(
            'TEST.SECRETS',
            null,
            ['password' => 'hunter2'],
            ['nested' => ['token' => 'abc', 'kept' => 1], 'pan' => 'ABCDE1234F'],
        );

        $this->assertSame('[REDACTED]', $log->before['password']);
        $this->assertSame('[REDACTED]', $log->after['nested']['token']);
        $this->assertSame(1, $log->after['nested']['kept']);
        $this->assertSame('[REDACTED]', $log->after['pan']);
    }

    public function test_the_audit_row_rolls_back_with_the_business_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                app(AuditLogger::class)->record('TEST.ROLLED_BACK');
                throw new RuntimeException('business step failed');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'TEST.ROLLED_BACK']);
    }

    public function test_audit_rows_cannot_be_updated_through_the_model(): void
    {
        $log = app(AuditLogger::class)->record('TEST.IMMUTABLE');

        $this->expectException(LogicException::class);

        $log->update(['action' => 'TEST.CHANGED']);
    }

    public function test_audit_rows_cannot_be_deleted_through_the_model(): void
    {
        $log = app(AuditLogger::class)->record('TEST.IMMUTABLE');

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
