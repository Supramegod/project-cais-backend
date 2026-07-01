<?php

namespace App\Http\Requests;

use App\Models\Pks;
use App\Models\Quotation;
use App\Models\Spk;
use App\Models\SpkSite;
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

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tipe = $this->route('tipe');
            $leadsId = $this->input('leads_id');
            $quotationId = $this->input('quotation_id');
            $spkId = $this->input('spk_id');
            $pksIndukId = $this->input('pks_induk_id');

            if ($quotationId) {
                $quotation = Quotation::query()->select(['id', 'leads_id'])->find($quotationId);
                if ($quotation && (int) $quotation->leads_id !== (int) $leadsId) {
                    $validator->errors()->add('quotation_id', 'Quotation tidak terhubung dengan leads yang dipilih');
                }
            }

            if ($spkId) {
                $spk = Spk::query()->select(['id', 'leads_id', 'quotation_id'])->find($spkId);
                if ($spk && (int) $spk->leads_id !== (int) $leadsId) {
                    $validator->errors()->add('spk_id', 'SPK tidak terhubung dengan leads yang dipilih');
                }

                if ($spk && $quotationId) {
                    $linkedQuotationId = $this->resolveSpkQuotationId($spk);
                    if ($linkedQuotationId !== null && (int) $linkedQuotationId !== (int) $quotationId) {
                        $validator->errors()->add('quotation_id', 'Quotation yang dipilih tidak terhubung dengan SPK yang dipilih');
                    }
                }
            }

            if ($tipe === 'addendum' && $pksIndukId) {
                $pksInduk = Pks::query()->select(['id', 'leads_id', 'quotation_id'])->find($pksIndukId);
                if ($pksInduk && (int) $pksInduk->leads_id !== (int) $leadsId) {
                    $validator->errors()->add('pks_induk_id', 'PKS induk tidak terhubung dengan leads yang dipilih');
                }

                if ($pksInduk && $quotationId && $pksInduk->quotation_id && (int) $pksInduk->quotation_id !== (int) $quotationId) {
                    $validator->errors()->add('quotation_id', 'Quotation yang dipilih tidak sama dengan quotation pada PKS induk');
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        $tipe = $this->route('tipe');
        $spkId = $this->input('spk_id');
        $quotationId = $this->input('quotation_id');

        if ($tipe === 'baru' && $spkId && !$quotationId) {
            $spk = Spk::query()->select(['id', 'quotation_id'])->find($spkId);
            $resolvedQuotationId = $spk ? $this->resolveSpkQuotationId($spk) : null;

            if ($resolvedQuotationId) {
                $this->merge([
                    'quotation_id' => $resolvedQuotationId,
                ]);
            }
        }
    }

    private function resolveSpkQuotationId(Spk $spk): ?int
    {
        if ($spk->quotation_id) {
            return (int) $spk->quotation_id;
        }

        $quotationIds = SpkSite::query()
            ->where('spk_id', $spk->id)
            ->whereNull('deleted_at')
            ->pluck('quotation_id')
            ->filter()
            ->unique()
            ->values();

        if ($quotationIds->count() === 1) {
            return (int) $quotationIds->first();
        }

        return null;
    }
}
