<?php

namespace App\Services\Quotation\Steps;

use App\Enums\JenisKontrak;
use App\Models\BidangPerusahaan;
use App\Models\JenisPerusahaan;
use App\Models\Quotation;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Step5Service
{
    public function __construct(
        protected StepHelperService $helper,
    ) {}

    /**
     * Aturan is_bpjs_kes, berurutan:
     *
     * 1. Kalau frontend mengirim kes[detailId], itu yang menang.
     * 2. Kalau absen dan kontraknya GC/PKHL, nilai tersimpan dipertahankan.
     * 3. Kalau absen dan kontraknya selain itu, dipaksa aktif.
     *
     * Butir 2 yang membuat BPJS Kesehatan default mati pada GC/PKHL, tapi
     * mekanismenya ada di DATABASE, bukan di sini: sl_quotation_detail
     * .is_bpjs_kes bertipe tinyint(1) NULL DEFAULT 0, jadi detail yang baru
     * dibuat sudah bernilai 0 dan tinggal dipertahankan. Konsekuensinya nilai 0
     * "bawaan" tidak bisa dibedakan dari opt-out yang disengaja — dan memang
     * tidak perlu, karena keduanya berarti sama: jangan dibebankan.
     *
     * Butir 2 juga yang melindungi quotation GC/PKHL lama dari perubahan harga
     * diam-diam ketika frontend belum mengirim field kes. Butir 3 sengaja
     * dipertahankan seperti perilaku lama karena di luar GC/PKHL BPJS Kesehatan
     * wajib, sehingga nilai 0 warisan harus tetap dinormalkan.
     *
     * ?? false di bawah hanya penjaga untuk baris warisan yang benar-benar
     * NULL (sudah sangat langka sejak kolomnya punya default).
     */
    public function execute(Quotation $quotation, Request $request): void
    {
        $isBpjsKesOpsional = JenisKontrak::isBpjsKesOpsional($quotation->jenis_kontrak);

        $detailIdsBpjsBerubah = [];

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
            $fallbackBpjsKes = $isBpjsKesOpsional ? ($detail->is_bpjs_kes ?? false) : true;

            $flagBpjs = [
                'is_bpjs_jkk' => $isBpu ? 0 : ($this->helper->toBoolean($request->jkk[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jkm' => $isBpu ? 0 : ($this->helper->toBoolean($request->jkm[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jht' => $isBpu ? 0 : ($this->helper->toBoolean($request->jht[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_jp' => $isBpu ? 0 : ($this->helper->toBoolean($request->jp[$detailId] ?? false) ? 1 : 0),
                'is_bpjs_kes' => $this->helper->toBoolean(
                    $request->kes[$detailId] ?? $fallbackBpjsKes
                ) ? 1 : 0,
            ];

            if ($this->faktorBpjsBerubah($detail, $flagBpjs, $penjamin, $nominalTakaful)) {
                $detailIdsBpjsBerubah[] = $detailId;
            }

            $detail->update(array_merge($flagBpjs, [
                'penjamin_kesehatan' => $penjamin,
                'nominal_takaful' => $nominalTakaful,
                'updated_by' => Auth::user()->full_name,
            ]));
        }

        $this->kosongkanBpjsTersimpan($detailIdsBpjsBerubah);

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

    /**
     * Semua faktor yang menentukan nominal BPJS di calculateBpjs: kelima flag
     * opt-out, penjamin kesehatan (BPU menihilkan semua, Asuransi/Takaful
     * mengganti iuran kesehatan dengan nominal takaful), dan nominal takaful.
     *
     * @param  array<string, int>  $flagBpjs
     */
    private function faktorBpjsBerubah($detail, array $flagBpjs, ?string $penjamin, $nominalTakaful): bool
    {
        foreach ($flagBpjs as $kolom => $nilaiBaru) {
            if ((int) ($detail->{$kolom} ?? 0) !== $nilaiBaru) {
                return true;
            }
        }

        if (($detail->penjamin_kesehatan ?? null) !== $penjamin) {
            return true;
        }

        return abs((float) ($detail->nominal_takaful ?? 0) - (float) $nominalTakaful) > 0.01;
    }

    /**
     * Nominal BPJS yang tersimpan di baris HPP/COSS dipakai calculateBpjs sebagai
     * override manual — sales memang bisa mengetiknya lewat hpp_editable_data di
     * step 10/11. Begitu faktor penentunya berubah di step ini, nominal lama itu
     * tidak lagi sah, jadi dikosongkan supaya dihitung ulang.
     *
     * Yang dikosongkan hanya nominalnya. Kolom persen_bpjs_* sengaja dibiarkan
     * supaya override persentase manual lewat bpjs_persentase_data tetap hidup.
     * Detail yang faktornya tidak berubah tidak disentuh sama sekali, sehingga
     * override manual di sana tetap aman.
     *
     * @param  array<int, int>  $detailIds
     */
    private function kosongkanBpjsTersimpan(array $detailIds): void
    {
        if (empty($detailIds)) {
            return;
        }

        $nominalKosong = [
            'bpjs_jkk' => null,
            'bpjs_jkm' => null,
            'bpjs_jht' => null,
            'bpjs_jp' => null,
            'bpjs_ks' => null,
        ];

        QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)->update($nominalKosong);
        QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)->update($nominalKosong);
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
