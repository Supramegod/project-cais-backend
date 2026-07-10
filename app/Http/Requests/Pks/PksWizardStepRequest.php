<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

use App\Models\Pks;
use SanderMuller\FluentValidation\FluentRule;

class PksWizardStepRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = (int) $this->route('step');
        $pks = Pks::query()->select(['id', 'tipe_pks', 'wizard_payload'])->find($this->route('pksId'));
        $tipePks = $pks?->tipe_pks
            ?? data_get($pks?->wizard_payload, 'source.tipe_pks')
            ?? 'baru';

        $rules = [
            'mark_as_complete' => FluentRule::boolean()->nullable(),
        ];

        switch ($step) {
            case 1:
                $rules['step_data'] = FluentRule::array()->nullable();
                $rules['step_data.confirmed'] = FluentRule::boolean()->nullable();
                $rules['step_data.notes'] = FluentRule::string()->nullable()->max(1000);
                break;

            case 2:
                $rules['step_data'] = FluentRule::array()->required();
                $rules['step_data.tanggal_pks'] = FluentRule::date()->required();
                $rules['step_data.tanggal_awal_kontrak'] = FluentRule::date()->required();
                $rules['step_data.tanggal_akhir_kontrak'] = FluentRule::date()->required()->rule('after_or_equal:step_data.tanggal_awal_kontrak');
                $rules['step_data.company_id'] = FluentRule::integer()->required()->exists('mysqlhris.m_company', 'id');
                $rules['step_data.salary_rule_id'] = FluentRule::integer()->required()->exists('m_salary_rule', 'id');
                $rules['step_data.rule_thr_id'] = FluentRule::integer()->required()->exists('m_rule_thr', 'id');
                $rules['step_data.kategori_sesuai_hc_id'] = FluentRule::integer()->nullable()->exists('m_kategori_sesuai_hc', 'id');
                $rules['step_data.loyalty_id'] = FluentRule::integer()->nullable()->exists('m_loyalty', 'id');
                break;

            case 3:
                $rules['step_data'] = FluentRule::array()->required();
                if ($tipePks === 'baru') {
                    $rules['step_data.site_ids'] = FluentRule::array()->required()->min(1)->children([
                        '*' => FluentRule::integer()->required()->exists('sl_spk_site', 'id'),
                    ]);
                } elseif ($tipePks === 'rekontrak') {
                    $rules['step_data.quotation_site_ids'] = FluentRule::array()->required()->min(1)->children([
                        '*' => FluentRule::integer()->required()->exists('sl_quotation_site', 'id'),
                    ]);
                } else {
                    $rules['step_data.skipped'] = FluentRule::boolean()->required();
                    $rules['step_data.reason'] = FluentRule::string()->nullable()->max(255);
                }
                $rules['step_data.primary_quotation_id'] = FluentRule::integer()->nullable()->exists('sl_quotation', 'id');
                break;

            case 4:
                $rules['step_data'] = FluentRule::array()->required();
                $rules['step_data.pic_1'] = FluentRule::string()->required()->max(255);
                $rules['step_data.jabatan_pic_1'] = FluentRule::string()->nullable()->max(255);
                $rules['step_data.email_pic_1'] = FluentRule::email()->nullable()->max(255);
                $rules['step_data.telp_pic_1'] = FluentRule::string()->nullable()->max(50);
                $rules['step_data.pic_2'] = FluentRule::string()->nullable()->max(255);
                $rules['step_data.jabatan_pic_2'] = FluentRule::string()->nullable()->max(255);
                $rules['step_data.email_pic_2'] = FluentRule::email()->nullable()->max(255);
                $rules['step_data.telp_pic_2'] = FluentRule::string()->nullable()->max(50);
                $rules['step_data.pic_3'] = FluentRule::string()->nullable()->max(255);
                $rules['step_data.jabatan_pic_3'] = FluentRule::string()->nullable()->max(255);
                $rules['step_data.email_pic_3'] = FluentRule::email()->nullable()->max(255);
                $rules['step_data.telp_pic_3'] = FluentRule::string()->nullable()->max(50);
                break;

            case 5:
                $rules['step_data'] = FluentRule::array()->nullable();
                break;

            case 6:
                $rules['step_data'] = FluentRule::array()->nullable();
                $rules['step_data.regenerate_preview'] = FluentRule::boolean()->nullable();
                $rules['step_data.manual_notes'] = FluentRule::string()->nullable()->max(1000);
                break;

            case 7:
                $rules['step_data'] = FluentRule::array()->nullable();
                $rules['step_data.ready_for_finalize'] = FluentRule::boolean()->nullable();
                $rules['step_data.review_notes'] = FluentRule::string()->nullable()->max(1000);
                break;

            default:
                $rules['step_data'] = FluentRule::array()->nullable();
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        if (!$this->has('step_data')) {
            $this->merge([
                'step_data' => [],
            ]);
        }
    }
}
