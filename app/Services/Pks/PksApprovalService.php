<?php

namespace App\Services\Pks;

use App\Models\Client;
use App\Models\CustomerActivity;
use App\Models\HrisSite;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationMargin;
use App\Models\Site;
use App\Models\Spk;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PksApprovalService
{
    public function __construct(
        private PksHelperService $helperService,
        private PksQueryService $queryService
    ) {}

    public function approvePks($pks, $otLevel): void
    {
        $statusMap = [
            1 => 2, 2 => 3, 3 => 4, 4 => 5,
        ];

        $approveField = "ot{$otLevel}";

        $pks->update([
            $approveField => Auth::user()->full_name,
            'status_pks_id' => $statusMap[$otLevel] ?? $pks->status_pks_id,
            'updated_by' => Auth::user()->full_name,
        ]);
    }

    public function approvePksById($id, $otLevel): void
    {
        $pks = Pks::find($id);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS not found');
        }

        if (!$this->queryService->isWizardFinalized($pks)) {
            throw new \RuntimeException('PKS wizard belum finalized');
        }

        $this->approvePks($pks, $otLevel);
    }

    public function activatePks($id): void
    {
        $pks = Pks::find($id);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS not found');
        }

        if (!$this->queryService->isWizardFinalized($pks)) {
            throw new \RuntimeException('PKS wizard belum finalized');
        }

        $currentDateTime = Carbon::now()->toDateTimeString();

        DB::transaction(function () use ($pks, $currentDateTime) {
            DB::connection('mysqlhris')->beginTransaction();

            try {
                $leads = $this->updateStatus($pks, $currentDateTime);
                $clientId = $this->syncCustomerToHris($leads, $currentDateTime);
                $this->processPksSites($pks, $leads, $clientId, $currentDateTime);
                $this->createCustomerActivityLog($pks, $leads, $currentDateTime);

                DB::connection('mysqlhris')->commit();
            } catch (\Throwable $e) {
                DB::connection('mysqlhris')->rollBack();
                \Log::error('Failed to activate PKS sites: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    public function updateStatus($pks, $currentDateTime): Leads
    {
        $pks->update([
            'ot5' => Auth::user()->full_name,
            'status_pks_id' => 7,
            'is_aktif' => 1,
            'updated_at' => $currentDateTime,
            'updated_by' => Auth::user()->full_name,
        ]);

        if ($pks->quotation_id) {
            Quotation::where('id', $pks->quotation_id)
                ->where('status_quotation_id', '!=', 100)
                ->update([
                    'status_quotation_id' => 6,
                    'updated_at' => $currentDateTime,
                    'updated_by' => Auth::user()->full_name,
                ]);
        }

        Spk::where('leads_id', $pks->leads_id)
            ->whereNotIn('status_spk_id', [100])
            ->update([
                'status_spk_id' => 4,
                'updated_at' => $currentDateTime,
                'updated_by' => Auth::user()->full_name,
            ]);

        $leads = Leads::find($pks->leads_id);
        if (!$leads) {
            throw new \Exception('Leads not found');
        }

        $leads->ro_id_1 = $leads->ro_id_1 ?? 0;
        $leads->ro_id_2 = $leads->ro_id_2 ?? 0;
        $leads->ro_id_3 = $leads->ro_id_3 ?? 0;
        $leads->ro_id = $leads->ro_id ?? 0;

        $leads->update([
            'status_leads_id' => 102,
            'updated_at' => $currentDateTime,
            'updated_by' => Auth::user()->full_name,
        ]);

        return $leads;
    }

    public function syncCustomerToHris($leads, $currentDateTime): int
    {
        $client = Client::where('customer_id', $leads->id)
            ->where('is_active', 1)
            ->first();

        if ($client != null) {
            return $client->id;
        }

        return Client::insertGetId([
            'customer_id' => $leads->id,
            'name' => $leads->nama_perusahaan,
            'address' => $leads->alamat ?? '-',
            'is_active' => 1,
            'created_at' => $currentDateTime,
            'created_by' => Auth::user()->id,
            'created_by_user_id' => Auth::user()->id,
            'updated_at' => $currentDateTime,
            'updated_by' => Auth::user()->id,
        ]);
    }

    public function processPksSites($pks, $leads, $clientId, $currentDateTime): void
    {
        $siteList = Site::where('pks_id', $pks->id)
            ->whereNull('deleted_at')
            ->with('quotation')
            ->get();

        foreach ($siteList as $site) {
            $quotation = $site->quotation;
            if (!$quotation) {
                continue;
            }

            $this->syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $currentDateTime);
            $this->updateQuotationCalculations($site, $quotation, $leads, $currentDateTime);
        }
    }

    private function syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $currentDateTime): void
    {
        HrisSite::create([
            'site_id' => $site->id,
            'code' => $leads->nomor,
            'proyek_id' => 0,
            'contract_number' => $pks->nomor,
            'name' => $site->nama_site,
            'address' => $site->penempatan,
            'layanan_id' => $site->kebutuhan_id,
            'client_id' => $clientId,
            'city_id' => $site->kota_id,
            'branch_id' => $leads->branch_id,
            'company_id' => $quotation->company_id,
            'pic_id_1' => $leads->ro_id_1,
            'pic_id_2' => $leads->ro_id_2,
            'pic_id_3' => $leads->ro_id_3,
            'supervisor_id' => $leads->ro_id,
            'reliever' => $quotation->joker_reliever,
            'contract_value' => 0,
            'contract_start' => $pks->kontrak_awal,
            'contract_end' => $pks->kontrak_akhir,
            'contract_terminated' => null,
            'note_terminated' => '',
            'contract_status' => 'Aktif',
            'health_insurance_status' => 'Terdaftar',
            'labor_insurance_status' => 'Terdaftar',
            'vacation' => 0,
            'attendance_machine' => '',
            'is_active' => 1,
            'created_at' => $currentDateTime,
            'created_by' => Auth::user()->id,
            'created_by_user_id' => Auth::user()->id,
            'updated_at' => $currentDateTime,
            'updated_by' => Auth::user()->id,
        ]);
    }

    private function updateQuotationCalculations($site, $quotation, $leads, $currentDateTime): void
    {
        $detailQuotation = QuotationDetail::whereNull('deleted_at')
            ->where('quotation_site_id', $site->quotation_site_id)
            ->get();

        $calcQuotation = $this->calculateQuotationSimple($quotation);
        $totalData = $this->updateHppAndCossCalculations($calcQuotation, $leads, $currentDateTime);
        $this->insertQuotationMargin($quotation, $leads, $totalData, $currentDateTime);
    }

    private function calculateQuotationSimple($quotation): object
    {
        return (object) [
            'jumlah_hc' => 0,
            'nominal_upah' => 0,
            'total_invoice' => 0,
            'total_invoice_coss' => 0,
            'ppn' => 0,
            'ppn_coss' => 0,
            'grand_total_sebelum_pajak' => 0,
            'grand_total_sebelum_pajak_coss' => 0,
            'nominal_management_fee' => 0,
            'nominal_management_fee_coss' => 0,
            'persentase' => 0,
            'persen_bunga_bank' => 0,
            'persen_insentif' => 0,
            'pembulatan' => 0,
            'pembulatan_coss' => 0,
            'penagihan' => 'Tanpa Pembulatan',
            'pph' => 0,
            'pph_coss' => 0,
            'quotation_detail' => [],
        ];
    }

    private function updateHppAndCossCalculations($calcQuotation, $leads, $currentDateTime): array
    {
        $totalNominal = $totalNominalCoss = $ppn = $ppnCoss = $totalBiaya = $totalBiayaCoss = 0;

        foreach ($calcQuotation->quotation_detail as $kbd) {
            QuotationDetailHpp::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    'updated_at' => $currentDateTime,
                    'updated_by' => Auth::user()->full_name,
                ]);

            QuotationDetailCoss::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    'updated_at' => $currentDateTime,
                    'updated_by' => Auth::user()->full_name,
                ]);

            $totalNominal += $calcQuotation->total_invoice;
            $totalNominalCoss += $calcQuotation->total_invoice_coss;
            $ppn += $calcQuotation->ppn;
            $ppnCoss += $calcQuotation->ppn_coss;
            $totalBiaya += $kbd->sub_total_personil ?? 0;
            $totalBiayaCoss += $kbd->sub_total_personil ?? 0;
        }

        $margin = $totalNominal - $ppn - $totalBiaya;
        $marginCoss = $totalNominalCoss - $ppnCoss - $totalBiayaCoss;
        $gpm = $totalBiaya > 0 ? ($margin / $totalBiaya) * 100 : 0;
        $gpmCoss = $totalBiayaCoss > 0 ? ($marginCoss / $totalBiayaCoss) * 100 : 0;

        return compact(
            'totalNominal', 'totalNominalCoss', 'ppn', 'ppnCoss',
            'totalBiaya', 'totalBiayaCoss', 'margin', 'marginCoss', 'gpm', 'gpmCoss'
        );
    }

    private function insertQuotationMargin($quotation, $leads, $totalData, $currentDateTime): void
    {
        QuotationMargin::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $leads->id,
            'nominal_hpp' => $totalData['totalNominal'],
            'nominal_harga_pokok' => $totalData['totalNominalCoss'],
            'ppn_hpp' => $totalData['ppn'],
            'ppn_harga_pokok' => $totalData['ppnCoss'],
            'total_biaya_hpp' => $totalData['totalBiaya'],
            'total_biaya_harga_pokok' => $totalData['totalBiayaCoss'],
            'margin_hpp' => $totalData['margin'],
            'margin_harga_pokok' => $totalData['marginCoss'],
            'gpm_hpp' => $totalData['gpm'],
            'gpm_harga_pokok' => $totalData['gpmCoss'],
            'created_at' => $currentDateTime,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function createCustomerActivityLog($pks, $leads, $currentDateTime): void
    {
        $nomorActivity = $this->helperService->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $currentDateTime,
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pks->nomor . ' telah diaktifkan oleh ' . Auth::user()->full_name,
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_at' => $currentDateTime,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }
}
