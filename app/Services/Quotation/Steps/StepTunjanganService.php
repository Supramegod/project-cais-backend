<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailTunjangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Handles tunjangan data synchronization and BPJS KS updates.
 * Extracted from StepHelperTrait for single responsibility.
 */
class StepTunjanganService
{
    public function __construct(
        protected StepHelperService $helper,
    ) {}

    /**
     * Sync tunjangan data – Optimized batch
     */
    public function syncTunjanganData(Quotation $quotation, array $tunjanganData, Carbon $currentDateTime, string $user): void
    {
        $detailIds = array_keys($tunjanganData);

        $existingTunjangans = QuotationDetailTunjangan::whereIn('quotation_detail_id', $detailIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('quotation_detail_id');

        $insertData = [];
        $updateData = [];

        foreach ($tunjanganData as $detailId => $tunjangans) {
            $existing = $existingTunjangans->get($detailId, collect())->keyBy('nama_tunjangan');
            $processed = [];

            foreach ($tunjangans ?? [] as $item) {
                $nama = trim($item['nama_tunjangan'] ?? '');
                if (empty($nama)) {
                    continue;
                }

                $jenis = $item['jenis'] ?? 'Nominal';

                if (in_array($jenis, ['Normatif', 'Ditagihkan'])) {
                    $nominal = 0;
                    $nominalCoss = 0;
                } else {
                    $nominal = $this->helper->parseNominal($item['nominal'] ?? 0);
                    $nominalCoss = $this->helper->parseNominal($item['nominal_coss'] ?? 0);
                }

                $processed[] = $nama;

                if ($existing->has($nama)) {
                    $updateData[] = [
                        'id' => $existing[$nama]->id,
                        'nominal' => $nominal,
                        'nominal_coss' => $nominalCoss,
                        'jenis' => $jenis,
                        'updated_at' => $currentDateTime,
                        'updated_by' => $user,
                    ];
                } else {
                    $insertData[] = [
                        'quotation_id' => $quotation->id,
                        'quotation_detail_id' => $detailId,
                        'nama_tunjangan' => $nama,
                        'nominal' => $nominal,
                        'nominal_coss' => $nominalCoss,
                        'jenis' => $jenis,
                        'created_at' => $currentDateTime,
                        'created_by' => $user,
                        'created_by_user_id' => Auth::id(),
                    ];
                }
            }

            $toDelete = $existing->keys()->diff($processed);

            if ($toDelete->isNotEmpty()) {
                QuotationDetailTunjangan::where('quotation_detail_id', $detailId)
                    ->whereIn('nama_tunjangan', $toDelete->toArray())
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);
            }
        }

        if (! empty($insertData)) {
            QuotationDetailTunjangan::insert($insertData);
        }

        if (! empty($updateData)) {
            foreach ($updateData as $data) {
                $id = $data['id'];
                unset($data['id']);
                QuotationDetailTunjangan::where('id', $id)->update($data);
            }
        }
    }

    /**
     * Update nominal BPJS KS di HPP dan COSS
     */
    public function updateBpjsKsNominal(Quotation $quotation, array $bpjsKsData, string $user, Carbon $currentDateTime): void
    {
        Log::info('Updating BPJS KS nominal', [
            'quotation_id' => $quotation->id,
            'details_count' => count($bpjsKsData),
        ]);

        foreach ($bpjsKsData as $detailId => $nominalBpjsKs) {
            if (is_string($nominalBpjsKs)) {
                $nominalBpjsKs = (float) str_replace(['.', ','], ['', '.'], $nominalBpjsKs);
            }

            QuotationDetailHpp::where('quotation_detail_id', $detailId)->update([
                'bpjs_ks' => $nominalBpjsKs,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ]);

            QuotationDetailCoss::where('quotation_detail_id', $detailId)->update([
                'bpjs_ks' => $nominalBpjsKs,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ]);
        }

        Log::info('Updated BPJS KS nominal', [
            'quotation_id' => $quotation->id,
        ]);
    }
}
