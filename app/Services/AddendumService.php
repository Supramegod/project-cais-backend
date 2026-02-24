<?php
namespace App\Services;

use App\Models\Pks;
use App\Models\Site;
use App\Models\Quotation;
use App\Models\Leads;
use App\Models\CustomerActivity;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddendumService
{
    public function process(Quotation $newQuot): array
    {
        $results = [
            'new_quot_id' => $newQuot->id,
            'new_quot_no' => $newQuot->nomor,
            'added_sites' => 0,
            'errors' => [],
            'warnings' => []
        ];

        // Validasi tipe addendum
        if ($newQuot->tipe_quotation !== 'addendum') {
            $results['errors'][] = 'Quotation bukan tipe addendum';
            return $results;
        }

        if (empty($newQuot->quotation_referensi_id)) {
            $results['errors'][] = 'Quotation addendum tidak memiliki parent quotation';
            return $results;
        }

        $parentQuot = Quotation::find($newQuot->quotation_referensi_id);
        if (!$parentQuot) {
            $results['errors'][] = 'Parent quotation tidak ditemukan';
            return $results;
        }

        DB::beginTransaction();

        try {
            // Cari PKS melalui site yang terkait dengan parent quotation
            $siteParent = Site::where('quotation_id', $parentQuot->id)->first();
            $pks = null;

            if ($siteParent && $siteParent->pks_id) {
                $pks = Pks::find($siteParent->pks_id);
            }

            // Fallback: cari PKS langsung berdasarkan quotation_id (jika ada relasi langsung)
            if (!$pks) {
                $pks = Pks::where('quotation_id', $parentQuot->id)->first();
            }

            if (!$pks) {
                $results['errors'][] = 'Tidak ditemukan PKS untuk parent quotation';
                DB::rollBack();
                return $results;
            }
            $originalPksNumber = $pks->nomor;
            $newPksNumber = null;
         
            $originalPksNumber = $pks->nomor;
            $newPksNumber = null;

            if (preg_match('/^ADD\/(.+)\/(\d+)$/', $originalPksNumber, $matches)) {
                // Sudah dalam format ADD, ambil nomor asli dan urutan terakhir
                $nomorAsli = $matches[1];
                $urutanTerakhir = (int) $matches[2];
                $urutan = $urutanTerakhir + 1;
                $newPksNumber = "ADD/{$nomorAsli}/" . str_pad($urutan, 5, '0', STR_PAD_LEFT);
            } else {
                $nomorAsli = $originalPksNumber;
                $newPksNumber = "ADD/{$nomorAsli}/" . str_pad(1, 5, '0', STR_PAD_LEFT);
            }

            $pks->nomor = $newPksNumber;
            $pks->save();

            // Catat di hasil (opsional)
            // $results['pks_number_updated'] = $newPksNumber;

            // Ambil SPK ID dari site yang sudah ada di PKS ini
            $sampleSite = Site::where('pks_id', $pks->id)->first();
            // $spkId = $sampleSite ? $sampleSite->spk_id : null;

            // if (!$spkId) {
            //     $results['errors'][] = 'Tidak ditemukan SPK untuk PKS ini';
            //     DB::rollBack();
            //     return $results;
            // }

            $addendumSites = $newQuot->quotationSites;

            if ($addendumSites->isEmpty()) {
                $results['warnings'][] = 'Tidak ada site pada quotation addendum';
                DB::commit();
                return $results;
            }

            foreach ($addendumSites as $addendumSite) {
                // Generate nomor site berdasarkan urutan terbaru di PKS
                $siteCount = Site::where('pks_id', $pks->id)->count();
                $nomorSite = $pks->nomor . '-' . sprintf("%04d", $siteCount + 1);

                // Generate nama proyek
                $namaProyek = sprintf(
                    '%s-%s.%s.%s',
                    Carbon::parse($pks->kontrak_awal)->format('my'),
                    Carbon::parse($pks->kontrak_akhir)->format('my'),
                    strtoupper(substr($pks->layanan ?? 'NN', 0, 2)),
                    strtoupper($pks->nama_perusahaan)
                );

                // Buat Site baru (tanpa spk_site_id)
                Site::create([
                    'quotation_id' => $newQuot->id,
                    // 'spk_id' => $spkId,
                    'pks_id' => $pks->id,
                    'quotation_site_id' => $addendumSite->id,
                    'spk_site_id' => null, // tidak buat SpkSite
                    'leads_id' => $parentQuot->leads_id,
                    'nomor' => $nomorSite,
                    'nomor_proyek' => $namaProyek,
                    'nama_proyek' => $namaProyek,
                    'nama_site' => $addendumSite->nama_site,
                    'provinsi_id' => $addendumSite->provinsi_id,
                    'provinsi' => $addendumSite->provinsi,
                    'kota_id' => $addendumSite->kota_id,
                    'kota' => $addendumSite->kota,
                    'ump' => $addendumSite->ump,
                    'umk' => $addendumSite->umk,
                    'nominal_upah' => $addendumSite->nominal_upah,
                    'penempatan' => $addendumSite->penempatan,
                    'kebutuhan_id' => $addendumSite->kebutuhan_id,
                    'kebutuhan' => $addendumSite->kebutuhan,
                    'nomor_quotation' => $newQuot->nomor,
                    'created_by' => Auth::user()->full_name ?? 'System'
                ]);

                $results['added_sites']++;
            }

            // Catat aktivitas
            $this->createAddendumActivity($newQuot, $parentQuot, $pks);

            DB::commit();

            Log::info('Addendum berhasil', $results);
            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = $e->getMessage();
            Log::error('Error addendum: ' . $e->getMessage());
            return $results;
        }
    }

    private function createAddendumActivity(Quotation $newQuot, Quotation $parentQuot, Pks $pks): void
    {
        $leads = $newQuot->leads;
        if (!$leads)
            return;

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $newQuot->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNo($leads->id),
            'tipe' => 'Addendum',
            'notes' => 'Quotation addendum ' . $newQuot->nomor . ' menambahkan site ke PKS ' . $pks->nomor,
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name ?? 'System'
        ]);
    }

    private function generateActivityNo($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => "SG/",
                2 => "LS/",
                3 => "CS/",
                4 => "LL/",
                default => "NN/"
            };
            $prefix .= $leads->nomor . "-";
        } else {
            $prefix .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . "-%")->count();
        $seq = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $seq;
    }
}