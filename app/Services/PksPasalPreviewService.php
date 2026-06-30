<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Pks;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Services\PksTemplate\PksTemplateFactory;
use Illuminate\Support\Arr;

class PksPasalPreviewService
{
    public function generatePreview(Pks $pks, array $input = []): array
    {
        $regenerate = (bool) ($input['regenerate'] ?? false);
        $existing = $pks->pasal_preview_payload ?? [];

        if (!$regenerate && !empty($existing) && $pks->tipe_pks !== 'addendum') {
            return $existing;
        }

        $sections = $pks->tipe_pks === 'addendum'
            ? $this->generateAddendumSections($pks, $input)
            : $this->generateTemplateSections($pks);

        $preview = [];
        foreach (array_values($sections) as $index => $section) {
            $previous = $existing[$index] ?? null;
            $preview[] = [
                'key' => 'section_' . $index,
                'pasal' => $section['pasal'],
                'judul' => $section['judul'],
                'raw_text' => ($previous['is_edited'] ?? false) ? $previous['raw_text'] : $section['raw_text'],
                'is_edited' => (bool) ($previous['is_edited'] ?? false),
            ];
        }

        $pks->forceFill([
            'pasal_preview_payload' => $preview,
        ])->save();

        return $preview;
    }

    public function updatePreview(Pks $pks, string $pasalKey, array $data): array
    {
        $preview = $pks->pasal_preview_payload ?? [];
        $found = false;

        foreach ($preview as &$section) {
            if (($section['key'] ?? null) !== $pasalKey) {
                continue;
            }

            $section['pasal'] = $data['pasal'] ?? $section['pasal'];
            $section['judul'] = $data['judul'] ?? $section['judul'];
            $section['raw_text'] = $data['raw_text'];
            $section['is_edited'] = true;
            $found = true;
            break;
        }
        unset($section);

        if (!$found) {
            throw new \InvalidArgumentException('Pasal preview tidak ditemukan');
        }

        $pks->forceFill([
            'pasal_preview_payload' => $preview,
        ])->save();

        return $preview;
    }

    private function generateTemplateSections(Pks $pks): array
    {
        $company = Company::findOrFail(Arr::get($pks->wizard_payload ?? [], 'header.company_id', $pks->company_id));
        $kebutuhan = Kebutuhan::findOrFail($pks->layanan_id);
        $ruleThr = RuleThr::findOrFail(Arr::get($pks->wizard_payload ?? [], 'header.rule_thr_id', $pks->rule_thr_id));
        $salaryRule = SalaryRule::findOrFail(Arr::get($pks->wizard_payload ?? [], 'header.salary_rule_id', $pks->salary_rule_id));

        $factory = new PksTemplateFactory();
        $service = $factory->make($pks->leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pks->nomor);

        return $service->generateAllSections();
    }

    private function generateAddendumSections(Pks $pks, array $input): array
    {
        $articles = $input['additional_articles'] ?? [];
        if (!empty($articles)) {
            return $articles;
        }

        $existing = $pks->pasal_preview_payload ?? [];
        if (!empty($existing)) {
            return $existing;
        }

        $nomorInduk = $pks->pksInduk?->nomor ?? Arr::get($pks->wizard_payload ?? [], 'source.pks_induk.nomor');

        return [[
            'pasal' => 'Addendum 1',
            'judul' => 'PASAL TAMBAHAN',
            'raw_text' => '<p>Addendum untuk PKS induk ' . e((string) $nomorInduk) . '.</p>',
        ]];
    }
}
