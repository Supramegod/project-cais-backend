<?php

namespace App\Services\Quotation\Calculation;

use App\DTO\DetailCalculation;
use App\Enums\JenisKontrak;

class QuotationComponentCalculationService
{
    private const PKHL_DEFAULT_HARI_KERJA = 25;

    public function calculateTunjangan($detail, $daftarTunjangan): array
    {
        $totalTunjangan = 0;
        $totalTunjanganCoss = 0;

        foreach ($daftarTunjangan as $tunjangan) {
            $dtTunjangan = $detail->quotationDetailTunjangans
                ->where('nama_tunjangan', $tunjangan->nama)->first();

            if ($dtTunjangan && in_array($dtTunjangan->jenis, ['Normatif', 'Ditagihkan'])) {
                $detail->{$tunjangan->nama} = 0;

                continue;
            }

            $value = $dtTunjangan && is_numeric($dtTunjangan->nominal) ? (float) $dtTunjangan->nominal : 0.0;
            $valuecoss = $dtTunjangan && is_numeric($dtTunjangan->nominal_coss) ? (float) $dtTunjangan->nominal_coss : 0.0;

            $detail->{$tunjangan->nama} = $value;
            $totalTunjangan += $value;
            $totalTunjanganCoss += $valuecoss;
        }
        $detail->total_tunjangan = $totalTunjangan;
        $detail->total_tunjangan_coss = $totalTunjanganCoss;

        return ['total' => $totalTunjangan, 'total_coss' => $totalTunjanganCoss];
    }

    /**
     * Pada General Cleaning basis iuran BPJS Ketenagakerjaan SELALU UMK, bukan
     * upah dan bukan batas bawah. Upah GC adalah nilai borongan per pekerjaan,
     * bukan gaji bulanan, sehingga memakainya sebagai basis iuran menghasilkan
     * angka yang tidak masuk akal. Kontrak lain tetap memakai batas bawah UMP.
     *
     * Basis BPJS Kesehatan sengaja TIDAK ikut aturan ini: tetap batas bawah UMK
     * untuk semua jenis kontrak, GC termasuk. Ini keputusan sadar, bukan
     * kelupaan — jangan diseragamkan tanpa konfirmasi bisnis.
     *
     * BPJS Kesehatan tidak dipaksa nol: pada GC dan PKHL opt-out is_bpjs_kes
     * dihormati walau penjamin BPJS, sehingga sales yang menentukan lewat step 5.
     */
    public function calculateBpjs($detail, $quotation, $hpp): void
    {
        $isGC = JenisKontrak::isGeneralCleaning($quotation->jenis_kontrak);
        $isBpjsKesOpsional = JenisKontrak::isBpjsKesOpsional($quotation->jenis_kontrak);

        $isBpu = ($detail->penjamin_kesehatan === 'BPU');
        if ($isBpu) {
            $detail->bpjs_kes = 0;
            $detail->persen_bpjs_kes = 0;
            $detail->bpjs_jkk = 0;
            $detail->persen_bpjs_jkk = 0;
            $detail->bpjs_jkm = 0;
            $detail->persen_bpjs_jkm = 0;
            $detail->bpjs_jht = 0;
            $detail->persen_bpjs_jht = 0;
            $detail->bpjs_jp = 0;
            $detail->persen_bpjs_jp = 0;
            $detail->bpjs_ketenagakerjaan = 0;
            $detail->persen_bpjs_ketenagakerjaan = 0;
            $detail->bpjs_kesehatan = 0;
            $detail->persen_bpjs_kesehatan = 0;

            return;
        }

        $nominalUpah = $detail->nominal_upah_bulanan ?? $detail->nominal_upah;
        $umk = $detail->umk ?? 0;
        $ump = $detail->ump ?? 0;

        $baseKetenagakerjaan = $isGC
            ? $umk
            : (($nominalUpah < $ump) ? $ump : $nominalUpah);
        $baseKesehatan = ($nominalUpah < $umk) ? $umk : $nominalUpah;
        $bpjsConfig = [
            'jkk' => ['field' => 'bpjs_jkk', 'hpp_field' => 'bpjs_jkk', 'percent' => 'persen_bpjs_jkk', 'default' => fn () => $this->getJkkPercent($quotation->resiko), 'base' => $baseKetenagakerjaan],
            'jkm' => ['field' => 'bpjs_jkm', 'hpp_field' => 'bpjs_jkm', 'percent' => 'persen_bpjs_jkm', 'default' => 0.30, 'base' => $baseKetenagakerjaan],
            'jht' => ['field' => 'bpjs_jht', 'hpp_field' => 'bpjs_jht', 'percent' => 'persen_bpjs_jht', 'default' => 3.70, 'base' => $baseKetenagakerjaan],
            'jp' => ['field' => 'bpjs_jp', 'hpp_field' => 'bpjs_jp', 'percent' => 'persen_bpjs_jp', 'default' => 2.00, 'base' => $baseKetenagakerjaan],
            'kes' => ['field' => 'bpjs_kes', 'hpp_field' => 'bpjs_ks', 'percent' => 'persen_bpjs_kes', 'default' => 4.00, 'base' => $baseKesehatan],
        ];

        foreach ($bpjsConfig as $key => $config) {
            $persentase = 0.0;
            $base = $config['base'];
            $optOutField = 'is_bpjs_'.$key;
            $hppField = $config['hpp_field'];
            $defaultPercent = is_callable($config['default']) ? $config['default']() : $config['default'];

            if (isset($detail->{$config['percent']}) && (float) $detail->{$config['percent']} != 0) {
                $persentase = (float) $detail->{$config['percent']};
            } elseif ($hpp && isset($hpp->{$config['percent']}) && (float) $hpp->{$config['percent']} != 0) {
                $persentase = (float) $hpp->{$config['percent']};
            } else {
                $persentase = $defaultPercent;
            }

            $isOptOut = false;
            if (isset($detail->{$optOutField})) {
                $optValue = $detail->{$optOutField};
                if (
                    ($optValue === '0' || $optValue === 0 || $optValue === false ||
                        (is_string($optValue) && strtolower(trim($optValue)) === 'tidak'))
                    && ! ($key === 'kes' && $detail->penjamin_kesehatan === 'BPJS' && ! $isBpjsKesOpsional)
                ) {
                    $isOptOut = true;
                }
            }

            if ($isOptOut) {
                $detail->{$config['field']} = 0;
                $detail->{$config['percent']} = 0;
            } elseif ($key === 'kes' && in_array($detail->penjamin_kesehatan, ['Asuransi Swasta', 'Takaful'])) {
                $detail->{$config['field']} = $detail->nominal_takaful ?? 0;
                $detail->{$config['percent']} = 0;
            } elseif ($hpp && $hpp->{$hppField} !== null) {
                $detail->{$config['field']} = (float) $hpp->{$hppField};
                $detail->{$config['percent']} = $persentase;
            } else {
                $detail->{$config['field']} = ($base * $persentase) / 100;
                $detail->{$config['percent']} = $persentase;
            }
        }

        $this->updateQuotationBpjs($detail, $quotation);
    }

    public function calculateExtras($detail, $quotation, $hpp, $coss, $wage, bool $isGC = false, int $hariKerja = 1): void
    {
        $baseUpahBulanan = $detail->nominal_upah_bulanan ?? $detail->nominal_upah;

        $tunjanganHariRayaHpp = $hpp ? (float) ($hpp->tunjangan_hari_raya ?? 0) : 0;
        $tunjanganHariRayaCoss = $coss ? (float) ($coss->tunjangan_hari_raya ?? 0) : 0;

        if ($tunjanganHariRayaHpp == 0 && $wage && isset($wage->thr)) {
            if (in_array(strtolower(trim($wage->thr ?? 'Tidak Ada')), ['diprovisikan'])) {
                $divisor = $isGC ? $hariKerja : 12;
                $tunjanganHariRayaHpp = $baseUpahBulanan / $divisor;
                $tunjanganHariRayaCoss = $baseUpahBulanan / $divisor;
            }
        }

        $kompensasiHpp = $hpp ? (float) ($hpp->kompensasi ?? 0) : 0;
        $kompensasiCoss = $coss ? (float) ($coss->kompensasi ?? 0) : 0;

        if ($kompensasiHpp == 0 && $wage && isset($wage->kompensasi)) {
            if (in_array(strtolower(trim($wage->kompensasi ?? 'Tidak Ada')), ['diprovisikan'])) {
                $divisor = $isGC ? $hariKerja : 12;
                $kompensasiHpp = $baseUpahBulanan / $divisor;
                $kompensasiCoss = $baseUpahBulanan / $divisor;
            }
        }

        $tunjanganHolidayHpp = $hpp ? (float) ($hpp->tunjangan_hari_libur_nasional ?? 0) : 0;
        $tunjanganHolidayCoss = $coss ? (float) ($coss->tunjangan_hari_libur_nasional ?? 0) : 0;

        if ($tunjanganHolidayHpp == 0 && $wage && isset($wage->tunjangan_holiday)) {
            if (str_contains(strtolower(trim($wage->tunjangan_holiday ?? 'Tidak Ada')), 'flat')) {
                $calculated = $this->calculateTunjanganHolidayFromWage($wage);
                $tunjanganHolidayHpp = $calculated;
                $tunjanganHolidayCoss = $calculated;
            }
        }

        $lemburHpp = $hpp ? (float) ($hpp->lembur ?? 0) : 0;
        $lemburCoss = $coss ? (float) ($coss->lembur ?? 0) : 0;

        if ($lemburHpp == 0 && $wage && isset($wage->lembur)) {
            if (str_contains(strtolower(trim($wage->lembur ?? 'Tidak Ada')), 'flat')) {
                $calculated = $this->calculateLemburFromWage($wage);
                $lemburHpp = $calculated;
                $lemburCoss = $calculated;
            }
        }

        $detail->tunjangan_hari_raya_hpp = round($tunjanganHariRayaHpp, 2);
        $detail->tunjangan_hari_raya_coss = round($tunjanganHariRayaCoss, 2);
        $detail->kompensasi_hpp = round($kompensasiHpp, 2);
        $detail->kompensasi_coss = round($kompensasiCoss, 2);
        $detail->tunjangan_holiday_hpp = round($tunjanganHolidayHpp, 2);
        $detail->tunjangan_holiday_coss = round($tunjanganHolidayCoss, 2);
        $detail->lembur_hpp = round($lemburHpp, 2);
        $detail->lembur_coss = round($lemburCoss, 2);

        $detail->tunjangan_hari_raya = $tunjanganHariRayaHpp;
        $detail->kompensasi = $kompensasiHpp;
        $detail->tunjangan_holiday = $tunjanganHolidayHpp;
        $detail->lembur = $lemburHpp;
    }

    public function calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss): void
    {
        if (is_array($totalTunjanganResult)) {
            $totalTunjanganHpp = (float) ($totalTunjanganResult['total'] ?? 0);
            $totalTunjanganCoss = (float) ($totalTunjanganResult['total_coss'] ?? 0);
        } else {
            $totalTunjanganHpp = (float) ($detail->total_tunjangan ?? 0);
            $totalTunjanganCoss = (float) ($detail->total_tunjangan_coss ?? 0);
        }

        $potonganBpu = $detail->penjamin_kesehatan === 'BPU' ? 16800 : 0;
        if ($potonganBpu) {
            $detail->potongan_bpu = $potonganBpu;
        }

        $nominalUpah = (float) ($detail->nominal_upah_bulanan ?? $detail->nominal_upah ?? 0);
        $bpjsJkk = (float) ($detail->bpjs_jkk ?? 0);
        $bpjsJkm = (float) ($detail->bpjs_jkm ?? 0);
        $bpjsJht = (float) ($detail->bpjs_jht ?? 0);
        $bpjsJp = (float) ($detail->bpjs_jp ?? 0);
        $bpjsKes = (float) ($detail->bpjs_kes ?? 0);
        $bpjsKetenagakerjaanHpp = $bpjsJkk + $bpjsJkm + $bpjsJht + $bpjsJp;
        $bpjsKetenagakerjaanCoss = $bpjsKetenagakerjaanHpp;

        $detail->total_base_manpower = round($nominalUpah + $totalTunjanganHpp, 2);
        $detail->total_base_manpower_coss = round($nominalUpah + $totalTunjanganCoss, 2);

        $detail->total_personil = round(
            $nominalUpah + $totalTunjanganHpp
            + (float) ($detail->tunjangan_hari_raya_hpp ?? 0) + (float) ($detail->kompensasi_hpp ?? 0)
            + (float) ($detail->tunjangan_holiday_hpp ?? 0) + (float) ($detail->lembur_hpp ?? 0)
            + $bpjsKetenagakerjaanHpp + $bpjsKes
            + (float) ($detail->personil_kaporlap ?? 0) + (float) ($detail->personil_devices ?? 0)
            + (float) ($detail->personil_chemical ?? 0) + (float) ($detail->personil_ohc ?? 0)
            + (float) ($detail->bunga_bank ?? 0) + (float) ($detail->insentif ?? 0) + $potonganBpu,
            2
        );
        $detail->sub_total_personil = round($detail->total_personil * $detail->jumlah_hc_hpp, 2);

        $detail->total_exclude_base_manpower = round(
            (float) ($detail->tunjangan_hari_raya_coss ?? 0) + (float) ($detail->kompensasi_coss ?? 0)
            + (float) ($detail->tunjangan_holiday_coss ?? 0) + (float) ($detail->lembur_coss ?? 0)
            + $bpjsKes + $bpjsKetenagakerjaanCoss
            + (float) ($detail->personil_kaporlap_coss ?? 0) + (float) ($detail->personil_devices_coss ?? 0)
            + (float) ($detail->personil_chemical_coss ?? 0),
            2
        );
        $detail->total_personil_coss = round(
            $detail->total_base_manpower_coss + $detail->total_exclude_base_manpower
            + (float) ($detail->personil_ohc_coss ?? 0) + $potonganBpu,
            2
        );
        $detail->sub_total_personil_coss = round($detail->total_personil_coss * $detail->jumlah_hc_original, 2);

        if ($this->isRo($detail)) {
            $detail->total_base_manpower_coss = 0;
            $detail->total_exclude_base_manpower = 0;
            $detail->total_personil_coss = 0;
            $detail->sub_total_personil_coss = 0;
        }
    }

    public function populateDetailCalculation($detail, $quotation, DetailCalculation $detailCalculation): void
    {
        $potonganBpu = ($detail->penjamin_kesehatan === 'BPU') ? 16800 : 0;

        $detailCalculation->hpp_data = $this->buildHppData($detail, $quotation, $potonganBpu);

        if ($this->isRo($detail)) {
            $detailCalculation->coss_data = $this->buildEmptyCossData($detail, $quotation);
        } else {
            $detailCalculation->coss_data = $this->buildCossData($detail, $quotation, $potonganBpu);
        }
    }

    private function buildHppData($detail, $quotation, int $potonganBpu): array
    {
        return [
            'quotation_detail_id' => $detail->id, 'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id, 'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_hpp, 'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya_hpp ?? 0,
            'kompensasi' => $detail->kompensasi_hpp ?? 0,
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday_hpp ?? 0,
            'lembur' => $detail->lembur_hpp ?? 0, 'takaful' => $detail->nominal_takaful ?? 0,
            'bpjs_jkk' => $detail->bpjs_jkk ?? null, 'bpjs_jkm' => $detail->bpjs_jkm ?? null,
            'bpjs_jht' => $detail->bpjs_jht ?? null, 'bpjs_jp' => $detail->bpjs_jp ?? null,
            'bpjs_ks' => $detail->bpjs_kes ?? null,
            'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0, 'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
            'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0, 'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
            'persen_bpjs_ks' => $detail->persen_bpjs_kes ?? 0,
            'provisi_seragam' => $detail->personil_kaporlap ?? 0,
            'provisi_peralatan' => $detail->personil_devices ?? 0,
            'provisi_chemical' => $detail->personil_chemical ?? 0, 'provisi_ohc' => $detail->personil_ohc ?? 0,
            'bunga_bank' => $detail->bunga_bank ?? 0, 'insentif' => $detail->insentif ?? 0,
            'potongan_bpu' => $potonganBpu,
            'total_biaya_per_personil' => $detail->total_personil ?? 0,
            'total_biaya_all_personil' => $detail->sub_total_personil ?? 0,
        ];
    }

    private function buildEmptyCossData($detail, $quotation): array
    {
        $data = ['quotation_detail_id' => $detail->id, 'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id, 'position_id' => $detail->position_id];
        foreach (['jumlah_hc', 'gaji_pokok', 'total_tunjangan', 'total_base_manpower', 'tunjangan_hari_raya',
            'kompensasi', 'tunjangan_hari_libur_nasional', 'lembur', 'bpjs_jkk', 'bpjs_jkm', 'bpjs_jht', 'bpjs_jp', 'bpjs_ks',
            'persen_bpjs_jkk', 'persen_bpjs_jkm', 'persen_bpjs_jht', 'persen_bpjs_jp', 'persen_bpjs_ks',
            'provisi_seragam', 'provisi_peralatan', 'provisi_chemical', 'provisi_ohc',
            'total_personil_coss', 'sub_total_personil_coss', 'total_exclude_base_manpower',
            'bunga_bank', 'insentif', 'potongan_bpu'] as $k) {
            $data[$k] = 0;
        }

        return $data;
    }

    private function buildCossData($detail, $quotation, int $potonganBpu): array
    {
        return [
            'quotation_detail_id' => $detail->id, 'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id, 'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_original, 'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'total_base_manpower' => $detail->total_base_manpower_coss ?? 0,
            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya_coss ?? 0,
            'kompensasi' => $detail->kompensasi_coss ?? 0,
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday_coss ?? 0,
            'lembur' => $detail->lembur_coss ?? 0,
            'bpjs_jkk' => $detail->bpjs_jkk ?? null, 'bpjs_jkm' => $detail->bpjs_jkm ?? null,
            'bpjs_jht' => $detail->bpjs_jht ?? null, 'bpjs_jp' => $detail->bpjs_jp ?? null,
            'bpjs_ks' => $detail->bpjs_kes ?? null,
            'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0, 'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
            'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0, 'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
            'persen_bpjs_ks' => $detail->persen_bpjs_kes ?? 0,
            'provisi_seragam' => $detail->personil_kaporlap_coss ?? 0,
            'provisi_peralatan' => $detail->personil_devices_coss ?? 0,
            'provisi_chemical' => $detail->personil_chemical_coss ?? 0,
            'provisi_ohc' => $detail->personil_ohc_coss ?? 0,
            'total_personil_coss' => $detail->total_personil_coss ?? 0,
            'sub_total_personil_coss' => $detail->sub_total_personil_coss ?? 0,
            'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
            'bunga_bank' => $detail->bunga_bank ?? 0, 'insentif' => $detail->insentif ?? 0,
            'potongan_bpu' => $potonganBpu,
        ];
    }

    private function getJkkPercent($resiko): float
    {
        return ['Sangat Rendah' => 0.24, 'Rendah' => 0.54, 'Sedang' => 0.89,
            'Tinggi' => 1.27, 'Sangat Tinggi' => 1.74][$resiko] ?? 0.24;
    }

    public function updateQuotationBpjs($detail, $quotation): void
    {
        $detail->persen_bpjs_ketenagakerjaan = ($detail->persen_bpjs_jkk ?? 0) + ($detail->persen_bpjs_jkm ?? 0)
            + ($detail->persen_bpjs_jht ?? 0) + ($detail->persen_bpjs_jp ?? 0);
        $detail->bpjs_ketenagakerjaan = ($detail->bpjs_jkk ?? 0) + ($detail->bpjs_jkm ?? 0)
            + ($detail->bpjs_jht ?? 0) + ($detail->bpjs_jp ?? 0);
        $bpjsProvider = in_array($detail->penjamin_kesehatan, ['BPJS', 'BPJS Kesehatan']);
        $takafulProvider = in_array($detail->penjamin_kesehatan, ['Asuransi Swasta', 'Takaful']);
        $detail->bpjs_kesehatan = $bpjsProvider ? ($detail->bpjs_kes ?? 0) : ($takafulProvider ? ($detail->nominal_takaful ?? 0) : 0);
        $detail->persen_bpjs_kesehatan = $bpjsProvider ? ($detail->persen_bpjs_kes ?? 0) : 0;
    }

    public function makeEmptyWageObject(): \stdClass
    {
        $w = new \stdClass;
        $w->upah = null;
        $w->hitungan_upah = null;
        $w->lembur = 'Tidak';
        $w->nominal_lembur = 0;
        $w->jenis_bayar_lembur = null;
        $w->jam_per_bulan_lembur = 0;
        $w->lembur_ditagihkan = 'Tidak Ditagihkan';
        $w->kompensasi = 'Tidak';
        $w->thr = 'Tidak';
        $w->tunjangan_holiday = 'Tidak';
        $w->nominal_tunjangan_holiday = 0;
        $w->jenis_bayar_tunjangan_holiday = null;

        return $w;
    }

    public function isRo($detail): bool
    {
        return ($detail->position_id ?? null) === 224;
    }

    private function calculateTunjanganHolidayFromWage($wage): float
    {
        if (! $wage || ! str_contains(strtolower(trim($wage->tunjangan_holiday ?? 'Tidak')), 'flat')) {
            return 0.0;
        }

        return round((float) str_replace(['.', ','], ['', '.'], (string) ($wage->nominal_tunjangan_holiday ?? 0)), 2);
    }

    private function calculateLemburFromWage($wage): float
    {
        if (! $wage || ! str_contains(strtolower(trim($wage->lembur ?? 'Tidak')), 'flat')) {
            return 0.0;
        }
        if (str_contains(strtolower($wage->lembur_ditagihkan ?? ''), 'terpisah')) {
            return 0.0;
        }

        return round((float) str_replace(['.', ','], ['', '.'], (string) ($wage->nominal_lembur ?? 0)), 2);
    }
}
