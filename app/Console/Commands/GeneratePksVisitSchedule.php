<?php

namespace App\Console\Commands;

use App\Models\Pks;
use App\Services\Pks\Fulfillment\VisitSchedulingService;
use Illuminate\Console\Command;

class GeneratePksVisitSchedule extends Command
{
    protected $signature = 'pks:generate-visit-schedule {pks_id : ID PKS yang akan digenerate jadwalnya}';
    protected $description = 'Generate jadwal visit untuk PKS yang sudah aktif (backfill)';

    public function handle(VisitSchedulingService $service): int
    {
        $pks = Pks::find($this->argument('pks_id'));

        if (!$pks) {
            $this->error('PKS not found.');
            return self::FAILURE;
        }

        if ((int) $pks->status_pks_id !== 7) {
            $this->error('PKS belum aktif (status_pks_id != 7).');
            return self::FAILURE;
        }

        if (!$pks->quotation_id) {
            $this->error('PKS tidak memiliki quotation_id.');
            return self::FAILURE;
        }

        $this->info("PKS: {$pks->nomor}");
        $this->info("Kontrak: {$pks->kontrak_awal} - {$pks->kontrak_akhir}");

        $this->info('Step 1: Snapshot targets...');
        $service->snapshotTargets($pks);
        $targets = \DB::table('sl_pks_visit_target')->where('pks_id', $pks->id)->get();
        foreach ($targets as $t) {
            $this->info("  Role: {$t->role} | target_total: {$t->target_total}");
        }

        $this->info('Step 2: Generate schedules...');
        $schedules = $service->generateSchedules($pks);
        $this->info("  {$schedules->count()} jadwal dibuat.");

        $this->info('SELESAI. Silakan test endpoint visit.');

        return self::SUCCESS;
    }
}
