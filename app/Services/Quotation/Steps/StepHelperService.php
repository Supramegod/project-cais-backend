<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Pure utility helpers extracted from StepHelperTrait.
 * No database writes — parsing, cleaning, and data preparation only.
 */
class StepHelperService
{
    public function parseNominal(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function convertToFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return (float) str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
    }

    public function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['true', '1', 'yes', 'on']);
        }

        return (bool) $value;
    }

    public function isRo($detail): bool
    {
        return ($detail->position_id ?? null) === 224;
    }

    /**
     * Clean non-database attributes from Quotation model
     */
    public function cleanQuotationAttributes(Quotation $quotation): void
    {
        $nonDatabaseAttributes = [
            'quotation_detail',
            'quotation_site',
            'management_fee',
            '_mf_config',
            'jumlah_hc',
            'provisi',
            'persen_bpjs_ketenagakerjaan',
            'persen_bpjs_kesehatan',
        ];

        foreach ($nonDatabaseAttributes as $attribute) {
            if (isset($quotation->$attribute)) {
                unset($quotation->$attribute);
            }
        }

        $quotation->unsetRelation('quotationDetails');
        $quotation->unsetRelation('quotationSites');
        $quotation->unsetRelation('wage');
    }

    /**
     * Prepare quotation data for update from request Step 11
     */
    public function prepareQuotationDataForUpdate(Quotation $quotation, Request $request, string $user, Carbon $currentDateTime): array
    {
        $data = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        if ($request->has('penagihan')) {
            $data['penagihan'] = $request->penagihan;
        } else {
            $data['penagihan'] = $quotation->penagihan ?? 'Transfer';
        }

        if ($request->filled('persentase')) {
            $persentase = $request->persentase;
            if (is_string($persentase) && ! is_numeric($persentase)) {
                $persentase = (float) str_replace(['.', ','], ['', '.'], $persentase);
            }
            $data['persentase'] = $persentase;
        }

        if ($request->filled('nama_perusahaan')) {
            $data['nama_perusahaan'] = $request->nama_perusahaan;
        }

        if ($request->filled('persen_insentif')) {
            $persenInsentif = $request->persen_insentif;
            if (is_string($persenInsentif) && ! is_numeric($persenInsentif)) {
                $persenInsentif = (float) str_replace(['.', ','], ['', '.'], $persenInsentif);
            }
            $data['persen_insentif'] = $persenInsentif;
        }

        if ($request->filled('persen_bunga_bank')) {
            $persenBungaBank = $request->persen_bunga_bank;
            if (is_string($persenBungaBank) && ! is_numeric($persenBungaBank)) {
                $persenBungaBank = (float) str_replace(['.', ','], ['', '.'], $persenBungaBank);
            }
            $data['persen_bunga_bank'] = $persenBungaBank;
        }

        if ($request->filled('note_harga_jual')) {
            $data['note_harga_jual'] = $request->note_harga_jual;
        }

        $optionalFields = ['is_ppn', 'ppn_pph_dipotong', 'management_fee_id'];
        foreach ($optionalFields as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->$field;
            }
        }

        $validFields = [
            'nama_perusahaan',
            'penagihan',
            'persentase',
            'persen_insentif',
            'persen_bunga_bank',
            'note_harga_jual',
            'is_ppn',
            'ppn_pph_dipotong',
            'management_fee_id',
            'updated_by',
            'updated_at',
        ];

        return array_intersect_key($data, array_flip($validFields));
    }
}
