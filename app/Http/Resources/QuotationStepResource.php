<?php

namespace App\Http\Resources;
use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Province;
use App\Models\Ump;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Position;
use App\Models\ManagementFee;
use App\Models\JenisPerusahaan;
use App\Models\BidangPerusahaan;
use App\Models\AplikasiPendukung;
use App\Models\Barang;
use App\Models\Training;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class QuotationStepResource extends JsonResource
{
    public function toArray($request)
    {
        $step = $this['step'];

        return [
            'quotation' => new QuotationResource($this['quotation']),
            'step' => $step,
            'step_data' => $this->getStepData($step),
            'additional_data' => $this['additional_data'] ?? [],
        ];
    }

    private function getStepData($step)
    {
        $data = [];

        switch ($step) {
            case 1:
                $data = [
                    'company_list' => Company::where('is_active', 1)->get(),
                    'kebutuhan_list' => Kebutuhan::all(),
                    'province_list' => Province::get()->map(function ($province) {
                        $ump = Ump::where('is_aktif', 1)
                            ->where('province_id', $province->id)
                            ->first();
                             $umpValue = $ump ? $ump->ump : 0;
                        $province->ump = $ump ? "UMP : Rp. " . number_format($umpValue, 2, ",", ".") : "UMP : Rp. 0";
                        return $province;
                    }),
                ];
                break;

            case 2:
                $data = [
                    'salary_rules' => SalaryRule::all(),
                    'top_list' => Top::orderBy('nama', 'asc')->get(),
                ];
                break;

            case 3:
                $data = [
                    'positions' => Position::where('is_active', 1)
                        ->where('layanan_id', $this['quotation']->kebutuhan_id)
                        ->orderBy('name', 'asc')
                        ->get(),
                ];
                break;

            case 4:
                $data = [
                    'management_fees' => ManagementFee::all(),
                ];
                break;

            case 5:
                $data = [
                    'jenis_perusahaan' => JenisPerusahaan::all(),
                    'bidang_perusahaan' => BidangPerusahaan::all(),
                ];
                break;

            case 6:
                // Asumsi model AplikasiPendukung
                $data = [
                    'aplikasi_pendukung' => AplikasiPendukung::all(),
                    'selected_aplikasi' => $this['quotation']->quotationAplikasis->pluck('aplikasi_pendukung_id')->toArray(),
                ];
                break;

            case 7:
                $data = $this->getKaporlapData();
                break;

            case 8:
                $data = $this->getDevicesData();
                break;

            case 9:
                $data = [
                    'chemicals' => Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                        ->orderBy("urutan", "asc")
                        ->orderBy("nama", "asc")
                        ->get()
                        ->map(function ($chemical) {
                            $chemical->harga_formatted = number_format($chemical->harga, 0, ",", ".");
                            return $chemical;
                        }),
                ];
                break;

            case 10:
                $data = [
                    'ohc_items' => Barang::whereIn('jenis_barang_id', [6, 7, 8])
                        ->orderBy("urutan", "asc")
                        ->orderBy("nama", "asc")
                        ->get()
                        ->map(function ($ohc) {
                            $ohc->harga_formatted = number_format($ohc->harga, 0, ",", ".");
                            return $ohc;
                        }),
                    'trainings' => Training::all(),
                    'selected_trainings' => $this['quotation']->quotationTrainings->pluck('training_id')->toArray(),
                ];
                break;

            case 11:
                $data = $this->getCalculationData();
                break;

            case 12:
                $data = [
                    'kerjasama_list' => $this['quotation']->quotationKerjasamas,
                ];
                break;
        }

        return $data;
    }

    private function getKaporlapData()
    {
        $arrKaporlap = [1, 2, 3, 4, 5];
        if ($this['quotation']->kebutuhan_id != 1) {
            $arrKaporlap = [5];
        }

        $listJenis = DB::table('m_jenis_barang')->whereIn('id', $arrKaporlap)->get();
        $listKaporlap = DB::table('m_barang')
            ->whereNull('deleted_at')
            ->whereIn('jenis_barang_id', $arrKaporlap)
            ->orderBy("urutan", "asc")
            ->orderBy("nama", "asc")
            ->get();

        // Set default quantities
        foreach ($listKaporlap as $kaporlap) {
            foreach ($this['quotation']->quotationDetails as $detail) {
                $fieldName = 'jumlah_' . $detail->id;
                $kaporlap->$fieldName = 0;

                if ($this['quotation']->revisi == 0) {
                    $qtyDefault = DB::table('m_barang_default_qty')
                        ->whereNull('deleted_at')
                        ->where('layanan_id', $this['quotation']->kebutuhan_id)
                        ->where('barang_id', $kaporlap->id)
                        ->first();
                    if ($qtyDefault) {
                        $kaporlap->$fieldName = $qtyDefault->qty_default;
                    }
                } else {
                    $existing = DB::table('sl_quotation_kaporlap')
                        ->whereNull('deleted_at')
                        ->where('barang_id', $kaporlap->id)
                        ->where('quotation_detail_id', $detail->id)
                        ->first();
                    if ($existing) {
                        $kaporlap->$fieldName = $existing->jumlah;
                    }
                }
            }
        }

        return [
            'jenis_barang' => $listJenis,
            'kaporlap_items' => $listKaporlap,
        ];
    }

    private function getDevicesData()
    {
        $listJenis = DB::table('m_jenis_barang')->whereIn('id', [9, 10, 11, 12, 17])->get();
        $listDevices = DB::table('m_barang')
            ->whereNull('deleted_at')
            ->whereIn('jenis_barang_id', [8, 9, 10, 11, 12, 17])
            ->orderBy("urutan", "asc")
            ->orderBy("nama", "asc")
            ->get();

        foreach ($listDevices as $device) {
            $device->jumlah = 0;

            if ($this['quotation']->revisi == 0) {
                $qtyDefault = DB::table('m_barang_default_qty')
                    ->whereNull('deleted_at')
                    ->where('layanan_id', $this['quotation']->kebutuhan_id)
                    ->where('barang_id', $device->id)
                    ->first();
                if ($qtyDefault) {
                    $device->jumlah = $qtyDefault->qty_default;
                }
            } else {
                $existing = DB::table('sl_quotation_devices')
                    ->whereNull('deleted_at')
                    ->where('barang_id', $device->id)
                    ->where('quotation_id', $this['quotation']->id)
                    ->first();
                if ($existing) {
                    $device->jumlah = $existing->jumlah;
                }
            }
        }

        return [
            'jenis_barang' => $listJenis,
            'devices_items' => $listDevices,
        ];
    }

    private function getCalculationData()
    {
        $quotationService = new \App\Services\QuotationService();
        $calculation = $quotationService->calculateQuotation($this['quotation']);

        return [
            'calculation' => $calculation,
            'daftar_tunjangan' => DB::select("SELECT DISTINCT nama_tunjangan as nama FROM sl_quotation_detail_tunjangan WHERE deleted_at is null and quotation_id = ?", [$this['quotation']->id]),
            'training_list' => DB::table('m_training')->whereNull('deleted_at')->get(),
            'jabatan_pic_list' => DB::table('m_jabatan_pic')->whereNull('deleted_at')->get(),
        ];
    }
}