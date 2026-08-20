<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use Illuminate\Http\Request;

class StepDriverService
{
    public function execute(Quotation $quotation, Request $request): void
    {
        // Kebutuhan Drivers array
        $driversData = $request->input('drivers', []);

        // Delete existing drivers
        $quotation->quotationDrivers()->delete();

        if (!empty($driversData)) {
            $formattedDrivers = array_map(function ($driver) {
                return [
                    'status_kendaraan' => $driver['status_kendaraan'] ?? null,
                    'jenis_kendaraan' => $driver['jenis_kendaraan'] ?? null,
                    'nama_kendaraan' => $driver['nama_kendaraan'] ?? null,
                    'kepemilikan_sim' => $driver['kepemilikan_sim'] ?? null,
                    'asuransi_mobil' => $driver['asuransi_mobil'] ?? null,
                    'gps_map' => $driver['gps_map'] ?? null,
                    'tipe_layanan_angkut' => $driver['tipe_layanan_angkut'] ?? null,
                    'area_dihandle' => $driver['area_dihandle'] ?? null,
                    'kapasitas_bobot_maksimal' => $driver['kapasitas_bobot_maksimal'] ?? null,
                    'asuransi_barang' => $driver['asuransi_barang'] ?? null,
                    'biaya_khusus_kecelakaan' => $driver['biaya_khusus_kecelakaan'] ?? 0,
                    'created_by' => auth()->user()->full_name ?? null,
                    'created_by_user_id' => auth()->id() ?? null,
                    'updated_by' => auth()->user()->full_name ?? null,
                    'updated_by_user_id' => auth()->id() ?? null,
                ];
            }, $driversData);

            $quotation->quotationDrivers()->createMany($formattedDrivers);
        }
    }
}
