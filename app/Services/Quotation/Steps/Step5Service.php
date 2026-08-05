<?php

namespace App\Services\Quotation\Steps;

use App\Models\BidangPerusahaan;
use App\Models\JenisPerusahaan;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Step5Service
{
    public function __construct(
        protected StepHelperService $helper,
    ) {}

    public function execute(Quotation $quotation, Request $request): void
    {
        foreach ($quotation->quotationDetails as $detail) {
            $detailId = $detail->id;
            $penjamin = $request->penjamin[$detailId] ?? null;

            $nominalTakaful = 0;
            if ($penjamin === 'Asuransi Swasta' || $penjamin === 'Takaful') {
                $nominalTakaful = $request->nominal_takaful[$detailId] ?? 0;
                if (is_string($nominalTakaful)) {
                    $nominalTakaful = (int) str_replace('.', '', $nominalTakaful);
                }
            }

            if ($penjamin === 'BPJS Kesehatan') {
                $penjamin = 'BPJS';
            }

            $isBpu = ($penjamin === 'BPU');

            $detail->update([
                'penjamin_kesehatan' => $penjamin,
                'is_bpjs_jkk' => $isBpu ? 0 : ($this->helper->toBoolean($request->jkk[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jkm' => $isBpu ? 0 : ($this->helper->toBoolean($request->jkm[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jht' => $isBpu ? 0 : ($this->helper->toBoolean($request->jht[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jp'  => $isBpu ? 0 : ($this->helper->toBoolean($request->jp[$detailId]  ?? false) ? 1 : 0),
                'is_bpjs_kes' => $this->helper->toBoolean($request->kes[$detailId] ?? true) ? 1 : 0,
                'nominal_takaful' => $nominalTakaful,
                'updated_by' => Auth::user()->full_name,
            ]);
        }

        $companyData = $this->prepareCompanyData($request);

        $quotationUpdateData = array_merge([
            'is_aktif' => $this->calculateIsAktif($quotation, $request),
            'program_bpjs' => $request->input('program_bpjs', $request->input('program-bpjs')),
            'calculated_at' => null,
            'updated_by' => Auth::user()->full_name,
        ], $companyData);

        $quotation->update($quotationUpdateData);

        if ($quotation->leads) {
            $quotation->leads->update($companyData);
        }
    }

    private function prepareCompanyData(Request $request): array
    {
        $jenisPerusahaanId = $request->input('jenis_perusahaan_id', $request->input('jenis-perusahaan'));
        $bidangPerusahaanId = $request->input('bidang_perusahaan_id', $request->input('bidang-perusahaan'));

        $data = [
            'jenis_perusahaan_id' => $jenisPerusahaanId,
            'bidang_perusahaan_id' => $bidangPerusahaanId,
            'resiko' => $request->input('resiko'),
        ];

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

        if ($isAktif == 2) {
            $isAktif = 1;
        }

        return $isAktif;
    }
}
