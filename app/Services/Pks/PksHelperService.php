<?php

namespace App\Services\Pks;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\SalesActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PksHelperService
{
    public function generateNomor($leadsId, $companyId, string $tipePks = 'baru', ?int $pksIndukId = null): string
    {
        return app(PksNumberingService::class)->generate($leadsId, $companyId, $tipePks, $pksIndukId);
    }

    public function generateNomorAddendum($pksIndukId): string
    {
        $pksInduk = Pks::findOrFail($pksIndukId);
        return app(PksNumberingService::class)->generate(
            $pksInduk->leads_id,
            $pksInduk->company_id ?? 0,
            'addendum',
            $pksIndukId
        );
    }

    public function generateNomorActivity(Leads $leads): string
    {
        $now = Carbon::now();

        $prefix = 'CAT/';
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => 'SG/',
                2 => 'LS/',
                3 => 'CS/',
                4 => 'LL/',
                default => 'NN/'
            };
            $prefix .= $leads->nomor . '-';
        } else {
            $prefix .= 'NN/NNNNN-';
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . '-%')->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . '-' . $sequence;
    }

    public function createCustomerActivity($leads, $customerNomor): void
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function createInitialActivity($pks, $leads, $pksNomor): void
    {
        $nomorActivity = $this->generateNomorActivity($leads);
        $user = Auth::user();
        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $this->createSalesActivity($pks, $user->full_name);
        } else {
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'pks_id' => $pks->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $nomorActivity,
                'tipe' => 'PKS',
                'notes' => 'PKS dengan nomor :' . $pksNomor . ' terbentuk',
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
            ]);
        }
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    public function createSalesActivity(Pks $pks, string $createdBy): void
    {
        $user = Auth::user();
        $quotation = $pks->quotations;
        $kebutuhanId = $quotation?->kebutuhan_id ?? $pks->layanan_id;
        $kebutuhanNama = $quotation?->kebutuhan ?? $pks->layanan;

        $leadsKebutuhan = \App\Models\LeadsKebutuhan::where('leads_id', $pks->leads_id)
            ->where('kebutuhan_id', $kebutuhanId)
            ->where('tim_sales_d_id', $user->id)
            ->first();

        SalesActivity::create([
            'leads_id' => $pks->leads_id,
            'leads_kebutuhan_id' => $leadsKebutuhan?->id,
            'pks_id' => $pks->id,
            'tgl_activity' => Carbon::now(),
            'jenis_activity' => 'PKS',
            'notulen' => "pks baru {$pks->nomor} dibuat untuk kebutuhan {$kebutuhanNama}",
            'created_by' => $createdBy,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function createCustomerCreationActivity($leads, $customerNomor, $currentDateTime): void
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $currentDateTime,
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_at' => $currentDateTime,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }
}
