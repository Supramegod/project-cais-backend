<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Focused resource for quotation calculation summary data.
 * Exposes HPP, COSS, BPJS breakdown, and detail-level calculation data.
 */
class QuotationCalculationResource extends JsonResource
{
    /**
     * Transform calculation summary from QuotationCalculationResult.
     */
    public static function calculationSummary($calculatedQuotation): ?array
    {
        if (! $calculatedQuotation || ! $calculatedQuotation->calculation_summary) {
            return null;
        }

        $summary = $calculatedQuotation->calculation_summary;

        return [
            'bpu' => [
                'total_potongan_bpu' => $summary->total_potongan_bpu ?? 0,
                'potongan_bpu_per_orang' => $summary->potongan_bpu_per_orang ?? 0,
            ],
            'hpp' => [
                'total_sebelum_management_fee' => $summary->total_sebelum_management_fee ?? 0,
                'nominal_management_fee' => $summary->nominal_management_fee ?? 0,
                'grand_total_sebelum_pajak' => $summary->grand_total_sebelum_pajak ?? 0,
                'ppn' => $summary->ppn ?? 0,
                'pph' => $summary->pph ?? 0,
                'dpp' => $summary->dpp ?? 0,
                'total_invoice' => $summary->total_invoice ?? 0,
                'pembulatan' => $summary->pembulatan ?? 0,
                'margin' => $summary->margin ?? 0,
                'gpm' => $summary->gpm ?? 0,
                'persen_bunga_bank' => 0,
                'persen_insentif' => 0,
                'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan ?? 0,
                'persen_bpjs_ketenagakerjaan' => round($summary->persen_bpjs_ketenagakerjaan ?? 0, 2),
                'breakdown_bpjs' => [
                    'persen_bpjs_jkk' => round($summary->persen_bpjs_jkk ?? 0, 2),
                    'persen_bpjs_jkm' => round($summary->persen_bpjs_jkm ?? 0, 2),
                    'persen_bpjs_jht' => round($summary->persen_bpjs_jht ?? 0, 2),
                    'persen_bpjs_jp' => round($summary->persen_bpjs_jp ?? 0, 2),
                ],
            ],
            'coss' => [
                'total_sebelum_management_fee_coss' => $summary->total_sebelum_management_fee_coss ?? 0,
                'nominal_management_fee_coss' => $summary->nominal_management_fee_coss ?? 0,
                'grand_total_sebelum_pajak_coss' => $summary->grand_total_sebelum_pajak_coss ?? 0,
                'ppn_coss' => $summary->ppn_coss ?? 0,
                'pph_coss' => $summary->pph_coss ?? 0,
                'dpp_coss' => $summary->dpp_coss ?? 0,
                'total_invoice_coss' => $summary->total_invoice_coss ?? 0,
                'pembulatan_coss' => $summary->pembulatan_coss ?? 0,
                'margin_coss' => $summary->margin_coss ?? 0,
                'gpm_coss' => $summary->gpm_coss ?? 0,
                'persen_bunga_bank' => 0,
                'persen_insentif' => 0,
                'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan_coss ?? 0,
                'persen_bpjs_ketenagakerjaan' => round($summary->persen_bpjs_ketenagakerjaan_coss ?? 0, 2),
                'breakdown_bpjs' => [
                    'persen_bpjs_jkk' => round($summary->persen_bpjs_jkk_coss ?? 0, 2),
                    'persen_bpjs_jkm' => round($summary->persen_bpjs_jkm_coss ?? 0, 2),
                    'persen_bpjs_jht' => round($summary->persen_bpjs_jht_coss ?? 0, 2),
                    'persen_bpjs_jp' => round($summary->persen_bpjs_jp_coss ?? 0, 2),
                ],
            ],
        ];
    }

    /**
     * Transform detail calculations with display logic for HPP/COSS values.
     */
    public static function detailCalculations($calculatedQuotation): array
    {
        if (! $calculatedQuotation || ! $calculatedQuotation->quotation) {
            return [];
        }

        $resolveDisplay = function ($wage, $jenisField, $hppValue, $cossValue, $fieldDitagihkan = null) {
            if (! $wage) {
                return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
            }
            $jenis = strtolower(trim($wage->$jenisField ?? ''));

            if ($fieldDitagihkan && strtolower(trim($wage->$fieldDitagihkan ?? '')) === 'ditagihkan terpisah') {
                return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
            }
            if (in_array($jenis, ['normatif', 'ditagihkan'])) {
                return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
            }
            if (in_array($jenis, ['flat', 'diprovisikan'])) {
                return [
                    'hpp' => $hppValue > 0 ? $hppValue : 'Tidak Ada',
                    'coss' => $cossValue > 0 ? $cossValue : 'Tidak Ada',
                ];
            }
            return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
        };

        $items = [];
        foreach ($calculatedQuotation->quotation->quotationDetails as $detail) {
            $wage = $detail->wage ?? null;
            $detailCalc = $calculatedQuotation->detail_calculations[$detail->id] ?? null;
            $hppData = $detailCalc?->hpp_data ?? [];
            $cossData = $detailCalc?->coss_data ?? [];
            $isRoDetail = $detail?->position_id === 224;

            $thr = $resolveDisplay($wage, 'thr', $hppData['tunjangan_hari_raya'] ?? 0, $cossData['tunjangan_hari_raya'] ?? 0);
            $komp = $resolveDisplay($wage, 'kompensasi', $hppData['kompensasi'] ?? 0, $cossData['kompensasi'] ?? 0);
            $lembur = $resolveDisplay($wage, 'lembur', $hppData['lembur'] ?? 0, $cossData['lembur'] ?? 0, 'lembur_ditagihkan');
            $holiday = $resolveDisplay($wage, 'tunjangan_holiday', $hppData['tunjangan_hari_libur_nasional'] ?? 0, $cossData['tunjangan_hari_libur_nasional'] ?? 0);

            $item = [
                'id' => $detail->id,
                'position_name' => $detail->jabatan_kebutuhan,
                'nama_site' => $detail->nama_site,
                'quotation_site_id' => $detail->quotation_site_id,
                'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                'upah' => $wage?->upah ?? 0,
                'jumlah_hc_hpp' => $hppData['jumlah_hc'] ?? 0,
                'jumlah_hc_coss' => $isRoDetail ? 0 : ($cossData['jumlah_hc'] ?? 0),
                'tunjangan_data' => $detail->quotationDetailTunjangans->map(fn ($t) => [
                    'nama_tunjangan' => $t->nama_tunjangan,
                    'nominal' => $t->nominal,
                    'nominal_coss' => $t->nominal_coss,
                    'jenis' => $t->jenis,
                ])->values()->toArray(),
                'hpp' => [
                    'nominal_upah' => $hppData['gaji_pokok'] ?? 0,
                    'total_tunjangan' => $hppData['total_tunjangan'] ?? 0,
                    'tunjangan_hari_raya' => $thr['hpp'],
                    'kompensasi' => $komp['hpp'],
                    'lembur' => $lembur['hpp'],
                    'tunjangan_holiday' => $holiday['hpp'],
                    'bpjs_ketenagakerjaan' => ($hppData['bpjs_jkk'] ?? 0) + ($hppData['bpjs_jkm'] ?? 0) + ($hppData['bpjs_jht'] ?? 0) + ($hppData['bpjs_jp'] ?? 0),
                    'bpjs_kesehatan' => $hppData['bpjs_ks'] ?? 0,
                    'bpjs_jkk' => $hppData['bpjs_jkk'] ?? 0,
                    'bpjs_jkm' => $hppData['bpjs_jkm'] ?? 0,
                    'bpjs_jht' => $hppData['bpjs_jht'] ?? 0,
                    'bpjs_jp' => $hppData['bpjs_jp'] ?? 0,
                    'bpjs_kes' => $hppData['bpjs_ks'] ?? 0,
                    'persen_bpjs_jkk' => $hppData['persen_bpjs_jkk'] ?? 0,
                    'persen_bpjs_jkm' => $hppData['persen_bpjs_jkm'] ?? 0,
                    'persen_bpjs_jht' => $hppData['persen_bpjs_jht'] ?? 0,
                    'persen_bpjs_jp' => $hppData['persen_bpjs_jp'] ?? 0,
                    'persen_bpjs_kes' => $hppData['persen_bpjs_ks'] ?? 0,
                    'potongan_bpu' => $hppData['potongan_bpu'] ?? 0,
                    'personil_kaporlap' => $hppData['provisi_seragam'] ?? 0,
                    'personil_devices' => $hppData['provisi_peralatan'] ?? 0,
                    'personil_ohc' => $hppData['provisi_ohc'] ?? 0,
                    'personil_chemical' => $hppData['provisi_chemical'] ?? 0,
                    'total_personil' => $hppData['total_biaya_per_personil'] ?? 0,
                    'sub_total_personil' => $hppData['total_biaya_all_personil'] ?? 0,
                    'bunga_bank' => $hppData['bunga_bank'] ?? 0,
                    'insentif' => $hppData['insentif'] ?? 0,
                ],
            ];

            if (! $isRoDetail) {
                $item['coss'] = [
                    'nominal_upah' => $cossData['gaji_pokok'] ?? 0,
                    'total_tunjangan' => $cossData['total_tunjangan'] ?? 0,
                    'tunjangan_hari_raya' => $thr['coss'],
                    'kompensasi' => $komp['coss'],
                    'lembur' => $lembur['coss'],
                    'tunjangan_holiday' => $holiday['coss'],
                    'bpjs_ketenagakerjaan' => ($cossData['bpjs_jkk'] ?? 0) + ($cossData['bpjs_jkm'] ?? 0) + ($cossData['bpjs_jht'] ?? 0) + ($cossData['bpjs_jp'] ?? 0),
                    'bpjs_kesehatan' => $cossData['bpjs_ks'] ?? 0,
                    'bpjs_jkk' => $cossData['bpjs_jkk'] ?? 0,
                    'bpjs_jkm' => $cossData['bpjs_jkm'] ?? 0,
                    'bpjs_jht' => $cossData['bpjs_jht'] ?? 0,
                    'bpjs_jp' => $cossData['bpjs_jp'] ?? 0,
                    'bpjs_kes' => $cossData['bpjs_ks'] ?? 0,
                    'persen_bpjs_jkk' => $cossData['persen_bpjs_jkk'] ?? 0,
                    'persen_bpjs_jkm' => $cossData['persen_bpjs_jkm'] ?? 0,
                    'persen_bpjs_jht' => $cossData['persen_bpjs_jht'] ?? 0,
                    'persen_bpjs_jp' => $cossData['persen_bpjs_jp'] ?? 0,
                    'persen_bpjs_kes' => $cossData['persen_bpjs_ks'] ?? 0,
                    'potongan_bpu' => $cossData['potongan_bpu'] ?? 0,
                    'personil_kaporlap_coss' => $cossData['provisi_seragam'] ?? 0,
                    'personil_devices_coss' => $cossData['provisi_peralatan'] ?? 0,
                    'personil_ohc_coss' => $cossData['provisi_ohc'] ?? 0,
                    'personil_chemical_coss' => $cossData['provisi_chemical'] ?? 0,
                    'total_personil' => $cossData['total_personil_coss'] ?? 0,
                    'sub_total_personil' => $cossData['sub_total_personil_coss'] ?? 0,
                    'total_base_manpower' => $cossData['total_base_manpower'] ?? 0,
                    'total_exclude_base_manpower' => $cossData['total_exclude_base_manpower'] ?? 0,
                    'bunga_bank' => $cossData['bunga_bank'] ?? 0,
                    'insentif' => $cossData['insentif'] ?? 0,
                ];
            }

            $items[] = $item;
        }

        return $items;
    }
}
