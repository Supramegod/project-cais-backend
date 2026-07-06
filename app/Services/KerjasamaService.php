<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationKerjasama;
use Carbon\Carbon;

/**
 * Centralized service for Kerjasama synchronization logic.
 * Eliminates duplication between QuotationStepService and ProcessQuotationFinalization.
 */
class KerjasamaService
{
    /**
     * Sync kerjasama data for a quotation (used by Step12).
     */
    public function syncKerjasama(Quotation $quotation, array $kerjasamaData, Carbon $currentDateTime, string $user): void
    {
        if (empty($kerjasamaData)) {
            // Soft delete semua jika tidak ada data
            QuotationKerjasama::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user,
                ]);

            return;
        }

        // Siapkan data untuk upsert
        $upsertData = [];
        $incomingIds = [];

        foreach ($kerjasamaData as $item) {
            $perjanjian = trim($item['perjanjian'] ?? '');
            if ($perjanjian === '') {
                continue;
            }

            $record = [
                'quotation_id' => $quotation->id,
                'perjanjian' => $perjanjian,
                'is_delete' => $item['is_delete'] ?? 1,
                'updated_at' => $currentDateTime,
                'updated_by' => $user,
            ];

            if (! empty($item['id'])) {
                $record['id'] = $item['id'];
                $incomingIds[] = $item['id'];
            } else {
                $record['created_at'] = $currentDateTime;
                $record['created_by'] = $user;
            }
            $upsertData[] = $record;
        }

        // Batch upsert (insert or update)
        if (! empty($upsertData)) {
            QuotationKerjasama::upsert(
                $upsertData,
                ['id'],
                ['perjanjian', 'is_delete', 'updated_at', 'updated_by']
            );
        }

        // Soft delete yang tidak ada di incoming
        if (! empty($incomingIds)) {
            QuotationKerjasama::where('quotation_id', $quotation->id)
                ->whereNotIn('id', $incomingIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user,
                ]);
        } else {
            QuotationKerjasama::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user,
                ]);
        }
    }

    /**
     * Sync from finalization step (used by ProcessQuotationFinalization).
     */
    public function syncFromFinalizationStep(Quotation $quotation, array $kerjasamaData, string $user, Carbon $now): void
    {
        $this->syncKerjasama($quotation, $kerjasamaData, $now, $user);
    }

    /**
     * Sync kerjasama from ProcessQuotationFinalization handle method.
     * Handles null kerjasamaData case.
     */
    public function syncFromFinalization(Quotation $quotation, ?array $kerjasamaData, Carbon $now, string $user): void
    {
        if ($kerjasamaData !== null) {
            $this->syncKerjasama($quotation, $kerjasamaData, $now, $user);
        } else {
            QuotationKerjasama::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'deleted_by' => $user,
                ]);
        }
    }
}
