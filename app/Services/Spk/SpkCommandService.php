<?php

namespace App\Services\Spk;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\SalesActivity;
use App\Models\Spk;
use App\Models\SpkSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpkCommandService
{
    use SpkActivityTrait;

    public function add(int $leadsId, string $tanggalSpk, array $siteIds): Spk
    {
        return DB::transaction(function () use ($leadsId, $tanggalSpk, $siteIds) {
            $leads = Leads::whereNull('deleted_at')->find($leadsId);
            if (! $leads) {
                throw new \Exception("Leads dengan ID {$leadsId} tidak ditemukan atau sudah dihapus.");
            }
            if (QuotationSite::whereIn('id', $siteIds)->where('leads_id', '!=', $leadsId)->exists()) {
                throw new \Exception('Beberapa site yang dipilih tidak termasuk dalam leads yang dipilih.');
            }
            if (QuotationSite::whereIn('id', $siteIds)->whereHas('spkSite')->exists()) {
                throw new \Exception('Beberapa site yang dipilih sudah memiliki SPK.');
            }
            $firstSite = QuotationSite::find($siteIds[0]);
            $quotationId = $firstSite?->quotation_id;

            // Ambil company_id dari quotation
            $quotation = $quotationId ? Quotation::find($quotationId) : null;
            $companyId = $quotation?->company_id ?? 0;
            $spkNomor = $this->generateNomorNew($leads->id, $companyId);
            $spk = Spk::create([
                'leads_id' => $leads->id, 'nomor' => $spkNomor, 'tgl_spk' => $tanggalSpk,
                'nama_perusahaan' => $leads->nama_perusahaan, 'tim_sales_id' => $leads->tim_sales_id,
                'tim_sales_d_id' => $leads->tim_sales_d_id, 'link_spk_disetujui' => null, 'status_spk_id' => 1,
                'created_by' => Auth::user()->full_name ?? 'System', 'created_by_user_id' => Auth::id(),
            ]);
            $this->createSpkSites($spk, $siteIds);
            $this->createCustomerActivity($leads, $spk, $spkNomor);
            if ($quotationId) {
                Quotation::where('id', $quotationId)->where('status_quotation_id', '!=', 100)
                    ->update(['status_quotation_id' => 4, 'updated_by' => Auth::user()->full_name ?? 'System']);
            }
            if (! in_array($leads->status_leads_id, [99, 100, 101, 102])) {
                $leads->update(['status_leads_id' => 3, 'updated_by' => Auth::user()->full_name ?? 'System']);
            }

            return $spk->load(['spkSites', 'leads']);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $spk = Spk::find($id);
            if (! $spk) {
                throw new \Exception('SPK not found');
            }
            SpkSite::where('spk_id', $id)->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
            $spk->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
            $this->createDeleteActivity($spk);
        });
    }

    public function deleteSite(int $siteId): void
    {
        DB::transaction(function () use ($siteId) {
            $spkSite = SpkSite::find($siteId);
            if (! $spkSite) {
                throw new \Exception('SPK site not found');
            }
            $spkSite->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
            $remainingSites = SpkSite::where('spk_id', $spkSite->spk_id)->whereNull('deleted_at')->count();
            if ($remainingSites === 0) {
                $spk = Spk::find($spkSite->spk_id);
                if ($spk) {
                    $spk->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
                    $this->createDeleteActivity($spk);
                }
            }
            $lead = Leads::find($spkSite->leads_id);
            if ($lead) {
                $lead->tgl_leads = Carbon::now();
                $lead->save();
            }
        });
    }

    private function createSpkSites(Spk $spk, array $siteIds): void
    {
        foreach ($siteIds as $siteId) {
            $qs = QuotationSite::with('quotation')->find($siteId);
            if (! $qs) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak ditemukan.");
            }
            if ($qs->leads_id != $spk->leads_id) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak termasuk dalam leads yang dipilih.");
            }
            SpkSite::create([
                'spk_id' => $spk->id, 'quotation_id' => $qs->quotation_id, 'quotation_site_id' => $qs->id,
                'leads_id' => $qs->leads_id, 'nama_site' => $qs->nama_site, 'provinsi_id' => $qs->provinsi_id,
                'provinsi' => $qs->provinsi, 'kota_id' => $qs->kota_id, 'kota' => $qs->kota,
                'ump' => $qs->ump, 'umk' => $qs->umk, 'nominal_upah' => $qs->nominal_upah, 'penempatan' => $qs->penempatan,
                'kebutuhan_id' => $qs->quotation->kebutuhan_id, 'kebutuhan' => $qs->quotation->kebutuhan,
                'jenis_site' => $qs->quotation->jumlah_site, 'nomor_quotation' => $qs->quotation->nomor,
                'created_by' => Auth::user()->full_name ?? 'System', 'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    private function createCustomerActivity($leads, $spk, $spkNomor): void
    {
        $user = Auth::user();
        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $this->createSalesActivity($spk, $leads);
        } else {
            CustomerActivity::create([
                'leads_id' => $leads->id, 'spk_id' => $spk->id, 'branch_id' => $leads->branch_id,
                'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK', 'notes' => 'SPK dengan nomor : '.$spkNomor.' terbentuk',
                'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function createSalesActivity($spk, $leads): void
    {
        $user = Auth::user();
        $kebutuhanList = LeadsKebutuhan::where('leads_id', $spk->leads_id)->whereNotNull('tim_sales_d_id')->get();
        foreach ($kebutuhanList as $lk) {
            if (SpkSite::where('spk_id', $spk->id)->where('kebutuhan_id', $lk->kebutuhan_id)->exists()) {
                SalesActivity::create([
                    'leads_id' => $spk->leads_id, 'leads_kebutuhan_id' => $lk->id, 'spk_id' => $spk->id, 'tgl_activity' => Carbon::now(),
                    'jenis_activity' => 'SPK', 'notulen' => "SPK baru {$spk->nomor} dibuat untuk kebutuhan {$lk->kebutuhan->nama}",
                    'created_by' => $user->full_name, 'created_by_user_id' => $user->id,
                ]);
            }
        }
    }

    private function createDeleteActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);
        CustomerActivity::create([
            'leads_id' => $leads->id, 'spk_id' => $spk->id, 'branch_id' => $leads->branch_id,
            'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK', 'notes' => 'SPK dengan nomor : '.$spk->nomor.' dihapus',
            'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::user()->id,
        ]);
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function generateNomorNew(int $leadsId, int $companyId = 0): string
    {
        return app(\App\Services\Spk\SpkNumberingService::class)->generate($leadsId, $companyId);
    }
}
