<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use App\Models\Position;
use App\Models\Quotation;
use SanderMuller\FluentValidation\FluentRule;

class Step3Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'headCountData' => FluentRule::array()->required()->each([
                'quotation_site_id' => FluentRule::integer()->required(),
                'position_id' => FluentRule::integer()->required(),
                'jumlah_hc' => FluentRule::integer()->required()->min(1),
                'jabatan_kebutuhan' => FluentRule::string()->required(),
                'nama_site' => FluentRule::string()->required(),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'headCountData.required' => 'Data headcount harus diisi',
            'headCountData.array' => 'Data headcount harus berupa array',
            'headCountData.*.quotation_site_id.required' => 'Site ID harus diisi',
            'headCountData.*.quotation_site_id.integer' => 'Site ID harus berupa angka',
            'headCountData.*.position_id.required' => 'Position ID harus diisi',
            'headCountData.*.position_id.integer' => 'Position ID harus berupa angka',
            'headCountData.*.jumlah_hc.required' => 'Jumlah HC harus diisi',
            'headCountData.*.jumlah_hc.integer' => 'Jumlah HC harus berupa angka',
            'headCountData.*.jumlah_hc.min' => 'Jumlah HC minimal 1',
            'headCountData.*.jabatan_kebutuhan.required' => 'Jabatan kebutuhan harus diisi',
            'headCountData.*.jabatan_kebutuhan.string' => 'Jabatan kebutuhan harus berupa teks',
            'headCountData.*.nama_site.required' => 'Nama site harus diisi',
            'headCountData.*.nama_site.string' => 'Nama site harus berupa teks',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $quotationId = $this->route('quotation') ?? $this->route('id') ?? $this->route('quotation_id');

            if (preg_match('/quotation\/(\d+)\/step/', $this->path(), $matches)) {
                $quotationId = $matches[1];
            }

            if (! $quotationId) {
                return;
            }

            $quotation = Quotation::with('quotationSites')->find($quotationId);

            if (! $quotation) {
                $validator->errors()->add('headCountData', 'Quotation tidak ditemukan.');
                return;
            }

            $siteIdsInQuotation = $quotation->quotationSites->pluck('id')->toArray();
            $siteIdsInRequest = is_array($this->headCountData)
                ? collect($this->headCountData)->pluck('quotation_site_id')->unique()->toArray()
                : [];

            $missingSites = array_diff($siteIdsInQuotation, $siteIdsInRequest);
            if (! empty($missingSites)) {
                $missingSiteNames = $quotation->quotationSites->whereIn('id', $missingSites)->pluck('nama_site')->toArray();
                $validator->errors()->add(
                    'headCountData.missing_sites',
                    'Setiap site harus memiliki minimal satu headcount. Site berikut belum memiliki headcount: '.implode(', ', $missingSiteNames)
                );
            }

            $invalidSites = array_diff($siteIdsInRequest, $siteIdsInQuotation);
            if (! empty($invalidSites)) {
                $validator->errors()->add(
                    'headCountData.invalid_sites',
                    'Site dengan ID berikut tidak valid untuk quotation ini: '.implode(', ', $invalidSites)
                );
            }

            if (is_array($this->headCountData) && count($this->headCountData) > 0) {
                $validPositionIds = Position::where('layanan_id', $quotation->kebutuhan_id)->pluck('id')->toArray();
                $invalidPositions = [];

                foreach ($this->headCountData as $data) {
                    if (! in_array($data['position_id'], $validPositionIds)) {
                        $invalidPositions[] = $data['position_id'];
                    }
                }

                if (! empty($invalidPositions)) {
                    $validator->errors()->add(
                        'headCountData.invalid_positions',
                        'Position ID: '.implode(', ', array_unique($invalidPositions)).' tidak valid untuk layanan ini.'
                    );
                }
            }
        });
    }
}
