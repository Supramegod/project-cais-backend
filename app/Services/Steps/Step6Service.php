<?php

namespace App\Services\Steps;

use App\Models\AplikasiPendukung;
use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationDetail;
use App\Models\QuotationDevices;
use App\Services\Steps\Traits\StepHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step6Service
{
    use StepHelperTrait;

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now();
            $user = Auth::user()->full_name;

            if ($request->has('aplikasi_pendukung') && is_array($request->aplikasi_pendukung)) {
                $aplikasiIds = $request->aplikasi_pendukung;

                // Preload semua aplikasi pendukung yang dipilih
                $aplikasiList = AplikasiPendukung::whereIn('id', $aplikasiIds)
                    ->get()
                    ->keyBy('id');

                // Hitung jumlah HC per site dari database
                $siteHcMap = QuotationDetail::where('quotation_id', $quotation->id)
                    ->whereNull('deleted_at')
                    ->select('quotation_site_id', DB::raw('SUM(jumlah_hc) as total_hc'))
                    ->groupBy('quotation_site_id')
                    ->pluck('total_hc', 'quotation_site_id')
                    ->toArray();

                // Step 1 — Update atau create QuotationAplikasi
                $quotationAplikasiIds = [];
                $qaAplikasiMap = [];

                foreach ($aplikasiIds as $aplikasiId) {
                    $app = $aplikasiList->get($aplikasiId);
                    if (! $app) {
                        continue;
                    }

                    $qa = QuotationAplikasi::updateOrCreate(
                        [
                            'quotation_id' => $quotation->id,
                            'aplikasi_pendukung_id' => $aplikasiId,
                        ],
                        [
                            'aplikasi_pendukung' => $app->nama,
                            'harga' => $app->harga,
                            'updated_at' => $currentDateTime,
                            'updated_by' => $user,
                            'deleted_at' => null,
                        ]
                    );

                    $quotationAplikasiIds[] = $qa->id;
                    $qaAplikasiMap[$qa->id] = $aplikasiId;
                }

                QuotationDevices::where('quotation_id', $quotation->id)
                    ->where('jenis_barang_id', 17)
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);

                $devicesToInsert = [];

                foreach ($quotationAplikasiIds as $qaId) {
                    $aplikasiId = $qaAplikasiMap[$qaId] ?? null;
                    $app = $aplikasiId ? $aplikasiList->get($aplikasiId) : null;

                    if (! $app) {
                        continue;
                    }

                    foreach ($siteHcMap as $siteId => $jumlahHc) {
                        if ($jumlahHc <= 0) {
                            continue;
                        }

                        $devicesToInsert[] = [
                            'quotation_id' => $quotation->id,
                            'quotation_aplikasi_id' => $qaId,
                            'quotation_site_id' => $siteId,
                            'barang_id' => $app->barang_id,
                            'jumlah' => $jumlahHc,
                            'harga' => $app->harga,
                            'nama' => $app->nama,
                            'jenis_barang' => 'Aplikasi Pendukung',
                            'jenis_barang_id' => 8,
                            'created_at' => $currentDateTime,
                            'created_by' => $user,
                            'created_by_user_id' => Auth::id(),
                            'updated_at' => $currentDateTime,
                            'updated_by' => $user,
                        ];
                    }
                }

                if (! empty($devicesToInsert)) {
                    QuotationDevices::insert($devicesToInsert);
                }

                QuotationAplikasi::where('quotation_id', $quotation->id)
                    ->whereNotIn('aplikasi_pendukung_id', $aplikasiIds)
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);

            } else {
                QuotationAplikasi::where('quotation_id', $quotation->id)
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);

                QuotationDevices::where('quotation_id', $quotation->id)
                    ->where('jenis_barang_id', 8)
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);
            }

            // Update timestamp quotation
            $quotation->update([
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
                'calculated_at' => null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateStep6', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
