<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PksStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipe = $this->route('tipe');

        $rules = [
            'leads_id' => FluentRule::field('Leads')->required()->exists('sl_leads', 'id'),
            'tanggal_pks' => FluentRule::date('Tanggal PKS')->required(),
            'tanggal_awal_kontrak' => FluentRule::date('Tanggal Awal Kontrak')->required(),
            'tanggal_akhir_kontrak' => FluentRule::date('Tanggal Akhir Kontrak')->required()->after('tanggal_awal_kontrak'),
            'kategoriHC' => FluentRule::field('Kategori HC')->required()->exists('m_kategori_sesuai_hc', 'id'),
            'loyalty' => FluentRule::field('Loyalty')->required()->exists('m_loyalty', 'id'),
            'salary_rule' => FluentRule::field('Salary Rule')->required()->exists('m_salary_rule', 'id'),
            'rule_thr' => FluentRule::field('Rule THR')->required()->exists('m_rule_thr', 'id'),
            'entitas' => FluentRule::field('Entitas')->required()->exists('m_company', 'id'),
        ];

        if ($tipe === 'baru') {
            $rules['site_ids'] = FluentRule::array(label: 'Site')->required();
            $rules['site_ids.*'] = FluentRule::integer()->exists('sl_spk_site', 'id');
        } elseif ($tipe === 'rekontrak' || $tipe === 'addendum') {
            $rules['quotation_site_ids'] = FluentRule::array(label: 'Quotation Site')->required();
            $rules['quotation_site_ids.*'] = FluentRule::integer()->exists('sl_quotation_site', 'id');

            $rules['pks_id'] = FluentRule::field('PKS')->required()->exists('sl_pks', 'id');
        }

        return $rules;
    }
}
