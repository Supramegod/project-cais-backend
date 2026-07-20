<?php

namespace App\Jobs;

use App\Mail\ItemFulfillmentReminderNotification;
use App\Models\Pks;
use App\Models\User;
use App\Services\Pks\ItemFulfillmentService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendItemFulfillmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int|null  $onlyPksId  Bila diisi → mode paksa/manual satu PKS (abaikan guard midpoint & reminded).
     */
    public function __construct(
        public ?int $onlyPksId = null,
    ) {}

    public function handle(): void
    {
        $isManual = $this->onlyPksId !== null;

        $query = Pks::query()
            ->where('status_pks_id', 7)
            ->whereNotNull('quotation_id')
            ->whereNotNull('kontrak_awal')
            ->whereNotNull('kontrak_akhir');

        if ($isManual) {
            $query->where('id', $this->onlyPksId);
        } else {
            // Mode scan normal: hanya yang belum pernah diingatkan.
            $query->whereNull('item_fulfillment_reminded_at');
        }

        $candidates = $query->get();
        $now = Carbon::now();
        $sent = 0;

        foreach ($candidates as $pks) {
            try {
                if (! $isManual) {
                    $awal = $pks->kontrak_awal instanceof Carbon ? $pks->kontrak_awal : Carbon::parse($pks->kontrak_awal);
                    $akhir = $pks->kontrak_akhir instanceof Carbon ? $pks->kontrak_akhir : Carbon::parse($pks->kontrak_akhir);

                    // midpoint = kontrak_awal + (kontrak_akhir - kontrak_awal)/2
                    $midpoint = $awal->copy()->addSeconds((int) ($awal->diffInSeconds($akhir) / 2));

                    // (a) sudah melewati titik tengah, (b) belum melewati kontrak_akhir
                    if ($now->lt($midpoint) || $now->gt($akhir)) {
                        continue;
                    }
                }

                $pendingItems = $this->collectPendingItems($pks);
                if (empty($pendingItems)) {
                    continue;
                }

                $emails = $this->resolveRecipients($pks);
                if (empty($emails)) {
                    Log::warning('SendItemFulfillmentReminder: tidak ada penerima email', [
                        'pks_id' => $pks->id,
                        'nomor' => $pks->nomor,
                    ]);
                    continue;
                }

                Mail::to($emails)->send(new ItemFulfillmentReminderNotification($pks, $pendingItems));

                $pks->item_fulfillment_reminded_at = now();
                $pks->save();

                $sent++;
            } catch (\Throwable $e) {
                Log::error('SendItemFulfillmentReminder: gagal memproses PKS', [
                    'pks_id' => $pks->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('SendItemFulfillmentReminder: selesai', [
            'reminder_terkirim' => $sent,
            'kandidat' => $candidates->count(),
            'manual' => $isManual,
        ]);
    }

    /**
     * Kumpulkan item belum terpenuhi (remaining > 0) dari semua site milik PKS.
     *
     * @return array<int, array{nama: string, qty_diminta: int, qty_terpenuhi: int, remaining: int, site: string}>
     */
    private function collectPendingItems(Pks $pks): array
    {
        $service = app(ItemFulfillmentService::class);
        $pending = [];

        foreach ($pks->sites as $site) {
            $items = $service->getRequestedItems($pks, (int) $site->id);
            foreach ($items as $item) {
                if (($item['remaining'] ?? 0) > 0) {
                    $pending[] = [
                        'nama' => $item['nama'] ?? '-',
                        'qty_diminta' => $item['qty_diminta'] ?? 0,
                        'qty_terpenuhi' => $item['qty_terpenuhi'] ?? 0,
                        'remaining' => $item['remaining'] ?? 0,
                        'site' => $site->nama_site ?? ('Site #' . $site->id),
                    ];
                }
            }
        }

        return $pending;
    }

    /**
     * Kumpulkan email PIC dari mysqlhris m_user (query terpisah, tanpa join lintas DB).
     * Fallback ke env('DIR_SALES_EMAIL') bila kosong.
     *
     * @return array<int, string>
     */
    private function resolveRecipients(Pks $pks): array
    {
        $ids = array_values(array_unique(array_filter([
            $pks->crm_id_1,
            $pks->crm_id_2,
            $pks->crm_id_3,
            $pks->ro_id_1,
            $pks->ro_id_2,
            $pks->ro_id_3,
            $pks->spv_ro_id,
        ])));

        $emails = [];
        if (! empty($ids)) {
            $emails = User::whereIn('id', $ids)
                ->whereNotNull('email')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (empty($emails)) {
            $fallback = env('DIR_SALES_EMAIL');
            if (! empty($fallback)) {
                $emails = [$fallback];
            }
        }

        return $emails;
    }
}
