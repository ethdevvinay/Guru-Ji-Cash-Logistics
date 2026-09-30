<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_sealing_links_rows_into_a_chain_that_verifies(): void
    {
        $logger = app(AuditLogger::class);
        $first = $logger->record('TEST.ONE');
        $second = $logger->record('TEST.TWO');

        $this->artisan('audit:seal')->assertSuccessful();

        $rows = DB::table('audit_logs')->orderBy('seal_seq')->get();
        $this->assertSame([1, 2], $rows->pluck('seal_seq')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame(str_repeat('0', 64), $rows[0]->prev_hash);
        $this->assertSame($rows[0]->hash, $rows[1]->prev_hash);
        $this->assertSame($first->id, (int) $rows[0]->id);
        $this->assertSame($second->id, (int) $rows[1]->id);

        $this->artisan('audit:verify-chain')->expectsOutputToContain('intact')->assertSuccessful();
    }

    public function test_changing_a_sealed_row_in_the_database_is_detected(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('TEST.ONE', null, null, ['amount_paise' => 4000000]);
        $second = $logger->record('TEST.TWO', null, null, ['amount_paise' => 5000000]);
        $this->artisan('audit:seal')->assertSuccessful();

        DB::table('audit_logs')->where('id', $second->id)->update(['after' => json_encode(['amount_paise' => 9900000])]);

        $this->artisan('audit:verify-chain')->expectsOutputToContain("id={$second->id}")->assertFailed();
    }

    public function test_deleting_a_sealed_row_in_the_database_is_detected(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('TEST.ONE');
        $middle = $logger->record('TEST.TWO');
        $logger->record('TEST.THREE');
        $this->artisan('audit:seal')->assertSuccessful();

        DB::table('audit_logs')->where('id', $middle->id)->delete();

        $this->artisan('audit:verify-chain')->assertFailed();
    }

    public function test_rows_committed_out_of_id_order_still_form_a_valid_chain(): void
    {
        $this->insertRawRow(900);
        $this->artisan('audit:seal')->assertSuccessful();

        $this->insertRawRow(800); // a slower transaction commits later with a lower id
        $this->artisan('audit:seal')->assertSuccessful();

        $this->assertSame(2, (int) DB::table('audit_logs')->where('id', 800)->value('seal_seq'));
        $this->artisan('audit:verify-chain')->assertSuccessful();
    }

    public function test_hindi_text_seals_and_verifies_without_false_alarms(): void
    {
        app(AuditLogger::class)->record('TEST.HINDI', null, null, ['shop_name' => 'राधे डिजिटल स्टोर', 'city' => 'रोहतक']);
        DB::table('audit_logs')->insert([
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'actor_type' => 'SYSTEM',
            'action' => 'TEST.HINDI_RAW',
            'after' => json_encode(['shop_name' => 'गुप्ता ग्राहक सेवा केंद्र'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->artisan('audit:seal')->assertSuccessful();
        $this->artisan('audit:verify-chain')->assertSuccessful();
    }

    public function test_sealing_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('audit:seal')
            ->assertSuccessful();
    }

    private function insertRawRow(int $id): void
    {
        DB::table('audit_logs')->insert([
            'id' => $id,
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'actor_type' => 'SYSTEM',
            'action' => 'TEST.RAW',
            'after' => json_encode(['n' => $id]),
        ]);
    }
}
