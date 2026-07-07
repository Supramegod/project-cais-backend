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
            'candidate_spk_ids' => FluentRule::array()->nullable()->children([
                '*' => FluentRule::integer()->required()->exists('sl_spk', 'id'),
            ]),
            'candidate_quotation_ids' => FluentRule::array()->nullable()->children([
                '*' => FluentRule::integer()->required()->exists('sl_quotation', 'id'),
            ]),
        ];

        if ($tipe === 'addendum') {
            $rules['pks_induk_id'] = FluentRule::integer()->required()->exists('sl_pks', 'id');
            $rules['company_id'] = FluentRule::integer()->nullable()->exists('mysqlhris.m_company', 'id');
        } else {
            $rules['company_id'] = FluentRule::integer()->required()->exists('mysqlhris.m_company', 'id');
        }

        if ($tipe === 'baru') {
            $rules['candidate_spk_ids'] = FluentRule::array()->required()->min(1)->children([
                '*' => FluentRule::integer()->required()->exists('sl_spk', 'id'),
            ]);
        }

        if ($tipe === 'rekontrak') {
            $rules['candidate_quotation_ids'] = FluentRule::array()->required()->min(1)->children([
                '*' => FluentRule::integer()->required()->exists('sl_quotation', 'id'),
            ]);
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $candidateSpkIds = $this->input('candidate_spk_ids', []);
        $candidateQuotationIds = $this->input('candidate_quotation_ids', []);

        if ($this->filled('spk_id')) {
            $candidateSpkIds[] = (int) $this->input('spk_id');
        }

        if ($this->filled('quotation_id')) {
            $candidateQuotationIds[] = (int) $this->input('quotation_id');
        }

        $candidateSpkIds = collect($candidateSpkIds)->filter()->map(fn($id) => (int) $id)->unique()->values()->all();
        $candidateQuotationIds = collect($candidateQuotationIds)->filter()->map(fn($id) => (int) $id)->unique()->values()->all();

        if ($this->route('tipe') === 'baru' && !empty($candidateSpkIds) && empty($candidateQuotationIds)) {
            $derivedQuotationIds = Spk::query()
                ->whereIn('id', $candidateSpkIds)
                ->pluck('quotation_id')
                ->merge(
                    SpkSite::query()
                        ->whereIn('spk_id', $candidateSpkIds)
                        ->whereNull('deleted_at')
                        ->pluck('quotation_id')
                )
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $candidateQuotationIds = $derivedQuotationIds;
        }

        $this->merge([
            'candidate_spk_ids' => $candidateSpkIds,
            'candidate_quotation_ids' => $candidateQuotationIds,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tipe = $this->route('tipe');
            $leadsId = (int) $this->input('leads_id');
            $candidateSpkIds = collect($this->input('candidate_spk_ids', []))->map(fn($id) => (int) $id);
            $candidateQuotationIds = collect($this->input('candidate_quotation_ids', []))->map(fn($id) => (int) $id);
            $pksIndukId = $this->input('pks_induk_id');

            if ($candidateQuotationIds->isNotEmpty()) {
                $invalidQuotationExists = Quotation::query()
                    ->whereIn('id', $candidateQuotationIds->all())
                    ->where('leads_id', '!=', $leadsId)
                    ->exists();

                if ($invalidQuotationExists) {
                    $validator->errors()->add('candidate_quotation_ids', 'Ada quotation yang tidak terhubung dengan leads yang dipilih');
                }
            }

            if ($candidateSpkIds->isNotEmpty()) {
                $invalidSpkExists = Spk::query()
                    ->whereIn('id', $candidateSpkIds->all())
                    ->where('leads_id', '!=', $leadsId)
                    ->exists();

                if ($invalidSpkExists) {
                    $validator->errors()->add('candidate_spk_ids', 'Ada SPK yang tidak terhubung dengan leads yang dipilih');
                }
            }

            if ($candidateSpkIds->isNotEmpty() && $candidateQuotationIds->isNotEmpty()) {
                $linkedQuotationIds = Spk::query()
                    ->whereIn('id', $candidateSpkIds->all())
                    ->pluck('quotation_id')
                    ->merge(
                        SpkSite::query()
                            ->whereIn('spk_id', $candidateSpkIds->all())
                            ->whereNull('deleted_at')
                            ->pluck('quotation_id')
                    )
                    ->filter()
                    ->map(fn($id) => (int) $id)
                    ->unique();

                $outsideSelection = $candidateQuotationIds->diff($linkedQuotationIds);
                if ($outsideSelection->isNotEmpty()) {
                    $validator->errors()->add('candidate_quotation_ids', 'Ada quotation candidate yang tidak terhubung dengan SPK candidate yang dipilih');
                }
            }

            if ($tipe === 'addendum' && $pksIndukId) {
                $pksInduk = Pks::query()->select(['id', 'leads_id'])->find($pksIndukId);
                if ($pksInduk && (int) $pksInduk->leads_id !== $leadsId) {
                    $validator->errors()->add('pks_induk_id', 'PKS induk tidak terhubung dengan leads yang dipilih');
                }
            }
        });
    }
}
