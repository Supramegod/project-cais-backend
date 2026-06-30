<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\KategoriSesuaiHc;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\PksPerjanjian;
use App\Models\PksWizardStatus;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\SalesActivity;
use App\Models\Site;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PksWizardFinalizeService
{
    public function finalize(Pks $pks, User $user): Pks
    {
        return DB::transaction(function () use ($pks, $user) {
            /** @var Pks $lockedPks */
            $lockedPks = Pks::with(['leads', 'quotations', 'pksInduk'])
                ->lockForUpdate()
                ->findOrFail($pks->id);

            $this->assertFinalizable($lockedPks);

            $payload = $lockedPks->wizard_payload ?? [];
            $header = Arr::get($payload, 'header', []);
            $pic = Arr::get($payload, 'pic', []);
            $sitesPayload = Arr::get($payload, 'sites', []);
            $preview = $lockedPks->pasal_preview_payload ?? [];

            $leads = $lockedPks->leads ?? Leads::findOrFail($lockedPks->leads_id);
            $company = Company::findOrFail($header['company_id'] ?? $lockedPks->company_id);
            $salaryRule = SalaryRule::findOrFail($header['salary_rule_id'] ?? $lockedPks->salary_rule_id);
            $ruleThr = RuleThr::findOrFail($header['rule_thr_id'] ?? $lockedPks->rule_thr_id);
            $kebutuhan = Kebutuhan::findOrFail($lockedPks->layanan_id);
            $kategori = !empty($header['kategori_sesuai_hc_id']) ? KategoriSesuaiHc::find($header['kategori_sesuai_hc_id']) : null;
            $loyalty = !empty($header['loyalty_id']) ? Loyalty::find($header['loyalty_id']) : null;

            $nomorFinal = preg_replace('/^draft\//', '', $lockedPks->nomor);

            $lockedPks->update([
                'nomor' => $nomorFinal,
                'tgl_pks' => $header['tanggal_pks'] ?? $lockedPks->tgl_pks,
                'kontrak_awal' => $header['tanggal_awal_kontrak'] ?? $lockedPks->kontrak_awal,
                'kontrak_akhir' => $header['tanggal_akhir_kontrak'] ?? $lockedPks->kontrak_akhir,
                'company_id' => $company->id,
                'salary_rule_id' => $salaryRule->id,
                'rule_thr_id' => $ruleThr->id,
                'kategori_sesuai_hc_id' => $kategori?->id,
                'kategori_sesuai_hc' => $kategori?->nama,
                'loyalty_id' => $loyalty?->id,
                'loyalty' => $loyalty?->nama,
                'pic_1' => $pic['pic_1'] ?? null,
                'jabatan_pic_1' => $pic['jabatan_pic_1'] ?? null,
                'email_pic_1' => $pic['email_pic_1'] ?? null,
                'telp_pic_1' => $pic['telp_pic_1'] ?? null,
                'pic_2' => $pic['pic_2'] ?? null,
                'jabatan_pic_2' => $pic['jabatan_pic_2'] ?? null,
                'email_pic_2' => $pic['email_pic_2'] ?? null,
                'telp_pic_2' => $pic['telp_pic_2'] ?? null,
                'pic_3' => $pic['pic_3'] ?? null,
                'jabatan_pic_3' => $pic['jabatan_pic_3'] ?? null,
                'email_pic_3' => $pic['email_pic_3'] ?? null,
                'telp_pic_3' => $pic['telp_pic_3'] ?? null,
                'wizard_status_id' => PksWizardStatus::FINALIZED,
                'wizard_current_step' => 7,
                'finalized_at' => now(),
                'updated_by' => $user->full_name,
                'template_payload' => $this->updateTemplatePayloadForFinalize($lockedPks->template_payload ?? [], $nomorFinal),
            ]);

            Site::where('pks_id', $lockedPks->id)->delete();
            if ($lockedPks->tipe_pks !== 'addendum') {
                $this->syncFinalSites($lockedPks, $sitesPayload, $nomorFinal, $kebutuhan, $leads, $user);
            }

            PksPerjanjian::where('pks_id', $lockedPks->id)->delete();
            foreach ($preview as $section) {
                PksPerjanjian::create([
                    'pks_id' => $lockedPks->id,
                    'pasal' => $section['pasal'],
                    'judul' => $section['judul'],
                    'raw_text' => $section['raw_text'],
                    'created_by' => $user->full_name,
                ]);
            }

            Spk::where('leads_id', $leads->id)
                ->whereNotIn('status_spk_id', [100])
                ->update([
                    'status_spk_id' => 3,
                    'updated_by' => $user->full_name,
                ]);

            if ($lockedPks->quotation_id) {
                Quotation::where('id', $lockedPks->quotation_id)
                    ->where('status_quotation_id', '!=', 100)
                    ->update([
                        'status_quotation_id' => 5,
                        'updated_by' => $user->full_name,
                    ]);
            }

            if (!in_array($leads->status_leads_id, [100, 101], true)) {
                $leads->update([
                    'status_leads_id' => 99,
                    'updated_by' => $user->full_name,
                    'tgl_leads' => Carbon::now()->toDateString(),
                ]);
            }

            $this->createFinalizeActivity($lockedPks->fresh(['quotations']), $leads, $user);

            return $lockedPks->fresh(['wizardStatus', 'sites', 'perjanjian']);
        });
    }

    private function assertFinalizable(Pks $pks): void
    {
        if ((int) $pks->wizard_status_id === PksWizardStatus::FINALIZED) {
            throw new \InvalidArgumentException('PKS wizard sudah finalized');
        }

        $requiredSteps = $pks->tipe_pks === 'addendum'
            ? [1, 2, 4, 5, 6, 7]
            : [1, 2, 3, 4, 5, 6, 7];

        $completed = $pks->wizard_completed_steps ?? [];
        foreach ($requiredSteps as $step) {
            if (!in_array($step, $completed, true)) {
                throw new \InvalidArgumentException("Step {$step} belum selesai");
            }
        }

        if (empty($pks->pasal_preview_payload)) {
            throw new \InvalidArgumentException('Pasal preview belum tersedia');
        }

        $header = Arr::get($pks->wizard_payload ?? [], 'header', []);
        foreach (['tanggal_pks', 'tanggal_awal_kontrak', 'tanggal_akhir_kontrak', 'company_id', 'salary_rule_id', 'rule_thr_id'] as $field) {
            if (empty($header[$field])) {
                throw new \InvalidArgumentException("Header PKS belum lengkap: {$field}");
            }
        }
    }

    private function syncFinalSites(Pks $pks, array $sitesPayload, string $nomorFinal, Kebutuhan $kebutuhan, Leads $leads, User $user): void
    {
        $siteIds = $pks->tipe_pks === 'baru'
            ? ($sitesPayload['site_ids'] ?? [])
            : ($sitesPayload['quotation_site_ids'] ?? []);

        $isBaru = $pks->tipe_pks === 'baru';
        $sourceSites = $isBaru
            ? SpkSite::whereIn('id', $siteIds)->with('quotation:id,nomor')->get()->keyBy('id')
            : QuotationSite::whereIn('id', $siteIds)->with('quotation:id,nomor')->get()->keyBy('id');

        foreach ($siteIds as $index => $id) {
            $sourceSite = $sourceSites->get($id);
            if (!$sourceSite) {
                continue;
            }

            $nomorSite = $nomorFinal . '-' . sprintf('%04d', $index + 1);
            $namaProyek = sprintf(
                '%s-%s.%s.%s',
                Carbon::parse($pks->kontrak_awal)->format('my'),
                Carbon::parse($pks->kontrak_akhir)->format('my'),
                strtoupper(substr($kebutuhan->nama, 0, 2)),
                strtoupper($leads->nama_perusahaan)
            );

            Site::create([
                'pks_id' => $pks->id,
                'leads_id' => $leads->id,
                'quotation_id' => $sourceSite->quotation_id,
                'nomor' => $nomorSite,
                'nomor_proyek' => $nomorSite,
                'nama_proyek' => $namaProyek,
                'nama_site' => $sourceSite->nama_site,
                'provinsi_id' => $sourceSite->provinsi_id,
                'provinsi' => $sourceSite->provinsi,
                'kota_id' => $sourceSite->kota_id,
                'kota' => $sourceSite->kota,
                'nominal_upah' => $sourceSite->nominal_upah,
                'penempatan' => $sourceSite->penempatan,
                'kebutuhan_id' => $leads->kebutuhan_id,
                'kebutuhan' => $kebutuhan->nama,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
                'spk_id' => $isBaru ? $sourceSite->spk_id : null,
                'spk_site_id' => $isBaru ? $sourceSite->id : null,
                'quotation_site_id' => $isBaru ? $sourceSite->quotation_site_id : $sourceSite->id,
                'nomor_quotation' => $isBaru ? $sourceSite->nomor_quotation : ($sourceSite->quotation->nomor ?? null),
                'ump' => $sourceSite->ump ?? null,
                'umk' => $sourceSite->umk ?? null,
            ]);
        }
    }

    private function createFinalizeActivity(Pks $pks, Leads $leads, User $user): void
    {
        $now = Carbon::now();
        $nomorActivity = $this->generateNomorActivity($leads);

        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33], true)) {
            $kebutuhanId = $pks->quotations?->kebutuhan_id ?? $pks->layanan_id;
            $kebutuhanNama = $pks->quotations?->kebutuhan ?? $pks->layanan;

            SalesActivity::create([
                'leads_id' => $pks->leads_id,
                'tgl_activity' => $now,
                'jenis_activity' => 'PKS',
                'notulen' => "pks {$pks->nomor} finalized untuk kebutuhan {$kebutuhanNama}",
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            return;
        }

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $now,
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pks->nomor . ' finalized',
            'is_activity' => 0,
            'user_id' => $user->id,
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function updateTemplatePayloadForFinalize(array $templatePayload, string $nomorFinal): array
    {
        $templatePayload['pks']['nomor'] = $nomorFinal;
        return $templatePayload;
    }

    private function generateNomorActivity(Leads $leads): string
    {
        $now = Carbon::now();

        $prefix = 'CAT/';
        $prefix .= match ($leads->kebutuhan_id) {
            1 => 'SG/',
            2 => 'LS/',
            3 => 'CS/',
            4 => 'LL/',
            default => 'NN/',
        };
        $prefix .= $leads->nomor . '-';

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . '-%')->count();

        return $prefix . $month . $year . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}
