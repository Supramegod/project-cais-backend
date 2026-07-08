<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class PksUpdateRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_pks' => FluentRule::date('Tanggal PKS')->sometimes(),
            'tanggal_awal_kontrak' => FluentRule::date('Tanggal Awal Kontrak')->sometimes(),
            'tanggal_akhir_kontrak' => FluentRule::date('Tanggal Akhir Kontrak')->sometimes()->after('tanggal_awal_kontrak'),
            'status_pks_id' => FluentRule::integer('Status PKS')->sometimes()->exists('m_status_pks', 'id'),
            'is_aktif' => FluentRule::boolean('Is Aktif')->sometimes(),
        ];
    }
}
