<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class PksWizardInitializeRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipe = $this->route('tipe');

        $rules = [
            'leads_id' => FluentRule::integer()->required()->exists('sl_leads', 'id'),
            'quotation_id' => FluentRule::integer()->nullable()->exists('sl_quotation', 'id'),
            'spk_id' => FluentRule::integer()->nullable()->exists('sl_spk', 'id'),
        ];

        if ($tipe === 'addendum') {
            $rules['pks_induk_id'] = FluentRule::integer()->required()->exists('sl_pks', 'id');
            $rules['company_id'] = FluentRule::integer()->nullable()->exists('mysqlhris.m_company', 'id');
        } else {
            $rules['company_id'] = FluentRule::integer()->required()->exists('mysqlhris.m_company', 'id');
        }

        if ($tipe === 'baru') {
            $rules['spk_id'] = FluentRule::integer()->required()->exists('sl_spk', 'id');
        }

        if ($tipe === 'rekontrak') {
            $rules['quotation_id'] = FluentRule::integer()->required()->exists('sl_quotation', 'id');
        }

        return $rules;
    }
}
