<?php

namespace App\Console\Commands;

use App\Jobs\SendItemFulfillmentReminder;
use Illuminate\Console\Command;

class SendItemFulfillmentReminders extends Command
{
    protected $signature = 'pks:send-item-reminders {--pks= : ID PKS tertentu (mode paksa)}';
    protected $description = 'Kirim email pengingat pemenuhan barang untuk PKS aktif yang sudah melewati titik tengah kontrak';

    public function handle(): int
    {
        $pksId = $this->option('pks');
        $pksId = $pksId !== null ? (int) $pksId : null;

        if ($pksId !== null) {
            $this->info("Mode paksa: memproses PKS id={$pksId}...");
        } else {
            $this->info('Mode scan: memproses semua PKS aktif yang sudah melewati titik tengah kontrak...');
        }

        (new SendItemFulfillmentReminder($pksId))->handle();

        $this->info('Selesai. Cek log untuk detail reminder yang terkirim.');

        return self::SUCCESS;
    }
}
