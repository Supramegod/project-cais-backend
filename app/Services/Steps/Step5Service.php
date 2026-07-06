<?php

namespace App\Services\Steps;

use App\Models\BidangPerusahaan;
use App\Models\JenisPerusahaan;
use App\Models\Quotation;
use App\Services\Steps\Traits\StepHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Step5Service
{
    use StepHelperTrait;

    public function execute(Quotation $quotation, Request $request): void
    {
        // Update BPJS data untuk setiap detail (position)
        foreach ($quotation->quotationDetails as $detail) {
            $detailId = $detail->id;
            $penjamin = $request->penjamin[$detailId] ?? null;

            $nominalTakaful = 0;
            if ($penjamin === 'Asuransi Swasta' || $penjamin === 'Takaful') {
                $nominalTakaful = $request->nominal_takaful[$detailId] ?? 0;
                if (is_string($nominalTakaful)) {
                    $nominalTakaful = (int) str_replace('.', '', $nominalTakaful);
                }

                Log::info('Setting Takaful for detail', [
                    'detail_id' => $detailId,
                    'penjamin' => $penjamin,
                    'nominal_takaful' => $nominalTakaful,
                ]);
            }

            if ($penjamin === 'BPJS Kesehatan') {
                $penjamin = 'BPJS';
            }

            $detail->update([
                'penjamin_kesehatan' => $penjamin,
                'is_bpjs_jkk' => $this->toBoolean($request->jkk[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jkm' => $this->toBoolean($request->jkm[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jht' => $this->toBoolean($request->jht[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jp' => $this->toBoolean($request->jp[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_kes' => $this->toBoolean($request->kes[$detailId] ?? true) ? 1 : 0,
                'nominal_takaful' => $nominalTakaful,
                'updated_by' => Auth::user()->full_name,
            ]);
        }

        $companyData = $this->prepareCompanyData($request);

        $quotationUpdateData = array_merge([
            'is_aktif' => $this->calculateIsAktif($quotation, $request),
            'program_bpjs' => $request->input('program-bpjs'),
            'calculated_at' => null,
            'updated_by' => Auth::user()->full_name,
        ], $companyData);

        $quotation->update($quotationUpdateData);

        // Update leads data
        if ($quotation->leads) {
            $quotation->leads->update($companyData);
        }

        Log::info('Step 5 completed with BPJS data', [
            'quotation_id' => $quotation->id,
            'program_bpjs' => $request->input('program-bpjs'),
        ]);
    }

    private function prepareCompanyData(Request $request): array
    {
        $jenisPerusahaanId = $request->input('jenis-perusahaan');
        $bidangPerusahaanId = $request->input('bidang-perusahaan');

        $data = [
            'jenis_perusahaan_id' => $jenisPerusahaanId,
            'bidang_perusahaan_id' => $bidangPerusahaanId,
            'resiko' => $request->input('resiko'),
        ];

        Log::info('Preparing company data', [
            'jenis_perusahaan_id' => $jenisPerusahaanId,
            'bidang_perusahaan_id' => $bidangPerusahaanId,
            'resiko' => $request->input('resiko'),
        ]);

        if ($jenisPerusahaanId) {
            $jenisPerusahaan = JenisPerusahaan::where('id', $jenisPerusahaanId)->first();
            $data['jenis_perusahaan'] = $jenisPerusahaan ? $jenisPerusahaan->nama : null;
        }

        if ($bidangPerusahaanId) {
            $bidangPerusahaan = BidangPerusahaan::where('id', $bidangPerusahaanId)->first();
            $data['bidang_perusahaan'] = $bidangPerusahaan ? $bidangPerusahaan->nama : null;
        }

        return $data;
    }

    private function calculateIsAktif(Quotation $quotation, Request $request): int
    {
        $isAktif = $quotation->is_aktif;

        Log::info('Calculate is_aktif', [
            'current_is_aktif' => $isAktif,
            'new_is_aktif' => ($isAktif == 2) ? 1 : $isAktif,
        ]);

        if ($isAktif == 2) {
            $isAktif = 1;
        }

        return $isAktif;
    }
}
