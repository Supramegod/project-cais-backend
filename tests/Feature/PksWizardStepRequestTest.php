<?php

namespace Tests\Feature;

use App\Http\Requests\PksWizardStepRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression: PksWizardStepRequest used the non-existent static factory
 * FluentRule::rule('date') / FluentRule::rule('email'), which threw at runtime
 * whenever rules() was built for the date steps and step 4 (PIC emails) — the
 * PKS wizard step 4 could not validate at all. Fixed to FluentRule::date() /
 * FluentRule::email(). This locks that rules() compiles and validates.
 */
class PksWizardStepRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        DB::purge('sqlite');
        DB::purge('mysqlhris');
        DB::purge('mysql');
        DB::reconnect('sqlite');
        DB::reconnect('mysqlhris');
        DB::reconnect('mysql');

        Schema::dropIfExists('sl_pks');
        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipe_pks')->nullable();
            $table->json('wizard_payload')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function rulesForStep(int $step): array
    {
        $request = new PksWizardStepRequest();
        $request->setRouteResolver(fn () => new class($step)
        {
            public function __construct(private int $step) {}

            public function parameter($key, $default = null)
            {
                return ['step' => $this->step, 'pksId' => null][$key] ?? $default;
            }
        });

        // Must not throw (previously FluentRule::rule('date'/'email') did).
        return $request->rules();
    }

    public function test_step2_and_step4_rules_compile_without_throwing(): void
    {
        $step2 = $this->rulesForStep(2);
        $this->assertArrayHasKey('step_data.tanggal_pks', $step2);
        $this->assertArrayHasKey('step_data.tanggal_akhir_kontrak', $step2);

        $step4 = $this->rulesForStep(4);
        $this->assertArrayHasKey('step_data.email_pic_1', $step4);
        $this->assertArrayHasKey('step_data.email_pic_2', $step4);
        $this->assertArrayHasKey('step_data.email_pic_3', $step4);
    }

    public function test_step4_email_rule_actually_validates(): void
    {
        $rules = $this->rulesForStep(4);

        // Valid PIC + email passes.
        $ok = Validator::make(
            ['step_data' => ['pic_1' => 'Budi', 'email_pic_1' => 'budi@example.com']],
            $rules
        );
        $this->assertFalse($ok->fails(), 'Valid step-4 payload should pass');

        // Malformed email fails on email_pic_1.
        $bad = Validator::make(
            ['step_data' => ['pic_1' => 'Budi', 'email_pic_1' => 'not-an-email']],
            $rules
        );
        $this->assertTrue($bad->fails());
        $this->assertArrayHasKey('step_data.email_pic_1', $bad->errors()->toArray());
    }
}
